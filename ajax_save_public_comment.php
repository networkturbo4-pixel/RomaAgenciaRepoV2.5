<?php
// ajax_save_public_comment.php
ob_start();
header('Content-Type: application/json');

try {
    require_once 'config/database.php';
    $db = (new Database())->getConnection();

    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $comment_text = $_POST['comment_text'] ?? '';
    
    // Novedades: Hotspots y Copy sugerido
    $hotspot_x = isset($_POST['hotspot_x']) && $_POST['hotspot_x'] !== '' ? (float)$_POST['hotspot_x'] : null;
    $hotspot_y = isset($_POST['hotspot_y']) && $_POST['hotspot_y'] !== '' ? (float)$_POST['hotspot_y'] : null;
    $suggested_copy = isset($_POST['suggested_copy']) && trim($_POST['suggested_copy']) !== '' ? $_POST['suggested_copy'] : null;

    if ($post_id <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    $image_link = '';
    $audio_link = null;

    // Manejo de la subida de imagen local (desde input o pegada con Ctrl+V)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/comments/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        if (empty($ext) || !in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = 'png';
        }
        $fileName = 'cmt_' . time() . '_' . $post_id . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            $image_link = $targetPath;
        }
    }

    if (trim($comment_text) === '' && !empty($image_link)) {
        $comment_text = 'Adjunto captura';
    }

    // Manejo de Audio: guardar LOCAL primero (para reproducción), luego subir a Drive como respaldo
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK && $month_id > 0) {
        // 1. Guardar localmente SIEMPRE (el browser puede reproducir archivos locales)
        $audioDir = 'uploads/audios/';
        if (!is_dir($audioDir)) @mkdir($audioDir, 0777, true);
        $localAudioName = 'audio_' . time() . '_' . $post_id . '.webm';
        $localAudioPath = $audioDir . $localAudioName;
        
        if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $localAudioPath)) {
            $audio_link = $localAudioPath;
            
            // 2. Subir copia a Google Drive como respaldo (no afecta el link guardado)
            if (file_exists('includes/GoogleDriveHelper.php')) {
                try {
                    $stmtM = $db->prepare("SELECT drive_folder_id FROM project_months WHERE id = ?");
                    $stmtM->execute([$month_id]);
                    $month = $stmtM->fetch();
                    
                    if ($month && !empty($month['drive_folder_id'])) {
                        require_once 'includes/GoogleDriveHelper.php';
                        if (class_exists('GoogleDriveHelper')) {
                            $GLOBALS['db'] = $db;
                            $drive = new GoogleDriveHelper();
                            if ($drive->isConfigured()) {
                                $monthDriveId = $month['drive_folder_id'];
                                
                                // Buscar o crear carpeta "Audios"
                                $folders = $drive->listFolders($monthDriveId);
                                $audioFolderId = null;
                                if ($folders) {
                                    foreach ($folders as $f) {
                                        if (strtolower($f->getName()) === 'audios' || strtolower($f->getName()) === 'audio') {
                                            $audioFolderId = $f->getId();
                                            break;
                                        }
                                    }
                                }
                                if (!$audioFolderId) {
                                    $audioFolderId = $drive->createFolder('Audios', $monthDriveId);
                                }
                                
                                if ($audioFolderId) {
                                    $audioFileName = 'Audio_Cliente_' . date('Ymd_His') . '.webm';
                                    $drive->uploadFile($localAudioPath, $audioFileName, $audioFolderId);
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // Si Drive falla, no importa — el audio local ya está guardado
                    error_log("Drive audio backup failed: " . $e->getMessage());
                }
            }
        }
    }

    $comment_phase = $_POST['comment_phase'] ?? 'Parrilla Final';

    // Insertar el comentario (con fallback si alguna columna no existe en BD antigua)
    try {
        $stmt = $db->prepare("INSERT INTO post_comments (post_id, comment_text, image_link, status, phase, audio_link, hotspot_x, hotspot_y, suggested_copy) VALUES (?, ?, ?, 'Pendiente', ?, ?, ?, ?, ?)");
        $stmt->execute([$post_id, $comment_text, $image_link, $comment_phase, $audio_link, $hotspot_x, $hotspot_y, $suggested_copy]);
    } catch (Throwable $eInsert) {
        // Fallback básico si columnas avanzadas no existen
        try {
            $stmt = $db->prepare("INSERT INTO post_comments (post_id, comment_text, image_link, status, phase) VALUES (?, ?, ?, 'Pendiente', ?)");
            $stmt->execute([$post_id, $comment_text, $image_link, $comment_phase]);
        } catch (Throwable $eInsert2) {
            $stmt = $db->prepare("INSERT INTO post_comments (post_id, comment_text, image_link, status) VALUES (?, ?, ?, 'Pendiente')");
            $stmt->execute([$post_id, $comment_text, $image_link]);
        }
    }

    // Actualizar el estado del post a 'En Revisión (Con Cambios)'
    try {
        $stmtUpdate = $db->prepare("UPDATE month_posts SET status = 'En Revisión (Con Cambios)' WHERE id = ?");
        $stmtUpdate->execute([$post_id]);
    } catch (Throwable $eUpdate) {
        error_log("Error updating post status: " . $eUpdate->getMessage());
    }

    // Notify team members (opcional y seguro)
    if (file_exists('includes/PushHelper.php')) {
        try {
            $stmtProj = $db->prepare("SELECT p.team_members, mp.concept FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id JOIN projects p ON pm.project_id = p.id WHERE mp.id = ?");
            $stmtProj->execute([$post_id]);
            $proj = $stmtProj->fetch();
            if ($proj && !empty($proj['team_members'])) {
                $assignedIds = json_decode($proj['team_members'], true) ?: [];
                if (!empty($assignedIds)) {
                    require_once 'includes/PushHelper.php';
                    if (class_exists('PushHelper')) {
                        PushHelper::sendPushNotification($db, $assignedIds, "Comentario de Cliente", "El cliente dejó un comentario en '{$proj['concept']}': {$comment_text}", "index.php?module=calendar", "calendar", ['module' => 'calendar']);
                    }
                }
            }
        } catch (Throwable $ePush) {
            error_log("Push error: " . $ePush->getMessage());
        }
    }

    ob_clean();
    echo json_encode(['success' => true]);
    exit();

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
?>
