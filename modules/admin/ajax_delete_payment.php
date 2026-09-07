<?php
// modules/admin/ajax_delete_payment.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pay_id = intval($_POST['pay_id'] ?? $data['pay_id'] ?? $data['id'] ?? 0);

if ($pay_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit();
}

try {
    // Get info to delete the linked finance record
    $stmtOrig = $db->prepare("SELECT ep.payment_date, ep.concept, e.name as emp_name FROM employee_payments ep JOIN employees e ON ep.employee_id = e.id WHERE ep.id = ?");
    $stmtOrig->execute([$pay_id]);
    $orig = $stmtOrig->fetch(PDO::FETCH_ASSOC);

    if ($orig) {
        $orig_gasto = "Pago Nómina: " . $orig['emp_name'] . " - " . $orig['concept'];
        $stmtDeleteFin = $db->prepare("DELETE FROM finance_expenses WHERE nombre_gasto = ? AND fecha = ?");
        $stmtDeleteFin->execute([$orig_gasto, $orig['payment_date']]);
    }

    $stmt = $db->prepare("DELETE FROM employee_payments WHERE id = ?");
    $stmt->execute([$pay_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
