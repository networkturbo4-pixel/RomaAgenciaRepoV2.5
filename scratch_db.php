<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$tables = ['tasks', 'task_subtasks', 'users'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $stmt = $db->query("DESCRIBE $t");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
