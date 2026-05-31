<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$messages = [];

try {
    // 1. Añadir chat_reactions
    $db->exec("CREATE TABLE IF NOT EXISTS chat_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        emoji VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reaction (message_id, user_id, emoji)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Tabla chat_reactions creada.";

    // 2. Añadir notificaciones (si no existe)
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "✅ Tabla notifications verificada/creada.";

    // 3. Modificar users para is_vip y bg_preference, y spotify_token
    $stmt = $db->query("DESCRIBE users");
    $existingCols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingCols[] = $row['Field'];
    }

    if (!in_array('is_vip', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN is_vip TINYINT(1) DEFAULT 0");
        $messages[] = "✅ Columna is_vip añadida a users.";
    }
    if (!in_array('bg_preference', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN bg_preference VARCHAR(100) DEFAULT 'default'");
        $messages[] = "✅ Columna bg_preference añadida a users.";
    }
    if (!in_array('spotify_token', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN spotify_token TEXT NULL");
        $messages[] = "✅ Columna spotify_token añadida a users.";
    }

} catch (PDOException $e) {
    $messages[] = "❌ Error: " . $e->getMessage();
}

foreach ($messages as $msg) {
    echo $msg . "\n";
}
?>
