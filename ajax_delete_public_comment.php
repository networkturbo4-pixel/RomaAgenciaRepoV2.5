<?php
// ajax_delete_public_comment.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
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
        echo json_encode(['success' => false, 'error' => 'No autorizado para eliminar este comentario']);
        exit();
    }

    $stmt = $db->prepare("DELETE FROM post_comments WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
