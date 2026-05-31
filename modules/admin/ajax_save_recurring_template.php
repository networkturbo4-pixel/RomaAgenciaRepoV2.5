<?php
// modules/admin/ajax_save_recurring_template.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

$id = isset($data['id']) ? intval($data['id']) : 0;
$nombre_gasto = $data['nombre_gasto'] ?? '';
$monto = isset($data['monto']) ? floatval($data['monto']) : 0;
$categoria = $data['categoria'] ?? '';
$dia_pago = isset($data['dia_pago']) ? intval($data['dia_pago']) : 1;

if (empty($nombre_gasto) || $monto <= 0 || $dia_pago < 1 || $dia_pago > 31) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campos obligatorios: nombre_gasto, monto (>0) y dia_pago (1-31).']);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE finance_recurring_expenses SET nombre_gasto=?, monto=?, categoria=?, dia_pago=? WHERE id=?");
        $stmt->execute([$nombre_gasto, $monto, $categoria, $dia_pago, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO finance_recurring_expenses (nombre_gasto, monto, categoria, dia_pago, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$nombre_gasto, $monto, $categoria, $dia_pago]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
