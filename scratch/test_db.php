<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();

echo "Whiteboards:\n";
$stmt = $db->query("SELECT id, title, created_by, folder_id FROM whiteboards");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "Whiteboard Users:\n";
$stmt = $db->query("SELECT whiteboard_id, user_id, role FROM whiteboard_users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
