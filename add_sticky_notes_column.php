<?php
require_once __DIR__ . '/config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("ALTER TABLE month_posts ADD COLUMN sticky_notes LONGTEXT NULL");
    echo "Column sticky_notes added successfully.\n";
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column sticky_notes already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
