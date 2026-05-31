<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec("ALTER TABLE employee_payments ADD COLUMN extra_payment DECIMAL(10,2) DEFAULT 0.00 AFTER amount");
    echo "Column extra_payment added successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column extra_payment already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
try {
    $db->exec("ALTER TABLE finance_expenses ADD COLUMN voucher VARCHAR(255) NULL");
    echo "Column voucher added to finance_expenses successfully.\n";
} catch (Exception $e) {
    echo "Voucher check: " . $e->getMessage() . "\n";
}
?>
