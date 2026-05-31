<?php
// ajax_portal.php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';
require_once 'includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            $dni = $_POST['dni'] ?? '';
            if (!$dni) {
                echo json_encode(['success' => false, 'error' => 'DNI requerido']);
                exit();
            }

            $stmt = $db->prepare("SELECT id, name, portal_enabled FROM clients WHERE dni = ?");
            $stmt->execute([$dni]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && $client['portal_enabled']) {
                $_SESSION['client_portal_id'] = $client['id'];
                
                // Log access
                $ip = $_SERVER['REMOTE_ADDR'];
                $ua = $_SERVER['HTTP_USER_AGENT'];
                $db->prepare("INSERT INTO client_portal_logs (client_id, ip_address, user_agent) VALUES (?, ?, ?)")
                   ->execute([$client['id'], $ip, $ua]);
                   
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'DNI incorrecto o acceso deshabilitado']);
            }
            break;

        case 'logout':
            unset($_SESSION['client_portal_id']);
            echo json_encode(['success' => true]);
            break;

        case 'get_dashboard':
            if (!isset($_SESSION['client_portal_id'])) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit();
            }
            $client_id = $_SESSION['client_portal_id'];

            // Get client info
            $stmt = $db->prepare("SELECT name FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get payments
            $stmt = $db->prepare("SELECT note_code, company_name, start_date, total, status, public_token, schedule_json FROM payment_notes WHERE client_id = ? ORDER BY id DESC");
            $stmt->execute([$client_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get active projects (from work_orders/project_months)
            // Need to join clients -> client_brands -> work_orders -> projects -> project_months
            $stmt = $db->prepare("
                SELECT pm.id, pm.pin as public_pin, pm.drive_folder_id, pm.month, pm.year, pm.status as project_status, cb.name as brand_name, cb.logo as brand_logo, p.team_members
                FROM project_months pm
                JOIN projects p ON pm.project_id = p.id
                JOIN work_orders w ON p.work_order_id = w.id
                JOIN client_brands cb ON w.brand_name = cb.name
                WHERE cb.client_id = ?
                ORDER BY pm.id DESC
            ");
            $stmt->execute([$client_id]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch users so we can resolve team_members
            $stmtUsers = $db->query("SELECT id, name, avatar FROM users");
            $allUsers = [];
            foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $allUsers[$u['id']] = $u;
            }

            // Fetch post counts for projects
            $projectIds = array_column($projects, 'id');
            $postCounts = [];
            if (!empty($projectIds)) {
                $in = str_repeat('?,', count($projectIds) - 1) . '?';
                $stmtCount = $db->prepare("SELECT month_id, COUNT(*) as count FROM month_posts WHERE month_id IN ($in) GROUP BY month_id");
                $stmtCount->execute($projectIds);
                foreach ($stmtCount->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $postCounts[$row['month_id']] = (int)$row['count'];
                }
            }

            // Process projects
            $driveFolders = [];
            $monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            foreach ($projects as &$p) {
                if ($p['drive_folder_id']) {
                    $driveFolders[] = $p['drive_folder_id'];
                }
                $m = (int)$p['month'];
                $p['month_name'] = ($m >= 1 && $m <= 12 ? $monthNames[$m] : 'Mes') . ' ' . $p['year'];
                $p['post_count'] = $postCounts[$p['id']] ?? 0;
                
                // resolve team members
                $tms = json_decode($p['team_members'] ?: '[]', true) ?: [];
                $resolvedMembers = [];
                foreach ($tms as $uid) {
                    if (isset($allUsers[$uid])) {
                        $resolvedMembers[] = $allUsers[$uid];
                    }
                }
                $p['team'] = $resolvedMembers;
            }

            // Fetch Design Tasks for this client
            $stmt = $db->prepare("SELECT id, title as name, priority, status, due_date, drive_folder_id, description, assigned_to, external_links FROM design_tasks WHERE deleted_at IS NULL AND client_id = ? ORDER BY created_at DESC");
            $stmt->execute([$client_id]);
            $designTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch subtasks for these tasks
            $taskIds = array_column($designTasks, 'id');
            $subtasksByTask = [];
            $advancesByTask = [];
            if (!empty($taskIds)) {
                $in = str_repeat('?,', count($taskIds) - 1) . '?';
                $stmtSub = $db->prepare("SELECT design_task_id, id, title, is_completed, due_date FROM design_task_subtasks WHERE design_task_id IN ($in) ORDER BY created_at ASC");
                $stmtSub->execute($taskIds);
                foreach ($stmtSub->fetchAll(PDO::FETCH_ASSOC) as $sub) {
                    $subtasksByTask[$sub['design_task_id']][] = $sub;
                }
                
                $stmtAtt = $db->prepare("SELECT design_task_id, id, file_path, file_name, created_at FROM design_task_attachments WHERE attachment_type = 'avance' AND design_task_id IN ($in) ORDER BY created_at ASC");
                $stmtAtt->execute($taskIds);
                foreach ($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $att) {
                    $advancesByTask[$att['design_task_id']][] = $att;
                }
            }

            // Process design tasks
            foreach ($designTasks as &$dt) {
                if ($dt['drive_folder_id']) {
                    $driveFolders[] = $dt['drive_folder_id'];
                }
                // resolve team members
                $tms = json_decode($dt['assigned_to'] ?: '[]', true) ?: [];
                $resolvedMembers = [];
                foreach ($tms as $uid) {
                    if (isset($allUsers[$uid])) {
                        $resolvedMembers[] = $allUsers[$uid];
                    }
                }
                $dt['team'] = $resolvedMembers;
                $dt['subtasks'] = $subtasksByTask[$dt['id']] ?? [];
                $dt['advances'] = $advancesByTask[$dt['id']] ?? [];
                $dt['external_links'] = json_decode($dt['external_links'] ?: '[]', true) ?: [];
            }

            echo json_encode([
                'success' => true,
                'client' => $client,
                'payments' => $payments,
                'projects' => $projects,
                'designTasks' => $designTasks,
                'folders' => $driveFolders
            ]);
            break;

        case 'get_drive_files':
            if (!isset($_SESSION['client_portal_id'])) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit();
            }
            $client_id = $_SESSION['client_portal_id'];

            $targetFolderId = $_POST['folder_id'] ?? $_GET['folder_id'] ?? null;
            $allFiles = [];

            if ($targetFolderId) {
                $drive = new GoogleDriveHelper();
                if (!$drive->isConfigured()) {
                    echo json_encode(['success' => false, 'error' => 'Drive no configurado']);
                    exit();
                }
                // Fetch specific subfolder
                $files = $drive->listFiles($targetFolderId);
                if ($files) $allFiles = $files;
            } else {
                // Present assigned folders as the root directory
                
                // Get project month folders
                $stmt = $db->prepare("
                    SELECT pm.drive_folder_id, pm.month, pm.year, cb.name as brand_name
                    FROM project_months pm
                    JOIN projects p ON pm.project_id = p.id
                    JOIN work_orders w ON p.work_order_id = w.id
                    JOIN client_brands cb ON w.brand_name = cb.name
                    WHERE cb.client_id = ? AND pm.drive_folder_id IS NOT NULL
                ");
                $stmt->execute([$client_id]);
                $projectFolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

                foreach ($projectFolders as $pf) {
                    $m = (int)$pf['month'];
                    $mName = ($m >= 1 && $m <= 12 ? $monthNames[$m] : 'Mes');
                    $folderName = $mName . ' ' . $pf['year'];
                    $allFiles[] = [
                        'id' => $pf['drive_folder_id'],
                        'name' => $folderName,
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'webViewLink' => '',
                        'webContentLink' => '',
                        'category' => 'Calendario'
                    ];
                }

                // Get design task folders
                $stmt = $db->prepare("SELECT drive_folder_id, title FROM design_tasks WHERE deleted_at IS NULL AND client_id = ? AND drive_folder_id IS NOT NULL");
                $stmt->execute([$client_id]);
                $designFolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($designFolders as $df) {
                    $allFiles[] = [
                        'id' => $df['drive_folder_id'],
                        'name' => $df['title'],
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'webViewLink' => '',
                        'webContentLink' => '',
                        'category' => 'Diseño'
                    ];
                }
            }

            echo json_encode(['success' => true, 'files' => $allFiles]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
