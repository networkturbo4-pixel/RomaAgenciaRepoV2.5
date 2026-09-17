<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("ALTER TABLE brand_projects ADD COLUMN start_date DATE NULL AFTER secondary_image");
    echo "start_date added.\n";
} catch(Exception $e) {}

try {
    $db->exec("ALTER TABLE brand_projects DROP COLUMN budget_range");
    echo "budget_range dropped.\n";
} catch(Exception $e) {}

echo "Done.";
?>
