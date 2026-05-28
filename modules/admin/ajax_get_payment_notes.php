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
            'status' => $note['status'],
            'public_token' => $note['public_token']
        ];
    }

    $stmtClients = $db->query("
        SELECT c.id, c.name, 
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
