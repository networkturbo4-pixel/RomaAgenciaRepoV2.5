<?php
// modules/conexiones/ajax_test_smtp.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

require_once 'config/database.php';
require_once 'includes/Mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
$toEmail = $input['email'] ?? '';

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Correo inválido']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $mailer = new Mailer($db);
    $subject = '🚀 Prueba de Conexión SMTP - ROMA SaaS';
    $htmlBody = '
        <div style="font-family: sans-serif; text-align: center; padding: 20px;">
            <h2 style="color: #4f46e5;">¡Conexión Exitosa!</h2>
            <p>Si estás recibiendo este correo, significa que tu servidor SMTP está configurado correctamente en el CRM.</p>
        </div>
    ';
    
    $result = $mailer->sendCustomEmail($toEmail, '', $subject, $htmlBody);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al enviar (revisa tus credenciales o el host SMTP)']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
