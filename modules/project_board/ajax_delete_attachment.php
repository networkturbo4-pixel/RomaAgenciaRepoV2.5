<?php
// modules/project_board/ajax_delete_attachment.php

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no válido']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("SELECT id, type, drive_file_id FROM project_attachments WHERE id = ?");
    $stmt->execute([$id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        throw new Exception("Archivo o formulario no encontrado.");
    }

    if ($attachment['type'] === 'pdf' && !empty($attachment['drive_file_id'])) {
        $driveHelper = new GoogleDriveHelper();
        if ($driveHelper->isConfigured()) {
            $driveHelper->deleteFile($attachment['drive_file_id']);
        }
    }

    $stmtDelete = $db->prepare("DELETE FROM project_attachments WHERE id = ?");
    $stmtDelete->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
