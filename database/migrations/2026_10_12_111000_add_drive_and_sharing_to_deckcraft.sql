-- Agregar campos de Google Drive y enlace público a deckcraft_presentations
ALTER TABLE `deckcraft_presentations`
    ADD COLUMN `drive_folder_id` VARCHAR(255) NULL AFTER `project_id`,
    ADD COLUMN `drive_folder_name` VARCHAR(255) NULL AFTER `drive_folder_id`,
    ADD COLUMN `drive_folder_url` VARCHAR(500) NULL AFTER `drive_folder_name`,
    ADD COLUMN `drive_file_id` VARCHAR(255) NULL AFTER `drive_folder_url`,
    ADD COLUMN `drive_file_url` VARCHAR(500) NULL AFTER `drive_file_id`,
    ADD COLUMN `share_token` VARCHAR(64) NULL AFTER `drive_file_url`,
    ADD COLUMN `is_public` TINYINT(1) DEFAULT 1 AFTER `share_token`,
    ADD UNIQUE KEY `idx_share_token` (`share_token`),
    ADD INDEX `idx_drive_folder_id` (`drive_folder_id`);
