<?php
require 'config/database.php';
$db = (new Database())->getConnection();

try {
    // First check current state
    $stmt = $db->query('SHOW COLUMNS FROM msg_messages WHERE Field="type"');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "BEFORE: " . $row['Type'] . "\n";
    
    // Try using PDO directly with query instead of exec
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->query("ALTER TABLE `msg_messages` MODIFY `type` ENUM('text','image','video','audio','file','task','pendiente') NOT NULL DEFAULT 'text'");
    
    // Verify
    $stmt = $db->query('SHOW COLUMNS FROM msg_messages WHERE Field="type"');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "AFTER: " . $row['Type'] . "\n";
    
    if (strpos($row['Type'], 'pendiente') !== false) {
        // Fix existing pendiente messages
        $db->query("UPDATE msg_messages SET type='pendiente' WHERE content LIKE '%Pendiente:%' AND (type='' OR type='text')");
        echo "Fixed pendiente messages\n";
    } else {
        echo "ENUM still not updated, trying raw SQL via mysqli...\n";
        // Fallback: try mysqli
        $mysqli = new mysqli('localhost', 'root', '', 'sistema_roma');
        if ($mysqli->connect_error) {
            echo "mysqli connection error: " . $mysqli->connect_error . "\n";
        } else {
            $result = $mysqli->query("ALTER TABLE `msg_messages` MODIFY `type` ENUM('text','image','video','audio','file','task','pendiente') NOT NULL DEFAULT 'text'");
            echo "mysqli result: " . var_export($result, true) . "\n";
            if ($mysqli->error) echo "mysqli error: " . $mysqli->error . "\n";
            
            // Verify via mysqli
            $res = $mysqli->query('SHOW COLUMNS FROM msg_messages WHERE Field="type"');
            $r = $res->fetch_assoc();
            echo "AFTER mysqli: " . $r['Type'] . "\n";
            
            if (strpos($r['Type'], 'pendiente') !== false) {
                $mysqli->query("UPDATE msg_messages SET type='pendiente' WHERE content LIKE '%Pendiente:%' AND (type='' OR type='text')");
                echo "Fixed " . $mysqli->affected_rows . " pendiente messages via mysqli\n";
            }
            $mysqli->close();
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
