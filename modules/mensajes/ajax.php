<?php
// modules/mensajes/ajax.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';
$pusher_options = array('cluster' => 'us2', 'useTLS' => true);
$pusher = new Pusher\Pusher('b31f38612d61b0285c78', 'c0cabd7a57efdc79f42e', '2156473', $pusher_options);

function triggerChatRefresh($chat_id) {
    global $pusher;
    try {
        $pusher->trigger('chat-' . $chat_id, 'refresh', ['time' => time()]);
    } catch(Exception $e) {}
}

function triggerChatRefreshByMsg($msg_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT chat_id FROM msg_messages WHERE id = ?");
        $stmt->execute([$msg_id]);
        $chat_id = $stmt->fetchColumn();
        if ($chat_id) triggerChatRefresh($chat_id);
    } catch(Exception $e) {}
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$guest_id = $_SESSION['guest_id'] ?? null;

if (!$user_id && !$guest_id) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Auto-create msg_starred table and is_pinned column if they don't exist
try {
    $db->exec("ALTER TABLE msg_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
} catch (Exception $e) { }

// Auto-add task columns to msg_messages
try {
    $db->exec("ALTER TABLE msg_messages MODIFY COLUMN type ENUM('text', 'image', 'video', 'audio', 'file', 'task', 'pendiente', 'whiteboard') NOT NULL DEFAULT 'text'");
    // Delete old test columns if they exist
    try { $db->exec("ALTER TABLE msg_messages DROP COLUMN task_title"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE msg_messages DROP COLUMN task_subtitle"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE msg_messages DROP COLUMN task_due_date"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE msg_messages DROP COLUMN task_status"); } catch(Exception $e) {}
    // Add new JSON data column
    $db->exec("ALTER TABLE msg_messages ADD COLUMN task_data TEXT NULL");
} catch (Exception $e) { }

try {
    $db->exec("ALTER TABLE msg_chats ADD COLUMN avatar VARCHAR(255) NULL");
} catch (Exception $e) { }

try {
    $db->exec("ALTER TABLE msg_participants ADD COLUMN last_typing_at DATETIME NULL");
} catch (Exception $e) { }

try {
    $db->exec("ALTER TABLE msg_receipts ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Exception $e) { }

try {
    $db->exec("CREATE TABLE IF NOT EXISTS msg_starred (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NULL,
        guest_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES msg_messages(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Ignore error if table exists or cannot be created
}

switch ($action) {
    case 'get_global_unread_count':
        if (!$user_id) {
            echo json_encode(['success' => true, 'count' => 0]);
            exit;
        }
        $stmt = $db->prepare("
            SELECT COUNT(m.id) as unread
            FROM msg_messages m
            JOIN msg_participants p ON m.chat_id = p.chat_id
            WHERE p.user_id = ? 
              AND (m.sender_user_id != ? OR m.sender_user_id IS NULL)
              AND NOT EXISTS (
                  SELECT 1 FROM msg_receipts r 
                  WHERE r.message_id = m.id AND r.user_id = ? AND r.status = 'read'
              )
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $count = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => (int)$count]);
        break;

    case 'create_chat':
        if (!$user_id) {
            echo json_encode(['error' => 'Solo usuarios pueden crear chats']);
            exit;
        }
        $name = $_POST['name'] ?? 'Nuevo Chat';
        $public_link = bin2hex(random_bytes(16));
        
        $stmt = $db->prepare("INSERT INTO msg_chats (type, name, public_link, created_by) VALUES ('group', ?, ?, ?)");
        $stmt->execute([$name, $public_link, $user_id]);
        $chat_id = $db->lastInsertId();
        
        // Add creator as admin
        $stmt = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$chat_id, $user_id]);
        
        echo json_encode(['success' => true, 'chat_id' => $chat_id, 'public_link' => $public_link]);
        break;

    case 'get_chats':
        if ($user_id) {
            // Get all chats the user is in
            $stmt = $db->prepare("
                SELECT c.*, 
                       (SELECT content FROM msg_messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT u.name FROM msg_participants p2 JOIN users u ON p2.user_id = u.id WHERE p2.chat_id = c.id AND p2.user_id != ? LIMIT 1) as other_user_name,
                       (SELECT u.avatar FROM msg_participants p2 JOIN users u ON p2.user_id = u.id WHERE p2.chat_id = c.id AND p2.user_id != ? LIMIT 1) as other_user_avatar,
                       (SELECT COUNT(*) FROM msg_messages m WHERE m.chat_id = c.id AND (m.sender_user_id != ? OR m.sender_user_id IS NULL) AND NOT EXISTS (SELECT 1 FROM msg_receipts r WHERE r.message_id = m.id AND r.user_id = ? AND r.status = 'read')) as unread_count
                FROM msg_chats c
                JOIN msg_participants p ON c.id = p.chat_id
                WHERE p.user_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        } else {
            // Get chats guest is in
            $stmt = $db->prepare("
                SELECT c.*,
                       (SELECT content FROM msg_messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       NULL as other_user_name, NULL as other_user_avatar,
                       (SELECT COUNT(*) FROM msg_messages m WHERE m.chat_id = c.id AND (m.sender_guest_id != ? OR m.sender_guest_id IS NULL) AND NOT EXISTS (SELECT 1 FROM msg_receipts r WHERE r.message_id = m.id AND r.guest_id = ? AND r.status = 'read')) as unread_count
                FROM msg_chats c
                JOIN msg_participants p ON c.id = p.chat_id
                WHERE p.guest_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$guest_id, $guest_id, $guest_id]);
        }
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($chats as &$chat) {
            if ($chat['type'] === 'direct' && $user_id) {
                $chat['name'] = $chat['other_user_name'] ?? 'Usuario Desconocido';
                $chat['avatar'] = $chat['other_user_avatar'];
            }
        }
        echo json_encode(['chats' => $chats]);
        break;

    case 'search_users':
        if (!$user_id) {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        $query = $_GET['q'] ?? '';
        $stmt = $db->prepare("SELECT id, name, avatar, email FROM users WHERE id != ? AND name LIKE ? ORDER BY name ASC LIMIT 20");
        $stmt->execute([$user_id, "%$query%"]);
        echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'start_direct_chat':
        if (!$user_id) {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        $target_id = $_POST['target_id'] ?? 0;
        if (!$target_id) {
            echo json_encode(['error' => 'ID de usuario no válido']);
            exit;
        }
        // Check if direct chat already exists
        $stmt = $db->prepare("
            SELECT c.id FROM msg_chats c
            JOIN msg_participants p1 ON c.id = p1.chat_id
            JOIN msg_participants p2 ON c.id = p2.chat_id
            WHERE c.type = 'direct' AND p1.user_id = ? AND p2.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $target_id]);
        $existing_chat = $stmt->fetchColumn();
        
        if ($existing_chat) {
            echo json_encode(['success' => true, 'chat_id' => $existing_chat]);
        } else {
            // Create new
            $stmt = $db->prepare("INSERT INTO msg_chats (type, created_by) VALUES ('direct', ?)");
            $stmt->execute([$user_id]);
            $chat_id = $db->lastInsertId();
            
            $stmt = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'admin'), (?, ?, 'admin')");
            $stmt->execute([$chat_id, $user_id, $chat_id, $target_id]);
            
            echo json_encode(['success' => true, 'chat_id' => $chat_id]);
        }
        break;

    case 'get_info':
        $chat_id = $_GET['chat_id'] ?? 0;
        
        // Verify access
        $stmt = $db->prepare("SELECT id FROM msg_participants WHERE chat_id = ? AND (user_id = ? OR guest_id = ?)");
        $stmt->execute([$chat_id, $user_id, $guest_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'No access']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT type, drive_folder_id, name, avatar FROM msg_chats WHERE id = ?");
        $stmt->execute([$chat_id]);
        $chatInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $folder_id = $chatInfo['drive_folder_id'] ?? null;
        $chat_name = $chatInfo['name'] ?? 'Chat';
        $chat_avatar = $chatInfo['avatar'] ?? null;
        $chat_type = $chatInfo['type'] ?? 'group';
        
        // Get members
        $stmt = $db->prepare("
            SELECT p.role, p.user_id, u.name as uname, u.avatar as uavatar, g.name as gname
            FROM msg_participants p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN msg_guests g ON p.guest_id = g.id
            WHERE p.chat_id = ?
        ");
        $stmt->execute([$chat_id]);
        $membersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $members = [];
        foreach ($membersRaw as $m) {
            $members[] = [
                'name' => $m['uname'] ?? $m['gname'],
                'role' => $m['role']
            ];
            
            // Override chat name and avatar for direct chats
            if ($chat_type === 'direct' && $user_id && $m['user_id'] != $user_id) {
                $chat_name = $m['uname'];
                $chat_avatar = $m['uavatar'];
            }
        }
        
        echo json_encode(['type' => $chat_type, 'drive_folder_id' => $folder_id, 'name' => $chat_name, 'avatar' => $chat_avatar, 'members' => $members]);
        break;

    case 'poll':
        $chat_id = $_GET['chat_id'] ?? 0;
        $last_id = $_GET['last_id'] ?? 0;
        
        // Get new messages
        $stmt = $db->prepare("
            SELECT m.*, u.name as user_name, g.name as guest_name,
                   rm.content as reply_content, ru.name as reply_user_name, rg.name as reply_guest_name, rm.type as reply_type, rm.file_name as reply_file_name,
                   (SELECT 1 FROM msg_starred s WHERE s.message_id = m.id AND (s.user_id = ? OR s.guest_id = ?)) as is_starred
            FROM msg_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN msg_guests g ON m.sender_guest_id = g.id
            LEFT JOIN msg_messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.sender_user_id = ru.id
            LEFT JOIN msg_guests rg ON rm.sender_guest_id = rg.id
            WHERE m.chat_id = ? AND m.id > ?
            ORDER BY m.id ASC
        ");
        $stmt->execute([$user_id, $guest_id, $chat_id, $last_id]);
        $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $messages_formatted = [];
        foreach($new_messages as $m) {
            $reply_sender = $m['reply_user_name'] ?? $m['reply_guest_name'];
            $reply_content = $m['reply_content'];
            if ($m['reply_type'] !== 'text' && empty($reply_content)) {
                $reply_content = $m['reply_file_name'] ?? 'Archivo adjunto';
            }
            
            $messages_formatted[] = [
                'id' => $m['id'],
                'sender_user_id' => $m['sender_user_id'],
                'sender_guest_id' => $m['sender_guest_id'],
                'sender_name' => $m['user_name'] ?? $m['guest_name'],
                'content' => $m['content'],
                'type' => $m['type'],
                'file_url' => $m['file_url'],
                'file_name' => $m['file_name'],
                'created_at' => $m['created_at'],
                'reply_to_id' => $m['reply_to_id'],
                'reply_sender' => $reply_sender,
                'reply_content' => $reply_content,
                'is_edited' => $m['is_edited'] ? true : false,
                'is_deleted' => $m['is_deleted'] ? true : false,
                'is_starred' => $m['is_starred'] ? true : false,
                'is_pinned' => $m['is_pinned'] ? true : false,
                'task_data' => $m['task_data'] ?? null
            ];
        }

        // Mark them as read if they are not from me
        foreach($messages_formatted as $m) {
            if ($m['sender_user_id'] != $user_id || $m['sender_guest_id'] != $guest_id) {
                // Upsert read receipt
                $stmt = $db->prepare("
                    INSERT INTO msg_receipts (message_id, user_id, guest_id, status)
                    VALUES (?, ?, ?, 'read')
                    ON DUPLICATE KEY UPDATE status = 'read'
                ");
                $stmt->execute([$m['id'], $user_id, $guest_id]);
            }
        }
        
        // Get receipt statuses for my messages in this chat
        $stmt = $db->prepare("
            SELECT r.message_id, r.status, r.updated_at 
            FROM msg_receipts r
            JOIN msg_messages m ON r.message_id = m.id
            WHERE m.chat_id = ? AND (m.sender_user_id = ? OR m.sender_guest_id = ?)
        ");
        $stmt->execute([$chat_id, $user_id, $guest_id]);
        $receiptsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Aggregate read count vs total members
        $receipts = [];
        // Group by message_id
        $grouped = [];
        foreach($receiptsRaw as $r) {
            $grouped[$r['message_id']][] = $r;
        }
        
        // Total other members
        $stmt = $db->prepare("SELECT COUNT(*) FROM msg_participants WHERE chat_id = ? AND (user_id != ? OR guest_id != ?)");
        $stmt->execute([$chat_id, $user_id ?? 0, $guest_id ?? 0]);
        $totalOthers = $stmt->fetchColumn();
        
        foreach($grouped as $msgId => $records) {
            $readCount = 0;
            $lastReadTime = '';
            foreach($records as $r) {
                if ($r['status'] === 'read') {
                    $readCount++;
                    if ($r['updated_at'] > $lastReadTime) $lastReadTime = $r['updated_at'];
                }
            }
            if ($totalOthers > 0 && $readCount >= $totalOthers) {
                $receipts[] = ['message_id' => $msgId, 'status' => 'read', 'time' => $lastReadTime];
            } else if (count($records) > 0) {
                $receipts[] = ['message_id' => $msgId, 'status' => 'delivered', 'time' => ''];
            }
        }

        // Get Reactions
        $stmt = $db->prepare("
            SELECT r.message_id, r.emoji, u.name as uname, g.name as gname
            FROM msg_reactions r
            JOIN msg_messages m ON r.message_id = m.id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN msg_guests g ON r.guest_id = g.id
            WHERE m.chat_id = ?
        ");
        $stmt->execute([$chat_id]);
        $reactionsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reactions = [];
        foreach($reactionsRaw as $r) {
            $reactions[$r['message_id']][] = [
                'emoji' => $r['emoji'],
                'name' => $r['uname'] ?? $r['gname']
            ];
        }
        // Get deleted messages to remove them from UI
        $stmt = $db->prepare("SELECT id FROM msg_messages WHERE chat_id = ? AND is_deleted = 1");
        $stmt->execute([$chat_id]);
        $deletedRaw = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get typing status
        $stmt = $db->prepare("
            SELECT u.name as uname, g.name as gname
            FROM msg_participants p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN msg_guests g ON p.guest_id = g.id
            WHERE p.chat_id = ? 
            AND p.last_typing_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
            AND (p.user_id != ? OR p.user_id IS NULL)
            AND (p.guest_id != ? OR p.guest_id IS NULL)
        ");
        $stmt->execute([$chat_id, $user_id ?? 0, $guest_id ?? 0]);
        $typingRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $typing_users = [];
        foreach($typingRaw as $t) {
            $typing_users[] = $t['uname'] ?? $t['gname'];
        }

        echo json_encode([
            'messages' => $messages_formatted,
            'receipts' => $receipts,
            'reactions' => $reactions,
            'deleted' => $deletedRaw,
            'typing' => $typing_users
        ]);
        break;

    case 'typing':
        $chat_id = $_POST['chat_id'] ?? 0;
        if ($chat_id) {
            if ($user_id) {
                $stmt = $db->prepare("UPDATE msg_participants SET last_typing_at = NOW() WHERE chat_id = ? AND user_id = ?");
                $stmt->execute([$chat_id, $user_id]);
            } else if ($guest_id) {
                $stmt = $db->prepare("UPDATE msg_participants SET last_typing_at = NOW() WHERE chat_id = ? AND guest_id = ?");
                $stmt->execute([$chat_id, $guest_id]);
            }
        }
        echo json_encode(['success' => true]);
        break;
    case 'link_preview':
        $url = $_GET['url'] ?? '';
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['error' => 'URL inválida']);
            exit;
        }
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        $html = @file_get_contents($url, false, $context);
        if (!$html) {
            echo json_encode(['error' => 'No se pudo obtener el enlace']);
            exit;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        $title = ''; $description = ''; $image = '';
        $titleNodes = $doc->getElementsByTagName('title');
        if ($titleNodes->length > 0) $title = $titleNodes->item(0)->nodeValue;

        $metas = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            $prop = $meta->getAttribute('property') ?: $meta->getAttribute('name');
            $content = $meta->getAttribute('content');
            if ($prop == 'og:title' && $content) $title = $content;
            if (($prop == 'og:description' || $prop == 'description') && $content && !$description) $description = $content;
            if ($prop == 'og:image' && $content && !$image) $image = $content;
        }

        $parsed_url = parse_url($url);
        $domain = $parsed_url['host'] ?? '';

        echo json_encode([
            'title' => trim($title),
            'description' => trim($description),
            'image' => $image,
            'domain' => $domain
        ]);
        break;

    case 'send_message':
        $chat_id = $_POST['chat_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        
        $type = $_POST['type'] ?? 'text';
        $file_url = null;
        $file_name = null;
        $task_data = $_POST['task_data'] ?? null;
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $file = $_FILES['file'];
            $file_name = $file['name'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (strpos($file_type, 'image/') === 0) {
                $type = 'image';
            } elseif (strpos($file_type, 'video/') === 0 && strpos($file_name, 'audio_') !== 0) {
                $type = 'video';
            } elseif (strpos($file_type, 'audio/') === 0 || strpos($file_name, 'audio_') === 0) {
                $type = 'audio';
            } else {
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'])) $type = 'image';
                elseif (in_array($ext, ['mp4','avi','mov','webm']) && strpos($file_name, 'audio_') !== 0) $type = 'video';
                elseif (in_array($ext, ['mp3','wav','ogg']) || (strpos($file_name, 'audio_') === 0 && $ext === 'webm')) $type = 'audio';
                else $type = 'file';
            }
            
            // Check drive
            $stmt = $db->prepare("SELECT drive_folder_id FROM msg_chats WHERE id = ?");
            $stmt->execute([$chat_id]);
            $folder_id = $stmt->fetchColumn();
            
            if ($folder_id) {
                $drive = new GoogleDriveHelper();
                if ($drive->isConfigured()) {
                    $driveFile = $drive->uploadFile($file['tmp_name'], $file_name, $folder_id);
                    if ($driveFile && isset($driveFile['webViewLink'])) {
                        $file_url = $driveFile['webViewLink'];
                    }
                }
            }
            
            if (!$file_url) {
                $upload_dir = __DIR__ . '/../../uploads/mensajes/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $file_path = 'uploads/mensajes/' . time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $file_name);
                if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../../' . $file_path)) {
                    $file_url = $file_path;
                }
            }
        } elseif (isset($_POST['gif_url']) && !empty($_POST['gif_url'])) {
            $type = 'image';
            $file_url = $_POST['gif_url'];
            $file_name = 'GIF Animado';
        }
        
        $reply_to_id = $_POST['reply_to_id'] ?? null;
        if ($reply_to_id === '') $reply_to_id = null;

        // Handle multiple reference files for 'pendiente' or others
        if (isset($_FILES['references'])) {
            $references = [];
            $upload_dir = __DIR__ . '/../../uploads/mensajes/referencias/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (is_array($_FILES['references']['name'])) {
                $file_count = count($_FILES['references']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['references']['error'][$i] == 0) {
                        $ref_name = $_FILES['references']['name'][$i];
                        $ref_tmp = $_FILES['references']['tmp_name'][$i];
                        $ref_path = 'uploads/mensajes/referencias/' . time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $ref_name);
                        if (move_uploaded_file($ref_tmp, __DIR__ . '/../../' . $ref_path)) {
                            $references[] = [
                                'name' => $ref_name,
                                'url' => $ref_path
                            ];
                        }
                    }
                }
            }
            if (!empty($references) && $task_data) {
                $t_data = json_decode($task_data, true);
                if ($t_data) {
                    $t_data['references'] = $references;
                    $task_data = json_encode($t_data);
                }
            }
        }

        $stmt = $db->prepare("INSERT INTO msg_messages (chat_id, sender_user_id, sender_guest_id, content, type, file_url, file_name, reply_to_id, task_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$chat_id, $user_id, $guest_id, $content, $type, $file_url, $file_name, $reply_to_id, $task_data]);
        $msg_id = $db->lastInsertId();
        
        // Notify others it was delivered (or let them mark read later). We can just insert delivered for all participants
        $stmt = $db->prepare("SELECT user_id, guest_id FROM msg_participants WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($participants as $p) {
            if ($p['user_id'] != $user_id || $p['guest_id'] != $guest_id) {
                $stmt = $db->prepare("INSERT INTO msg_receipts (message_id, user_id, guest_id, status) VALUES (?, ?, ?, 'delivered')");
                $stmt->execute([$msg_id, $p['user_id'], $p['guest_id']]);
            }
        }
        
        triggerChatRefresh($chat_id);
        echo json_encode(['success' => true]);
        break;

    case 'react':
        $message_id = $_POST['message_id'] ?? 0;
        $emoji = $_POST['emoji'] ?? '';
        
        if ($message_id && $emoji) {
            // Upsert reaction
            // First check if user/guest already reacted to this message with any emoji
            // We can delete their old reaction to this message to replace it, or just let them add multiple. WhatsApp replaces it.
            if ($user_id) {
                $db->prepare("DELETE FROM msg_reactions WHERE message_id = ? AND user_id = ?")->execute([$message_id, $user_id]);
                $stmt = $db->prepare("INSERT INTO msg_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)");
                $stmt->execute([$message_id, $user_id, $emoji]);
            } else if ($guest_id) {
                $db->prepare("DELETE FROM msg_reactions WHERE message_id = ? AND guest_id = ?")->execute([$message_id, $guest_id]);
                $stmt = $db->prepare("INSERT INTO msg_reactions (message_id, guest_id, emoji) VALUES (?, ?, ?)");
                $stmt->execute([$message_id, $guest_id, $emoji]);
            }
        }
        if ($message_id) triggerChatRefreshByMsg($message_id);
        echo json_encode(['success' => true]);
        break;

    case 'edit_message':
        $message_id = $_POST['message_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        $task_data = $_POST['task_data'] ?? null;
        
        if ($message_id && ($content || $task_data)) {
            if ($user_id) {
                if ($task_data) {
                    $stmt = $db->prepare("UPDATE msg_messages SET content = ?, task_data = ?, is_edited = 1 WHERE id = ? AND sender_user_id = ?");
                    $stmt->execute([$content, $task_data, $message_id, $user_id]);
                } else {
                    $stmt = $db->prepare("UPDATE msg_messages SET content = ?, is_edited = 1 WHERE id = ? AND sender_user_id = ?");
                    $stmt->execute([$content, $message_id, $user_id]);
                }
            } else if ($guest_id) {
                if ($task_data) {
                    $stmt = $db->prepare("UPDATE msg_messages SET content = ?, task_data = ?, is_edited = 1 WHERE id = ? AND sender_guest_id = ?");
                    $stmt->execute([$content, $task_data, $message_id, $guest_id]);
                } else {
                    $stmt = $db->prepare("UPDATE msg_messages SET content = ?, is_edited = 1 WHERE id = ? AND sender_guest_id = ?");
                    $stmt->execute([$content, $message_id, $guest_id]);
                }
            }
        }
        if ($message_id) triggerChatRefreshByMsg($message_id);
        echo json_encode(['success' => true]);
        break;

    case 'delete_message':
        $message_id = $_POST['message_id'] ?? 0;
        if ($message_id) {
            if ($user_id) {
                $stmt = $db->prepare("UPDATE msg_messages SET is_deleted = 1 WHERE id = ? AND sender_user_id = ?");
                $stmt->execute([$message_id, $user_id]);
            } else if ($guest_id) {
                $stmt = $db->prepare("UPDATE msg_messages SET is_deleted = 1 WHERE id = ? AND sender_guest_id = ?");
                $stmt->execute([$message_id, $guest_id]);
            }
        }
        if ($message_id) triggerChatRefreshByMsg($message_id);
        echo json_encode(['success' => true]);
        break;

    case 'pin_message':
        $message_id = $_POST['message_id'] ?? 0;
        $chat_id = $_POST['chat_id'] ?? 0;
        
        if ($message_id && $chat_id) {
            // Check current status
            $stmt = $db->prepare("SELECT is_pinned FROM msg_messages WHERE id = ? AND chat_id = ?");
            $stmt->execute([$message_id, $chat_id]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($msg) {
                $new_status = $msg['is_pinned'] ? 0 : 1;
                $stmt = $db->prepare("UPDATE msg_messages SET is_pinned = ? WHERE id = ?");
                $stmt->execute([$new_status, $message_id]);
                triggerChatRefresh($chat_id);
                echo json_encode(['success' => true, 'is_pinned' => $new_status]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        break;

    case 'update_task_status':
        $message_id = $_POST['message_id'] ?? 0;
        $subtask_id = $_POST['subtask_id'] ?? null;
        $is_completed = isset($_POST['is_completed']) ? filter_var($_POST['is_completed'], FILTER_VALIDATE_BOOLEAN) : null;
        $mark_all = isset($_POST['mark_all']) ? filter_var($_POST['mark_all'], FILTER_VALIDATE_BOOLEAN) : false;
        
        $stmt = $db->prepare("SELECT chat_id, task_data FROM msg_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['chat_id']) {
            $stmt = $db->prepare("SELECT id FROM msg_participants WHERE chat_id = ? AND (user_id = ? OR guest_id = ?)");
            $stmt->execute([$row['chat_id'], $user_id, $guest_id]);
            if ($stmt->fetch()) {
                if ($row['task_data']) {
                    $task = json_decode($row['task_data'], true);
                    if ($task) {
                        if ($mark_all) {
                            $task['status'] = 'completed';
                            if (isset($task['subtasks'])) {
                                foreach ($task['subtasks'] as &$st) {
                                    $st['completed'] = true;
                                }
                            }
                        } else if ($subtask_id !== null) {
                            $all_completed = true;
                            if (isset($task['subtasks'])) {
                                foreach ($task['subtasks'] as &$st) {
                                    if ($st['id'] == $subtask_id) {
                                        $st['completed'] = $is_completed;
                                    }
                                    if (!$st['completed']) {
                                        $all_completed = false;
                                    }
                                }
                            }
                            $task['status'] = $all_completed ? 'completed' : 'in_progress';
                        }
                        
                        $stmt = $db->prepare("UPDATE msg_messages SET task_data = ? WHERE id = ?");
                        $stmt->execute([json_encode($task), $message_id]);
                        triggerChatRefresh($row['chat_id']);
                        echo json_encode(['success' => true, 'all_completed' => ($task['status'] === 'completed')]);
                        exit;
                    }
                }
            }
        }
        echo json_encode(['error' => 'No autorizado o tarea no encontrada']);
        break;

    case 'forward_message':
        $message_id = $_POST['message_id'] ?? 0;
        $target_chat_id = $_POST['target_chat_id'] ?? 0;
        
        if ($message_id && $target_chat_id) {
            // Retrieve original message
            $stmt = $db->prepare("SELECT * FROM msg_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            $msg = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($msg) {
                // Determine sender
                $s_user = $user_id ?: null;
                $s_guest = $guest_id ?: null;
                
                // Copy the message to the target chat
                $stmt = $db->prepare("INSERT INTO msg_messages (chat_id, sender_user_id, sender_guest_id, type, content, file_url, file_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $target_chat_id,
                    $s_user,
                    $s_guest,
                    $msg['type'],
                    $msg['content'],
                    $msg['file_url'],
                    $msg['file_name']
                ]);
                triggerChatRefresh($target_chat_id);
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        break;

    case 'save_drive_folder':
        $chat_id = $_POST['chat_id'] ?? 0;
        $folder_id = $_POST['folder_id'] ?? '';
        
        $stmt = $db->prepare("UPDATE msg_chats SET drive_folder_id = ? WHERE id = ?");
        if ($stmt->execute([$folder_id, $chat_id])) {
            triggerChatRefresh($chat_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        break;

    case 'delete_chat':
        $chat_id = $_POST['chat_id'] ?? 0;
        if (!$user_id) {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        // Verify user is in chat
        $stmt = $db->prepare("SELECT id FROM msg_participants WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chat_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'No perteneces a este chat']);
            exit;
        }
        
        // Delete dependencies to avoid foreign key issues
        $db->prepare("DELETE FROM msg_receipts WHERE message_id IN (SELECT id FROM msg_messages WHERE chat_id = ?)")->execute([$chat_id]);
        $db->prepare("DELETE FROM msg_messages WHERE chat_id = ?")->execute([$chat_id]);
        $db->prepare("DELETE FROM msg_participants WHERE chat_id = ?")->execute([$chat_id]);
        $db->prepare("DELETE FROM msg_chats WHERE id = ?")->execute([$chat_id]);
        
        echo json_encode(['success' => true]);
        break;

    case 'update_chat_info':
        $chat_id = $_POST['chat_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        
        if (!$user_id) {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT role FROM msg_participants WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chat_id, $user_id]);
        if ($stmt->fetchColumn() !== 'admin') {
            echo json_encode(['error' => 'Solo administradores pueden editar el grupo']);
            exit;
        }

        $avatarUrl = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $file = $_FILES['avatar'];
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $file['name']);
            $upload_dir = __DIR__ . '/../../uploads/mensajes/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_path = 'uploads/mensajes/avatars/' . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../../' . $file_path)) {
                $avatarUrl = $file_path;
            }
        }
        
        if ($avatarUrl) {
            $stmt = $db->prepare("UPDATE msg_chats SET name = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$name, $avatarUrl, $chat_id]);
        } else {
            $stmt = $db->prepare("UPDATE msg_chats SET name = ? WHERE id = ?");
            $stmt->execute([$name, $chat_id]);
        }
        
        echo json_encode(['success' => true, 'avatar' => $avatarUrl]);
        break;

    case 'get_single_message':
        $message_id = $_GET['message_id'] ?? 0;
        $stmt = $db->prepare("
            SELECT m.*, u.name as user_name, g.name as guest_name,
                   rm.content as reply_content, ru.name as reply_user_name, rg.name as reply_guest_name, rm.type as reply_type, rm.file_name as reply_file_name,
                   (SELECT 1 FROM msg_starred s WHERE s.message_id = m.id AND (s.user_id = ? OR s.guest_id = ?)) as is_starred
            FROM msg_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN msg_guests g ON m.sender_guest_id = g.id
            LEFT JOIN msg_messages rm ON m.reply_to_id = rm.id
            LEFT JOIN users ru ON rm.sender_user_id = ru.id
            LEFT JOIN msg_guests rg ON rm.sender_guest_id = rg.id
            WHERE m.id = ?
        ");
        $stmt->execute([$user_id, $guest_id, $message_id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($m) {
            $reply_sender = $m['reply_user_name'] ?? $m['reply_guest_name'];
            $reply_content = $m['reply_content'];
            if ($m['reply_type'] !== 'text' && empty($reply_content)) {
                $reply_content = $m['reply_file_name'] ?? 'Archivo adjunto';
            }
            
            $msg_formatted = [
                'id' => $m['id'],
                'sender_user_id' => $m['sender_user_id'],
                'sender_guest_id' => $m['sender_guest_id'],
                'sender_name' => $m['user_name'] ?? $m['guest_name'],
                'content' => $m['content'],
                'type' => $m['type'],
                'file_url' => $m['file_url'],
                'file_name' => $m['file_name'],
                'created_at' => $m['created_at'],
                'reply_to_id' => $m['reply_to_id'],
                'reply_sender' => $reply_sender,
                'reply_content' => $reply_content,
                'is_edited' => $m['is_edited'] ? true : false,
                'is_deleted' => $m['is_deleted'] ? true : false,
                'is_starred' => $m['is_starred'] ? true : false,
                'is_pinned' => $m['is_pinned'] ? true : false,
                'task_data' => $m['task_data'] ?? null
            ];
            echo json_encode(['success' => true, 'message' => $msg_formatted]);
        } else {
            echo json_encode(['error' => 'Not found']);
        }
        break;

    case 'get_gallery':
        $chat_id = $_GET['chat_id'] ?? 0;
        
        // Images & Video
        $stmt = $db->prepare("SELECT id, file_url, file_name, type, created_at FROM msg_messages WHERE chat_id = ? AND type IN ('image','video') AND file_url IS NOT NULL ORDER BY created_at DESC");
        $stmt->execute([$chat_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Docs (file, audio)
        $stmt = $db->prepare("SELECT id, file_url, file_name, type, created_at FROM msg_messages WHERE chat_id = ? AND type IN ('file','audio') AND file_url IS NOT NULL ORDER BY created_at DESC");
        $stmt->execute([$chat_id]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Links (we extract links from text messages)
        $stmt = $db->prepare("SELECT id, content, created_at FROM msg_messages WHERE chat_id = ? AND type = 'text' AND content LIKE '%http%' ORDER BY created_at DESC");
        $stmt->execute([$chat_id]);
        $linksRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $links = [];
        foreach($linksRaw as $l) {
            preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $l['content'], $match);
            if (!empty($match[0])) {
                foreach($match[0] as $url) {
                    $links[] = [
                        'id' => $l['id'],
                        'url' => $url,
                        'created_at' => $l['created_at']
                    ];
                }
            }
        }
        
        echo json_encode(['images' => $images, 'docs' => $docs, 'links' => $links]);
        break;

    case 'get_available_users':
        $chat_id = $_GET['chat_id'] ?? 0;
        
        // Select users not in the chat
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.avatar 
            FROM users u 
            WHERE u.id NOT IN (
                SELECT user_id FROM msg_participants WHERE chat_id = ? AND user_id IS NOT NULL
            )
        ");
        $stmt->execute([$chat_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['users' => $users]);
        break;

    case 'add_participant':
        $chat_id = $_POST['chat_id'] ?? 0;
        $add_user_id = $_POST['user_id'] ?? 0;
        
        if (!$user_id) {
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        
        // check if inviter is admin
        $stmt = $db->prepare("SELECT role FROM msg_participants WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chat_id, $user_id]);
        if ($stmt->fetchColumn() !== 'admin') {
            echo json_encode(['error' => 'Solo administradores pueden añadir usuarios']);
            exit;
        }
        
        // Insert
        try {
            $stmt = $db->prepare("INSERT INTO msg_participants (chat_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$chat_id, $add_user_id]);
            echo json_encode(['success' => true]);
        } catch(PDOException $e) {
            echo json_encode(['error' => 'Error al añadir usuario (quizás ya está en el grupo)']);
        }
        break;
    case 'link_preview':
        $url = $_GET['url'] ?? '';
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['error' => 'URL inválida']);
            exit;
        }

        // Fetch HTML content
        $html = @file_get_contents($url, false, stream_context_create([
            'http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 5]
        ]));

        if (!$html) {
            echo json_encode(['error' => 'No se pudo obtener la página']);
            exit;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $title = '';
        $description = '';
        $image = '';

        foreach ($doc->getElementsByTagName('meta') as $meta) {
            $property = $meta->getAttribute('property');
            $name = $meta->getAttribute('name');
            $content = $meta->getAttribute('content');

            if ($property === 'og:title' || $name === 'title') $title = $title ?: $content;
            if ($property === 'og:description' || $name === 'description') $description = $description ?: $content;
            if ($property === 'og:image' || $name === 'image') $image = $image ?: $content;
        }

        if (!$title) {
            $titles = $doc->getElementsByTagName('title');
            if ($titles->length > 0) $title = $titles->item(0)->textContent;
        }

        echo json_encode([
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'domain' => parse_url($url, PHP_URL_HOST)
        ]);
        break;

    case 'stream_drive_audio':
        $file_id = $_GET['id'] ?? '';
        if (!$file_id) {
            http_response_code(400);
            exit;
        }
        $drive = new GoogleDriveHelper();
        if ($drive->isConfigured()) {
            $content = $drive->streamFile($file_id);
            if ($content) {
                // Determine mime type, default to audio/webm since we use it for voice notes
                header('Content-Type: audio/webm');
                header('Cache-Control: no-cache');
                echo $content;
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(500);
        }
        break;
        
    case 'star_message':
        $message_id = $_POST['message_id'] ?? null;
        if (!$message_id) {
            echo json_encode(['error' => 'ID de mensaje requerido']);
            break;
        }
        
        $stmt = $db->prepare("SELECT id FROM msg_starred WHERE message_id = ? AND (user_id = ? OR guest_id = ?)");
        $stmt->execute([$message_id, $user_id, $guest_id]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("DELETE FROM msg_starred WHERE message_id = ? AND (user_id = ? OR guest_id = ?)");
            $stmt->execute([$message_id, $user_id, $guest_id]);
            triggerChatRefreshByMsg($message_id);
            echo json_encode(['success' => true, 'action' => 'unstarred']);
        } else {
            $stmt = $db->prepare("INSERT INTO msg_starred (message_id, user_id, guest_id) VALUES (?, ?, ?)");
            $stmt->execute([$message_id, $user_id, $guest_id]);
            triggerChatRefreshByMsg($message_id);
            echo json_encode(['success' => true, 'action' => 'starred']);
        }
        break;
}
