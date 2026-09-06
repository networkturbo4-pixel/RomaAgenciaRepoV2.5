<?php
// modules/suppliers/ajax_save_payment.php
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
$payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
$period_month = trim($_POST['period_month'] ?? date('Y-m', strtotime($payment_date)));
$concept = trim($_POST['concept'] ?? '');
$amount = floatval(str_replace(',', '.', $_POST['amount'] ?? '0'));
$currency = in_array($_POST['currency'] ?? 'PEN', ['PEN', 'USD']) ? $_POST['currency'] : 'PEN';
$payment_method = trim($_POST['payment_method'] ?? 'Transferencia');
$reference_number = trim($_POST['reference_number'] ?? '');
$status = in_array($_POST['status'] ?? 'paid', ['paid', 'pending', 'under_review', 'cancelled']) ? $_POST['status'] : 'paid';
$notes = trim($_POST['notes'] ?? '');

$missing = [];
if ($supplier_id <= 0) $missing[] = 'proveedor';
if (empty($concept)) $missing[] = 'concepto';
if ($amount <= 0) $missing[] = 'monto válido';
if (empty($payment_date)) $missing[] = 'fecha de pago';

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos: ' . implode(', ', $missing)]);
    exit();
}

try {
    // Check if supplier exists
    $stmtSup = $db->prepare("SELECT id FROM suppliers WHERE id = ?");
    $stmtSup->execute([$supplier_id]);
    if (!$stmtSup->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
        exit();
    }

    // Handle voucher file upload if present
    $voucher_url = null;
    if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/vouchers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['voucher']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Formatos aceptados: JPG, PNG, WEBP, PDF']);
            exit();
        }

        $filename = 'sup_voucher_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['voucher']['tmp_name'], $destination)) {
            $voucher_url = 'uploads/vouchers/' . $filename;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo adjunto']);
            exit();
        }
    }

    if ($id > 0) {
        if ($voucher_url) {
            $stmt = $db->prepare("UPDATE supplier_payments SET 
                supplier_id = ?, 
                payment_date = ?, 
                period_month = ?, 
                concept = ?, 
                amount = ?, 
                currency = ?, 
                payment_method = ?, 
                reference_number = ?, 
                status = ?, 
                voucher_url = ?, 
                notes = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$supplier_id, $payment_date, $period_month, $concept, $amount, $currency, $payment_method, $reference_number, $status, $voucher_url, $notes, $id]);
        } else {
            $stmt = $db->prepare("UPDATE supplier_payments SET 
                supplier_id = ?, 
                payment_date = ?, 
                period_month = ?, 
                concept = ?, 
                amount = ?, 
                currency = ?, 
                payment_method = ?, 
                reference_number = ?, 
                status = ?, 
                notes = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$supplier_id, $payment_date, $period_month, $concept, $amount, $currency, $payment_method, $reference_number, $status, $notes, $id]);
        }
        $payment_id = $id;
    } else {
        $stmt = $db->prepare("INSERT INTO supplier_payments 
            (supplier_id, payment_date, period_month, concept, amount, currency, payment_method, reference_number, status, voucher_url, notes, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$supplier_id, $payment_date, $period_month, $concept, $amount, $currency, $payment_method, $reference_number, $status, $voucher_url, $notes]);
        $payment_id = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => $id > 0 ? 'Pago actualizado exitosamente' : 'Pago registrado exitosamente',
        'payment_id' => $payment_id,
        'voucher_url' => $voucher_url
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
