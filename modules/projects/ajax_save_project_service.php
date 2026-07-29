<?php
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $id           = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
    $project_id   = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? intval($_POST['project_id']) : null;
    $service_id   = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? intval($_POST['service_id']) : null;
    $title        = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';
    $start_date   = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] : null;
    $due_date     = isset($_POST['due_date']) && $_POST['due_date'] !== '' ? $_POST['due_date'] : null;
    $status       = isset($_POST['status']) && $_POST['status'] !== '' ? $_POST['status'] : 'pending';
    $create_sub   = isset($_POST['create_subfolder']) && $_POST['create_subfolder'] == '1';

    if (!$project_id || !$title || !$service_id) {
        echo json_encode(['success' => false, 'message' => 'El proyecto, el título y el tipo de tarea son obligatorios']);
        exit;
    }

    if ($id) {
        $sql = "UPDATE project_services SET 
                    service_id = :service_id, 
                    title = :title, 
                    description = :description, 
                    start_date = :start_date, 
                    due_date = :due_date, 
                    status = :status, 
                    updated_at = NOW()
                WHERE id = :id AND project_id = :project_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':due_date', $due_date);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        
        $new_id = $id;
        $response_msg = 'Tarea actualizada exitosamente';
    } else {
        $sql = "INSERT INTO project_services 
                    (project_id, service_id, title, description, start_date, due_date, status, created_at, updated_at)
                VALUES 
                    (:project_id, :service_id, :title, :description, :start_date, :due_date, :status, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':due_date', $due_date);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        $new_id = $db->lastInsertId();
        $response_msg = 'Tarea agregada al proyecto exitosamente';
    }

    // ── Google Drive Subfolder Integration ──
    if ($create_sub) {
        // Fetch parent project drive folder
        $stmtPj = $db->prepare("SELECT drive_folder_id FROM module_projects WHERE id = ?");
        $stmtPj->execute([$project_id]);
        $parentFolderId = $stmtPj->fetchColumn();

        if ($parentFolderId) {
            require_once 'includes/GoogleDriveHelper.php';
            $driveHelper = new GoogleDriveHelper();
            
            if ($driveHelper->isConfigured()) {
                // Create subfolder named after the service title
                $folderId = $driveHelper->createFolder($title, $parentFolderId);
                if ($folderId) {
                    $folderLink = "https://drive.google.com/drive/folders/" . $folderId;
                    
                    // Create inner folders
                    $editablesId = $driveHelper->createFolder("Editables", $folderId);
                    $driveHelper->createFolder("Formularios", $folderId);
                    $referenciasId = $driveHelper->createFolder("Referencias", $folderId);
                    
                    // Update the newly created service with its folder ID and Referencias/Editables ID
                    $stmtDrive = $db->prepare("UPDATE project_services SET drive_folder_id = ?, drive_folder_link = ?, drive_referencias_id = ?, drive_editables_id = ? WHERE id = ?");
                    $stmtDrive->execute([$folderId, $folderLink, $referenciasId, $editablesId, $new_id]);
                    
                    $response_msg .= ' (Carpeta principal y subcarpetas creadas en Drive)';
                }
            }
        }
    }

    echo json_encode([
        'success'    => true,
        'message'    => $response_msg,
        'service_id' => intval($new_id)
    ]);
} catch (PDOException $e) {
    error_log("DB Error ajax_save_project_service: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
