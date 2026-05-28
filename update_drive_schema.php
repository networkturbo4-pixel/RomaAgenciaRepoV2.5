<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

$queries = [
    "ALTER TABLE projects ADD COLUMN drive_folder_id VARCHAR(255) NULL",
    "ALTER TABLE projects ADD COLUMN drive_folder_link VARCHAR(500) NULL",
    "ALTER TABLE project_months ADD COLUMN drive_folder_id VARCHAR(255) NULL",
    "ALTER TABLE project_months ADD COLUMN drive_folder_link VARCHAR(500) NULL",
    "ALTER TABLE month_posts ADD COLUMN drive_file_id VARCHAR(255) NULL"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "Success: $q\n";
    } catch (PDOException $e) {
        echo "Error or already exists: " . $e->getMessage() . "\n";
    }
}
echo "Schema updated for Drive.\n";
