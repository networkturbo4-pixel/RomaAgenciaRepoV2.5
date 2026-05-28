<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = "
ALTER TABLE project_months 
ADD COLUMN start_date DATE NULL AFTER year,
ADD COLUMN folder_references VARCHAR(255) NULL AFTER folder_link,
ADD COLUMN folder_editables VARCHAR(255) NULL AFTER folder_references,
ADD COLUMN folder_finals VARCHAR(255) NULL AFTER folder_editables;
";

try {
    $db->exec($sql);
    echo "Tabla project_months actualizada con nuevas columnas.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
