<?php
// ajax/ajax_run_backup.php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No estás autenticado.']);
    exit;
}

require_once '../config/database.php';
$db = (new Database())->getConnection();

// Verificar que sea admin
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role_id = $stmt->fetchColumn();

if ($role_id != 1) { // 1 = Admin
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para ejecutar backups.']);
    exit;
}

// Ejecutar el script de backup
require_once '../cron/drive_backup.php';
