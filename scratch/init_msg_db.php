<?php
require 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("DROP TABLE IF EXISTS msg_receipts");
    $db->exec("DROP TABLE IF EXISTS msg_messages");
    $db->exec("DROP TABLE IF EXISTS msg_participants");
    $db->exec("DROP TABLE IF EXISTS msg_guests");
    $db->exec("DROP TABLE IF EXISTS msg_chats");

    // 1. msg_chats
    $db->exec("CREATE TABLE msg_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('direct', 'group') NOT NULL DEFAULT 'group',
        name VARCHAR(100) NULL,
        description TEXT NULL,
        drive_folder_id VARCHAR(255) NULL,
        public_link VARCHAR(64) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. msg_guests
    $db->exec("CREATE TABLE msg_guests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. msg_participants
    $db->exec("CREATE TABLE msg_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        user_id INT NULL,
        guest_id INT NULL,
        role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_typing DATETIME NULL,
        FOREIGN KEY (chat_id) REFERENCES msg_chats(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES msg_guests(id) ON DELETE CASCADE
    )");

    // 4. msg_messages
    $db->exec("CREATE TABLE msg_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        sender_user_id INT NULL,
        sender_guest_id INT NULL,
        reply_to_id INT NULL,
        content TEXT NULL,
        type ENUM('text', 'image', 'video', 'audio', 'file') NOT NULL DEFAULT 'text',
        file_url VARCHAR(255) NULL,
        file_name VARCHAR(255) NULL,
        is_edited TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES msg_chats(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_guest_id) REFERENCES msg_guests(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_id) REFERENCES msg_messages(id) ON DELETE SET NULL
    )");

    // 4.5. msg_reactions
    $db->exec("CREATE TABLE msg_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NULL,
        guest_id INT NULL,
        emoji VARCHAR(32) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES msg_messages(id) ON DELETE CASCADE
    )");

    // 5. msg_receipts
    $db->exec("CREATE TABLE msg_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NULL,
        guest_id INT NULL,
        status ENUM('delivered', 'read') NOT NULL DEFAULT 'delivered',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES msg_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES msg_guests(id) ON DELETE CASCADE
    )");

    echo "Base de datos de mensajes creada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
