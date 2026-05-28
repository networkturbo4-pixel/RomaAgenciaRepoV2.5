<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once 'config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['endpoint'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

try {
    $db = (new Database())->getConnection();
    
    $endpoint = $data['endpoint'];
    $p256dh = $data['keys']['p256dh'] ?? '';
    $auth = $data['keys']['auth'] ?? '';
    $userId = $_SESSION['user_id'];
    
    // Comprobar si ya existe la suscripción
    $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    if ($stmt->fetch()) {
        // Actualizar por si acaso el user_id cambió (mismo dispositivo, otra cuenta)
        $stmtUpdate = $db->prepare("UPDATE push_subscriptions SET user_id = ?, p256dh = ?, auth_token = ? WHERE endpoint = ?");
        $stmtUpdate->execute([$userId, $p256dh, $auth, $endpoint]);
    } else {
        // Insertar nueva
        $stmtInsert = $db->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_token) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$userId, $endpoint, $p256dh, $auth]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
