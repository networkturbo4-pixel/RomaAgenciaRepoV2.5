<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');
$db->exec("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','card','file','poll','task') NOT NULL DEFAULT 'text'");
echo "Table altered successfully.";
?>
