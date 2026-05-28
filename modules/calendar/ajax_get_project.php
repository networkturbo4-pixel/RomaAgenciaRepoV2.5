<?php
// modules/calendar/ajax_get_project.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT team_members FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'team_members' => json_decode($project['team_members'], true) ?: []
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
