<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$postId = $_POST['post_id'] ?? null;
$postDate = $_POST['post_date'] ?? null;

if (!$postId || !$postDate) {
    echo json_encode(['success' => false, 'error' => 'Post ID o fecha requeridos']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Validar formato de fecha básico
    $date = date('Y-m-d H:i:s', strtotime($postDate));
    
    $stmt = $db->prepare("UPDATE month_posts SET post_date = ? WHERE id = ?");
    $stmt->execute([$date, $postId]);
    
    echo json_encode(['success' => true, 'new_date' => $date]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
