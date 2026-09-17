<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$tables = ['whiteboards', 'whiteboard_folders', 'whiteboard_users', 'whiteboard_invitations'];
foreach($tables as $t) {
    echo "TABLE $t:\n";
    try {
        $stmt = $db->query("DESCRIBE $t");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  " . $col['Field'] . " - " . $col['Type'] . "\n";
        }
    } catch(Exception $e) {
        echo "Not found\n";
    }
    echo "\n";
}
