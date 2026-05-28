<?php
// modules/tasks/ajax.php — Centro de Tareas Backend API v2

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

require_once '../../config/database.php';
require_once '../../includes/PushHelper.php';
$db = (new Database())->getConnection();

$action = $_POST['action_type'] ?? $_GET['action_type'] ?? '';
$userId = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] === 'admin');

// ── Check granular permission for "view all" ──
function canViewAll($db, $isAdmin, $userId) {
    if ($isAdmin) return true;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'task_center_view_all_roles'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val) {
            $roles = json_decode($val, true) ?: [];
            $stmtR = $db->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmtR->execute([$userId]);
            $roleId = (int)$stmtR->fetchColumn();
            return in_array($roleId, $roles);
        }
    } catch(Throwable $e) {}
    return false;
}

function getUserMap($db) {
    static $map = null;
    if ($map === null) {
        $stmt = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $map[$r['id']] = $r;
    }
    return $map;
}

function normalizeStatus($status, $source) {
    $s = strtolower(trim($status));
    $maps = [
        'task' => ['pending'=>'pending','in_progress'=>'in_progress','in_review'=>'in_review','completed'=>'completed'],
        'design_task' => ['pendiente'=>'pending','en progreso'=>'in_progress','en revisión'=>'in_review','en revision'=>'in_review','terminado'=>'completed'],
        'project_month' => ['pendiente'=>'pending','en progreso'=>'in_progress','en_progreso'=>'in_progress','en revisión'=>'in_review','en revision'=>'in_review','en_revision'=>'in_review','finalizado'=>'completed']
    ];
    return $maps[$source][$s] ?? 'pending';
}

function denormalizeStatus($ns, $source) {
    $maps = [
        'task' => ['pending'=>'pending','in_progress'=>'in_progress','in_review'=>'in_review','completed'=>'completed'],
        'design_task' => ['pending'=>'Pendiente','in_progress'=>'En progreso','in_review'=>'En revisión','completed'=>'Terminado'],
        'project_month' => ['pending'=>'Pendiente','in_progress'=>'En progreso','in_review'=>'En revisión','completed'=>'Finalizado']
    ];
    return $maps[$source][$ns] ?? $ns;
}

function resolveUsers($json, $map) {
    $ids = json_decode($json, true) ?: [];
    $result = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if (isset($map[$id])) {
            $result[] = ['id'=>$id, 'name'=>$map[$id]['name'], 'avatar'=>$map[$id]['avatar'], 'initial'=>strtoupper(substr($map[$id]['name'],0,1))];
        }
    }
    return $result;
}

// Push notification helper replaced by global PushHelper

$monthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

