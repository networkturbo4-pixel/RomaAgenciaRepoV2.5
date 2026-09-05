<?php
session_start();
$_SESSION['user_id'] = 1;
$_POST = ['id' => 1, 'start_date' => '2026-08-01', 'due_date' => '2026-08-31'];
require 'ajax_update_month.php';

