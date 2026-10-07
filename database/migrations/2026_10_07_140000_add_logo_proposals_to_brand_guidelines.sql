-- Migración: Añadir soporte para propuestas de diseño de logotipo en brand_guidelines
-- Fecha: 2026-10-07 14:00:00
-- Descripción: Permite gestionar propuestas conceptuales de logo con justificación y mockups, con visibilidad controlada [ON/OFF] de forma 100% segura y no destructiva para producción.

SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS `add_brand_guidelines_proposals_columns`;

DELIMITER $$
CREATE PROCEDURE `add_brand_guidelines_proposals_columns`()
BEGIN
    -- Comprobar si existe la columna show_proposals antes de crearla
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'brand_guidelines' 
        AND COLUMN_NAME = 'show_proposals'
    ) THEN
        ALTER TABLE `brand_guidelines` ADD COLUMN `show_proposals` TINYINT(1) NOT NULL DEFAULT 0 AFTER `applications_json`;
    END IF;

    -- Comprobar si existe la columna logo_proposals_json antes de crearla
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'brand_guidelines' 
        AND COLUMN_NAME = 'logo_proposals_json'
    ) THEN
        ALTER TABLE `brand_guidelines` ADD COLUMN `logo_proposals_json` LONGTEXT DEFAULT NULL AFTER `show_proposals`;
    END IF;
END $$
DELIMITER ;

CALL `add_brand_guidelines_proposals_columns`();
DROP PROCEDURE IF EXISTS `add_brand_guidelines_proposals_columns`;

SET FOREIGN_KEY_CHECKS = 1;
