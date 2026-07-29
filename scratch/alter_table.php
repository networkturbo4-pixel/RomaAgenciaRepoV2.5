<?php
require 'config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec("ALTER TABLE whiteboards ADD COLUMN access_type ENUM('restricted', 'public') NOT NULL DEFAULT 'restricted'");
    $db->exec("ALTER TABLE whiteboards ADD COLUMN public_role ENUM('viewer', 'editor') NOT NULL DEFAULT 'viewer'");
    echo "OK";
} catch (Exception $e) {
    echo $e->getMessage();
}
