<?php
// ajax/ajax_list_backups.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No estás autenticado.']);
    exit;
}

require_once '../config/database.php';
require_once '../includes/GoogleDriveHelper.php';

$db = (new Database())->getConnection();

// Verificar admin
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para ver las copias de seguridad.']);
    exit;
}

try {
    $drive = new GoogleDriveHelper();
    if (!$drive->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado.']);
        exit;
    }

    $folderName = 'copia de seguridad';
    $targetFolderId = null;

    $folders = $drive->listFolders('root');
    if ($folders) {
        foreach ($folders as $f) {
            if (strtolower($f->getName()) === strtolower($folderName)) {
                $targetFolderId = $f->getId();
                break;
            }
        }
    }

    if (!$targetFolderId) {
        echo json_encode(['success' => true, 'files' => []]);
        exit;
    }

    $driveFiles = $drive->listFiles($targetFolderId);
    if ($driveFiles) {
        usort($driveFiles, function($a, $b) {
            return strtotime($b['createdTime']) - strtotime($a['createdTime']);
        });
        echo json_encode(['success' => true, 'files' => $driveFiles]);
    } else {
        echo json_encode(['success' => true, 'files' => []]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
