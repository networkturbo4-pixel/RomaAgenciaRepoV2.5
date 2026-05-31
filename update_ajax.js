const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, 'modules/chat/ajax.php');
let content = fs.readFileSync(file, 'utf8');

// 1. Add GoogleDriveHelper require
if (!content.includes('GoogleDriveHelper.php')) {
    content = content.replace(
        "require_once __DIR__ . '/cache.php';",
        "require_once __DIR__ . '/cache.php';\nrequire_once __DIR__ . '/../../includes/GoogleDriveHelper.php';"
    );
}

// 2. Add uploadToChatDrive function
if (!content.includes('function uploadToChatDrive')) {
    const fnTarget = "    return ['valid' => true];\n}";
    const fnReplacement = `    return ['valid' => true];
}

/**
 * Sube un archivo al Google Drive en la carpeta CHAT/{canal}
 */
function uploadToChatDrive($localPath, $originalName, $channelName) {
    try {
        $drive = new GoogleDriveHelper();
        if (!$drive->isConfigured()) return null;

        $chatFolders = $drive->listFolders('root');
        $chatFolderId = null;
        if ($chatFolders) {
            foreach ($chatFolders as $f) {
                if (strtoupper($f->getName()) === 'CHAT') {
                    $chatFolderId = $f->getId();
                    break;
                }
            }
        }
        if (!$chatFolderId) return null;

        $channelFolders = $drive->listFolders($chatFolderId);
        $channelFolderId = null;
        $safeName = preg_replace('/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ\\s\\-_]/', '', $channelName);
        if ($channelFolders) {
            foreach ($channelFolders as $f) {
                if ($f->getName() === $safeName) {
                    $channelFolderId = $f->getId();
                    break;
                }
            }
        }
        if (!$channelFolderId) {
            $channelFolderId = $drive->createFolder($safeName, $chatFolderId);
        }
        if (!$channelFolderId) return null;

        return $drive->uploadFile($localPath, $originalName, $channelFolderId);
    } catch (Exception $e) {
        error_log("Error uploading chat file to Drive: " . $e->getMessage());
        return null;
    }
}`;
    content = content.replace(fnTarget, fnReplacement);
}

// 3. Add Google Drive Sync logic inside file upload
const syncLogic = `                $attachmentName = $_FILES['attachment']['name'];
                if ($messageType === 'text' && empty($message)) $messageType = 'file';

                // Sync to Google Drive
                try {
                    $stmtChName = $db->prepare("SELECT name FROM chat_channels WHERE id = ?");
                    $stmtChName->execute([$channelId]);
                    $chName = $stmtChName->fetchColumn() ?: 'General';
                    uploadToChatDrive($uploadDir . $filename, $attachmentName, $chName);
                } catch (Exception $e) {
                    error_log("Drive sync failed: " . $e->getMessage());
                }
            }`;
if (!content.includes('uploadToChatDrive($uploadDir . $filename')) {
    content = content.replace(
        `                $attachmentName = $_FILES['attachment']['name'];\n                if ($messageType === 'text' && empty($message)) $messageType = 'file';\n            }`,
        syncLogic
    );
}

// 4. Update pin_message case for duration
const pinTarget = `        case 'pin_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $db->prepare("INSERT IGNORE INTO chat_pinned_messages (channel_id, message_id, pinned_by) VALUES (?, ?, ?)")->execute([$channelId, $messageId, $userId]);
            echo json_encode(['success' => true]);
            break;`;
const pinReplacement = `        case 'pin_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $duration = $_POST['pin_duration'] ?? 'permanent';
            $expiresAt = null;
            $durations = ['1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800];
            if (isset($durations[$duration])) {
                $expiresAt = date('Y-m-d H:i:s', time() + $durations[$duration]);
            }
            if ($expiresAt) {
                $db->prepare("INSERT IGNORE INTO chat_pinned_messages (channel_id, message_id, pinned_by, expires_at) VALUES (?, ?, ?, ?)")->execute([$channelId, $messageId, $userId, $expiresAt]);
            } else {
                $db->prepare("INSERT IGNORE INTO chat_pinned_messages (channel_id, message_id, pinned_by, expires_at) VALUES (?, ?, ?, NULL)")->execute([$channelId, $messageId, $userId]);
            }
            echo json_encode(['success' => true]);
            break;`;
