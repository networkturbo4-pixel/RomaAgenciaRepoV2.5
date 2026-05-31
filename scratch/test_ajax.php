<?php
$_POST['action'] = 'send_message';
$_POST['channel_id'] = 15;
$_POST['message_type'] = 'task';
$_POST['message'] = '';
$_POST['card_data'] = '{"title":"sdadasd","items":[{"id":0,"text":"asdasd","done":false,"user_id":null}]}';
// Mock session
session_start();
$_SESSION['user_id'] = 1;
require_once 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
