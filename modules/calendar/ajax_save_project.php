<?php
// modules/calendar/ajax_save_project.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$work_order_id = $_POST['work_order_id'] ?? null;
$team_members = $_POST['team_members'] ?? [];

if (!$work_order_id) {
    echo json_encode(['success' => false, 'error' => 'La orden de servicio es requerida']);
    exit();
}

try {
    // Check if a project already exists for this work order
    $stmtCheck = $db->prepare("SELECT id, brand_name FROM work_orders WHERE id = ?");
    $stmtCheck->execute([$work_order_id]);
    $workOrder = $stmtCheck->fetch();
    if (!$workOrder) {
        echo json_encode(['success' => false, 'error' => 'Orden de servicio no encontrada']);
        exit();
    }

    $stmtCheckProj = $db->prepare("SELECT id FROM projects WHERE work_order_id = ?");
    $stmtCheckProj->execute([$work_order_id]);
    if ($stmtCheckProj->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ya existe un proyecto para esta orden de servicio']);
        exit();
    }

    $teamJson = json_encode($team_members);

    // Google Drive Integration
    require_once '../../includes/GoogleDriveHelper.php';
    $drive = new GoogleDriveHelper();
    $driveFolderId = null;
    $driveFolderLink = null;

    $global_folder_id = $_POST['global_folder_id'] ?? null;
    $global_folder_link = $_POST['global_folder_link'] ?? null;

    if ($drive->isConfigured()) {
        if ($global_folder_id && $global_folder_link) {
            $driveFolderId = $global_folder_id;
            $driveFolderLink = $global_folder_link;
        } else {
            $folderName = "Proyecto - " . $workOrder['brand_name'] . " (WO: " . $work_order_id . ")";
            $folderInfo = $drive->createFolder($folderName);
            if ($folderInfo) {
                $driveFolderId = $folderInfo;
                $driveFolderLink = "https://drive.google.com/drive/folders/" . $folderInfo;
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO projects (work_order_id, team_members, status, drive_folder_id, drive_folder_link) VALUES (?, ?, 'active', ?, ?)");
    $stmt->execute([$work_order_id, $teamJson, $driveFolderId, $driveFolderLink]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos o Drive: ' . $e->getMessage()]);
}
