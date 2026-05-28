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

    $month_id = isset($_GET['month_id']) ? (int)$_GET['month_id'] : 0;

    if (!$month_id) {
        echo json_encode(['success' => false, 'error' => 'ID de mes inválido']);
        exit();
    }

    $stmt = $db->prepare("SELECT * FROM month_posts WHERE month_id = ? ORDER BY post_date ASC, id ASC");
    $stmt->execute([$month_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $posts]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
