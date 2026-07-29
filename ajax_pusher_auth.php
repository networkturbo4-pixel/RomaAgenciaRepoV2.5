<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Forbidden';
    exit;
}

require __DIR__ . '/vendor/autoload.php';
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

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

$socketId = $_POST['socket_id'];
$channelName = $_POST['channel_name'];

// Get user info
$stmt = $db->prepare("SELECT id, name, avatar FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$presence_data = ['name' => $user['name']];
if (!empty($user['avatar'])) {
    $presence_data['avatar'] = $user['avatar'];
}

echo $pusher->presence_auth($channelName, $socketId, $user['id'], $presence_data);
