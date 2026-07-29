<?php
// modules/task_manager/ajax.php — Gestor de Tareas API
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

require_once '../../config/database.php';
try {
    require_once '../../includes/PushHelper.php';
} catch (Throwable $e) {}

$db = (new Database())->getConnection();
$action = $_POST['action_type'] ?? $_GET['action_type'] ?? '';
$userId = (int)$_SESSION['user_id'];
$userRoleId = (int)($_SESSION['role_id'] ?? 1); // fallback to 1

$isAdmin = false;
if ($userRoleId == 1) {
    $isAdmin = true;
} elseif (in_array('admin', $_SESSION['user_permissions'] ?? [])) {
    $isAdmin = true;
} elseif (strtolower($_SESSION['user_role'] ?? '') === 'administrador' || strtolower($_SESSION['user_role'] ?? '') === 'admin') {
    $isAdmin = true;
}

function getUserMap($db) {
    $stmt = $db->query("SELECT id, name, avatar, role_id FROM users ORDER BY name ASC");
    $map = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $map[$r['id']] = $r;
    return $map;
}

function checkOverdueTasks($db) {
    // Si due_date pasó y status es new o pending, cambia a overdue
    try {
        $db->query("UPDATE tm_tasks SET status = 'overdue' WHERE due_date IS NOT NULL AND due_date < NOW() AND status IN ('new', 'pending')");
    } catch(Throwable $e) {}
}

