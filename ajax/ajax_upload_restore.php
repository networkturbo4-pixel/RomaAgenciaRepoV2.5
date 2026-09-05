<?php
// ajax/ajax_upload_restore.php
// Subida y restauración segura de copias de seguridad desde archivo ZIP o SQL

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada. Por favor inicie sesión nuevamente.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BackupHelper.php';

$db = (new Database())->getConnection();

// Verificar rol de Administrador
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado: Se requieren privilegios de Administrador.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['backup_file'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo para restaurar.']);
    exit;
}

$file = $_FILES['backup_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por la configuración del servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite permitido por el formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente. Intente nuevamente.',
        UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida del archivo.'
    ];
    $msg = $errorMessages[$file['error']] ?? 'Error al subir el archivo (Código: ' . $file['error'] . ').';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['zip', 'sql'])) {
    echo json_encode(['success' => false, 'error' => 'Formato no soportado. Solo se admiten archivos .ZIP o .SQL.']);
    exit;
}

$scratchDir = __DIR__ . '/../scratch';
if (!is_dir($scratchDir)) {
    @mkdir($scratchDir, 0777, true);
}

$tempTarget = $scratchDir . '/upload_restore_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $tempTarget)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo subido en el servidor.']);
    exit;
}

try {
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $backupHelper = new BackupHelper($db);
    $password = !empty($_POST['backup_password']) ? trim($_POST['backup_password']) : null;

    if ($ext === 'zip') {
        $result = $backupHelper->restoreFromZipFile($tempTarget, $password);
    } else {
        $result = $backupHelper->restoreFromSqlFile($tempTarget);
    }

    @unlink($tempTarget);

    echo json_encode([
        'success' => true,
        'message' => '¡Base de datos restaurada exitosamente! Se procesaron las tablas correctamente.',
        'tables_restored' => $result['tables_restored'] ?? 0,
        'original_name' => htmlspecialchars($file['name'])
    ]);

} catch (Exception $e) {
    @unlink($tempTarget);
    echo json_encode([
        'success' => false,
        'error' => 'Fallo en la restauración: ' . $e->getMessage()
    ]);
}
