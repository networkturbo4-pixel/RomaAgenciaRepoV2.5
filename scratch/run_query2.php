<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');
$stmt = $db->query("DESCRIBE chat_messages");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
