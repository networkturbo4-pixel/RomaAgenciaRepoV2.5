<?php
// index.php
session_start();

// PREVENT CACHING: Force the browser to always fetch the latest version of the CRM
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config/database.php';

// Instantiate DB and connect
$database = new Database();
$db = $database->getConnection();

// Fetch Global Settings
$stmt = $db->query("SELECT * FROM settings");
$global_settings_raw = $stmt->fetchAll();
$global_settings = [];
foreach ($global_settings_raw as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

// Basic Routing
$module = !empty($_GET['module']) ? $_GET['module'] : 'dashboard';
$action = !empty($_GET['action']) ? $_GET['action'] : 'index';

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    if ($module !== 'auth' 
        && !($module === 'work_orders' && $action === 'public') 
        && !($module === 'chat' && $action === 'public')
        && !($module === 'mensajes' && $action === 'guest')
        && !($module === 'forms' && $action === 'fill')
        && !($module === 'forms' && $action === 'ajax_submit_form')
        && !($module === 'forms' && $action === 'ajax_upload_temp')
        && !($module === 'forms' && $action === 'view_submission')
        && !($module === 'admin' && $action === 'payment_note_webview' && isset($_GET['view']) && $_GET['view'] === 'public')
        && !($module === 'pizarras' && $action === 'join_invite')
        && !($module === 'pizarras' && $action === 'view')
        && !($module === 'quotes' && $action === 'public')
        && $module !== 'public'
    ) {
        header("Location: index.php?module=auth&action=login");
        exit();
    }
} else {
    // User is logged in, fetch permissions
    $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    $role_id = $stmtRole->fetchColumn();

    $user_permissions = [];
    $allowed_modules = ['auth', 'dashboard', 'workspace', 'desarrollo_marca', 'drive', 'config', 'clients', 'work_orders', 'admin', 'services', 'calendar', 'quotes', 'forms', 'contracts', 'conexiones', 'reuniones', 'herramientas', 'pizarras', 'mensajes', 'romita', 'project_board', 'month_board', 'community', 'projects', 'public', 'whatsapp', 'task_manager'];
    
    if ($role_id) {
        if ($role_id == 1) {
            // Administrador role gets all permissions by default
            $user_permissions = $allowed_modules;
        } else {
            $stmtPerms = $db->prepare("SELECT module_name FROM role_permissions WHERE role_id = ?");
            $stmtPerms->execute([$role_id]);
            $user_permissions = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
        }
        $_SESSION['user_permissions'] = $user_permissions;
    }

    // Enforce permission
    if ($module !== 'auth' 
        && !($module === 'work_orders' && $action === 'public')
        && !($module === 'forms' && $action === 'fill')
        && !($module === 'forms' && $action === 'ajax_submit_form')
        && !($module === 'forms' && $action === 'view_submission')
        && !($module === 'admin' && $action === 'payment_note_webview' && isset($_GET['view']) && $_GET['view'] === 'public')
        && !($module === 'pizarras' && $action === 'join_invite')
        && !($module === 'pizarras' && $action === 'view')
        && !($module === 'quotes' && $action === 'public')
        && $module !== 'public'
    ) {
        if (!in_array($module, $user_permissions)) {
            if (!empty($user_permissions)) {
                $first = $user_permissions[0];
                header("Location: index.php?module={$first}&action=index");
                exit();
            } else {
                echo "<div style='padding:2rem; font-family:sans-serif;'><h2>403 - Acceso Denegado</h2><p>No tienes permisos para ningún módulo.</p><a href='index.php?module=auth&action=logout'>Cerrar sesión</a></div>";
                exit();
            }
        }
    }

    // Control estricto de asistencia y horarios laborales (Entrada programada y Salida programada por empleado)
    $is_user_blocked_late = false;
    $is_user_shift_ended = false;
    $is_user_before_shift = false;
    $bloqueo_info = null;
    $shift_end_info = null;
    $before_shift_info = null;

    if ($role_id != 1) {
        $stmt_set = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
            'asistencia_hora_entrada_default', 'asistencia_tolerancia_minutos', 'asistencia_bloqueo_minutos', 
            'asistencia_bloqueo_activo', 'asistencia_hora_salida_default', 'asistencia_salida_bloqueo_activo',
            'asistencia_salida_gracia_minutos', 'asistencia_salida_bloqueo_minutos', 'asistencia_bloqueo_fuera_horario'
        )");
        $bsets = $stmt_set ? $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        // Obtener horario individual programado del empleado
        $stmt_emp = $db->prepare("
            SELECT e.work_start, e.work_end 
            FROM users u 
            LEFT JOIN employees e ON (LOWER(TRIM(u.email)) = LOWER(TRIM(e.email)) OR LOWER(TRIM(u.name)) = LOWER(TRIM(e.name)))
            WHERE u.id = ?
        ");
        $stmt_emp->execute([$_SESSION['user_id']]);
        $emp_schedule = $stmt_emp->fetch(PDO::FETCH_ASSOC);

        $hora_entrada_prog = (!empty($emp_schedule['work_start'])) ? $emp_schedule['work_start'] : ($bsets['asistencia_hora_entrada_default'] ?? '09:00:00');
        $hora_salida_prog  = (!empty($emp_schedule['work_end']))   ? $emp_schedule['work_end']   : ($bsets['asistencia_hora_salida_default'] ?? '18:00:00');

        $b_activo = intval($bsets['asistencia_bloqueo_activo'] ?? 1);
        $salida_bloqueo_activo = intval($bsets['asistencia_salida_bloqueo_activo'] ?? 1);
        $bloqueo_fuera_horario = intval($bsets['asistencia_bloqueo_fuera_horario'] ?? 1);

        $b_mins_entrada = intval($bsets['asistencia_bloqueo_minutos'] ?? 20);
        $b_mins_salida_extremo = intval($bsets['asistencia_salida_bloqueo_minutos'] ?? 30);

        // Consultar asistencia registrada de hoy
        $stmt_asist = $db->prepare("SELECT * FROM asistencias WHERE user_id = ? AND fecha = CURDATE()");
        $stmt_asist->execute([$_SESSION['user_id']]);
        $asist_today = $stmt_asist->fetch(PDO::FETCH_ASSOC);

        $now_ts = time();
        $today_str = date('Y-m-d');
        $ts_entrada_prog = strtotime($today_str . ' ' . $hora_entrada_prog);
        $ts_salida_prog  = strtotime($today_str . ' ' . $hora_salida_prog);

        // 1. EVALUAR FIN DE JORNADA (Desconexión laboral, salida marcada o fin de horario extremo)
        if ($salida_bloqueo_activo) {
            $desbloqueado_fin = (!empty($asist_today['desbloqueado_fin_jornada']) && $asist_today['desbloqueado_fin_jornada'] == 1);
            $salida_marcada = (!empty($asist_today['salida']));
            $horas_extras_activas = (!empty($asist_today['realiza_horas_extras']) && $asist_today['realiza_horas_extras'] == 1);
            $tiempo_extremo_ts = $ts_salida_prog + ($b_mins_salida_extremo * 60);

            if (!$desbloqueado_fin && ($salida_marcada || ($now_ts > $tiempo_extremo_ts && !$horas_extras_activas))) {
                $is_user_shift_ended = true;
                $shift_end_info = [
                    'salida_marcada' => $salida_marcada,
                    'hora_salida_registrada' => $salida_marcada ? substr($asist_today['salida'], 0, 5) : '',
                    'hora_salida_programada' => substr($hora_salida_prog, 0, 5),
                    'hora_actual' => date('H:i'),
                    'minutos_pasados' => max(0, (int)ceil(($now_ts - $ts_salida_prog) / 60))
                ];

                if ($module !== 'dashboard' && $module !== 'auth') {
                    header("Location: index.php?module=dashboard");
                    exit();
                }
            }
        }

        // 2. EVALUAR ANTES DE HORARIO (Ingreso demasiado temprano antes de la jornada)
        if (!$is_user_shift_ended && $bloqueo_fuera_horario) {
            $margen_temprano_ts = $ts_entrada_prog - (30 * 60); // 30 min antes
            if ($now_ts < $margen_temprano_ts && (!$asist_today || empty($asist_today['entrada']))) {
                $is_user_before_shift = true;
                $before_shift_info = [
                    'hora_entrada_programada' => substr($hora_entrada_prog, 0, 5),
                    'hora_actual' => date('H:i')
                ];

                if ($module !== 'dashboard' && $module !== 'auth') {
                    header("Location: index.php?module=dashboard");
                    exit();
                }
            }
        }

        // 3. EVALUAR TARDANZA EXTREMA MATUTINA (Dentro del turno pero sin marcar entrada a tiempo)
        if (!$is_user_shift_ended && !$is_user_before_shift && $b_activo) {
            if (!$asist_today || empty($asist_today['entrada'])) {
                $limit_entrada_ts = $ts_entrada_prog + ($b_mins_entrada * 60);

                if ($now_ts > $limit_entrada_ts && $now_ts < $ts_salida_prog) {
                    $diff_seconds = $now_ts - $ts_entrada_prog;
                    $mins_late = max(1, (int)ceil($diff_seconds / 60));
                    $is_user_blocked_late = true;
                    $bloqueo_info = [
                        'hora_programada' => substr($hora_entrada_prog, 0, 5),
                        'hora_actual' => date('H:i'),
                        'minutos_tarde' => $mins_late,
                        'bloqueo_minutos' => $b_mins_entrada
                    ];

                    if ($module !== 'dashboard' && $module !== 'auth') {
                        header("Location: index.php?module=dashboard");
                        exit();
                    }
                }
            }
        }
    }
}

// Map modules to their respective files
$allowed_modules = ['auth', 'dashboard', 'workspace', 'drive', 'config', 'clients', 'work_orders', 'admin', 'services', 'calendar', 'community', 'project_board', 'month_board', 'quotes', 'forms', 'public', 'contracts', 'conexiones', 'projects', 'reuniones', 'herramientas', 'pizarras', 'mensajes', 'whatsapp', 'romita', 'task_manager', 'desarrollo_marca'];
if (in_array($module, $allowed_modules)) {
    $module_file = "modules/{$module}/{$action}.php";
    if (file_exists($module_file)) {
        require_once $module_file;
    } else {
        echo "404 - Action Not Found: " . htmlspecialchars($module_file);
    }
} else {
    echo "404 - Module Not Found";
}
?>
