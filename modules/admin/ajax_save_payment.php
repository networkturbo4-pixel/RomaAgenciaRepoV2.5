<?php
// modules/admin/ajax_save_payment.php
// DB connection is handled by index.php

$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$payment_date = $_POST['payment_date'] ?? '';
$concept = $_POST['concept'] ?? '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$extra_amount = isset($_POST['extra_amount']) ? floatval($_POST['extra_amount']) : 0;
$extra_days = isset($_POST['extra_days']) ? floatval($_POST['extra_days']) : 0;
$extra_hours = isset($_POST['extra_hours']) ? floatval($_POST['extra_hours']) : 0;
$bonuses = isset($_POST['bonuses']) ? floatval($_POST['bonuses']) : 0;
$discounts = isset($_POST['discounts']) ? floatval($_POST['discounts']) : 0;
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

$pay_id = isset($_POST['pay_id']) ? intval($_POST['pay_id']) : 0;

try {
    // Get employee name
    $stmtEmp = $db->prepare("SELECT name FROM employees WHERE id = ?");
    $stmtEmp->execute([$employee_id]);
    $empName = $stmtEmp->fetchColumn();
    $nombre_gasto = "Pago Nómina: $empName - $concept";

    if ($pay_id > 0) {
        // Fetch original to update finance record properly if needed
        $stmtOrig = $db->prepare("SELECT payment_date, concept, voucher_url, status FROM employee_payments WHERE id = ?");
        $stmtOrig->execute([$pay_id]);
        $orig = $stmtOrig->fetch(PDO::FETCH_ASSOC);

        $final_voucher = !empty($voucher_url) ? $voucher_url : ($orig['voucher_url'] ?? '');

        // Update existing payment
        $stmt = $db->prepare("UPDATE employee_payments SET payment_date=?, concept=?, amount=?, extra_payment=?, extra_days=?, extra_hours=?, bonuses=?, discounts=?, voucher_url=?, status=? WHERE id=?");
        $stmt->execute([$payment_date, $concept, $amount, $extra_amount, $extra_days, $extra_hours, $bonuses, $discounts, $final_voucher, $status, $pay_id]);

        // Try to sync finance record
        if ($orig) {
            $orig_gasto = "Pago Nómina: $empName - " . $orig['concept'];
            
            if ($status === 'Pagado') {
                // Check if it already exists in finances
                $stmtCheck = $db->prepare("SELECT id FROM finance_expenses WHERE nombre_gasto=? AND fecha=?");
                $stmtCheck->execute([$orig_gasto, $orig['payment_date']]);
                if ($stmtCheck->rowCount() > 0) {
                    $stmtUpdateFin = $db->prepare("UPDATE finance_expenses SET fecha=?, nombre_gasto=?, monto=?, voucher=? WHERE nombre_gasto=? AND fecha=?");
                    $stmtUpdateFin->execute([$payment_date, $nombre_gasto, $amount, $final_voucher, $orig_gasto, $orig['payment_date']]);
                } else {
                    $stmtExp = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, voucher, created_at, updated_at) VALUES (?, ?, ?, 'Personal', ?, NOW(), NOW())");
                    $stmtExp->execute([$payment_date, $nombre_gasto, $amount, $final_voucher]);
                }
            } else {
                // Status is Pendiente, remove from finances if it exists
                $stmtDeleteFin = $db->prepare("DELETE FROM finance_expenses WHERE nombre_gasto=? AND fecha=?");
                $stmtDeleteFin->execute([$orig_gasto, $orig['payment_date']]);
            }
        }

    } else {
        // Insert new payment
        $stmt = $db->prepare("INSERT INTO employee_payments (employee_id, payment_date, concept, amount, extra_payment, extra_days, extra_hours, bonuses, discounts, voucher_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employee_id, $payment_date, $concept, $amount, $extra_amount, $extra_days, $extra_hours, $bonuses, $discounts, $voucher_url, $status]);
        
        // Register in Finances only if Pagado
        if ($status === 'Pagado') {
            $stmtExp = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, voucher, created_at, updated_at) VALUES (?, ?, ?, 'Personal', ?, NOW(), NOW())");
            $stmtExp->execute([$payment_date, $nombre_gasto, $amount, $voucher_url]);
        }
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
