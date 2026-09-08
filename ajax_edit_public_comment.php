<?php
// ajax_edit_public_comment.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

    // Verificar autorización: Usuario autenticado, Portal de cliente o Acceso al tablero público
    $is_authorized = isset($_SESSION['user_id']) || !empty($_SESSION['client_portal_id']);
    if (!$is_authorized) {
        $stmtM = $db->prepare("
            SELECT mp.month_id, pm.pin 
            FROM post_comments c 
            JOIN month_posts mp ON c.post_id = mp.id 
            JOIN project_months pm ON mp.month_id = pm.id 
            WHERE c.id = ?
        ");
        $stmtM->execute([$id]);
        $boardInfo = $stmtM->fetch(PDO::FETCH_ASSOC);
        if ($boardInfo) {
            $month_id = (int)$boardInfo['month_id'];
            $boardPin = trim($boardInfo['pin'] ?? '');
            // Si el tablero no tiene PIN (acceso libre) o la sesión tiene la clave validada
            if (empty($boardPin) || !empty($_SESSION['public_auth_' . $month_id])) {
                $is_authorized = true;
            }
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
