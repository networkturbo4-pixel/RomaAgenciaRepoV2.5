<?php
// modules/services/ajax_restore_service.php
session_start();
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE services SET deleted_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Servicio restaurado correctamente']);
    } catch (PDOException $e) {
        error_log("Error restoring service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al restaurar el servicio']);
    }
}
