<?php
// modules/admin/ajax_delete_income.php
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

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit();
}

try {
    // Fetch the income to get its fecha_pago
    $stmtFetch = $db->prepare("SELECT fecha_pago FROM finance_incomes WHERE id = ?");
    $stmtFetch->execute([$id]);
    $income = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$income) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ingreso no encontrado.']);
        exit();
    }

    // Check if the month is closed
    $period = date('Y-m', strtotime($income['fecha_pago']));
    $stmtCheck = $db->prepare("SELECT `status` FROM finance_monthly_closings WHERE period = ?");
    $stmtCheck->execute([$period]);
    $monthStatus = $stmtCheck->fetchColumn();

    if ($monthStatus === 'cerrado') {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar. El mes ' . $period . ' está cerrado.']);
        exit();
    }

    // Delete the income
    $stmtDelete = $db->prepare("DELETE FROM finance_incomes WHERE id = ?");
    $stmtDelete->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
