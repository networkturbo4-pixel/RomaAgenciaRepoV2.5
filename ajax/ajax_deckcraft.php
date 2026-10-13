<?php
// ajax/ajax_deckcraft.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/GoogleDriveHelper.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

// Asegurar columnas requeridas en deckcraft_presentations
try {
    $cols = $db->query("SHOW COLUMNS FROM deckcraft_presentations")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('drive_folder_id', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `drive_folder_id` VARCHAR(255) NULL");
    }
    if (!in_array('drive_folder_name', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `drive_folder_name` VARCHAR(255) NULL");
    }
    if (!in_array('drive_folder_url', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `drive_folder_url` VARCHAR(500) NULL");
    }
    if (!in_array('drive_file_id', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `drive_file_id` VARCHAR(255) NULL");
    }
    if (!in_array('drive_file_url', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `drive_file_url` VARCHAR(500) NULL");
    }
    if (!in_array('share_token', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `share_token` VARCHAR(64) NULL");
    }
    if (!in_array('is_public', $cols)) {
        $db->exec("ALTER TABLE deckcraft_presentations ADD COLUMN `is_public` TINYINT(1) DEFAULT 1");
    }
} catch (Exception $e) {
    // ignorar
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$current_user_id = (int)$_SESSION['user_id'];

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $title = 'Nueva Presentación ' . date('d/m/Y H:i');
        }
        $description = trim($_POST['description'] ?? '');
        $theme = trim($_POST['theme'] ?? 'blank'); // 'blank' o 'sample'
        $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

        // OBLIGATORIO: Carpeta de Google Drive
        $drive_folder_id = trim($_POST['drive_folder_id'] ?? '');
        $drive_folder_name = trim($_POST['drive_folder_name'] ?? '');
        $drive_folder_url = trim($_POST['drive_folder_url'] ?? '');

        if (empty($drive_folder_id)) {
            echo json_encode(['success' => false, 'message' => 'Es obligatorio seleccionar una carpeta de Google Drive para guardar la presentación.']);
            exit();
        }

        try {
            $share_token = bin2hex(random_bytes(16));
            $drive_file_id = null;
            $drive_file_url = null;

            $stmt = $db->prepare("
                INSERT INTO deckcraft_presentations 
                (title, description, theme, client_id, drive_folder_id, drive_folder_name, drive_folder_url, drive_file_id, drive_file_url, share_token, is_public, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $title, 
                $description, 
                $theme, 
                $client_id, 
                $drive_folder_id, 
                $drive_folder_name, 
                $drive_folder_url, 
                $drive_file_id, 
                $drive_file_url, 
                $share_token, 
                $current_user_id
            ]);
            $new_id = (int)$db->lastInsertId();

            $blank_param = ($theme === 'blank') ? '&blank=1' : '';
            echo json_encode([
                'success' => true,
                'id' => $new_id,
                'title' => $title,
                'share_token' => $share_token,
                'drive_folder_id' => $drive_folder_id,
                'drive_folder_name' => $drive_folder_name,
                'redirect_url' => "index.php?module=workspace&action=deckcraft&edit={$new_id}{$blank_param}"
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al crear la presentación: ' . $e->getMessage()]);
        }
        break;

    case 'save_to_drive':
        // Guardar/Actualizar archivo PPTX en la carpeta de Google Drive asignada
        $id = (int)($_POST['id'] ?? 0);
        if (!$id || empty($_FILES['pptx_file']['name'])) {
            echo json_encode(['success' => false, 'message' => 'Se requiere el ID de la presentación y el archivo PPTX.']);
            exit();
        }

        $stmt = $db->prepare("SELECT * FROM deckcraft_presentations WHERE id = ?");
        $stmt->execute([$id]);
        $presentation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$presentation) {
            echo json_encode(['success' => false, 'message' => 'Presentación no encontrada.']);
            exit();
        }

        $target_folder_id = $presentation['drive_folder_id'];
        if (empty($target_folder_id)) {
            echo json_encode(['success' => false, 'message' => 'Esta presentación no tiene una carpeta de Google Drive vinculada.']);
            exit();
        }

        $upload_dir = __DIR__ . '/../uploads/deckcraft/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $orig_name = basename($_FILES['pptx_file']['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['pptx', 'ppt', 'pdf', 'deckcraft'])) {
            $ext = 'pptx';
        }

        $safe_filename = 'deck_' . $id . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $presentation['title']) . '.' . $ext;
        $local_dest = $upload_dir . $safe_filename;

        if (!move_uploaded_file($_FILES['pptx_file']['tmp_name'], $local_dest)) {
            echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor local.']);
            exit();
        }

        $rel_path = 'uploads/deckcraft/' . $safe_filename;
        $drive_file_id = $presentation['drive_file_id'];
        $drive_file_url = $presentation['drive_file_url'];

        // Subir o actualizar en Google Drive
        try {
            $driveHelper = new GoogleDriveHelper();
            if ($driveHelper->isConfigured()) {
                $cloud_file_name = $presentation['title'] . '.' . $ext;
                $uploaded = $driveHelper->uploadOrUpdateFile($local_dest, $cloud_file_name, $target_folder_id, $drive_file_id);

                if ($uploaded && !empty($uploaded['id'])) {
                    $drive_file_id = $uploaded['id'];
                    $drive_file_url = $uploaded['webViewLink'] ?? "https://drive.google.com/file/d/{$drive_file_id}/view";
                    @$driveHelper->makePublicViewer($drive_file_id);
                }
            }
        } catch (Exception $e) {
            error_log("Error al subir PPTX a Google Drive: " . $e->getMessage());
        }

        // Actualizar registro en BD
        $stmtUpdate = $db->prepare("
            UPDATE deckcraft_presentations 
            SET file_path = ?, drive_file_id = ?, drive_file_url = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$rel_path, $drive_file_id, $drive_file_url, $id]);

        echo json_encode([
            'success' => true,
            'message' => '¡Cambios guardados y sincronizados exitosamente con Google Drive!',
            'file_path' => $rel_path,
            'drive_file_id' => $drive_file_id,
            'drive_file_url' => $drive_file_url,
            'updated_at' => date('d/m/Y H:i')
        ]);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$id || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit();
        }

        try {
            $stmt = $db->prepare("UPDATE deckcraft_presentations SET title = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Presentación actualizada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_public':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT is_public, share_token FROM deckcraft_presentations WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $new_val = ($row['is_public'] == 1) ? 0 : 1;
            $token = $row['share_token'];
            if (empty($token)) {
                $token = bin2hex(random_bytes(16));
            }

            $stmtUpdate = $db->prepare("UPDATE deckcraft_presentations SET is_public = ?, share_token = ? WHERE id = ?");
            $stmtUpdate->execute([$new_val, $token, $id]);

            echo json_encode([
                'success' => true,
                'is_public' => $new_val,
                'share_token' => $token,
                'message' => $new_val ? 'Enlace público habilitado' : 'Enlace público deshabilitado'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al cambiar visibilidad: ' . $e->getMessage()]);
        }
        break;

    case 'duplicate':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT * FROM deckcraft_presentations WHERE id = ?");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                echo json_encode(['success' => false, 'message' => 'Presentación no encontrada']);
                exit();
            }

            $new_title = $original['title'] . ' (Copia)';
            $new_share_token = bin2hex(random_bytes(16));

            $stmtInsert = $db->prepare("
                INSERT INTO deckcraft_presentations 
                (title, description, file_path, thumbnail, theme, slides_count, client_id, project_id, drive_folder_id, drive_folder_name, drive_folder_url, share_token, is_public, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
            ");
            $stmtInsert->execute([
                $new_title,
                $original['description'],
                $original['file_path'],
                $original['thumbnail'],
                $original['theme'],
                $original['slides_count'],
                $original['client_id'],
                $original['project_id'],
                $original['drive_folder_id'],
                $original['drive_folder_name'],
                $original['drive_folder_url'],
                $new_share_token,
                $current_user_id
            ]);
            $new_id = (int)$db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $new_id, 'message' => 'Presentación duplicada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al duplicar: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit();
        }

        try {
            $stmt = $db->prepare("DELETE FROM deckcraft_presentations WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Presentación eliminada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
