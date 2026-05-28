<?php
require_once __DIR__ . '/config/database.php';
$db = (new Database())->getConnection();

// Add drawing_data column if it doesn't exist
try {
    $db->exec("ALTER TABLE month_posts ADD COLUMN drawing_data LONGTEXT NULL");
    echo "Column drawing_data added successfully.\n";
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column drawing_data already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
