<?php
// ajax/ajax_download_backup.php
// Descarga directa de copias de seguridad en formato ZIP al navegador

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Acceso denegado: Inicie sesión.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BackupHelper.php';

$db = (new Database())->getConnection();

// Verificar que sea administrador
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    http_response_code(403);
    die('Acceso denegado: Se requieren permisos de Administrador.');
}

$type = isset($_GET['type']) && $_GET['type'] === 'full' ? 'full' : 'db';

try {
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $backupHelper = new BackupHelper($db);
    $result = $backupHelper->createZipBackup($type);

    $zipPath = $result['zipPath'];
    $fileName = $result['fileName'];

    if (!file_exists($zipPath)) {
        throw new Exception("El archivo generado no se encuentra en el servidor.");
    }

    // Limpiar cualquier salida previa del búfer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Enviar encabezados HTTP para descarga forzada en navegador
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipPath));

    readfile($zipPath);

    // Eliminar archivo temporal después de la descarga
    @unlink($zipPath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error al generar la copia de seguridad: ' . htmlspecialchars($e->getMessage()));
}
