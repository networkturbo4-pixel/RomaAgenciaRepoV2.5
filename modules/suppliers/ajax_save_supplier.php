<?php
// modules/suppliers/ajax_save_supplier.php
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
$name = trim($_POST['name'] ?? '');
$contact_name = trim($_POST['contact_name'] ?? '');
$category = trim($_POST['category'] ?? 'General');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$tax_id = trim($_POST['tax_id'] ?? '');
$address = trim($_POST['address'] ?? '');
$bank_info = trim($_POST['bank_info'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre o razón social es obligatorio.']);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE suppliers SET 
            name = ?, 
            contact_name = ?, 
            category = ?, 
            email = ?, 
            phone = ?, 
            tax_id = ?, 
            address = ?, 
            bank_info = ?, 
            notes = ?, 
            status = ?, 
            updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$name, $contact_name, $category, $email, $phone, $tax_id, $address, $bank_info, $notes, $status, $id]);
        $supplier_id = $id;
    } else {
        $public_token = bin2hex(random_bytes(20));
        $stmt = $db->prepare("INSERT INTO suppliers 
            (name, contact_name, category, email, phone, tax_id, address, bank_info, notes, status, public_token, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $contact_name, $category, $email, $phone, $tax_id, $address, $bank_info, $notes, $status, $public_token]);
        $supplier_id = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true, 
        'message' => $id > 0 ? 'Proveedor actualizado con éxito' : 'Proveedor creado con éxito',
        'supplier_id' => $supplier_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
