<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('DESCRIBE chat_channel_members');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
