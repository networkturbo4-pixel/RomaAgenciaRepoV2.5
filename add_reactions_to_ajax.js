const fs = require('fs');
const path = require('path');

const ajaxFile = path.join(__dirname, 'modules/chat/ajax.php');
let php = fs.readFileSync(ajaxFile, 'utf8');

// We need to inject the reactions fetch logic right after $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
// in get_messages, and after $messages = $stmt->fetchAll(PDO::FETCH_ASSOC); in poll_updates

const fetchReactionsLogic = `

            // --- FETCH REACTIONS FOR MESSAGES ---
            if (!empty($messages)) {
                $msgIds = array_column($messages, 'id');
                $inQuery = implode(',', array_fill(0, count($msgIds), '?'));
                
                // Get all reaction counts
                $stmtReact = $db->prepare("SELECT message_id, emoji, COUNT(*) as count FROM chat_reactions WHERE message_id IN ($inQuery) GROUP BY message_id, emoji");
                $stmtReact->execute($msgIds);
                $allReactions = $stmtReact->fetchAll(PDO::FETCH_ASSOC);
                
                // Get user's own reactions
                $stmtMyReact = $db->prepare("SELECT message_id, emoji FROM chat_reactions WHERE message_id IN ($inQuery) AND user_id = ?");
                $paramsMy = $msgIds;
                $paramsMy[] = $userId;
                $stmtMyReact->execute($paramsMy);
                $myReactions = $stmtMyReact->fetchAll(PDO::FETCH_ASSOC);
                
                // Group by message_id
                $reactionsByMsg = [];
                foreach ($allReactions as $r) {
                    $reactionsByMsg[$r['message_id']][] = ['emoji' => $r['emoji'], 'count' => $r['count']];
                }
                
                $myReactionsByMsg = [];
                foreach ($myReactions as $mr) {
                    $myReactionsByMsg[$mr['message_id']][] = $mr['emoji'];
                }
                
                foreach ($messages as &$msg) {
                    $msg['reactions'] = $reactionsByMsg[$msg['id']] ?? [];
                    $msg['my_reactions'] = $myReactionsByMsg[$msg['id']] ?? [];
                }
            }
`;

// Patch get_messages
if (php.includes('$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));') && !php.includes('FETCH REACTIONS FOR MESSAGES')) {
    php = php.replace('$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));', '$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));' + fetchReactionsLogic);
}

// Patch poll_updates
const pollTarget = '$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);';
if (php.includes(pollTarget)) {
    // replace the first instance inside poll_updates
    const parts = php.split("case 'poll_updates':");
    if (parts.length > 1) {
        let pollPart = parts[1];
        if (pollPart.includes(pollTarget)) {
            pollPart = pollPart.replace(pollTarget, pollTarget + fetchReactionsLogic);
            php = parts[0] + "case 'poll_updates':" + pollPart;
        }
    }
}

fs.writeFileSync(ajaxFile, php);
console.log("Added reactions to ajax.php");
