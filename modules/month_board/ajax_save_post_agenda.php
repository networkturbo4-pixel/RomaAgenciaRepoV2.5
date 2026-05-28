<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$postId = $_POST['post_id'] ?? 0;
$notes = $_POST['notes'] ?? '';
$tasks = $_POST['tasks'] ?? '[]';

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'ID de post inválido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("UPDATE month_posts SET presenter_notes = ?, agenda_tasks = ? WHERE id = ?");
    $stmt->execute([$notes, $tasks, $postId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
