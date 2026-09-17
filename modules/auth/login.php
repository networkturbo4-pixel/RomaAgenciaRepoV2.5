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

// Detectar si la petición es AJAX
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (isset($_POST['csrf_token']) && !csrf_validate($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor recargue la página.';
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $error]);
            exit();
        }
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

                if ($is_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'user_name' => $user['name'],
                        'redirect' => 'index.php?module=dashboard&action=index'
                    ]);
                    exit();
                }

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

                if ($is_ajax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit();
                }
            }
        } else {
            $error = 'Por favor ingrese su correo y contraseña.';
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $error]);
                exit();
            }
        }
    }
}
?>
<?php 
global $global_settings; 
$site_name_seo = $global_settings['site_name'] ?? 'Roma Agencia';
$seo_title = $site_name_seo . ($global_settings['seo_title_suffix'] ?? ' | Gestión Integral para su Empresa');
$seo_desc = $global_settings['seo_description'] ?? 'Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo.';
$seo_keys = $global_settings['seo_keywords'] ?? 'CRM, Gestión de Proyectos, Análisis de Datos, Productividad, Agencia';
$primaryColor = $global_settings['primary_color'] ?? '#0f172a';

// Canales y contacto desde configuración
$company_email = !empty($global_settings['company_email']) ? $global_settings['company_email'] : 'agencia@romaagencia.com';
$company_whatsapp = !empty($global_settings['company_whatsapp']) ? $global_settings['company_whatsapp'] : '';

$social_fb = !empty($global_settings['social_facebook']) ? $global_settings['social_facebook'] : '#';
$social_ig = !empty($global_settings['social_instagram']) ? $global_settings['social_instagram'] : '#';

// Manejo inteligente de TikTok y LinkedIn
$social_tt = !empty($global_settings['social_tiktok']) ? $global_settings['social_tiktok'] : '';
$social_li = !empty($global_settings['social_linkedin']) ? $global_settings['social_linkedin'] : '';
if (empty($social_tt) && !empty($social_li) && strpos($social_li, 'tiktok') !== false) {
    $social_tt = $social_li;
    $social_li = '#';
}
if (empty($social_tt)) $social_tt = '#';
if (empty($social_li)) $social_li = '#';

