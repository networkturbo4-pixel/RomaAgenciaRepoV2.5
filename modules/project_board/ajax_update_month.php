<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/TaskSyncHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $status = isset($_POST['status']) ? trim($_POST['status']) : null;

    // Quick status update only
    if ($status !== null && empty($_POST['start_date']) && empty($_POST['due_date']) && !isset($_POST['folder_references'])) {
        TaskSyncHelper::syncMonthStatusToTasks($db, $id, $status);
        echo json_encode(['success' => true]);
        exit();
    }

    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $folder_references = isset($_POST['folder_references']) ? $_POST['folder_references'] : null;
    $folder_editables = isset($_POST['folder_editables']) ? $_POST['folder_editables'] : null;
    $folder_finals = isset($_POST['folder_finals']) ? $_POST['folder_finals'] : null;

    $drive_folders_json = isset($_POST['drive_folders_json']) ? $_POST['drive_folders_json'] : null;
    
    $extraSet = "";
    $params = [$start_date, $due_date, $folder_references, $folder_editables, $folder_finals];

    if ($status !== null) {
        $extraSet .= ", status = ?";
        $params[] = $status;
    }

    if ($drive_folders_json) {
        $foldersData = json_decode($drive_folders_json, true);
        if ($foldersData && isset($foldersData['main_folder'])) {
            $driveFolderId = $foldersData['main_folder']['id'];
            $driveFolderLink = $foldersData['main_folder']['url'];
            
            $extraSet .= ", drive_folder_id = ?, drive_folder_link = ?, drive_folders_json = ?";
            $params[] = $driveFolderId;
            $params[] = $driveFolderLink;
            $params[] = $drive_folders_json;
        }
    }
    
    $params[] = $id;

    $stmtUpdate = $db->prepare("
        UPDATE project_months 
        SET start_date = ?, due_date = ?, folder_references = ?, folder_editables = ?, folder_finals = ? {$extraSet}
        WHERE id = ?
    ");
    $stmtUpdate->execute($params);

    if ($status !== null) {
        TaskSyncHelper::syncMonthStatusToTasks($db, $id, $status);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


