<?php
// modules/month_board/ajax_finish_resumable_upload.php

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

$fileId = $_POST['file_id'] ?? '';

if (empty($fileId)) {
    echo json_encode(['success' => false, 'error' => 'Falta el ID del archivo']);
    exit();
}

$driveHelper = new GoogleDriveHelper();
if (!$driveHelper->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Drive no configurado']);
    exit();
}

// Make the file public (reader)
$driveHelper->makePublic($fileId);

// You could fetch the file metadata to return the webViewLink here if needed, 
// but since the UI mostly just uploads it to the folder, returning success is often enough.
// We'll return the standard drive link.
$webViewLink = "https://drive.google.com/file/d/" . $fileId . "/view";

echo json_encode(['success' => true, 'web_view_link' => $webViewLink]);
