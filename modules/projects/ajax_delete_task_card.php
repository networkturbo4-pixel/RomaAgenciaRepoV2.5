<?php
// modules/projects/ajax_delete_task_card.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $db->prepare("UPDATE task_cards SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    // Log the deletion
    $stmtLog = $db->prepare("INSERT INTO task_card_logs (task_card_id, user_id, action) VALUES (?, ?, ?)");
    $stmtLog->execute([$id, $_SESSION['user_id'], 'Envió la tarjeta a la papelera']);

    echo json_encode(['success' => true, 'message' => 'Tarjeta eliminada']);
} catch (Exception $e) {
    error_log("Error borrando tarjeta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
