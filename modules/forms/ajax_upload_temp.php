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

$uploadedFiles = [];
$errors = [];

if (!empty($_FILES)) {
    foreach ($_FILES as $fieldKey => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            // Multi-file
            for ($i = 0; $i < count($fileInfo['name']); $i++) {
                if ($fileInfo['error'][$i] === UPLOAD_ERR_OK && $fileInfo['size'][$i] > 0) {
                    $ext = pathinfo($fileInfo['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid('tmp_') . '_' . time() . '.' . $ext;
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
                        $errors[] = "Error al mover el archivo " . $fileInfo['name'][$i];
                    }
                }
            }
        } else {
            // Single-file
            if ($fileInfo['error'] === UPLOAD_ERR_OK && $fileInfo['size'] > 0) {
                $ext = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
                $filename = uniqid('tmp_') . '_' . time() . '.' . $ext;
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
                    $errors[] = "Error al mover el archivo " . $fileInfo['name'];
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
