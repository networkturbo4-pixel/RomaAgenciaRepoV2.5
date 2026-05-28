<?php
// modules/month_board/ajax_mark_comment_resolved.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $db = (new Database())->getConnection();

    $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    if ($comment_id <= 0 || $post_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    // Actualizar el comentario
    $stmt = $db->prepare("UPDATE post_comments SET status = 'Levantado' WHERE id = ?");
    $stmt->execute([$comment_id]);

    // Opcional: Revertir el post a Borrador o dejar que el usuario lo cambie
    $stmtUpdate = $db->prepare("UPDATE month_posts SET status = 'Borrador' WHERE id = ?");
    $stmtUpdate->execute([$post_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
