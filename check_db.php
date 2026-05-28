<?php
require_once 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE month_posts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
