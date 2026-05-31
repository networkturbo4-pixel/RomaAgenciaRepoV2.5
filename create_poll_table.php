<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS chat_poll_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        option_index INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (message_id, user_id, option_index)
    )";
    $db->exec($sql);
    echo "Table chat_poll_votes created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
