<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW CREATE TABLE chat_pinned_messages");
print_r($stmt->fetch());
