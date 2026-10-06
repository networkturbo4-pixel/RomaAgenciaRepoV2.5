<?php
// modules/brand_guidelines/download_asset.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';

$database = new Database();
$db = $database->getConnection();

$guidelineId = (int)($_GET['id'] ?? 0);
$fileUrl = trim($_GET['file'] ?? '');

if (!$guidelineId || empty($fileUrl)) {
    die("Parámetros insuficientes.");
}

$stmt = $db->prepare("SELECT * FROM brand_guidelines WHERE id = ?");
$stmt->execute([$guidelineId]);
$bg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bg) {
    die("Manual de marca no encontrado.");
}

// Check access permission if private
$isLoggedIn = isset($_SESSION['user_id']);
$isUnlocked = !empty($_SESSION['bg_unlocked_' . $guidelineId]);

if ($bg['is_public'] == 0 && !$isLoggedIn && !$isUnlocked) {
    die("Acceso restringido. Este manual es privado.");
}

if ($bg['allow_asset_download'] == 0 && !$isLoggedIn) {
    die("La descarga directa de archivos está deshabilitada para este manual.");
}

// Handle Google Drive proxy downloads
if (strpos($fileUrl, 'drive_proxy.php') !== false) {
    $parts = parse_url($fileUrl);
    parse_str($parts['query'] ?? '', $q);
    if (!empty($q['id'])) {
        require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';
        $drive = new GoogleDriveHelper();
        if ($drive->isConfigured()) {
            $content = $drive->streamFile($q['id']);
            if ($content) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: attachment; filename="asset_' . $q['id'] . '"');
                echo $content;
                exit();
            }
        }
    }
}

// Sanitize path to prevent directory traversal
$cleanPath = str_replace(['../', '..\\'], '', $fileUrl);
$fullPath = realpath(__DIR__ . '/../../' . ltrim($cleanPath, '/'));
$uploadsDir = realpath(__DIR__ . '/../../uploads/brand_guidelines');

if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, $uploadsDir) !== 0) {
    die("Archivo no encontrado o acceso no permitido.");
}

$mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
$filename = basename($fullPath);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
flush();
readfile($fullPath);
exit();
