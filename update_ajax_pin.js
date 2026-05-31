const fs = require('fs');
const path = require('path');

const ajaxFile = path.join(__dirname, 'modules/chat/ajax.php');
let php = fs.readFileSync(ajaxFile, 'utf8');

const newCode = `        // ── PIN / UNPIN MESSAGES ──
        case 'pin_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $duration = $_POST['pin_duration'] ?? 'permanent';
            
            $stmt = $db->prepare("SELECT id FROM chat_messages WHERE id = ? AND channel_id = ?");
            $stmt->execute([$messageId, $channelId]);
            if ($stmt->fetch()) {
                $expiresAt = null;
                $durations = ['1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800];
                if (isset($durations[$duration])) {
                    $expiresAt = date('Y-m-d H:i:s', time() + $durations[$duration]);
                }
                
                // Note: chat_pinned_messages needs a UNIQUE constraint on (channel_id, message_id) or we just delete first
                $db->prepare("DELETE FROM chat_pinned_messages WHERE channel_id = ? AND message_id = ?")->execute([$channelId, $messageId]);
                
                if ($expiresAt) {
                    $db->prepare("INSERT INTO chat_pinned_messages (channel_id, message_id, pinned_by, expires_at) VALUES (?, ?, ?, ?)")->execute([$channelId, $messageId, $userId, $expiresAt]);
                } else {
                    $db->prepare("INSERT INTO chat_pinned_messages (channel_id, message_id, pinned_by, expires_at) VALUES (?, ?, ?, NULL)")->execute([$channelId, $messageId, $userId]);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado']);
            }
            break;

        case 'unpin_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $db->prepare("DELETE FROM chat_pinned_messages WHERE channel_id = ? AND message_id = ?")->execute([$channelId, $messageId]);
            echo json_encode(['success' => true]);
            break;

        case 'get_pinned_messages':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $db->prepare("DELETE FROM chat_pinned_messages WHERE expires_at IS NOT NULL AND expires_at < NOW()")->execute();
            
            $stmt = $db->prepare("SELECT p.id as pin_id, p.message_id, m.message, m.attachment, m.attachment_name, u.name as pinned_by_name, p.expires_at 
                                  FROM chat_pinned_messages p 
                                  JOIN chat_messages m ON p.message_id = m.id 
                                  LEFT JOIN users u ON p.pinned_by = u.id 
                                  WHERE p.channel_id = ? 
                                  ORDER BY p.pinned_at DESC");
            $stmt->execute([$channelId]);
            echo json_encode(['success' => true, 'pinned' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:`;

php = php.replace('default:', newCode);
fs.writeFileSync(ajaxFile, php);
console.log('ajax.php updated');
