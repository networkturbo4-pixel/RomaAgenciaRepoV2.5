<?php
// ajax/ajax_audiovisual.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'get_system_users':
        try {
            $stmt = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
            echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_form_submissions':
        try {
            $stmt = $db->query("
                SELECT fs.id, fs.correlativo, fs.respondent_name, ft.title as form_name 
                FROM form_submissions fs
                LEFT JOIN form_templates ft ON fs.template_id = ft.id
                ORDER BY fs.created_at DESC
            ");
            echo json_encode(['success' => true, 'submissions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_projects':
        try {
            $user_id = $_SESSION['user_id'];
            $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmtRole->execute([$user_id]);
            $role_id = $stmtRole->fetchColumn();

            $baseSql = "
                SELECT p.*, 
                       c.name as client_db_name, 
                       c.avatar as client_db_avatar, 
                       c.business_name as client_business_name,
                       c.drive_folder_id as client_drive_folder_id,
                       wo.correlativo as work_order_correlativo, 
                       wo.brand_name as work_order_brand
                FROM audiovisual_projects p
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN work_orders wo ON p.work_order_id = wo.id
            ";

            if ($role_id == 1) {
                $stmt = $db->query($baseSql . " ORDER BY p.created_at DESC");
            } else {
                $stmt = $db->prepare($baseSql . "
                    JOIN audiovisual_project_users pu ON p.id = pu.project_id
                    WHERE pu.user_id = ?
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([$user_id]);
            }
            
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch tags and assigned users for each project
            foreach ($projects as &$project) {
                $stmtTags = $db->prepare("
                    SELECT t.* FROM audiovisual_tags t 
                    JOIN audiovisual_project_tags pt ON t.id = pt.tag_id 
                    WHERE pt.project_id = ?
                ");
                $stmtTags->execute([$project['id']]);
                $project['tags'] = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

                $stmtUsers = $db->prepare("
                    SELECT u.id, u.name, u.avatar FROM users u
                    JOIN audiovisual_project_users pu ON u.id = pu.user_id
                    WHERE pu.project_id = ?
                ");
                $stmtUsers->execute([$project['id']]);
                $project['assigned_users'] = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

                // Fetch tasks and subtasks to calculate progress scale
                $stmtTasks = $db->prepare("
                    SELECT t.id, t.status,
                           COUNT(st.id) as total_subtasks,
                           SUM(CASE WHEN st.completed = 1 THEN 1 ELSE 0 END) as completed_subtasks
                    FROM audiovisual_tasks t
                    JOIN audiovisual_task_groups g ON t.group_id = g.id
                    LEFT JOIN audiovisual_subtasks st ON st.task_id = t.id
                    WHERE g.project_id = ?
                    GROUP BY t.id, t.status
                ");
                $stmtTasks->execute([$project['id']]);
                $projectTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

                $totalTasks = count($projectTasks);
                $completedTasks = 0;
                $totalSubtasks = 0;
                $completedSubtasks = 0;
                $totalScore = 0;

                foreach ($projectTasks as $pt) {
                    $subCount = intval($pt['total_subtasks']);
                    $subDone = intval($pt['completed_subtasks']);
                    $totalSubtasks += $subCount;
                    $completedSubtasks += $subDone;

                    if ($pt['status'] === 'completed') {
                        $completedTasks++;
                        $totalScore += 1.0;
                    } else if ($subCount > 0) {
                        $taskScore = $subDone / $subCount;
                        $totalScore += $taskScore;
                        if ($subDone === $subCount && $subCount > 0) {
                            $completedTasks++;
                        }
                    } else if ($pt['status'] === 'review') {
                        $totalScore += 0.75;
                    } else if ($pt['status'] === 'in_progress') {
                        $totalScore += 0.5;
                    }
                }

                $progress = 0;
                if ($project['status'] === 'Completed') {
                    $progress = 100;
                } else if ($totalTasks > 0) {
                    $progress = round(($totalScore / $totalTasks) * 100);
                }

                $project['progress'] = min(100, max(0, $progress));
                $project['total_tasks'] = $totalTasks;
                $project['completed_tasks'] = $completedTasks;
                $project['total_subtasks'] = $totalSubtasks;
                $project['completed_subtasks'] = $completedSubtasks;
            }

            echo json_encode(['success' => true, 'projects' => $projects]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_project':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $client_name = trim($_POST['client_name'] ?? '');
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        $work_order_id = !empty($_POST['work_order_id']) ? intval($_POST['work_order_id']) : null;
        $status = $_POST['status'] ?? 'Active';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $drive_folder_url = $_POST['drive_folder_url'] ?? '';
        $drive_folder_id = $_POST['drive_folder_id'] ?? '';
        $drive_subfolders_json = !empty($_POST['drive_subfolders_json']) ? $_POST['drive_subfolders_json'] : null;
        $tags = isset($_POST['tags']) ? json_decode($_POST['tags'], true) : [];
        $assigned_users = isset($_POST['assigned_users']) ? json_decode($_POST['assigned_users'], true) : [];
        $form_submission_id = !empty($_POST['form_submission_id']) ? intval($_POST['form_submission_id']) : null;

        // Handle file uploads
        $cover_image = $_POST['existing_covers'] ?? '';
        if (isset($_FILES['cover_files'])) {
            $upload_dir = '../uploads/audiovisual/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $uploaded_urls = [];
            foreach ($_FILES['cover_files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['cover_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['cover_files']['name'][$key], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
                        continue;
                    }
                    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($tmp_name, $target)) {
                        $uploaded_urls[] = 'uploads/audiovisual/' . $filename;
                    }
                }
            }
            if (!empty($uploaded_urls)) {
                $cover_image = implode(',', $uploaded_urls);
            }
        }

        try {
            $db->beginTransaction();

            if ($id > 0) {
                // Update
                $stmt = $db->prepare("UPDATE audiovisual_projects SET 
                    form_submission_id = ?, client_id = ?, work_order_id = ?, title = ?, description = ?, client_name = ?, status = ?, cover_image = ?, start_date = ?, due_date = ?, drive_folder_url = ?, drive_folder_id = ?, drive_subfolders_json = ? 
                    WHERE id = ?");
                $stmt->execute([$form_submission_id, $client_id, $work_order_id, $title, $description, $client_name, $status, $cover_image, $start_date, $due_date, $drive_folder_url, $drive_folder_id, $drive_subfolders_json, $id]);
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO audiovisual_projects 
                    (form_submission_id, client_id, work_order_id, title, description, client_name, status, cover_image, start_date, due_date, drive_folder_url, drive_folder_id, drive_subfolders_json) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$form_submission_id, $client_id, $work_order_id, $title, $description, $client_name, $status, $cover_image, $start_date, $due_date, $drive_folder_url, $drive_folder_id, $drive_subfolders_json]);
                $id = $db->lastInsertId();
            }

            // Sync tags
            $db->prepare("DELETE FROM audiovisual_project_tags WHERE project_id = ?")->execute([$id]);
            if (!empty($tags)) {
                $stmtTag = $db->prepare("INSERT INTO audiovisual_project_tags (project_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmtTag->execute([$id, $tag_id]);
                }
            }

            // Sync assigned users
            $db->prepare("DELETE FROM audiovisual_project_users WHERE project_id = ?")->execute([$id]);
            if (!empty($assigned_users)) {
                $stmtUser = $db->prepare("INSERT INTO audiovisual_project_users (project_id, user_id) VALUES (?, ?)");
                foreach ($assigned_users as $uid) {
                    $stmtUser->execute([$id, $uid]);
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_project':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            $stmt = $db->prepare("DELETE FROM audiovisual_projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_tags':
        try {
            $stmt = $db->query("SELECT * FROM audiovisual_tags ORDER BY name ASC");
            $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'tags' => $tags]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_tag':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = $_POST['name'] ?? '';
        $color = $_POST['color'] ?? '#f59e0b';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE audiovisual_tags SET name = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $color, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO audiovisual_tags (name, color) VALUES (?, ?)");
                $stmt->execute([$name, $color]);
                $id = $db->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'color' => $color]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_tag':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            $stmt = $db->prepare("DELETE FROM audiovisual_tags WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'search_clients':
        $query = $_POST['query'] ?? '';
        try {
            $stmt = $db->prepare("SELECT id, name, email, phone, business_name, avatar, drive_folder_id FROM clients WHERE name LIKE ? OR business_name LIKE ? OR email LIKE ? ORDER BY name ASC LIMIT 20");
            $like = '%' . $query . '%';
            $stmt->execute([$like, $like, $like]);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'clients' => $clients]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_work_orders':
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $client_name = trim($_POST['client_name'] ?? '');
        try {
            $sql = "SELECT id, correlativo, brand_name, data, created_at FROM work_orders WHERE is_archived = 0";
            $params = [];
            if ($client_name !== '') {
                $sql .= " AND (brand_name LIKE ? OR data LIKE ?)";
                $params[] = "%$client_name%";
                $params[] = "%$client_name%";
            }
            $sql .= " ORDER BY id DESC LIMIT 50";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $orders = [];
            foreach ($rawOrders as $wo) {
                $data = json_decode($wo['data'], true) ?: [];
                $clientVal = $data['cliente'] ?? $wo['brand_name'] ?? 'Cliente';
                $brandVal = $data['marca'] ?? $wo['brand_name'] ?? '';
                $serviceVal = $data['servicio'] ?? $data['procesos'] ?? '';
                
                $orders[] = [
                    'id' => (int)$wo['id'],
                    'correlativo' => $wo['correlativo'],
                    'brand_name' => $wo['brand_name'],
                    'client_name' => is_string($clientVal) ? $clientVal : ($clientVal['name'] ?? 'Cliente'),
                    'brand' => is_string($brandVal) ? $brandVal : '',
                    'display' => $wo['correlativo'] . ' • ' . ($wo['brand_name'] ?: 'Sin marca') . ($clientVal ? " ({$clientVal})" : '')
                ];
            }

            echo json_encode(['success' => true, 'orders' => $orders]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'change_status':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = $_POST['status'] ?? 'Active';
        try {
            $stmt = $db->prepare("UPDATE audiovisual_projects SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_project_tasks':
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        try {
            $stmt = $db->prepare("SELECT * FROM audiovisual_task_groups WHERE project_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$project_id]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($groups as &$group) {
                $stmtTasks = $db->prepare("SELECT * FROM audiovisual_tasks WHERE group_id = ? ORDER BY sort_order ASC");
                $stmtTasks->execute([$group['id']]);
                $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($tasks as &$task) {
                    $stmtSub = $db->prepare("SELECT * FROM audiovisual_subtasks WHERE task_id = ? ORDER BY sort_order ASC, id ASC");
                    $stmtSub->execute([$task['id']]);
                    $task['subtasks'] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
                }
                $group['tasks'] = $tasks;
            }
            echo json_encode(['success' => true, 'groups' => $groups]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_task_group':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $name = $_POST['name'] ?? '';
        
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE audiovisual_task_groups SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $db->prepare("SELECT MAX(sort_order) FROM audiovisual_task_groups WHERE project_id = ?");
                $stmt->execute([$project_id]);
                $maxSort = intval($stmt->fetchColumn());
                
                $stmt = $db->prepare("INSERT INTO audiovisual_task_groups (project_id, name, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$project_id, $name, $maxSort + 1]);
                $id = $db->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_task_group':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            // Delete subtasks for each task in group
            $stmt = $db->prepare("SELECT id FROM audiovisual_tasks WHERE group_id = ?");
            $stmt->execute([$id]);
            $taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($taskIds)) {
                $in = implode(',', array_fill(0, count($taskIds), '?'));
                $db->prepare("DELETE FROM audiovisual_subtasks WHERE task_id IN ($in)")->execute($taskIds);
            }
            $db->prepare("DELETE FROM audiovisual_tasks WHERE group_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM audiovisual_task_groups WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_task':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $tags = $_POST['tags'] ?? '[]';
        $assigned_users = $_POST['assigned_users'] ?? '[]';
        $attachments = $_POST['attachments'] ?? '[]';
        $subtasks_json = $_POST['subtasks'] ?? '[]';
        $subtasks = json_decode($subtasks_json, true) ?: [];
        
        try {
            $db->beginTransaction();
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE audiovisual_tasks SET title = ?, description = ?, status = ?, start_date = ?, due_date = ?, tags = ?, assigned_users = ?, attachments = ? WHERE id = ?");
                $stmt->execute([$title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $attachments, $id]);
            } else {
                $stmt = $db->prepare("SELECT MAX(sort_order) FROM audiovisual_tasks WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $maxSort = intval($stmt->fetchColumn());
                
                $stmt = $db->prepare("INSERT INTO audiovisual_tasks (group_id, title, description, status, start_date, due_date, tags, assigned_users, attachments, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$group_id, $title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $attachments, $maxSort + 1]);
                $id = $db->lastInsertId();
            }

            // Sync subtasks
            $db->prepare("DELETE FROM audiovisual_subtasks WHERE task_id = ?")->execute([$id]);
            if (!empty($subtasks)) {
                $stmtSub = $db->prepare("INSERT INTO audiovisual_subtasks (task_id, title, description, completed, sort_order) VALUES (?, ?, ?, ?, ?)");
                $sort = 1;
                foreach ($subtasks as $st) {
                    $stTitle = trim($st['title'] ?? '');
                    if (!empty($stTitle)) {
                        $stDesc = trim($st['description'] ?? '');
                        $stDone = !empty($st['completed']) ? 1 : 0;
                        $stmtSub->execute([$id, $stTitle, $stDesc, $stDone, $sort++]);
                    }
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'upload_task_attachment':
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo o hubo un error al subirlo.']);
            exit;
        }

        $file = $_FILES['file'];
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'inc', 'pl', 'py', 'cgi', 'sh', 'bash', 'exe', 'bat', 'cmd', 'js', 'html', 'htm'];
        if (in_array($ext, $dangerousExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido por razones de seguridad.']);
            exit;
        }

        // Get project drive folder ID and subfolders if exists
        $subfolder_type = trim($_POST['subfolder_type'] ?? 'referencias');
        $stmt = $db->prepare("SELECT drive_folder_id, drive_folder_url, drive_subfolders_json FROM audiovisual_projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        $targetFolderId = $project['drive_folder_id'] ?? null;
        if (!empty($project['drive_subfolders_json'])) {
            $subs = json_decode($project['drive_subfolders_json'], true);
            if (is_array($subs) && !empty($subs[$subfolder_type]['id'])) {
                $targetFolderId = $subs[$subfolder_type]['id'];
            }
        }
        $driveUploaded = false;
        $fileUrl = '';
        $fileId = '';

        // Try Google Drive upload if helper and folder exist
        require_once __DIR__ . '/../includes/GoogleDriveHelper.php';
        $driveHelper = new GoogleDriveHelper();
        
        if ($driveHelper->isConfigured() && !empty($targetFolderId)) {
            $uploadResult = $driveHelper->uploadFile($fileTmp, $fileName, $targetFolderId);
            if ($uploadResult && !empty($uploadResult['id'])) {
                $driveUploaded = true;
                $fileId = $uploadResult['id'];
                $fileUrl = $uploadResult['webViewLink'] ?? ('ajax/drive_proxy.php?id=' . $fileId);
            }
        }

        // Fallback or local copy if Drive not configured or failed
        if (!$driveUploaded) {
            $upload_dir = '../uploads/audiovisual_tasks/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $fileName);
            $target = $upload_dir . $uniqueName;
            if (move_uploaded_file($fileTmp, $target)) {
                $fileUrl = 'uploads/audiovisual_tasks/' . $uniqueName;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar archivo en el servidor local.']);
                exit;
            }
        }

        $folderLabels = [
            'referencias' => 'Referencias Audiovisuales',
            'videos_terminados' => 'Videos Terminados',
            'empaquetados' => 'Empaquetados'
        ];

        $attachmentData = [
            'id' => $fileId ?: uniqid('file_'),
            'name' => $fileName,
            'size' => $fileSize,
            'ext' => $ext,
            'url' => $fileUrl,
            'drive' => $driveUploaded,
            'folder_type' => $subfolder_type,
            'folder_label' => $folderLabels[$subfolder_type] ?? 'General',
            'uploaded_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success' => true,
            'attachment' => $attachmentData
        ]);
        break;

    case 'sync_drive_folders':
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $project_title = trim($_POST['project_title'] ?? ($_POST['title'] ?? ''));
        $client_name = trim($_POST['client_name'] ?? '');
        $parent_folder_id = trim($_POST['parent_folder_id'] ?? '');

        try {
            require_once __DIR__ . '/../includes/GoogleDriveHelper.php';
            $driveHelper = new GoogleDriveHelper();

            if (!$driveHelper->isConfigured()) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Google Drive no está conectado o configurado en el sistema.'
                ]);
                break;
            }

            $currentDriveId = '';
            if ($project_id > 0) {
                $stmt = $db->prepare("SELECT title, client_name, drive_folder_id, drive_folder_url, drive_subfolders_json FROM audiovisual_projects WHERE id = ?");
                $stmt->execute([$project_id]);
                $proj = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($proj) {
                    $project_title = $project_title ?: $proj['title'];
                    $client_name = $client_name ?: $proj['client_name'];
                    $currentDriveId = $proj['drive_folder_id'];
                }
            }

            // 1. Obtener o crear carpeta principal del proyecto
            $mainFolderId = $currentDriveId;
            if (empty($mainFolderId)) {
                $folderName = "[AV] " . ($project_title ?: 'Producción Audiovisual') . ($client_name ? " - {$client_name}" : "");
                $mainFolderId = $driveHelper->createFolder($folderName, $parent_folder_id ?: null);
                if (!$mainFolderId) {
                    throw new Exception("No se pudo crear la carpeta principal del proyecto en Drive.");
                }
            }

            // 2. Crear las 3 subcarpetas específicas:
            // 01. Referencias Audiovisuales
            // 02. Videos Terminados
            // 03. Empaquetados de Videos
            $subfolderSpecs = [
                'referencias' => '01. Referencias Audiovisuales',
                'videos_terminados' => '02. Videos Terminados',
                'empaquetados' => '03. Empaquetados de Videos'
            ];

            $existingFolders = $driveHelper->listFolders($mainFolderId) ?: [];
            $existingMap = [];
            foreach ($existingFolders as $ef) {
                $existingMap[$ef->getName()] = [
                    'id' => $ef->getId(),
                    'url' => $ef->getWebViewLink() ?: "https://drive.google.com/drive/folders/{$ef->getId()}"
                ];
            }

            $subfoldersData = [];
            foreach ($subfolderSpecs as $key => $subName) {
                if (isset($existingMap[$subName])) {
                    $subfoldersData[$key] = $existingMap[$subName];
                } else {
                    $newSubId = $driveHelper->createFolder($subName, $mainFolderId);
                    if ($newSubId) {
                        $subfoldersData[$key] = [
                            'id' => $newSubId,
                            'url' => "https://drive.google.com/drive/folders/{$newSubId}"
                        ];
                    }
                }
            }

            $mainFolderUrl = "https://drive.google.com/drive/folders/{$mainFolderId}";
            $subfoldersJson = json_encode($subfoldersData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($project_id > 0) {
                $upd = $db->prepare("UPDATE audiovisual_projects SET drive_folder_id = ?, drive_folder_url = ?, drive_subfolders_json = ? WHERE id = ?");
                $upd->execute([$mainFolderId, $mainFolderUrl, $subfoldersJson, $project_id]);
            }

            echo json_encode([
                'success' => true,
                'folder_id' => $mainFolderId,
                'folder_url' => $mainFolderUrl,
                'subfolders' => $subfoldersData
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'romita_automate_task':
        $task_type = trim($_POST['task_type'] ?? 'generate_subtasks');
        $task_title = trim($_POST['task_title'] ?? '');
        $phase_name = trim($_POST['phase_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $client_name = trim($_POST['client_name'] ?? '');
        $project_title = trim($_POST['project_title'] ?? '');
        $custom_prompt = trim($_POST['custom_prompt'] ?? '');

        try {
            // Obtener llaves API de settings
            $stmtKeys = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('groq_api_key', 'gemini_api_key')");
            $apiKeys = $stmtKeys->fetchAll(PDO::FETCH_KEY_PAIR);
            $groqKey = trim($apiKeys['groq_api_key'] ?? '');
            $geminiKey = trim($apiKeys['gemini_api_key'] ?? '');

            if (empty($groqKey) && empty($geminiKey)) {
                echo json_encode(['success' => false, 'error' => 'No hay llaves de IA (Groq o Gemini) configuradas en el sistema.']);
                break;
            }

            $systemPrompt = "Eres Romita, la directora creativa y productora audiovisual ejecutiva de Roma Agencia. Eres moderna, sumamente técnica y resolutiva en producción audiovisual comercial (Reels 9:16, spots de alta retención, videos corporativos, cinematografía, iluminación, montaje, postproducción y masterización).";
            
            $userPrompt = "Contexto de la Producción:\n";
            if ($project_title) $userPrompt .= "- Proyecto: {$project_title}\n";
            if ($client_name) $userPrompt .= "- Cliente: {$client_name}\n";
            if ($phase_name) $userPrompt .= "- Fase / Etapa: {$phase_name}\n";
            if ($task_title) $userPrompt .= "- Tarea: {$task_title}\n";
            if ($description) $userPrompt .= "- Descripción actual: {$description}\n";

            if ($task_type === 'generate_subtasks') {
                $userPrompt .= "\nObjetivo: Genera entre 4 y 7 subtareas técnicas secuenciales y accionables para ejecutar con éxito esta tarea audiovisual.\n"
                    . "IMPORTANTE: Responde ÚNICAMENTE un arreglo JSON válido (sin formato markdown ```json, solo el JSON puro) con este esquema exacto:\n"
                    . "[{\"title\": \"Nombre de la subtarea técnica\", \"description\": \"Detalle conciso de ejecución\"}]";
            } elseif ($task_type === 'write_script') {
                $userPrompt .= "\nObjetivo: Redacta una propuesta de guion técnico y literario audiovisual para esta tarea. Incluye:\n"
                    . "1. ⚡ Gancho de Atención (0-3 segundos para frenar el scroll)\n"
                    . "2. 💡 Desarrollo & Retención (Storytelling ágil, valor o demostración)\n"
                    . "3. 🎯 Llamado a la Acción (CTA claro)\n"
                    . "4. 🎬 Indicaciones visuales y de audio (planos de cámara, B-Roll, SFX y música).\n"
                    . ($custom_prompt ? "\nInstrucciones adicionales del usuario: {$custom_prompt}" : "");
            } elseif ($task_type === 'tech_specs') {
                $userPrompt .= "\nObjetivo: Define las especificaciones técnicas recomendadas para rodaje y postproducción de esta tarea:\n"
                    . "- Resolución y Aspect Ratio (16:9 4K UHD o 9:16 Vertical 1080x1920)\n"
                    . "- Cuadros por segundo (24fps / 60fps)\n"
                    . "- Perfil de Color & Códec (LOG, Rec.709, ProRes 422, H.264)\n"
                    . "- Setup de Cámaras, Iluminación y Microfonía\n"
                    . "- Formatos de exportación y entrega final.\n"
                    . ($custom_prompt ? "\nInstrucciones adicionales: {$custom_prompt}" : "");
            } else {
                $userPrompt .= "\nSolicitud personalizada del usuario: " . ($custom_prompt ?: "Dame recomendaciones y optimizaciones audiovisuales para esta tarea.");
            }

            // Inferencia: Groq prioritario, Gemini como fallback
            $aiText = '';
            if (!empty($groqKey)) {
                $groqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b'];
                foreach ($groqModels as $gModel) {
                    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $groqKey,
                            'Content-Type: application/json'
                        ],
                        CURLOPT_POSTFIELDS => json_encode([
                            'model' => $gModel,
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'user', 'content' => $userPrompt]
                            ],
                            'temperature' => 0.5,
                            'max_tokens' => 1000
                        ]),
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_TIMEOUT => 25
                    ]);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && !empty($res)) {
                        $data = json_decode($res, true);
                        $aiText = trim($data['choices'][0]['message']['content'] ?? '');
                        if (!empty($aiText)) break;
                    }
                }
            }

            // Fallback a Gemini si Groq no respondió
            if (empty($aiText) && !empty($geminiKey)) {
                $geminiModels = ['gemini-3.8-flash', 'gemini-flash-latest', 'gemini-2.5-flash-lite'];
                foreach ($geminiModels as $gemModel) {
                    $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$gemModel}:generateContent?key=" . $geminiKey;
                    $ch = curl_init($geminiUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode([
                            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                            'contents' => [['parts' => [['text' => $userPrompt]]]]
                        ]),
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_TIMEOUT => 25
                    ]);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && !empty($res)) {
                        $data = json_decode($res, true);
                        $aiText = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
                        if (!empty($aiText)) break;
                    }
                }
            }

            if (empty($aiText)) {
                throw new Exception("No se obtuvo respuesta del motor de Romita AI. Intenta de nuevo.");
            }

            // Si es generate_subtasks, parsear JSON
            if ($task_type === 'generate_subtasks') {
                $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($aiText));
                $subtasks = json_decode($cleanJson, true);
                if (!is_array($subtasks)) {
                    if (preg_match('/\[.*\]/s', $cleanJson, $matches)) {
                        $subtasks = json_decode($matches[0], true);
                    }
                }
                if (!is_array($subtasks)) {
                    $subtasks = [
                        ['title' => 'Revisión y desglose de requerimientos', 'description' => 'Alinear objetivos clave'],
                        ['title' => 'Grabación / Selección de recursos visuales', 'description' => 'Captura de material'],
                        ['title' => 'Edición y ensamblaje de tomas', 'description' => 'Montaje rítmico'],
                        ['title' => 'Exportación y verificación de entrega', 'description' => 'Master final']
                    ];
                }
                echo json_encode([
                    'success' => true,
                    'subtasks' => $subtasks,
                    'raw' => $aiText
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'text' => $aiText
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'toggle_subtask':
        $subtask_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $completed = isset($_POST['completed']) ? intval($_POST['completed']) : 0;
        try {
            $stmt = $db->prepare("UPDATE audiovisual_subtasks SET completed = ? WHERE id = ?");
            $stmt->execute([$completed, $subtask_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_task':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            $db->prepare("DELETE FROM audiovisual_subtasks WHERE task_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM audiovisual_tasks WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'reorder_groups':
        $orders = isset($_POST['orders']) ? json_decode($_POST['orders'], true) : [];
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE audiovisual_task_groups SET sort_order = ? WHERE id = ?");
            foreach ($orders as $o) {
                $stmt->execute([$o['order'], $o['id']]);
            }
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'reorder_tasks':
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $orders = isset($_POST['orders']) ? json_decode($_POST['orders'], true) : [];
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE audiovisual_tasks SET group_id = ?, sort_order = ? WHERE id = ?");
            foreach ($orders as $o) {
                $stmt->execute([$group_id, $o['order'], $o['id']]);
            }
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_templates':
        try {
            $stmt = $db->query("SELECT id, name, description, template_data, created_at FROM audiovisual_group_templates ORDER BY id ASC");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'templates' => $templates]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_template':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $template_data = $_POST['template_data'] ?? '[]';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la plantilla es obligatorio']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE audiovisual_group_templates SET name = ?, description = ?, template_data = ? WHERE id = ?");
                $stmt->execute([$name, $description, $template_data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO audiovisual_group_templates (name, description, template_data) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $template_data]);
                $id = $db->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_template':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            $stmt = $db->prepare("DELETE FROM audiovisual_group_templates WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'apply_template':
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if ($project_id <= 0 || $template_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
            exit;
        }

        try {
            $stmt = $db->prepare("SELECT * FROM audiovisual_group_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $tmpl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tmpl) {
                echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada']);
                exit;
            }

            $groups = json_decode($tmpl['template_data'], true);
            if (!is_array($groups)) {
                echo json_encode(['success' => false, 'message' => 'Datos de plantilla corruptos']);
                exit;
            }

            $db->beginTransaction();

            // Find current highest sort order of groups in project
            $stmtMax = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM audiovisual_task_groups WHERE project_id = ?");
            $stmtMax->execute([$project_id]);
            $currentOrder = intval($stmtMax->fetchColumn());

            // Prepare tag cache
            $existingTagsStmt = $db->query("SELECT id, name FROM audiovisual_tags");
            $existingTags = [];
            while ($row = $existingTagsStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingTags[strtolower($row['name'])] = $row['id'];
            }

            $insertGroupStmt = $db->prepare("INSERT INTO audiovisual_task_groups (project_id, name, sort_order) VALUES (?, ?, ?)");
            $insertTaskStmt = $db->prepare("INSERT INTO audiovisual_tasks (group_id, title, description, status, tags, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $insertSubtaskStmt = $db->prepare("INSERT INTO audiovisual_subtasks (task_id, title, description, completed, sort_order) VALUES (?, ?, ?, ?, ?)");
            $insertNewTagStmt = $db->prepare("INSERT INTO audiovisual_tags (name, color) VALUES (?, ?)");

            foreach ($groups as $g) {
                $currentOrder++;
                $gName = $g['name'] ?? 'Nueva Fase';
                $insertGroupStmt->execute([$project_id, $gName, $currentOrder]);
                $groupId = $db->lastInsertId();

                $taskOrder = 0;
                if (!empty($g['tasks']) && is_array($g['tasks'])) {
                    foreach ($g['tasks'] as $t) {
                        $taskOrder++;
                        $tTitle = $t['title'] ?? 'Tarea';
                        $tDesc = $t['description'] ?? '';
                        $tStatus = $t['status'] ?? 'pending';
                        
                        // Format tags JSON
                        $taskTagsArr = [];
                        if (!empty($t['tags']) && is_array($t['tags'])) {
                            foreach ($t['tags'] as $tagName) {
                                $tagKey = strtolower(trim($tagName));
                                if (!isset($existingTags[$tagKey])) {
                                    $insertNewTagStmt->execute([trim($tagName), '#f59e0b']);
                                    $newTagId = $db->lastInsertId();
                                    $existingTags[$tagKey] = $newTagId;
                                }
                                $taskTagsArr[] = ['value' => trim($tagName), 'color' => '#f59e0b'];
                            }
                        }
                        $tagsJson = json_encode($taskTagsArr, JSON_UNESCAPED_UNICODE);

                        $insertTaskStmt->execute([$groupId, $tTitle, $tDesc, $tStatus, $tagsJson, $taskOrder]);
                        $taskId = $db->lastInsertId();

                        // Associate subtasks
                        if (!empty($t['subtasks']) && is_array($t['subtasks'])) {
                            $stOrder = 0;
                            foreach ($t['subtasks'] as $st) {
                                $stOrder++;
                                $stTitle = $st['title'] ?? '';
                                $stDesc = $st['description'] ?? '';
                                $stComp = !empty($st['completed']) ? 1 : 0;
                                if (!empty($stTitle)) {
                                    $insertSubtaskStmt->execute([$taskId, $stTitle, $stDesc, $stComp, $stOrder]);
                                }
                            }
                        }
                    }
                }
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>
