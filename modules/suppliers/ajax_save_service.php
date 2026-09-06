<?php
// modules/suppliers/ajax_save_service.php
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
$supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
$period_month = trim($_POST['period_month'] ?? date('Y-m'));
$service_title = trim($_POST['service_title'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount = floatval(str_replace(',', '.', $_POST['amount'] ?? '0'));
$currency = in_array($_POST['currency'] ?? 'PEN', ['PEN', 'USD']) ? $_POST['currency'] : 'PEN';
$service_date = !empty($_POST['service_date']) ? trim($_POST['service_date']) : null;
$status = in_array($_POST['status'] ?? 'delivered', ['in_progress', 'delivered', 'approved', 'cancelled']) ? $_POST['status'] : 'delivered';

$missing = [];
if ($supplier_id <= 0) $missing[] = 'proveedor';
if (empty($service_title)) $missing[] = 'título del servicio';
if (empty($period_month)) $missing[] = 'periodo / mes';

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos: ' . implode(', ', $missing)]);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE supplier_services SET 
            supplier_id = ?, 
            period_month = ?, 
            service_title = ?, 
            description = ?, 
            amount = ?, 
            currency = ?, 
            service_date = ?, 
            status = ?, 
            updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$supplier_id, $period_month, $service_title, $description, $amount, $currency, $service_date, $status, $id]);
        $service_id = $id;
    } else {
        $stmt = $db->prepare("INSERT INTO supplier_services 
            (supplier_id, period_month, service_title, description, amount, currency, service_date, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$supplier_id, $period_month, $service_title, $description, $amount, $currency, $service_date, $status]);
        $service_id = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => $id > 0 ? 'Servicio actualizado exitosamente' : 'Servicio registrado exitosamente',
        'service_id' => $service_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
