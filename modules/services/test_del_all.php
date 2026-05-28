<?php
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once '../../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT id FROM services');
while ($row = $stmt->fetch()) {
    $_POST['id'] = $row['id'];
    echo "Deleting " . $row['id'] . ": ";
    include('ajax_delete_service.php');
    echo "\n";
}
?>
