<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$st = $db->query('DESCRIBE task_subtasks');
print_r($st->fetchAll(PDO::FETCH_ASSOC));
$st2 = $db->query('DESCRIBE design_task_subtasks');
print_r($st2->fetchAll(PDO::FETCH_ASSOC));
