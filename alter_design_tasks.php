<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$db->exec("ALTER TABLE design_tasks MODIFY COLUMN due_date DATETIME");
$db->exec("ALTER TABLE design_tasks ADD COLUMN drive_folder_id VARCHAR(255) NULL");
echo "Table altered successfully.";
