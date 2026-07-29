<?php
// modules/conexiones/ajax_test_template.php
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
$htmlBody = $input['html'] ?? '';

if (empty(trim(strip_tags($htmlBody))) && empty(trim($htmlBody))) {
    $htmlBody = '
        <div style="font-family: sans-serif; text-align: center; padding: 20px;">
            <h2>Prueba de Plantilla</h2>
            <p>El diseño de tu plantilla estaba vacío, pero la conexión funciona correctamente.</p>
        </div>
    ';
}

$subject = $input['subject'] ?? 'Prueba de Plantilla';

if (empty($subject)) {
    $subject = 'Prueba de Plantilla';
}

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Correo inválido']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $mailer = new Mailer($db);
    $result = $mailer->sendCustomEmail($toEmail, '', $subject, $htmlBody);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al enviar (revisa tus credenciales SMTP)']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
