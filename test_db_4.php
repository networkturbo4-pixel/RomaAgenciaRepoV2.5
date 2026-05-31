<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT name FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
