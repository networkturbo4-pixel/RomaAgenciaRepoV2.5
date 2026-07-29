<?php
require_once 'config/database.php';
try {
    $db = (new Database())->getConnection();
    $sql = "ALTER TABLE romita_skills ADD COLUMN allowed_role VARCHAR(50) DEFAULT 'all' AFTER prompt_base";
    $db->exec($sql);
    echo "Column allowed_role added successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
