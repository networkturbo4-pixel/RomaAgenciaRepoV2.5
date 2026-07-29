<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = (new Database())->getConnection();

$q = $_GET['q'] ?? '';

$sql = "SELECT id, name FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 5";
$stmt = $db->prepare($sql);
$stmt->execute(["%$q%", "%$q%"]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);
?>
