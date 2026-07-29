<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT id, access_type, public_role FROM whiteboards');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
