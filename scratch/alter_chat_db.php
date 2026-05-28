<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check if column exists before adding it
    $stmt = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'reply_to_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN reply_to_id INT(11) NULL DEFAULT NULL AFTER message_type");
        // Add foreign key constraint? No, not strictly necessary. But setting ON DELETE SET NULL is good.
        $pdo->exec("ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_reply FOREIGN KEY (reply_to_id) REFERENCES chat_messages(id) ON DELETE SET NULL");
        echo "Successfully added reply_to_id to chat_messages\n";
    } else {
        echo "reply_to_id already exists in chat_messages\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
