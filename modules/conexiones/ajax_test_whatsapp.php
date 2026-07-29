<?php
// modules/conexiones/ajax_test_whatsapp.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

require_once 'config/database.php';
require_once 'includes/WhatsAppJsonPe.php';

$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $wa = new WhatsAppJsonPe($db);
    $message = "🤖 *Prueba de Conexión JSON.pe*\n\n¡Hola! Si recibes este mensaje, tu integración con WhatsApp en el CRM está funcionando correctamente. ✅";
    
    $result = $wa->sendMessage($phone, $message);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al enviar (revisa el token o el estado de la instancia en json.pe)']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
