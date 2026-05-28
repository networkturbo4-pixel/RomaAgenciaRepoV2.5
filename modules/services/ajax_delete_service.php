<?php
// modules/services/ajax_delete_service.php
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
        $db->beginTransaction();

        // Eliminar las características del servicio primero para evitar error de llave foránea
        $stmtFeatures = $db->prepare("DELETE FROM service_features WHERE service_id = :id");
        $stmtFeatures->execute([':id' => $id]);

        $stmt = $db->prepare("DELETE FROM services WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Servicio eliminado correctamente']);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error deleting service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el servicio']);
    }
}
