<?php
// ajax/get_all_brands.php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = (new Database())->getConnection();

// Fetch all active brands
$stmt = $db->query("SELECT id, name FROM client_brands ORDER BY name ASC");
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['marcas' => $marcas]);
