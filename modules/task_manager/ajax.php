<?php
// modules/task_manager/ajax.php — Gestor de Tareas, Objetivos Diarios y Conexiones API
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

require_once __DIR__ . '/../../config/database.php';
try {
    require_once __DIR__ . '/../../includes/PushHelper.php';
} catch (Throwable $e) {}

$db = (new Database())->getConnection();

// Robust payload parsing (supports FormData, URLSearchParams, text/plain, raw string, and JSON)
if (empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            $_POST = array_merge($_POST, $jsonData);
        } else {
            parse_str($rawInput, $parsedPost);
            if (is_array($parsedPost)) {
                $_POST = array_merge($_POST, $parsedPost);
            }
        }
    }
}

$action = $_POST['action_type'] ?? $_GET['action_type'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$userRoleId = (int)($_SESSION['role_id'] ?? 1);

$isAdmin = false;
if ($userRoleId == 1) {
    $isAdmin = true;
} elseif (in_array('admin', $_SESSION['user_permissions'] ?? [])) {
    $isAdmin = true;
} elseif (strtolower($_SESSION['user_role'] ?? '') === 'administrador' || strtolower($_SESSION['user_role'] ?? '') === 'admin') {
    $isAdmin = true;
}

$monthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

function getUserMap($db) {
    $stmt = $db->query("SELECT id, name, avatar, role_id FROM users ORDER BY name ASC");
    $map = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[$r['id']] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'avatar' => $r['avatar'],
            'role_id' => (int)$r['role_id'],
            'initial' => strtoupper(substr($r['name'], 0, 1))
        ];
    }
    return $map;
}

function syncCalendarMonthDates($db, $projectMonthId, $startDate, $dueDate) {
    $projectMonthId = (int)$projectMonthId;
    if ($projectMonthId <= 0 || (!$startDate && !$dueDate)) return;
    $mStart = $startDate ? substr(str_replace('T', ' ', $startDate), 0, 10) : null;
    $mDue = $dueDate ? substr(str_replace('T', ' ', $dueDate), 0, 10) : null;
    try {
        $st = $db->prepare("SELECT start_date, due_date FROM project_months WHERE id = ?");
        $st->execute([$projectMonthId]);
        $curr = $st->fetch(PDO::FETCH_ASSOC);
        if (!$curr) return;
        $currStart = $curr['start_date'] ?? null;
        $currDue = $curr['due_date'] ?? null;

        if ($mStart && !$mDue) {
            if (!$currDue || strtotime($currDue) <= strtotime($mStart)) {
                $mDue = date('Y-m-d', strtotime($mStart . ' +30 days'));
            } else {
                $mDue = $currDue;
            }
        } elseif ($mDue && !$mStart) {
            if (!$currStart || strtotime($currStart) >= strtotime($mDue)) {
                $mStart = date('Y-m-d', strtotime($mDue . ' -30 days'));
            } else {
                $mStart = $currStart;
            }
        }

        $stmtM = $db->prepare("UPDATE project_months SET start_date = ?, due_date = ?, status = 'en progreso' WHERE id = ?");
        $stmtM->execute([$mStart, $mDue, $projectMonthId]);
    } catch(Throwable $e) {}
}

function syncBrandGroupDates($db, $brandGroupId, $startDate, $dueDate) {
    $brandGroupId = (int)$brandGroupId;
    if ($brandGroupId <= 0 || (!$startDate && !$dueDate)) return;
    $bgStart = $startDate ? substr(str_replace('T', ' ', $startDate), 0, 10) : null;
    $bgDue = $dueDate ? substr(str_replace('T', ' ', $dueDate), 0, 10) : null;
    try {
        $st = $db->prepare("SELECT start_date, due_date FROM brand_task_groups WHERE id = ?");
        $st->execute([$brandGroupId]);
        $curr = $st->fetch(PDO::FETCH_ASSOC);
        if (!$curr) return;
        $currStart = $curr['start_date'] ?? null;
        $currDue = $curr['due_date'] ?? null;

        if ($bgStart && !$bgDue) {
            if (!$currDue || strtotime($currDue) <= strtotime($bgStart)) {
                $bgDue = date('Y-m-d', strtotime($bgStart . ' +14 days'));
            } else {
                $bgDue = $currDue;
            }
        } elseif ($bgDue && !$bgStart) {
            if (!$currStart || strtotime($currStart) >= strtotime($bgDue)) {
                $bgStart = date('Y-m-d', strtotime($bgDue . ' -14 days'));
            } else {
                $bgStart = $currStart;
            }
        }

        $stmtBG = $db->prepare("UPDATE brand_task_groups SET start_date = ?, due_date = ? WHERE id = ?");
        $stmtBG->execute([$bgStart, $bgDue, $brandGroupId]);
    } catch(Throwable $e) {}
}

function checkOverdueTasks($db) {
    try {
        $db->query("UPDATE tm_tasks SET status = 'overdue' WHERE due_date IS NOT NULL AND due_date < NOW() AND status IN ('new', 'pending')");
    } catch(Throwable $e) {}
}

