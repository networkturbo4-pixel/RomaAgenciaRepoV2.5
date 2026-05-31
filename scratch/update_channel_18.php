<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$db->exec("UPDATE chat_channels SET type = 'video' WHERE id = 18");
echo "Channel 18 updated to video.\n";
?>
