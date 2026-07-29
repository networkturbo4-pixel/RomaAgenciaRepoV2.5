<?php
// modules/admin/ajax_send_note_email.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$to = $input['to'] ?? '';
$subject = $input['subject'] ?? '';
$messageText = $input['message'] ?? '';
$note_id = $input['note_id'] ?? '';
$url = $input['url'] ?? '';

if (empty($to) || empty($subject) || empty($messageText)) {
    echo json_encode(['success' => false, 'error' => 'Correo, asunto y mensaje son obligatorios']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/Mailer.php';

// Prepare a professional HTML email layout wrapping the message
$htmlMessage = nl2br(htmlspecialchars($messageText));

$htmlBody = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .header { background-color: #1e293b; padding: 30px 20px; text-align: center; }
    .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
    .content { padding: 40px 30px; color: #334155; line-height: 1.6; font-size: 16px; }
    .button-container { text-align: center; margin: 30px 0; }
    .button { background-color: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block; }
    .footer { background-color: #f1f5f9; padding: 20px; text-align: center; color: #64748b; font-size: 13px; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Documento de Pago</h1>
        </div>
        <div class='content'>
            {$htmlMessage}
            
            <div class='button-container'>
                <a href='{$url}' class='button'>Ver Nota de Pago</a>
            </div>
            
            <p style='margin-top: 30px; font-size: 14px; color: #64748b;'>Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:<br>
            <a href='{$url}' style='color: #4f46e5; word-break: break-all;'>{$url}</a></p>
        </div>
        <div class='footer'>
            Este es un correo automático. Por favor no responder a esta dirección a menos que se indique lo contrario.
        </div>
    </div>
</body>
</html>
";

try {
    $db = (new Database())->getConnection();
    $mailer = new Mailer($db);
    $result = $mailer->sendCustomEmail($to, '', $subject, $htmlBody);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo enviar el correo. Verifica tu configuración SMTP.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