function processRecurringTasks($db) {
    try {
        // Daily recurring templates
        $db->exec("
            INSERT INTO tm_tasks (title, description, priority, frequency, area, assigned_users, assigned_roles, tags, created_by, status, is_daily_objective, objective_date)
            SELECT title, description, priority, 'daily', 'general', assigned_users, assigned_roles, tags, created_by, 'new', 1, CURDATE()
            FROM tm_recurring_templates
            WHERE (last_generated IS NULL OR DATE(last_generated) < CURDATE())
            AND recurrence_type = 'daily'
        ");
        $db->exec("UPDATE tm_recurring_templates SET last_generated = NOW() WHERE recurrence_type = 'daily' AND (last_generated IS NULL OR DATE(last_generated) < CURDATE())");
    } catch(Throwable $e) {}
}

// ══════════════════════════════════════════════════════════
// 1. GET PROJECTS, CALENDAR MONTHS, BRAND PROJECTS & USERS
// ══════════════════════════════════════════════════════════
if ($action === 'get_projects_and_months') {
    try {
        $userMap = getUserMap($db);

        // Active Projects (From projects + work_orders)
        $projects = [];
        $stmtP = $db->query("
            SELECT p.id, w.brand_name as name, p.team_members, 'marketing' as type 
            FROM projects p 
            JOIN work_orders w ON p.work_order_id = w.id 
            WHERE p.status = 'active' 
            ORDER BY w.brand_name ASC
        ");
        while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
            $tmRaw = json_decode($r['team_members'] ?: '[]', true) ?: [];
            $tmInts = [];
            foreach ((array)$tmRaw as $uid) {
                if (is_numeric($uid) && (int)$uid > 0) $tmInts[] = (int)$uid;
            }
            $projects[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'] ?: 'Proyecto #' . $r['id'],
                'type' => 'marketing',
                'team_members' => $tmInts
            ];
        }

        // Active Module Projects (from module_projects)
        $stmtMP = $db->query("SELECT id, name FROM module_projects WHERE status = 'active' ORDER BY name ASC");
        while ($r = $stmtMP->fetch(PDO::FETCH_ASSOC)) {
            $projects[] = [
                'id' => (int)$r['id'] + 10000, // Namespace separation
                'real_id' => (int)$r['id'],
                'name' => $r['name'],
                'type' => 'module_project',
                'team_members' => []
            ];
        }

        // Active Calendar Months (project_months)
        $projectMonths = [];
        $stmtM = $db->query("
            SELECT pm.id, pm.project_id, pm.month, pm.year, pm.status, pm.content_phase, pm.start_date, pm.due_date, w.brand_name 
            FROM project_months pm 
            JOIN projects p ON pm.project_id = p.id 
            JOIN work_orders w ON p.work_order_id = w.id 
            WHERE p.status = 'active' AND (pm.status != 'Finalizado' OR pm.status IS NULL)
            ORDER BY pm.year DESC, pm.month DESC
        ");
        while ($r = $stmtM->fetch(PDO::FETCH_ASSOC)) {
            $mLabel = ($monthNames[$r['month']] ?? 'Mes ' . $r['month']) . ' ' . $r['year'];
            $phaseStr = $r['content_phase'] ? ' - ' . $r['content_phase'] : '';
            $projectMonths[] = [
                'id' => (int)$r['id'],
                'project_id' => (int)$r['project_id'],
                'month' => (int)$r['month'],
                'year' => (int)$r['year'],
                'brand_name' => $r['brand_name'] ?: 'Proyecto #' . $r['project_id'],
                'label' => $mLabel . ' (' . ($r['status'] ?: 'Activo') . $phaseStr . ')',
                'raw_label' => $mLabel,
                'status' => $r['status'] ?: 'Activo',
                'content_phase' => $r['content_phase'] ?: 'En Borrador',
                'start_date' => $r['start_date'] ?: '',
                'due_date' => $r['due_date'] ?: ''
            ];
        }

        // Brand Projects (brand_projects for Desarrollo de Marca)
        $brandProjects = [];
        $bpuMap = [];
        try {
            $stBPU = $db->query("SELECT project_id, user_id FROM brand_project_users");
            while ($b = $stBPU->fetch(PDO::FETCH_ASSOC)) {
                $bpuMap[(int)$b['project_id']][] = (int)$b['user_id'];
            }
        } catch(Throwable $e) {}

        try {
            $stmtBP = $db->query("SELECT id, title, client_name, status, start_date, due_date FROM brand_projects WHERE status = 'Active' OR status IS NULL OR status = '' ORDER BY id DESC");
            while ($r = $stmtBP->fetch(PDO::FETCH_ASSOC)) {
                $bpId = (int)$r['id'];
                $brandProjects[] = [
                    'id' => $bpId,
                    'title' => $r['title'],
                    'client_name' => $r['client_name'] ?? '',
                    'status' => $r['status'] ?? 'Active',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: '',
                    'team_members' => $bpuMap[$bpId] ?? []
                ];
            }
        } catch(Throwable $e) {}

        // Project Services (for Desarrollo Web and Audiovisual from project_services)
        $projectServices = [];
        try {
            $stmtPS = $db->query("
                SELECT ps.id, ps.project_id, ps.service_id, ps.title, ps.status, ps.start_date, ps.due_date,
                       mp.name as project_name, s.name as service_name, sc.name as category_name, sc.id as category_id
                FROM project_services ps
                LEFT JOIN module_projects mp ON ps.project_id = mp.id
                LEFT JOIN services s ON ps.service_id = s.id
                LEFT JOIN service_categories sc ON s.category_id = sc.id
                WHERE mp.status = 'active' OR mp.status IS NULL
                ORDER BY ps.id DESC
            ");
            while ($r = $stmtPS->fetch(PDO::FETCH_ASSOC)) {
                $areaType = 'general';
                $catName = mb_strtolower($r['category_name'] ?? '');
                $srvName = mb_strtolower($r['service_name'] ?? '');
                if (strpos($catName, 'web') !== false || strpos($srvName, 'web') !== false) {
                    $areaType = 'desarrollo_web';
                } else if (strpos($catName, 'audio') !== false || strpos($srvName, 'video') !== false || strpos($srvName, 'audio') !== false || strpos($srvName, 'video') !== false) {
                    $areaType = 'audiovisual';
                }
                $projectServices[] = [
                    'id' => (int)$r['id'],
                    'project_id' => (int)$r['project_id'],
                    'title' => $r['title'] ?: ($r['service_name'] ?: 'Servicio #' . $r['id']),
                    'service_name' => $r['service_name'] ?? '',
                    'category_name' => $r['category_name'] ?? '',
                    'project_name' => $r['project_name'] ?? '',
                    'status' => $r['status'] ?: 'Pendiente',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: '',
                    'area' => $areaType
                ];
            }
        } catch(Throwable $e) {}

        // Available suggested tags
        $availableTags = ['Diseño', 'Revisión', 'Web', 'Video', 'Urgente', 'Contenido', 'Campaña', 'Copywriting', 'Estrategia', 'Logotipo'];
        try {
            $stBT = $db->query("SELECT name FROM brand_tags LIMIT 30");
            while ($bt = $stBT->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($bt['name']) && !in_array($bt['name'], $availableTags)) {
                    $availableTags[] = $bt['name'];
                }
            }
            $stTT = $db->query("SELECT tags FROM tm_tasks WHERE tags IS NOT NULL AND tags != '' AND tags != '[]' LIMIT 50");
            while ($tt = $stTT->fetch(PDO::FETCH_ASSOC)) {
                $dec = json_decode($tt['tags'], true) ?: [];
                foreach ((array)$dec as $tg) {
                    if (is_string($tg) && trim($tg) && !in_array(trim($tg), $availableTags)) {
                        $availableTags[] = trim($tg);
                    }
                }
            }
        } catch(Throwable $e) {}

        // Brand Task Groups (Fases de Desarrollo de Marca)
        $brandGroups = [];
        try {
            $stmtBG = $db->query("SELECT id, project_id, name, sort_order, color, start_date, due_date FROM brand_task_groups ORDER BY sort_order ASC, id ASC");
            while ($r = $stmtBG->fetch(PDO::FETCH_ASSOC)) {
                $brandGroups[] = [
                    'id' => (int)$r['id'],
                    'project_id' => (int)$r['project_id'],
                    'name' => $r['name'],
                    'color' => $r['color'] ?: '#6366f1',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: ''
                ];
            }
        } catch(Throwable $e) {}

        echo json_encode([
            'success' => true,
            'projects' => $projects,
            'project_months' => $projectMonths,
            'brand_projects' => $brandProjects,
            'brand_groups' => $brandGroups,
            'project_services' => $projectServices,
            'available_tags' => $availableTags,
            'users' => array_values($userMap)
        ]);
    } catch(Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 2. GET ALL TASKS (with filters, stats & relational data)
// ══════════════════════════════════════════════════════════
if ($action === 'get_all_tasks') {
    checkOverdueTasks($db);
    processRecurringTasks($db);
    
    $filterUser = $_POST['filter_user'] ?? 'me';
    $filterArea = $_POST['filter_area'] ?? 'all';
    $filterFrequency = $_POST['filter_frequency'] ?? 'all';
    $filterDailyObjective = $_POST['filter_daily_objective'] ?? 'all';
    $filterDate = $_POST['filter_date'] ?? '';
    
    $userMap = getUserMap($db);
    $allTasks = [];
    
    try {
        // Pre-fetch Project metadata map and Team Members
        $projectMap = [];
        $projectTeamMap = [];
        try {
            $stmtP = $db->query("SELECT p.id, w.brand_name, p.team_members FROM projects p JOIN work_orders w ON p.work_order_id = w.id");
            while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                $projectMap[$r['id']] = $r['brand_name'];
                $tmRaw = json_decode($r['team_members'] ?: '[]', true) ?: [];
                $tmInts = [];
                foreach ((array)$tmRaw as $uid) {
                    if (is_numeric($uid) && (int)$uid > 0) $tmInts[] = (int)$uid;
                }
                $projectTeamMap[(int)$r['id']] = $tmInts;
            }
            $stmtMP = $db->query("SELECT id, name FROM module_projects");
            while ($r = $stmtMP->fetch(PDO::FETCH_ASSOC)) $projectMap[$r['id'] + 10000] = $r['name'];
        } catch(Throwable $e) {}

        // Pre-fetch Brand Projects team members
        $bpuMap = [];
        try {
            $stBPU = $db->query("SELECT project_id, user_id FROM brand_project_users");
            while ($b = $stBPU->fetch(PDO::FETCH_ASSOC)) {
                $bpuMap[(int)$b['project_id']][] = (int)$b['user_id'];
            }
        } catch(Throwable $e) {}

        // Pre-fetch Calendar Month metadata map
        $monthMap = [];
        try {
            $stmtM = $db->query("
                SELECT pm.id, pm.project_id, pm.month, pm.year, pm.status, pm.content_phase, pm.start_date, pm.due_date, w.brand_name 
                FROM project_months pm 
                JOIN projects p ON pm.project_id = p.id 
                JOIN work_orders w ON p.work_order_id = w.id
            ");
            while ($r = $stmtM->fetch(PDO::FETCH_ASSOC)) {
                $mName = $monthNames[$r['month']] ?? 'Mes ' . $r['month'];
                $monthMap[$r['id']] = [
                    'id' => (int)$r['id'],
                    'project_id' => (int)$r['project_id'],
                    'label' => ($r['brand_name'] ? $r['brand_name'] . ' - ' : '') . $mName . ' ' . $r['year'],
                    'month_name' => $mName,
                    'year' => (int)$r['year'],
                    'status' => $r['status'] ?: 'Activo',
                    'content_phase' => $r['content_phase'] ?: 'En Borrador',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: ''
                ];
            }
        } catch(Throwable $e) {}

        // Pre-fetch Brand Projects map
        $brandProjectMap = [];
        try {
            $stmtBP = $db->query("SELECT id, title, client_name, status, start_date, due_date FROM brand_projects");
            while ($r = $stmtBP->fetch(PDO::FETCH_ASSOC)) {
                $brandProjectMap[$r['id']] = [
                    'id' => (int)$r['id'],
                    'title' => $r['title'],
                    'client_name' => $r['client_name'] ?? '',
                    'status' => $r['status'] ?: 'Active',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: ''
                ];
            }
        } catch(Throwable $e) {}

        // Pre-fetch Brand Groups map (Fases de Marca)
        $brandGroupMap = [];
        try {
            $stmtBG = $db->query("SELECT id, project_id, name, start_date, due_date, color FROM brand_task_groups");
            while ($r = $stmtBG->fetch(PDO::FETCH_ASSOC)) {
                $brandGroupMap[(int)$r['id']] = [
                    'id' => (int)$r['id'],
                    'project_id' => (int)$r['project_id'],
                    'name' => $r['name'],
                    'color' => $r['color'] ?: '#6366f1',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: ''
                ];
            }
        } catch(Throwable $e) {}

        // Pre-fetch Project Services map (Web / Audiovisual)
        $projectServiceMap = [];
        try {
            $stmtPS = $db->query("
                SELECT ps.id, ps.project_id, ps.title, ps.status, ps.start_date, ps.due_date,
                       mp.name as project_name, s.name as service_name, sc.name as category_name
                FROM project_services ps
                LEFT JOIN module_projects mp ON ps.project_id = mp.id
                LEFT JOIN services s ON ps.service_id = s.id
                LEFT JOIN service_categories sc ON s.category_id = sc.id
            ");
            while ($r = $stmtPS->fetch(PDO::FETCH_ASSOC)) {
                $projectServiceMap[$r['id']] = [
                    'id' => (int)$r['id'],
                    'project_id' => (int)$r['project_id'],
                    'project_name' => $r['project_name'] ?: 'Proyecto',
                    'service_name' => $r['service_name'] ?: $r['title'],
                    'category_name' => $r['category_name'] ?: '',
                    'title' => $r['title'] ?: ($r['service_name'] ?: 'Servicio #' . $r['id']),
                    'status' => $r['status'] ?: 'pending',
                    'start_date' => $r['start_date'] ?: '',
                    'due_date' => $r['due_date'] ?: ''
                ];
            }
        } catch(Throwable $e) {}

        // Build WHERE clause
        $whereConditions = ["t.status != 'archived'"];
        
        // User filter
        if ($filterUser === 'me') {
            $whereConditions[] = "(
                JSON_CONTAINS(t.assigned_users, '\"{$userId}\"') OR 
                JSON_CONTAINS(t.assigned_roles, '\"{$userRoleId}\"') OR 
                t.created_by = {$userId}
            )";
        } elseif ($filterUser !== 'all' && is_numeric($filterUser)) {
            $fUid = (int)$filterUser;
            $whereConditions[] = "(
                JSON_CONTAINS(t.assigned_users, '\"{$fUid}\"') OR 
                t.created_by = {$fUid}
            )";
        } elseif (!$isAdmin && $filterUser === 'all') {
            // Empleado que elige "all" pero no es admin sólo ve lo permitido o general
            $whereConditions[] = "(
                JSON_CONTAINS(t.assigned_users, '\"{$userId}\"') OR 
                JSON_CONTAINS(t.assigned_roles, '\"{$userRoleId}\"') OR 
                t.created_by = {$userId} OR 
                t.assigned_users = '[]' OR t.assigned_users IS NULL
            )";
        }

        // Area filter
        if ($filterArea !== 'all' && in_array($filterArea, ['desarrollo_marca', 'desarrollo_web', 'audiovisual', 'general'])) {
            $whereConditions[] = "t.area = " . $db->quote($filterArea);
        }

        // Frequency filter
        if ($filterFrequency !== 'all' && in_array($filterFrequency, ['daily', 'weekly', 'one_time'])) {
            $whereConditions[] = "t.frequency = " . $db->quote($filterFrequency);
        }

        // Daily Objective filter
        if ($filterDailyObjective === '1') {
            $whereConditions[] = "(t.is_daily_objective = 1 OR t.frequency = 'daily')";
            if ($filterDate) {
                $whereConditions[] = "(t.objective_date = " . $db->quote($filterDate) . " OR t.objective_date IS NULL OR t.frequency = 'daily')";
            }
        }

        $whereSQL = implode(' AND ', $whereConditions);
        $stmtT = $db->query("SELECT t.*, u.name as creator_name FROM tm_tasks t LEFT JOIN users u ON t.created_by = u.id WHERE {$whereSQL} ORDER BY t.created_at DESC");
        $tasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);
        
        $tIds = array_column($tasks, 'id');
        $subStats = [];
        if (!empty($tIds)) {
            $in = implode(',', $tIds);
            $st = $db->query("SELECT task_id, COUNT(*) as total, SUM(is_completed) as completed FROM tm_subtasks WHERE task_id IN ($in) GROUP BY task_id");
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $subStats[$r['task_id']] = ['total'=>(int)$r['total'],'completed'=>(int)$r['completed']];
            }
        }
        
        $areaLabels = [
            'desarrollo_marca' => 'Desarrollo de Marca',
            'desarrollo_web' => 'Desarrollo Web',
            'audiovisual' => 'Audiovisual',
            'general' => 'General'
        ];

        $frequencyLabels = [
            'daily' => 'Diaria',
            'weekly' => 'Semanal',
            'one_time' => 'Puntual'
        ];

        foreach ($tasks as $t) {
            $areaKey = !empty($t['area']) ? $t['area'] : 'general';
            $freqKey = !empty($t['frequency']) ? $t['frequency'] : 'one_time';
            $pId = (int)($t['project_id'] ?? 0);
            $pmId = (int)($t['project_month_id'] ?? 0);
            $bpId = (int)($t['brand_project_id'] ?? 0);
            $bgId = (int)($t['brand_group_id'] ?? 0);
            $psId = (int)($t['project_service_id'] ?? 0);

            // resolve users
            $usersArr = json_decode($t['assigned_users']??'[]', true) ?: [];
            if (empty($usersArr)) {
                if ($pId && !empty($projectTeamMap[$pId])) {
                    $usersArr = $projectTeamMap[$pId];
                } elseif ($bpId && !empty($bpuMap[$bpId])) {
                    $usersArr = $bpuMap[$bpId];
                }
            }

            $aUsers = [];
            foreach ($usersArr as $uid) {
                if(isset($userMap[$uid])) {
                    $u = $userMap[$uid];
                    $aUsers[] = ['id'=>$uid, 'name'=>$u['name'], 'avatar'=>$u['avatar'], 'initial'=>$u['initial']];
                }
            }
            
            $allTasks[] = [
                'id' => (int)$t['id'],
                'title' => $t['title'],
                'description' => $t['description'] ?? '',
                'status' => $t['status'] ?: 'new',
                'priority' => $t['priority'] ?: 'medium',
                'frequency' => $freqKey,
                'frequency_label' => $frequencyLabels[$freqKey] ?? 'Puntual',
                'area' => $areaKey,
                'area_label' => $areaLabels[$areaKey] ?? 'General',
                'project_id' => $pId,
                'project_name' => $projectMap[$pId] ?? null,
                'project_month_id' => $pmId,
                'project_month_info' => $monthMap[$pmId] ?? null,
                'brand_project_id' => $bpId,
                'brand_project_info' => $brandProjectMap[$bpId] ?? null,
                'brand_project_title' => isset($brandProjectMap[$bpId]) ? $brandProjectMap[$bpId]['title'] : null,
                'brand_group_id' => $bgId,
                'brand_group_info' => $brandGroupMap[$bgId] ?? null,
                'brand_group_name' => isset($brandGroupMap[$bgId]) ? $brandGroupMap[$bgId]['name'] : null,
                'project_service_id' => $psId,
                'project_service_info' => $projectServiceMap[$psId] ?? null,
                'is_daily_objective' => (int)($t['is_daily_objective'] ?? 0),
                'objective_date' => $t['objective_date'] ?? null,
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
        $stats = [
            'total' => count($allTasks),
            'new' => 0,
            'pending' => 0,
            'overdue' => 0,
            'completed' => 0,
            'approved' => 0,
            'daily_count' => 0,
            'weekly_count' => 0,
            'daily_objectives_total' => 0,
            'daily_objectives_completed' => 0,
            'marca_count' => 0,
            'web_count' => 0,
            'audio_count' => 0
        ];

        $todayStr = date('Y-m-d');
        foreach($allTasks as $t) {
            if (isset($stats[$t['status']])) $stats[$t['status']]++;
            if ($t['frequency'] === 'daily') $stats['daily_count']++;
            if ($t['frequency'] === 'weekly') $stats['weekly_count']++;
            if ($t['area'] === 'desarrollo_marca') $stats['marca_count']++;
            if ($t['area'] === 'desarrollo_web') $stats['web_count']++;
            if ($t['area'] === 'audiovisual') $stats['audio_count']++;

            if ($t['is_daily_objective'] || $t['frequency'] === 'daily') {
                $stats['daily_objectives_total']++;
                if (in_array($t['status'], ['completed', 'approved'])) {
                    $stats['daily_objectives_completed']++;
                }
            }
        }
        
        echo json_encode(['success'=>true, 'tasks'=>$allTasks, 'stats'=>$stats]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 3. CREATE TASK
// ══════════════════════════════════════════════════════════
if ($action === 'create_task') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $frequency = $_POST['frequency'] ?? 'one_time';
    $area = $_POST['area'] ?? 'general';
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $projectMonthId = !empty($_POST['project_month_id']) ? (int)$_POST['project_month_id'] : null;
    $brandProjectId = !empty($_POST['brand_project_id']) ? (int)$_POST['brand_project_id'] : null;
    $brandGroupId = !empty($_POST['brand_group_id']) ? (int)$_POST['brand_group_id'] : null;
    $projectServiceId = !empty($_POST['project_service_id']) ? (int)$_POST['project_service_id'] : null;
    $isDailyObjective = !empty($_POST['is_daily_objective']) ? 1 : 0;
    $objectiveDate = !empty($_POST['objective_date']) ? $_POST['objective_date'] : ($isDailyObjective ? date('Y-m-d') : null);

    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $assignedUsers = $_POST['assigned_users'] ?? '[]';
    $assignedRoles = $_POST['assigned_roles'] ?? '[]';
    $tags = $_POST['tags'] ?? '[]';
    $subtasksJson = $_POST['subtasks'] ?? '[]';

    if (!$title) { echo json_encode(['success'=>false, 'error'=>'El título es obligatorio']); exit; }

    try {
        $stmt = $db->prepare("
            INSERT INTO tm_tasks (
                title, description, priority, status, frequency, area, 
                project_id, project_month_id, brand_project_id, brand_group_id, project_service_id, is_daily_objective, objective_date, 
                start_date, due_date, assigned_users, assigned_roles, tags, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $desc, $priority, $status, $frequency, $area,
            $projectId, $projectMonthId, $brandProjectId, $brandGroupId, $projectServiceId, $isDailyObjective, $objectiveDate,
            $startDate, $dueDate, $assignedUsers, $assignedRoles, $tags, $userId
        ]);
        $taskId = $db->lastInsertId();

        // Sincronizar fechas con el Mes de Calendario vinculado para reiniciar el cronómetro del mes
        syncCalendarMonthDates($db, $projectMonthId, $startDate, $dueDate);

        // Sincronizar fechas con la Fase / Grupo de Marca si está vinculada
        syncBrandGroupDates($db, $brandGroupId, $startDate, $dueDate);

        // Sincronizar estado con el Mes de Calendario vinculado
        require_once __DIR__ . '/../../includes/TaskSyncHelper.php';
        TaskSyncHelper::syncTaskStatusToMonth($db, $taskId, $status);

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

        // Push notification to assigned users
        try {
            $assigned = json_decode($assignedUsers, true) ?: [];
            $assignedIds = array_values(array_diff($assigned, [$userId]));
            if (!empty($assignedIds) && class_exists('PushHelper')) {
                PushHelper::sendPushNotification(
                    $db, 
                    $assignedIds, 
                    "Nueva Tarea Asignada", 
                    "\"{$title}\" ha sido asignada para ti.", 
                    "index.php?module=task_manager", 
                    "task_manager", 
                    ['module' => 'task_manager', 'task_id' => $taskId]
                );
            }
        } catch(Throwable $e) {}

        echo json_encode(['success'=>true, 'task_id'=>$taskId]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 4. GET SINGLE TASK DETAILS
// ══════════════════════════════════════════════════════════
if ($action === 'get_task') {
    $taskId = (int)($_POST['task_id'] ?? $_GET['task_id'] ?? 0);
    if (!$taskId) { echo json_encode(['success'=>false, 'error'=>'Falta task_id']); exit; }
    
    try {
        $stmt = $db->prepare("SELECT * FROM tm_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            $task['assigned_users'] = json_decode($task['assigned_users'] ?? '[]', true) ?: [];
            if (empty($task['assigned_users']) && !empty($task['project_id'])) {
                $stTM = $db->prepare("SELECT team_members FROM projects WHERE id = ?");
                $stTM->execute([(int)$task['project_id']]);
                $tmRaw = $stTM->fetchColumn();
                $task['assigned_users'] = json_decode($tmRaw ?: '[]', true) ?: [];
            } elseif (empty($task['assigned_users']) && !empty($task['brand_project_id'])) {
                $stBPU = $db->prepare("SELECT user_id FROM brand_project_users WHERE project_id = ?");
                $stBPU->execute([(int)$task['brand_project_id']]);
                $task['assigned_users'] = $stBPU->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
            $task['assigned_roles'] = json_decode($task['assigned_roles'] ?? '[]', true) ?: [];
            $task['tags'] = json_decode($task['tags'] ?? '[]', true) ?: [];
            
            // Get subtasks list
            $stmtSub = $db->prepare("SELECT id, title, is_completed FROM tm_subtasks WHERE task_id = ? ORDER BY id ASC");
            $stmtSub->execute([$taskId]);
            $task['subtasks_list'] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

            // Fetch attached sync info
            $task['sync_info'] = null;
            if (!empty($task['project_month_id'])) {
                $st = $db->prepare("
                    SELECT pm.id, pm.project_id, pm.month, pm.year, pm.status, pm.content_phase, pm.start_date, pm.due_date, w.brand_name 
                    FROM project_months pm 
                    JOIN projects p ON pm.project_id = p.id 
                    JOIN work_orders w ON p.work_order_id = w.id 
                    WHERE pm.id = ?
                ");
                $st->execute([$task['project_month_id']]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $mName = $monthNames[$r['month']] ?? 'Mes ' . $r['month'];
                    $task['sync_info'] = [
                        'type' => 'calendar_month',
                        'id' => (int)$r['id'],
                        'title' => ($r['brand_name'] ? $r['brand_name'] . ' - ' : '') . $mName . ' ' . $r['year'],
                        'start_date' => $r['start_date'] ?: '',
                        'due_date' => $r['due_date'] ?: '',
                        'status' => $r['status'] ?: 'Activo',
                        'content_phase' => $r['content_phase'] ?: 'En Borrador'
                    ];
                }
            } elseif (!empty($task['brand_group_id'])) {
                $stBG = $db->prepare("
                    SELECT bg.id, bg.name, bg.start_date, bg.due_date, bp.title as project_title, bp.status as project_status 
                    FROM brand_task_groups bg 
                    LEFT JOIN brand_projects bp ON bg.project_id = bp.id 
                    WHERE bg.id = ?
                ");
                $stBG->execute([(int)$task['brand_group_id']]);
                $r = $stBG->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $task['brand_group_info'] = $r;
                    $task['sync_info'] = [
                        'type' => 'brand_group',
                        'id' => (int)$r['id'],
                        'title' => ($r['project_title'] ? $r['project_title'] . ' · ' : '') . $r['name'],
                        'start_date' => $r['start_date'] ?: '',
                        'due_date' => $r['due_date'] ?: '',
                        'status' => $r['project_status'] ?: 'Active'
                    ];
                }
            } elseif (!empty($task['brand_project_id'])) {
                $st = $db->prepare("SELECT id, title, client_name, status, start_date, due_date FROM brand_projects WHERE id = ?");
                $st->execute([$task['brand_project_id']]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $task['sync_info'] = [
                        'type' => 'brand_project',
                        'id' => (int)$r['id'],
                        'title' => $r['title'],
                        'start_date' => $r['start_date'] ?: '',
                        'due_date' => $r['due_date'] ?: '',
                        'status' => $r['status'] ?: 'Active'
                    ];
                }
            } elseif (!empty($task['project_service_id'])) {
                $st = $db->prepare("
                    SELECT ps.id, ps.project_id, ps.title, ps.status, ps.start_date, ps.due_date,
                           mp.name as project_name, s.name as service_name, sc.name as category_name
                    FROM project_services ps
                    LEFT JOIN module_projects mp ON ps.project_id = mp.id
                    LEFT JOIN services s ON ps.service_id = s.id
                    LEFT JOIN service_categories sc ON s.category_id = sc.id
                    WHERE ps.id = ?
                ");
                $st->execute([$task['project_service_id']]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $task['sync_info'] = [
                        'type' => 'project_service',
                        'id' => (int)$r['id'],
                        'title' => ($r['project_name'] ? $r['project_name'] . ' - ' : '') . ($r['title'] ?: $r['service_name']),
                        'start_date' => $r['start_date'] ?: '',
                        'due_date' => $r['due_date'] ?: '',
                        'status' => $r['status'] ?: 'pending'
                    ];
                }
            }

            echo json_encode(['success'=>true, 'task'=>$task]);
        } else {
            echo json_encode(['success'=>false, 'error'=>'Tarea no encontrada']);
        }
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 5. UPDATE TASK DETAILS
// ══════════════════════════════════════════════════════════
if ($action === 'update_task_details') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $frequency = $_POST['frequency'] ?? 'one_time';
    $area = $_POST['area'] ?? 'general';
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $projectMonthId = !empty($_POST['project_month_id']) ? (int)$_POST['project_month_id'] : null;
    $brandProjectId = !empty($_POST['brand_project_id']) ? (int)$_POST['brand_project_id'] : null;
    $brandGroupId = !empty($_POST['brand_group_id']) ? (int)$_POST['brand_group_id'] : null;
    $projectServiceId = !empty($_POST['project_service_id']) ? (int)$_POST['project_service_id'] : null;
    $isDailyObjective = !empty($_POST['is_daily_objective']) ? 1 : 0;
    $objectiveDate = !empty($_POST['objective_date']) ? $_POST['objective_date'] : ($isDailyObjective ? date('Y-m-d') : null);

    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
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
        $stmt = $db->prepare("
            UPDATE tm_tasks SET 
                title = ?, description = ?, priority = ?, status = ?, frequency = ?, area = ?, 
                project_id = ?, project_month_id = ?, brand_project_id = ?, brand_group_id = ?, project_service_id = ?, is_daily_objective = ?, objective_date = ?, 
                start_date = ?, due_date = ?, assigned_users = ?, assigned_roles = ?, tags = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $desc, $priority, $status, $frequency, $area,
            $projectId, $projectMonthId, $brandProjectId, $brandGroupId, $projectServiceId, $isDailyObjective, $objectiveDate,
            $startDate, $dueDate, $assignedUsers, $assignedRoles, $tags, $taskId
        ]);

        // Sincronizar fechas con el Mes de Calendario vinculado para reiniciar el cronómetro del mes
        syncCalendarMonthDates($db, $projectMonthId, $startDate, $dueDate);

        // Sincronizar fechas con la Fase / Grupo de Marca si está vinculada
        syncBrandGroupDates($db, $brandGroupId, $startDate, $dueDate);

        // Sincronizar estado con el Mes de Calendario vinculado
        require_once __DIR__ . '/../../includes/TaskSyncHelper.php';
        TaskSyncHelper::syncTaskStatusToMonth($db, $taskId, $status);
        
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

// ══════════════════════════════════════════════════════════
// 6. UPDATE STATUS (Drag & Drop or direct update)
// ══════════════════════════════════════════════════════════
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

        // Sincronizar estado con el Mes de Calendario vinculado
        require_once __DIR__ . '/../../includes/TaskSyncHelper.php';
        TaskSyncHelper::syncTaskStatusToMonth($db, $taskId, $newStatus);

        // Check if this task is part of a linked entity and whether all its tasks are done
        $completionNotice = null;
        if (in_array($newStatus, ['completed', 'approved'])) {
            $taskInfoStmt = $db->prepare("SELECT project_month_id, brand_project_id, project_service_id FROM tm_tasks WHERE id = ?");
            $taskInfoStmt->execute([$taskId]);
            $tRow = $taskInfoStmt->fetch(PDO::FETCH_ASSOC);
            if ($tRow) {
                if (!empty($tRow['project_month_id'])) {
                    $pmId = (int)$tRow['project_month_id'];
                    $chk = $db->query("SELECT COUNT(*) as pending_cnt FROM tm_tasks WHERE project_month_id = {$pmId} AND status NOT IN ('completed', 'approved') AND id != {$taskId}")->fetchColumn();
                    if ((int)$chk === 0) {
                        $completionNotice = [
                            'entity_type' => 'calendar_month',
                            'entity_id' => $pmId,
                            'message' => '¡Todas las tareas de este mes de calendario están completadas! Puedes avanzar la fase a Aprobado o Publicado.'
                        ];
                    }
                } elseif (!empty($tRow['brand_project_id'])) {
                    $bpId = (int)$tRow['brand_project_id'];
                    $chk = $db->query("SELECT COUNT(*) as pending_cnt FROM tm_tasks WHERE brand_project_id = {$bpId} AND status NOT IN ('completed', 'approved') AND id != {$taskId}")->fetchColumn();
                    if ((int)$chk === 0) {
                        $completionNotice = [
                            'entity_type' => 'brand_project',
                            'entity_id' => $bpId,
                            'message' => '¡Todas las tareas de este proyecto de marca están completadas! Puedes marcar el proyecto como Completed.'
                        ];
                    }
                } elseif (!empty($tRow['project_service_id'])) {
                    $psId = (int)$tRow['project_service_id'];
                    $chk = $db->query("SELECT COUNT(*) as pending_cnt FROM tm_tasks WHERE project_service_id = {$psId} AND status NOT IN ('completed', 'approved') AND id != {$taskId}")->fetchColumn();
                    if ((int)$chk === 0) {
                        $completionNotice = [
                            'entity_type' => 'project_service',
                            'entity_id' => $psId,
                            'message' => '¡Todas las tareas de este servicio están completadas! Puedes marcar el servicio como completed.'
                        ];
                    }
                }
            }
        }

        echo json_encode(['success'=>true, 'completion_notice' => $completionNotice]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 6c. UPDATE TASK DATES (Gantt Drag & Drop or Resizing)
// ══════════════════════════════════════════════════════════
if ($action === 'update_task_dates') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'error' => 'ID de tarea inválido']);
        exit;
    }
    
    if ($startDate) $startDate = str_replace('T', ' ', substr($startDate, 0, 19));
    if ($dueDate) $dueDate = str_replace('T', ' ', substr($dueDate, 0, 19));

    if ($startDate && $dueDate && strtotime($startDate) > strtotime($dueDate)) {
        $temp = $startDate;
        $startDate = $dueDate;
        $dueDate = $temp;
    }

    try {
        $stmt = $db->prepare("UPDATE tm_tasks SET start_date = ?, due_date = ? WHERE id = ?");
        $stmt->execute([$startDate, $dueDate, $taskId]);

        // Sincronizar fechas con el Mes de Calendario o Grupo de Marca vinculado
        $stMCheck = $db->prepare("SELECT project_month_id, brand_group_id FROM tm_tasks WHERE id = ?");
        $stMCheck->execute([$taskId]);
        $rowLinks = $stMCheck->fetch(PDO::FETCH_ASSOC);
        if ($rowLinks) {
            $linkedPmId = (int)($rowLinks['project_month_id'] ?? 0);
            $linkedBgId = (int)($rowLinks['brand_group_id'] ?? 0);
            if ($linkedPmId > 0 && ($startDate || $dueDate)) {
                syncCalendarMonthDates($db, $linkedPmId, $startDate, $dueDate);
            }
            if ($linkedBgId > 0 && ($startDate || $dueDate)) {
                syncBrandGroupDates($db, $linkedBgId, $startDate, $dueDate);
            }
        }

        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'start_date' => $startDate,
            'due_date' => $dueDate
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 6b. UPDATE ENTITY PROCESS PHASE / STATUS (Calendar, Brand, Web, Audio)
// ══════════════════════════════════════════════════════════
if ($action === 'update_entity_process_phase') {
    $entityType = $_POST['entity_type'] ?? ''; // 'calendar_month', 'brand_project', 'project_service'
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $newPhase = trim($_POST['new_phase'] ?? '');
    $newStatus = trim($_POST['new_status'] ?? '');

    if (!$entityId || !$entityType || (!$newPhase && !$newStatus)) {
        echo json_encode(['success' => false, 'error' => 'Parámetros insuficientes']);
        exit;
    }

    try {
        if ($entityType === 'calendar_month') {
            $updates = [];
            $params = [];
            if ($newPhase) {
                $updates[] = "content_phase = ?";
                $params[] = $newPhase;
            }
            if ($newStatus) {
                $updates[] = "status = ?";
                $params[] = $newStatus;
            }
            $params[] = $entityId;
            $sql = "UPDATE project_months SET " . implode(', ', $updates) . " WHERE id = ?";
            $db->prepare($sql)->execute($params);
            echo json_encode([
                'success' => true, 
                'message' => 'Fase de calendario actualizada a: ' . ($newPhase ?: $newStatus),
                'content_phase' => $newPhase,
                'status' => $newStatus
            ]);
            exit;
        } elseif ($entityType === 'brand_project') {
            $statusVal = $newStatus ?: $newPhase;
            $db->prepare("UPDATE brand_projects SET status = ? WHERE id = ?")->execute([$statusVal, $entityId]);
            echo json_encode([
                'success' => true, 
                'message' => 'Estado de marca actualizado a: ' . $statusVal,
                'status' => $statusVal
            ]);
            exit;
        } elseif ($entityType === 'project_service') {
            $statusVal = $newStatus ?: $newPhase;
            $db->prepare("UPDATE project_services SET status = ? WHERE id = ?")->execute([$statusVal, $entityId]);
            echo json_encode([
                'success' => true, 
                'message' => 'Estado del servicio actualizado a: ' . $statusVal,
                'status' => $statusVal
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Tipo de entidad no reconocido']);
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ══════════════════════════════════════════════════════════
// 7. TOGGLE DAILY OBJECTIVE STATUS / COMPLETION
// ══════════════════════════════════════════════════════════
if ($action === 'toggle_daily_objective') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $toggleType = $_POST['toggle_type'] ?? 'mark_objective'; // 'mark_objective' or 'toggle_completion'
    
    if (!$taskId) { echo json_encode(['success'=>false]); exit; }

    try {
        if ($toggleType === 'toggle_completion') {
            $stmt = $db->prepare("SELECT status FROM tm_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $curr = $stmt->fetchColumn();
            $newStatus = in_array($curr, ['completed', 'approved']) ? 'pending' : 'completed';
            $stmtUp = $db->prepare("UPDATE tm_tasks SET status = ? WHERE id = ?");
            $stmtUp->execute([$newStatus, $taskId]);

            // Sincronizar estado con el Mes de Calendario vinculado
            require_once __DIR__ . '/../../includes/TaskSyncHelper.php';
            TaskSyncHelper::syncTaskStatusToMonth($db, $taskId, $newStatus);

            echo json_encode(['success'=>true, 'new_status'=>$newStatus]);
        } else {
            // Toggle whether it is a daily objective
            $stmt = $db->prepare("SELECT is_daily_objective FROM tm_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $isObj = (int)$stmt->fetchColumn();
            $newObj = $isObj ? 0 : 1;
            $newDate = $newObj ? date('Y-m-d') : null;
            $stmtUp = $db->prepare("UPDATE tm_tasks SET is_daily_objective = ?, objective_date = COALESCE(objective_date, ?) WHERE id = ?");
            $stmtUp->execute([$newObj, $newDate, $taskId]);
            echo json_encode(['success'=>true, 'is_daily_objective'=>$newObj]);
        }
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 8. GET DAILY OBJECTIVES & EVALUATION FOR SPECIFIC DATE
// ══════════════════════════════════════════════════════════
if ($action === 'get_daily_objectives') {
    $targetDate = $_POST['date'] ?? date('Y-m-d');
    $targetUser = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $userId;
    
    try {
        // Fetch tasks marked as daily objectives or daily frequency for this user and date
        $whereUser = "(JSON_CONTAINS(t.assigned_users, '\"{$targetUser}\"') OR t.created_by = {$targetUser})";
        $stmtObj = $db->prepare("
            SELECT t.*, u.name as creator_name 
            FROM tm_tasks t 
            LEFT JOIN users u ON t.created_by = u.id 
            WHERE (t.is_daily_objective = 1 OR t.frequency = 'daily') 
            AND (t.objective_date = ? OR t.objective_date IS NULL OR t.frequency = 'daily')
            AND t.status != 'archived'
            AND {$whereUser}
            ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.id DESC
        ");
        $stmtObj->execute([$targetDate]);
        $objectives = $stmtObj->fetchAll(PDO::FETCH_ASSOC);

        $total = count($objectives);
        $completed = 0;
        foreach ($objectives as &$obj) {
            $isDone = in_array($obj['status'], ['completed', 'approved']);
            if ($isDone) $completed++;
            $obj['is_completed'] = $isDone ? 1 : 0;
        }
        $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        // Fetch existing evaluation if present
        $stmtEval = $db->prepare("
            SELECT e.*, u.name as evaluator_name 
            FROM tm_daily_evaluations e 
            LEFT JOIN users u ON e.evaluated_by = u.id 
            WHERE e.user_id = ? AND e.evaluation_date = ?
        ");
        $stmtEval->execute([$targetUser, $targetDate]);
        $evalRecord = $stmtEval->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'date' => $targetDate,
            'user_id' => $targetUser,
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
            'objectives' => $objectives,
            'evaluation' => $evalRecord ?: null
        ]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 9. SAVE DAILY OBJECTIVES EVALUATION
// ══════════════════════════════════════════════════════════
if ($action === 'save_daily_evaluation') {
    $targetUser = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $userId;
    $evalDate = $_POST['evaluation_date'] ?? date('Y-m-d');
    $score = !empty($_POST['score']) ? max(1, min(5, (int)$_POST['score'])) : 3;
    $notes = trim($_POST['evaluation_notes'] ?? '');
    
    try {
        // Re-count active objectives for this date
        $whereUser = "(JSON_CONTAINS(t.assigned_users, '\"{$targetUser}\"') OR t.created_by = {$targetUser})";
        $stmtObj = $db->prepare("
            SELECT t.status 
            FROM tm_tasks t 
            WHERE (t.is_daily_objective = 1 OR t.frequency = 'daily') 
            AND (t.objective_date = ? OR t.objective_date IS NULL OR t.frequency = 'daily')
            AND t.status != 'archived'
            AND {$whereUser}
        ");
        $stmtObj->execute([$evalDate]);
        $rows = $stmtObj->fetchAll(PDO::FETCH_ASSOC);

        $total = count($rows);
        $completed = 0;
        foreach ($rows as $r) {
            if (in_array($r['status'], ['completed', 'approved'])) $completed++;
        }
        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        // Determine performance level
        if ($percentage >= 90) {
            $perfLevel = 'excellent';
        } elseif ($percentage >= 70) {
            $perfLevel = 'good';
        } elseif ($percentage >= 40) {
            $perfLevel = 'average';
        } else {
            $perfLevel = 'poor';
        }

        $stmtSave = $db->prepare("
            INSERT INTO tm_daily_evaluations (
                user_id, evaluation_date, total_objectives, completed_objectives, 
                compliance_percentage, score, performance_level, evaluation_notes, evaluated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_objectives = VALUES(total_objectives),
                completed_objectives = VALUES(completed_objectives),
                compliance_percentage = VALUES(compliance_percentage),
                score = VALUES(score),
                performance_level = VALUES(performance_level),
                evaluation_notes = VALUES(evaluation_notes),
                evaluated_by = VALUES(evaluated_by),
                updated_at = NOW()
        ");
        $stmtSave->execute([
            $targetUser, $evalDate, $total, $completed,
            $percentage, $score, $perfLevel, $notes, $userId
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $targetUser,
                'evaluation_date' => $evalDate,
                'total_objectives' => $total,
                'completed_objectives' => $completed,
                'compliance_percentage' => $percentage,
                'score' => $score,
                'performance_level' => $perfLevel,
                'evaluation_notes' => $notes
            ]
        ]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 10. GET DAILY EVALUATION HISTORY
// ══════════════════════════════════════════════════════════
if ($action === 'get_daily_evaluation_history') {
    $targetUser = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $userId;
    $limit = !empty($_POST['limit']) ? min(60, (int)$_POST['limit']) : 30;

    try {
        $whereUser = $isAdmin && ($_POST['filter_all'] ?? false) ? "1=1" : "e.user_id = {$targetUser}";
        $stmtH = $db->query("
            SELECT e.*, u.name as user_name, u.avatar as user_avatar, ev.name as evaluator_name 
            FROM tm_daily_evaluations e 
            JOIN users u ON e.user_id = u.id 
            LEFT JOIN users ev ON e.evaluated_by = ev.id 
            WHERE {$whereUser} 
            ORDER BY e.evaluation_date DESC 
            LIMIT {$limit}
        ");
        $history = $stmtH->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'history'=>$history]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 11. SUBTASKS TOGGLE & DELETE
// ══════════════════════════════════════════════════════════
if ($action === 'toggle_subtask') {
    $subtaskId = (int)($_POST['subtask_id'] ?? 0);
    if (!$subtaskId) { echo json_encode(['success'=>false]); exit; }
    try {
        $stmt = $db->prepare("UPDATE tm_subtasks SET is_completed = NOT is_completed WHERE id = ?");
        $stmt->execute([$subtaskId]);
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
// 12. DELETE TASK
// ══════════════════════════════════════════════════════════
if ($action === 'delete_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if (!$taskId) { echo json_encode(['success'=>false]); exit; }
    try {
        $db->exec("DELETE FROM tm_subtasks WHERE task_id = $taskId");
        $db->exec("DELETE FROM tm_tasks WHERE id = $taskId");
        echo json_encode(['success'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['success'=>false, 'error'=>'Acción desconocida']);

