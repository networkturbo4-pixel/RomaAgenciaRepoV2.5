<?php
// modules/admin/ajax_get_payments.php
// DB connection is handled by index.php

$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

if ($employee_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Employee ID']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC, created_at DESC");
    $stmt->execute([$employee_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $payments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
