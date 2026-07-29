<?php
// ajax/upload_whiteboard_image.php
error_reporting(0);
require_once '../config/database.php';
require_once '../includes/GoogleDriveHelper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
    exit;
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Error de subida PHP: ' . $file['error']]);
    exit;
}

$db = (new Database())->getConnection();
$driveHelper = new GoogleDriveHelper();

if (!$driveHelper->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado. Comunícate con el administrador.']);
    exit;
}

$folderName = "Pizarras_Assets";
$parentFolderId = null;

$existingFolders = $driveHelper->searchFiles("name='{$folderName}' and mimeType='application/vnd.google-apps.folder'");
if (!empty($existingFolders)) {
    $parentFolderId = $existingFolders[0]['id'];
} else {
    $parentFolderId = $driveHelper->createFolder($folderName);
    $driveHelper->makePublic($parentFolderId);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'png';
$newName = 'wb_img_' . uniqid() . '.' . $ext;

$uploadResult = $driveHelper->uploadFile($file['tmp_name'], $newName, $parentFolderId);

if ($uploadResult && isset($uploadResult['id'])) {
    $driveHelper->makePublic($uploadResult['id']);
    
    // Devolvemos el proxy para evitar el problema de Canvas Tainted / CORS
    $proxyUrl = 'ajax/drive_proxy.php?id=' . $uploadResult['id'];
    
    echo json_encode([
        'success' => true,
        'url' => $proxyUrl,
        'drive_id' => $uploadResult['id']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al subir la imagen a Google Drive']);
}
