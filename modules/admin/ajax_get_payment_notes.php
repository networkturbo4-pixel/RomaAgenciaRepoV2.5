<?php
// modules/admin/ajax_get_payment_notes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->query("SELECT * FROM payment_notes ORDER BY id DESC");
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($notes as $note) {
        $formatted[] = [
            'db_id' => $note['id'],
            'id' => $note['note_code'],
            'client' => $note['client_name'],
            'company' => $note['company_name'],
            'startDate' => $note['start_date'],
            'date' => $note['created_at'],
            'total' => $note['total'],
            'servicios' => json_decode($note['services_json'], true) ?: [],
            'cronograma' => json_decode($note['schedule_json'], true) ?: [],
            'abonos' => json_decode($note['abonos_json'] ?? '[]', true) ?: [],
            'apply_igv' => (bool)$note['apply_igv'],
            'discount_percent' => floatval($note['discount_percent']),
            'show_memberships' => (bool)($note['show_memberships'] ?? true),
            'show_advances' => (bool)($note['show_advances'] ?? false),
            'status' => $note['status'],
            'public_token' => $note['public_token'],
            'due_days' => intval($note['due_days'] ?? 30),
            'access_pin' => $note['access_pin'] ?? null,
            'view_count' => intval($note['view_count'] ?? 0),
            'last_viewed_at' => $note['last_viewed_at'] ?? null,
            'voucher_url' => $note['voucher_url'] ?? null,
            'operation_number' => $note['operation_number'] ?? null,
            'voucher_uploaded_at' => $note['voucher_uploaded_at'] ?? null
        ];
    }

    $stmtClients = $db->query("
        SELECT c.id, c.name, c.whatsapp, c.email, 
               GROUP_CONCAT(b.name SEPARATOR '||') as brands
        FROM clients c
        LEFT JOIN client_brands b ON c.id = b.client_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notes' => $formatted, 'clients' => $clients]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
