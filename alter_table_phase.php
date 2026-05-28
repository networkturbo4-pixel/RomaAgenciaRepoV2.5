<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = "
ALTER TABLE project_months 
ADD COLUMN content_phase VARCHAR(50) DEFAULT 'En Borrador' AFTER status;
";

try {
    $db->exec($sql);
    echo "Tabla project_months actualizada con columna content_phase.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
