<?php
// modules/conexiones/ajax_save_template.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

require_once 'config/database.php';
$db = (new Database())->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos válidos']);
    exit;
}

$id = intval($input['id'] ?? 0);
$name = trim($input['name'] ?? 'Nueva Plantilla');
$subject = trim($input['subject'] ?? '');
$body_html = $input['body_html'] ?? '';
$body_design = json_encode($input['body_design'] ?? []);

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE email_templates SET name = ?, subject = ?, body_html = ?, body_design = ? WHERE id = ?");
        $stmt->execute([$name, $subject, $body_html, $body_design, $id]);
        echo json_encode(['success' => true]);
    } else {
        $stmt = $db->prepare("INSERT INTO email_templates (name, subject, body_html, body_design) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $subject, $body_html, $body_design]);
        $new_id = $db->lastInsertId();
        echo json_encode(['success' => true, 'new_id' => $new_id]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
