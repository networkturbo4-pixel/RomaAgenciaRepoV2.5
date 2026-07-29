const fs = require('fs');
let content = fs.readFileSync('modules/chat/ajax.php', 'utf8');

// 1. ADD VOTE_POLL AND TOGGLE_TASK
const widgetsCode = `
        // ── VOTE POLL ──
        case 'vote_poll':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $optionIndex = (int)($_POST['option_index'] ?? 0);
            $allowMultiple = filter_var($_POST['allow_multiple'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Fetch the message card_data
            $stmt = $db->prepare("SELECT card_data FROM chat_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $cardDataStr = $stmt->fetchColumn();
            if (!$cardDataStr) {
                echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado']);
                exit();
            }

            // check if user already voted
            $stmtCheck = $db->prepare("SELECT option_index FROM chat_poll_votes WHERE message_id = ? AND user_id = ?");
            $stmtCheck->execute([$messageId, $userId]);
            $existingVote = $stmtCheck->fetchColumn();

            if ($existingVote !== false) {
                if ($existingVote == $optionIndex) {
                    // Toggle off (remove vote)
                    $db->prepare("DELETE FROM chat_poll_votes WHERE message_id = ? AND user_id = ?")->execute([$messageId, $userId]);
                } else {
                    // Change vote
                    $db->prepare("UPDATE chat_poll_votes SET option_index = ? WHERE message_id = ? AND user_id = ?")->execute([$optionIndex, $messageId, $userId]);
                }
            } else {
                // New vote
                $db->prepare("INSERT INTO chat_poll_votes (message_id, user_id, option_index) VALUES (?, ?, ?)")->execute([$messageId, $userId, $optionIndex]);
            }

            // Get updated poll votes
            $stmtVotes = $db->prepare("
                SELECT option_index, COUNT(*) as count, GROUP_CONCAT(u.name SEPARATOR ', ') as users 
                FROM chat_poll_votes v
                LEFT JOIN users u ON u.id = v.user_id
                WHERE message_id = ?
                GROUP BY option_index
            ");
            $stmtVotes->execute([$messageId]);
            $pollVotes = $stmtVotes->fetchAll(PDO::FETCH_ASSOC);

            // Get my votes
            $stmtMy = $db->prepare("SELECT option_index FROM chat_poll_votes WHERE message_id = ? AND user_id = ?");
            $stmtMy->execute([$messageId, $userId]);
            $myVotesRows = $stmtMy->fetchAll(PDO::FETCH_ASSOC);
            $myVotes = array_column($myVotesRows, 'option_index');

            echo json_encode(['success' => true, 'poll_votes' => $pollVotes, 'my_votes' => $myVotes]);
            break;

        // ── TOGGLE TASK ──
        case 'toggle_task':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $itemIndex = (int)($_POST['item_index'] ?? 0);

            $stmt = $db->prepare("SELECT card_data FROM chat_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $cardDataStr = $stmt->fetchColumn();
            if ($cardDataStr) {
                $cardData = json_decode($cardDataStr, true);
                if (isset($cardData['items'][$itemIndex])) {
                    $isDone = $cardData['items'][$itemIndex]['completed'] ?? false;
                    $cardData['items'][$itemIndex]['completed'] = !$isDone;
                    $cardData['items'][$itemIndex]['done'] = !$isDone;
                    $newCardData = json_encode($cardData);
                    $db->prepare("UPDATE chat_messages SET card_data = ? WHERE id = ?")->execute([$newCardData, $messageId]);
                    
                    echo json_encode(['success' => true, 'items' => $cardData['items']]);
                    exit();
                }
            }
            echo json_encode(['success' => false, 'error' => 'Error toggling task']);
            break;

        // ── PIN / UNPIN MESSAGES ──
        case 'pin_message':`;

if (!content.includes("case 'vote_poll':")) {
    content = content.replace(/\/\/ ── PIN \/ UNPIN MESSAGES ──\s*case 'pin_message':/, widgetsCode);
}

// 2. ADD GET_CHANNEL
const getChannelCode = `
        // ── GET CHANNEL ──
        case 'get_channel':
            $chId = (int)($_POST['channel_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM chat_channels WHERE id = ?");
            $stmt->execute([$chId]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($channel) {
                $stmtMem = $db->prepare("SELECT user_id FROM chat_channel_members WHERE channel_id = ?");
                $stmtMem->execute([$chId]);
                $members = $stmtMem->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode(['success' => true, 'channel' => $channel, 'members' => $members]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Canal no encontrado']);
            }
            break;

        // ── CREATE CHANNEL ──
        case 'create_channel':`;

