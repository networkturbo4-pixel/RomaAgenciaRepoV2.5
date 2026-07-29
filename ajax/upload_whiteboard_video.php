<?php
// ajax/upload_whiteboard_video.php
error_reporting(0);
require_once '../config/database.php';
require_once '../includes/GoogleDriveHelper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!isset($_FILES['video'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún video']);
    exit;
}

$file = $_FILES['video'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Error de subida PHP: ' . $file['error']]);
    exit;
}

// Basic video mime type check
$mime = mime_content_type($file['tmp_name']);
if (strpos($mime, 'video/') !== 0) {
    echo json_encode(['success' => false, 'error' => 'El archivo no es un video válido']);
    exit;
}

$db = (new Database())->getConnection();
$driveHelper = new GoogleDriveHelper();

if (!$driveHelper->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado. Comunícate con el administrador.']);
    exit;
}

$folderName = "Pizarras_Videos";
$parentFolderId = null;

$existingFolders = $driveHelper->searchFiles("name='{$folderName}' and mimeType='application/vnd.google-apps.folder'");
if (!empty($existingFolders)) {
    $parentFolderId = $existingFolders[0]['id'];
} else {
    $parentFolderId = $driveHelper->createFolder($folderName);
    $driveHelper->makePublic($parentFolderId);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'mp4';
$newName = 'wb_vid_' . uniqid() . '.' . $ext;

$uploadResult = $driveHelper->uploadFile($file['tmp_name'], $newName, $parentFolderId);

if ($uploadResult && isset($uploadResult['id'])) {
    $driveHelper->makePublic($uploadResult['id']);
    
    // We can use the drive_proxy.php or a direct Google Drive link
    $proxyUrl = 'ajax/drive_proxy.php?id=' . $uploadResult['id'];
    
    echo json_encode([
        'success' => true,
        'url' => $proxyUrl,
        'drive_id' => $uploadResult['id']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al subir el video a Google Drive']);
}
