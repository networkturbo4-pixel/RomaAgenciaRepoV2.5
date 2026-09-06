<?php
// modules/suppliers/ajax_delete_supplier.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de proveedor inválido']);
    exit();
}

try {
    // Delete payments and services associated
    $stmt1 = $db->prepare("DELETE FROM supplier_payments WHERE supplier_id = ?");
    $stmt1->execute([$id]);

    $stmt2 = $db->prepare("DELETE FROM supplier_services WHERE supplier_id = ?");
    $stmt2->execute([$id]);

    $stmt3 = $db->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt3->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Proveedor eliminado correctamente']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
