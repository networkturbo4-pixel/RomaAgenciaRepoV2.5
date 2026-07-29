<?php
// Mock session to avoid header redirect
session_start();
$_SESSION['user_id'] = 1;

require 'includes/header.php';
try {
    global $db;
    $stmtTags = $db->prepare("SELECT tags FROM whiteboards WHERE tags IS NOT NULL AND tags != '' AND tags != '[]'");
    $stmtTags->execute();
    echo "Success\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
