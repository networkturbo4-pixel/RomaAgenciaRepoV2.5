<?php session_start(); $_SESSION['user_id'] = 1; $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST['id'] = 1; include('ajax_delete_service.php'); ?>
