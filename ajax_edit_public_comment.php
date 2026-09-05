<?php
// ajax_edit_public_comment.php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

    if ($id <= 0 || empty($comment_text)) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    $stmt = $db->prepare("UPDATE post_comments SET comment_text = ? WHERE id = ?");
    $stmt->execute([$comment_text, $id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
