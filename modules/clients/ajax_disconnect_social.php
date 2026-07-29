<?php
// modules/clients/ajax_disconnect_social.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$platform = isset($_POST['platform']) ? $_POST['platform'] : '';

if (!$client_id || !$platform) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$stmt = $db->prepare("DELETE FROM client_social_accounts WHERE client_id = ? AND platform = ?");
if ($stmt->execute([$client_id, $platform])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
