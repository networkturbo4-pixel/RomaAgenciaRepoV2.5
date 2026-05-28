<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $post_date = isset($_POST['post_date']) ? trim($_POST['post_date']) : '';

    if (!$post_id || empty($post_date)) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit();
    }

    $stmt = $db->prepare("UPDATE month_posts SET post_date = ? WHERE id = ?");
    $result = $stmt->execute([$post_date, $post_id]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el post en la base de datos']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
}
?>
