<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec('ALTER TABLE project_attachments CHANGE form_id submission_id INT(11) DEFAULT NULL;');
    $db->exec('ALTER TABLE design_tasks CHANGE linked_form_id linked_submission_id INT(11) DEFAULT NULL;');
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
