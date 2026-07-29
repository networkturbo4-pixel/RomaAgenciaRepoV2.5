<?php
require '../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT month, year FROM project_months LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
