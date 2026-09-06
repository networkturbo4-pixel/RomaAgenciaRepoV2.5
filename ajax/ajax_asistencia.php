<?php
// ajax/ajax_asistencia.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';
require_once '../includes/GoogleAuthenticator.php';
$dbClass = new Database();
$db = $dbClass->getConnection();

// Función de autocorrección / migración transparente de columnas en la tabla asistencias
function ensureAsistenciaSchema($db) {
    static $checked = false;
    if ($checked || !$db) return;
    try {
        $cols = $db->query("SHOW COLUMNS FROM `asistencias`")->fetchAll(PDO::FETCH_COLUMN);
        if ($cols && count($cols) > 0) {
            if (!in_array('salida_previa', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `salida_previa` DATETIME NULL AFTER `salida`");
            }
            if (!in_array('es_tardanza', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `es_tardanza` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!in_array('minutos_tarde', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `minutos_tarde` INT NOT NULL DEFAULT 0");
            }
            if (!in_array('hora_programada', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `hora_programada` TIME NULL");
            }
            if (!in_array('tolerancia_minutos', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `tolerancia_minutos` INT NOT NULL DEFAULT 5");
            }
            if (!in_array('bloqueado_por_tardanza', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `bloqueado_por_tardanza` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!in_array('realiza_horas_extras', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `realiza_horas_extras` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!in_array('motivo_horas_extras', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `motivo_horas_extras` VARCHAR(255) NULL");
            }
            if (!in_array('desbloqueado_fin_jornada', $cols)) {
                $db->exec("ALTER TABLE `asistencias` ADD COLUMN `desbloqueado_fin_jornada` TINYINT(1) NOT NULL DEFAULT 0");
            }
        }
        $checked = true;
    } catch (Throwable $e) {
        // Ignorar excepciones de esquema si no hay permisos de ALTER
    }
}
ensureAsistenciaSchema($db);

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$valid_actions = [
    'entrada', 'inicio_refrigerio', 'fin_refrigerio', 'salida', 'status', 
    'request_permiso', 'admin_today_status', 'get_permisos', 'update_permiso_status',
    'save_settings', 'unlock_and_entrada', 'unlock_fin_jornada', 'get_totp_qr', 'generate_new_totp_secret'
];

if (!in_array($action, $valid_actions)) {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit();
}

// Helper para verificar si es admin
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$user_id]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

try {
    // Check if there's a record for today
    $stmt = $db->prepare("SELECT * FROM asistencias WHERE user_id = ? AND fecha = CURDATE()");
    $stmt->execute([$user_id]);
    $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'status') {
        $is_blocked_late = false;
        $is_shift_ended = false;
        $is_before_shift = false;
        $bloqueo_info = null;
        $shift_end_info = null;
        $before_shift_info = null;

        if (!$is_admin) {
            $stmt_set = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
                'asistencia_hora_entrada_default', 'asistencia_bloqueo_minutos', 'asistencia_bloqueo_activo',
                'asistencia_hora_salida_default', 'asistencia_salida_bloqueo_activo', 'asistencia_salida_gracia_minutos',
                'asistencia_salida_bloqueo_minutos', 'asistencia_bloqueo_fuera_horario'
            )");
            $bsets = $stmt_set ? $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR) : [];

            // Obtener horario programado del empleado
            $stmt_emp = $db->prepare("
                SELECT e.work_start, e.work_end 
                FROM users u 
                LEFT JOIN employees e ON (LOWER(TRIM(u.email)) = LOWER(TRIM(e.email)) OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.name)))
                WHERE u.id = ?
            ");
            $stmt_emp->execute([$user_id]);
            $emp_schedule = $stmt_emp->fetch(PDO::FETCH_ASSOC);

            $hora_entrada_prog = (!empty($emp_schedule['work_start'])) ? $emp_schedule['work_start'] : ($bsets['asistencia_hora_entrada_default'] ?? '09:00:00');
            $hora_salida_prog  = (!empty($emp_schedule['work_end']))   ? $emp_schedule['work_end']   : ($bsets['asistencia_hora_salida_default'] ?? '18:00:00');

            $b_activo = intval($bsets['asistencia_bloqueo_activo'] ?? 1);
            $salida_bloqueo_activo = intval($bsets['asistencia_salida_bloqueo_activo'] ?? 1);
            $bloqueo_fuera_horario = intval($bsets['asistencia_bloqueo_fuera_horario'] ?? 1);

            $b_mins_entrada = intval($bsets['asistencia_bloqueo_minutos'] ?? 20);
            $b_mins_salida_extremo = intval($bsets['asistencia_salida_bloqueo_minutos'] ?? 30);

            $now_ts = time();
            $today_str = date('Y-m-d');
            $ts_entrada_prog = strtotime($today_str . ' ' . $hora_entrada_prog);
            $ts_salida_prog  = strtotime($today_str . ' ' . $hora_salida_prog);

            // 1. Evaluar Fin de Jornada
            if ($salida_bloqueo_activo) {
                $desbloqueado_fin = (!empty($asistencia['desbloqueado_fin_jornada']) && $asistencia['desbloqueado_fin_jornada'] == 1);
                $salida_marcada = (!empty($asistencia['salida']));
                $horas_extras_activas = (!empty($asistencia['realiza_horas_extras']) && $asistencia['realiza_horas_extras'] == 1);
                $tiempo_extremo_ts = $ts_salida_prog + ($b_mins_salida_extremo * 60);

                if (!$desbloqueado_fin && ($salida_marcada || ($now_ts > $tiempo_extremo_ts && !$horas_extras_activas))) {
                    $is_shift_ended = true;
                    $shift_end_info = [
                        'salida_marcada' => $salida_marcada,
                        'hora_salida_registrada' => $salida_marcada ? substr($asistencia['salida'], 0, 5) : '',
                        'hora_salida_programada' => substr($hora_salida_prog, 0, 5),
                        'hora_actual' => date('H:i'),
                        'minutos_pasados' => max(0, (int)ceil(($now_ts - $ts_salida_prog) / 60))
                    ];
                }
            }

            // 2. Evaluar Antes de Jornada
            if (!$is_shift_ended && $bloqueo_fuera_horario) {
                $margen_temprano_ts = $ts_entrada_prog - (30 * 60);
                if ($now_ts < $margen_temprano_ts && (!$asistencia || empty($asistencia['entrada']))) {
                    $is_before_shift = true;
                    $before_shift_info = [
                        'hora_entrada_programada' => substr($hora_entrada_prog, 0, 5),
                        'hora_actual' => date('H:i')
                    ];
                }
            }

            // 3. Evaluar Tardanza Extrema Matutina
            if (!$is_shift_ended && !$is_before_shift && $b_activo) {
                if (!$asistencia || empty($asistencia['entrada'])) {
                    $limit_entrada_ts = $ts_entrada_prog + ($b_mins_entrada * 60);

                    if ($now_ts > $limit_entrada_ts && $now_ts < $ts_salida_prog) {
                        $diff_seconds = $now_ts - $ts_entrada_prog;
                        $mins_late = max(1, (int)ceil($diff_seconds / 60));
                        $is_blocked_late = true;
                        $bloqueo_info = [
                            'hora_programada' => substr($hora_entrada_prog, 0, 5),
                            'hora_actual' => date('H:i'),
                            'minutos_tarde' => $mins_late,
                            'bloqueo_minutos' => $b_mins_entrada
                        ];
                    }
                }
            }
        }

        echo json_encode([
            'success' => true, 
            'data' => $asistencia,
            'is_blocked_late' => $is_blocked_late,
            'bloqueo_info' => $bloqueo_info,
            'is_shift_ended' => $is_shift_ended,
            'shift_end_info' => $shift_end_info,
            'is_before_shift' => $is_before_shift,
            'before_shift_info' => $before_shift_info
        ]);
        exit();
    }

    if ($action === 'entrada') {
        if ($asistencia) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu entrada hoy.']);
            exit();
        }

        // Obtener horario programado del empleado
        $stmt_user = $db->prepare("
            SELECT u.email, e.work_start, e.work_end 
            FROM users u 
            LEFT JOIN employees e ON (LOWER(TRIM(u.email)) = LOWER(TRIM(e.email)) OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.name)))
            WHERE u.id = ?
        ");
        $stmt_user->execute([$user_id]);
        $user_emp = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // Ajustes predeterminados si no tiene horario individual
        $stmt_set = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('asistencia_hora_entrada_default', 'asistencia_tolerancia_minutos', 'asistencia_bloqueo_minutos', 'asistencia_bloqueo_activo')");
        $sets = $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR);

        $hora_programada = (!empty($user_emp['work_start'])) ? $user_emp['work_start'] : ($sets['asistencia_hora_entrada_default'] ?? '09:00:00');
        $tolerancia = isset($sets['asistencia_tolerancia_minutos']) ? intval($sets['asistencia_tolerancia_minutos']) : 5;
        $bloqueo_minutos = isset($sets['asistencia_bloqueo_minutos']) ? intval($sets['asistencia_bloqueo_minutos']) : 20;
        $bloqueo_activo = isset($sets['asistencia_bloqueo_activo']) ? intval($sets['asistencia_bloqueo_activo']) : 1;

        $now_ts = time();
        $today_str = date('Y-m-d');
        $scheduled_ts = strtotime($today_str . ' ' . $hora_programada);
        $limit_ts = $scheduled_ts + ($tolerancia * 60);

        $es_tardanza = ($now_ts > $limit_ts) ? 1 : 0;
        $minutos_tarde = 0;
        if ($es_tardanza === 1) {
            $diff_seconds = $now_ts - $scheduled_ts;
            $minutos_tarde = max(1, (int)ceil($diff_seconds / 60));
        }

        $hora_marcada_str = date('H:i');
        $hora_programada_str = substr($hora_programada, 0, 5);

        // BLOQUEO POR EXCESO DE TARDANZA (ej. 20 a 30 min)
        if ($bloqueo_activo && !$is_admin && $es_tardanza === 1 && $minutos_tarde >= $bloqueo_minutos) {
            echo json_encode([
                'success' => false,
                'requires_unlock' => true,
                'bloqueado' => true,
                'minutos_tarde' => $minutos_tarde,
                'bloqueo_minutos' => $bloqueo_minutos,
                'hora_marcada' => $hora_marcada_str,
                'hora_programada' => $hora_programada_str,
                'message' => "Estás fuera del horario de ingreso permitido ({$minutos_tarde} min tarde). El sistema se encuentra bloqueado y requiere autorización con Google Authenticator."
            ]);
            exit();
        }

        $stmt = $db->prepare("
            INSERT INTO asistencias (user_id, fecha, entrada, es_tardanza, minutos_tarde, hora_programada, tolerancia_minutos) 
            VALUES (?, CURDATE(), NOW(), ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $es_tardanza, $minutos_tarde, $hora_programada, $tolerancia]);

        if ($es_tardanza === 1) {
            $msg = "Estás tarde. Tu entrada fue registrada a las {$hora_marcada_str} con {$minutos_tarde} min de tardanza.";
        } else {
            $msg = "Entrada registrada con éxito a las {$hora_marcada_str}. ¡Puntual!";
        }

        echo json_encode([
            'success' => true,
            'is_late' => ($es_tardanza === 1),
            'es_tardanza' => $es_tardanza,
            'minutos_tarde' => $minutos_tarde,
            'hora_marcada' => $hora_marcada_str,
            'hora_programada' => $hora_programada_str,
            'tolerancia_minutos' => $tolerancia,
            'message' => $msg
        ]);
        exit();
    }

    if ($action === 'unlock_and_entrada') {
        if ($asistencia) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu entrada hoy.']);
            exit();
        }

        $otp_code = trim($_POST['otp_code'] ?? '');
        $realiza_horas_extras = !empty($_POST['realiza_horas_extras']) ? 1 : 0;
        $motivo_horas_extras = trim($_POST['motivo_horas_extras'] ?? '');

        if (empty($otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Debes ingresar el código de 6 dígitos de Google Authenticator.']);
            exit();
        }

        // Obtener clave secreta TOTP
        $stmt_sec = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'asistencia_totp_secret'");
        $secret = $stmt_sec ? $stmt_sec->fetchColumn() : '';

        if (empty($secret)) {
            echo json_encode(['success' => false, 'message' => 'Google Authenticator no está configurado en el sistema. Contacta al administrador.']);
            exit();
        }

        // Validar código TOTP
        if (!GoogleAuthenticator::verifyCode($secret, $otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Código de Google Authenticator incorrecto o expirado. Solicita el código actual a tu supervisor.']);
            exit();
        }

        // Obtener horario programado del empleado
        $stmt_user = $db->prepare("
            SELECT u.email, e.work_start, e.work_end 
            FROM users u 
            LEFT JOIN employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
            WHERE u.id = ?
        ");
        $stmt_user->execute([$user_id]);
        $user_emp = $stmt_user->fetch(PDO::FETCH_ASSOC);

        $stmt_set = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('asistencia_hora_entrada_default', 'asistencia_tolerancia_minutos')");
        $sets = $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR);

        $hora_programada = (!empty($user_emp['work_start'])) ? $user_emp['work_start'] : ($sets['asistencia_hora_entrada_default'] ?? '09:00:00');
        $tolerancia = isset($sets['asistencia_tolerancia_minutos']) ? intval($sets['asistencia_tolerancia_minutos']) : 5;

        $now_ts = time();
        $today_str = date('Y-m-d');
        $scheduled_ts = strtotime($today_str . ' ' . $hora_programada);
        $diff_seconds = max(0, $now_ts - $scheduled_ts);
        $minutos_tarde = max(1, (int)ceil($diff_seconds / 60));

        $stmt = $db->prepare("
            INSERT INTO asistencias (
                user_id, fecha, entrada, es_tardanza, minutos_tarde, 
                hora_programada, tolerancia_minutos, bloqueado_por_tardanza, 
                realiza_horas_extras, motivo_horas_extras
            ) VALUES (?, CURDATE(), NOW(), 1, ?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute([
            $user_id, $minutos_tarde, $hora_programada, $tolerancia, 
            $realiza_horas_extras, $motivo_horas_extras
        ]);

        $hora_marcada_str = date('H:i');
        $hora_programada_str = substr($hora_programada, 0, 5);

        echo json_encode([
            'success' => true,
            'unlocked' => true,
            'is_late' => true,
            'es_tardanza' => 1,
            'minutos_tarde' => $minutos_tarde,
            'hora_marcada' => $hora_marcada_str,
            'hora_programada' => $hora_programada_str,
            'realiza_horas_extras' => $realiza_horas_extras,
            'message' => "¡Desbloqueo exitoso! Entrada registrada a las {$hora_marcada_str} ({$minutos_tarde} min de tardanza)."
        ]);
        exit();
    }

    if ($action === 'unlock_fin_jornada') {
        $otp_code = trim($_POST['otp_code'] ?? '');
        $realiza_horas_extras = !empty($_POST['realiza_horas_extras']) ? 1 : 0;
        $motivo_horas_extras = trim($_POST['motivo_horas_extras'] ?? '');

        if (empty($otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Debes ingresar el código de 6 dígitos de Google Authenticator.']);
            exit();
        }

        // Obtener clave secreta TOTP del supervisor
        $stmt_sec = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'asistencia_totp_secret'");
        $secret = $stmt_sec ? $stmt_sec->fetchColumn() : '';

        if (empty($secret)) {
            echo json_encode(['success' => false, 'message' => 'Google Authenticator no está configurado en el sistema. Contacta al administrador.']);
            exit();
        }

        // Validar código TOTP
        if (!GoogleAuthenticator::verifyCode($secret, $otp_code)) {
            echo json_encode(['success' => false, 'message' => 'Código de Google Authenticator incorrecto o expirado. Solicita el código dinámico a tu supervisor.']);
            exit();
        }

        if ($asistencia) {
            if ($realiza_horas_extras === 1) {
                // Si ya había registrado salida, guardarla en salida_previa y reabrir jornada para marcar nueva salida al terminar
                if (!empty($asistencia['salida'])) {
                    $stmt_upd = $db->prepare("
                        UPDATE asistencias 
                        SET salida_previa = IFNULL(salida_previa, salida),
                            salida = NULL,
                            realiza_horas_extras = 1,
                            motivo_horas_extras = ?,
                            desbloqueado_fin_jornada = 1
                        WHERE id = ?
                    ");
                    $stmt_upd->execute([$motivo_horas_extras, $asistencia['id']]);
                } else {
                    $stmt_upd = $db->prepare("
                        UPDATE asistencias 
                        SET realiza_horas_extras = 1,
                            motivo_horas_extras = ?,
                            desbloqueado_fin_jornada = 1
                        WHERE id = ?
                    ");
                    $stmt_upd->execute([$motivo_horas_extras, $asistencia['id']]);
                }
            } else {
                // Solo desbloqueo sin registrar horas extras
                $stmt_upd = $db->prepare("
                    UPDATE asistencias 
                    SET desbloqueado_fin_jornada = 1
                    WHERE id = ?
                ");
                $stmt_upd->execute([$asistencia['id']]);
            }
        } else {
            // Caso borde: Bloqueo fuera de horario sin registro de entrada previo
            $stmt_ins = $db->prepare("
                INSERT INTO asistencias (user_id, fecha, entrada, realiza_horas_extras, motivo_horas_extras, desbloqueado_fin_jornada)
                VALUES (?, CURDATE(), NOW(), ?, ?, 1)
            ");
            $stmt_ins->execute([$user_id, $realiza_horas_extras, $motivo_horas_extras]);
        }

        echo json_encode([
            'success' => true,
            'unlocked' => true,
            'realiza_horas_extras' => ($realiza_horas_extras === 1),
            'motivo_horas_extras' => $motivo_horas_extras,
            'message' => '¡Sistema desbloqueado con éxito mediante Google Authenticator!'
        ]);
        exit();
    }

    if ($action === 'get_totp_qr') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $stmt_sec = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'asistencia_totp_secret'");
        $secret = $stmt_sec ? $stmt_sec->fetchColumn() : '';

        if (empty($secret)) {
            $secret = GoogleAuthenticator::generateSecret();
            $stmt_ins = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_totp_secret', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt_ins->execute([$secret, $secret]);
        }

        $stmt_site = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
        $site_name = $stmt_site ? ($stmt_site->fetchColumn() ?: 'ROMA SaaS') : 'ROMA SaaS';

        $qr_url = GoogleAuthenticator::getQrImageUrl('Supervisor Asistencias', $secret, $site_name);
        $current_code = GoogleAuthenticator::getCode($secret);

        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_url' => $qr_url,
            'current_code' => $current_code
        ]);
        exit();
    }

    if ($action === 'generate_new_totp_secret') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $secret = GoogleAuthenticator::generateSecret();
        $stmt_ins = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_totp_secret', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt_ins->execute([$secret, $secret]);

        $stmt_site = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
        $site_name = $stmt_site ? ($stmt_site->fetchColumn() ?: 'ROMA SaaS') : 'ROMA SaaS';

        $qr_url = GoogleAuthenticator::getQrImageUrl('Supervisor Asistencias', $secret, $site_name);
        $current_code = GoogleAuthenticator::getCode($secret);

        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_url' => $qr_url,
            'current_code' => $current_code,
            'message' => 'Nueva clave de Google Authenticator generada. Escanéala con tu aplicación.'
        ]);
        exit();
    }

    if ($action === 'save_settings') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $hora_entrada = $_POST['hora_entrada'] ?? '09:00:00';
        $tolerancia = intval($_POST['tolerancia'] ?? 5);
        $bloqueo_minutos = intval($_POST['bloqueo_minutos'] ?? 20);
        $bloqueo_activo = isset($_POST['bloqueo_activo']) ? intval($_POST['bloqueo_activo']) : 1;

        $hora_salida = $_POST['hora_salida'] ?? '18:00:00';
        $salida_bloqueo_activo = isset($_POST['salida_bloqueo_activo']) ? intval($_POST['salida_bloqueo_activo']) : 1;
        $salida_gracia = intval($_POST['salida_gracia'] ?? 15);
        $salida_bloqueo_minutos = intval($_POST['salida_bloqueo_minutos'] ?? 30);
        $bloqueo_fuera_horario = isset($_POST['bloqueo_fuera_horario']) ? intval($_POST['bloqueo_fuera_horario']) : 1;

        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_hora_entrada_default', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$hora_entrada, $hora_entrada]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_tolerancia_minutos', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$tolerancia, $tolerancia]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_bloqueo_minutos', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$bloqueo_minutos, $bloqueo_minutos]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_bloqueo_activo', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$bloqueo_activo, $bloqueo_activo]);

        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_hora_salida_default', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$hora_salida, $hora_salida]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_salida_bloqueo_activo', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$salida_bloqueo_activo, $salida_bloqueo_activo]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_salida_gracia_minutos', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$salida_gracia, $salida_gracia]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_salida_bloqueo_minutos', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$salida_bloqueo_minutos, $salida_bloqueo_minutos]);
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('asistencia_bloqueo_fuera_horario', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$bloqueo_fuera_horario, $bloqueo_fuera_horario]);

        echo json_encode(['success' => true, 'message' => 'Configuración de asistencia, fin de jornada y bloqueo guardada con éxito.']);
        exit();
    }

    if ($action === 'request_permiso') {
        $motivo = $_POST['motivo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        
        if (empty($motivo)) {
            echo json_encode(['success' => false, 'message' => 'El motivo es obligatorio.']);
            exit();
        }

        $imagenes = [];
        if (isset($_FILES['imagenes'])) {
            $upload_dir = '../uploads/permisos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_count = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['imagenes']['error'][$i] === 0) {
                    $ext = pathinfo($_FILES['imagenes']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid('perm_') . '_' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$i], $target)) {
                        $imagenes[] = 'uploads/permisos/' . $filename;
                    }
                }
            }
        }
        
        $imagenes_json = json_encode($imagenes);
        $stmt = $db->prepare("INSERT INTO asistencia_permisos (user_id, motivo, descripcion, imagenes_json) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $motivo, $descripcion, $imagenes_json]);
        
        echo json_encode(['success' => true, 'message' => 'Permiso solicitado correctamente.']);
        exit();
    }

    if ($action === 'admin_today_status') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        // Obtener usuarios con su estado de asistencia de hoy
        $query = "
            SELECT u.id, u.name, u.email, 
                   a.entrada, a.inicio_refrigerio, a.fin_refrigerio, a.salida,
                   a.es_tardanza, a.minutos_tarde, a.hora_programada,
                   a.bloqueado_por_tardanza, a.realiza_horas_extras, a.motivo_horas_extras,
                   e.work_start, e.work_end,
                   p.estado as estado_permiso, p.motivo as motivo_permiso
            FROM users u
            LEFT JOIN employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
            LEFT JOIN asistencias a ON u.id = a.user_id AND a.fecha = CURDATE()
            LEFT JOIN asistencia_permisos p ON u.id = p.user_id AND DATE(p.created_at) = CURDATE()
            WHERE a.id IS NOT NULL OR p.id IS NOT NULL
            ORDER BY u.name ASC
        ";
        $stmt = $db->query($query);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

    if ($action === 'get_permisos') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $query = "
            SELECT p.*, u.name as user_name 
            FROM asistencia_permisos p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.created_at DESC
        ";
        $stmt = $db->query($query);
        $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $permisos]);
        exit();
    }

    if ($action === 'update_permiso_status') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $permiso_id = $_POST['permiso_id'] ?? 0;
        $estado = $_POST['estado'] ?? '';
        $respuesta = $_POST['respuesta'] ?? '';
        
        if (!in_array($estado, ['Aprobado', 'Rechazado'])) {
            echo json_encode(['success' => false, 'message' => 'Estado inválido.']);
            exit();
        }
        
        $stmt = $db->prepare("UPDATE asistencia_permisos SET estado = ?, respuesta_jefe = ? WHERE id = ?");
        $stmt->execute([$estado, $respuesta, $permiso_id]);
        
        echo json_encode(['success' => true, 'message' => 'Permiso actualizado.']);
        exit();
    }

    if ($action === 'salida') {
        if (!$asistencia) {
            // Si el empleado no registró entrada (ej. jornada concluida y desea registrar salida y descansar)
            $stmt = $db->prepare("INSERT INTO asistencias (user_id, fecha, entrada, salida, desbloqueado_fin_jornada) VALUES (?, CURDATE(), NOW(), NOW(), 0)");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'message' => 'Salida registrada con éxito. ¡Descansa!']);
            exit();
        }

        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET salida = NOW(), desbloqueado_fin_jornada = 0 WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Salida registrada con éxito.']);
        exit();
    }

    if (!$asistencia) {
        echo json_encode(['success' => false, 'message' => 'Debes marcar tu entrada primero.']);
        exit();
    }

    if ($action === 'inicio_refrigerio') {
        if ($asistencia['inicio_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Ya iniciaste tu refrigerio hoy.']);
            exit();
        }
        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET inicio_refrigerio = NOW() WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Inicio de refrigerio registrado.']);
        exit();
    }

    if ($action === 'fin_refrigerio') {
        if (!$asistencia['inicio_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Debes iniciar tu refrigerio primero.']);
            exit();
        }
        if ($asistencia['fin_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Ya finalizaste tu refrigerio hoy.']);
            exit();
        }
        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET fin_refrigerio = NOW() WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Fin de refrigerio registrado.']);
        exit();
    }

} catch (Throwable $e) {
    error_log("Asistencia Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
