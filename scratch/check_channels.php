<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$q = $db->query('SELECT id, name, type FROM chat_channels ORDER BY id DESC LIMIT 5');
$res = $q->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
?>
