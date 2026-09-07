<?php
// modules/admin/ajax_upload_note_voucher.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
$db = (new Database())->getConnection();

// Auto-migrate: ensure all columns exist in payment_notes so production never fails with Unknown column
try {
    $stmtCols = $db->query("SHOW COLUMNS FROM payment_notes");
    $existingCols = $stmtCols ? $stmtCols->fetchAll(PDO::FETCH_COLUMN) : [];
    $needed = [
        'abonos_json' => "ALTER TABLE `payment_notes` ADD `abonos_json` text DEFAULT NULL",
        'apply_igv' => "ALTER TABLE `payment_notes` ADD `apply_igv` tinyint(1) DEFAULT 0",
        'discount_percent' => "ALTER TABLE `payment_notes` ADD `discount_percent` decimal(5,2) DEFAULT 0.00",
        'show_memberships' => "ALTER TABLE `payment_notes` ADD `show_memberships` tinyint(1) DEFAULT 1",
        'show_advances' => "ALTER TABLE `payment_notes` ADD `show_advances` tinyint(1) DEFAULT 0",
        'view_count' => "ALTER TABLE `payment_notes` ADD `view_count` int(11) DEFAULT 0",
        'last_viewed_at' => "ALTER TABLE `payment_notes` ADD `last_viewed_at` timestamp NULL DEFAULT NULL",
        'due_days' => "ALTER TABLE `payment_notes` ADD `due_days` int(11) DEFAULT 30",
        'access_pin' => "ALTER TABLE `payment_notes` ADD `access_pin` varchar(4) DEFAULT NULL",
        'mp_preference_id' => "ALTER TABLE `payment_notes` ADD `mp_preference_id` varchar(100) DEFAULT NULL",
        'mp_payment_id' => "ALTER TABLE `payment_notes` ADD `mp_payment_id` varchar(100) DEFAULT NULL",
        'mp_payment_status' => "ALTER TABLE `payment_notes` ADD `mp_payment_status` varchar(50) DEFAULT NULL",
        'mp_paid_at' => "ALTER TABLE `payment_notes` ADD `mp_paid_at` timestamp NULL DEFAULT NULL",
        'voucher_url' => "ALTER TABLE `payment_notes` ADD `voucher_url` varchar(255) DEFAULT NULL",
        'operation_number' => "ALTER TABLE `payment_notes` ADD `operation_number` varchar(100) DEFAULT NULL",
        'voucher_uploaded_at' => "ALTER TABLE `payment_notes` ADD `voucher_uploaded_at` datetime DEFAULT NULL"
    ];
    foreach ($needed as $col => $sql) {
        if (!in_array($col, $existingCols)) {
            @$db->exec($sql);
        }
    }
} catch (Exception $e) {
    // Continue even if SHOW COLUMNS or ALTER fails
}

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

$targetDir = __DIR__ . '/../../uploads/vouchers/';
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

// Find note
$note = null;
if (!empty($token)) {
    $stmt = $db->prepare("SELECT * FROM payment_notes WHERE public_token = ? OR LEFT(public_token, 8) = ? LIMIT 1");
    $stmt->execute([$token, $token]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$note && !empty($note_code) && $note_code !== 'NEW') {
    $stmt = $db->prepare("SELECT * FROM payment_notes WHERE note_code = ? OR id = ? LIMIT 1");
    $stmt->execute([$note_code, $note_code]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$note) {
    // If not found in DB (e.g. creating note on the fly or new note), permit voucher upload
    if (!empty($_FILES['voucher']) && $_FILES['voucher']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['voucher'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) $ext = 'jpg';
        $filename = 'voucher_temp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $targetDir . $filename;
        $saved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $targetPath) : copy($file['tmp_name'], $targetPath);
        if ($saved) {
            echo json_encode([
                'success' => true,
                'voucher_url' => 'uploads/vouchers/' . $filename,
                'operation_number' => $operation_number,
                'status' => 'pagado',
                'message' => 'Comprobante guardado temporalmente'
            ]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'error' => 'Nota de pago no encontrada y no se subió ningún archivo']);
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
