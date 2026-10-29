<?php
// ajax/fcm_register.php
// Registra el token FCM del dispositivo Android para el usuario con sesión activa.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/FcmHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $token = trim($_POST['token'] ?? '');
    $device = trim($_POST['device'] ?? 'android');

    if (($_POST['action'] ?? '') === 'unregister') {
        FcmHelper::removeToken($db, $token);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => FcmHelper::saveToken($db, $userId, $token, $device)]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
