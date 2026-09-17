-- ========================================================
-- ACTUALIZACIÓN DE BASE DE DATOS: FIN DE JORNADA Y OTP
-- Desbloqueo con Google Authenticator y Horas Extras
-- ========================================================

-- 1. Agregar columnas para la salida previa y el desbloqueo de fin de jornada
ALTER TABLE `asistencias`
  ADD `salida_previa` DATETIME NULL AFTER `salida`,
  ADD `desbloqueado_fin_jornada` TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Asegurar parámetros de configuración de fin de jornada en `settings`
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('asistencia_hora_salida_default', '18:00:00'),
  ('asistencia_salida_bloqueo_activo', '1'),
  ('asistencia_salida_gracia_minutos', '15'),
  ('asistencia_salida_bloqueo_minutos', '30'),
  ('asistencia_bloqueo_fuera_horario', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
