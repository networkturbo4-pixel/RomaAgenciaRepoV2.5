-- Migración: Añadir soporte para propuestas de diseño de logotipo en brand_guidelines
-- Fecha: 2026-10-07 14:00:00
-- Descripción: Permite gestionar propuestas conceptuales de logo con justificación y mockups, con visibilidad controlada [ON/OFF] de forma 100% segura y no destructiva para producción.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `brand_guidelines` ADD COLUMN `show_proposals` TINYINT(1) NOT NULL DEFAULT 0 AFTER `applications_json`;

ALTER TABLE `brand_guidelines` ADD COLUMN `logo_proposals_json` LONGTEXT DEFAULT NULL AFTER `show_proposals`;

SET FOREIGN_KEY_CHECKS = 1;
