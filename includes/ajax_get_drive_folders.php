<?php
// includes/ajax_get_drive_folders.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

require_once 'GoogleDriveHelper.php';

$action = $_POST['action'] ?? 'list_folders';

try {
    $drive = new GoogleDriveHelper();
    if (!$drive->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Drive no configurado.']);
        exit();
    }

    if ($action === 'list_shared_drives') {
        $drives = $drive->listSharedDrives();
        $response = [];
        if ($drives) {
            foreach ($drives as $d) {
                $response[] = [
                    'id' => $d->id,
                    'name' => $d->name,
                    'type' => 'drive'
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $response]);
        exit();
    }

    if ($action === 'list_folders') {
        $parentId = $_POST['parent_id'] ?? 'root';
        $driveId = $_POST['drive_id'] ?? null;
        
        $folders = $drive->listFolders($parentId, $driveId);
        $response = [];
        if ($folders) {
            foreach ($folders as $f) {
                $response[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'icon' => $f->iconLink,
                    'url' => $f->webViewLink,
                    'created' => $f->createdTime
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $response]);
        exit();
    }

    if ($action === 'get_folder_info') {
        $folderId = $_POST['folder_id'] ?? null;
        if (!$folderId) throw new Exception("Folder ID is required");
        $info = $drive->getFolderInfo($folderId);
        echo json_encode(['success' => true, 'data' => [
            'id' => $info->id,
            'name' => $info->name,
            'parents' => $info->parents
        ]]);
        exit();
    }

    if ($action === 'create_folder') {
        $folderName = $_POST['folder_name'] ?? 'Nueva Carpeta';
        $parentId = $_POST['parent_id'] ?? 'root';
        $newFolderId = $drive->createFolder($folderName, $parentId);
        if ($newFolderId) {
            echo json_encode(['success' => true, 'data' => ['id' => $newFolderId, 'name' => $folderName]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear la carpeta']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Acción no válida']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