// Update only the first occurrence
let firstPinIndex = content.indexOf(pinTarget);
if (firstPinIndex !== -1) {
    content = content.substring(0, firstPinIndex) + pinReplacement + content.substring(firstPinIndex + pinTarget.length);
}

// 5. Update get_pinned case to filter out expired
if (!content.includes('p.expires_at IS NULL OR p.expires_at > NOW()')) {
    const getPinnedTarget = `            $stmt = $db->prepare("SELECT m.id, m.message, m.attachment, m.created_at, u.name as user_name FROM chat_pinned_messages p JOIN chat_messages m ON m.id = p.message_id LEFT JOIN users u ON u.id = m.user_id WHERE p.channel_id = ? ORDER BY p.pinned_at DESC");`;
    const getPinnedReplacement = `            $stmt = $db->prepare("SELECT m.id, m.message, m.attachment, m.created_at, u.name as user_name FROM chat_pinned_messages p JOIN chat_messages m ON m.id = p.message_id LEFT JOIN users u ON u.id = m.user_id WHERE p.channel_id = ? AND (p.expires_at IS NULL OR p.expires_at > NOW()) ORDER BY p.pinned_at DESC");`;
    
    // Replace all occurrences just in case
    content = content.replaceAll(getPinnedTarget, getPinnedReplacement);
}

// 6. Add get_channel_media action
if (!content.includes("case 'get_channel_media':")) {
    const mediaBlock = `
        // ── GET CHANNEL MEDIA ──
        case 'get_channel_media':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            if (!$channelId) { echo json_encode(['success' => false]); exit(); }
            
            // Images/Videos
            $stmt = $db->prepare("SELECT id, attachment, created_at, user_id FROM chat_messages WHERE channel_id = ? AND attachment IS NOT NULL AND attachment != '' AND (attachment LIKE '%.jpg' OR attachment LIKE '%.jpeg' OR attachment LIKE '%.png' OR attachment LIKE '%.gif' OR attachment LIKE '%.webp' OR attachment LIKE '%.mp4' OR attachment LIKE '%.webm') ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$channelId]);
            $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Documents
            $stmt = $db->prepare("SELECT id, attachment, attachment_name, created_at, user_id FROM chat_messages WHERE channel_id = ? AND attachment IS NOT NULL AND attachment != '' AND attachment NOT LIKE '%.jpg' AND attachment NOT LIKE '%.jpeg' AND attachment NOT LIKE '%.png' AND attachment NOT LIKE '%.gif' AND attachment NOT LIKE '%.webp' AND attachment NOT LIKE '%.mp4' AND attachment NOT LIKE '%.webm' ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$channelId]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Links from messages
            $stmt = $db->prepare("SELECT id, message, created_at, user_id FROM chat_messages WHERE channel_id = ? AND message REGEXP 'https?://' ORDER BY created_at DESC LIMIT 30");
            $stmt->execute([$channelId]);
            $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pinned messages
            $stmt = $db->prepare("SELECT m.id, m.message, m.attachment, m.created_at, u.name as user_name FROM chat_pinned_messages p JOIN chat_messages m ON m.id = p.message_id LEFT JOIN users u ON u.id = m.user_id WHERE p.channel_id = ? AND (p.expires_at IS NULL OR p.expires_at > NOW()) ORDER BY p.pinned_at DESC");
            $stmt->execute([$channelId]);
            $pinned = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Members
            $stmt = $db->prepare("SELECT u.id, u.name, u.avatar, u.is_online FROM chat_channel_members cm JOIN users u ON u.id = cm.user_id WHERE cm.channel_id = ? ORDER BY u.name");
            $stmt->execute([$channelId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'media' => $media, 'docs' => $docs, 'links' => $links, 'pinned' => $pinned, 'members' => $members]);
            break;
`;
    content = content.replace("case 'get_pinned':", mediaBlock + "\n        case 'get_pinned':");
}

fs.writeFileSync(file, content);
console.log('ajax.php updated successfully.');
