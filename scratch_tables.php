<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

$stmt = $db->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $db->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
