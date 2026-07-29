<?php
// cron/drive_backup.php
// Este script genera un backup de la base de datos y archivos, y lo sube a Google Drive.
// Conserva únicamente los 5 backups más recientes.

// Set execution time to unlimited for large backups
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/GoogleDriveHelper.php';

$log = [];
function addLog($msg) {
    global $log;
    $time = date('Y-m-d H:i:s');
    $log[] = "[$time] $msg";
    if (php_sapi_name() === 'cli') {
        echo "[$time] $msg\n";
    }
}

$backupType = $_GET['type'] ?? $_POST['type'] ?? 'full';
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--type=') === 0) {
            $backupType = substr($arg, 7);
        }
    }
}

addLog("Iniciando proceso de backup (" . ($backupType === 'db' ? 'Solo Base de Datos' : 'Completo') . ")...");

$db = (new Database())->getConnection();
if (!$db) {
    addLog("Error: No se pudo conectar a la base de datos.");
    exit(1);
}

// Obtener contraseña maestra si existe
$stmt_pass = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'backup_password'");
$backupPassword = $stmt_pass->fetchColumn();

// 1. Generar SQL Dump
addLog("Generando dump de la base de datos...");
$tables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sqlDump = "-- Generado automáticamente el " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    $stmt = $db->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
    $sqlDump .= $row[1] . ";\n\n";

    $stmt = $db->query("SELECT * FROM `$table`");
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        $sqlDump .= "INSERT INTO `$table` VALUES \n";
        $rowsData = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = "NULL";
                } else {
                    $vals[] = $db->quote($val);
                }
            }
            $rowsData[] = "(" . implode(", ", $vals) . ")";
        }
        $sqlDump .= implode(",\n", $rowsData) . ";\n\n";
    }
}

$tempDbFile = __DIR__ . '/../scratch/backup_db_' . date('Ymd_His') . '.sql';
if (!is_dir(__DIR__ . '/../scratch')) {
    mkdir(__DIR__ . '/../scratch', 0777, true);
}
file_put_contents($tempDbFile, $sqlDump);
addLog("Dump de base de datos generado: " . basename($tempDbFile));

// 2. Crear archivo ZIP con la base de datos y opcionalmente los archivos importantes
$prefix = ($backupType === 'db') ? 'backup_db_' : 'backup_full_';
$backupFileName = $prefix . date('Ymd_His') . '.zip';
$tempZipFile = __DIR__ . '/../scratch/' . $backupFileName;

addLog("Creando archivo ZIP...");
$zip = new ZipArchive();
if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    addLog("Error: No se pudo crear el archivo ZIP.");
    unlink($tempDbFile);
    exit(1);
}

// Añadir DB
$zip->addFile($tempDbFile, 'database_dump.sql');
if (!empty($backupPassword)) {
    $zip->setPassword($backupPassword);
    $zip->setEncryptionName('database_dump.sql', ZipArchive::EM_AES_256);
}

// Función recursiva para añadir archivos
function addFolderToZip($dir, $zipArchive, $zipPath = '', $password = null) {
    $excludeDirs = ['.git', 'scratch', 'node_modules', 'vendor']; // Ignoramos estos para ahorrar espacio
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (in_array($file, $excludeDirs) && $zipPath === '') continue; // Excluir carpetas raíz

        $filePath = $dir . '/' . $file;
        $localPath = $zipPath . $file;

        if (is_dir($filePath)) {
            $zipArchive->addEmptyDir($localPath);
            addFolderToZip($filePath, $zipArchive, $localPath . '/', $password);
        } else {
            $zipArchive->addFile($filePath, $localPath);
            if (!empty($password)) {
                $zipArchive->setEncryptionName($localPath, ZipArchive::EM_AES_256);
            }
        }
    }
}

if ($backupType !== 'db') {
    addLog("Empaquetando archivos del sistema...");
    addFolderToZip(__DIR__ . '/..', $zip, '', $backupPassword);
} else {
    addLog("Omitiendo empaquetado de archivos del sistema (modo Solo DB)...");
}

$zip->close();
addLog("Archivo ZIP creado exitosamente. Tamaño: " . round(filesize($tempZipFile) / 1024 / 1024, 2) . " MB");

// 3. Subir a Google Drive
addLog("Conectando con Google Drive...");
$drive = new GoogleDriveHelper();

if (!$drive->isConfigured()) {
    addLog("Error: Google Drive no está configurado en el sistema.");
    unlink($tempDbFile);
    unlink($tempZipFile);
    exit(1);
}

// Buscar o crear la carpeta "copia de seguridad"
$folderName = 'copia de seguridad';
$targetFolderId = null;

$folders = $drive->listFolders('root');
if ($folders) {
    foreach ($folders as $f) {
        if (strtolower($f->getName()) === strtolower($folderName)) {
            $targetFolderId = $f->getId();
            break;
        }
    }
}

if (!$targetFolderId) {
    addLog("La carpeta '$folderName' no existe. Creándola...");
    $targetFolderId = $drive->createFolder($folderName, 'root');
    if (!$targetFolderId) {
        addLog("Error: No se pudo crear la carpeta en Drive.");
        unlink($tempDbFile);
        unlink($tempZipFile);
        exit(1);
    }
}

addLog("Subiendo archivo a la carpeta ID: $targetFolderId...");
$uploadResult = $drive->uploadFile($tempZipFile, $backupFileName, $targetFolderId);

if ($uploadResult && isset($uploadResult['id'])) {
    addLog("Archivo subido con éxito: " . $uploadResult['webViewLink']);
} else {
    addLog("Error al subir el archivo a Google Drive.");
}

// 4. Limpieza en Google Drive (Mantener solo los últimos 5 backups)
addLog("Limpiando backups antiguos en Drive...");
$driveFiles = $drive->listFiles($targetFolderId);

if ($driveFiles && count($driveFiles) > 5) {
    // Sort by createdTime DESC
    usort($driveFiles, function($a, $b) {
        return strtotime($b['createdTime']) - strtotime($a['createdTime']);
    });

    // Remove older files
    for ($i = 5; $i < count($driveFiles); $i++) {
        $fileToDelete = $driveFiles[$i];
        addLog("Eliminando backup antiguo de Drive: " . $fileToDelete['name']);
        $drive->deleteFile($fileToDelete['id']);
    }
} else {
    addLog("Hay " . (is_array($driveFiles) ? count($driveFiles) : 0) . " backups actualmente. No es necesario limpiar.");
}

// 5. Limpieza local
addLog("Limpiando archivos temporales locales...");
if (file_exists($tempDbFile)) unlink($tempDbFile);
if (file_exists($tempZipFile)) unlink($tempZipFile);

addLog("Proceso de backup completado con éxito.");
echo json_encode(['success' => true, 'log' => $log, 'link' => $uploadResult['webViewLink'] ?? '']);
?>
