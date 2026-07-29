<?php
// modules/config/ajax_mp_verify.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['connected' => false, 'error' => 'No autorizado']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Read access token from settings
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mp_access_token' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $accessToken = $row['setting_value'] ?? '';

    if (empty($accessToken)) {
        echo json_encode(['connected' => false, 'error' => 'Access Token no configurado']);
        exit();
    }

    // Test connection to Mercado Pago API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payment_methods',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['connected' => false, 'error' => 'Error de red: ' . $curlError]);
        exit();
    }

    if ($httpCode === 200) {
        echo json_encode(['connected' => true, 'message' => 'Conexión exitosa']);
    } else {
        $body = json_decode($response, true);
        $errorMsg = $body['message'] ?? 'Token inválido o sin conexión';
        echo json_encode(['connected' => false, 'error' => $errorMsg . ' (HTTP ' . $httpCode . ')']);
    }

} catch (Exception $e) {
    echo json_encode(['connected' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
