<?php
/**
 * ajax_create_mp_preference.php
 * Public endpoint - creates a Mercado Pago payment preference for a note
 * POST JSON body: {"token": "..."}
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

    // Get MP credentials from settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mp_access_token', 'mp_public_key', 'mp_mode')");
    $mp_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mp_settings[$row['setting_key']] = $row['setting_value'];
    }

    $access_token = $mp_settings['mp_access_token'] ?? '';
    $public_key = $mp_settings['mp_public_key'] ?? '';

    if (empty($access_token) || empty($public_key)) {
        echo json_encode(['success' => false, 'error' => 'Mercado Pago no configurado']);
        exit;
    }

    // Get note data
    $token = $input['token'];
    $stmt = $db->prepare("SELECT * FROM payment_notes WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }

    // Calculate pending balance
    $servicios = json_decode($note['services_json'], true) ?: [];
    $cronograma = json_decode($note['schedule_json'], true) ?: [];
    $abonos = json_decode($note['abonos_json'] ?? '[]', true) ?: [];

    $totalServ = 0;
    foreach ($servicios as $s) {
        $totalServ += ($s['cantidad'] ?? 1) * ($s['costoUnit'] ?? 0);
    }
    $totalCrono = 0;
    foreach ($cronograma as $c) {
        $totalCrono += floatval($c['costoUnit'] ?? $c['monto'] ?? 0);
    }
    $totalAbonos = 0;
    foreach ($abonos as $a) {
        $totalAbonos += floatval($a['monto'] ?? 0);
    }

    $subtotal = $totalServ + $totalCrono;
    
    // Apply discount
    $discountPercent = floatval($note['discount_percent'] ?? 0);
    $discountAmount = $subtotal * ($discountPercent / 100);
    $baseForIgv = $subtotal - $discountAmount;
    
    // Apply IGV
    $applyIgv = (bool)($note['apply_igv'] ?? false);
    $igvAmount = $applyIgv ? $baseForIgv * 0.18 : 0;
    
    $totalGeneral = $baseForIgv + $igvAmount;
    $saldo = max(0, $totalGeneral - $totalAbonos);

    if ($saldo <= 0) {
        echo json_encode(['success' => false, 'error' => 'Esta nota ya está pagada']);
        exit;
    }

    // Always generate a new preference to ensure the latest credentials and amount are used

    // Determine the base URL for callbacks
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
    $noteUrl = $baseUrl . '/CESARMENDOZA/index.php?module=admin&action=payment_note_webview&view=public&token=' . $token;
    $isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

    // Create MP Preference via API
    $preference_data = [
        'items' => [
            [
                'title' => 'Nota de Pago ' . $note['note_code'],
                'description' => 'Pago de servicios - ' . ($note['client_name'] ?? ''),
                'quantity' => 1,
                'currency_id' => 'PEN',
                'unit_price' => round($saldo, 2)
            ]
        ],
        'external_reference' => $note['note_code']
    ];

    // Only add back_urls and notification_url for non-localhost (MP rejects localhost URLs)
    if (!$isLocalhost) {
        $preference_data['back_urls'] = [
            'success' => $noteUrl . '&mp_status=approved',
            'failure' => $noteUrl . '&mp_status=failure',
            'pending' => $noteUrl . '&mp_status=pending'
        ];
        $preference_data['auto_return'] = 'approved';
        $preference_data['notification_url'] = $baseUrl . '/webhook_mercadopago.php';
    }

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($preference_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $curlError]);
        exit;
    }

    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['message'] ?? 'Error al crear preferencia';
        echo json_encode(['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode]);
        exit;
    }

    $mp_response = json_decode($response, true);
    $preference_id = $mp_response['id'] ?? '';

    // Save preference_id
    $stmtUpdate = $db->prepare("UPDATE payment_notes SET mp_preference_id = ? WHERE id = ?");
    $stmtUpdate->execute([$preference_id, $note['id']]);

    echo json_encode([
        'success' => true,
        'preference_id' => $preference_id,
        'public_key' => $public_key,
        'amount' => $saldo
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
