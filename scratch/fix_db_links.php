<?php
require_once 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();

// First get all the messages that contain the bad link
$stmt = $db->query("SELECT id, message FROM chat_messages WHERE msg_type = 'card' AND message LIKE '%module=design_tasks&action=board%'");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($messages as $msg) {
    // Decode the JSON message
    $message = json_decode($msg['message'], true);
    if ($message && isset($message['link'])) {
        // Fix the link by replacing the bad part, but we also want to add open_task
        // Let's just remove &action=board, and append &open_task={card_id}
        // Actually, we can just replace action=board with open_task={card_id}
        if (strpos($message['link'], 'action=board') !== false) {
            $message['link'] = str_replace('action=board', 'open_task=' . $message['card_id'], $message['link']);
            
            // Update the DB
            $updateStmt = $db->prepare("UPDATE chat_messages SET message = ? WHERE id = ?");
            $updateStmt->execute([json_encode($message, JSON_UNESCAPED_UNICODE), $msg['id']]);
            $updated++;
        }
    }
}

echo "Updated $updated messages.";
