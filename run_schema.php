<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$sql = file_get_contents('update_design_tasks_schema.sql');
$db->exec($sql);
echo "Schema updated successfully.";
