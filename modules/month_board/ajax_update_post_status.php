<?php
// modules/month_board/ajax_update_post_status.php
header('Content-Type: application/json');
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
    
    // Notify team members
    $stmtProj = $db->prepare("SELECT p.team_members, mp.concept FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id JOIN projects p ON pm.project_id = p.id WHERE mp.id = ?");
    $stmtProj->execute([$id]);
    $proj = $stmtProj->fetch();
    if ($proj && !empty($proj['team_members'])) {
        $assignedIds = json_decode($proj['team_members'], true) ?: [];
        $userId = $_SESSION['user_id'] ?? 0;
        $assignedIds = array_values(array_diff($assignedIds, [$userId]));
        if (!empty($assignedIds)) {
            PushHelper::sendPushNotification($db, $assignedIds, "Estado de Post Actualizado", "El post '{$proj['concept']}' cambió a: {$status}", "index.php?module=calendar", "calendar", ['module' => 'calendar']);
        }
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
