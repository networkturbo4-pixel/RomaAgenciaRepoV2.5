-- Migración: Conexión de Audiovisual con Clientes, Órdenes de Servicio y Subcarpetas de Google Drive
-- Fecha: 2026-10-06 14:00:00
-- Descripción: Agrega campos client_id, work_order_id y drive_subfolders_json a audiovisual_projects de forma no destructiva.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Agregar columnas a audiovisual_projects
ALTER TABLE `audiovisual_projects` ADD COLUMN `client_id` INT(11) NULL AFTER `form_submission_id`;
ALTER TABLE `audiovisual_projects` ADD COLUMN `work_order_id` INT(11) NULL AFTER `client_id`;
ALTER TABLE `audiovisual_projects` ADD COLUMN `drive_subfolders_json` TEXT NULL AFTER `drive_folder_id`;

-- 2. Asegurar índices para búsquedas ágiles
ALTER TABLE `audiovisual_projects` ADD INDEX `idx_av_client` (`client_id`);
ALTER TABLE `audiovisual_projects` ADD INDEX `idx_av_wo` (`work_order_id`);

SET FOREIGN_KEY_CHECKS = 1;
