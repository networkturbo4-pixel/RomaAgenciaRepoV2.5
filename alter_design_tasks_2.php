<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("ALTER TABLE design_task_subtasks ADD COLUMN description TEXT NULL");
    echo "Subtasks description added.\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE design_task_attachments ADD COLUMN subtask_id INT NULL");
    echo "Attachments subtask_id added.\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE design_task_attachments ADD COLUMN attachment_type VARCHAR(50) DEFAULT 'general'");
    echo "Attachments attachment_type added.\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

echo "Database updated successfully.";
