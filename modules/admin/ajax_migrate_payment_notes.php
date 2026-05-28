<?php
// modules/admin/ajax_migrate_payment_notes.php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

$perms = $_SESSION['user_permissions'] ?? [];
if (!isset($_SESSION['user_id']) || !in_array('admin', $perms)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['notes'])) {
    echo json_encode(['success' => true]);
    exit();
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT IGNORE INTO payment_notes (note_code, client_name, company_name, start_date, total, services_json, schedule_json, status, public_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($data['notes'] as $note) {
        $client_name = $note['client'] ?? '';
        $company_name = $note['company'] ?? '';
        $start_date = $note['startDate'] ?? date('Y-m-d');
        $total = $note['total'] ?? 0;
        $services_json = json_encode($note['servicios'] ?? []);
        $schedule_json = json_encode($note['cronograma'] ?? []);
        $status = $note['status'] ?? 'En proceso';
        $public_token = bin2hex(random_bytes(16));

        $stmt->execute([
            $note['id'],
            $client_name,
            $company_name,
            $start_date,
            $total,
            $services_json,
            $schedule_json,
            $status,
            $public_token
        ]);
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
