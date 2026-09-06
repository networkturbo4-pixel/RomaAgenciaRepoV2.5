<?php
// modules/admin/ajax_upload_note_voucher.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
$db = (new Database())->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$token = trim($_POST['token'] ?? '');
$note_code = trim($_POST['note_code'] ?? $_POST['note_id'] ?? '');
$operation_number = trim($_POST['operation_number'] ?? '');

if (empty($token) && empty($note_code)) {
    echo json_encode(['success' => false, 'error' => 'Identificador de nota no especificado']);
    exit();
}

// Find note
$stmt = null;
if (!empty($token)) {
    $stmt = $db->prepare("SELECT * FROM payment_notes WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
} else {
    $stmt = $db->prepare("SELECT * FROM payment_notes WHERE note_code = ? OR id = ? LIMIT 1");
    $stmt->execute([$note_code, $note_code]);
}
$note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$note) {
    // If not found in DB but user is authenticated admin creating note on the fly, permit temporary voucher upload
    if (isset($_SESSION['user_id']) && !empty($_FILES['voucher'])) {
        // Upload temporary voucher
        $file = $_FILES['voucher'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) $ext = 'jpg';
        $filename = 'voucher_temp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $saved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $targetPath) : copy($file['tmp_name'], $targetPath);
        if ($saved) {
            echo json_encode([
                'success' => true,
                'voucher_url' => 'uploads/vouchers/' . $filename,
                'operation_number' => $operation_number,
                'status' => 'pagado'
            ]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'error' => 'Nota de pago no encontrada']);
    exit();
}

$voucher_url = $note['voucher_url'] ?? '';

// Process uploaded file
if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['voucher'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'El archivo supera el límite de 10MB']);
        exit();
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'error' => 'Formato no soportado. Sube una imagen o PDF']);
        exit();
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'])) {
        $ext = 'jpg';
    }

    $targetDir = __DIR__ . '/../../uploads/vouchers/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $sanitizedCode = preg_replace('/[^a-zA-Z0-9_\-]/', '', $note['note_code'] ?: 'nota');
    $filename = 'voucher_' . $sanitizedCode . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $targetPath = $targetDir . $filename;

    $saved = is_uploaded_file($file['tmp_name']) 
        ? move_uploaded_file($file['tmp_name'], $targetPath) 
        : copy($file['tmp_name'], $targetPath);

    if ($saved) {
        $voucher_url = 'uploads/vouchers/' . $filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor']);
        exit();
    }
}

// Update schedule and abonos to mark as pagado
$crono = json_decode($note['schedule_json'] ?? '[]', true) ?: [];
$cronoUpdated = false;
foreach ($crono as &$item) {
    if (($item['estado'] ?? '') === 'pendiente') {
        $item['estado'] = 'pagado';
        $cronoUpdated = true;
    }
}

// Record in abonos_json
$abonos = json_decode($note['abonos_json'] ?? '[]', true) ?: [];
$abonoConcept = "Pago con Comprobante (Voucher)";
if (!empty($operation_number)) {
    $abonoConcept .= " - Op: " . $operation_number;
}

$alreadyHasAbono = false;
foreach ($abonos as $ab) {
    if (!empty($voucher_url) && ($ab['voucher_url'] ?? '') === $voucher_url) {
        $alreadyHasAbono = true;
        break;
    }
}

if (!$alreadyHasAbono) {
    $abonos[] = [
        'concepto' => $abonoConcept,
        'metodo' => 'Transferencia / Voucher',
        'monto' => floatval($note['total']),
        'fecha' => date('Y-m-d'),
        'voucher_url' => $voucher_url,
        'operacion' => $operation_number
    ];
}

$newScheduleJson = json_encode($crono);
$newAbonosJson = json_encode($abonos);
$newStatus = 'pagado';

$stmtUpd = $db->prepare("
    UPDATE payment_notes 
    SET voucher_url = ?, 
        operation_number = ?, 
        voucher_uploaded_at = NOW(), 
        status = ?, 
        schedule_json = ?, 
        abonos_json = ?,
        show_advances = 1,
        updated_at = CURRENT_TIMESTAMP 
    WHERE id = ?
");
$stmtUpd->execute([
    $voucher_url,
    $operation_number,
    $newStatus,
    $newScheduleJson,
    $newAbonosJson,
    $note['id']
]);

echo json_encode([
    'success' => true,
    'voucher_url' => $voucher_url,
    'operation_number' => $operation_number,
    'status' => 'pagado',
    'message' => 'Comprobante registrado y nota marcada como pagada correctamente'
]);
