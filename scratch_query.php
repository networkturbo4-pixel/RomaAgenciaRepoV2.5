<?php
require 'c:/xampp/htdocs/CESARMENDOZA/includes/db.php';
$stmt = $db->query('SELECT * FROM reuniones LIMIT 5');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
