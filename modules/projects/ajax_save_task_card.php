<?php
// modules/projects/ajax_save_task_card.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/GoogleDriveHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
    $task_group_id = isset($_POST['task_group_id']) ? intval($_POST['task_group_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Nuevo';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Media';
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '[]';
    $is_locked = !empty($_POST['is_locked']) ? 1 : 0;
    $is_approved = !empty($_POST['is_approved']) ? 1 : 0;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $subtasks_json = isset($_POST['subtasks']) ? $_POST['subtasks'] : '[]';

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'El título es obligatorio']);
        exit;
    }

    $reference_image = null;
    $editable_file = null;
    
    if ($card_id > 0) {
        // Fetch existing images if we are updating
        $stmtImg = $db->prepare("SELECT reference_image, editable_file, task_group_id FROM task_cards WHERE id = ?");
        $stmtImg->execute([$card_id]);
        $row = $stmtImg->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $reference_image = $row['reference_image'];
            $editable_file = $row['editable_file'];
            // If no task_group_id provided, use existing
            if (!$task_group_id) $task_group_id = $row['task_group_id'];
        }
    }

    if (!$task_group_id) {
        echo json_encode(['success' => false, 'message' => 'El grupo es obligatorio']);
        exit;
    }

    // Fetch the project_services drive IDs
    $stmtDrive = $db->prepare("
        SELECT ps.drive_referencias_id, ps.drive_editables_id 
        FROM task_groups tg
        JOIN project_services ps ON tg.project_service_id = ps.id
        WHERE tg.id = ?
    ");
    $stmtDrive->execute([$task_group_id]);
    $driveData = $stmtDrive->fetch(PDO::FETCH_ASSOC);
    $driveReferenciasId = $driveData ? $driveData['drive_referencias_id'] : null;
    $driveEditablesId = $driveData ? $driveData['drive_editables_id'] : null;

    $driveHelper = null;
    if ($driveReferenciasId || $driveEditablesId) {
        $driveHelper = new GoogleDriveHelper();
        if (!$driveHelper->isConfigured()) {
            $driveHelper = null;
        }
    }

    // Handle reference_image upload
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] == UPLOAD_ERR_OK && $driveHelper && $driveReferenciasId) {
        $fileTmpPath = $_FILES['reference_image']['tmp_name'];
        $fileName = $_FILES['reference_image']['name'];
        $uploadData = $driveHelper->uploadFile($fileTmpPath, $fileName, $driveReferenciasId);
        if ($uploadData && isset($uploadData['webViewLink'])) {
            $driveHelper->makePublicViewer($uploadData['id']);
            $reference_image = $uploadData['webViewLink'];
        }
    }

    // Handle editable_file upload
    if (isset($_FILES['editable_file']) && $_FILES['editable_file']['error'] == UPLOAD_ERR_OK && $driveHelper && $driveEditablesId) {
        $fileTmpPath = $_FILES['editable_file']['tmp_name'];
        $fileName = $_FILES['editable_file']['name'];
        $uploadData = $driveHelper->uploadFile($fileTmpPath, $fileName, $driveEditablesId);
        if ($uploadData && isset($uploadData['webViewLink'])) {
            $driveHelper->makePublicEditor($uploadData['id']); // Give editor permissions for editables
            $editable_file = $uploadData['webViewLink'];
        }
    }

    if ($card_id > 0) {
        $stmt = $db->prepare("
            UPDATE task_cards 
            SET task_group_id = ?, title = ?, description = ?, details = ?, reference_image = ?, editable_file = ?, status = ?, priority = ?, tags = ?, is_locked = ?, is_approved = ?, start_date = ?, due_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $task_group_id, $title, $description, $details, $reference_image, $editable_file, $status, $priority, $tags, $is_locked, $is_approved, $start_date, $due_date, $card_id
        ]);
        $message = 'Tarjeta actualizada';
    } else {
        $stmt = $db->prepare("
            INSERT INTO task_cards (task_group_id, title, description, details, reference_image, editable_file, status, priority, tags, is_locked, is_approved, start_date, due_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_group_id, $title, $description, $details, $reference_image, $editable_file, $status, $priority, $tags, $is_locked, $is_approved, $start_date, $due_date
        ]);
        $card_id = $db->lastInsertId();
        $message = 'Tarjeta creada';
    }

    // Process subtasks
    $subtasks = json_decode($subtasks_json, true);
    if (is_array($subtasks)) {
        // Simple sync approach: Delete old, insert new (with their statuses and sort order)
        $stmtDel = $db->prepare("DELETE FROM task_card_subtasks WHERE task_card_id = ?");
        $stmtDel->execute([$card_id]);

        $stmtSub = $db->prepare("INSERT INTO task_card_subtasks (task_card_id, title, is_completed, sort_order, assigned_user_id, due_date) VALUES (?, ?, ?, ?, ?, ?)");
        $sort = 0;
        foreach ($subtasks as $st) {
            $st_title = trim($st['title']);
            if (!empty($st_title)) {
                $is_comp = !empty($st['is_completed']) ? 1 : 0;
                $assigned_user = !empty($st['assigned_user_id']) ? intval($st['assigned_user_id']) : null;
                $due_date = !empty($st['due_date']) ? $st['due_date'] : null;
                $stmtSub->execute([$card_id, $st_title, $is_comp, $sort, $assigned_user, $due_date]);
                $sort++;
            }
        }
    }

    // Simple Log
    $logAction = "Actualizó la tarjeta";
    if ($status === 'Terminado') $logAction = "Movió a Terminado";
    if ($is_approved) $logAction = "Aprobó la tarjeta";
    if ($is_locked) $logAction = "Congeló la tarjeta";
    if (isset($message) && $message == 'Tarjeta creada') $logAction = "Creó la tarjeta";

    $stmtLog = $db->prepare("INSERT INTO task_card_logs (task_card_id, user_id, action) VALUES (?, ?, ?)");
    $stmtLog->execute([$card_id, $_SESSION['user_id'], $logAction]);

    echo json_encode([
        'success' => true, 
        'message' => $message, 
        'card' => [
            'id' => $card_id,
            'title' => $title,
            'status' => $status,
            'reference_image' => $reference_image
        ]
    ]);
} catch (Exception $e) {
    error_log("Error guardando tarjeta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar la tarjeta']);
}
?>
