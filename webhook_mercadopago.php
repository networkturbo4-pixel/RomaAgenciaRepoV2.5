<?php
/**
 * webhook_mercadopago.php
 * Webhook endpoint for Mercado Pago payment notifications
 * MP calls this URL when a payment status changes
 */

header('Content-Type: application/json');
require_once 'config/database.php';

// MP sends notifications via POST or GET
$topic = $_GET['topic'] ?? $_GET['type'] ?? '';
$id = $_GET['id'] ?? $_GET['data_id'] ?? '';

// Also check POST body
$body = json_decode(file_get_contents('php://input'), true);
if ($body) {
    $topic = $body['type'] ?? $topic;
    $id = $body['data']['id'] ?? $id;
}

// Only process payment notifications
if (!in_array($topic, ['payment', 'merchant_order'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
    exit;
}

if (empty($id)) {
    http_response_code(200);
    echo json_encode(['status' => 'no_id']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    // Get access token
    $stmtSettings = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'mp_access_token'");
    $access_token = $stmtSettings->fetchColumn();

    if (!$access_token) {
        http_response_code(200);
        echo json_encode(['status' => 'no_token']);
        exit;
    }

    // Get payment details from MP
    $ch = curl_init("https://api.mercadopago.com/v1/payments/{$id}");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        http_response_code(200);
        echo json_encode(['status' => 'mp_api_error']);
        exit;
    }

    $payment = json_decode($response, true);
    $external_ref = $payment['external_reference'] ?? '';
    $status = $payment['status'] ?? '';

    if (empty($external_ref)) {
        http_response_code(200);
        echo json_encode(['status' => 'no_reference']);
        exit;
    }

    // Map MP status to note status
    $mp_status_map = [
        'approved' => 'PAGADO',
        'pending' => 'En proceso',
        'in_process' => 'En proceso',
        'rejected' => 'En proceso',
        'refunded' => 'En proceso',
        'cancelled' => 'En proceso'
    ];

    $new_status = $mp_status_map[$status] ?? 'En proceso';

    // Update the note
    $stmt = $db->prepare("UPDATE payment_notes SET mp_payment_id = ?, mp_payment_status = ?, mp_paid_at = CASE WHEN ? = 'approved' THEN CURRENT_TIMESTAMP ELSE mp_paid_at END, status = ? WHERE note_code = ?");
    $stmt->execute([$id, $status, $status, $new_status, $external_ref]);

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    http_response_code(200); // Always return 200 to MP to prevent retries
    echo json_encode(['status' => 'error']);
}
