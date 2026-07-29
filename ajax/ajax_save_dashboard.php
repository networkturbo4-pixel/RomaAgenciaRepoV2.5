<?php
// ajax/ajax_save_dashboard.php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$dbClass = new Database();
$db = $dbClass->getConnection();

$layout = $_POST['layout'] ?? '';

if (empty($layout)) {
    echo json_encode(['success' => false, 'message' => 'Layout vacío']);
    exit();
}

try {
    $stmt = $db->prepare("UPDATE users SET dashboard_layout = ? WHERE id = ?");
    $stmt->execute([$layout, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
