<?php
// modules/admin/ajax_share_link.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['note_data'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $token = substr(md5(uniqid(rand(), true)), 0, 8); // Generate 8 char token
    $note_data = $data['note_data']; // This is the base64 string
    
    $stmt = $db->prepare("INSERT INTO shared_links (token, data) VALUES (?, ?)");
    if ($stmt->execute([$token, $note_data])) {
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el enlace']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
}
