<?php
// modules/auth/login.php
require_once __DIR__ . '/../../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

// Rate Limiting contra ataques de fuerza bruta (5 intentos / 15 minutos)
$max_attempts = 5;
$lockout_seconds = 15 * 60;
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rate_limit_key = 'login_attempts_' . md5($user_ip);
$lockout_key = 'login_locked_until_' . md5($user_ip);
$now = time();

$is_locked = false;
if (!empty($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > $now) {
    $is_locked = true;
    $remaining_mins = (int)ceil(($_SESSION[$lockout_key] - $now) / 60);
    $error = "Demasiados intentos fallidos. Por seguridad, el acceso está bloqueado temporalmente. Intente de nuevo en {$remaining_mins} minuto(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (isset($_POST['csrf_token']) && !csrf_validate($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor recargue la página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($email) && !empty($password)) {
            global $db;
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Éxito: Limpiar contadores de intentos
                unset($_SESSION[$rate_limit_key]);
                unset($_SESSION[$lockout_key]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role_id'] ?? null;
                header("Location: index.php?module=dashboard&action=index");
                exit();
            } else {
                // Fallo: Incrementar contador
                $_SESSION[$rate_limit_key] = ($_SESSION[$rate_limit_key] ?? 0) + 1;
                $current_attempts = $_SESSION[$rate_limit_key];

                if ($current_attempts >= $max_attempts) {
                    $_SESSION[$lockout_key] = $now + $lockout_seconds;
                    $_SESSION[$rate_limit_key] = 0;
                    $error = "Ha superado el número máximo de intentos fallidos ({$max_attempts}). Su acceso ha sido bloqueado por 15 minutos.";
                } else {
                    $restantes = $max_attempts - $current_attempts;
                    $error = "Credenciales inválidas. Le quedan {$restantes} intento(s) antes del bloqueo de seguridad.";
                }
            }
        } else {
            $error = 'Por favor ingrese su correo y contraseña.';
        }
    }
}
?>
<?php global $global_settings; ?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <?php 
    $site_name_seo = $global_settings['site_name'] ?? 'Roma Agencia';
    $seo_title = $site_name_seo . ($global_settings['seo_title_suffix'] ?? ' | Gestión Integral para su Empresa');
    $seo_desc = $global_settings['seo_description'] ?? 'Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo.';
    $seo_keys = $global_settings['seo_keywords'] ?? 'CRM, Gestión de Proyectos, Análisis de Datos, Productividad, Agencia';
    $primaryColor = $global_settings['primary_color'] ?? '#004e36';
    ?>
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo_keys); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($site_name_seo); ?>">
    <meta name="robots" content="index, follow">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="<?php echo htmlspecialchars($primaryColor); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($global_settings['site_name'] ?? 'RomaAgencia'); ?>">
    <link rel="manifest" href="manifest.php">
    
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primaryColor); ?>;
            --primary-glow: color-mix(in srgb, var(--primary) 25%, transparent);
            --font-display: 'Plus Jakarta Sans', 'Inter', sans-serif;
        }

        /* Dark / Light Theme Tokens */
        [data-theme="dark"] {
            --bg-body: #09090b;
            --bg-card: rgba(18, 18, 23, 0.85);
            --bg-surface: #121216;
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.18);
            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;
            --input-bg: rgba(24, 24, 30, 0.8);
            --tab-bg: rgba(24, 24, 30, 0.9);
            --keypad-btn: #18181b;
            --keypad-btn-hover: #27272a;
            --keypad-text: #f4f4f5;
            --shadow-elevation: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        [data-theme="light"] {
            --bg-body: #f8fafc;
            --bg-card: rgba(255, 255, 255, 0.95);
            --bg-surface: #ffffff;
            --border: rgba(226, 232, 240, 0.85);
            --border-hover: rgba(203, 213, 225, 1);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --tab-bg: #f1f5f9;
            --keypad-btn: #f1f5f9;
            --keypad-btn-hover: #e2e8f0;
            --keypad-text: #0f172a;
            --shadow-elevation: 0 20px 45px -10px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .font-display {
            font-family: var(--font-display);
        }

        /* Left Hero Mesh Gradient */
        .hero-mesh-background {
            background-color: #050508;
            background-image: 
                radial-gradient(circle at 15% 20%, color-mix(in srgb, var(--primary) 70%, transparent) 0%, transparent 45%),
                radial-gradient(circle at 85% 75%, color-mix(in srgb, #6366f1 45%, transparent) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, color-mix(in srgb, var(--primary) 35%, transparent) 0%, transparent 60%);
            background-size: 150% 150%;
            animation: meshShift 22s ease infinite alternate;
        }

        @keyframes meshShift {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 50% 0%; }
        }

        /* Glassmorphism Card Effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Floating Cards Float Animation */
        @keyframes subtleFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .animate-float-1 { animation: subtleFloat 6s ease-in-out infinite; }
        .animate-float-2 { animation: subtleFloat 7s ease-in-out 1.5s infinite; }

        /* Floating Input Focus Halos */
        .modern-input {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modern-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background: var(--bg-surface);
        }

        /* Primary Button Shimmer */
        .btn-primary-glow {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 8px 24px -4px var(--primary-glow);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-primary-glow:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 12px 28px -4px var(--primary-glow);
        }
        .btn-primary-glow:active {
            transform: translateY(1px) scale(0.99);
        }

        /* Biometric Neon Pulse */
        @keyframes pulseRing {
            0% { box-shadow: 0 0 0 0 var(--primary-glow); }
            70% { box-shadow: 0 0 0 10px transparent; }
            100% { box-shadow: 0 0 0 0 transparent; }
        }
        .biometric-btn {
            border: 1px solid var(--border);
            background: var(--bg-surface);
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        .biometric-btn:hover {
            border-color: var(--primary);
            animation: pulseRing 1.5s infinite;
        }

        /* DNI Slot Boxes */
        .dni-slot {
            width: 32px;
            height: 42px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            background: var(--input-bg);
            transition: all 0.15s ease;
        }
        .dni-slot.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
            transform: scale(1.05);
        }
        .dni-slot.filled {
            border-color: color-mix(in srgb, var(--primary) 60%, var(--border));
            color: var(--text-main);
        }
    </style>
</head>
<body class="min-h-screen min-h-[100dvh] bg-[var(--bg-card)] lg:bg-[var(--bg-body)] selection:bg-emerald-500/20 selection:text-emerald-500 overflow-x-hidden">

<!-- Desktop Floating Theme Toggle Button -->
<div class="hidden lg:block fixed top-6 right-6 z-50">
    <button id="themeToggleBtn" onclick="toggleTheme()" class="w-10 h-10 rounded-full flex items-center justify-center border border-[var(--border)] bg-[var(--bg-surface)] text-[var(--text-main)] shadow-sm hover:scale-105 active:scale-95 transition-all cursor-pointer" title="Cambiar tema (Claro/Oscuro)">
        <i class="ph ph-sun text-lg hidden" id="iconLight"></i>
        <i class="ph ph-moon text-lg" id="iconDark"></i>
    </button>
</div>

<!-- Main Split Screen Layout -->
<main class="min-h-screen w-full flex flex-col lg:flex-row">
    
    <!-- LEFT PANEL: Hero Product Showcase (Desktop Only) -->
    <section class="hidden lg:flex w-7/12 hero-mesh-background flex-col justify-between p-12 xl:p-16 relative overflow-hidden text-white">
        <!-- Background Ambient Light Spheres -->
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-[var(--primary)] rounded-full blur-[140px] opacity-35 pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-indigo-600 rounded-full blur-[150px] opacity-30 pointer-events-none"></div>

        <!-- Top Spacer -->
        <div></div>

        <!-- Center: Floating Product Showcase Mockup -->
        <div class="relative z-10 my-auto py-10 max-w-xl">
            <h1 class="font-display text-4xl xl:text-5xl font-extrabold tracking-tight text-white leading-tight mb-4">
                Gestión inteligente, <br/>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">productividad sin límites.</span>
            </h1>
            <p class="text-white/70 text-base leading-relaxed mb-8 max-w-lg font-normal">
                Coordina equipos, supervisa campañas y visualiza balances financieros en una plataforma diseñada para agencias de alto impacto.
            </p>

            <!-- Floating Glass Widget 1: Live Campaign Performance -->
            <div class="glass-card rounded-2xl p-5 mb-4 max-w-md shadow-2xl animate-float-1">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
                            <i class="ph-bold ph-trend-up text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-white/60 uppercase tracking-wider">Crecimiento Mensual</div>
                            <div class="text-lg font-extrabold text-white">S/ 48,250.00 <span class="text-xs font-bold text-emerald-400 ml-1">+34.8%</span></div>
                        </div>
                    </div>
                    <span class="text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2.5 py-1 rounded-full">En tiempo real</span>
                </div>
                <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-400 to-teal-300 h-full rounded-full" style="width: 78%;"></div>
                </div>
            </div>

            <!-- Floating Glass Widget 2: Romita AI Co-Pilot Active -->
            <div class="glass-card rounded-2xl p-4 max-w-sm ml-auto shadow-2xl animate-float-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/25 border border-indigo-400/30 flex items-center justify-center text-indigo-300 text-xl flex-shrink-0">
                        <i class="ph-fill ph-sparkle"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span>Romita AI Co-Pilot</span>
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        </div>
                        <div class="text-[12px] text-white/70 truncate">Generando grilla de contenidos para 3 marcas...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Trust Indicators -->
        <div class="relative z-10 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-white/60">
            <div class="flex items-center gap-2">
                <i class="ph-bold ph-lock-key text-emerald-400"></i>
                <span>Cifrado TLS 1.3 de Extremo a Extremo</span>
            </div>
            <div class="flex items-center gap-2">
                <i class="ph-bold ph-lightning text-yellow-400"></i>
                <span>99.98% Disponibilidad Garantizada</span>
            </div>
        </div>
    </section>

    <!-- RIGHT PANEL: Authentication Form Zone -->
    <section class="w-full lg:w-5/12 min-h-screen min-h-[100dvh] flex-1 flex flex-col justify-between items-center p-5 sm:p-8 xl:p-14 bg-[var(--bg-card)] relative">
        
        <!-- Mobile Header Bar: Theme Toggle -->
        <div class="w-full max-w-[420px] flex justify-end items-center mb-3 lg:hidden">
            <button type="button" onclick="toggleTheme()" class="w-9 h-9 rounded-full flex items-center justify-center border border-[var(--border)] bg-[var(--bg-surface)] text-[var(--text-main)] shadow-sm active:scale-95 transition-all cursor-pointer" title="Cambiar tema (Claro/Oscuro)">
                <i class="ph ph-sun text-base hidden mobile-icon-light"></i>
                <i class="ph ph-moon text-base mobile-icon-dark"></i>
            </button>
        </div>

        <div class="w-full max-w-[420px] mx-auto my-auto">
            
            <!-- Segmented Control Tab Switcher (iOS / Mac Style) -->
            <div class="bg-[var(--tab-bg)] p-1 rounded-xl flex items-center mb-6 relative border border-[var(--border)]">
                <button type="button" id="tabBtnAgency" onclick="switchAuthTab('agency')" class="flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 bg-[var(--bg-surface)] text-[var(--text-main)] shadow-sm">
                    <i class="ph-bold ph-briefcase"></i>
                    <span>Equipo Agencia</span>
                </button>
                <button type="button" id="tabBtnClient" onclick="switchAuthTab('client')" class="flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 text-[var(--text-muted)] hover:text-[var(--text-main)]">
                    <i class="ph-bold ph-user-circle"></i>
                    <span>Soy Cliente</span>
                </button>
            </div>

            <!-- Error Banner -->
            <?php if ($error): ?>
                <div class="mb-5 p-3.5 rounded-xl text-xs sm:text-sm font-medium flex items-start gap-2.5 bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400">
                    <i class="ph-fill ph-warning-circle text-lg flex-shrink-0 mt-0.5"></i>
                    <div class="flex-1"><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <!-- FORM 1: Equipo Agencia (Email & Password) -->
            <form id="formAgency" action="index.php?module=auth&action=login" method="POST" class="space-y-4">
                <?php echo csrf_field(); ?>

                <!-- Email Input -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] mb-1.5" for="email">
                        Correo Corporativo
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-muted)] text-lg pointer-events-none">
                            <i class="ph ph-envelope-simple"></i>
                        </span>
                        <input type="email" id="email" name="email" class="w-full pl-11 pr-4 py-3.5 rounded-xl modern-input text-sm font-medium outline-none" placeholder="nombre@romaagencia.com" required autocomplete="email" />
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]" for="password">
                            Contraseña
                        </label>
                        <span class="text-[11px] text-[var(--text-muted)] hover:text-[var(--primary)] cursor-pointer select-none">¿Olvidaste tu clave?</span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-muted)] text-lg pointer-events-none">
                            <i class="ph ph-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" class="w-full pl-11 pr-11 py-3.5 rounded-xl modern-input text-sm font-medium outline-none" placeholder="••••••••••••" required autocomplete="current-password" />
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[var(--text-muted)] hover:text-[var(--text-main)] text-lg transition-colors" title="Mostrar/Ocultar contraseña">
                            <i class="ph ph-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="remember" name="remember" checked class="w-4 h-4 rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)] accent-[var(--primary)]" />
                        <span class="text-xs font-medium text-[var(--text-muted)]">Recordar en este equipo</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="btnSubmitLogin" class="w-full py-3.5 rounded-xl font-display font-bold text-sm btn-primary-glow flex items-center justify-center gap-2 cursor-pointer mt-2">
                    <span>Ingresar a la Plataforma</span>
                    <i class="ph-bold ph-arrow-right"></i>
                </button>

                <!-- Biometric Divider -->
                <div class="relative py-2 flex items-center justify-center">
                    <div class="w-full border-t border-[var(--border)]"></div>
                    <span class="absolute bg-[var(--bg-card)] px-3 text-[11px] uppercase tracking-widest text-[var(--text-muted)] font-semibold">O rápido con</span>
                </div>

                <!-- WebAuthn Biometrics Button -->
                <button type="button" onclick="loginWithBiometrics()" class="w-full py-3 rounded-xl font-display font-semibold text-xs sm:text-sm biometric-btn flex items-center justify-center gap-2.5 cursor-pointer">
                    <i class="ph-bold ph-fingerprint text-emerald-500 text-lg"></i>
                    <span>Acceder con Huella Digital / FaceID</span>
                </button>
            </form>

            <!-- FORM 2: Portal de Clientes (Fintech Keypad) -->
            <div id="formClient" class="hidden space-y-5">
                <div class="text-center">
                    <p class="text-xs text-[var(--text-muted)]">Ingresa tu número de documento para consultar tus proyectos, entregables y pagos.</p>
                </div>

                <!-- DNI Interactive Display Slot Boxes -->
                <div class="flex justify-center gap-1.5 sm:gap-2 my-2" id="dniSlotsContainer">
                    <div class="dni-slot active" id="slot-0">-</div>
                    <div class="dni-slot" id="slot-1">-</div>
                    <div class="dni-slot" id="slot-2">-</div>
                    <div class="dni-slot" id="slot-3">-</div>
                    <div class="dni-slot" id="slot-4">-</div>
                    <div class="dni-slot" id="slot-5">-</div>
                    <div class="dni-slot" id="slot-6">-</div>
                    <div class="dni-slot" id="slot-7">-</div>
                </div>

                <!-- Numeric Keypad (Fintech Style) -->
                <div class="grid grid-cols-3 gap-2.5 max-w-[280px] mx-auto">
                    <?php for($i = 1; $i <= 9; $i++): ?>
                        <button type="button" onclick="typeDigit('<?php echo $i; ?>')" class="h-14 rounded-2xl bg-[var(--keypad-btn)] hover:bg-[var(--keypad-btn-hover)] text-[var(--keypad-text)] font-display font-bold text-xl active:scale-90 transition-all flex items-center justify-center border border-[var(--border)] shadow-sm">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    <button type="button" onclick="deleteDigit()" class="h-14 rounded-2xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 font-display font-bold text-xl active:scale-90 transition-all flex items-center justify-center border border-rose-500/20" title="Borrar">
                        <i class="ph-bold ph-backspace"></i>
                    </button>
                    <button type="button" onclick="typeDigit('0')" class="h-14 rounded-2xl bg-[var(--keypad-btn)] hover:bg-[var(--keypad-btn-hover)] text-[var(--keypad-text)] font-display font-bold text-xl active:scale-90 transition-all flex items-center justify-center border border-[var(--border)] shadow-sm">
                        0
                    </button>
                    <button type="button" id="btnClientSubmit" onclick="submitClientLogin()" class="h-14 rounded-2xl btn-primary-glow font-display font-bold text-xl active:scale-90 transition-all flex items-center justify-center shadow-lg" title="Ingresar">
                        <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer Security Guarantee -->
        <div class="w-full max-w-[420px] mx-auto mt-6 pt-4 border-t border-[var(--border)] text-center">
            <div class="inline-flex items-center gap-1.5 text-[11px] text-[var(--text-muted)] font-medium">
                <i class="ph-fill ph-shield-check text-emerald-500 text-sm"></i>
                <span>Sesión Blindada con Protección Anti-Fuerza Bruta &bull; Roma Shield</span>
            </div>
        </div>
    </section>
