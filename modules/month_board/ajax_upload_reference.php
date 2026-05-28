<?php
// modules/month_board/ajax_upload_reference.php
header('Content-Type: application/json');
session_start();
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '1024M');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = isset($_FILES['image']['error']) ? $_FILES['image']['error'] : 'no_file_sent';
    $postSize = $_SERVER['CONTENT_LENGTH'] ?? 'unknown';
    $phpMaxPost = ini_get('post_max_size');
    $phpMaxUpload = ini_get('upload_max_filesize');
    echo json_encode(['success' => false, 'error' => "No se recibió ninguna imagen o hubo un error en la subida (Código: $errCode, Tamaño: $postSize, max post: $phpMaxPost)"]);
    exit();
}

$uploadDir = '../../uploads/references/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file = $_FILES['image'];
$tmpName = $file['tmp_name'];
$mimeType = mime_content_type($tmpName);
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4'];

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Formato no soportado. Solo JPG, PNG, WEBP, MP4.']);
    exit();
}

$extension = 'jpg';
if ($mimeType === 'image/png') $extension = 'png';
if ($mimeType === 'image/webp') $extension = 'webp';
if ($mimeType === 'video/mp4') $extension = 'mp4';

$fileName = uniqid('ref_') . '.' . $extension;
$destination = $uploadDir . $fileName;

$saved = false;

if ($mimeType === 'video/mp4') {
    // Si es MP4, solo lo movemos sin usar GD
    $saved = move_uploaded_file($tmpName, $destination);
} else {
    // Lógica de compresión y redimensionado con GD
    $maxWidth = 1200;

    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($tmpName);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($tmpName);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($tmpName);
            break;
        default:
            $sourceImage = false;
    }

    if ($sourceImage) {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = floor($height * ($maxWidth / $width));
            $virtualImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preservar transparencia para PNG/WEBP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($virtualImage, false);
                imagesavealpha($virtualImage, true);
                $transparent = imagecolorallocatealpha($virtualImage, 255, 255, 255, 127);
                imagefilledrectangle($virtualImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($virtualImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $finalImage = $virtualImage;
        } else {
            $finalImage = $sourceImage;
        }

        // Guardar
        if ($mimeType === 'image/png') {
            $saved = imagepng($finalImage, $destination, 8); // compresión 0-9
        } else if ($mimeType === 'image/webp') {
            $saved = imagewebp($finalImage, $destination, 80); // calidad 80
        } else {
            $saved = imagejpeg($finalImage, $destination, 80); // calidad 80
        }

        imagedestroy($sourceImage);
        if (isset($virtualImage)) imagedestroy($virtualImage);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al procesar la imagen con GD']);
        exit();
    }
}

if ($saved) {
    $driveUrl = null;
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'Referencia Visual';
    $old_url = isset($_POST['old_url']) ? trim($_POST['old_url']) : '';
    
    if ($month_id > 0) {
        require_once '../../config/database.php';
        require_once '../../includes/GoogleDriveHelper.php';
        
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT drive_folders_json FROM project_months WHERE id = ?");
        $stmt->execute([$month_id]);
        $jsonStr = $stmt->fetchColumn();
        
        $drive = new GoogleDriveHelper();
        
        if ($drive->isConfigured()) {
            // Buscar la carpeta destino
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

            // Eliminar archivo anterior si existe y es de Drive
            if ($old_url) {
                if (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?export=view&id=|thumbnail\?id=)([\w-]+)/', $old_url, $matches)) {
                    $oldFileId = $matches[1];
                    $drive->deleteFile($oldFileId);
                } else if (strpos($old_url, 'uploads/references/') !== false && $targetFolderId) {
                    $oldFileName = basename($old_url);
                    $drive->deleteFileByName($oldFileName, $targetFolderId);
                    // Opcional: Eliminar el archivo local también para no llenar el servidor
                    $oldLocalPath = '../../' . $old_url;
                    if (file_exists($oldLocalPath)) unlink($oldLocalPath);
                }
            }
            
            if ($targetFolderId) {
                $driveFileInfo = $drive->uploadFile($destination, $fileName, $targetFolderId);
                if ($driveFileInfo && isset($driveFileInfo['id'])) {
                    if ($mimeType === 'video/mp4') {
                        $driveUrl = $driveFileInfo['webViewLink'];
                    } else {
                        $driveUrl = 'https://drive.google.com/uc?export=view&id=' . $driveFileInfo['id'];
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'url' => 'uploads/references/' . $fileName,
        'local_url' => 'uploads/references/' . $fileName,
        'drive_url' => $driveUrl
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar la imagen comprimida']);
}
