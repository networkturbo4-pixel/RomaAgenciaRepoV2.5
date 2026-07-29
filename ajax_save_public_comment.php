<?php
// ajax_save_public_comment.php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/PushHelper.php';
require_once 'includes/GoogleDriveHelper.php';

try {
    $db = (new Database())->getConnection();

    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $comment_text = $_POST['comment_text'] ?? '';
    
    // Novedades: Hotspots y Copy sugerido
    $hotspot_x = isset($_POST['hotspot_x']) && $_POST['hotspot_x'] !== '' ? (float)$_POST['hotspot_x'] : null;
    $hotspot_y = isset($_POST['hotspot_y']) && $_POST['hotspot_y'] !== '' ? (float)$_POST['hotspot_y'] : null;
    $suggested_copy = isset($_POST['suggested_copy']) && trim($_POST['suggested_copy']) !== '' ? $_POST['suggested_copy'] : null;

    if ($post_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    $image_link = '';
    $audio_link = null;

    // Manejo de la subida de imagen local
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/comments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['image_file']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            $image_link = $targetPath;
        }
    }

    // Manejo de Audio: guardar LOCAL primero (para reproducción), luego subir a Drive como respaldo
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK && $month_id > 0) {
        // 1. Guardar localmente SIEMPRE (el browser puede reproducir archivos locales)
        $audioDir = 'uploads/audios/';
        if (!is_dir($audioDir)) mkdir($audioDir, 0777, true);
        $localAudioName = 'audio_' . time() . '_' . $post_id . '.webm';
        $localAudioPath = $audioDir . $localAudioName;
        
        if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $localAudioPath)) {
            $audio_link = $localAudioPath;
            
            // 2. Subir copia a Google Drive como respaldo (no afecta el link guardado)
            try {
                $stmtM = $db->prepare("SELECT drive_folder_id FROM project_months WHERE id = ?");
                $stmtM->execute([$month_id]);
                $month = $stmtM->fetch();
                
                if ($month && !empty($month['drive_folder_id'])) {
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
            } catch (Throwable $e) {
                // Si Drive falla, no importa — el audio local ya está guardado
                error_log("Drive audio backup failed: " . $e->getMessage());
            }
        }
    }

    $comment_phase = $_POST['comment_phase'] ?? 'Parrilla Final';

    // Insertar el comentario
    $stmt = $db->prepare("INSERT INTO post_comments (post_id, comment_text, image_link, status, phase, audio_link, hotspot_x, hotspot_y, suggested_copy) VALUES (?, ?, ?, 'Pendiente', ?, ?, ?, ?, ?)");
    $stmt->execute([$post_id, $comment_text, $image_link, $comment_phase, $audio_link, $hotspot_x, $hotspot_y, $suggested_copy]);

    // Actualizar el estado del post a 'En Revisión (Con Cambios)'
    $stmtUpdate = $db->prepare("UPDATE month_posts SET status = 'En Revisión (Con Cambios)' WHERE id = ?");
    $stmtUpdate->execute([$post_id]);

    // Notify team members
    try {
        $stmtProj = $db->prepare("SELECT p.team_members, mp.concept FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id JOIN projects p ON pm.project_id = p.id WHERE mp.id = ?");
        $stmtProj->execute([$post_id]);
        $proj = $stmtProj->fetch();
        if ($proj && !empty($proj['team_members'])) {
            $assignedIds = json_decode($proj['team_members'], true) ?: [];
            if (!empty($assignedIds)) {
                PushHelper::sendPushNotification($db, $assignedIds, "Comentario de Cliente", "El cliente dejó un comentario en '{$proj['concept']}': {$comment_text}", "index.php?module=calendar", "calendar", ['module' => 'calendar']);
            }
        }
    } catch(Throwable $e) {}

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
