<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec("ALTER TABLE finance_expenses ADD COLUMN voucher VARCHAR(255) DEFAULT NULL AFTER categoria");
    echo "Done - voucher column added\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
