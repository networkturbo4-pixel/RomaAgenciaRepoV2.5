<?php
// modules/quotes/ajax_search_quotes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "SELECT q.*, c.name as client_name 
        FROM quotes q 
        LEFT JOIN clients c ON q.client_id = c.id 
        WHERE 1=1";
$params = [];

if ($query !== '') {
    $sql .= " AND (c.name LIKE ? OR q.id LIKE ? OR q.total LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
    $params[] = "%$query%";
}

if ($status !== '' && $status !== 'todos') {
    $sql .= " AND q.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY q.created_at DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $quotes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
