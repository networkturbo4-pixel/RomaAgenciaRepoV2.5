<?php
require '../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('DESCRIBE quotes');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
