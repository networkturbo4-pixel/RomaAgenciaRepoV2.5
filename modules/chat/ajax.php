<?php
// modules/chat/ajax.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
$isGuest = !$userId;

// Public actions that don't require login
$publicActions = ['get_public_channel', 'get_public_messages', 'send_public_message', 'poll_public'];

if (!$userId && !in_array($action, $publicActions)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Update user status on every action
if ($userId) {
    $db->prepare("INSERT INTO chat_user_status (user_id, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()")
       ->execute([$userId]);
}

try {
    switch ($action) {

        // ── GET CHANNELS ──
        case 'get_channels':
            $stmt = $db->prepare("
                SELECT c.*, 
                    (SELECT COUNT(*) FROM chat_messages m 
                     WHERE m.channel_id = c.id 
                     AND m.created_at > COALESCE(
                         (SELECT cm2.last_read_at FROM chat_channel_members cm2 WHERE cm2.channel_id = c.id AND cm2.user_id = ?),
                         '1970-01-01'
                     )
                     AND m.user_id != ?
                    ) as unread_count,
                    (SELECT m2.message FROM chat_messages m2 WHERE m2.channel_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                    (SELECT m3.created_at FROM chat_messages m3 WHERE m3.channel_id = c.id ORDER BY m3.created_at DESC LIMIT 1) as last_message_at
                FROM chat_channels c
                INNER JOIN chat_channel_members cm ON cm.channel_id = c.id AND cm.user_id = ?
                ORDER BY last_message_at DESC, c.created_at DESC
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For direct channels, get the other user's info
            foreach ($channels as &$ch) {
                if ($ch['type'] === 'direct') {
                    $stmtOther = $db->prepare("
                        SELECT u.id, u.name, u.avatar,
                            CASE WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 1 ELSE 0 END as is_online
                        FROM chat_channel_members cm
                        JOIN users u ON u.id = cm.user_id
                        LEFT JOIN chat_user_status s ON s.user_id = u.id
                        WHERE cm.channel_id = ? AND cm.user_id != ?
                        LIMIT 1
                    ");
                    $stmtOther->execute([$ch['id'], $userId]);
                    $ch['other_user'] = $stmtOther->fetch(PDO::FETCH_ASSOC);
                }
                $ch['unread_count'] = (int)$ch['unread_count'];
            }

            echo json_encode(['success' => true, 'channels' => $channels]);
            break;

        // ── GET MESSAGES ──
        case 'get_messages':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $before = $_POST['before'] ?? null; // for pagination
            $limit = 50;

        $sql = "SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name
                FROM chat_messages m
                LEFT JOIN users u ON u.id = m.user_id
                LEFT JOIN chat_messages r ON r.id = m.reply_to_id
                LEFT JOIN users ru ON ru.id = r.user_id
                WHERE m.channel_id = ?";
        $params = [$channelId];

            if ($before) {
                $sql .= " AND m.id < ?";
                $params[] = (int)$before;
            }
            $sql .= " ORDER BY m.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

            // Mark as read
            $db->prepare("UPDATE chat_channel_members SET last_read_at = NOW() WHERE channel_id = ? AND user_id = ?")
               ->execute([$channelId, $userId]);

            // Get channel info
            $stmtCh = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM chat_channel_members WHERE channel_id = c.id) as member_count FROM chat_channels c WHERE c.id = ?");
            $stmtCh->execute([$channelId]);
            $channel = $stmtCh->fetch(PDO::FETCH_ASSOC);

            if ($channel && $channel['type'] === 'direct') {
                $stmtOther = $db->prepare("
                    SELECT u.name FROM chat_channel_members cm 
                    JOIN users u ON u.id = cm.user_id 
                    WHERE cm.channel_id = ? AND cm.user_id != ? LIMIT 1
                ");
                $stmtOther->execute([$channelId, $userId]);
                $otherName = $stmtOther->fetchColumn();
                if ($otherName) {
                    $channel['name'] = $otherName;
                }
            }

            // Get online count
            $stmtOnline = $db->prepare("
                SELECT COUNT(*) FROM chat_channel_members cm
                JOIN chat_user_status s ON s.user_id = cm.user_id
                WHERE cm.channel_id = ? AND TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5
            ");
            $stmtOnline->execute([$channelId]);
            $channel['online_count'] = (int)$stmtOnline->fetchColumn();

            echo json_encode(['success' => true, 'messages' => $messages, 'channel' => $channel]);
            break;

        // ── SEND MESSAGE ──
        case 'send_message':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $messageType = $_POST['message_type'] ?? 'text';
            $cardData = $_POST['card_data'] ?? null;
            $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

            if (empty($message) && $messageType === 'text') {
                echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
                exit();
            }

            $attachment = null;
            $attachmentName = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/chat/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $filename = 'chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename);
                $attachment = 'uploads/chat/' . $filename;
                $attachmentName = $_FILES['attachment']['name'];
                if ($messageType === 'text' && empty($message)) $messageType = 'file';
            }

            $stmt = $db->prepare("INSERT INTO chat_messages (channel_id, user_id, message, message_type, card_data, attachment, attachment_name, reply_to_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$channelId, $userId, $message, $messageType, $cardData, $attachment, $attachmentName, $replyToId]);
            $msgId = $db->lastInsertId();

            // Mark as read for sender
            $db->prepare("UPDATE chat_channel_members SET last_read_at = NOW() WHERE channel_id = ? AND user_id = ?")
               ->execute([$channelId, $userId]);

            // Fetch the inserted message with user info
            $stmtMsg = $db->prepare("
                SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name
                FROM chat_messages m 
                LEFT JOIN users u ON u.id = m.user_id 
                LEFT JOIN chat_messages r ON r.id = m.reply_to_id
                LEFT JOIN users ru ON ru.id = r.user_id
                WHERE m.id = ?
            ");
            $stmtMsg->execute([$msgId]);
            $newMsg = $stmtMsg->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'message' => $newMsg]);
            break;

        // ── POLL UPDATES ──
        case 'poll_updates':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $lastId = (int)($_POST['last_id'] ?? 0);

            $stmt = $db->prepare("
                SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name
                FROM chat_messages m 
                LEFT JOIN users u ON u.id = m.user_id 
                LEFT JOIN chat_messages r ON r.id = m.reply_to_id
                LEFT JOIN users ru ON ru.id = r.user_id
                WHERE m.channel_id = ? AND m.id > ? 
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$channelId, $lastId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark as read
            if (!empty($messages)) {
                $db->prepare("UPDATE chat_channel_members SET last_read_at = NOW() WHERE channel_id = ? AND user_id = ?")
                   ->execute([$channelId, $userId]);
            }

            // Get unread counts for sidebar
            $stmtUnread = $db->prepare("
                SELECT c.id, COUNT(m.id) as unread
                FROM chat_channels c
                JOIN chat_channel_members cm ON cm.channel_id = c.id AND cm.user_id = ?
                LEFT JOIN chat_messages m ON m.channel_id = c.id AND m.created_at > COALESCE(cm.last_read_at, '1970-01-01') AND m.user_id != ?
                GROUP BY c.id
            ");
            $stmtUnread->execute([$userId, $userId]);
            $unreads = [];
            while ($row = $stmtUnread->fetch()) {
                $unreads[$row['id']] = (int)$row['unread'];
            }

            echo json_encode(['success' => true, 'messages' => $messages, 'unreads' => $unreads]);
            break;

        // ── CREATE CHANNEL ──
        case 'create_channel':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $members = json_decode($_POST['members'] ?? '[]', true);

            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
                exit();
            }

            $stmt = $db->prepare("INSERT INTO chat_channels (name, type, description, created_by) VALUES (?, 'group', ?, ?)");
            $stmt->execute([$name, $description, $userId]);
            $chId = $db->lastInsertId();

            // Add creator
            $db->prepare("INSERT INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)")->execute([$chId, $userId]);
            // Add selected members
            $stmtAdd = $db->prepare("INSERT IGNORE INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)");
            foreach ($members as $mid) {
                $stmtAdd->execute([$chId, (int)$mid]);
            }

            echo json_encode(['success' => true, 'channel_id' => $chId]);
            break;

        // ── CREATE DM ──
        case 'create_dm':
            $otherUserId = (int)($_POST['other_user_id'] ?? 0);

            // Check if DM already exists
            $stmt = $db->prepare("
                SELECT c.id FROM chat_channels c
                JOIN chat_channel_members cm1 ON cm1.channel_id = c.id AND cm1.user_id = ?
                JOIN chat_channel_members cm2 ON cm2.channel_id = c.id AND cm2.user_id = ?
                WHERE c.type = 'direct' LIMIT 1
            ");
            $stmt->execute([$userId, $otherUserId]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode(['success' => true, 'channel_id' => $existing['id']]);
            } else {
                $stmtOther = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmtOther->execute([$otherUserId]);
                $otherName = $stmtOther->fetchColumn();

                $db->prepare("INSERT INTO chat_channels (name, type, created_by) VALUES (?, 'direct', ?)")
                   ->execute(['DM', $userId]);
                $chId = $db->lastInsertId();
                $db->prepare("INSERT INTO chat_channel_members (channel_id, user_id) VALUES (?, ?), (?, ?)")
                   ->execute([$chId, $userId, $chId, $otherUserId]);
                echo json_encode(['success' => true, 'channel_id' => $chId]);
            }
            break;

        // ── GET ONLINE USERS ──
        case 'get_online_users':
            $stmt = $db->query("
                SELECT u.id, u.name, u.avatar,
                    CASE WHEN TIMESTAMPDIFF(MINUTE, s.last_seen, NOW()) <= 5 THEN 1 ELSE 0 END as is_online
                FROM users u
                LEFT JOIN chat_user_status s ON s.user_id = u.id
                ORDER BY is_online DESC, u.name ASC
            ");
            echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── GENERATE INVITE ──
        case 'generate_invite':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $token = bin2hex(random_bytes(16));
            $db->prepare("UPDATE chat_channels SET public_token = ? WHERE id = ?")->execute([$token, $channelId]);
            echo json_encode(['success' => true, 'token' => $token]);
            break;

        // ── REVOKE INVITE ──
        case 'revoke_invite':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $db->prepare("UPDATE chat_channels SET public_token = NULL WHERE id = ?")->execute([$channelId]);
            echo json_encode(['success' => true]);
            break;

        // ── SEARCH ITEMS (for cards) ──
        case 'search_items':
            $type = $_POST['type'] ?? '';
            $q = '%' . trim($_POST['q'] ?? '') . '%';
            $results = [];

            if ($type === 'client') {
                $stmt = $db->prepare("SELECT id, name, whatsapp, email FROM clients WHERE name LIKE ? LIMIT 10");
                $stmt->execute([$q]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'quote') {
                $stmt = $db->prepare("SELECT q.id, q.total, q.currency, q.status, q.due_date, c.name as client_name FROM quotes q LEFT JOIN clients c ON c.id = q.client_id WHERE c.name LIKE ? OR q.id LIKE ? LIMIT 10");
                $stmt->execute([$q, $q]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'service') {
                $stmt = $db->prepare("SELECT s.id, s.name, s.price, s.description, sc.name as category_name FROM services s LEFT JOIN service_categories sc ON sc.id = s.category_id WHERE s.name LIKE ? LIMIT 10");
                $stmt->execute([$q]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'month') {
                $stmt = $db->prepare("
                    SELECT pm.id, pm.month, pm.year, pm.status, w.brand_name,
                        (SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id) as post_count
                    FROM project_months pm
                    JOIN projects p ON p.id = pm.project_id
                    JOIN work_orders w ON w.id = p.work_order_id
                    WHERE w.brand_name LIKE ? LIMIT 10
                ");
                $stmt->execute([$q]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['success' => true, 'results' => $results]);
            break;

        // ── PUBLIC: GET CHANNEL ──
        case 'get_public_channel':
            $token = $_POST['token'] ?? '';
            $stmt = $db->prepare("SELECT id, name, description FROM chat_channels WHERE public_token = ?");
            $stmt->execute([$token]);
            $ch = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => (bool)$ch, 'channel' => $ch]);
            break;

        // ── PUBLIC: GET MESSAGES ──
        case 'get_public_messages':
            $token = $_POST['token'] ?? '';
            $stmt = $db->prepare("SELECT id FROM chat_channels WHERE public_token = ?");
            $stmt->execute([$token]);
            $ch = $stmt->fetch();
            if (!$ch) { echo json_encode(['success' => false]); exit(); }

            $stmt = $db->prepare("SELECT m.*, u.name as user_name, u.avatar as user_avatar FROM chat_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.channel_id = ? ORDER BY m.created_at DESC LIMIT 50");
            $stmt->execute([$ch['id']]);
            echo json_encode(['success' => true, 'messages' => array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC))]);
            break;

        // ── PUBLIC: SEND MESSAGE ──
        case 'send_public_message':
            $token = $_POST['token'] ?? '';
            $guestName = trim($_POST['guest_name'] ?? '');
            $message = trim($_POST['message'] ?? '');

            $stmt = $db->prepare("SELECT id, allow_guest_write FROM chat_channels WHERE public_token = ?");
            $stmt->execute([$token]);
            $ch = $stmt->fetch();
            if (!$ch || !$ch['allow_guest_write']) { echo json_encode(['success' => false, 'error' => 'No permitido']); exit(); }

            $db->prepare("INSERT INTO chat_messages (channel_id, user_id, guest_name, message, message_type) VALUES (?, NULL, ?, ?, 'text')")
               ->execute([$ch['id'], $guestName, $message]);

            echo json_encode(['success' => true]);
            break;

        // ── PUBLIC: POLL ──
        case 'poll_public':
            $token = $_POST['token'] ?? '';
            $lastId = (int)($_POST['last_id'] ?? 0);
            $stmt = $db->prepare("SELECT id FROM chat_channels WHERE public_token = ?");
            $stmt->execute([$token]);
            $ch = $stmt->fetch();
            if (!$ch) { echo json_encode(['success' => false]); exit(); }

            $stmt = $db->prepare("SELECT m.*, u.name as user_name, u.avatar as user_avatar FROM chat_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.channel_id = ? AND m.id > ? ORDER BY m.created_at ASC");
            $stmt->execute([$ch['id'], $lastId]);
            echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── DELETE MESSAGE ──
        case 'delete_message':
            $msgId = (int)($_POST['message_id'] ?? 0);
            
            // Verificamos que el mensaje sea del usuario actual o que sea admin
            $stmt = $db->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
            $stmt->execute([$msgId]);
            $msg = $stmt->fetch();
            
            // Check roles if exist, else we rely on $userId
            // Note: $role might not be defined explicitly in all scopes if not fetched. Let's fetch the role.
            $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmtRole->execute([$userId]);
            $userRoleId = $stmtRole->fetchColumn();

            if ($msg && ($msg['user_id'] == $userId || $userRoleId == 1)) {
                $db->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$msgId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
            }
            break;

        // ── DELETE CHANNEL ──
        case 'delete_channel':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            
            // Verificamos que el canal sea del usuario actual o que sea admin
            $stmt = $db->prepare("SELECT created_by FROM chat_channels WHERE id = ?");
            $stmt->execute([$channelId]);
            $ch = $stmt->fetch();
            
            $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmtRole->execute([$userId]);
            $userRoleId = $stmtRole->fetchColumn();
            
            if ($ch && ($ch['created_by'] == $userId || $userRoleId == 1)) {
                $db->prepare("DELETE FROM chat_messages WHERE channel_id = ?")->execute([$channelId]);
                $db->prepare("DELETE FROM chat_channel_members WHERE channel_id = ?")->execute([$channelId]);
                $db->prepare("DELETE FROM chat_channels WHERE id = ?")->execute([$channelId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No autorizado para eliminar este canal']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
