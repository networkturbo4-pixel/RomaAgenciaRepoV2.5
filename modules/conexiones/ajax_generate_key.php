<?php
// modules/conexiones/ajax_generate_key.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verificar rol de admin
    $stmtAdmin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtAdmin->execute([$_SESSION['user_id']]);
    if ($stmtAdmin->fetchColumn() != 1) {
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
        exit;
    }

    // Generar nueva clave segura
    $newKey = 'roma_live_' . bin2hex(random_bytes(16));

    // Guardar en la tabla settings
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'crm_api_key'");
    $stmtCheck->execute();
    
    if ($stmtCheck->fetchColumn() > 0) {
        $stmtUpd = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'crm_api_key'");
        $stmtUpd->execute([$newKey]);
    } else {
        $stmtIns = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_api_key', ?)");
        $stmtIns->execute([$newKey]);
    }

    // Asegurar que crm_api_enabled esté activo si no existe
    $stmtEn = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'crm_api_enabled'");
    $stmtEn->execute();
    if ($stmtEn->fetchColumn() == 0) {
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_api_enabled', '1')")->execute();
    }

    echo json_encode([
        'success' => true,
        'api_key' => $newKey,
        'message' => 'Nueva App Key generada exitosamente'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
