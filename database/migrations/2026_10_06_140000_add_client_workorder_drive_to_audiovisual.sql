-- Migración: Conexión de Audiovisual con Clientes, Órdenes de Servicio y Subcarpetas de Google Drive
-- Fecha: 2026-10-06 14:00:00
-- Descripción: Agrega campos client_id, work_order_id y drive_subfolders_json a audiovisual_projects de forma no destructiva.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Agregar columnas a audiovisual_projects
ALTER TABLE `audiovisual_projects` 
  ADD COLUMN IF NOT EXISTS `client_id` INT(11) NULL AFTER `form_submission_id`,
  ADD COLUMN IF NOT EXISTS `work_order_id` INT(11) NULL AFTER `client_id`,
  ADD COLUMN IF NOT EXISTS `drive_subfolders_json` TEXT NULL AFTER `drive_folder_id`;

-- 2. Asegurar índices para búsquedas ágiles
ALTER TABLE `audiovisual_projects`
  ADD INDEX IF NOT EXISTS `idx_av_client` (`client_id`),
  ADD INDEX IF NOT EXISTS `idx_av_wo` (`work_order_id`);

SET FOREIGN_KEY_CHECKS = 1;
