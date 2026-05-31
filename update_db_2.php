<?php
require 'config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec('ALTER TABLE chat_channel_members ADD COLUMN is_pinned TINYINT(1) DEFAULT 0');
    echo 'is_pinned column added successfully.';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo 'Column already exists.';
    } else {
        echo $e->getMessage();
    }
}
