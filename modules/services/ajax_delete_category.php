<?php
// modules/services/ajax_delete_category.php
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
        $stmt = $db->prepare("DELETE FROM service_categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Categoría eliminada correctamente']);
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la categoría. Asegúrate de que no esté en uso.']);
    }
}
