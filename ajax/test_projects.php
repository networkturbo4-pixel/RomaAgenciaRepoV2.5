<?php
require '../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('DESCRIBE projects');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $db->query('DESCRIBE work_orders');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $db->query('DESCRIBE project_months');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
