-- Centro de Tareas: Mejoras avanzadas
-- Ejecutar en phpMyAdmin o CLI

-- Flag de urgencia para tareas regulares
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS is_urgent TINYINT(1) DEFAULT 0;

-- Permiso granular: roles que pueden ver todas las tareas
-- Se guarda como JSON array de role_ids, ej: [1,3]
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('task_center_view_all_roles', '[1]');
