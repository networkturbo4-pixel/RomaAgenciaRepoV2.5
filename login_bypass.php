<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
header('Location: index.php?module=project_board&id=1');
exit;
