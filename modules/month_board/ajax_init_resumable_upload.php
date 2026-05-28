<?php
// modules/month_board/ajax_init_resumable_upload.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

require_once '../../includes/GoogleDriveHelper.php';

$fileName = $_POST['file_name'] ?? '';
$mimeType = $_POST['mime_type'] ?? '';
$folderId = $_POST['folder_id'] ?? '';

if (empty($fileName) || empty($mimeType) || empty($folderId)) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit();
}

$driveHelper = new GoogleDriveHelper();
if (!$driveHelper->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Drive no configurado']);
    exit();
}

$uploadUrl = $driveHelper->createResumableUploadSession($fileName, $mimeType, $folderId);

if ($uploadUrl) {
    echo json_encode(['success' => true, 'upload_url' => $uploadUrl]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo crear la sesión de subida']);
}
