<?php
require 'config/database.php';
$db = (new Database())->getConnection();
try {
    $db->exec('ALTER TABLE chat_pinned_messages ADD COLUMN expires_at DATETIME NULL');
    echo 'Column added successfully.';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo 'Column already exists.';
    } else {
        echo $e->getMessage();
    }
}
