<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("DESCRIBE chat_messages");
    echo "--- chat_messages ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $pdo->query("DESCRIBE chat_channels");
    echo "\n--- chat_channels ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
