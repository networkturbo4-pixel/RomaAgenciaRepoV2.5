<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$monthId = $_POST['month_id'] ?? null;
$agenda = $_POST['agenda_text'] ?? '';

if (!$monthId) {
    echo json_encode(['success' => false, 'error' => 'Month ID es requerido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("UPDATE project_months SET agenda_text = ? WHERE id = ?");
    $stmt->execute([$agenda, $monthId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
