<?php
$_POST['action'] = 'get_messages';
$_POST['channel_id'] = 5;
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/ajax.php';
