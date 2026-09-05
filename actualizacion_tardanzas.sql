-- ========================================================
-- ACTUALIZACIÓN DE BASE DE DATOS: MÓDULO DE ASISTENCIAS
-- Control de Tardanzas, Horarios y Tolerancia
-- ========================================================

-- 1. Agregar columnas para tardanzas, bloqueo y horas extras en la tabla `asistencias`
ALTER TABLE `asistencias`
  ADD COLUMN IF NOT EXISTS `salida_previa` DATETIME NULL AFTER `salida`,
  ADD COLUMN IF NOT EXISTS `es_tardanza` TINYINT(1) NOT NULL DEFAULT 0 AFTER `salida_previa`,
  ADD COLUMN IF NOT EXISTS `minutos_tarde` INT NOT NULL DEFAULT 0 AFTER `es_tardanza`,
  ADD COLUMN IF NOT EXISTS `hora_programada` TIME NULL AFTER `minutos_tarde`,
  ADD COLUMN IF NOT EXISTS `tolerancia_minutos` INT NOT NULL DEFAULT 5 AFTER `hora_programada`,
  ADD COLUMN IF NOT EXISTS `bloqueado_por_tardanza` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tolerancia_minutos`,
  ADD COLUMN IF NOT EXISTS `realiza_horas_extras` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bloqueado_por_tardanza`,
  ADD COLUMN IF NOT EXISTS `motivo_horas_extras` VARCHAR(255) NULL AFTER `realiza_horas_extras`,
  ADD COLUMN IF NOT EXISTS `desbloqueado_fin_jornada` TINYINT(1) NOT NULL DEFAULT 0 AFTER `motivo_horas_extras`;

-- 2. Parámetros de configuración general en la tabla `settings`
INSERT INTO `settings` (`setting_key`, `setting_value`) 
VALUES 
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

-- 3. (Opcional) Recalcular registros históricos existentes de asistencias
UPDATE `asistencias` a
JOIN `users` u ON a.user_id = u.id
LEFT JOIN `employees` e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
SET 
  a.hora_programada = COALESCE(e.work_start, '09:00:00'),
  a.tolerancia_minutos = 5,
  a.es_tardanza = IF(TIME(a.entrada) > ADDTIME(COALESCE(e.work_start, '09:00:00'), SEC_TO_TIME(5 * 60)), 1, 0),
  a.minutos_tarde = IF(
    TIME(a.entrada) > ADDTIME(COALESCE(e.work_start, '09:00:00'), SEC_TO_TIME(5 * 60)),
    GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, CONCAT(a.fecha, ' ', COALESCE(e.work_start, '09:00:00')), a.entrada) / 60)),
    0
  )
WHERE a.entrada IS NOT NULL;
