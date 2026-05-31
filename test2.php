<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SHOW COLUMNS FROM finance_expenses');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
