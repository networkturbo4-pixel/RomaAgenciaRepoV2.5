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

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $folder_references = isset($_POST['folder_references']) ? $_POST['folder_references'] : null;
    $folder_editables = isset($_POST['folder_editables']) ? $_POST['folder_editables'] : null;
    $folder_finals = isset($_POST['folder_finals']) ? $_POST['folder_finals'] : null;

    $drive_folders_json = isset($_POST['drive_folders_json']) ? $_POST['drive_folders_json'] : null;
    
    $extraSet = "";
    $params = [$start_date, $folder_references, $folder_editables, $folder_finals];

    if ($drive_folders_json) {
        $foldersData = json_decode($drive_folders_json, true);
        if ($foldersData && isset($foldersData['main_folder'])) {
            $driveFolderId = $foldersData['main_folder']['id'];
            $driveFolderLink = $foldersData['main_folder']['url'];
            
            $extraSet = ", drive_folder_id = ?, drive_folder_link = ?, drive_folders_json = ?";
            $params[] = $driveFolderId;
            $params[] = $driveFolderLink;
            $params[] = $drive_folders_json;
        }
    }

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }
    
    $params[] = $id;

    $stmtUpdate = $db->prepare("
        UPDATE project_months 
        SET start_date = ?, folder_references = ?, folder_editables = ?, folder_finals = ? {$extraSet}
        WHERE id = ?
    ");
    $stmtUpdate->execute($params);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
