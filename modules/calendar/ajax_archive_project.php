<?php
// modules/calendar/ajax_archive_project.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !in_array($status, ['active', 'archived'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

try {
    $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
