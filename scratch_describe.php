<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "QUOTE_ITEMS:\n";
try {
    $stmt = $db->query('DESCRIBE quote_items');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

echo "QUOTE_GANTT_TASKS:\n";
try {
    $stmt = $db->query('DESCRIBE quote_gantt_tasks');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) { echo $e->getMessage() . "\n"; }
