<?php
// modules/admin/ajax_reopen_month.php
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

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de mes inválido. Use YYYY-MM.']);
    exit();
}

try {
    $stmt = $db->prepare("UPDATE finance_monthly_closings SET `status`='abierto', closed_by=NULL, closed_at=NULL WHERE period=?");
    $stmt->execute([$month]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontró registro de cierre para el mes ' . $month . '.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Mes ' . $month . ' reabierto exitosamente.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
