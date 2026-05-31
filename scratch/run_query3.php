<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');
$stmt = $db->query("SELECT * FROM chat_messages ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
