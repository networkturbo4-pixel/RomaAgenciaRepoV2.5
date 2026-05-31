<?php
require 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW TABLES LIKE 'notifications'");
if ($stmt->fetch()) echo "notifications exists\n"; else echo "notifications missing\n";
?>
