<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW TABLES LIKE 'chat_pinned_messages'");
print_r($stmt->fetchAll());