// ════════════════════════════════════════════
// GET ALL TASKS
// ════════════════════════════════════════════
if ($action === 'get_all_tasks') {
    $filterUser = $_POST['filter_user'] ?? 'me';
    $filterSource = $_POST['filter_source'] ?? 'all';
    $hasViewAll = canViewAll($db, $isAdmin, $userId);
    
    $showAll = false;
    $targetUserId = $userId;
    if ($hasViewAll) {
        if ($filterUser === 'all') $showAll = true;
        elseif ($filterUser !== 'me' && is_numeric($filterUser)) $targetUserId = (int)$filterUser;
    }
    
    $userMap = getUserMap($db);
    $allTasks = [];
    
    // Source 1: Regular Tasks
    if ($filterSource === 'all' || $filterSource === 'task') {
        try {
            $where = $showAll ? "t.is_archived = 0" : "t.is_archived = 0 AND (t.assigned_to LIKE '%\"{$targetUserId}\"%' OR t.created_by = {$targetUserId})";
            $stmtT = $db->query("SELECT t.*, u.name as creator_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id WHERE {$where} ORDER BY t.created_at DESC");
            $tasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);
            $tIds = array_column($tasks, 'id');
            $subStats = [];
            if (!empty($tIds)) {
                $in = implode(',', $tIds);
                $st = $db->query("SELECT task_id, COUNT(*) as total, SUM(is_completed) as completed FROM task_subtasks WHERE task_id IN ($in) GROUP BY task_id");
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) $subStats[$r['task_id']] = ['total'=>(int)$r['total'],'completed'=>(int)$r['completed']];
            }
            foreach ($tasks as $t) {
                $ctx = [];
                $desc = $t['description'] ?? '';
                if (str_starts_with($desc, '{')) {
                    $parsed = json_decode($desc, true);
                    if ($parsed && isset($parsed['template_source'])) {
                        $ctx = $parsed;
                        $desc = ''; // hide from normal description
                    }
                }
                
                $allTasks[] = [
                    'source'=>'task','source_id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$desc,
                    'status'=>normalizeStatus($t['status'],'task'),'priority'=>null,'due_date'=>$t['due_date'],
                    'assigned_users'=>resolveUsers($t['assigned_to']??'[]',$userMap),
                    'created_by_name'=>$t['creator_name']??'Sistema','created_at'=>$t['created_at'],
                    'context'=>$ctx,'subtasks'=>$subStats[$t['id']]??null,'link'=>null,
                    'source_label'=>'Tarea','source_icon'=>'ph-check-square-offset','source_color'=>'#64748b',
                    'is_urgent'=>(int)($t['is_urgent']??0)
                ];
            }
        } catch(Throwable $e) {}
    }
    
    // Source 2: Design Tasks
    if ($filterSource === 'all' || $filterSource === 'design_task') {
        try {
            $where = $showAll ? "dt.deleted_at IS NULL" : "dt.deleted_at IS NULL AND (dt.assigned_to LIKE '%\"{$targetUserId}\"%' OR dt.created_by = {$targetUserId})";
            $stmtD = $db->query("SELECT dt.*, u.name as creator_name FROM design_tasks dt LEFT JOIN users u ON dt.created_by = u.id WHERE {$where} ORDER BY dt.created_at DESC");
            $dTasks = $stmtD->fetchAll(PDO::FETCH_ASSOC);
            $dIds = array_column($dTasks, 'id');
            $dSub = [];
            if (!empty($dIds)) {
                $in = implode(',', $dIds);
                $st = $db->query("SELECT design_task_id, COUNT(*) as total, SUM(is_completed) as completed FROM design_task_subtasks WHERE design_task_id IN ($in) GROUP BY design_task_id");
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) $dSub[$r['design_task_id']] = ['total'=>(int)$r['total'],'completed'=>(int)$r['completed']];
            }
            $dTags = [];
            if (!empty($dIds)) {
                $in = implode(',', $dIds);
                $st = $db->query("SELECT design_task_id, name, color FROM design_task_tags WHERE design_task_id IN ($in)");
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) $dTags[$r['design_task_id']][] = ['name'=>$r['name'],'color'=>$r['color']];
            }
            foreach ($dTasks as $dt) {
                $allTasks[] = [
                    'source'=>'design_task','source_id'=>(int)$dt['id'],'title'=>$dt['title'],'description'=>$dt['description']??'',
                    'status'=>normalizeStatus($dt['status'],'design_task'),'priority'=>$dt['priority']??'media',
                    'due_date'=>$dt['due_date']?date('Y-m-d',strtotime($dt['due_date'])):null,
                    'assigned_users'=>resolveUsers($dt['assigned_to']??'[]',$userMap),
                    'created_by_name'=>$dt['creator_name']??'Sistema','created_at'=>$dt['created_at'],
                    'context'=>['tags'=>$dTags[$dt['id']]??[],'time_spent'=>(int)($dt['time_spent']??0)],
                    'subtasks'=>$dSub[$dt['id']]??null,'link'=>'index.php?module=design_tasks&action=index',
                    'source_label'=>'Diseño Gráfico','source_icon'=>'ph-paint-brush','source_color'=>'#8b5cf6',
                    'is_urgent'=>0
                ];
            }
        } catch(Throwable $e) {}
    }
    
    // Source 3: Project Months
    if ($filterSource === 'all' || $filterSource === 'project_month') {
        try {
            $stmtP = $db->query("SELECT p.id, p.team_members, w.brand_name FROM projects p JOIN work_orders w ON p.work_order_id = w.id WHERE p.status = 'active'");
            $projects = $stmtP->fetchAll(PDO::FETCH_ASSOC);
            $pIds = []; $pDet = [];
            foreach ($projects as $p) {
                $mem = json_decode($p['team_members'],true)?:[];
                if ($showAll || in_array((string)$targetUserId,$mem) || in_array($targetUserId,$mem)) {
                    $pIds[] = $p['id']; $pDet[$p['id']] = $p;
                }
            }
            if (!empty($pIds)) {
                $in = implode(',', array_fill(0,count($pIds),'?'));
                $stmtM = $db->prepare("SELECT * FROM project_months WHERE project_id IN ($in)");
                $stmtM->execute($pIds);
                foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $mt) {
                    $pi = $pDet[$mt['project_id']]??[];
                    $brand = $pi['brand_name']??'Proyecto';
                    $mName = $monthNames[$mt['month']]??$mt['month'];
                    $allTasks[] = [
                        'source'=>'project_month','source_id'=>(int)$mt['id'],
                        'title'=>$brand.' — '.$mName.' '.$mt['year'],'description'=>'',
                        'status'=>normalizeStatus($mt['status']??'Pendiente','project_month'),'priority'=>null,
                        'due_date'=>$mt['start_date'],
                        'assigned_users'=>resolveUsers($pi['team_members']??'[]',$userMap),
                        'created_by_name'=>'Sistema','created_at'=>$mt['created_at'],
                        'context'=>['brand'=>$brand,'phase'=>$mt['content_phase']??'En Borrador','month'=>$mName,'year'=>$mt['year']],
                        'subtasks'=>null,'link'=>'index.php?module=calendar&action=index',
                        'source_label'=>'Mes de Proyecto','source_icon'=>'ph-calendar-dots','source_color'=>'#0ea5e9',
                        'is_urgent'=>0
                    ];
                }
            }
        } catch(Throwable $e) {}
    }
    
    // Sort
    usort($allTasks, function($a,$b) {
        if ($a['status']==='completed' && $b['status']!=='completed') return 1;
        if ($a['status']!=='completed' && $b['status']==='completed') return -1;
        if ($a['is_urgent'] && !$b['is_urgent']) return -1;
        if (!$a['is_urgent'] && $b['is_urgent']) return 1;
        $now = time();
        $aO = $a['due_date'] && strtotime($a['due_date'])<$now && $a['status']!=='completed';
        $bO = $b['due_date'] && strtotime($b['due_date'])<$now && $b['status']!=='completed';
        if ($aO && !$bO) return -1;
        if (!$aO && $bO) return 1;
        if ($a['due_date'] && $b['due_date']) return strtotime($a['due_date'])-strtotime($b['due_date']);
        if ($a['due_date'] && !$b['due_date']) return -1;
        if (!$a['due_date'] && $b['due_date']) return 1;
        return strtotime($b['created_at'])-strtotime($a['created_at']);
    });
    
    // Stats
    $now = time();
    $stats = ['total'=>count($allTasks),'pending'=>0,'in_progress'=>0,'in_review'=>0,'completed'=>0,'overdue'=>0,'due_soon'=>0,'urgent'=>0];
    foreach ($allTasks as $t) {
        $stats[$t['status']]++;
        if ($t['is_urgent']) $stats['urgent']++;
        if ($t['due_date'] && $t['status']!=='completed') {
            $d = strtotime($t['due_date']);
            if ($d<$now) $stats['overdue']++;
            elseif ($d<=strtotime('+3 days')) $stats['due_soon']++;
        }
    }
    
    // Weekly productivity
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
    $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
    $thisWeekCompleted = 0; $lastWeekCompleted = 0;
    try {
        $stW = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status='completed' AND updated_at >= ?");
        $stW->execute([$weekStart]); $thisWeekCompleted += (int)$stW->fetchColumn();
        $stW2 = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status='completed' AND updated_at >= ? AND updated_at < ?");
        $stW2->execute([$lastWeekStart, $weekStart]); $lastWeekCompleted += (int)$stW2->fetchColumn();
        // Design tasks
        $stD = $db->prepare("SELECT COUNT(*) FROM design_tasks WHERE status='Terminado' AND updated_at >= ?");
        $stD->execute([$weekStart]); $thisWeekCompleted += (int)$stD->fetchColumn();
        $stD2 = $db->prepare("SELECT COUNT(*) FROM design_tasks WHERE status='Terminado' AND updated_at >= ? AND updated_at < ?");
        $stD2->execute([$lastWeekStart, $weekStart]); $lastWeekCompleted += (int)$stD2->fetchColumn();
    } catch(Throwable $e) {}
    $stats['this_week_completed'] = $thisWeekCompleted;
    $stats['last_week_completed'] = $lastWeekCompleted;
    
    // Workload per user
    $workload = [];
    foreach ($allTasks as $t) {
        if ($t['status'] === 'completed') continue;
        foreach ($t['assigned_users'] as $u) {
            if (!isset($workload[$u['id']])) $workload[$u['id']] = ['name'=>$u['name'],'avatar'=>$u['avatar'],'count'=>0];
            $workload[$u['id']]['count']++;
        }
    }
    arsort($workload);
    $stats['workload'] = array_values($workload);
    
    // Data version hash for polling
    $stats['version'] = md5(json_encode(array_map(fn($t)=>$t['source'].$t['source_id'].$t['status'], $allTasks)));
    
    echo json_encode(['success'=>true,'tasks'=>$allTasks,'stats'=>$stats]);
    exit;
}

