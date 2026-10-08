<?php
// modules/quotes/ajax_upload_cover.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cover_file'])) {
    $file = $_FILES['cover_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        exit();
    }
    
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'Formato no soportado. Usa JPG, PNG, WEBP o AVIF.']);
        exit();
    }
    
    // Max 8MB
    if ($file['size'] > 8 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'La imagen no debe superar los 8MB.']);
        exit();
    }
    
    $upload_dir = __DIR__ . '/../../uploads/quotes/covers/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }
    
    $filename = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $relative_url = 'uploads/quotes/covers/' . $filename;
        echo json_encode([
            'success' => true,
            'message' => 'Portada subida con éxito',
            'url' => $relative_url
        ]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar la imagen en el servidor.']);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Petición inválida']);
