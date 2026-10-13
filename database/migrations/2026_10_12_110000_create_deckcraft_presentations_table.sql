-- Migración para la tabla de presentaciones de DeckCraft
CREATE TABLE IF NOT EXISTS `deckcraft_presentations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Presentación sin título',
    `description` TEXT NULL,
    `file_path` VARCHAR(500) NULL,
    `thumbnail` VARCHAR(500) NULL,
    `theme` VARCHAR(100) DEFAULT 'default',
    `slides_count` INT DEFAULT 1,
    `client_id` INT NULL,
    `project_id` INT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
