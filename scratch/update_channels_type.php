<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$q = $db->query("SHOW COLUMNS FROM chat_channels LIKE 'type'");
$row = $q->fetch(PDO::FETCH_ASSOC);
print_r($row);

// Let's modify the enum to include 'voice' and 'video' if it's an ENUM
if (strpos($row['Type'], 'enum') !== false) {
    // Modify to ENUM('direct', 'group', 'voice', 'video')
    $db->exec("ALTER TABLE chat_channels MODIFY COLUMN type ENUM('direct', 'group', 'voice', 'video') NOT NULL DEFAULT 'direct'");
    echo "\nModified type column.\n";
} else {
    // If it's a varchar, we don't need to do anything
    echo "\nType is varchar, no modification needed.\n";
}
?>
