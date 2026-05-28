<?php
/**
 * Setup Chat Module Tables & Update Users Table
 * Run this file once to create all necessary tables.
 */

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$messages = [];

try {
    // 1. Update users table - add avatar, phone, username
    $existingCols = [];
    $stmt = $db->query("DESCRIBE users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingCols[] = $row['Field'];
    }

    if (!in_array('avatar', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) NULL AFTER password");
        $messages[] = "✅ Added 'avatar' column to users table";
    } else {
        $messages[] = "⏭️ 'avatar' column already exists in users table";
    }

    if (!in_array('phone', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL AFTER avatar");
        $messages[] = "✅ Added 'phone' column to users table";
    } else {
        $messages[] = "⏭️ 'phone' column already exists in users table";
    }

    if (!in_array('username', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER name");
        $messages[] = "✅ Added 'username' column to users table";
    } else {
        $messages[] = "⏭️ 'username' column already exists in users table";
    }

    // 2. Create chat_channels table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('group','direct') NOT NULL DEFAULT 'group',
        description TEXT NULL,
        public_token VARCHAR(64) NULL,
        allow_guest_write TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Created chat_channels table";

    // 3. Create chat_channel_members table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_channel_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_read_at TIMESTAMP NULL,
        FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member (channel_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Created chat_channel_members table";

    // 4. Create chat_messages table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT NOT NULL,
        user_id INT NULL,
        guest_name VARCHAR(100) NULL,
        message TEXT NULL,
        message_type ENUM('text','card','file') NOT NULL DEFAULT 'text',
        card_data JSON NULL,
        attachment VARCHAR(500) NULL,
        attachment_name VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
        INDEX idx_channel_created (channel_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Created chat_messages table";

    // 5. Create chat_user_status table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_user_status (
        user_id INT PRIMARY KEY,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Created chat_user_status table";

    // 6. Create push_subscriptions table
    $db->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth_token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Created push_subscriptions table";

    // 7. Create default #General channel if it doesn't exist
    $stmt = $db->query("SELECT id FROM chat_channels WHERE name = 'General' AND type = 'group' LIMIT 1");
    $generalChannel = $stmt->fetch();

    if (!$generalChannel) {
        // Get first admin user
        $stmtAdmin = $db->query("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
        $admin = $stmtAdmin->fetch();
        $adminId = $admin ? $admin['id'] : 1;

        $db->prepare("INSERT INTO chat_channels (name, type, description, created_by) VALUES (?, ?, ?, ?)")
           ->execute(['General', 'group', 'Canal general del equipo. Todos los miembros están aquí.', $adminId]);
        $channelId = $db->lastInsertId();

        // Add all existing users to General channel
        $stmtUsers = $db->query("SELECT id FROM users");
        $stmtInsert = $db->prepare("INSERT IGNORE INTO chat_channel_members (channel_id, user_id) VALUES (?, ?)");
        while ($user = $stmtUsers->fetch()) {
            $stmtInsert->execute([$channelId, $user['id']]);
        }
        $messages[] = "✅ Created #General channel with all users";
    } else {
        $messages[] = "⏭️ #General channel already exists";
    }

    // 8. Initialize chat_user_status for all users
    $db->exec("INSERT IGNORE INTO chat_user_status (user_id) SELECT id FROM users");
    $messages[] = "✅ Initialized user status records";

} catch (PDOException $e) {
    $messages[] = "❌ Error: " . $e->getMessage();
}

// Output results
echo "<html><head><title>Chat Setup</title><style>
    body { font-family: 'Inter', system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 1rem; background: #0f172a; color: #e2e8f0; }
    h1 { color: #60a5fa; } .msg { padding: 0.5rem 0; border-bottom: 1px solid #1e293b; }
    a { color: #60a5fa; }
</style></head><body>";
echo "<h1>🗨️ Chat Module Setup</h1>";
foreach ($messages as $msg) {
    echo "<div class='msg'>$msg</div>";
}
echo "<br><a href='index.php?module=chat&action=index'>→ Go to Chat</a>";
echo "</body></html>";
