<?php
session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Check role
$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$role_id = $stmtRole->fetchColumn();
$isAdmin = ($role_id == 1 || $role_id == 2); // Admins and maybe managers
$isClient = ($role_id == 3);

$drive = new GoogleDriveHelper();
if (!$drive->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado.']);
    exit();
}

try {
    switch ($action) {
        case 'list':
            $folderId = $_POST['folderId'] ?? $_GET['folderId'] ?? '';
            if (!$folderId) {
                echo json_encode(['success' => false, 'error' => 'Folder ID es requerido']);
                exit();
            }

            // Get current folder info for breadcrumbs
            $folderInfo = $drive->getFolderInfo($folderId);
            $files = $drive->listFiles($folderId);
            
            // Generate breadcrumbs recursively could be slow, so we just return the parents
            $breadcrumbs = [];
            if ($folderInfo) {
                $breadcrumbs[] = [
                    'id' => $folderInfo->getId(),
                    'name' => $folderInfo->getName()
                ];
            }

            echo json_encode(['success' => true, 'files' => $files, 'currentFolder' => $folderInfo]);
            break;

        case 'create_folder':
            if ($isClient) {
                echo json_encode(['success' => false, 'error' => 'No tienes permisos para crear carpetas']);
                exit();
            }
            $parentFolderId = $_POST['parentFolderId'] ?? '';
            $folderName = $_POST['folderName'] ?? '';
            
            if (!$parentFolderId || !$folderName) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit();
            }

            $newFolderId = $drive->createFolder($folderName, $parentFolderId);
            if ($newFolderId) {
                echo json_encode(['success' => true, 'id' => $newFolderId, 'name' => $folderName]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear la carpeta']);
            }
            break;

        case 'rename':
            if ($isClient) {
                echo json_encode(['success' => false, 'error' => 'No tienes permisos para renombrar']);
                exit();
            }
            $fileId = $_POST['fileId'] ?? '';
            $newName = $_POST['newName'] ?? '';
            
            if (!$fileId || !$newName) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit();
            }

            $updated = $drive->renameFile($fileId, $newName);
            if ($updated) {
                echo json_encode(['success' => true, 'file' => $updated]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al renombrar el archivo']);
            }
            break;

        case 'delete':
            if ($isClient) {
                echo json_encode(['success' => false, 'error' => 'No tienes permisos para eliminar']);
                exit();
            }
            $fileId = $_POST['fileId'] ?? '';
            
            if (!$fileId) {
                echo json_encode(['success' => false, 'error' => 'ID de archivo requerido']);
                exit();
            }

            $deleted = $drive->deleteFile($fileId);
            if ($deleted) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar el archivo']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
