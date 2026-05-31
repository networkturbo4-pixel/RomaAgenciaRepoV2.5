<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    $db->exec("ALTER TABLE employee_payments ADD COLUMN bonuses DECIMAL(10,2) DEFAULT 0.00 AFTER extra_hours");
    $db->exec("ALTER TABLE employee_payments ADD COLUMN discounts DECIMAL(10,2) DEFAULT 0.00 AFTER bonuses");
    echo "Columns added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
