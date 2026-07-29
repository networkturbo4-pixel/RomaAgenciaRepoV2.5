<?php
// ajax/ajax_desarrollo_marca.php
session_start();
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
            $stmt = $db->prepare("SELECT id, name FROM clients WHERE name LIKE ? LIMIT 15");
            $stmt->execute(['%' . $query . '%']);
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
                $group['tasks'] = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
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
        
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE brand_tasks SET title = ?, description = ?, status = ?, start_date = ?, due_date = ?, tags = ?, assigned_users = ? WHERE id = ?");
                $stmt->execute([$title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $id]);
            } else {
                $stmt = $db->prepare("SELECT MAX(sort_order) FROM brand_tasks WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $maxSort = intval($stmt->fetchColumn());
                
                $stmt = $db->prepare("INSERT INTO brand_tasks (group_id, title, description, status, start_date, due_date, tags, assigned_users, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$group_id, $title, $description, $status, $start_date, $due_date, $tags, $assigned_users, $maxSort + 1]);
                $id = $db->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_task':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        try {
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

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>
