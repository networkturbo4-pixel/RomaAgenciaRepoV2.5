<?php
// create_forms_schema.php — Run once to create form_templates and form_submissions tables
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    // form_templates
    $db->exec("CREATE TABLE IF NOT EXISTS `form_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `fields_json` LONGTEXT DEFAULT NULL,
        `settings_json` LONGTEXT DEFAULT NULL,
        `public_token` VARCHAR(64) DEFAULT NULL,
        `drive_folder_id` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','draft','archived') DEFAULT 'draft',
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `public_token` (`public_token`),
        KEY `created_by` (`created_by`),
        CONSTRAINT `form_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "✅ form_templates created.<br>";

    // form_submissions
    $db->exec("CREATE TABLE IF NOT EXISTS `form_submissions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_id` INT(11) NOT NULL,
        `correlativo` VARCHAR(50) NOT NULL,
        `respondent_name` VARCHAR(255) DEFAULT NULL,
        `respondent_email` VARCHAR(255) DEFAULT NULL,
        `data_json` LONGTEXT DEFAULT NULL,
        `drive_file_id` VARCHAR(255) DEFAULT NULL,
        `submission_month` VARCHAR(20) DEFAULT NULL,
        `status` ENUM('nuevo','revisado','archivado') DEFAULT 'nuevo',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `correlativo` (`correlativo`),
        KEY `template_id` (`template_id`),
        CONSTRAINT `form_submissions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `form_templates`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "✅ form_submissions created.<br>";
    echo "<br><strong>✅ Schema ready!</strong>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
