<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$postId = $_POST['post_id'] ?? 0;
$stickyNotes = $_POST['sticky_notes'] ?? '[]';

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'ID de post invalido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("UPDATE month_posts SET sticky_notes = ? WHERE id = ?");
    $stmt->execute([$stickyNotes, $postId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
