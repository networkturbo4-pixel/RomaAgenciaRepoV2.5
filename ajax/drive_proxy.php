<?php
// ajax/drive_proxy.php
error_reporting(0);
require_once '../config/database.php';
require_once '../includes/GoogleDriveHelper.php';
session_start();

if (!isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    exit;
}

$fileId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
$db = (new Database())->getConnection();
$driveHelper = new GoogleDriveHelper();

if (!$driveHelper->isConfigured()) {
    header('HTTP/1.0 500 Internal Server Error');
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Cache-Control: public, max-age=31536000");

$content = $driveHelper->streamFile($fileId);

if ($content) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($content);
    if ($mime) {
        header("Content-Type: " . $mime);
    } else {
        header("Content-Type: application/octet-stream");
    }
    
    // If download requested, set proper filename
    if (isset($_GET['dl']) && $_GET['dl'] == '1') {
        $filename = isset($_GET['name']) ? basename($_GET['name']) : 'download';
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    }
    
    header("Content-Length: " . strlen($content));
    echo $content;
} else {
    header('HTTP/1.0 404 Not Found');
}
