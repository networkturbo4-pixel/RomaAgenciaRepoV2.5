<?php
// ajax/ajax_restore_backup.php
session_start();
header('Content-Type: application/json');

// Aumentar límites para grandes bases de datos
set_time_limit(0);
ini_set('memory_limit', '1024M');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No estás autenticado.']);
    exit;
}

require_once '../config/database.php';
require_once '../includes/GoogleDriveHelper.php';

$db = (new Database())->getConnection();

// Verificar admin
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit;
}

$fileId = $_POST['fileId'] ?? '';
if (empty($fileId)) {
    echo json_encode(['success' => false, 'error' => 'ID de archivo no proporcionado.']);
    exit;
}

try {
    // 1. Obtener contraseña
    $stmt_pass = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'backup_password'");
    $backupPassword = $stmt_pass->fetchColumn();

    // 2. Descargar archivo
    $drive = new GoogleDriveHelper();
    if (!$drive->isConfigured()) {
        throw new Exception("Google Drive no está configurado.");
    }

    $scratchDir = __DIR__ . '/../scratch';
    if (!is_dir($scratchDir)) {
        mkdir($scratchDir, 0777, true);
    }

    $tempZipFile = $scratchDir . '/restore_' . time() . '.zip';
    if (!$drive->downloadFile($fileId, $tempZipFile)) {
        throw new Exception("Error al descargar el archivo desde Google Drive.");
    }

    // 3. Extraer SQL
    $zip = new ZipArchive();
    if ($zip->open($tempZipFile) !== true) {
        unlink($tempZipFile);
        throw new Exception("No se pudo abrir el archivo ZIP descargado.");
    }

    if (!empty($backupPassword)) {
        $zip->setPassword($backupPassword);
    }

    $sqlFileName = 'database_dump.sql';
    $extractPath = $scratchDir . '/extracted_' . time();
    if (!is_dir($extractPath)) mkdir($extractPath, 0777, true);

    if (!$zip->extractTo($extractPath, $sqlFileName)) {
        $zip->close();
        unlink($tempZipFile);
        // Clean up
        array_map('unlink', glob("$extractPath/*.*"));
        rmdir($extractPath);
        throw new Exception("Fallo al extraer la base de datos. ¿Es correcta la contraseña maestra?");
    }
    $zip->close();
    unlink($tempZipFile); // Borrar zip de inmediato

    $sqlFilePath = $extractPath . '/' . $sqlFileName;
    if (!file_exists($sqlFilePath)) {
        throw new Exception("El archivo ZIP no contiene una copia de seguridad válida (database_dump.sql).");
    }

    // 4. Ejecutar SQL
    $sqlContent = file_get_contents($sqlFilePath);
    if (empty($sqlContent)) {
        throw new Exception("El archivo SQL está vacío.");
    }

    // Desactivar validación de claves foráneas temporalmente
    $db->exec("SET FOREIGN_KEY_CHECKS=0;");

    // Ejecutar todo el script SQL
    try {
        $db->exec($sqlContent);
    } catch (PDOException $e) {
        $db->exec("SET FOREIGN_KEY_CHECKS=1;");
        throw new Exception("Error al importar la base de datos: " . $e->getMessage());
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1;");

    // 5. Limpiar
    unlink($sqlFilePath);
    rmdir($extractPath);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Intentar limpiar si hubo error
    if (isset($tempZipFile) && file_exists($tempZipFile)) unlink($tempZipFile);
    if (isset($sqlFilePath) && file_exists($sqlFilePath)) unlink($sqlFilePath);
    if (isset($extractPath) && is_dir($extractPath)) rmdir($extractPath);

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
