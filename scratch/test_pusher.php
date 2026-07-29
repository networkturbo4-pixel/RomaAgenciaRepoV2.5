<?php
session_start();
$_SESSION['user_id'] = 1;
$_POST['socket_id'] = '1234.1234';
$_POST['channel_name'] = 'presence-whiteboard-1';
require 'c:/xampp/htdocs/CESARMENDOZA/ajax_pusher_auth.php';
