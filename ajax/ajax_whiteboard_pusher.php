<?php
// ajax/ajax_whiteboard_pusher.php
require_once '../config/database.php';
require_once '../vendor/autoload.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input']);
    exit;
}

$board_id = $input['board_id'] ?? 0;
$event = $input['event'] ?? 'canvas-updated';
$data = $input['data'] ?? [];

if (!$board_id) {
    echo json_encode(['success' => false, 'error' => 'Board ID missing']);
    exit;
}

$options = array(
    'cluster' => 'us2',
    'useTLS' => true
);
$pusher = new Pusher\Pusher(
    'b31f38612d61b0285c78',
    'c0cabd7a57efdc79f42e',
    '2156473',
    $options
);

// We append the user_id who made the change so we can ignore it on the client side
$data['sender_id'] = $_SESSION['user_id'];

try {
    $pusher->trigger('presence-whiteboard-' . $board_id, $event, $data);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
