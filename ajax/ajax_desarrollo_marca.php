<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config/database.php';

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
            // Join with form_templates to optionally show the form name
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

            if ($role_id == 1) {
                $stmt = $db->query("SELECT * FROM brand_projects ORDER BY created_at DESC");
            } else {
                $stmt = $db->prepare("
                    SELECT p.* FROM brand_projects p
                    JOIN brand_project_users pu ON p.id = pu.project_id
                    WHERE pu.user_id = ?
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([$user_id]);
            }
            
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch tags for each project
            foreach ($projects as &$project) {
                $stmtTags = $db->prepare("
                    SELECT t.* FROM brand_tags t 
                    JOIN brand_project_tags pt ON t.id = pt.tag_id 
                    WHERE pt.project_id = ?
                ");
                $stmtTags->execute([$project['id']]);
                $project['tags'] = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

                $stmtUsers = $db->prepare("
                    SELECT u.id, u.name, u.avatar FROM users u
                    JOIN brand_project_users pu ON u.id = pu.user_id
                    WHERE pu.project_id = ?
                ");
                $stmtUsers->execute([$project['id']]);
                $project['assigned_users'] = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

                // Fetch tasks and subtasks to calculate progress scale
                $stmtTasks = $db->prepare("
                    SELECT t.id, t.status,
                           COUNT(st.id) as total_subtasks,
                           SUM(CASE WHEN st.completed = 1 THEN 1 ELSE 0 END) as completed_subtasks
                    FROM brand_tasks t
                    JOIN brand_task_groups g ON t.group_id = g.id
                    LEFT JOIN brand_subtasks st ON st.task_id = t.id
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
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $client_name = $_POST['client_name'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $drive_folder_url = $_POST['drive_folder_url'] ?? '';
        $drive_folder_id = $_POST['drive_folder_id'] ?? '';
        $tags = isset($_POST['tags']) ? json_decode($_POST['tags'], true) : [];
        $assigned_users = isset($_POST['assigned_users']) ? json_decode($_POST['assigned_users'], true) : [];
        $form_submission_id = !empty($_POST['form_submission_id']) ? intval($_POST['form_submission_id']) : null;

        // Handle file uploads
        $cover_image = $_POST['existing_covers'] ?? '';
        if (isset($_FILES['cover_files'])) {
            $upload_dir = '../uploads/brand/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $uploaded_urls = [];
            foreach ($_FILES['cover_files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['cover_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = time() . '_' . basename($_FILES['cover_files']['name'][$key]);
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($tmp_name, $target)) {
                        $uploaded_urls[] = 'uploads/brand/' . $filename;
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
                $stmt = $db->prepare("UPDATE brand_projects SET 
                    form_submission_id = ?, title = ?, description = ?, client_name = ?, status = ?, cover_image = ?, start_date = ?, due_date = ?, drive_folder_url = ?, drive_folder_id = ? 
                    WHERE id = ?");
                $stmt->execute([$form_submission_id, $title, $description, $client_name, $status, $cover_image, $start_date, $due_date, $drive_folder_url, $drive_folder_id, $id]);
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO brand_projects 
                    (form_submission_id, title, description, client_name, status, cover_image, start_date, due_date, drive_folder_url, drive_folder_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$form_submission_id, $title, $description, $client_name, $status, $cover_image, $start_date, $due_date, $drive_folder_url, $drive_folder_id]);
                $id = $db->lastInsertId();
            }

            // Sync tags
            $db->prepare("DELETE FROM brand_project_tags WHERE project_id = ?")->execute([$id]);
            if (!empty($tags)) {
                $stmtTag = $db->prepare("INSERT INTO brand_project_tags (project_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmtTag->execute([$id, $tag_id]);
                }
            }

            // Sync assigned users
            $db->prepare("DELETE FROM brand_project_users WHERE project_id = ?")->execute([$id]);
            if (!empty($assigned_users)) {
                $stmtUser = $db->prepare("INSERT INTO brand_project_users (project_id, user_id) VALUES (?, ?)");
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
            $stmt = $db->prepare("DELETE FROM brand_projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_tags':
        try {
            $stmt = $db->query("SELECT * FROM brand_tags ORDER BY name ASC");
            $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'tags' => $tags]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save_tag':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = $_POST['name'] ?? '';
        $color = $_POST['color'] ?? '#6366f1';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE brand_tags SET name = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $color, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO brand_tags (name, color) VALUES (?, ?)");
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
            $stmt = $db->prepare("DELETE FROM brand_tags WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'search_clients':
        $query = $_POST['query'] ?? '';
        try {
            $stmt = $db->prepare("SELECT id, name, email, phone, business_name, avatar FROM clients WHERE name LIKE ? OR business_name LIKE ? OR email LIKE ? ORDER BY name ASC LIMIT 15");
            $like = '%' . $query . '%';
            $stmt->execute([$like, $like, $like]);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'clients' => $clients]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'change_status':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = $_POST['status'] ?? 'Active';
        try {
            $stmt = $db->prepare("UPDATE brand_projects SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_project_tasks':
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        try {
            $stmt = $db->prepare("SELECT * FROM brand_task_groups WHERE project_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$project_id]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($groups as &$group) {
                $stmtTasks = $db->prepare("SELECT * FROM brand_tasks WHERE group_id = ? ORDER BY sort_order ASC");
                $stmtTasks->execute([$group['id']]);
                $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($tasks as &$task) {
                    $stmtSub = $db->prepare("SELECT * FROM brand_subtasks WHERE task_id = ? ORDER BY sort_order ASC, id ASC");
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
                $stmt = $db->prepare("UPDATE brand_task_groups SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $db->prepare("SELECT MAX(sort_order) FROM brand_task_groups WHERE project_id = ?");
                $stmt->execute([$project_id]);
                $maxSort = intval($stmt->fetchColumn());
                
                $stmt = $db->prepare("INSERT INTO brand_task_groups (project_id, name, sort_order) VALUES (?, ?, ?)");
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
            $stmt = $db->prepare("SELECT id FROM brand_tasks WHERE group_id = ?");
            $stmt->execute([$id]);
            $taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($taskIds)) {
                $in = implode(',', array_fill(0, count($taskIds), '?'));
                $db->prepare("DELETE FROM brand_subtasks WHERE task_id IN ($in)")->execute($taskIds);
            }
            $db->prepare("DELETE FROM brand_tasks WHERE group_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM brand_task_groups WHERE id = ?")->execute([$id]);
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
                $stmt = $db->prepare("UPDATE brand_tasks SET title = ?, description = ?, status = ?, start_date = ?, due_date = ?, tags = ?, assigned_users = ?, attachments = ? WHERE id = ?");
                $stmt->execute([$title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $attachments, $id]);
            } else {
                $stmt = $db->prepare("SELECT MAX(sort_order) FROM brand_tasks WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $maxSort = intval($stmt->fetchColumn());
                
                $stmt = $db->prepare("INSERT INTO brand_tasks (group_id, title, description, status, start_date, due_date, tags, assigned_users, attachments, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$group_id, $title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $attachments, $maxSort + 1]);
                $id = $db->lastInsertId();
            }

            // Sync subtasks
            $db->prepare("DELETE FROM brand_subtasks WHERE task_id = ?")->execute([$id]);
            if (!empty($subtasks)) {
                $stmtSub = $db->prepare("INSERT INTO brand_subtasks (task_id, title, description, completed, sort_order) VALUES (?, ?, ?, ?, ?)");
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

        // Get project drive folder ID if exists
        $stmt = $db->prepare("SELECT drive_folder_id, drive_folder_url FROM brand_projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        $targetFolderId = $project['drive_folder_id'] ?? null;
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
            $upload_dir = '../uploads/brand_tasks/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $fileName);
            $target = $upload_dir . $uniqueName;
            if (move_uploaded_file($fileTmp, $target)) {
                $fileUrl = 'uploads/brand_tasks/' . $uniqueName;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar archivo en el servidor local.']);
                exit;
            }
        }

        $attachmentData = [
            'id' => $fileId ?: uniqid('file_'),
            'name' => $fileName,
            'size' => $fileSize,
            'ext' => $ext,
            'url' => $fileUrl,
            'drive' => $driveUploaded,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];

        echo json_encode([
            'success' => true,
            'attachment' => $attachmentData
        ]);
        break;

    case 'toggle_subtask':
        $subtask_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $completed = isset($_POST['completed']) ? intval($_POST['completed']) : 0;
        try {
            $stmt = $db->prepare("UPDATE brand_subtasks SET completed = ? WHERE id = ?");
            $stmt->execute([$completed, $subtask_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_task':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
            $db->prepare("DELETE FROM brand_subtasks WHERE task_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM brand_tasks WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'reorder_groups':
        $orders = isset($_POST['orders']) ? json_decode($_POST['orders'], true) : [];
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE brand_task_groups SET sort_order = ? WHERE id = ?");
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
            $stmt = $db->prepare("UPDATE brand_tasks SET group_id = ?, sort_order = ? WHERE id = ?");
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
            $stmt = $db->query("SELECT id, name, description, template_data, created_at FROM brand_group_templates ORDER BY id ASC");
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
                $stmt = $db->prepare("UPDATE brand_group_templates SET name = ?, description = ?, template_data = ? WHERE id = ?");
                $stmt->execute([$name, $description, $template_data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO brand_group_templates (name, description, template_data) VALUES (?, ?, ?)");
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
            $stmt = $db->prepare("DELETE FROM brand_group_templates WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM brand_group_templates WHERE id = ?");
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
            $stmtMax = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM brand_task_groups WHERE project_id = ?");
            $stmtMax->execute([$project_id]);
            $currentOrder = intval($stmtMax->fetchColumn());

            // Prepare tag cache
            $existingTagsStmt = $db->query("SELECT id, name FROM brand_tags");
            $existingTags = [];
            while ($row = $existingTagsStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingTags[strtolower($row['name'])] = $row['id'];
            }

            $insertGroupStmt = $db->prepare("INSERT INTO brand_task_groups (project_id, name, sort_order) VALUES (?, ?, ?)");
            $insertTaskStmt = $db->prepare("INSERT INTO brand_tasks (group_id, title, description, status, tags, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $insertSubtaskStmt = $db->prepare("INSERT INTO brand_subtasks (task_id, title, description, completed, sort_order) VALUES (?, ?, ?, ?, ?)");
            $insertNewTagStmt = $db->prepare("INSERT INTO brand_tags (name, color) VALUES (?, ?)");

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
                                    $insertNewTagStmt->execute([trim($tagName), '#6366f1']);
                                    $newTagId = $db->lastInsertId();
                                    $existingTags[$tagKey] = $newTagId;
                                }
                                $taskTagsArr[] = ['value' => trim($tagName), 'color' => '#6366f1'];
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
