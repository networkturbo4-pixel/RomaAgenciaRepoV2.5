<?php
require_once 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Active participants in a voice room
    $db->exec("CREATE TABLE IF NOT EXISTS chat_voice_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT NOT NULL,
        user_id INT NOT NULL,
        peer_id VARCHAR(100) NOT NULL,
        is_muted BOOLEAN DEFAULT 0,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_ping_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY(channel_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // WebRTC Signaling Table
    // To exchange offers, answers, and ICE candidates
    $db->exec("CREATE TABLE IF NOT EXISTS chat_voice_signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        signal_type VARCHAR(20) NOT NULL, /* 'offer', 'answer', 'ice' */
        payload TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(receiver_id, channel_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Voice room tables created successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
