<?php
// modules/services/ajax_get_service.php
session_start();
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM services WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($service) {
        $stmtFeat = $db->prepare("SELECT title, description, type FROM service_features WHERE service_id = :id ORDER BY id ASC");
        $stmtFeat->execute([':id' => $id]);
        $features = $stmtFeat->fetchAll(PDO::FETCH_ASSOC);
        
        $service['features'] = $features;

        echo json_encode(['success' => true, 'data' => $service]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Servicio no encontrado']);
    }
} catch (PDOException $e) {
    error_log("Error fetching service: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener el servicio']);
}
