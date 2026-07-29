<?php
require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
$stmt = $db->query("SHOW CREATE TABLE employees");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
