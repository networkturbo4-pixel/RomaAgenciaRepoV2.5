-- ==============================================================================
-- ACTUALIZACIÓN COMPLETA DE BASE DE DATOS
-- Sistema: Roma Agencia SaaS
-- Fecha: 06 de Septiembre 2026
-- Compatible con MySQL 5.7+, MySQL 8.0+ y MariaDB 10.2+ (phpMyAdmin / cPanel)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ==============================================================================
-- SECCIÓN 1: TABLAS DEL GESTOR DE TAREAS (TASK MANAGER & GANTT)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `tm_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('new','pending','overdue','completed','approved') DEFAULT 'new',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `frequency` enum('daily','weekly','one_time') DEFAULT 'one_time',
  `area` enum('general','desarrollo_marca','desarrollo_web','audiovisual') DEFAULT 'general',
  `project_id` int(11) DEFAULT NULL,
  `project_month_id` int(11) DEFAULT NULL,
  `brand_project_id` int(11) DEFAULT NULL,
  `brand_group_id` int(11) DEFAULT NULL,
  `project_service_id` int(11) DEFAULT NULL,
  `is_daily_objective` tinyint(1) DEFAULT 0,
  `objective_date` date DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `assigned_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_users`)),
  `assigned_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_roles`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tm_status` (`status`),
  KEY `idx_tm_project` (`project_id`),
  KEY `idx_tm_month` (`project_month_id`),
  KEY `idx_tm_brand_project` (`brand_project_id`),
  KEY `idx_tm_brand_group` (`brand_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_subtasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `tm_subtasks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tm_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_recurring_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `recurrence_type` enum('daily','weekly','monthly') DEFAULT 'daily',
  `recurrence_day` varchar(50) DEFAULT NULL,
  `assigned_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_users`)),
  `assigned_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_roles`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `created_by` int(11) NOT NULL,
  `last_generated` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tm_daily_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `total_objectives` int(11) DEFAULT 0,
  `completed_objectives` int(11) DEFAULT 0,
  `compliance_percentage` decimal(5,2) DEFAULT 0.00,
  `score` int(11) DEFAULT NULL,
  `performance_level` enum('pending','excellent','good','average','poor') DEFAULT 'pending',
  `evaluation_notes` text DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_eval_date` (`user_id`,`evaluation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==============================================================================
-- SECCIÓN 2: ETIQUETAS Y FASES DE DESARROLLO DE MARCA
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `brand_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(30) DEFAULT '#6366f1',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `brand_task_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `color` varchar(7) DEFAULT '#0f172a',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sembrar etiquetas sugeridas
INSERT IGNORE INTO `brand_tags` (`name`, `color`) VALUES
  ('Diseño', '#f58300'),
  ('Revisión', '#eab308'),
  ('Web', '#3b82f6'),
  ('Video', '#8b5cf6'),
  ('Urgente', '#ef4444'),
  ('Contenido', '#10b981'),
  ('Campaña', '#ec4899'),
  ('Copywriting', '#06b6d4'),
  ('Estrategia', '#6366f1'),
  ('Logotipo', '#6366f1'),
  ('Diseño de Marca', '#6366f1'),
  ('Identidad de Marca', '#00bfff'),
  ('Investigación', '#6366f1'),
  ('Manual de Marca', '#6366f1'),
  ('Social Media', '#6366f1'),
  ('Entrega', '#6366f1');


