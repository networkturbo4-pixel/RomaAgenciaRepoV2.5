<?php
// modules/admin/ajax_close_month.php
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
$month = $data['month'] ?? '';
$monto_repartido = isset($data['monto_repartido']) ? floatval($data['monto_repartido']) : 0;

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de mes inválido. Use YYYY-MM.']);
    exit();
}

try {
    // Calculate totals
    $stmtInc = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
    $stmtInc->execute([$month]);
    $total_incomes = (float)$stmtInc->fetchColumn();

    $stmtExp = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
    $stmtExp->execute([$month]);
    $total_expenses = (float)$stmtExp->fetchColumn();

    $utilidad = $total_incomes - $total_expenses;
    $userId = $_SESSION['user_id'] ?? null;

    // Check if a closing record already exists
    $stmtExists = $db->prepare("SELECT id FROM finance_monthly_closings WHERE period = ?");
    $stmtExists->execute([$month]);
    $existingId = $stmtExists->fetchColumn();

    if ($existingId) {
        $stmt = $db->prepare("UPDATE finance_monthly_closings SET `status`='cerrado', total_incomes=?, total_expenses=?, monto_repartido=?, closed_by=?, closed_at=NOW() WHERE period=?");
        $stmt->execute([$total_incomes, $total_expenses, $monto_repartido, $userId, $month]);
    } else {
        $stmt = $db->prepare("INSERT INTO finance_monthly_closings (period, `status`, total_incomes, total_expenses, monto_repartido, closed_by, closed_at) VALUES (?, 'cerrado', ?, ?, ?, ?, NOW())");
        $stmt->execute([$month, $total_incomes, $total_expenses, $monto_repartido, $userId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Mes ' . $month . ' cerrado exitosamente.',
        'total_incomes' => $total_incomes,
        'total_expenses' => $total_expenses,
        'utilidad' => $utilidad
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
