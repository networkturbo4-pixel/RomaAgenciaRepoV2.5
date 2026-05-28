<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE projects");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
