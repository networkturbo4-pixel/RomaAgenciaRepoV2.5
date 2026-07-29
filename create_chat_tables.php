<?php
require_once 'config/database.php';
try {
    $db = (new Database())->getConnection();
    
    // Create romita_chats table
    $sql1 = "CREATE TABLE IF NOT EXISTS romita_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) DEFAULT 'Nuevo Chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )";
    $db->exec($sql1);
    
    // Create romita_messages table
    $sql2 = "CREATE TABLE IF NOT EXISTS romita_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        role ENUM('user', 'assistant') NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (chat_id),
        FOREIGN KEY (chat_id) REFERENCES romita_chats(id) ON DELETE CASCADE
    )";
    $db->exec($sql2);
    
    echo "Chat tables created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
