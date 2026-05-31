const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, 'modules/chat/ajax.php');
let content = fs.readFileSync(file, 'utf8');

// 1. Add 'is_pinned' to get_channels query
const getChanTarget = 'SELECT c.*,';
const getChanReplacement = 'SELECT c.*, cm.is_pinned,';
if (!content.includes('cm.is_pinned')) {
    content = content.replace(getChanTarget, getChanReplacement);
}

// 2. Order by is_pinned first in get_channels
const orderTarget = 'ORDER BY last_message_at DESC, c.created_at DESC';
const orderReplacement = 'ORDER BY cm.is_pinned DESC, last_message_at DESC, c.created_at DESC';
if (!content.includes('ORDER BY cm.is_pinned DESC')) {
    content = content.replace(orderTarget, orderReplacement);
}

// 3. Add channel_action logic
const channelActionLogic = `
        // ── CHANNEL ACTION (PIN / UNPIN) ──
        case 'channel_action':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            if ($channelId && in_array($type, ['pin', 'unpin'])) {
                $isPinned = $type === 'pin' ? 1 : 0;
                $db->prepare("UPDATE chat_channel_members SET is_pinned = ? WHERE channel_id = ? AND user_id = ?")->execute([$isPinned, $channelId, $userId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            break;
`;

if (!content.includes("case 'channel_action':")) {
    content = content.replace("case 'delete_channel':", channelActionLogic + "\n        case 'delete_channel':");
}

fs.writeFileSync(file, content);
console.log('channel_action added to ajax.php');