-- ==============================================================================
-- SECCIÓN 3: MÓDULO DE PROVEEDORES (SUPPLIERS)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `bank_info` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `public_token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `idx_suppliers_status` (`status`),
  KEY `idx_suppliers_token` (`public_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `period_month` varchar(7) NOT NULL,
  `concept` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` enum('PEN','USD') DEFAULT 'PEN',
  `payment_method` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('paid','pending','under_review','cancelled') DEFAULT 'paid',
  `voucher_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sp_supplier_id` (`supplier_id`),
  KEY `idx_sp_payment_date` (`payment_date`),
  KEY `idx_sp_period_month` (`period_month`),
  KEY `idx_sp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `period_month` varchar(7) NOT NULL,
  `service_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `currency` enum('PEN','USD') DEFAULT 'PEN',
  `service_date` date DEFAULT NULL,
  `status` enum('in_progress','delivered','approved','cancelled') DEFAULT 'delivered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ss_supplier_id` (`supplier_id`),
  KEY `idx_ss_period_month` (`period_month`),
  KEY `idx_ss_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==============================================================================
-- SECCIÓN 4: MIGRACIÓN SEGURA DE COLUMNAS (TOTALMENTE INDEPENDIENTE DE POSICIÓN)
-- ==============================================================================

-- 4.1. Fases de Desarrollo de Marca (brand_task_groups: color, start_date, due_date)
SET @col_exist_bg_color = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'brand_task_groups' AND column_name = 'color');
SET @query_bg_color = IF(@col_exist_bg_color = 0, "ALTER TABLE `brand_task_groups` ADD COLUMN `color` varchar(7) DEFAULT '#0f172a'", 'SELECT 1');
PREPARE stmt FROM @query_bg_color; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exist_bg_start = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'brand_task_groups' AND column_name = 'start_date');
SET @query_bg_start = IF(@col_exist_bg_start = 0, 'ALTER TABLE `brand_task_groups` ADD COLUMN `start_date` date DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query_bg_start; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exist_bg_due = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'brand_task_groups' AND column_name = 'due_date');
SET @query_bg_due = IF(@col_exist_bg_due = 0, 'ALTER TABLE `brand_task_groups` ADD COLUMN `due_date` date DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query_bg_due; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.2. Gestor de Tareas: Agregar brand_group_id a tm_tasks si no existe
SET @col_exist_tm_bg = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tm_tasks' AND column_name = 'brand_group_id');
SET @query_tm_bg = IF(@col_exist_tm_bg = 0, 'ALTER TABLE `tm_tasks` ADD COLUMN `brand_group_id` int(11) NULL', 'SELECT 1');
PREPARE stmt FROM @query_tm_bg; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.3. Calendario: Asegurar columnas de fecha y estado en project_months
SET @col_exist_pm_start = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'project_months' AND column_name = 'start_date');
SET @query_pm_start = IF(@col_exist_pm_start = 0, 'ALTER TABLE `project_months` ADD COLUMN `start_date` date DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query_pm_start; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exist_pm_due = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'project_months' AND column_name = 'due_date');
SET @query_pm_due = IF(@col_exist_pm_due = 0, 'ALTER TABLE `project_months` ADD COLUMN `due_date` date DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query_pm_due; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exist_pm_status = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'project_months' AND column_name = 'status');
SET @query_pm_status = IF(@col_exist_pm_status = 0, "ALTER TABLE `project_months` ADD COLUMN `status` varchar(50) DEFAULT 'pendiente'", 'SELECT 1');
PREPARE stmt FROM @query_pm_status; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.4. Módulo de Asistencias (Solo si la tabla asistencias existe en esta base de datos)
SET @table_exist_as = (SELECT count(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asistencias');

SET @col_exist_as_sp = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'asistencias' AND column_name = 'salida_previa');
SET @query_as_sp = IF(@table_exist_as > 0 AND @col_exist_as_sp = 0, 'ALTER TABLE `asistencias` ADD COLUMN `salida_previa` DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @query_as_sp; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exist_as_tard = (SELECT count(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'asistencias' AND column_name = 'es_tardanza');
SET @query_as_tard = IF(@table_exist_as > 0 AND @col_exist_as_tard = 0, 'ALTER TABLE `asistencias` ADD COLUMN `es_tardanza` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `minutos_tarde` INT NOT NULL DEFAULT 0, ADD COLUMN `hora_programada` TIME NULL, ADD COLUMN `tolerancia_minutos` INT NOT NULL DEFAULT 5, ADD COLUMN `bloqueado_por_tardanza` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `realiza_horas_extras` TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN `motivo_horas_extras` VARCHAR(255) NULL, ADD COLUMN `desbloqueado_fin_jornada` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query_as_tard; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ==============================================================================
-- SECCIÓN 5: PARÁMETROS GENERALES EN SETTINGS (ASISTENCIA / CONFIGURACIÓN)
-- ==============================================================================

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('asistencia_hora_entrada_default', '09:00:00'),
  ('asistencia_tolerancia_minutos', '5'),
  ('asistencia_bloqueo_minutos', '20'),
  ('asistencia_bloqueo_activo', '1'),
  ('asistencia_totp_secret', 'JBSWY3DPEHPK3PXP'),
  ('asistencia_hora_salida_default', '18:00:00'),
  ('asistencia_salida_bloqueo_activo', '1'),
  ('asistencia_salida_gracia_minutos', '15'),
  ('asistencia_salida_bloqueo_minutos', '30'),
  ('asistencia_bloqueo_fuera_horario', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
