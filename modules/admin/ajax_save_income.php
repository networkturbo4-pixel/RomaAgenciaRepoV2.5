<?php
// modules/admin/ajax_save_income.php
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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$empresa = $_POST['empresa'] ?? '';
$servicio = $_POST['servicio'] ?? '';
$monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
$fecha_pago = $_POST['fecha_pago'] ?? '';
$estado = $_POST['estado'] ?? '';
$n_operacion = $_POST['n_operacion'] ?? '';
$banco = $_POST['banco'] ?? '';

if (empty($empresa) || empty($servicio) || $monto <= 0 || empty($fecha_pago)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campos obligatorios: empresa, servicio, monto y fecha de pago.']);
    exit();
}

try {
    // Check if the month is closed
    $period = date('Y-m', strtotime($fecha_pago));
    $stmtCheck = $db->prepare("SELECT `status` FROM finance_monthly_closings WHERE period = ?");
    $stmtCheck->execute([$period]);
    $monthStatus = $stmtCheck->fetchColumn();

    if ($monthStatus === 'cerrado') {
        echo json_encode(['success' => false, 'message' => 'No se puede modificar. El mes ' . $period . ' está cerrado.']);
        exit();
    }

    // If updating, also check the original month (in case fecha_pago changed)
    if ($id > 0) {
        $stmtOrig = $db->prepare("SELECT fecha_pago FROM finance_incomes WHERE id = ?");
        $stmtOrig->execute([$id]);
        $origFecha = $stmtOrig->fetchColumn();
        if ($origFecha) {
            $origPeriod = date('Y-m', strtotime($origFecha));
            if ($origPeriod !== $period) {
                $stmtCheckOrig = $db->prepare("SELECT `status` FROM finance_monthly_closings WHERE period = ?");
                $stmtCheckOrig->execute([$origPeriod]);
                $origStatus = $stmtCheckOrig->fetchColumn();
                if ($origStatus === 'cerrado') {
                    echo json_encode(['success' => false, 'message' => 'No se puede modificar. El mes original ' . $origPeriod . ' está cerrado.']);
                    exit();
                }
            }
        }
    }

    // Handle voucher file upload
    $voucher = null;
    if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/vouchers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['voucher']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Use: ' . implode(', ', $allowed)]);
            exit();
        }

        $filename = 'voucher_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['voucher']['tmp_name'], $destination)) {
            $voucher = 'uploads/vouchers/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
            exit();
        }
    }

    if ($id > 0) {
        // UPDATE
        if ($voucher) {
            $stmt = $db->prepare("UPDATE finance_incomes SET empresa=?, servicio=?, monto=?, fecha_pago=?, estado=?, n_operacion=?, banco=?, voucher=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$empresa, $servicio, $monto, $fecha_pago, $estado, $n_operacion, $banco, $voucher, $id]);
        } else {
            $stmt = $db->prepare("UPDATE finance_incomes SET empresa=?, servicio=?, monto=?, fecha_pago=?, estado=?, n_operacion=?, banco=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$empresa, $servicio, $monto, $fecha_pago, $estado, $n_operacion, $banco, $id]);
        }
    } else {
        // INSERT
        $stmt = $db->prepare("INSERT INTO finance_incomes (empresa, servicio, monto, fecha_pago, estado, n_operacion, banco, voucher, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$empresa, $servicio, $monto, $fecha_pago, $estado, $n_operacion, $banco, $voucher]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
