<?php
require_once 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$database = new Database();
$db = $database->getConnection();
print_r($db->query('SELECT * FROM users LIMIT 1')->fetchAll(PDO::FETCH_ASSOC));
