<?php
$_POST['action'] = 'edit_message';
$_POST['message_id'] = 104; // Use an existing text message ID from run_query3
$_POST['message'] = 'Excelente editado';
// Mock session
session_start();
$_SESSION['user_id'] = 1;
require_once 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
