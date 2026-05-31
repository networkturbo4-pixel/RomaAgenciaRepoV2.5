const fs = require('fs');
const path = require('path');

const ajaxFile = path.join(__dirname, 'modules/chat/ajax.php');
let php = fs.readFileSync(ajaxFile, 'utf8');

const reactionCode = `
        case 'toggle_reaction':
            $msgId = (int)($_POST['message_id'] ?? 0);
            $emoji = $_POST['emoji'] ?? '';
            $userId = $_SESSION['user_id'];

            if ($msgId && $emoji) {
                // Check if user already has a reaction on this message
                $stmt = $db->prepare("SELECT id, emoji FROM chat_reactions WHERE message_id = ? AND user_id = ?");
                $stmt->execute([$msgId, $userId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    if ($existing['emoji'] === $emoji) {
                        // Toggle off
                        $stmt = $db->prepare("DELETE FROM chat_reactions WHERE id = ?");
                        $stmt->execute([$existing['id']]);
                    } else {
                        // Change reaction
                        $stmt = $db->prepare("UPDATE chat_reactions SET emoji = ? WHERE id = ?");
                        $stmt->execute([$emoji, $existing['id']]);
                    }
                } else {
                    // New reaction
                    $stmt = $db->prepare("INSERT INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)");
                    $stmt->execute([$msgId, $userId, $emoji]);
                }

                // Fetch updated reactions
                $stmt = $db->prepare("SELECT emoji, COUNT(*) as count FROM chat_reactions WHERE message_id = ? GROUP BY emoji");
                $stmt->execute([$msgId]);
                $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $db->prepare("SELECT emoji FROM chat_reactions WHERE message_id = ? AND user_id = ?");
                $stmt->execute([$msgId, $userId]);
                $my_reactions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                echo json_encode(['success' => true, 'reactions' => $reactions, 'my_reactions' => $my_reactions]);
            } else {
                echo json_encode(['error' => 'Faltan datos']);
            }
            break;

        default:`;

if (!php.includes("case 'toggle_reaction':")) {
    php = php.replace('default:', reactionCode);
    fs.writeFileSync(ajaxFile, php);
    console.log("Added toggle_reaction to ajax.php");
} else {
    console.log("toggle_reaction already exists");
}
