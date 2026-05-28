<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    $db->exec("ALTER TABLE project_months ADD COLUMN drive_folders_json JSON NULL AFTER folder_finals");
    echo "Column added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
