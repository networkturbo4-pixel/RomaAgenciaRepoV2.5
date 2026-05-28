<?php
// modules/calendar/ajax_update_project.php
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

$id = $_POST['id'] ?? null;
$team_members = $_POST['team_members'] ?? [];

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID de proyecto es requerido']);
    exit();
}

try {
    $teamJson = json_encode($team_members);

    $stmt = $db->prepare("UPDATE projects SET team_members = ? WHERE id = ?");
    $stmt->execute([$teamJson, $id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