</main>

<script>
    // --- Theme Switcher (Dark / Light) ---
    function initTheme() {
        const savedTheme = localStorage.getItem('roma_theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
        applyTheme(theme);
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('roma_theme', theme);
        const iconLight = document.getElementById('iconLight');
        const iconDark = document.getElementById('iconDark');
        const mobileIconLight = document.querySelector('.mobile-icon-light');
        const mobileIconDark = document.querySelector('.mobile-icon-dark');
        const logosLight = document.querySelectorAll('.logo-for-light');
        const logosDark = document.querySelectorAll('.logo-for-dark');

        if (theme === 'dark') {
            if (iconLight) iconLight.classList.remove('hidden');
            if (iconDark) iconDark.classList.add('hidden');
            if (mobileIconLight) mobileIconLight.classList.remove('hidden');
            if (mobileIconDark) mobileIconDark.classList.add('hidden');
            logosLight.forEach(el => el.classList.add('hidden'));
            logosDark.forEach(el => el.classList.remove('hidden'));
        } else {
            if (iconLight) iconLight.classList.add('hidden');
            if (iconDark) iconDark.classList.remove('hidden');
            if (mobileIconLight) mobileIconLight.classList.add('hidden');
            if (mobileIconDark) mobileIconDark.classList.remove('hidden');
            logosLight.forEach(el => el.classList.remove('hidden'));
            logosDark.forEach(el => el.classList.add('hidden'));
        }
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(newTheme);
    }

    initTheme();

    // --- Tab Switching Logic ---
    function switchAuthTab(tab) {
        const tabAgency = document.getElementById('tabBtnAgency');
        const tabClient = document.getElementById('tabBtnClient');
        const formAgency = document.getElementById('formAgency');
        const formClient = document.getElementById('formClient');

        if (tab === 'agency') {
            tabAgency.className = 'flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 bg-[var(--bg-surface)] text-[var(--text-main)] shadow-sm';
            tabClient.className = 'flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 text-[var(--text-muted)] hover:text-[var(--text-main)]';
            formAgency.classList.remove('hidden');
            formClient.classList.add('hidden');
        } else {
            tabClient.className = 'flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 bg-[var(--bg-surface)] text-[var(--text-main)] shadow-sm';
            tabAgency.className = 'flex-1 py-2.5 px-3 sm:px-4 rounded-lg text-xs sm:text-sm font-semibold whitespace-nowrap transition-all duration-200 flex items-center justify-center gap-2 text-[var(--text-muted)] hover:text-[var(--text-main)]';
            formAgency.classList.add('hidden');
            formClient.classList.remove('hidden');
        }
    }

    // --- Password Visibility Toggle ---
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'ph ph-eye-slash';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'ph ph-eye';
        }
    }

    // --- DNI Keypad Logic ---
    let dniValue = '';

    function updateDniSlots() {
        for (let i = 0; i < 8; i++) {
            const slot = document.getElementById('slot-' + i);
            if (!slot) continue;
            if (i < dniValue.length) {
                slot.textContent = dniValue[i];
                slot.className = 'dni-slot filled';
            } else if (i === dniValue.length) {
                slot.textContent = '-';
                slot.className = 'dni-slot active';
            } else {
                slot.textContent = '-';
                slot.className = 'dni-slot';
            }
        }
    }

    function typeDigit(digit) {
        if (dniValue.length < 8) {
            dniValue += digit;
            updateDniSlots();
        }
    }

    function deleteDigit() {
        if (dniValue.length > 0) {
            dniValue = dniValue.slice(0, -1);
            updateDniSlots();
        }
    }

    function submitClientLogin() {
        if (dniValue.length < 8) {
            alert('Por favor ingresa los 8 dígitos de tu documento.');
            return;
        }

        const btn = document.getElementById('btnClientSubmit');
        const oldContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin text-xl"></i>';

        fetch('ajax_portal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&dni=${encodeURIComponent(dniValue)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'portal.php';
            } else {
                alert(data.error || 'Documento no encontrado o sin acceso al portal.');
                dniValue = '';
                updateDniSlots();
                btn.disabled = false;
                btn.innerHTML = oldContent;
            }
        })
        .catch(e => {
            alert('Error de conexión con el servidor. Intenta de nuevo.');
            btn.disabled = false;
            btn.innerHTML = oldContent;
        });
    }

    // --- WebAuthn Biometrics Login Logic ---
    function base64ToArrayBuffer(base64) {
        var binary_string = window.atob(base64);
        var len = binary_string.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function arrayBufferToBase64(buffer) {
        var binary = '';
        var bytes = new Uint8Array(buffer);
        var len = bytes.byteLength;
        for (var i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function decodeArgs(obj) {
        const prefix = '=?BINARY?B?';
        const suffix = '?=';
        if (typeof obj === 'string') {
            if (obj.startsWith(prefix) && obj.endsWith(suffix)) {
                let b64 = obj.substring(prefix.length, obj.length - suffix.length);
                b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
                return base64ToArrayBuffer(b64);
            }
        } else if (typeof obj === 'object' && obj !== null) {
            for (let key in obj) {
                obj[key] = decodeArgs(obj[key]);
            }
        }
        return obj;
    }

    async function loginWithBiometrics() {
        if (!window.PublicKeyCredential) {
            alert("Autenticación biométrica no soportada en este dispositivo/navegador.");
            return;
        }

        const emailField = document.getElementById('email');
        if (!emailField || !emailField.value.trim()) {
            alert('Por favor escribe tu correo electrónico primero para identificar tu usuario.');
            if (emailField) emailField.focus();
            return;
        }

        try {
            const loginDataInit = new URLSearchParams();
            loginDataInit.append('action', 'get_login_args');
            loginDataInit.append('email', emailField.value.trim());

            const res = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: loginDataInit.toString()
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                alert("Error de comunicación con el servidor biométrico.");
                return;
            }
            
            if (data.error) {
                alert(data.error);
                return;
            }

            const args = decodeArgs(data.args);
            if (args.publicKey && args.publicKey.extensions) {
                delete args.publicKey.extensions;
            }
            
            const credential = await navigator.credentials.get(args);
            
            const loginData = new URLSearchParams();
            loginData.append('action', 'process_login');
            if (credential.rawId) {
                loginData.append('id', arrayBufferToBase64(credential.rawId));
            } else {
                loginData.append('id', credential.id);
            }
            loginData.append('clientDataJSON', arrayBufferToBase64(credential.response.clientDataJSON));
            loginData.append('authenticatorData', arrayBufferToBase64(credential.response.authenticatorData));
            loginData.append('signature', arrayBufferToBase64(credential.response.signature));
            if (credential.response.userHandle) {
                loginData.append('userHandle', arrayBufferToBase64(credential.response.userHandle));
            }

            const verifyRes = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: loginData.toString()
            });
            
            const verifyText = await verifyRes.text();
            let verifyData = JSON.parse(verifyText);

            if (verifyData.success) {
                window.location.href = 'index.php?module=dashboard&action=index';
            } else {
                alert(verifyData.error || 'Falló la verificación biométrica.');
            }
        } catch (e) {
            console.error('Error biométrico login:', e);
            if (e.name !== 'NotAllowedError') {
                alert('No se pudo completar la verificación biométrica: ' + e.message);
            }
        }
    }
</script>
</body>
</html>
