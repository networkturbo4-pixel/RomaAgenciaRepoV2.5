<?php
require 'config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec("ALTER TABLE month_posts ADD COLUMN agenda_tasks JSON NULL");
    echo "Success";
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Already exists";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
