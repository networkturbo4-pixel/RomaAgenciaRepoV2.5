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

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $phase = isset($_POST['content_phase']) ? $_POST['content_phase'] : '';

    if (!$id || !$phase) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    $stmtUpdate = $db->prepare("UPDATE project_months SET content_phase = ? WHERE id = ?");
    $stmtUpdate->execute([$phase, $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
