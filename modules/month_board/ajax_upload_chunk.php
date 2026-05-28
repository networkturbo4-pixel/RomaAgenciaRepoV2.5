<?php
// modules/month_board/ajax_upload_chunk.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 401, 'error' => 'No autorizado']);
    exit();
}

// Disable max execution time for chunk uploads
ini_set('max_execution_time', 0);

$uploadUrl = $_POST['upload_url'] ?? '';
$start = (int)($_POST['start'] ?? 0);
$end = (int)($_POST['end'] ?? 0);
$totalSize = (int)($_POST['total_size'] ?? 0);

if (empty($uploadUrl) || !isset($_FILES['chunk'])) {
    echo json_encode(['status' => 400, 'error' => 'Missing data']);
    exit();
}

$chunkFile = $_FILES['chunk']['tmp_name'];
$chunkSize = $_FILES['chunk']['size'];

// Ensure end doesn't exceed totalSize
if ($end > $totalSize) {
    $end = $totalSize;
}

try {
    $handle = fopen($chunkFile, 'r');
    if (!$handle) {
        throw new Exception("Cannot open chunk file");
    }

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $handle);
    curl_setopt($ch, CURLOPT_INFILESIZE, $chunkSize);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // HTTP status codes >= 400 will NOT cause curl to fail, we want to read the response.
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Range: bytes {$start}-" . ($end - 1) . "/{$totalSize}",
        "Content-Type: application/octet-stream"
    ]);

    $responseBody = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    fclose($handle);

    if ($error) {
        echo json_encode(['status' => 500, 'error' => $error]);
        exit();
    }

    // Google returns 308 for incomplete, 200/201 for complete
    echo json_encode([
        'status' => $statusCode,
        'body' => $responseBody
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 500, 'error' => $e->getMessage()]);
}
