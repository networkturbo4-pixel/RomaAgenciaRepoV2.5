<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT id, name, email FROM users WHERE name LIKE '%Mariana%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
