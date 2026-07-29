<?php
require 'config/database.php';
$db = (new Database())->getConnection();
echo 'Connected to: ' . $db->query('SELECT DATABASE()')->fetchColumn() . "<br>";
try {
    $db->query('SELECT type FROM linktree_links LIMIT 1');
    echo 'Column type EXISTS in linktree_links!';
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