if (!content.includes("case 'get_channel':")) {
    content = content.replace(/\/\/ ── CREATE CHANNEL ──\s*case 'create_channel':/, getChannelCode);
}

// 3. UPDATE CREATE_CHANNEL TO SUPPORT UPDATE
content = content.replace(/case 'create_channel':[\s\S]*?break;/g, (match) => {
    return `case 'create_channel':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $members = json_decode($_POST['members'] ?? '[]', true);
            $isPublic = (int)($_POST['is_public'] ?? 0);
            $requiresApproval = (int)($_POST['requires_approval'] ?? 0);
            $isSecret = (int)($_POST['is_secret'] ?? 0);
            $secretPassword = $_POST['secret_password'] ?? '';
            
            if ($isSecret && !empty($secretPassword)) {
                $secretPassword = password_hash($secretPassword, PASSWORD_DEFAULT);
            } else {
                $secretPassword = ''; // Avoid updating to plain text or null incorrectly, handle later
            }

            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
                exit();
            }

            // Handle Avatar Upload
            $avatar = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $avatarName = 'group_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $uploadPath = '../../uploads/chat_avatars/' . $avatarName;
                if (!is_dir('../../uploads/chat_avatars')) {
                    mkdir('../../uploads/chat_avatars', 0777, true);
                }
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                    $avatar = $avatarName;
                }
            }

            if ($channelId > 0) {
                // UPDATE
                if ($avatar) {
                    if ($isSecret && !empty($_POST['secret_password'])) {
                        $stmt = $db->prepare("UPDATE chat_channels SET name=?, description=?, avatar=?, is_public=?, requires_approval=?, is_secret=?, secret_password=? WHERE id=?");
                        $stmt->execute([$name, $description, $avatar, $isPublic, $requiresApproval, $isSecret, $secretPassword, $channelId]);
                    } else {
                        $stmt = $db->prepare("UPDATE chat_channels SET name=?, description=?, avatar=?, is_public=?, requires_approval=?, is_secret=? WHERE id=?");
                        $stmt->execute([$name, $description, $avatar, $isPublic, $requiresApproval, $isSecret, $channelId]);
                    }
                } else {
                    if ($isSecret && !empty($_POST['secret_password'])) {
                        $stmt = $db->prepare("UPDATE chat_channels SET name=?, description=?, is_public=?, requires_approval=?, is_secret=?, secret_password=? WHERE id=?");
                        $stmt->execute([$name, $description, $isPublic, $requiresApproval, $isSecret, $secretPassword, $channelId]);
                    } else {
                        $stmt = $db->prepare("UPDATE chat_channels SET name=?, description=?, is_public=?, requires_approval=?, is_secret=? WHERE id=?");
                        $stmt->execute([$name, $description, $isPublic, $requiresApproval, $isSecret, $channelId]);
                    }
                }
                $chId = $channelId;
                
                // Update members: delete all and re-add to simplify
                $db->prepare("DELETE FROM chat_channel_members WHERE channel_id=? AND user_id != ?")->execute([$chId, $userId]); // Keep creator maybe, or just delete all
                $db->prepare("DELETE FROM chat_channel_members WHERE channel_id=?")->execute([$chId]);
                
                $db->prepare("INSERT INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)")->execute([$chId, $userId]);
                $stmtAdd = $db->prepare("INSERT IGNORE INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)");
                foreach ($members as $mid) {
                    $stmtAdd->execute([$chId, (int)$mid]);
                }
            } else {
                // INSERT
                $stmt = $db->prepare("INSERT INTO chat_channels (name, type, description, avatar, is_public, requires_approval, is_secret, secret_password, created_by) VALUES (?, 'group', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $avatar, $isPublic, $requiresApproval, $isSecret, $secretPassword, $userId]);
                $chId = $db->lastInsertId();

                // Add creator
                $db->prepare("INSERT INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)")->execute([$chId, $userId]);
                // Add selected members
                $stmtAdd = $db->prepare("INSERT IGNORE INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)");
                foreach ($members as $mid) {
                    $stmtAdd->execute([$chId, (int)$mid]);
                }
            }

            echo json_encode(['success' => true, 'channel_id' => $chId]);
            break;`;
});

fs.writeFileSync('modules/chat/ajax.php', content);
console.log("Patched ajax.php master");
