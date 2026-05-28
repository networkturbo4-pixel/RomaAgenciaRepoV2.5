<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS `project_attachments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `project_id` INT(11) NOT NULL,
        `type` ENUM('form', 'pdf') NOT NULL,
        `form_id` INT(11) DEFAULT NULL,
        `file_name` VARCHAR(255) DEFAULT NULL,
        `file_path` VARCHAR(500) DEFAULT NULL,
        `drive_file_id` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `project_id` (`project_id`),
        CONSTRAINT `fk_project_attachments` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "project_attachments table created.\n";

    // Add linked_form_id to design_tasks if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM `design_tasks` LIKE 'linked_form_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE `design_tasks` ADD COLUMN `linked_form_id` INT(11) DEFAULT NULL AFTER `external_links`");
        echo "linked_form_id column added to design_tasks.\n";
    } else {
        echo "linked_form_id column already exists in design_tasks.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
