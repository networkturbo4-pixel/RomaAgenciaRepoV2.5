<?php
/**
 * ajax_mp_process_payment.php
 * Public endpoint - confirms and processes a MP payment result
 * POST JSON body: {"token": "...", "payment_id": "...", "status": "approved"}
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token requerido']);
    exit;
}

try {
    $db = (new Database())->getConnection();

    $token = $input['token'];
    $payment_id = $input['payment_id'] ?? '';
    $status = $input['status'] ?? '';

    // Get the note
    $stmt = $db->prepare("SELECT id, note_code FROM payment_notes WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }

    // Verify payment with MP API if we have a payment_id
    if ($payment_id) {
        $stmtSettings = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'mp_access_token'");
        $access_token = $stmtSettings->fetchColumn();

        if ($access_token) {
            $ch = curl_init("https://api.mercadopago.com/v1/payments/{$payment_id}");
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

            if ($httpCode === 200) {
                $mp_data = json_decode($response, true);
                $status = $mp_data['status'] ?? $status;
            }
        }
    }

    // Update note
    $mp_status_map = [
        'approved' => 'PAGADO',
        'pending' => 'En proceso',
        'in_process' => 'En proceso',
        'rejected' => 'En proceso'
    ];

    $new_status = $mp_status_map[$status] ?? 'En proceso';

    $stmtUpdate = $db->prepare("UPDATE payment_notes SET mp_payment_id = ?, mp_payment_status = ?, mp_paid_at = CASE WHEN ? = 'approved' THEN CURRENT_TIMESTAMP ELSE mp_paid_at END, status = ? WHERE id = ?");
    $stmtUpdate->execute([$payment_id, $status, $status, $new_status, $note['id']]);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'note_status' => $new_status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
