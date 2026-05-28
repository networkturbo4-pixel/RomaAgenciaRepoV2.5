<?php
// modules/project_board/ajax_upload_pdf.php

ob_start();
session_start();
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log_upload.txt');

require_once '../../config/database.php';
require_once '../../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Método no válido']);
    exit();
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
if (!$projectId) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'ID de proyecto no válido']);
    exit();
}

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Error al subir el archivo']);
    exit();
}

$fileTmpPath = $_FILES['pdf_file']['tmp_name'];
$fileName = $_FILES['pdf_file']['name'];
$fileSize = $_FILES['pdf_file']['size'];

$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($fileExtension !== 'pdf') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos PDF']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get project drive folder
    $stmt = $db->prepare("SELECT drive_folder_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project || empty($project['drive_folder_id'])) {
        throw new Exception("El proyecto no tiene una carpeta de Drive configurada.");
    }

    $projectRootId = $project['drive_folder_id'];

    $driveHelper = new GoogleDriveHelper();
    if (!$driveHelper->isConfigured()) {
        throw new Exception("Google Drive no está configurado.");
    }

    // Find or create "Form" folder
    $formFolderId = null;
    $existingFolders = $driveHelper->listFolders($projectRootId);
    if ($existingFolders) {
        foreach ($existingFolders as $f) {
            if (strtolower($f->name) === 'form' || strtolower($f->name) === 'formularios') {
                $formFolderId = $f->id;
                break;
            }
        }
    }

    if (!$formFolderId) {
        $formFolderId = $driveHelper->createFolder('Form', $projectRootId);
        if (!$formFolderId) {
            throw new Exception("No se pudo crear la carpeta Form en Drive.");
        }
    }

    // Upload file
    $uploadResult = $driveHelper->uploadFile($fileTmpPath, $fileName, $formFolderId);
    if (!$uploadResult) {
        throw new Exception("Error al subir el archivo a Google Drive.");
    }

    $driveFileId = $uploadResult['id'];
    $webViewLink = $uploadResult['webViewLink'];

    // Insert into project_attachments
    $stmtInsert = $db->prepare("
        INSERT INTO project_attachments (project_id, type, file_name, file_path, drive_file_id) 
        VALUES (?, 'pdf', ?, ?, ?)
    ");
    $stmtInsert->execute([
        $projectId,
        $fileName,
        $webViewLink,
        $driveFileId
    ]);

    $attachmentId = $db->lastInsertId();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'attachment' => [
            'id' => $attachmentId,
            'file_name' => $fileName,
            'url' => $webViewLink
        ]
    ]);

} catch (Throwable $e) {
    error_log("Upload Error: " . $e->getMessage() . " on line " . $e->getLine());
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
