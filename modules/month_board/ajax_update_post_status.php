<?php
// modules/month_board/ajax_update_post_status.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
require_once '../../includes/PushHelper.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

try {
    $db = (new Database())->getConnection();
    
    // Este endpoint es usado por la vista pública, por lo que actualizamos el estado
    // sin comprobar la sesión del admin.
    $stmt = $db->prepare("UPDATE month_posts SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    // Sincronizar finalización del mes hacia las tareas si los posts llegaron al 100%
    try {
        $stMonthId = $db->prepare("SELECT month_id FROM month_posts WHERE id = ?");
        $stMonthId->execute([$id]);
        $monthId = (int)$stMonthId->fetchColumn();
        if ($monthId > 0) {
            require_once '../../includes/TaskSyncHelper.php';
            TaskSyncHelper::syncMonthPostsCompletion($db, $monthId);
        }
    } catch (Throwable $eSync) {}
    
    // Notify team members and administrators
    try {
        $stmtProj = $db->prepare("
            SELECT p.team_members, mp.concept, mp.month_id, w.brand_name 
            FROM month_posts mp 
            JOIN project_months pm ON mp.month_id = pm.id 
            JOIN projects p ON pm.project_id = p.id 
            LEFT JOIN work_orders w ON p.work_order_id = w.id
            WHERE mp.id = ?
        ");
        $stmtProj->execute([$id]);
        $proj = $stmtProj->fetch(PDO::FETCH_ASSOC);
        if ($proj) {
            $assignedIds = [];
            if (!empty($proj['team_members'])) {
                $assignedIds = json_decode($proj['team_members'], true) ?: [];
            }
            $userId = $_SESSION['user_id'] ?? 0;
            if ($userId > 0) {
                $assignedIds = array_diff($assignedIds, [$userId]);
            }
            
            // Always include administrators
            $adminStmt = $db->query("SELECT id FROM users WHERE role_id = 1");
            if ($adminStmt) {
                $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($adminIds as $aid) {
                    if ($userId == 0 || (int)$aid !== (int)$userId) {
                        $assignedIds[] = (int)$aid;
                    }
                }
            }
            $allRecipients = array_values(array_unique(array_filter(array_map('intval', $assignedIds))));
            
            if (!empty($allRecipients)) {
                require_once __DIR__ . '/../../includes/NotificationHelper.php';
                $brandSuffix = !empty($proj['brand_name']) ? " ({$proj['brand_name']})" : '';
                $targetMonthId = (int)($proj['month_id'] ?? $monthId ?? 0);
                $link = "index.php?module=month_board&id={$targetMonthId}&open_post={$id}";

                NotificationHelper::send([
                    'user_id' => $allRecipients,
                    'title'   => "Estado de Post Actualizado{$brandSuffix}",
                    'message' => "El post '{$proj['concept']}' cambió a: {$status}",
                    'link'    => $link,
                    'type'    => 'calendar',
                    'icon'    => 'ph-calendar-check'
                ], $db);
            }
        }
    } catch (Throwable $ePush) {
        error_log("Push Helper error in ajax_update_post_status: " . $ePush->getMessage());
    }
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
