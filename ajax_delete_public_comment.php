<?php
// ajax_delete_public_comment.php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stmt = $db->prepare("DELETE FROM post_comments WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
