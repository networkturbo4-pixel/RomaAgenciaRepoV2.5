<?php
// modules/admin/ajax_delete_payment_note.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID requerido']);
    exit();
}

$db = (new Database())->getConnection();

$perms = $_SESSION['user_permissions'] ?? [];

if (!in_array('admin', $perms)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado (Rol)']);
    exit();
}

try {
    // Delete by note_code (id from frontend)
    $stmt = $db->prepare("DELETE FROM payment_notes WHERE note_code = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
