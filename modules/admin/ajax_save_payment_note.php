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
