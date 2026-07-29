<?php
// api_qr_share.php
require_once 'config/database.php';

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Generate new link
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['config'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    // Generate random 10 character token
    $token = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    $configJson = json_encode($data['config']);

    $stmt = $db->prepare("INSERT INTO shared_links (token, data) VALUES (?, ?)");
    if ($stmt->execute([$token, $configJson])) {
        // Construct the link URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        if ($path === '/' || $path === '\\') $path = '';
        
        $link = $protocol . '://' . $host . $path . '/index.php?module=herramientas&action=index&qr=' . $token;

        echo json_encode(['success' => true, 'link' => $link]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en base de datos']);
    }
} elseif ($method === 'GET') {
    // Fetch existing config
    $hash = $_GET['hash'] ?? '';
    if (!$hash) {
        echo json_encode(['success' => false, 'message' => 'Hash no proporcionado']);
        exit;
    }

    $stmt = $db->prepare("SELECT data FROM shared_links WHERE token = ?");
    $stmt->execute([$hash]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($res) {
        echo json_encode(['success' => true, 'config' => json_decode($res['data'], true)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no soportado']);
}
