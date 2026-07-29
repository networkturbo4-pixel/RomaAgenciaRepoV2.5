<?php
/**
 * ajax_track_note_view.php
 * Public endpoint - No authentication required
 * Tracks a view for a payment note by its public token.
 *
 * POST JSON body: {"token": "..."}
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token requerido']);
    exit;
}

$token = $input['token'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $db = (new Database())->getConnection();

    // Look up note by public_token
    $stmt = $db->prepare("SELECT id FROM payment_notes WHERE public_token = :token LIMIT 1");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }

    $note_id = $note['id'];

    // Insert view record
    $stmt = $db->prepare("INSERT INTO payment_note_views (note_id, ip_address, user_agent) VALUES (:note_id, :ip, :ua)");
    $stmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
    $stmt->bindParam(':ip', $ip_address);
    $stmt->bindParam(':ua', $user_agent);
    $stmt->execute();

    // Update view count and last viewed timestamp
    $stmt = $db->prepare("UPDATE payment_notes SET view_count = view_count + 1, last_viewed_at = CURRENT_TIMESTAMP WHERE id = :note_id");
    $stmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
