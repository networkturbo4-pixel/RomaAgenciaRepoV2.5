<?php
$_POST['action'] = 'get_channels';
session_start();
$_SESSION['user_id'] = 1; // Assuming 1
require 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/ajax.php';
?>
