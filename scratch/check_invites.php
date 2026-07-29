<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT * FROM whiteboard_invitations');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
