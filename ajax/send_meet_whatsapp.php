<?php
// ajax/send_meet_whatsapp.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';
$message = $input['message'] ?? '';

if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Teléfono y mensaje son obligatorios']);
    exit;
}

require_once '../config/database.php';
require_once '../includes/WhatsAppJsonPe.php';

try {
    $db = (new Database())->getConnection();
    $wa = new WhatsAppJsonPe($db);
    $result = $wa->sendMessage($phone, $message);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'El proveedor rechazó el envío o falló la conexión']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
