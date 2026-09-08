<?php
// ajax_edit_public_comment.php
session_start();
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

    // Verificar autorización: Usuario autenticado, Portal de cliente o PIN de tablero público
    $is_authorized = isset($_SESSION['user_id']) || !empty($_SESSION['client_portal_id']);
    if (!$is_authorized) {
        $stmtM = $db->prepare("SELECT mp.month_id FROM post_comments c JOIN month_posts mp ON c.post_id = mp.id WHERE c.id = ?");
        $stmtM->execute([$id]);
        $month_id = $stmtM->fetchColumn();
        if ($month_id && !empty($_SESSION['public_auth_' . $month_id])) {
            $is_authorized = true;
        }
    }

    if (!$is_authorized) {
        echo json_encode(['success' => false, 'error' => 'No autorizado para editar este comentario']);
        exit();
    }

    $stmt = $db->prepare("UPDATE post_comments SET comment_text = ? WHERE id = ?");
    $stmt->execute([$comment_text, $id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
