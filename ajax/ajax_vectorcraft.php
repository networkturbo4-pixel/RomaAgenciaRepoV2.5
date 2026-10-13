<?php
// ajax/ajax_vectorcraft.php
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

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$current_user_id = (int)$_SESSION['user_id'];

// Helper para generar URL pública
function getPublicShareUrl($token) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Obtenemos la raíz relativa del CRM
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // e.g. /CESARMENDOZA/ajax
    $rootDir = rtrim(dirname($scriptDir), '/\\');  // e.g. /CESARMENDOZA
    if ($rootDir === '' || $rootDir === '.') $rootDir = '';
    
    return "{$protocol}{$host}{$rootDir}/public_vectorcraft.php?token={$token}";
}

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $title = 'Nueva Ilustración ' . date('d/m/Y H:i');
        }
        $description = trim($_POST['description'] ?? '');
        $format_type = trim($_POST['format_type'] ?? 'svg');
        $drive_folder_id = trim($_POST['drive_folder_id'] ?? '');
        $drive_folder_name = trim($_POST['drive_folder_name'] ?? '');
        $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        // Validar obligatoriedad de la carpeta de Google Drive
        if (empty($drive_folder_id)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Es obligatorio seleccionar una carpeta de Google Drive para alojar esta ilustración.'
            ]);
            exit();
        }

        try {
            $share_token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("
                INSERT INTO vectorcraft_projects (
                    title, description, format_type, drive_folder_id, drive_folder_name, 
                    share_token, is_public, client_id, project_id, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $title, $description, $format_type, $drive_folder_id, $drive_folder_name,
                $share_token, $client_id, $project_id, $current_user_id
            ]);
            $new_id = (int)$db->lastInsertId();

            echo json_encode([
                'success' => true,
                'id' => $new_id,
                'title' => $title,
                'share_url' => getPublicShareUrl($share_token),
                'redirect_url' => "index.php?module=workspace&action=vectorcraft&edit={$new_id}"
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al crear la ilustración: ' . $e->getMessage()]);
        }
        break;

    case 'save_to_drive':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de proyecto no válido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT * FROM vectorcraft_projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
                exit();
            }

            $drive_folder_id = $project['drive_folder_id'];
            if (empty($drive_folder_id)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Este proyecto no tiene una carpeta de Google Drive asignada. Asígnala antes de guardar.'
                ]);
                exit();
            }

            $upload_dir = __DIR__ . '/../uploads/vectorcraft/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['title']);
            $clean_title = substr($clean_title, 0, 40);

            $ext = 'svg';
            $file_temp_path = null;
            $orig_name = '';

            // Caso A: Se envió un archivo por multipart/form-data
            if (!empty($_FILES['file']['name']) && file_exists($_FILES['file']['tmp_name'])) {
                $orig_name = basename($_FILES['file']['name']);
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION)) ?: 'svg';
                $dest_name = "vector_{$id}_{$clean_title}_" . time() . ".{$ext}";
                $file_temp_path = $upload_dir . $dest_name;
                
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_temp_path)) {
                    echo json_encode(['success' => false, 'message' => 'Error al almacenar archivo localmente']);
                    exit();
                }
            } 
            // Caso B: Se envió contenido raw / base64
            elseif (!empty($_POST['content'])) {
                $ext = strtolower(trim($_POST['format'] ?? 'svg'));
                if (!in_array($ext, ['svg', 'vectorcraft', 'png', 'jpg', 'jpeg'])) {
                    $ext = 'svg';
                }
                $orig_name = $project['title'] . '.' . $ext;
                $dest_name = "vector_{$id}_{$clean_title}_" . time() . ".{$ext}";
                $file_temp_path = $upload_dir . $dest_name;

                $content = $_POST['content'];
                // Si es base64 data URL
                if (preg_match('/^data:([a-zA-Z0-9\/\+]+);base64,/', $content, $matches)) {
                    $content = substr($content, strpos($content, ',') + 1);
                    $decoded = base64_decode($content);
                    file_put_contents($file_temp_path, $decoded);
                } else {
                    file_put_contents($file_temp_path, $content);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No se recibió ningún contenido o archivo para guardar']);
                exit();
            }

            $rel_path = 'uploads/vectorcraft/' . basename($file_temp_path);

            // Subir a Google Drive
            $driveHelper = new GoogleDriveHelper();
            $drive_file_id = null;
            $drive_web_link = null;

            if ($driveHelper->isConfigured()) {
                $driveUploadName = $project['title'] . '.' . $ext;
                $uploadRes = $driveHelper->uploadFile($file_temp_path, $driveUploadName, $drive_folder_id);

                if ($uploadRes && isset($uploadRes['id'])) {
                    $drive_file_id = $uploadRes['id'];
                    $drive_web_link = $uploadRes['webViewLink'] ?? "https://drive.google.com/file/d/{$drive_file_id}/view";
                    
                    // Hacerlo público como lector para que cualquiera con el enlace lo vea
                    $driveHelper->makePublicViewer($drive_file_id);
                }
            }

            // Actualizar datos en base de datos
            $stmtUpdate = $db->prepare("
                UPDATE vectorcraft_projects SET 
                    file_path = ?,
                    format_type = ?,
                    drive_file_id = COALESCE(?, drive_file_id),
                    drive_web_link = COALESCE(?, drive_web_link),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$rel_path, $ext, $drive_file_id, $drive_web_link, $id]);

            // Token para enlace público
            $share_token = $project['share_token'];
            if (empty($share_token)) {
                $share_token = bin2hex(random_bytes(16));
                $db->prepare("UPDATE vectorcraft_projects SET share_token = ? WHERE id = ?")->execute([$share_token, $id]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Ilustración guardada y sincronizada con Google Drive exitosamente.',
                'file_path' => $rel_path,
                'file_name' => basename($file_temp_path),
                'drive_file_id' => $drive_file_id ?: $project['drive_file_id'],
                'drive_web_link' => $drive_web_link ?: $project['drive_web_link'],
                'drive_folder_name' => $project['drive_folder_name'],
                'share_url' => getPublicShareUrl($share_token),
                'updated_at' => date('d/m/Y H:i')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar en Drive: ' . $e->getMessage()]);
        }
        break;

    case 'get_project_file':
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no válido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT * FROM vectorcraft_projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
                exit();
            }

            $local_path = $project['file_path'];
            $full_local = $local_path ? realpath(__DIR__ . '/../' . $local_path) : null;

            // Si el archivo local no existe pero tenemos ID en Drive, intentamos descargarlo para cachear
            if ((!$full_local || !file_exists($full_local)) && !empty($project['drive_file_id'])) {
                $driveHelper = new GoogleDriveHelper();
                if ($driveHelper->isConfigured()) {
                    $ext = $project['format_type'] ?: 'svg';
                    $dest_name = "vector_{$id}_cached_" . time() . ".{$ext}";
                    $cache_dest = __DIR__ . '/../uploads/vectorcraft/' . $dest_name;
                    if ($driveHelper->downloadFile($project['drive_file_id'], $cache_dest)) {
                        $local_path = 'uploads/vectorcraft/' . $dest_name;
                        $full_local = $cache_dest;
                        $db->prepare("UPDATE vectorcraft_projects SET file_path = ? WHERE id = ?")->execute([$local_path, $id]);
                    }
                }
            }

            $has_file = $full_local && file_exists($full_local);
            $share_token = $project['share_token'];
            if (empty($share_token)) {
                $share_token = bin2hex(random_bytes(16));
                $db->prepare("UPDATE vectorcraft_projects SET share_token = ? WHERE id = ?")->execute([$share_token, $id]);
            }

            echo json_encode([
                'success' => true,
                'id' => $project['id'],
                'title' => $project['title'],
                'has_file' => $has_file,
                'file_url' => $has_file ? $local_path : null,
                'file_name' => $has_file ? basename($local_path) : null,
                'format_type' => $project['format_type'] ?: 'svg',
                'drive_folder_id' => $project['drive_folder_id'],
                'drive_folder_name' => $project['drive_folder_name'],
                'drive_file_id' => $project['drive_file_id'],
                'drive_web_link' => $project['drive_web_link'],
                'share_url' => getPublicShareUrl($share_token),
                'updated_at' => date('d/m/Y H:i', strtotime($project['updated_at']))
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $format_type = trim($_POST['format_type'] ?? 'svg');
        $drive_folder_id = trim($_POST['drive_folder_id'] ?? '');
        $drive_folder_name = trim($_POST['drive_folder_name'] ?? '');

        if (!$id || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit();
        }

        try {
            $stmt = $db->prepare("
                UPDATE vectorcraft_projects SET 
                    title = ?, 
                    description = ?, 
                    format_type = ?, 
                    drive_folder_id = CASE WHEN ? != '' THEN ? ELSE drive_folder_id END,
                    drive_folder_name = CASE WHEN ? != '' THEN ? ELSE drive_folder_name END,
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $description, $format_type, 
                $drive_folder_id, $drive_folder_id,
                $drive_folder_name, $drive_folder_name,
                $id
            ]);
            echo json_encode(['success' => true, 'message' => 'Ilustración actualizada correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        break;

    case 'duplicate':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT * FROM vectorcraft_projects WHERE id = ?");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                echo json_encode(['success' => false, 'message' => 'Ilustración no encontrada']);
                exit();
            }

            $new_title = $original['title'] . ' (Copia)';
            $new_token = bin2hex(random_bytes(16));

            $stmtInsert = $db->prepare("
                INSERT INTO vectorcraft_projects (
                    title, description, file_path, thumbnail, format_type, 
                    drive_folder_id, drive_folder_name, drive_file_id, drive_web_link,
                    share_token, is_public, client_id, project_id, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), NOW())
            ");
            $stmtInsert->execute([
                $new_title,
                $original['description'],
                $original['file_path'],
                $original['thumbnail'],
                $original['format_type'],
                $original['drive_folder_id'],
                $original['drive_folder_name'],
                $original['drive_file_id'],
                $original['drive_web_link'],
                $new_token,
                $original['client_id'],
                $original['project_id'],
                $current_user_id
            ]);
            $new_id = (int)$db->lastInsertId();

            echo json_encode([
                'success' => true, 
                'id' => $new_id, 
                'share_url' => getPublicShareUrl($new_token),
                'message' => 'Ilustración duplicada exitosamente'
            ]);
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
            $stmt = $db->prepare("DELETE FROM vectorcraft_projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Ilustración eliminada']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;

    case 'get_share_link':
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit();
        }

        try {
            $stmt = $db->prepare("SELECT share_token FROM vectorcraft_projects WHERE id = ?");
            $stmt->execute([$id]);
            $token = $stmt->fetchColumn();

            if (!$token) {
                $token = bin2hex(random_bytes(16));
                $db->prepare("UPDATE vectorcraft_projects SET share_token = ? WHERE id = ?")->execute([$token, $id]);
            }

            echo json_encode([
                'success' => true,
                'share_url' => getPublicShareUrl($token)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
