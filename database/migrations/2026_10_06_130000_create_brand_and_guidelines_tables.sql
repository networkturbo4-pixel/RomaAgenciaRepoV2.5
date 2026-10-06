-- Migración: Tablas de Desarrollo de Marca y Brand Guidelines
-- Fecha: 2026-10-06 13:00:00
-- Descripción: Creación no destructiva de tablas para gestión de marcas, proyectos de identidad y manuales de marca interactivos.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Etiquetas de marca
CREATE TABLE IF NOT EXISTS `brand_tags` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(30) DEFAULT '#6366f1',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Proyectos de marca (Desarrollo de Marca)
CREATE TABLE IF NOT EXISTS `brand_projects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `form_submission_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `client_name` VARCHAR(150) DEFAULT NULL,
  `client_avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Active','Pending','Completed','Archived') DEFAULT 'Active',
  `cover_image` TEXT DEFAULT NULL,
  `secondary_image` TEXT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `drive_folder_url` TEXT DEFAULT NULL,
  `drive_folder_id` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `messages_count` INT(11) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Relación proyecto - etiquetas
CREATE TABLE IF NOT EXISTS `brand_project_tags` (
  `project_id` INT(11) NOT NULL,
  `tag_id` INT(11) NOT NULL,
  PRIMARY KEY (`project_id`, `tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Relación proyecto - usuarios asignados
CREATE TABLE IF NOT EXISTS `brand_project_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Grupos de tareas de marca
CREATE TABLE IF NOT EXISTS `brand_task_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `color` VARCHAR(7) DEFAULT '#0f172a',
  `start_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tareas de proyectos de marca
CREATE TABLE IF NOT EXISTS `brand_tasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `group_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `assigned_users` TEXT DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `start_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `tags` TEXT DEFAULT NULL,
  `attachments` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Subtareas de proyectos de marca
CREATE TABLE IF NOT EXISTS `brand_subtasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `completed` TINYINT(1) DEFAULT 0,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Plantillas de grupos de tareas de marca
CREATE TABLE IF NOT EXISTS `brand_group_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `template_data` LONGTEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Manuales de Identidad y Marca (Brand Guidelines)
CREATE TABLE IF NOT EXISTS `brand_guidelines` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) DEFAULT NULL,
  `brand_name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `tagline` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `mission` TEXT DEFAULT NULL,
  `vision` TEXT DEFAULT NULL,
  `values_json` LONGTEXT DEFAULT NULL,
  `tone_of_voice` TEXT DEFAULT NULL,
  `logo_primary` VARCHAR(500) DEFAULT NULL,
  `logo_primary_dark` VARCHAR(500) DEFAULT NULL,
  `logo_symbol` VARCHAR(500) DEFAULT NULL,
  `logo_variations_json` LONGTEXT DEFAULT NULL,
  `icons_json` LONGTEXT DEFAULT NULL,
  `safe_zone_rules` TEXT DEFAULT NULL,
  `min_size_rules` TEXT DEFAULT NULL,
  `incorrect_uses_json` LONGTEXT DEFAULT NULL,
  `colors_json` LONGTEXT DEFAULT NULL,
  `fonts_json` LONGTEXT DEFAULT NULL,
  `applications_json` LONGTEXT DEFAULT NULL,
  `allow_asset_download` TINYINT(1) DEFAULT 1,
  `is_public` TINYINT(1) DEFAULT 1,
  `access_password` VARCHAR(255) DEFAULT NULL,
  `views_count` INT(11) DEFAULT 0,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Marcas vinculadas a clientes
CREATE TABLE IF NOT EXISTS `client_brands` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `whatsapp_group` VARCHAR(255) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `services_ids` TEXT DEFAULT NULL,
  `has_membership` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Etiquetas iniciales por defecto (sin sobreescribir existentes)
INSERT IGNORE INTO `brand_tags` (`id`, `name`, `color`) VALUES
(1, 'Identidad de Marca', '#10b981'),
(2, 'Diseño', '#f58300'),
(3, 'Revisión', '#eab308'),
(4, 'Web', '#3b82f6'),
(5, 'Video', '#8b5cf6'),
(6, 'Urgente', '#ef4444'),
(7, 'Contenido', '#10b981'),
(8, 'Campaña', '#ec4899'),
(9, 'Copywriting', '#06b6d4'),
(10, 'Estrategia', '#6366f1'),
(11, 'Logotipo', '#6366f1'),
(12, 'Diseño de Marca', '#6366f1'),
(14, 'Investigación', '#6366f1'),
(15, 'Manual de Marca', '#6366f1'),
(16, 'Social Media', '#6366f1'),
(17, 'Entrega', '#6366f1');

-- 12. Permisos en roles para administradores y diseñadores
INSERT IGNORE INTO `role_permissions` (`role_id`, `module_name`)
SELECT r.id, 'brand_guidelines' FROM `roles` r WHERE r.id = 1 OR r.name IN ('Administrador', 'Diseñador');

INSERT IGNORE INTO `role_permissions` (`role_id`, `module_name`)
SELECT r.id, 'desarrollo_marca' FROM `roles` r WHERE r.id = 1 OR r.name IN ('Administrador', 'Diseñador');

SET FOREIGN_KEY_CHECKS = 1;
