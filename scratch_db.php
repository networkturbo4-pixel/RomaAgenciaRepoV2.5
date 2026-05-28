<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$res = $db->query('SELECT concept FROM month_posts')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
