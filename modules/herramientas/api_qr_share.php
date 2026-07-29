<?php
require_once '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

// Asegurar que la tabla existe
try {
    $db->exec("CREATE TABLE IF NOT EXISTS qr_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hash VARCHAR(20) NOT NULL UNIQUE,
        config_json TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // ignorar si ya existe
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Guardar configuración
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['config'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }
    
    $configJson = json_encode($data['config']);
    $hash = substr(md5(uniqid(rand(), true)), 0, 8); // 8 character random hash
    
    try {
        $stmt = $db->prepare("INSERT INTO qr_links (hash, config_json) VALUES (:hash, :config)");
        $stmt->bindParam(':hash', $hash);
        $stmt->bindParam(':config', $configJson);
        $stmt->execute();
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $path = strtok($_SERVER["REQUEST_URI"], '?');
        $dir = dirname($_SERVER['PHP_SELF']);
        $shortLink = $protocol . $host . $dir . '/?qr=' . $hash;
        
        echo json_encode(['success' => true, 'link' => $shortLink, 'hash' => $hash]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en BD: ' . $e->getMessage()]);
    }
} elseif ($method === 'GET') {
    // Recuperar configuración
    if (!isset($_GET['hash'])) {
        echo json_encode(['success' => false, 'message' => 'Falta el parámetro hash']);
        exit;
    }
    
    $hash = $_GET['hash'];
    try {
        $stmt = $db->prepare("SELECT config_json FROM qr_links WHERE hash = :hash LIMIT 1");
        $stmt->bindParam(':hash', $hash);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            echo json_encode(['success' => true, 'config' => json_decode($row['config_json'], true)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Enlace no encontrado o expirado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no soportado']);
}
?>
