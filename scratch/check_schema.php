<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$res = $db->query('DESCRIBE whiteboards')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
