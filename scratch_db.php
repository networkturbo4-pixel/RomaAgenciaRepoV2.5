<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SHOW COLUMNS FROM employee_payments");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
