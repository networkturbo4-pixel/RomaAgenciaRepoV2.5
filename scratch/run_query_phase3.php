<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');

try {
    // 1. Add requires_approval and is_secret, password to chat_channels
    $db->exec("ALTER TABLE chat_channels 
        ADD COLUMN requires_approval TINYINT(1) DEFAULT 0 AFTER public_token,
        ADD COLUMN is_secret TINYINT(1) DEFAULT 0 AFTER requires_approval,
        ADD COLUMN secret_password VARCHAR(255) NULL AFTER is_secret;");
        
    // 2. Add status to chat_channel_members
    $db->exec("ALTER TABLE chat_channel_members 
        ADD COLUMN status ENUM('pending', 'approved') DEFAULT 'approved' AFTER last_read_at;");

    // 3. Add tags to users
    $db->exec("ALTER TABLE users 
        ADD COLUMN chat_tags VARCHAR(255) NULL AFTER avatar;");

    echo "Phase 3 DB changes applied successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
