<?php
// modules/project_board/ajax_save_month.php
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

    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $month = isset($_POST['month']) ? (int)$_POST['month'] : 0;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (!$due_date && $year && $month) {
        $due_date = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    }
    
    $folder_references = isset($_POST['folder_references']) ? $_POST['folder_references'] : null;
    $folder_editables = isset($_POST['folder_editables']) ? $_POST['folder_editables'] : null;
    $folder_finals = isset($_POST['folder_finals']) ? $_POST['folder_finals'] : null;

    if (!$project_id || !$month || !$year) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
        exit();
    }

    // Check if month already exists for this project
    $stmtCheck = $db->prepare("SELECT id FROM project_months WHERE project_id = ? AND month = ? AND year = ?");
    $stmtCheck->execute([$project_id, $month, $year]);
    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'Este mes ya ha sido agregado al proyecto.']);
        exit();
    }

    $drive_folders_json = isset($_POST['drive_folders_json']) ? $_POST['drive_folders_json'] : null;
    
    $driveFolderId = null;
    $driveFolderLink = null;
    
    if ($drive_folders_json) {
        $foldersData = json_decode($drive_folders_json, true);
        if ($foldersData && isset($foldersData['main_folder'])) {
            $driveFolderId = $foldersData['main_folder']['id'];
            $driveFolderLink = $foldersData['main_folder']['url'];
        }
    }

    // Insert new month
    $stmtInsert = $db->prepare("
        INSERT INTO project_months 
        (project_id, month, year, start_date, due_date, folder_references, folder_editables, folder_finals, status, drive_folder_id, drive_folder_link, drive_folders_json) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)
    ");
    $stmtInsert->execute([$project_id, $month, $year, $start_date, $due_date, $folder_references, $folder_editables, $folder_finals, $driveFolderId, $driveFolderLink, $drive_folders_json]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
