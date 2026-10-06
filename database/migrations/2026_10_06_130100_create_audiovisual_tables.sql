-- Migración: Tablas del Módulo Audiovisual
-- Fecha: 2026-10-06 13:01:00
-- Descripción: Creación no destructiva de tablas para gestión de producciones audiovisuales, rodajes, edición, grupos de tareas y plantillas.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Etiquetas audiovisuales
CREATE TABLE IF NOT EXISTS `audiovisual_tags` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(30) DEFAULT '#6366f1',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Proyectos audiovisuales
CREATE TABLE IF NOT EXISTS `audiovisual_projects` (
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

-- 3. Relación proyecto audiovisual - etiquetas
CREATE TABLE IF NOT EXISTS `audiovisual_project_tags` (
  `project_id` INT(11) NOT NULL,
  `tag_id` INT(11) NOT NULL,
  PRIMARY KEY (`project_id`, `tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Relación proyecto audiovisual - usuarios asignados
CREATE TABLE IF NOT EXISTS `audiovisual_project_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Grupos de tareas audiovisuales (fases de producción)
CREATE TABLE IF NOT EXISTS `audiovisual_task_groups` (
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

-- 6. Tareas de proyectos audiovisuales
CREATE TABLE IF NOT EXISTS `audiovisual_tasks` (
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

-- 7. Subtareas de proyectos audiovisuales
CREATE TABLE IF NOT EXISTS `audiovisual_subtasks` (
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

-- 8. Plantillas de producción audiovisual
CREATE TABLE IF NOT EXISTS `audiovisual_group_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `template_data` LONGTEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Etiquetas iniciales por defecto (sin sobreescribir existentes)
INSERT IGNORE INTO `audiovisual_tags` (`id`, `name`, `color`) VALUES
(1, 'Preproducción', '#8b5cf6'),
(2, 'Guión & Storyboard', '#6366f1'),
(3, 'Rodaje / Grabación', '#ef4444'),
(4, 'Edición & Montaje', '#f59e0b'),
(5, 'Colorimetría', '#10b981'),
(6, 'Audio & Locución', '#06b6d4'),
(7, 'Motion Graphics', '#ec4899'),
(8, 'Revisión Cliente', '#f97316'),
(9, 'Master Final', '#14b8a6'),
(10, 'Urgente', '#dc2626');

-- 10. Plantillas predeterminadas de producción audiovisual
INSERT IGNORE INTO `audiovisual_group_templates` (`id`, `name`, `description`, `template_data`) VALUES
(1, 'Producción Audiovisual Integral (4 Fases)', 'Flujo completo estándar desde la conceptualización hasta la entrega del master final.', '[{\"name\":\"Fase 1: Preproducción\",\"tasks\":[{\"title\":\"Definición de Concepto y Objetivo del Video\",\"description\":\"Alinear el mensaje central, audiencia y formato requerido.\",\"tags\":[\"Preproducción\",\"Guión & Storyboard\"],\"subtasks\":[{\"title\":\"Brief de necesidades y referencias visuales\",\"completed\":0},{\"title\":\"Estructura de guión técnico y literario\",\"completed\":0},{\"title\":\"Storyboard o escaleta de planos\",\"completed\":0}]},{\"title\":\"Logística y Plan de Rodaje\",\"description\":\"Coordinar locaciones, talento, equipamiento y fecha de grabación.\",\"tags\":[\"Preproducción\"],\"subtasks\":[{\"title\":\"Scouting de locaciones\",\"completed\":0},{\"title\":\"Checklist de equipo de cámara, luces y audio\",\"completed\":0},{\"title\":\"Cronograma de llamado a rodaje\",\"completed\":0}]}]},{\"name\":\"Fase 2: Rodaje y Grabación\",\"tasks\":[{\"title\":\"Jornada de Grabación Principal\",\"description\":\"Captura de tomas principales (plano A) y audio directo.\",\"tags\":[\"Rodaje / Grabación\"],\"subtasks\":[{\"title\":\"Montaje de iluminación y calibración de audio\",\"completed\":0},{\"title\":\"Grabación de entrevistas / tomas principales\",\"completed\":0},{\"title\":\"Grabación de recursos complementarios (B-Roll)\",\"completed\":0}]},{\"title\":\"Backup In Situ y Control de Calidad\",\"description\":\"Descarga segura de tarjetas SD y verificación de material.\",\"tags\":[\"Rodaje / Grabación\"],\"subtasks\":[{\"title\":\"Copia de seguridad en disco externo principal\",\"completed\":0},{\"title\":\"Verificación de foco y niveles de audio\",\"completed\":0}]}]},{\"name\":\"Fase 3: Postproducción y Edición\",\"tasks\":[{\"title\":\"Primer Corte y Montaje Offline\",\"description\":\"Selección de tomas (ingest), sincronización de audio y estructura narrativa.\",\"tags\":[\"Edición & Montaje\"],\"subtasks\":[{\"title\":\"Sincronización multicámara y claquetas\",\"completed\":0},{\"title\":\"Corte grueso (Rough Cut)\",\"completed\":0},{\"title\":\"Aprobación interna de narrativa y ritmo\",\"completed\":0}]},{\"title\":\"Colorimetría y Diseño Sonoro\",\"description\":\"Corrección de color LUTs, ecualización, limpieza de ruido y música.\",\"tags\":[\"Colorimetría\",\"Audio & Locución\"],\"subtasks\":[{\"title\":\"Tratamiento de color (Color Grading)\",\"completed\":0},{\"title\":\"Mezcla y masterización de pistas de voz y música\",\"completed\":0},{\"title\":\"Efectos de sonido (Foley / SFX)\",\"completed\":0}]},{\"title\":\"Motion Graphics y Subtítulos\",\"description\":\"Títulos animados, logotipos, tercios inferiores y subtítulos legibles.\",\"tags\":[\"Motion Graphics\"],\"subtasks\":[{\"title\":\"Diseño de tercios inferiores (Lower Thirds)\",\"completed\":0},{\"title\":\"Subtitulado dinámico o cerrado\",\"completed\":0}]}]},{\"name\":\"Fase 4: Revisión y Entrega Final\",\"tasks\":[{\"title\":\"Ronda de Correcciones con Cliente\",\"description\":\"Envío de versión borrador con marca de agua para feedback.\",\"tags\":[\"Revisión Cliente\"],\"subtasks\":[{\"title\":\"Envío de enlace de revisión (Frame.io / Drive)\",\"completed\":0},{\"title\":\"Aplicación de ajustes solicitados por cliente\",\"completed\":0}]},{\"title\":\"Exportación Master y Cierre\",\"description\":\"Render final en alta calidad (ProRes / H.264) y entrega de entregables.\",\"tags\":[\"Master Final\"],\"subtasks\":[{\"title\":\"Exportación en resolución 4K / 1080p\",\"completed\":0},{\"title\":\"Versión adaptada a reels 9:16 (si aplica)\",\"completed\":0},{\"title\":\"Subida final y entrega formal al cliente\",\"completed\":0}]}]}]'),
(2, 'Spot Comercial / Video Corporativo', 'Plantilla optimizada para videos promocionales de marca, empresas o productos con foco comercial.', '[{\"name\":\"1. Concepto y Estrategia Comercial\",\"tasks\":[{\"title\":\"Definición de Objetivo y Mensaje Central\",\"description\":\"Propuesta de valor clave, público objetivo y llamado a la acción (CTA).\",\"tags\":[\"Preproducción\",\"Guión & Storyboard\"],\"subtasks\":[{\"title\":\"Propuesta de 2 líneas creativas para spot\",\"completed\":0},{\"title\":\"Redacción de guión de 30s / 60s\",\"completed\":0}]}]},{\"name\":\"2. Producción y Rodaje\",\"tasks\":[{\"title\":\"Grabación en Instalaciones / Estudio\",\"description\":\"Captura de infraestructura, colaboradores en acción y tomas de producto.\",\"tags\":[\"Rodaje / Grabación\"],\"subtasks\":[{\"title\":\"Filmación de producto con iluminación cinematográfica\",\"completed\":0},{\"title\":\"Tomas con gimbal o dolly de movimiento dinámico\",\"completed\":0}]}]},{\"name\":\"3. Edición Comercial y Look de Marca\",\"tasks\":[{\"title\":\"Montaje Rítmico con Animación y Color\",\"description\":\"Cortes ágiles, música energizante, voz profesional y paleta de la marca.\",\"tags\":[\"Edición & Montaje\",\"Colorimetría\",\"Motion Graphics\"],\"subtasks\":[{\"title\":\"Edición de video con ritmo dinámico\",\"completed\":0},{\"title\":\"Gráficos corporativos y llamadas a la acción\",\"completed\":0}]},{\"title\":\"Exportación Final y Entrega\",\"description\":\"Archivos listos para pauta publicitaria en Meta Ads, Google Ads y web.\",\"tags\":[\"Master Final\"],\"subtasks\":[{\"title\":\"Formatos de anuncio 1:1, 9:16 y 16:9\",\"completed\":0},{\"title\":\"Entrega de archivos en Drive\",\"completed\":0}]}]}]'),
(3, 'Pack de Reels & Contenido Redes Sociales', 'Flujo ágil de producción por lotes (Batch) para marcas activas en TikTok, Instagram y YouTube Shorts.', '[{\"name\":\"Fase 1: Guiones de Ganchos & Tendencias\",\"tasks\":[{\"title\":\"Matriz de Contenidos y Hooks\",\"description\":\"Definición de 5 a 10 ideas de videos cortos con ganchos iniciales atractivos.\",\"tags\":[\"Preproducción\",\"Guión & Storyboard\"],\"subtasks\":[{\"title\":\"Ganchos visuales y textuales de los primeros 3 segundos\",\"completed\":0},{\"title\":\"Estructura de guiones cortos (30s a 45s)\",\"completed\":0}]}]},{\"name\":\"Fase 2: Grabación por Lotes (Batch Recording)\",\"tasks\":[{\"title\":\"Sesión Única de Grabación de Todo el Lote\",\"description\":\"Rodaje ágil de todos los guiones aprobados en una misma jornada.\",\"tags\":[\"Rodaje / Grabación\"],\"subtasks\":[{\"title\":\"Grabación de vocero con teleprompter o guión\",\"completed\":0},{\"title\":\"Grabación de recursos visuales complementarios\",\"completed\":0}]}]},{\"name\":\"Fase 3: Edición Dinámica & Entrega\",\"tasks\":[{\"title\":\"Edición Dinámica con Subtítulos y Efectos\",\"description\":\"Subtítulos grandes destacados, transiciones, zooms, sound effects y stickers.\",\"tags\":[\"Edición & Montaje\",\"Audio & Locución\"],\"subtasks\":[{\"title\":\"Subtitulado automático estilizado y corregido\",\"completed\":0},{\"title\":\"Efectos de sonido (whoosh, pop, ding)\",\"completed\":0},{\"title\":\"Exportación en 1080x1920 (Vertical 9:16) a 60fps\",\"completed\":0}]}]}]');

-- 11. Permisos en roles para administradores, diseñadores y roles audiovisuales
INSERT IGNORE INTO `role_permissions` (`role_id`, `module_name`)
SELECT r.id, 'audiovisual' FROM `roles` r WHERE r.id = 1 OR r.name IN ('Administrador', 'Diseñador', 'Audiovisual', 'Editor');

SET FOREIGN_KEY_CHECKS = 1;