// ════════════════════════════════════════════
// CHECK VERSION (for polling)
// ════════════════════════════════════════════
if ($action === 'check_version') {
    // Lightweight check - just count and status
    try {
        $c1 = $db->query("SELECT GROUP_CONCAT(CONCAT(id,status) ORDER BY id) FROM tasks WHERE is_archived=0")->fetchColumn();
        $c2 = $db->query("SELECT GROUP_CONCAT(CONCAT(id,status) ORDER BY id) FROM design_tasks WHERE deleted_at IS NULL")->fetchColumn();
        $c3 = $db->query("SELECT GROUP_CONCAT(CONCAT(id,status) ORDER BY id) FROM project_months")->fetchColumn();
        echo json_encode(['success'=>true,'version'=>md5($c1.$c2.$c3)]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>true,'version'=>'error']);
    }
    exit;
}

// ════════════════════════════════════════════
// UPDATE STATUS
// ════════════════════════════════════════════
if ($action === 'update_status') {
    $source = $_POST['source']??'';
    $sourceId = (int)($_POST['source_id']??0);
    $newStatus = $_POST['status']??'';
    if (!$sourceId||!$source||!$newStatus) { echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit; }
    
    try {
        $dbS = denormalizeStatus($newStatus, $source);
        $table = ['task'=>'tasks','design_task'=>'design_tasks','project_month'=>'project_months'][$source] ?? null;
        if (!$table) { echo json_encode(['success'=>false,'error'=>'Fuente desconocida']); exit; }
        
        $stmt = $db->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
        $stmt->execute([$dbS, $sourceId]);
        
        // Push notification to assigned users
        if ($newStatus === 'completed' || $newStatus === 'in_review') {
            try {
                if ($source === 'task') {
                    $st = $db->prepare("SELECT assigned_to, title FROM tasks WHERE id = ?");
                    $st->execute([$sourceId]); $row = $st->fetch(PDO::FETCH_ASSOC);
                } elseif ($source === 'design_task') {
                    $st = $db->prepare("SELECT assigned_to, title FROM design_tasks WHERE id = ?");
                    $st->execute([$sourceId]); $row = $st->fetch(PDO::FETCH_ASSOC);
                }
                if (isset($row)) {
                    $assigned = json_decode($row['assigned_to']??'[]',true)?:[];
                    $statusLabel = ['completed'=>'Completada','in_review'=>'En Revisión'][$newStatus]??$newStatus;
                    $assignedIds = array_values(array_diff($assigned, [$userId]));
                    if (!empty($assignedIds)) {
                        PushHelper::sendPushNotification($db, $assignedIds, "Tarea actualizada", "\"{$row['title']}\" ahora está {$statusLabel}", "index.php?module=tasks", "task_center", ['module' => 'tasks']);
                    }
                }
            } catch(Throwable $e) {}
        }
        
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// TOGGLE URGENT
// ════════════════════════════════════════════
if ($action === 'toggle_urgent') {
    $sourceId = (int)($_POST['source_id']??0);
    if (!$sourceId) { echo json_encode(['success'=>false]); exit; }
    try {
        $stmt = $db->prepare("UPDATE tasks SET is_urgent = NOT is_urgent WHERE id = ?");
        $stmt->execute([$sourceId]);
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// UPDATE TITLE (inline edit)
// ════════════════════════════════════════════
if ($action === 'update_title') {
    $source = $_POST['source']??'';
    $sourceId = (int)($_POST['source_id']??0);
    $title = trim($_POST['title']??'');
    if (!$sourceId||!$source||!$title) { echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit; }
    try {
        $table = ['task'=>'tasks','design_task'=>'design_tasks'][$source]??null;
        if (!$table) { echo json_encode(['success'=>false,'error'=>'No editable']); exit; }
        $stmt = $db->prepare("UPDATE {$table} SET title = ? WHERE id = ?");
        $stmt->execute([$title, $sourceId]);
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// UPDATE ASSIGNED USERS
// ════════════════════════════════════════════
if ($action === 'update_assigned') {
    $source = $_POST['source']??'';
    $sourceId = (int)($_POST['source_id']??0);
    $userIds = $_POST['user_ids']??[];
    if (!$sourceId||!$source) { echo json_encode(['success'=>false]); exit; }
    try {
        $table = ['task'=>'tasks','design_task'=>'design_tasks'][$source]??null;
        if (!$table) { echo json_encode(['success'=>false,'error'=>'No editable']); exit; }
        $json = json_encode(array_map('strval', $userIds));
        $stmt = $db->prepare("UPDATE {$table} SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$json, $sourceId]);
        
        // Notify newly assigned users
        $assignedIds = array_values(array_diff($userIds, [$userId]));
        if (!empty($assignedIds)) {
            PushHelper::sendPushNotification($db, $assignedIds, "Nueva asignación", "Se te ha asignado una tarea", "index.php?module=tasks", "task_center", ['module' => 'tasks']);
        }
        
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// GET TASK DETAILS
// ════════════════════════════════════════════
if ($action === 'get_task_details') {
    $source = $_POST['source']??'';
    $sourceId = (int)($_POST['source_id']??0);
    if (!$sourceId||!$source) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
    
    $userMap = getUserMap($db);
    $result = ['success'=>true];
    
    if ($source === 'task') {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?"); $stmt->execute([$sourceId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) { echo json_encode(['success'=>false,'error'=>'No encontrada']); exit; }
        $stSub = $db->prepare("SELECT * FROM task_subtasks WHERE task_id = ? ORDER BY id ASC"); $stSub->execute([$sourceId]);
        $result['task'] = $task;
        $result['subtasks'] = $stSub->fetchAll(PDO::FETCH_ASSOC);
        $result['assigned_users'] = resolveUsers($task['assigned_to']??'[]',$userMap);
    } elseif ($source === 'design_task') {
        $stmt = $db->prepare("SELECT * FROM design_tasks WHERE id = ?"); $stmt->execute([$sourceId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) { echo json_encode(['success'=>false,'error'=>'No encontrada']); exit; }
        $stSub = $db->prepare("SELECT * FROM design_task_subtasks WHERE design_task_id = ? ORDER BY id ASC"); $stSub->execute([$sourceId]);
        $stTags = $db->prepare("SELECT name, color FROM design_task_tags WHERE design_task_id = ?"); $stTags->execute([$sourceId]);
        $result['task'] = $task;
        $result['subtasks'] = $stSub->fetchAll(PDO::FETCH_ASSOC);
        $result['tags'] = $stTags->fetchAll(PDO::FETCH_ASSOC);
        $result['assigned_users'] = resolveUsers($task['assigned_to']??'[]',$userMap);
    } elseif ($source === 'project_month') {
        $stmt = $db->prepare("SELECT pm.*, p.team_members, w.brand_name FROM project_months pm JOIN projects p ON pm.project_id = p.id JOIN work_orders w ON p.work_order_id = w.id WHERE pm.id = ?");
        $stmt->execute([$sourceId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) { echo json_encode(['success'=>false,'error'=>'No encontrado']); exit; }
        $result['task'] = $task;
        $result['subtasks'] = [];
        $result['assigned_users'] = resolveUsers($task['team_members']??'[]',$userMap);
    }
    
    // Get all users for reassign dropdown
    $result['all_users'] = array_values($userMap);
    
    echo json_encode($result);
    exit;
}

// ════════════════════════════════════════════
// TOGGLE SUBTASK
// ════════════════════════════════════════════
if ($action === 'toggle_subtask') {
    $source = $_POST['source']??'task';
    $subId = (int)($_POST['id']??0);
    if (!$subId) { echo json_encode(['success'=>false]); exit; }
    try {
        $tbl = $source==='design_task' ? 'design_task_subtasks' : 'task_subtasks';
        $stmt = $db->prepare("UPDATE {$tbl} SET is_completed = NOT is_completed WHERE id = ?");
        $stmt->execute([$subId]);
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// ADD SUBTASK
// ════════════════════════════════════════════
if ($action === 'add_subtask') {
    $source = $_POST['source'] ?? 'task';
    $sourceId = (int)($_POST['source_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $imageStr = $_POST['image'] ?? '';

    if (!$sourceId || (!$title && !$imageStr)) {
        echo json_encode(['success' => false, 'error' => 'Datos insuficientes']);
        exit;
    }

    try {
        if ($imageStr && str_starts_with($imageStr, 'data:image')) {
            $parts = explode(',', $imageStr);
            $decoded = base64_decode($parts[1]);
            $ext = 'png';
            if (preg_match('#^data:image/(\w+);base64#i', $parts[0], $match)) {
                $ext = strtolower($match[1]);
            }
            $filename = 'subtask_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            
            $uploadDir = '../../uploads/subtasks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            file_put_contents($uploadDir . $filename, $decoded);
            $title = ($title ? $title . ' - ' : '') . "<a href=\"uploads/subtasks/{$filename}\" target=\"_blank\">🖼️ Imagen adjunta</a>";
        }

        $title = substr($title, 0, 255); // Ensure it fits in varchar(255)

        if ($source === 'design_task') {
            $stmt = $db->prepare("INSERT INTO design_task_subtasks (design_task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())");
            $stmt->execute([$sourceId, $title]);
        } else {
            $stmt = $db->prepare("INSERT INTO task_subtasks (task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())");
            $stmt->execute([$sourceId, $title]);
        }

        echo json_encode(['success' => true]);
    } catch(Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════
// ARCHIVE COMPLETED
// ════════════════════════════════════════════
if ($action === 'archive_completed') {
    if (!canViewAll($db, $isAdmin, $userId)) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }
    try {
        $db->query("UPDATE tasks SET is_archived = 1 WHERE status = 'completed'");
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// GET USERS
// ════════════════════════════════════════════
if ($action === 'get_users') {
    echo json_encode(['success'=>true,'users'=>array_values(getUserMap($db))]);
    exit;
}

// ════════════════════════════════════════════
// CREATE TASK FROM TEMPLATE
// ════════════════════════════════════════════
if ($action === 'create_from_template') {
    $title = trim($_POST['title']??'');
    $source = $_POST['template_source']??'';
    $sourceId = (int)($_POST['template_source_id']??0);
    
    if (!$title || !$source || !$sourceId) { echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit; }
    
    try {
        $assigned = '[]';
        $desc = '';
        
        if ($source === 'task') {
            $stmt = $db->prepare("SELECT title, assigned_to FROM tasks WHERE id = ?"); $stmt->execute([$sourceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $assigned = $row['assigned_to']; $desc = "Creada a partir de: " . $row['title']; }
        } elseif ($source === 'design_task') {
            $stmt = $db->prepare("SELECT title, assigned_to FROM design_tasks WHERE id = ?"); $stmt->execute([$sourceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $assigned = $row['assigned_to']; $desc = "Creada a partir del diseño: " . $row['title']; }
        } elseif ($source === 'project_month') {
            $stmt = $db->prepare("SELECT pm.month, pm.year, p.team_members, w.brand_name FROM project_months pm JOIN projects p ON pm.project_id = p.id JOIN work_orders w ON p.work_order_id = w.id WHERE pm.id = ?");
            $stmt->execute([$sourceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { 
                $assigned = $row['team_members']; 
                $mName = $monthNames[$row['month']] ?? $row['month'];
                $desc = "Creada a partir del mes de proyecto: " . $row['brand_name'] . " - " . $mName . " " . $row['year'];
            }
        }
        
        $desc = json_encode(['template_source' => $source, 'template_source_id' => $sourceId]);
        
        $stmtIns = $db->prepare("INSERT INTO tasks (title, description, status, assigned_to, created_by, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?, NOW(), NOW())");
        $stmtIns->execute([$title, $desc, $assigned, $userId]);
        
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// CREATE EMPTY TASK
// ════════════════════════════════════════════
if ($action === 'create_task') {
    $title = trim($_POST['title']??'');
    if (!$title) { echo json_encode(['success'=>false,'error'=>'Título requerido']); exit; }
    
    try {
        $assigned = json_encode([(string)$userId]);
        $stmtIns = $db->prepare("INSERT INTO tasks (title, description, status, assigned_to, created_by, created_at, updated_at) VALUES (?, '', 'pending', ?, ?, NOW(), NOW())");
        $stmtIns->execute([$title, $assigned, $userId]);
        
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ════════════════════════════════════════════
// DELETE TASK
// ════════════════════════════════════════════
if ($action === 'delete_task') {
    $source = $_POST['source']??'';
    $sourceId = (int)($_POST['source_id']??0);
    if (!$sourceId || !$source) { echo json_encode(['success'=>false]); exit; }
    
    try {
        if ($source === 'task') {
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$sourceId]);
        } elseif ($source === 'design_task') {
            $stmt = $db->prepare("UPDATE design_tasks SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$sourceId]);
        }
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

echo json_encode(['success'=>false,'error'=>'Acción no válida']);

