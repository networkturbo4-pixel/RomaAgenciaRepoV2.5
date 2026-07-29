<?php
session_start();
$_SESSION['user_id'] = 1;
$_GET['q'] = 'chanca';
require 'search_global.php';
