<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stmt = $db->prepare("SELECT * FROM project_months WHERE id = ?");
    $stmt->execute([$id]);
    $month = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($month) {
        echo json_encode(['success' => true, 'data' => $month]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Mes no encontrado']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
