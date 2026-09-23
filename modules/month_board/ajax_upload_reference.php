<?php
// modules/month_board/ajax_upload_reference.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(0);
ignore_user_abort(true);
set_time_limit(300);
ini_set('memory_limit', '1024M');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado. Por favor inicia sesión nuevamente.']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = isset($_FILES['image']['error']) ? $_FILES['image']['error'] : 'no_file_sent';
    $errMap = [
        UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el formulario.',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente. Comprueba tu conexión a internet.',
        UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco del servidor.',
        UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo.'
    ];
    $msg = $errMap[$errCode] ?? "Error en la subida (Código: $errCode)";
    $postSize = $_SERVER['CONTENT_LENGTH'] ?? 'desconocido';
    $phpMaxPost = ini_get('post_max_size');
    $phpMaxUpload = ini_get('upload_max_filesize');
    echo json_encode([
        'success' => false,
        'error' => "$msg (Tamaño recibido: $postSize, max post: $phpMaxPost, max upload: $phpMaxUpload)"
    ]);
    exit();
}

$uploadDir = '../../uploads/references/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file = $_FILES['image'];
$tmpName = $file['tmp_name'];
$mimeType = mime_content_type($tmpName);

// Fallback al mime del cliente si el detector devuelve binario genérico o está vacío
if (empty($mimeType) || $mimeType === 'application/octet-stream') {
    $mimeType = $file['type'] ?? 'application/octet-stream';
}

$allowedTypes = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'image/heic', 'image/heif',
    'video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v', 'video/mpeg', 'video/3gpp'
];

$origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$validExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'heic', 'heif', 'mp4', 'mov', 'webm', 'm4v'];

if (!in_array($mimeType, $allowedTypes) && !in_array($origExt, $validExts)) {
    echo json_encode([
        'success' => false,
        'error' => "Formato no soportado ($mimeType, ext: $origExt). Formatos permitidos: JPG, PNG, WEBP, GIF, MP4, MOV, WEBM."
    ]);
    exit();
}

// Determinar extensión adecuada
$extension = 'jpg';
if ($mimeType === 'image/png') $extension = 'png';
elseif ($mimeType === 'image/webp') $extension = 'webp';
elseif ($mimeType === 'image/gif') $extension = 'gif';
elseif ($mimeType === 'image/svg+xml') $extension = 'svg';
elseif ($mimeType === 'video/mp4') $extension = 'mp4';
elseif ($mimeType === 'video/quicktime') $extension = 'mov';
elseif ($mimeType === 'video/webm') $extension = 'webm';
elseif ($mimeType === 'video/x-m4v') $extension = 'm4v';
else {
    if ($origExt === 'jpeg') $extension = 'jpg';
    elseif (in_array($origExt, ['jpg', 'png', 'webp', 'gif', 'svg', 'mp4', 'mov', 'webm', 'm4v', 'heic'])) {
        $extension = $origExt;
    }
}

$fileName = uniqid('ref_') . '.' . $extension;
$destination = $uploadDir . $fileName;

$saved = move_uploaded_file($tmpName, $destination);

if ($saved) {
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'Referencia Visual';
    $old_url = isset($_POST['old_url']) ? trim($_POST['old_url']) : '';
    $post_name = isset($_POST['post_name']) ? trim($_POST['post_name']) : '';
    $is_carousel = isset($_POST['is_carousel']) ? filter_var($_POST['is_carousel'], FILTER_VALIDATE_BOOLEAN) : false;
    $parent_folder_id = isset($_POST['parent_folder_id']) ? trim($_POST['parent_folder_id']) : '';

    $responseJson = json_encode([
        'success' => true,
        'url' => 'uploads/references/' . $fileName,
        'local_url' => 'uploads/references/' . $fileName,
        'drive_url' => null,
        'folder_id' => $parent_folder_id ?: null
    ]);

    // Enviar respuesta inmediata al cliente para no bloquear el modal ni agotar el timeout HTTP
    echo $responseJson;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    // Sincronización en segundo plano con Google Drive (sin bloquear la interfaz del usuario)
    if ($month_id > 0) {
        try {
            require_once '../../config/database.php';
            require_once '../../includes/GoogleDriveHelper.php';
            
            $db = (new Database())->getConnection();
            if ($db) {
                $stmt = $db->prepare("SELECT drive_folders_json FROM project_months WHERE id = ?");
                $stmt->execute([$month_id]);
                $jsonStr = $stmt->fetchColumn();
                
                $drive = new GoogleDriveHelper();
                
                if ($drive->isConfigured()) {
                    $targetFolderId = null;
                    if ($jsonStr) {
                        $foldersData = json_decode($jsonStr, true);
                        if (isset($foldersData['subfolders'])) {
                            $targetName = ($post_type === 'Post Terminado') ? 'POST TERMINADOS' : 'REFERENCIAS';
                            foreach ($foldersData['subfolders'] as $sf) {
                                if ($sf['name'] === $targetName) {
                                    $targetFolderId = $sf['id'];
                                    break;
                                }
                            }
                        }
                    }

                    if ($targetFolderId) {
                        if ($parent_folder_id) {
                            $targetFolderId = $parent_folder_id;
                        } else if ($is_carousel && $post_name) {
                            $carouselFolderId = null;
                            $folders = $drive->listFolders($targetFolderId);
                            if ($folders && isset($folders['files'])) {
                                foreach($folders['files'] as $f) {
                                    if (strtolower($f['name']) === strtolower($post_name)) {
                                        $carouselFolderId = $f['id']; break;
                                    }
                                }
                            }
                            if (!$carouselFolderId) {
                                $res = $drive->createFolder($post_name, $targetFolderId);
                                if ($res && isset($res['id'])) {
                                    $carouselFolderId = $res['id'];
                                    $drive->makePublicViewer($carouselFolderId);
                                }
                            }
                            if ($carouselFolderId) {
                                $targetFolderId = $carouselFolderId;
                            }
                        }
                    }

                    // Eliminar archivo anterior si existe y es de Drive
                    if ($old_url) {
                        if (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?export=view&id=|thumbnail\?id=)([\w-]+)/', $old_url, $matches)) {
                            $oldFileId = $matches[1];
                            $drive->deleteFile($oldFileId);
                        } else if (strpos($old_url, 'uploads/references/') !== false && $targetFolderId) {
                            $oldFileName = basename($old_url);
                            $drive->deleteFileByName($oldFileName, $targetFolderId);
                            $oldLocalPath = '../../' . $old_url;
                            if (file_exists($oldLocalPath)) @unlink($oldLocalPath);
                        }
                    }
                    
                    if ($targetFolderId && file_exists($destination)) {
                        $drive->uploadFile($destination, $fileName, $targetFolderId);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Error sincronizando archivo con Google Drive en segundo plano: " . $e->getMessage());
        }
    }
    exit();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el archivo en el servidor. Verifica los permisos de la carpeta uploads/references/.'
    ]);
    exit();
}
