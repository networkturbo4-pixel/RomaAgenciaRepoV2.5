<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$postId = $_POST['post_id'] ?? null;
$reviewed = $_POST['reviewed'] ?? 0;

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID es requerido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    if ($reviewed == 1) {
        $stmt = $db->prepare("UPDATE month_posts SET reviewed = ?, status = 'Aprobado', drawing_data = NULL, sticky_notes = NULL WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE month_posts SET reviewed = ?, status = 'En Revisión' WHERE id = ?");
    }
    $stmt->execute([$reviewed, $postId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
