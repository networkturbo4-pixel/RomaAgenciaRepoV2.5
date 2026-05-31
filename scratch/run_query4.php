<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');

try {
    $db->exec("CREATE TABLE IF NOT EXISTS chat_message_edits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        old_message TEXT NOT NULL,
        edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Table chat_message_edits created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
