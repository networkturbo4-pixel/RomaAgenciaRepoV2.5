-- Migración para la tabla de proyectos e ilustraciones de VectorCraft con soporte para Google Drive y Enlaces Públicos
CREATE TABLE IF NOT EXISTS `vectorcraft_projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Ilustración sin título',
    `description` TEXT NULL,
    `file_path` VARCHAR(500) NULL,
    `thumbnail` VARCHAR(500) NULL,
    `format_type` VARCHAR(50) DEFAULT 'svg',
    `drive_folder_id` VARCHAR(255) NULL,
    `drive_folder_name` VARCHAR(255) NULL,
    `drive_file_id` VARCHAR(255) NULL,
    `drive_web_link` VARCHAR(500) NULL,
    `share_token` VARCHAR(100) NULL,
    `is_public` TINYINT(1) DEFAULT 1,
    `client_id` INT NULL,
    `project_id` INT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
