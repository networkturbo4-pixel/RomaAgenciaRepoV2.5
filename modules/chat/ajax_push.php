<?php
// modules/chat/ajax_push.php — Push Notification Endpoints
session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

// VAPID keys — replace with your own generated keys
define('VAPID_PUBLIC_KEY', 'BAhu9ZcA2cypGC--dbgdXicyU_K4cvZUdRhP4nQ7Y4t8M2LN156sVAWKg1swXA6KIyjBZvZkeIKqTZxxNpdNksI');
define('VAPID_PRIVATE_KEY', 'QaRTxhVHLghTyAGwSw63Bw3sYMqPRpZi8wmvAqR0YWA');
define('VAPID_SUBJECT', 'mailto:admin@example.com');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

try {
    switch ($action) {
        case 'get_vapid_key':
            echo json_encode(['success' => true, 'key' => VAPID_PUBLIC_KEY]);
            break;

        case 'subscribe':
            if (!$userId) { echo json_encode(['success' => false]); exit(); }
            $endpoint = $_POST['endpoint'] ?? '';
            $p256dh = $_POST['p256dh'] ?? '';
            $auth = $_POST['auth'] ?? '';

            if (empty($endpoint) || empty($p256dh) || empty($auth)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit();
            }

            // Remove existing subscription for this endpoint
            $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$endpoint]);

            $stmt = $db->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $endpoint, $p256dh, $auth]);

            echo json_encode(['success' => true]);
            break;

        case 'unsubscribe':
            if (!$userId) { echo json_encode(['success' => false]); exit(); }
            $endpoint = $_POST['endpoint'] ?? '';
            $db->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?")->execute([$userId, $endpoint]);
            echo json_encode(['success' => true]);
            break;

        case 'send_push':
            // Called internally when a message is sent
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $senderId = (int)($_POST['sender_id'] ?? 0);
            $messageBody = $_POST['message_body'] ?? '';
            $senderName = $_POST['sender_name'] ?? 'Usuario';
            $senderAvatar = $_POST['sender_avatar'] ?? '';

            // Get all members of the channel except sender
            $stmtMembers = $db->prepare("
                SELECT ps.* FROM push_subscriptions ps
                JOIN chat_channel_members cm ON cm.user_id = ps.user_id
                WHERE cm.channel_id = ? AND ps.user_id != ?
            ");
            $stmtMembers->execute([$channelId, $senderId]);
            $subscriptions = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscriptions)) {
                echo json_encode(['success' => true, 'sent' => 0]);
                exit();
            }

            // Get channel name
            $stmtCh = $db->prepare("SELECT name, type FROM chat_channels WHERE id = ?");
            $stmtCh->execute([$channelId]);
            $ch = $stmtCh->fetch(PDO::FETCH_ASSOC);
            $channelLabel = $ch['type'] === 'group' ? "# {$ch['name']}" : 'Mensaje directo';

            $auth = [
                'VAPID' => [
                    'subject' => VAPID_SUBJECT,
                    'publicKey' => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ];

            $webPush = new Minishlink\WebPush\WebPush($auth);

            $payload = json_encode([
                'title' => $senderName,
                'body' => mb_substr($messageBody, 0, 100) . (mb_strlen($messageBody) > 100 ? '...' : ''),
                'icon' => $senderAvatar ?: '/assets/img/default-icon.png',
                'tag' => "chat-channel-{$channelId}",
                'url' => "index.php?module=chat&action=index&channel={$channelId}"
            ]);

            $sent = 0;
            foreach ($subscriptions as $sub) {
                $subscription = Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['p256dh'],
                    'authToken' => $sub['auth_token'],
                ]);

                $webPush->queueNotification($subscription, $payload);
                $sent++;
            }

            // Flush and handle expired subscriptions
            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$report->getEndpoint()]);
                }
            }

            echo json_encode(['success' => true, 'sent' => $sent]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
