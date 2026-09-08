<?php
// modules/forms/ajax_upload_temp.php — Handles background file uploads
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false, 'error'=>'Método no permitido']);
    exit();
}

$tmpDir = __DIR__ . '/../../uploads/tmp_forms/';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}

// Lista blanca estricta de extensiones permitidas para formularios públicos
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'mp4', 'mov'];
$dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'inc', 'pl', 'py', 'cgi', 'sh', 'bash', 'exe', 'bat', 'cmd', 'js', 'html', 'htm', 'svg'];
$maxFileSize = 30 * 1024 * 1024; // 30 MB

$uploadedFiles = [];
$errors = [];

if (!empty($_FILES)) {
    foreach ($_FILES as $fieldKey => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            // Multi-file
            for ($i = 0; $i < count($fileInfo['name']); $i++) {
                if ($fileInfo['error'][$i] === UPLOAD_ERR_OK && $fileInfo['size'][$i] > 0) {
                    if ($fileInfo['size'][$i] > $maxFileSize) {
                        $errors[] = "El archivo " . htmlspecialchars($fileInfo['name'][$i]) . " supera el tamaño máximo permitido (30MB).";
                        continue;
                    }

                    $ext = strtolower(pathinfo($fileInfo['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions) || in_array($ext, $dangerousExtensions)) {
                        $errors[] = "Tipo de archivo no permitido: " . htmlspecialchars($fileInfo['name'][$i]);
                        continue;
                    }

                    $filename = uniqid('tmp_') . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $tmpDir . $filename;
                    
                    if (move_uploaded_file($fileInfo['tmp_name'][$i], $destPath)) {
                        $uploadedFiles[] = [
                            'field' => $fieldKey,
                            'original_name' => $fileInfo['name'][$i],
                            'tmp_path' => 'uploads/tmp_forms/' . $filename,
                            'size' => $fileInfo['size'][$i],
                            'type' => $fileInfo['type'][$i]
                        ];
                    } else {
                        $errors[] = "Error al mover el archivo " . htmlspecialchars($fileInfo['name'][$i]);
                    }
                }
            }
        } else {
            // Single-file
            if ($fileInfo['error'] === UPLOAD_ERR_OK && $fileInfo['size'] > 0) {
                if ($fileInfo['size'] > $maxFileSize) {
                    $errors[] = "El archivo " . htmlspecialchars($fileInfo['name']) . " supera el tamaño máximo permitido (30MB).";
                    continue;
                }

                $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions) || in_array($ext, $dangerousExtensions)) {
                    $errors[] = "Tipo de archivo no permitido: " . htmlspecialchars($fileInfo['name']);
                    continue;
                }

                $filename = uniqid('tmp_') . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = $tmpDir . $filename;
                
                if (move_uploaded_file($fileInfo['tmp_name'], $destPath)) {
                    $uploadedFiles[] = [
                        'field' => $fieldKey,
                        'original_name' => $fileInfo['name'],
                        'tmp_path' => 'uploads/tmp_forms/' . $filename,
                        'size' => $fileInfo['size'],
                        'type' => $fileInfo['type']
                    ];
                } else {
                    $errors[] = "Error al mover el archivo " . htmlspecialchars($fileInfo['name']);
                }
            }
        }
    }
}

if (empty($uploadedFiles) && !empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
} else {
    echo json_encode(['success' => true, 'files' => $uploadedFiles, 'errors' => $errors]);
}
