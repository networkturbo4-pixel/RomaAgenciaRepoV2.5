<?php
// modules/month_board/ajax_delete_file.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$urls = isset($_POST['url']) ? $_POST['url'] : [];
$month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
$post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'Post Terminado';

if (!is_array($urls)) {
    $urls = [$urls];
}

if (empty($urls)) {
    echo json_encode(['success' => false, 'error' => 'No URL provided']);
    exit();
}

require_once '../../config/database.php';

// IMPORTANT: $db must be a global variable BEFORE GoogleDriveHelper is instantiated
// because GoogleDriveHelper constructor uses "global $db" to load credentials
$db = (new Database())->getConnection();

require_once '../../includes/GoogleDriveHelper.php';

$drive = new GoogleDriveHelper();
$targetFolderId = null;
$deletedFromDrive = [];
$deletedLocal = [];

if ($drive->isConfigured() && $month_id > 0) {
    $stmt = $db->prepare("SELECT drive_folders_json FROM project_months WHERE id = ?");
    $stmt->execute([$month_id]);
    $jsonStr = $stmt->fetchColumn();
    
    if ($jsonStr) {
        $foldersData = json_decode($jsonStr, true);
        if (isset($foldersData['subfolders'])) {
            $targetName = ($post_type === 'Post Terminado') ? 'POST TERMINADOS' : 'REFERENCIAS';
            foreach ($foldersData['subfolders'] as $sf) {
                if ($sf['name'] === $targetName) {
                    $targetFolderId = $sf['id'];
                    break;
                }
            }
        }
    }
}

foreach ($urls as $u) {
    if (empty($u)) continue;

    // Case 1: Direct Google Drive URL
    if (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?export=view&id=|thumbnail\?id=)([\w-]+)/', $u, $matches)) {
        $fileId = $matches[1];
        if ($drive->isConfigured()) {
            try {
                $drive->deleteFile($fileId);
                $deletedFromDrive[] = $fileId;
            } catch (Exception $e) {
                error_log("Error deleting Drive file $fileId: " . $e->getMessage());
            }
        }
    } 
    // Case 2: Local upload path - delete local file AND its copy on Drive
    else if (strpos($u, 'uploads/') !== false) {
        // Delete local file
        $localPath = '';
        if (preg_match('/(uploads\/.*)/', $u, $localMatch)) {
             $localPath = '../../' . $localMatch[1];
        }
        
        if ($localPath && file_exists($localPath)) {
            unlink($localPath);
            $deletedLocal[] = $localPath;
        }
        
        // Delete from Drive by filename
        if ($drive->isConfigured() && $targetFolderId) {
            $oldFileName = basename($u);
            try {
                $drive->deleteFileByName($oldFileName, $targetFolderId);
                $deletedFromDrive[] = $oldFileName;
            } catch (Exception $e) {
                error_log("Error deleting Drive file by name $oldFileName: " . $e->getMessage());
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'deleted_drive' => count($deletedFromDrive),
    'deleted_local' => count($deletedLocal)
]);
