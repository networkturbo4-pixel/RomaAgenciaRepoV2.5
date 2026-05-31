<?php
// modules/admin/ajax_save_expense.php
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

// Support both JSON and FormData
if (isset($_POST['fecha'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $fecha = $_POST['fecha'] ?? '';
    $nombre_gasto = $_POST['nombre_gasto'] ?? '';
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
    $categoria = $_POST['categoria'] ?? '';
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $fecha = $data['fecha'] ?? '';
    $nombre_gasto = $data['nombre_gasto'] ?? '';
    $monto = isset($data['monto']) ? floatval($data['monto']) : 0;
    $categoria = $data['categoria'] ?? '';
}

if (empty($fecha) || empty($nombre_gasto) || $monto <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campos obligatorios: fecha, nombre_gasto y monto.']);
    exit();
}

try {
    // Check if the month is closed
    $period = date('Y-m', strtotime($fecha));
    $stmtCheck = $db->prepare("SELECT `status` FROM finance_monthly_closings WHERE period = ?");
    $stmtCheck->execute([$period]);
    $monthStatus = $stmtCheck->fetchColumn();

    if ($monthStatus === 'cerrado') {
        echo json_encode(['success' => false, 'message' => 'No se puede modificar. El mes ' . $period . ' está cerrado.']);
        exit();
    }

    // If updating, also check the original month
    if ($id > 0) {
        $stmtOrig = $db->prepare("SELECT fecha FROM finance_expenses WHERE id = ?");
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

        $filename = 'exp_voucher_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['voucher']['tmp_name'], $destination)) {
            $voucher = 'uploads/vouchers/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
            exit();
        }
    }

    if ($id > 0) {
        if ($voucher) {
            $stmt = $db->prepare("UPDATE finance_expenses SET fecha=?, nombre_gasto=?, monto=?, categoria=?, voucher=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$fecha, $nombre_gasto, $monto, $categoria, $voucher, $id]);
        } else {
            $stmt = $db->prepare("UPDATE finance_expenses SET fecha=?, nombre_gasto=?, monto=?, categoria=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$fecha, $nombre_gasto, $monto, $categoria, $id]);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, voucher, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$fecha, $nombre_gasto, $monto, $categoria, $voucher]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
