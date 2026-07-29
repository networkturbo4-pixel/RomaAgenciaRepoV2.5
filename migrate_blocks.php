<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("ALTER TABLE linktree_links ADD COLUMN type VARCHAR(50) DEFAULT 'link'");
    $db->exec("ALTER TABLE linktree_links ADD COLUMN meta_data JSON NULL");
    echo "Migration successful\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
