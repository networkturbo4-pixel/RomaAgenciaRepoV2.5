<?php
// modules/forms/ajax_upload_form_avatar.php — Upload custom brand avatar for forms
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la subida']);
    exit();
}

$file = $_FILES['avatar'];
$maxSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'El archivo no debe exceder 5MB']);
    exit();
}

// Validate MIME type & Extension
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'error' => 'Formato no permitido. Sube una imagen (PNG, JPG, WEBP, SVG)']);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'])) {
    $ext = 'png';
}

$targetDir = __DIR__ . '/../../uploads/form_avatars/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = 'form_avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $targetDir . $filename;
$publicUrl = 'uploads/form_avatars/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'url' => $publicUrl,
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen en el servidor']);
}
