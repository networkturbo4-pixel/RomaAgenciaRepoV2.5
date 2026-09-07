<?php
// modules/admin/ajax_save_payment_note.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

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

$perms = $_SESSION['user_permissions'] ?? [];

if (!in_array('admin', $perms)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado (Rol)']);
    exit();
}

try {
    $note_code = $data['id'];
    $client_name = $data['client'] ?? '';
    $company_name = $data['company'] ?? '';
    $start_date = $data['startDate'] ?? date('Y-m-d');
    $total = $data['total'] ?? 0;
    $services_json = json_encode($data['servicios'] ?? []);
    $schedule_json = json_encode($data['cronograma'] ?? []);
    $abonos_json = json_encode($data['abonos'] ?? []);
    $status = $data['status'] ?? 'En proceso';
    $apply_igv = isset($data['apply_igv']) && $data['apply_igv'] ? 1 : 0;
    $discount_percent = isset($data['discount_percent']) ? floatval($data['discount_percent']) : 0;
    $show_memberships = isset($data['show_memberships']) ? (int)filter_var($data['show_memberships'], FILTER_VALIDATE_BOOLEAN) : 1;
    $show_advances = isset($data['show_advances']) ? (int)filter_var($data['show_advances'], FILTER_VALIDATE_BOOLEAN) : 0;
    $due_days = isset($data['due_days']) ? intval($data['due_days']) : 30;
    $access_pin = !empty($data['access_pin']) ? substr(preg_replace('/[^0-9]/', '', $data['access_pin']), 0, 4) : null;
    $voucher_url = !empty($data['voucher_url']) ? $data['voucher_url'] : null;
    $operation_number = !empty($data['operation_number']) ? $data['operation_number'] : null;

    // Find client_id if possible
    $stmtClient = $db->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
    $stmtClient->execute([$client_name]);
    $client_id = $stmtClient->fetchColumn() ?: null;

    $stmt = $db->prepare("SELECT id FROM payment_notes WHERE note_code = ?");
    $stmt->execute([$note_code]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $stmtUpdate = $db->prepare("UPDATE payment_notes SET client_id=?, client_name=?, company_name=?, start_date=?, total=?, services_json=?, schedule_json=?, abonos_json=?, status=?, apply_igv=?, discount_percent=?, show_memberships=?, show_advances=?, due_days=?, access_pin=?, voucher_url=COALESCE(?, voucher_url), operation_number=COALESCE(?, operation_number), updated_at=CURRENT_TIMESTAMP WHERE note_code=?");
        $stmtUpdate->execute([$client_id, $client_name, $company_name, $start_date, $total, $services_json, $schedule_json, $abonos_json, $status, $apply_igv, $discount_percent, $show_memberships, $show_advances, $due_days, $access_pin, $voucher_url, $operation_number, $note_code]);
        echo json_encode(['success' => true]);
    } else {
        $public_token = bin2hex(random_bytes(16));
        $stmtInsert = $db->prepare("INSERT INTO payment_notes (note_code, client_id, client_name, company_name, start_date, total, services_json, schedule_json, abonos_json, status, public_token, apply_igv, discount_percent, show_memberships, show_advances, due_days, access_pin, voucher_url, operation_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->execute([$note_code, $client_id, $client_name, $company_name, $start_date, $total, $services_json, $schedule_json, $abonos_json, $status, $public_token, $apply_igv, $discount_percent, $show_memberships, $show_advances, $due_days, $access_pin, $voucher_url, $operation_number]);
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