function processRecurringTasks($db) {
    // Simplificada: Busca plantillas que deban generarse hoy y que no se hayan generado
    // Para una implementación real se requiere comparar días o fechas
    try {
        $db->exec("
            INSERT INTO tm_tasks (title, description, priority, assigned_users, assigned_roles, tags, created_by, status)
            SELECT title, description, priority, assigned_users, assigned_roles, tags, created_by, 'new'
            FROM tm_recurring_templates
            WHERE (last_generated IS NULL OR DATE(last_generated) < CURDATE())
            AND recurrence_type = 'daily'
        ");
        $db->exec("UPDATE tm_recurring_templates SET last_generated = NOW() WHERE recurrence_type = 'daily' AND (last_generated IS NULL OR DATE(last_generated) < CURDATE())");
    } catch(Throwable $e) {}
}

if ($action === 'get_all_tasks') {
    checkOverdueTasks($db);
    processRecurringTasks($db);
    
    $filterUser = $_POST['filter_user'] ?? 'me';
    
    $userMap = getUserMap($db);
    $allTasks = [];
    
    try {
        // Build WHERE clause
        $where = "status != 'archived'";
        if (!$isAdmin) {
            // Empleado ve las asignadas a él o a su rol, o las que creó
            $where .= " AND (
                JSON_CONTAINS(assigned_users, '\"{$userId}\"') OR 
                JSON_CONTAINS(assigned_roles, '\"{$userRoleId}\"') OR 
                created_by = {$userId}
            )";
        }
        
        $stmtT = $db->query("SELECT t.*, u.name as creator_name FROM tm_tasks t LEFT JOIN users u ON t.created_by = u.id WHERE {$where} ORDER BY t.created_at DESC");
        $tasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);
        
        $tIds = array_column($tasks, 'id');
        $subStats = [];
        if (!empty($tIds)) {
            $in = implode(',', $tIds);
            $st = $db->query("SELECT task_id, COUNT(*) as total, SUM(is_completed) as completed FROM tm_subtasks WHERE task_id IN ($in) GROUP BY task_id");
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) $subStats[$r['task_id']] = ['total'=>(int)$r['total'],'completed'=>(int)$r['completed']];
        }
        
        foreach ($tasks as $t) {
            // resolve users
            $usersArr = json_decode($t['assigned_users']??'[]', true) ?: [];
            $aUsers = [];
            foreach ($usersArr as $uid) {
                if(isset($userMap[$uid])) {
                    $u = $userMap[$uid];
                    $aUsers[] = ['id'=>$uid, 'name'=>$u['name'], 'avatar'=>$u['avatar'], 'initial'=>strtoupper(substr($u['name'],0,1))];
                }
            }
            
            $allTasks[] = [
                'id' => (int)$t['id'],
                'title' => $t['title'],
                'description' => $t['description'] ?? '',
                'status' => $t['status'],
                'priority' => $t['priority'],
                'start_date' => $t['start_date'],
                'due_date' => $t['due_date'],
                'assigned_users' => $aUsers,
                'tags' => json_decode($t['tags']??'[]', true) ?: [],
                'created_by_name' => $t['creator_name'] ?? 'Sistema',
                'created_at' => $t['created_at'],
                'subtasks' => $subStats[$t['id']] ?? null
            ];
        }
        
        // Stats
        $stats = ['total'=>count($allTasks), 'new'=>0, 'pending'=>0, 'overdue'=>0, 'completed'=>0, 'approved'=>0];
        foreach($allTasks as $t) {
            if (isset($stats[$t['status']])) $stats[$t['status']]++;
        }
        
        echo json_encode(['success'=>true, 'tasks'=>$allTasks, 'stats'=>$stats]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'create_task') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $startDate = $_POST['start_date'] ?: null;
    $dueDate = $_POST['due_date'] ?: null;
    $assignedUsers = $_POST['assigned_users'] ?? '[]';
    $assignedRoles = $_POST['assigned_roles'] ?? '[]';
    $tags = $_POST['tags'] ?? '[]';

    $subtasksJson = $_POST['subtasks'] ?? '[]';

    if (!$title) { echo json_encode(['success'=>false, 'error'=>'El título es obligatorio']); exit; }

    try {
        $stmt = $db->prepare("INSERT INTO tm_tasks (title, description, priority, status, start_date, due_date, assigned_users, assigned_roles, tags, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $priority, $status, $startDate, $dueDate, $assignedUsers, $assignedRoles, $tags, $userId]);
        $taskId = $db->lastInsertId();

        // Insert subtasks
        $subtasksArr = json_decode($subtasksJson, true) ?: [];
        if (!empty($subtasksArr)) {
            $stmtSub = $db->prepare("INSERT INTO tm_subtasks (task_id, title) VALUES (?, ?)");
            foreach ($subtasksArr as $stTitle) {
                if (trim($stTitle)) {
                    $stmtSub->execute([$taskId, trim($stTitle)]);
                }
            }
        }

        echo json_encode(['success'=>true, 'task_id'=>$taskId]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'update_status') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    if (!$taskId || !$newStatus) { echo json_encode(['success'=>false]); exit; }
    
    if ($newStatus === 'approved' && !$isAdmin) {
        echo json_encode(['success'=>false, 'error'=>'Solo los administradores pueden aprobar tareas.']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE tm_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $taskId]);
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if (!$taskId) { echo json_encode(['success'=>false]); exit; }
    
    // Si no es admin, opcionalmente verificar que sea el creador.
    // Por ahora lo permitiremos o lo restringimos si se desea.
    try {
        // Eliminar subtareas primero (si no hay CASCADE)
        $db->exec("DELETE FROM tm_subtasks WHERE task_id = $taskId");
        $db->exec("DELETE FROM tm_tasks WHERE id = $taskId");
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'get_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if (!$taskId) { echo json_encode(['success'=>false]); exit; }
    
    try {
        $stmt = $db->prepare("SELECT * FROM tm_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            $task['assigned_users'] = json_decode($task['assigned_users'] ?? '[]', true) ?: [];
            $task['assigned_roles'] = json_decode($task['assigned_roles'] ?? '[]', true) ?: [];
            $task['tags'] = json_decode($task['tags'] ?? '[]', true) ?: [];
            
            // Get subtasks list
            $stmtSub = $db->prepare("SELECT id, title, is_completed FROM tm_subtasks WHERE task_id = ? ORDER BY id ASC");
            $stmtSub->execute([$taskId]);
            $task['subtasks_list'] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success'=>true, 'task'=>$task]);
        } else {
            echo json_encode(['success'=>false, 'error'=>'Tarea no encontrada']);
        }
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'update_task_details') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $startDate = $_POST['start_date'] ?: null;
    $dueDate = $_POST['due_date'] ?: null;
    $assignedUsers = $_POST['assigned_users'] ?? '[]';
    $assignedRoles = $_POST['assigned_roles'] ?? '[]';
    $tags = $_POST['tags'] ?? '[]';

    $newSubtasksJson = $_POST['new_subtasks'] ?? '[]';

    if (!$taskId || !$title) { echo json_encode(['success'=>false, 'error'=>'Datos inválidos']); exit; }

    if ($status === 'approved' && !$isAdmin) {
        echo json_encode(['success'=>false, 'error'=>'Solo administradores pueden aprobar tareas.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE tm_tasks SET title=?, description=?, priority=?, status=?, start_date=?, due_date=?, assigned_users=?, assigned_roles=?, tags=? WHERE id=?");
        $stmt->execute([$title, $desc, $priority, $status, $startDate, $dueDate, $assignedUsers, $assignedRoles, $tags, $taskId]);
        
        // Insert new subtasks added in edit modal
        $newSubtasksArr = json_decode($newSubtasksJson, true) ?: [];
        if (!empty($newSubtasksArr)) {
            $stmtSub = $db->prepare("INSERT INTO tm_subtasks (task_id, title) VALUES (?, ?)");
            foreach ($newSubtasksArr as $stTitle) {
                if (trim($stTitle)) {
                    $stmtSub->execute([$taskId, trim($stTitle)]);
                }
            }
        }

        echo json_encode(['success'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['success'=>false, 'error'=>'Acción desconocida']);
