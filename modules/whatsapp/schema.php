<?php
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // wa_sessions
    $db->exec("CREATE TABLE IF NOT EXISTS wa_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NULL,
        status ENUM('disconnected', 'qr', 'connected') DEFAULT 'disconnected',
        connected_by INT NULL,
        connected_at DATETIME NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (connected_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Initialize one row if not exists
    $stmt = $db->query("SELECT id FROM wa_sessions LIMIT 1");
    if ($stmt->rowCount() == 0) {
        $db->exec("INSERT INTO wa_sessions (status) VALUES ('disconnected')");
    }

    // wa_contacts
    $db->exec("CREATE TABLE IF NOT EXISTS wa_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jid VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        avatar_url VARCHAR(500) NULL,
        is_group TINYINT(1) DEFAULT 0,
        last_message_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // wa_messages
    $db->exec("CREATE TABLE IF NOT EXISTS wa_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wa_message_id VARCHAR(100) UNIQUE NOT NULL,
        contact_id INT NOT NULL,
        direction ENUM('in', 'out') NOT NULL,
        sent_by_user INT NULL,
        message_type ENUM('text', 'image', 'audio', 'video', 'document', 'sticker') DEFAULT 'text',
        body TEXT NULL,
        media_url VARCHAR(500) NULL,
        media_mime VARCHAR(50) NULL,
        media_filename VARCHAR(200) NULL,
        quoted_msg_id VARCHAR(100) NULL,
        status ENUM('sent', 'delivered', 'read') DEFAULT 'sent',
        timestamp DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES wa_contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (sent_by_user) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // wa_chat_assignments
    $db->exec("CREATE TABLE IF NOT EXISTS wa_chat_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES wa_contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (contact_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // wa_labels
    $db->exec("CREATE TABLE IF NOT EXISTS wa_labels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        color VARCHAR(7) DEFAULT '#4f46e5',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // wa_contact_labels
    $db->exec("CREATE TABLE IF NOT EXISTS wa_contact_labels (
        contact_id INT NOT NULL,
        label_id INT NOT NULL,
        PRIMARY KEY (contact_id, label_id),
        FOREIGN KEY (contact_id) REFERENCES wa_contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (label_id) REFERENCES wa_labels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Insert some default labels if empty
    $stmt = $db->query("SELECT id FROM wa_labels LIMIT 1");
    if ($stmt->rowCount() == 0) {
        $db->exec("INSERT INTO wa_labels (name, color) VALUES 
            ('Nuevo Cliente', '#10b981'),
            ('Soporte', '#ef4444'),
            ('Cotización', '#f59e0b'),
            ('Importante', '#6366f1')
        ");
    }

    echo "WhatsApp schema created/updated successfully.\n";

} catch (PDOException $e) {
    echo "Error creating WhatsApp schema: " . $e->getMessage() . "\n";
}
