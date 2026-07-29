<?php
// modules/projects/ajax_delete_task_group.php
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

    // Check if it has cards
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM task_cards WHERE task_group_id = ?");
    $stmtCount->execute([$id]);
    $count = $stmtCount->fetchColumn();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar: El grupo contiene tarjetas. Elimine las tarjetas primero.']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM task_groups WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Grupo eliminado']);
} catch (Exception $e) {
    error_log("Error borrando grupo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
