<?php
require_once '../config/database.php';
try {
    $db = (new Database())->getConnection();
    // Use IF NOT EXISTS equivalent for column? Or just try adding it
    $db->exec("ALTER TABLE post_comments ADD COLUMN phase VARCHAR(100) DEFAULT 'Parrilla Final';");
    echo "Columna agregada correctamente.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
