<?php
header('Content-Type: application/json');
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM project_services WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Servicio eliminado correctamente']);
    } catch (PDOException $e) {
        error_log("Error deleting project service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el servicio']);
    }
}
?>
