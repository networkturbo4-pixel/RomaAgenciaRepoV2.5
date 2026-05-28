<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = "
ALTER TABLE month_posts
    MODIFY COLUMN post_date DATETIME NULL,
    MODIFY COLUMN platform VARCHAR(255) NULL,
    ADD COLUMN post_type VARCHAR(100) NULL,
    ADD COLUMN end_date DATETIME NULL,
    ADD COLUMN periodicity VARCHAR(100) NULL,
    ADD COLUMN reminder VARCHAR(100) NULL,
    ADD COLUMN formats JSON NULL,
    ADD COLUMN design_brief TEXT NULL,
    ADD COLUMN visual_references JSON NULL,
    ADD COLUMN variations JSON NULL,
    ADD COLUMN drive_images JSON NULL;
";

try {
    $db->exec($sql);
    echo "Columnas añadidas a month_posts con éxito.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
