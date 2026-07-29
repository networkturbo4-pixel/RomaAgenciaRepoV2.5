<?php
require 'config/database.php';
$db = (new Database())->getConnection();

// Check current ENUM
$stmt = $db->query('SHOW COLUMNS FROM msg_messages WHERE Field="type"');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Current type definition: " . $row['Type'] . "\n";

// Check what the last few messages have for type
$stmt = $db->query('SELECT id, type, LEFT(content, 60) as content_preview FROM msg_messages ORDER BY id DESC LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID={$r['id']} type={$r['type']} content={$r['content_preview']}\n";
}
