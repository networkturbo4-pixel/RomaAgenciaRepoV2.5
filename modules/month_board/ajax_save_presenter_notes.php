<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$postId = $_POST['post_id'] ?? null;
$notes = $_POST['notes'] ?? '';

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID es requerido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("UPDATE month_posts SET presenter_notes = ? WHERE id = ?");
    $stmt->execute([$notes, $postId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
