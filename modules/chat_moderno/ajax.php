<?php
// modules/chat_moderno/ajax.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Auto-clean any corrupt undefined reactions from DB
try {
    $db->exec("DELETE FROM msg_reactions WHERE emoji = 'undefined' OR emoji IS NULL OR emoji = ''");
} catch (Exception $e) {}

// Pusher setup
$pusher = null;
try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('Pusher\Pusher')) {
        $pusher_options = ['cluster' => 'us2', 'useTLS' => true];
        $pusher = new Pusher\Pusher('b31f38612d61b0285c78', 'c0cabd7a57efdc79f42e', '2156473', $pusher_options);
    }
} catch (Exception $e) {
    // Pusher unavailable, fallback gracefully
}

function triggerRefresh($chat_id) {
    global $pusher;
    if ($pusher && $chat_id) {
        try {
            $pusher->trigger('chat-' . $chat_id, 'refresh', ['time' => time()]);
        } catch (Exception $e) {}
    }
}

function formatUserAvatar($avatar, $name) {
    if (!empty($avatar)) {
        if (strpos($avatar, 'http') === 0) {
            return $avatar;
        }
        $cleaned = ltrim($avatar, '/');
        $localPath = __DIR__ . '/../../' . $cleaned;
        if (file_exists($localPath)) {
            return $cleaned;
        }
    }
    // High-end SaaS SVG avatar generator with smooth gradient
    $safeName = !empty($name) ? $name : 'User';
    return 'https://ui-avatars.com/api/?name=' . urlencode($safeName) . '&background=4f46e5&color=ffffff&bold=true&rounded=true';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── 1. GET ALL ROOMS (CHATS) ──────────────────────────────
    case 'get_rooms':
        try {
            $stmt = $db->prepare("
                SELECT c.*,
                       (SELECT content FROM msg_messages m WHERE m.chat_id = c.id AND m.is_deleted = 0 ORDER BY m.created_at DESC LIMIT 1) as last_content,
                       (SELECT type FROM msg_messages m WHERE m.chat_id = c.id AND m.is_deleted = 0 ORDER BY m.created_at DESC LIMIT 1) as last_type,
                       (SELECT sender_user_id FROM msg_messages m WHERE m.chat_id = c.id AND m.is_deleted = 0 ORDER BY m.created_at DESC LIMIT 1) as last_sender_id,
                       (SELECT u.name FROM msg_messages m JOIN users u ON m.sender_user_id = u.id WHERE m.chat_id = c.id AND m.is_deleted = 0 ORDER BY m.created_at DESC LIMIT 1) as last_sender_name,
                       (SELECT created_at FROM msg_messages m WHERE m.chat_id = c.id AND m.is_deleted = 0 ORDER BY m.created_at DESC LIMIT 1) as last_created_at,
                       (SELECT u2.name FROM msg_participants p2 JOIN users u2 ON p2.user_id = u2.id WHERE p2.chat_id = c.id AND p2.user_id != ? LIMIT 1) as other_user_name,
                       (SELECT u2.avatar FROM msg_participants p2 JOIN users u2 ON p2.user_id = u2.id WHERE p2.chat_id = c.id AND p2.user_id != ? LIMIT 1) as other_user_avatar,
                       (SELECT COUNT(*) FROM msg_messages m 
                        WHERE m.chat_id = c.id 
                          AND (m.sender_user_id != ? OR m.sender_user_id IS NULL) 
                          AND m.is_deleted = 0
                          AND NOT EXISTS (
                              SELECT 1 FROM msg_receipts r 
                              WHERE r.message_id = m.id AND r.user_id = ? AND r.status = 'read'
                          )
                       ) as unread_count
                FROM msg_chats c
                JOIN msg_participants p ON c.id = p.chat_id
                WHERE p.user_id = ?
                ORDER BY COALESCE(last_created_at, c.created_at) DESC
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rooms = [];
            foreach ($chats as $chat) {
                $isDirect = ($chat['type'] === 'direct');
                $roomName = $isDirect ? ($chat['other_user_name'] ?? 'Usuario') : ($chat['name'] ?? 'Grupo');
                $rawAvatar = $isDirect ? $chat['other_user_avatar'] : $chat['avatar'];
                $avatar = formatUserAvatar($rawAvatar, $roomName);

                // Fetch participants of this chat
                $stmtP = $db->prepare("
                    SELECT u.id, u.name, u.avatar 
                    FROM msg_participants p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.chat_id = ?
                ");
                $stmtP->execute([$chat['id']]);
                $participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);
                
                $users = [];
                foreach ($participants as $p) {
                    $users[] = [
                        '_id' => (string)$p['id'],
                        'username' => $p['name'],
                        'avatar' => formatUserAvatar($p['avatar'], $p['name']),
                        'status' => [
                            'state' => 'online',
                            'lastChanged' => 'hoy'
                        ]
                    ];
                }

                // Format last message preview
                $lastMsgText = '';
                if ($chat['last_type'] === 'image') $lastMsgText = '📷 Foto';
                elseif ($chat['last_type'] === 'video') $lastMsgText = '🎥 Video';
                elseif ($chat['last_type'] === 'audio') $lastMsgText = '🎵 Nota de voz';
                elseif ($chat['last_type'] === 'file') $lastMsgText = '📎 Archivo';
                else $lastMsgText = $chat['last_content'] ?: 'Sin mensajes';

                $lastTimestamp = '';
                if (!empty($chat['last_created_at'])) {
                    $lastTimestamp = date('H:i', strtotime($chat['last_created_at']));
                }

                $rooms[] = [
                    'roomId' => (string)$chat['id'],
                    'roomName' => $roomName,
                    'avatar' => $avatar,
                    'unreadCount' => (int)$chat['unread_count'],
                    'index' => count($rooms),
                    'lastMessage' => [
                        'content' => $lastMsgText,
                        'senderId' => (string)($chat['last_sender_id'] ?: $user_id),
                        'username' => $chat['last_sender_name'] ?? '',
                        'timestamp' => $lastTimestamp,
                        'saved' => true,
                        'distributed' => true,
                        'seen' => (int)$chat['unread_count'] === 0
                    ],
                    'users' => $users,
                    'typingUsers' => []
                ];
            }

            echo json_encode(['success' => true, 'rooms' => $rooms]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── 2. GET MESSAGES FOR A ROOM ────────────────────────────
    case 'get_messages':
        $chat_id = (int)($_GET['chat_id'] ?? $_POST['chat_id'] ?? 0);
        if (!$chat_id) {
            echo json_encode(['error' => 'chat_id requerido']);
            exit;
        }

        // Verify participant
        $stmtCheck = $db->prepare("SELECT 1 FROM msg_participants WHERE chat_id = ? AND user_id = ?");
        $stmtCheck->execute([$chat_id, $user_id]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['error' => 'No perteneces a esta conversación']);
            exit;
        }

        // Fetch messages
        $stmt = $db->prepare("
            SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                   rm.content as reply_content, ru.name as reply_user_name, rm.type as reply_type, rm.file_name as reply_file_name, rm.sender_user_id as reply_sender_id
            FROM msg_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN msg_messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.sender_user_id = ru.id
            WHERE m.chat_id = ?
            ORDER BY m.id ASC
        ");
        $stmt->execute([$chat_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark unread messages as read
        $stmtUnread = $db->prepare("
            SELECT m.id FROM msg_messages m
            WHERE m.chat_id = ? AND m.sender_user_id != ?
              AND NOT EXISTS (SELECT 1 FROM msg_receipts r WHERE r.message_id = m.id AND r.user_id = ? AND r.status = 'read')
        ");
        $stmtUnread->execute([$chat_id, $user_id, $user_id]);
        $unreads = $stmtUnread->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($unreads)) {
            $stmtReceipt = $db->prepare("
                INSERT INTO msg_receipts (message_id, user_id, status)
                VALUES (?, ?, 'read')
                ON DUPLICATE KEY UPDATE status = 'read'
            ");
            foreach ($unreads as $mid) {
                $stmtReceipt->execute([$mid, $user_id]);
            }
        }

        // Fetch all reactions for this chat (excluding invalid 'undefined')
        $stmtReactions = $db->prepare("
            SELECT r.message_id, r.emoji, r.user_id
            FROM msg_reactions r
            JOIN msg_messages m ON r.message_id = m.id
            WHERE m.chat_id = ? AND r.emoji != 'undefined' AND r.emoji IS NOT NULL AND r.emoji != ''
        ");
        $stmtReactions->execute([$chat_id]);
        $allReactions = $stmtReactions->fetchAll(PDO::FETCH_ASSOC);
        $reactionsByMsg = [];
        foreach ($allReactions as $rx) {
            $mid = $rx['message_id'];
            $emoji = $rx['emoji'];
            $uid = (string)$rx['user_id'];
            if (!empty($emoji) && !empty($uid)) {
                if (!isset($reactionsByMsg[$mid])) $reactionsByMsg[$mid] = [];
                if (!isset($reactionsByMsg[$mid][$emoji])) $reactionsByMsg[$mid][$emoji] = [];
                $reactionsByMsg[$mid][$emoji][] = $uid;
            }
        }

        // Format for Vue-Advanced-Chat
        $messages = [];
        foreach ($rows as $row) {
            $senderId = (string)($row['sender_user_id'] ?? '');
            $senderName = $row['user_name'] ?? 'Usuario';
            $senderAvatar = formatUserAvatar($row['user_avatar'], $senderName);
            $msgDate = date('d F Y', strtotime($row['created_at']));
            $msgTime = date('H:i', strtotime($row['created_at']));

            $files = [];
            if (!empty($row['file_url'])) {
                $fileUrl = $row['file_url'];
                $isAudio = ($row['type'] === 'audio');
                $isImage = ($row['type'] === 'image');
                $isVideo = ($row['type'] === 'video');

                $mimeType = 'application/octet-stream';
                if ($isImage) $mimeType = 'image/jpeg';
                elseif ($isVideo) $mimeType = 'video/mp4';
                elseif ($isAudio) $mimeType = 'audio/webm';

                $files[] = [
                    'name' => $row['file_name'] ?: 'Archivo',
                    'size' => 0,
                    'type' => $row['type'],
                    'audio' => $isAudio,
                    'duration' => 0,
                    'url' => $fileUrl,
                    'preview' => $fileUrl
                ];
            }

            // Reply object
            $replyMessage = null;
            if (!empty($row['reply_to_id'])) {
                $rContent = $row['reply_content'];
                if ($row['reply_type'] !== 'text' && empty($rContent)) {
                    $rContent = $row['reply_file_name'] ?: 'Archivo multimedia';
                }
                $replyMessage = [
                    '_id' => (string)$row['reply_to_id'],
                    'content' => $rContent,
                    'senderId' => (string)($row['reply_sender_id'] ?? ''),
                    'files' => []
                ];
            }

            $msgReactions = $reactionsByMsg[$row['id']] ?? (object)[];

            $messages[] = [
                '_id' => (string)$row['id'],
                'indexId' => (int)$row['id'],
                'content' => $row['is_deleted'] ? 'Este mensaje fue eliminado' : ($row['content'] ?? ''),
                'senderId' => $senderId,
                'username' => $senderName,
                'avatar' => $senderAvatar,
                'date' => $msgDate,
                'timestamp' => $msgTime,
                'system' => false,
                'saved' => true,
                'distributed' => true,
                'seen' => true,
                'deleted' => (bool)$row['is_deleted'],
                'files' => $files,
                'reactions' => $msgReactions,
                'replyMessage' => $replyMessage
            ];
        }

        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    // ── 3. SEND MESSAGE ───────────────────────────────────────
    case 'send_message':
        $chat_id = (int)($_POST['chat_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $reply_to_id = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

        if (!$chat_id) {
            echo json_encode(['error' => 'chat_id requerido']);
            exit;
        }

        $type = 'text';
        $file_url = null;
        $file_name = null;

        // Check if there is an uploaded file or audio blob
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['file'];
            $file_name = $f['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $dangerous = ['php', 'phtml', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'cgi'];
            if (in_array($ext, $dangerous)) {
                echo json_encode(['error' => 'Extensión no permitida']);
                exit;
            }

            $mime = mime_content_type($f['tmp_name']);
            if (strpos($mime, 'image/') === 0 || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $type = 'image';
            } elseif (strpos($mime, 'video/') === 0 || in_array($ext, ['mp4', 'webm', 'mov'])) {
                $type = 'video';
            } elseif (strpos($mime, 'audio/') === 0 || in_array($ext, ['mp3', 'wav', 'ogg', 'webm', 'm4a']) || strpos($file_name, 'audio_') === 0) {
                $type = 'audio';
            } else {
                $type = 'file';
            }

            $upload_dir = __DIR__ . '/../../uploads/mensajes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $target_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'dat');
            $target_rel = 'uploads/mensajes/' . $target_name;

            if (move_uploaded_file($f['tmp_name'], __DIR__ . '/../../' . $target_rel)) {
                $file_url = $target_rel;
            }
        }

        if (empty($content) && empty($file_url)) {
            echo json_encode(['error' => 'Mensaje vacío']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO msg_messages (chat_id, sender_user_id, content, type, file_url, file_name, reply_to_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$chat_id, $user_id, $content, $type, $file_url, $file_name, $reply_to_id]);
        $new_msg_id = $db->lastInsertId();

        // Receipts for other participants
        $stmtP = $db->prepare("SELECT user_id FROM msg_participants WHERE chat_id = ? AND user_id != ?");
        $stmtP->execute([$chat_id, $user_id]);
        $otherParticipants = $stmtP->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($otherParticipants)) {
            $stmtR = $db->prepare("INSERT INTO msg_receipts (message_id, user_id, status) VALUES (?, ?, 'delivered')");
            foreach ($otherParticipants as $op_id) {
                $stmtR->execute([$new_msg_id, $op_id]);
            }
        }

        triggerRefresh($chat_id);

        echo json_encode(['success' => true, 'message_id' => $new_msg_id]);
        break;

    // ── 4. REACT TO MESSAGE ───────────────────────────────────
    case 'react':
        $message_id = (int)($_POST['message_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');
        $remove = filter_var($_POST['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Sanitize emoji string: reject 'undefined', null or empty
        if ($message_id && !empty($emoji) && $emoji !== 'undefined') {
            $stmtChat = $db->prepare("SELECT chat_id FROM msg_messages WHERE id = ?");
            $stmtChat->execute([$message_id]);
            $chat_id = $stmtChat->fetchColumn();

            if ($remove) {
                $db->prepare("DELETE FROM msg_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?")
                   ->execute([$message_id, $user_id, $emoji]);
            } else {
                $db->prepare("DELETE FROM msg_reactions WHERE message_id = ? AND user_id = ?")
                   ->execute([$message_id, $user_id]);
                $db->prepare("INSERT INTO msg_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)")
                   ->execute([$message_id, $user_id, $emoji]);
            }

            if ($chat_id) triggerRefresh($chat_id);
        }

        echo json_encode(['success' => true]);
        break;

    // ── 5. DELETE MESSAGE ─────────────────────────────────────
    case 'delete_message':
        $message_id = (int)($_POST['message_id'] ?? 0);
        if ($message_id) {
            $stmtChat = $db->prepare("SELECT chat_id FROM msg_messages WHERE id = ? AND sender_user_id = ?");
            $stmtChat->execute([$message_id, $user_id]);
            $chat_id = $stmtChat->fetchColumn();

            if ($chat_id) {
                $db->prepare("UPDATE msg_messages SET is_deleted = 1 WHERE id = ?")->execute([$message_id]);
                triggerRefresh($chat_id);
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['error' => 'No autorizado o mensaje no encontrado']);
        break;

    // ── 6. EDIT MESSAGE ───────────────────────────────────────
    case 'edit_message':
        $message_id = (int)($_POST['message_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($message_id && $content) {
            $stmtChat = $db->prepare("SELECT chat_id FROM msg_messages WHERE id = ? AND sender_user_id = ?");
            $stmtChat->execute([$message_id, $user_id]);
            $chat_id = $stmtChat->fetchColumn();

            if ($chat_id) {
                $db->prepare("UPDATE msg_messages SET content = ?, is_edited = 1 WHERE id = ?")->execute([$content, $message_id]);
                triggerRefresh($chat_id);
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['error' => 'No autorizado']);
        break;

    // ── 7. SEARCH USERS TO CHAT ───────────────────────────────
    case 'search_users':
        $query = trim($_GET['q'] ?? '');
        $stmt = $db->prepare("
            SELECT id, name, avatar, email 
            FROM users 
            WHERE id != ? AND (name LIKE ? OR email LIKE ?)
            ORDER BY name ASC LIMIT 20
        ");
        $stmt->execute([$user_id, "%$query%", "%$query%"]);
        $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($found as &$u) {
            $u['avatar'] = formatUserAvatar($u['avatar'], $u['name']);
        }
        echo json_encode(['success' => true, 'users' => $found]);
        break;

    // ── 8. START OR GET DIRECT CHAT ───────────────────────────
    case 'start_direct_chat':
        $target_id = (int)($_POST['target_id'] ?? 0);
        if (!$target_id) {
            echo json_encode(['error' => 'target_id requerido']);
            exit;
        }

        // Check if direct chat already exists
        $stmt = $db->prepare("
            SELECT p1.chat_id
            FROM msg_participants p1
            JOIN msg_participants p2 ON p1.chat_id = p2.chat_id
            JOIN msg_chats c ON c.id = p1.chat_id
            WHERE c.type = 'direct'
              AND p1.user_id = ?
              AND p2.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $target_id]);
        $existing_chat = $stmt->fetchColumn();

        if ($existing_chat) {
            echo json_encode(['success' => true, 'chat_id' => $existing_chat]);
        } else {
            $stmt = $db->prepare("INSERT INTO msg_chats (type, created_by) VALUES ('direct', ?)");
            $stmt->execute([$user_id]);
            $chat_id = $db->lastInsertId();

            $stmtP = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'admin'), (?, ?, 'admin')");
            $stmtP->execute([$chat_id, $user_id, $chat_id, $target_id]);

            echo json_encode(['success' => true, 'chat_id' => $chat_id]);
        }
        break;

    // ── 9. CREATE GROUP CHAT ──────────────────────────────────
    case 'create_group':
        $name = trim($_POST['name'] ?? 'Nuevo Grupo');
        $members = json_decode($_POST['members'] ?? '[]', true);
        if (empty($members) || !is_array($members)) {
            $members = [];
        }

        $stmt = $db->prepare("INSERT INTO msg_chats (type, name, created_by) VALUES ('group', ?, ?)");
        $stmt->execute([$name, $user_id]);
        $chat_id = $db->lastInsertId();

        $stmtP = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmtP->execute([$chat_id, $user_id]);

        $stmtAdd = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'member')");
        foreach ($members as $mid) {
            $mid = (int)$mid;
            if ($mid && $mid !== $user_id) {
                $stmtAdd->execute([$chat_id, $mid]);
            }
        }

        echo json_encode(['success' => true, 'chat_id' => $chat_id]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
