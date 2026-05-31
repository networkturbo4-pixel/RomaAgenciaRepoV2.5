<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$voiceCode = <<<EOT

        // ── VOICE ROOMS ──
        case 'voice_join':
            \$channelId = (int)(\$_POST['channel_id'] ?? 0);
            \$peerId = \$_POST['peer_id'] ?? '';
            
            // Check membership
            \$stmt = \$db->prepare("SELECT 1 FROM chat_channel_members WHERE channel_id = ? AND user_id = ?");
            \$stmt->execute([\$channelId, \$userId]);
            if (!\$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit();
            }

            // Clean up stale participants (no ping in 10s)
            \$db->exec("DELETE FROM chat_voice_participants WHERE last_ping_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");

            \$db->prepare("INSERT INTO chat_voice_participants (channel_id, user_id, peer_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE peer_id = ?, last_ping_at = CURRENT_TIMESTAMP")
               ->execute([\$channelId, \$userId, \$peerId, \$peerId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'voice_leave':
            \$channelId = (int)(\$_POST['channel_id'] ?? 0);
            \$db->prepare("DELETE FROM chat_voice_participants WHERE channel_id = ? AND user_id = ?")
               ->execute([\$channelId, \$userId]);
            
            // Clean up signals sent to this user in this channel
            \$db->prepare("DELETE FROM chat_voice_signals WHERE channel_id = ? AND receiver_id = ?")
               ->execute([\$channelId, \$userId]);
               
            echo json_encode(['success' => true]);
            break;

        case 'voice_sync':
            \$channelId = (int)(\$_POST['channel_id'] ?? 0);
            \$lastSignalId = (int)(\$_POST['last_signal_id'] ?? 0);

            // Ping
            \$db->prepare("UPDATE chat_voice_participants SET last_ping_at = CURRENT_TIMESTAMP WHERE channel_id = ? AND user_id = ?")
               ->execute([\$channelId, \$userId]);

            // Clean up stale participants
            \$db->exec("DELETE FROM chat_voice_participants WHERE last_ping_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");

            // Get participants
            \$stmtP = \$db->prepare("SELECT p.user_id, p.peer_id, p.is_muted, u.name, u.avatar FROM chat_voice_participants p JOIN users u ON u.id = p.user_id WHERE p.channel_id = ?");
            \$stmtP->execute([\$channelId]);
            \$participants = \$stmtP->fetchAll(PDO::FETCH_ASSOC);

            // Get new signals
            \$stmtS = \$db->prepare("SELECT * FROM chat_voice_signals WHERE channel_id = ? AND receiver_id = ? AND id > ? ORDER BY id ASC");
            \$stmtS->execute([\$channelId, \$userId, \$lastSignalId]);
            \$signals = \$stmtS->fetchAll(PDO::FETCH_ASSOC);

            // Delete signals older than 1 minute to keep table small
            // We can do this sporadically or right here
            if (rand(1, 10) === 1) {
                \$db->exec("DELETE FROM chat_voice_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            }

            echo json_encode([
                'success' => true,
                'participants' => \$participants,
                'signals' => \$signals
            ]);
            break;

        case 'voice_signal':
            \$channelId = (int)(\$_POST['channel_id'] ?? 0);
            \$receiverId = (int)(\$_POST['receiver_id'] ?? 0);
            \$type = \$_POST['signal_type'] ?? '';
            \$payload = \$_POST['payload'] ?? '';

            \$db->prepare("INSERT INTO chat_voice_signals (channel_id, sender_id, receiver_id, signal_type, payload) VALUES (?, ?, ?, ?, ?)")
               ->execute([\$channelId, \$userId, \$receiverId, \$type, \$payload]);
            
            echo json_encode(['success' => true]);
            break;
EOT;

// I will just use regex to replace the broken code block I injected earlier
$content = preg_replace("/\/\/ ── VOICE ROOMS ──.*break;/s", "", $content);

$search = "default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;";

$replace = $voiceCode . "\n\n        " . $search;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Added voice actions to ajax.php (fixed)\n";
?>
