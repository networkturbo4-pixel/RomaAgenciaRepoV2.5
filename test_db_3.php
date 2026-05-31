<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW CREATE TABLE chat_channel_members");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
