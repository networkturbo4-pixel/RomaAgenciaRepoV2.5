<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stM = $db->prepare("SELECT month_id FROM month_posts WHERE id = ?");
    $stM->execute([$id]);
    $monthId = (int)$stM->fetchColumn();

    $stmt = $db->prepare("DELETE FROM month_posts WHERE id = ?");
    $stmt->execute([$id]);

    if ($monthId > 0) {
        try {
            require_once '../../includes/TaskSyncHelper.php';
            TaskSyncHelper::syncMonthPostsCompletion($db, $monthId);
        } catch(Throwable $eSync) {}
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
