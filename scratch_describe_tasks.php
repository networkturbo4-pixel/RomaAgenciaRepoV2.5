<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

$stmt = $db->query('DESCRIBE design_tasks');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