$logo_light = !empty($global_settings['logo_light']) ? $global_settings['logo_light'] : '';
$logo_dark = !empty($global_settings['logo_dark']) ? $global_settings['logo_dark'] : '';
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['selector', '[data-theme="dark"]']
        };
    </script>

    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primaryColor); ?>;
            --primary-glow: color-mix(in srgb, var(--primary) 25%, transparent);
            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-serif: 'Instrument Serif', Georgia, serif;
        }

        /* High-Contrast Design Tokens */
        [data-theme="dark"] {
            --bg-body: #09090b;
            --card-bg: rgba(18, 18, 24, 0.96);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-title: #ffffff;
            --text-subtitle: #a1a1aa;
            --text-label: #f4f4f5;
            --text-muted: #a1a1aa;
            --contact-bg: rgba(26, 26, 34, 0.9);
            --contact-border: rgba(255, 255, 255, 0.12);
            --social-btn-bg: #27272a;
            --social-btn-border: rgba(255, 255, 255, 0.15);
            --tiktok-color: #ffffff;
            --input-bg: #18181f;
            --input-border: #3f3f46;
            --input-text: #ffffff;
            --input-placeholder: #71717a;
            --tab-bg: rgba(24, 24, 30, 0.95);
            --tab-border: rgba(255, 255, 255, 0.12);
            --tab-active-bg: #27272a;
            --tab-active-text: #ffffff;
            --tab-inactive-text: #a1a1aa;
            --keypad-btn: #27272a;
            --keypad-btn-hover: #3f3f46;
            --keypad-text: #ffffff;
            --security-text: #a1a1aa;
        }

        [data-theme="light"] {
            --bg-body: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-title: #0f172a;
            --text-subtitle: #475569;
            --text-label: #1e293b;
            --text-muted: #64748b;
            --contact-bg: #f8fafc;
            --contact-border: #e2e8f0;
            --social-btn-bg: #ffffff;
            --social-btn-border: #cbd5e1;
            --tiktok-color: #000000;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            --input-placeholder: #94a3b8;
            --tab-bg: #f1f5f9;
            --tab-border: #e2e8f0;
            --tab-active-bg: #ffffff;
            --tab-active-text: #0f172a;
            --tab-inactive-text: #64748b;
            --keypad-btn: #ffffff;
            --keypad-btn-hover: #f1f5f9;
            --keypad-text: #0f172a;
            --security-text: #475569;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-title);
        }

        .font-display {
            font-family: var(--font-display);
        }

        .font-quote-serif {
            font-family: var(--font-serif);
        }

        /* Full Background Art Landscape */
        .art-background-container {
            background-image: url('assets/img/login_art_bg.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }

        /* Bottom Defocus Blur Overlay */
        .bottom-defocus-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 48%;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.72) 0%, rgba(0, 0, 0, 0.35) 45%, transparent 100%);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            mask-image: linear-gradient(to top, rgba(0,0,0,1) 35%, rgba(0,0,0,0) 100%);
            -webkit-mask-image: linear-gradient(to top, rgba(0,0,0,1) 35%, rgba(0,0,0,0) 100%);
            pointer-events: none;
            z-index: 10;
        }

        /* Ambient Top Fade for Header Legibility */
        .top-subtle-gradient {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 140px;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.45) 0%, transparent 100%);
            pointer-events: none;
            z-index: 10;
        }

        /* Floating Card Styling */
        .login-card-shadow {
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.35), 0 0 0 1px var(--card-border);
        }

        /* Modern High-Contrast Input Styling */
        .login-input {
            background-color: var(--input-bg);
            border: 1.5px solid var(--input-border);
            color: var(--input-text);
            font-size: 0.9375rem;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .login-input::placeholder {
            color: var(--input-placeholder);
            font-weight: 400;
        }
        .login-input:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 3.5px rgba(15, 23, 42, 0.12);
            background-color: var(--input-bg);
        }
        [data-theme="dark"] .login-input:focus {
            border-color: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(255, 255, 255, 0.18);
        }

        /* DNI Slot Boxes */
        .dni-slot {
            width: 32px;
            height: 42px;
            border-radius: 10px;
            border: 1.5px solid var(--input-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            background: var(--input-bg);
            color: var(--text-title);
            transition: all 0.15s ease;
        }
        .dni-slot.active {
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15);
            transform: scale(1.04);
        }
        [data-theme="dark"] .dni-slot.active {
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }
        .dni-slot.filled {
            border-color: #0f172a;
            color: var(--text-title);
            background-color: var(--input-bg);
        }
        [data-theme="dark"] .dni-slot.filled {
            border-color: #ffffff;
        }

        /* Wiggle Hand Animation */
        @keyframes handWiggle {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(14deg); }
            40% { transform: rotate(-10deg); }
            60% { transform: rotate(14deg); }
            80% { transform: rotate(-4deg); }
        }
        .animate-wiggle {
            display: inline-block;
            transform-origin: 70% 70%;
            animation: handWiggle 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen min-h-[100dvh] art-background-container relative overflow-x-hidden selection:bg-black selection:text-white flex flex-col justify-between">

    <!-- Subtle Gradients for Legibility -->
    <div class="top-subtle-gradient"></div>
    <div class="bottom-defocus-overlay"></div>

    <!-- TOP BAR: Solo el Logotipo Corporativo y Conmutador de Tema -->
    <header class="relative z-20 w-full px-6 sm:px-10 lg:px-12 pt-6 sm:pt-8 flex items-center justify-between">
        <!-- Solo Logo (Sin texto redundante) -->
        <div class="flex items-center">
            <?php if (!empty($logo_light)): ?>
                <img src="<?php echo htmlspecialchars($logo_light); ?>" alt="<?php echo htmlspecialchars($site_name_seo); ?>" class="h-8 sm:h-9 w-auto object-contain drop-shadow-lg brightness-0 invert" />
            <?php else: ?>
                <div class="flex items-center gap-2 text-white font-display font-extrabold text-xl tracking-tight drop-shadow-md">
                    <i class="ph-fill ph-crown text-amber-300 text-2xl"></i>
                    <span><?php echo htmlspecialchars($site_name_seo); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Controls: Theme Toggle Button -->
        <div class="flex items-center gap-3">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="w-10 h-10 rounded-full flex items-center justify-center backdrop-blur-md bg-black/40 hover:bg-black/60 border border-white/25 text-white shadow-lg active:scale-95 transition-all cursor-pointer" title="Cambiar tema (Claro/Oscuro)">
                <i class="ph ph-sun text-lg hidden" id="iconLight"></i>
                <i class="ph ph-moon text-lg" id="iconDark"></i>
            </button>
        </div>
    </header>

    <!-- MAIN VIEWPORT: Content Grid with Bottom Quote & Floating Login Card -->
    <main class="relative z-20 flex-1 w-full max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 py-6 sm:py-10 flex flex-col lg:flex-row items-center justify-between gap-10">

        <!-- LEFT SIDE: Frase Motivadora en Español -->
        <div class="hidden lg:flex flex-col justify-end self-end max-w-xl pb-6 select-none">
            <h1 class="font-display text-4xl xl:text-5xl font-extrabold text-white leading-[1.14] tracking-tight drop-shadow-[0_4px_18px_rgba(0,0,0,0.6)]">
                Convertimos tu<br/>
                proyecto soñado en una <span class="font-quote-serif italic font-normal text-amber-200/95">realidad</span>
            </h1>
            <p class="text-white/90 text-sm mt-3 font-medium drop-shadow-md max-w-md">
                Plataforma de alta productividad para coordinar proyectos, entregables y finanzas en tiempo real.
            </p>
        </div>

        <!-- RIGHT SIDE: The Redesigned Floating Login Card (High Contrast) -->
        <div class="w-full max-w-[430px] lg:ml-auto">
            <div class="rounded-[2.2rem] p-6 sm:p-8 login-card-shadow transition-all duration-300" style="background-color: var(--card-bg);">
                
                <!-- 1. SALUDO SUPERIOR -->
                <div class="mb-5">
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-tight flex items-center gap-2" style="color: var(--text-title);">
                        <span>¡Bienvenido!</span>
                        <span class="animate-wiggle text-2xl sm:text-3xl">👋</span>
                    </h2>
                    <p class="text-xs font-medium mt-1" style="color: var(--text-subtitle);">
                        Ingresa tus credenciales oficiales para continuar.
                    </p>
                </div>

                <!-- 2. CARD DE CORREO DE CONTACTO Y REDES SOCIALES (Alto Contraste) -->
                <div class="rounded-2xl p-3.5 flex items-center justify-between gap-3 shadow-xs mb-4" style="background-color: var(--contact-bg); border: 1.5px solid var(--contact-border);">
                    <!-- Email de Contacto Oficial -->
                    <div class="min-w-0 flex-1 pl-1">
                        <span class="block text-[11px] font-bold uppercase tracking-wider leading-none mb-1.5" style="color: var(--text-muted);">Escríbenos a</span>
                        <a href="mailto:<?php echo htmlspecialchars($company_email); ?>" class="text-xs sm:text-[13px] font-bold text-blue-600 hover:text-blue-700 dark:text-sky-400 dark:hover:text-sky-300 hover:underline truncate block" title="<?php echo htmlspecialchars($company_email); ?>">
                            <?php echo htmlspecialchars($company_email); ?>
                        </a>
                    </div>

                    <!-- Iconos Redes Sociales (Facebook, Instagram, TikTok, LinkedIn) -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <!-- Facebook -->
                        <a href="<?php echo htmlspecialchars($social_fb); ?>" target="<?php echo $social_fb !== '#' ? '_blank' : '_self'; ?>" rel="noopener noreferrer" class="w-8 h-8 rounded-xl flex items-center justify-center text-[#1877F2] hover:scale-105 active:scale-95 transition-all shadow-xs" style="background-color: var(--social-btn-bg); border: 1.5px solid var(--social-btn-border);" title="Facebook">
                            <i class="ph-bold ph-facebook-logo text-base"></i>
                        </a>

                        <!-- Instagram -->
                        <a href="<?php echo htmlspecialchars($social_ig); ?>" target="<?php echo $social_ig !== '#' ? '_blank' : '_self'; ?>" rel="noopener noreferrer" class="w-8 h-8 rounded-xl flex items-center justify-center text-[#E4405F] hover:scale-105 active:scale-95 transition-all shadow-xs" style="background-color: var(--social-btn-bg); border: 1.5px solid var(--social-btn-border);" title="Instagram">
                            <i class="ph-bold ph-instagram-logo text-base"></i>
                        </a>

                        <!-- TikTok -->
                        <a href="<?php echo htmlspecialchars($social_tt); ?>" target="<?php echo $social_tt !== '#' ? '_blank' : '_self'; ?>" rel="noopener noreferrer" class="w-8 h-8 rounded-xl flex items-center justify-center hover:scale-105 active:scale-95 transition-all shadow-xs" style="background-color: var(--social-btn-bg); border: 1.5px solid var(--social-btn-border); color: var(--tiktok-color);" title="TikTok">
                            <svg class="w-4 h-4" style="fill: var(--tiktok-color);" viewBox="0 0 24 24">
                                <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-2.887 2.766 2.896 2.896 0 0 1-2.896-2.896 2.896 2.896 0 0 1 2.896-2.896c.328 0 .641.055.932.156V9.28a6.34 6.34 0 0 0-.932-.07 6.346 6.346 0 0 0-6.34 6.347 6.346 6.346 0 0 0 6.34 6.346 6.346 6.346 0 0 0 6.34-6.346V9.014a8.217 8.217 0 0 0 4.76 1.488V7.057a4.774 4.774 0 0 1-1.002-.371z"/>
                            </svg>
                        </a>

                        <!-- LinkedIn -->
                        <a href="<?php echo htmlspecialchars($social_li); ?>" target="<?php echo $social_li !== '#' ? '_blank' : '_self'; ?>" rel="noopener noreferrer" class="w-8 h-8 rounded-xl flex items-center justify-center text-[#0A66C2] hover:scale-105 active:scale-95 transition-all shadow-xs" style="background-color: var(--social-btn-bg); border: 1.5px solid var(--social-btn-border);" title="LinkedIn">
                            <i class="ph-bold ph-linkedin-logo text-base"></i>
                        </a>
                    </div>
                </div>

                <!-- 3. SEPARADOR OR -->
                <div class="relative my-4 text-center">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t" style="border-color: var(--contact-border);"></div>
                    </div>
                    <span class="relative px-3 text-[10px] font-extrabold uppercase tracking-widest" style="background-color: var(--card-bg); color: var(--text-muted);">OR</span>
                </div>

                <!-- 4. BOTÓN SWITCHER: EQUIPO AGENCIA / SOY CLIENTE -->
                <div class="p-1 rounded-xl flex items-center mb-4.5" style="background-color: var(--tab-bg); border: 1.5px solid var(--tab-border);">
                    <button type="button" id="tabBtnAgency" onclick="switchAuthTab('agency')" class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all duration-200 flex items-center justify-center gap-1.5 shadow-xs border" style="background-color: var(--tab-active-bg); color: var(--tab-active-text); border-color: var(--tab-border);">
                        <i class="ph-bold ph-briefcase text-sm"></i>
                        <span>Equipo Agencia</span>
                    </button>
                    <button type="button" id="tabBtnClient" onclick="switchAuthTab('client')" class="flex-1 py-2 px-3 rounded-lg text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5 border border-transparent" style="color: var(--tab-inactive-text); background-color: transparent;">
                        <i class="ph-bold ph-user-circle text-sm"></i>
                        <span>Soy Cliente</span>
                    </button>
                </div>

                <!-- BANNER DE ERROR (Dinámico y PHP) -->
                <div id="loginErrorBanner" class="<?php echo $error ? 'flex' : 'hidden'; ?> mb-4 p-3 rounded-xl text-xs font-semibold items-start gap-2 bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400">
                    <i class="ph-fill ph-warning-circle text-base flex-shrink-0 mt-0.5"></i>
                    <div class="flex-1" id="loginErrorMessage"><?php echo htmlspecialchars($error); ?></div>
                </div>

                <!-- 5. FORMULARIO 1: EQUIPO AGENCIA -->
                <form id="formAgency" onsubmit="handleAgencyLogin(event)" method="POST" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="ajax" value="1">

                    <!-- Correo Corporativo -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-label);" for="email">
                            Correo Corporativo
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none" style="color: var(--text-muted);">
                                <i class="ph-bold ph-envelope-simple"></i>
                            </span>
                            <input type="email" id="email" name="email" class="w-full pl-10 pr-4 py-3 rounded-xl login-input outline-none" placeholder="nombre@romaagencia.com" required autocomplete="email" />
                        </div>
                    </div>

                    <!-- Contraseña -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider" style="color: var(--text-label);" for="password">
                                Contraseña
                            </label>
                            <span class="text-xs font-semibold hover:underline cursor-pointer select-none" style="color: var(--text-muted);">¿Olvidaste tu clave?</span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-lg pointer-events-none" style="color: var(--text-muted);">
                                <i class="ph-bold ph-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" class="w-full pl-10 pr-11 py-3 rounded-xl login-input outline-none" placeholder="••••••••••••" required autocomplete="current-password" />
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-lg transition-colors cursor-pointer" style="color: var(--text-muted);" title="Mostrar/Ocultar contraseña">
                                <i class="ph-bold ph-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Recordar Sesión -->
                    <div class="flex items-center justify-between pt-0.5">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" id="remember" name="remember" checked class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black accent-black cursor-pointer" />
                            <span class="text-xs font-semibold" style="color: var(--text-label);">Recordar en este equipo</span>
                        </label>
                    </div>

                    <!-- Botón Iniciar Sesión (Alto Impacto) -->
                    <button type="submit" id="btnSubmitLogin" class="w-full py-3.5 px-4 rounded-xl font-display font-bold text-sm text-white bg-zinc-950 hover:bg-zinc-800 active:scale-[0.99] transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg mt-2">
                        <span id="btnSubmitText">Ingresar a la Plataforma</span>
                        <i class="ph-bold ph-arrow-right" id="btnSubmitIcon"></i>
                    </button>
                </form>

                <!-- 6. FORMULARIO 2: SOY CLIENTE (Keypad Fintech DNI) -->
                <div id="formClient" class="hidden space-y-4">
                    <div class="text-center">
                        <p class="text-xs font-semibold" style="color: var(--text-subtitle);">Ingresa tu número de documento para consultar proyectos, entregables y pagos.</p>
                    </div>

                    <!-- Slots DNI -->
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

                    <!-- Teclado Numérico -->
                    <div class="grid grid-cols-3 gap-2 max-w-[260px] mx-auto">
                        <?php for($i = 1; $i <= 9; $i++): ?>
                            <button type="button" onclick="typeDigit('<?php echo $i; ?>')" class="h-12 rounded-xl text-[var(--keypad-text)] font-display font-bold text-lg active:scale-90 transition-all flex items-center justify-center shadow-xs cursor-pointer border" style="background-color: var(--keypad-btn); border-color: var(--contact-border);">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                        <button type="button" onclick="deleteDigit()" class="h-12 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 font-display font-bold text-lg active:scale-90 transition-all flex items-center justify-center border border-rose-500/20 cursor-pointer" title="Borrar">
                            <i class="ph-bold ph-backspace"></i>
                        </button>
                        <button type="button" onclick="typeDigit('0')" class="h-12 rounded-xl text-[var(--keypad-text)] font-display font-bold text-lg active:scale-90 transition-all flex items-center justify-center shadow-xs cursor-pointer border" style="background-color: var(--keypad-btn); border-color: var(--contact-border);">
                            0
                        </button>
                        <button type="button" id="btnClientSubmit" onclick="submitClientLogin()" class="h-12 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-white font-display font-bold text-lg active:scale-90 transition-all flex items-center justify-center shadow-md cursor-pointer" title="Ingresar">
                            <i class="ph-bold ph-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Footer Garantía de Seguridad -->
                <div class="mt-5 pt-3.5 border-t text-center" style="border-color: var(--contact-border);">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold" style="color: var(--security-text);">
                        <i class="ph-fill ph-shield-check text-emerald-500 text-sm"></i>
                        <span>Sesión Segura &bull; Roma Shield TLS 1.3</span>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- MOBILE MOTIVATING PHRASE FOOTER (Visible on mobile/tablet) -->
    <div class="lg:hidden relative z-20 px-6 py-4 text-center select-none">
        <h3 class="font-display text-lg font-bold text-white leading-tight drop-shadow">
            Convertimos tu proyecto soñado en una <span class="font-quote-serif italic font-normal text-amber-200">realidad</span>
        </h3>
    </div>

    <!-- 7. OVERLAY TRANSICIÓN A BLANCO Y SALUDO PERSONALIZADO -->
    <div id="whiteWelcomeOverlay" class="fixed inset-0 z-[100] bg-white flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-700 ease-out">
        <div class="text-center px-6 transform translate-y-6 transition-transform duration-700 ease-out" id="whiteWelcomeBox">
            <!-- Icono de Saludo -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-zinc-100 text-zinc-900 mb-5 shadow-sm border border-zinc-200/80">
                <i class="ph-fill ph-hand-waving text-3xl animate-wiggle text-amber-500"></i>
            </div>
            <!-- Nombre de Usuario -->
            <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-zinc-900 tracking-tight mb-2">
                Hola, <span id="welcomeUserName" class="text-zinc-950 font-black"></span> 👋
            </h1>
            <p class="text-zinc-500 text-sm font-medium flex items-center justify-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Ingresando a tu panel de control...</span>
            </p>
        </div>
    </div>

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

        if (theme === 'dark') {
            if (iconLight) iconLight.classList.remove('hidden');
            if (iconDark) iconDark.classList.add('hidden');
        } else {
            if (iconLight) iconLight.classList.add('hidden');
            if (iconDark) iconDark.classList.remove('hidden');
        }
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(newTheme);
    }

    initTheme();

    // --- Tab Switcher (Equipo Agencia / Soy Cliente) ---
    function switchAuthTab(tab) {
        const tabAgency = document.getElementById('tabBtnAgency');
        const tabClient = document.getElementById('tabBtnClient');
        const formAgency = document.getElementById('formAgency');
        const formClient = document.getElementById('formClient');
        const errorBanner = document.getElementById('loginErrorBanner');
        if (errorBanner) errorBanner.classList.add('hidden');

        if (tab === 'agency') {
            tabAgency.style.backgroundColor = 'var(--tab-active-bg)';
            tabAgency.style.color = 'var(--tab-active-text)';
            tabAgency.style.borderColor = 'var(--tab-border)';
            tabAgency.classList.add('shadow-xs', 'font-bold');
            tabAgency.classList.remove('font-semibold');

            tabClient.style.backgroundColor = 'transparent';
            tabClient.style.color = 'var(--tab-inactive-text)';
            tabClient.style.borderColor = 'transparent';
            tabClient.classList.remove('shadow-xs', 'font-bold');
            tabClient.classList.add('font-semibold');

            formAgency.classList.remove('hidden');
            formClient.classList.add('hidden');
        } else {
            tabClient.style.backgroundColor = 'var(--tab-active-bg)';
            tabClient.style.color = 'var(--tab-active-text)';
            tabClient.style.borderColor = 'var(--tab-border)';
            tabClient.classList.add('shadow-xs', 'font-bold');
            tabClient.classList.remove('font-semibold');

            tabAgency.style.backgroundColor = 'transparent';
            tabAgency.style.color = 'var(--tab-inactive-text)';
            tabAgency.style.borderColor = 'transparent';
            tabAgency.classList.remove('shadow-xs', 'font-bold');
            tabAgency.classList.add('font-semibold');

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
            eyeIcon.className = 'ph-bold ph-eye-slash';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'ph-bold ph-eye';
        }
    }

    // --- Welcome Screen Transition (Todo blanco y saludo) ---
    function triggerWelcomeTransition(userName, redirectUrl) {
        const overlay = document.getElementById('whiteWelcomeOverlay');
        const box = document.getElementById('whiteWelcomeBox');
        const nameEl = document.getElementById('welcomeUserName');
        
        nameEl.textContent = userName || 'Bienvenido';

        // Hacer visible el overlay blanco
        overlay.classList.remove('pointer-events-none', 'opacity-0');
        overlay.classList.add('opacity-100');

        // Levantar suavemente el box con el saludo
        setTimeout(() => {
            box.classList.remove('translate-y-6');
            box.classList.add('translate-y-0');
        }, 50);

        // Redirigir al dashboard tras un momento
        setTimeout(() => {
            window.location.href = redirectUrl || 'index.php?module=dashboard&action=index';
        }, 1400);
    }

    // --- Formulario Agencia AJAX Submission ---
    function handleAgencyLogin(event) {
        event.preventDefault();
        const form = document.getElementById('formAgency');
        const errorBanner = document.getElementById('loginErrorBanner');
        const errorMessage = document.getElementById('loginErrorMessage');
        const submitBtn = document.getElementById('btnSubmitLogin');
        const submitText = document.getElementById('btnSubmitText');
        const submitIcon = document.getElementById('btnSubmitIcon');

        // Estado cargando en el botón
        submitBtn.disabled = true;
        submitText.textContent = 'Verificando...';
        submitIcon.className = 'ph ph-spinner ph-spin text-lg';
        errorBanner.classList.add('hidden');

        const formData = new FormData(form);

        fetch('index.php?module=auth&action=login', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Activar transición de pantalla en blanco y saludo personalizado
                triggerWelcomeTransition(data.user_name, data.redirect);
            } else {
                errorMessage.textContent = data.error || 'Credenciales incorrectas.';
                errorBanner.classList.remove('hidden');
                submitBtn.disabled = false;
                submitText.textContent = 'Ingresar a la Plataforma';
                submitIcon.className = 'ph-bold ph-arrow-right';
            }
        })
        .catch(err => {
            // Fallback en caso de error de red
            form.submit();
        });
    }

    // --- DNI Keypad Logic (Soy Cliente) ---
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
            const errorBanner = document.getElementById('loginErrorBanner');
            const errorMessage = document.getElementById('loginErrorMessage');
            errorMessage.textContent = 'Por favor ingresa los 8 dígitos de tu documento.';
            errorBanner.classList.remove('hidden');
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
                triggerWelcomeTransition(data.name || 'Cliente', 'portal.php');
            } else {
                const errorBanner = document.getElementById('loginErrorBanner');
                const errorMessage = document.getElementById('loginErrorMessage');
                errorMessage.textContent = data.error || 'Documento no encontrado o sin acceso al portal.';
                errorBanner.classList.remove('hidden');
                dniValue = '';
                updateDniSlots();
                btn.disabled = false;
                btn.innerHTML = oldContent;
            }
        })
        .catch(e => {
            const errorBanner = document.getElementById('loginErrorBanner');
            const errorMessage = document.getElementById('loginErrorMessage');
            errorMessage.textContent = 'Error de conexión con el servidor. Intenta de nuevo.';
            errorBanner.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = oldContent;
        });
    }
</script>
</body>
</html>
