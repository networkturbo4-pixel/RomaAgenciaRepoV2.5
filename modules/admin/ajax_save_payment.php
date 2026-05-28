<?php
// modules/admin/ajax_save_payment.php
// DB connection is handled by index.php

$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$payment_date = $_POST['payment_date'] ?? '';
$concept = $_POST['concept'] ?? '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$status = $_POST['status'] ?? 'Pagado';

if ($employee_id <= 0 || empty($payment_date) || empty($concept) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios o son inválidos.']);
    exit();
}

// Handle file upload
$voucher_url = '';
if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/vouchers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_info = pathinfo($_FILES['voucher']['name']);
    $ext = strtolower($file_info['extension']);
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (in_array($ext, $allowed)) {
        $filename = 'voucher_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['voucher']['tmp_name'], $target_path)) {
            $voucher_url = $target_path;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de archivo no permitido. Use JPG, PNG o PDF.']);
        exit();
    }
}

try {
    $stmt = $db->prepare("INSERT INTO employee_payments (employee_id, payment_date, concept, amount, voucher_url, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employee_id, $payment_date, $concept, $amount, $voucher_url, $status]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
