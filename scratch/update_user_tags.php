<?php
require_once 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if chat_tags exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'chat_tags'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN chat_tags VARCHAR(255) NULL");
        echo "Column chat_tags added.\n";
    } else {
        echo "Column chat_tags already exists.\n";
    }

    // Set some tags
    $db->exec("UPDATE users SET chat_tags = 'Admin,Desarrollador' WHERE id = 1");
    $db->exec("UPDATE users SET chat_tags = 'Soporte,VIP' WHERE id = 2");
    
    // Check values
    $stmt = $db->query("SELECT id, name, chat_tags FROM users LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "User {$row['id']} ({$row['name']}): {$row['chat_tags']}\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
