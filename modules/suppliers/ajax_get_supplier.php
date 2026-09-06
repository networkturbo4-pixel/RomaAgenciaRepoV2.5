<?php
// modules/suppliers/ajax_get_supplier.php
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
        exit();
    }

    // Get payments
    $stmtPayments = $db->prepare("SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC");
    $stmtPayments->execute([$id]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    // Get services
    $stmtServices = $db->prepare("SELECT * FROM supplier_services WHERE supplier_id = ? ORDER BY period_month DESC, service_date DESC, id DESC");
    $stmtServices->execute([$id]);
    $services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_paid_pen = 0;
    $total_paid_usd = 0;
    $total_pending_pen = 0;
    $total_pending_usd = 0;

    foreach ($payments as $p) {
        if ($p['status'] === 'paid') {
            if ($p['currency'] === 'USD') {
                $total_paid_usd += floatval($p['amount']);
            } else {
                $total_paid_pen += floatval($p['amount']);
            }
        } elseif ($p['status'] === 'pending') {
            if ($p['currency'] === 'USD') {
                $total_pending_usd += floatval($p['amount']);
            } else {
                $total_pending_pen += floatval($p['amount']);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'supplier' => $supplier,
        'payments' => $payments,
        'services' => $services,
        'totals' => [
            'total_paid_pen' => $total_paid_pen,
            'total_paid_usd' => $total_paid_usd,
            'total_pending_pen' => $total_pending_pen,
            'total_pending_usd' => $total_pending_usd,
            'payments_count' => count($payments),
            'services_count' => count($services)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
