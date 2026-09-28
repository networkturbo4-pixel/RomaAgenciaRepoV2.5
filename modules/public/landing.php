<?php
// modules/public/landing.php
// Roma Agencia OS - Landing Page Oficial de Alto Rendimiento
$is_public = true;

// Safe Database Connection & Global Settings Retrieval
$global_settings = [];
$services_db = [];
$phone_cleaned = '';

try {
    if (file_exists(__DIR__ . '/../../config/database.php')) {
        require_once __DIR__ . '/../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $global_settings[$row['setting_key']] = $row['setting_value'];
                }
            }

            // Fetch public services if available
            $stmt_serv = $db->query("
                SELECT s.id, s.name, s.slug, s.price, s.currency, s.cover_image, c.name as category_name
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                WHERE s.deleted_at IS NULL AND s.visibility = 'public' AND s.status != 'paused'
                ORDER BY s.id DESC LIMIT 6
            ");
            if ($stmt_serv) {
                $services_db = $stmt_serv->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Throwable $e) {
    // Graceful fallback: continue with default branding values
}

// Brand Tokens & Metadata
$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia');
$primary_color = htmlspecialchars($global_settings['primary_color'] ?? '#6366f1');
$company_phone = htmlspecialchars($global_settings['company_phone'] ?? '+51 900 000 000');
$phone_cleaned = preg_replace('/[^0-9]/', '', $company_phone);
if (empty($phone_cleaned)) $phone_cleaned = '51900000000';

$company_email = htmlspecialchars($global_settings['company_email'] ?? 'contacto@romaagencia.com');
$currency_code = htmlspecialchars($global_settings['currency'] ?? 'USD');
$currency_symbols = ['PEN' => 'S/', 'USD' => '$', 'EUR' => '€', 'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'COP' => '$'];
$currency = $currency_symbols[$currency_code] ?? '$';

$logo_light = !empty($global_settings['logo_light']) ? htmlspecialchars($global_settings['logo_light']) : '';
$logo_dark = !empty($global_settings['logo_dark']) ? htmlspecialchars($global_settings['logo_dark']) : '';

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo $base_url; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php echo $site_name; ?> | Creative & Tech Studio • Marcas, Software e Inteligencia Artificial</title>
    
    <!-- SEO & Social Meta Tags -->
    <meta name="description" content="Diseño de marca sofisticado, ingeniería de software a medida, producción audiovisual cinematográfica y automatización con Romita AI. Roma Agencia OS.">
    <meta name="keywords" content="Agencia Digital, Branding, Desarrollo Web, SaaS, Producción Audiovisual, Inteligencia Artificial, Romita AI, <?php echo $site_name; ?>">
    <meta name="author" content="<?php echo $site_name; ?>">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#07090e">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $site_name; ?> | The Agency Operating System">
    <meta property="og:description" content="Transformamos marcas visionarias con dirección de arte, ingeniería web y automatización con IA.">
    <meta property="og:image" content="<?php echo $base_url; ?>assets/img/login_slide_1.jpg">
    <meta property="og:url" content="<?php echo $base_url; ?>">

    <!-- Google Fonts & Phosphor Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Tailwind CSS with Custom Design Tokens -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        dark: {
                            base: '#07090e',
                            surface: '#0d111c',
                            card: '#131927',
                            border: 'rgba(255, 255, 255, 0.08)',
                            borderLight: 'rgba(255, 255, 255, 0.14)',
                        },
                        brand: {
                            primary: '<?php echo $primary_color; ?>',
                            indigo: '#6366f1',
                            violet: '#8b5cf6',
                            emerald: '#10b981',
                            cyan: '#06b6d4',
                            pink: '#ec4899',
                            amber: '#f59e0b',
                        }
                    },
                    boxShadow: {
                        'glow-indigo': '0 0 50px -10px rgba(99, 102, 241, 0.35)',
                        'glow-emerald': '0 0 50px -10px rgba(16, 185, 129, 0.35)',
                        'glow-violet': '0 0 50px -10px rgba(139, 92, 246, 0.35)',
                        'app': '0 20px 50px -10px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.08)',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'gradient-x': 'gradient-x 8s ease infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        'gradient-x': {
                            '0%, 100%': { 'background-size': '200% 200%', 'background-position': 'left center' },
                            '50%': { 'background-size': '200% 200%', 'background-position': 'right center' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Custom Modern App Glassmorphism & Micro-effects */
        :root {
            --brand-primary: <?php echo $primary_color; ?>;
            color-scheme: dark;
        }

        body {
            background-color: #07090e;
            color: #f1f5f9;
            overflow-x: hidden;
            selection-background-color: #6366f1;
            selection-color: #ffffff;
        }

        /* Ambient grid pattern background */
        .app-grid-bg {
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        }

        .ambient-radial-glow {
            background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.18) 0%, rgba(139, 92, 246, 0.08) 35%, transparent 70%);
        }

        /* Glassmorphic cards */
        .glass-panel {
            background: rgba(19, 25, 39, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .glass-panel-hover {
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-panel-hover:hover {
            background: rgba(24, 32, 50, 0.85);
            border-color: rgba(99, 102, 241, 0.35);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 30px -10px rgba(99, 102, 241, 0.2);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #07090e;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 9999px;
            border: 2px solid #07090e;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }

        /* Interactive Details FAQ animation */
        details[name="faq"] {
            transition: all 0.3s ease;
        }
        details[name="faq"] summary::-webkit-details-marker {
            display: none;
        }
        details[name="faq"][open] summary .faq-icon {
            transform: rotate(180deg);
            color: #6366f1;
        }

        /* Code window styling */
        .code-syntax {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8125rem;
            line-height: 1.6;
        }

        /* Active tab indicator */
        .tab-btn.active {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
            color: #ffffff;
        }

        /* Modal Dialog Styling */
        dialog::backdrop {
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
        }
    </style>
</head>
<body class="antialiased min-h-screen relative flex flex-col justify-between selection:bg-brand-indigo selection:text-white">

    <!-- Ambient Grid & Lighting Canvas -->
    <div class="fixed inset-0 app-grid-bg pointer-events-none z-0"></div>
    <div class="fixed top-0 left-0 right-0 h-[600px] ambient-radial-glow pointer-events-none z-0"></div>

    <!-- ========================================================================= -->
    <!-- NAVIGATION ISLAND (APP FLOATING BAR) -->
    <!-- ========================================================================= -->
    <header class="fixed top-4 sm:top-6 inset-x-0 z-50 px-4 max-w-7xl mx-auto flex items-center justify-between pointer-events-auto">
        <nav class="w-full glass-panel rounded-full px-4 sm:px-6 py-3 flex items-center justify-between shadow-2xl border border-dark-borderLight">
            
            <!-- Brand Mark / Logo -->
            <a href="landing" class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-brand-indigo rounded-full pr-2">
                <?php if (!empty($logo_light)): ?>
                    <img src="<?php echo $logo_light; ?>" alt="<?php echo $site_name; ?>" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-indigo via-brand-violet to-brand-pink flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform">
                        <i class="ph-bold ph-lightning text-white text-lg"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base font-extrabold tracking-tight text-white leading-tight flex items-center gap-1.5">
                            <?php echo $site_name; ?>
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-brand-emerald animate-ping"></span>
                        </span>
                        <span class="text-[10px] uppercase font-mono tracking-widest text-slate-400 -mt-0.5">Creative & Tech OS</span>
                    </div>
                <?php endif; ?>
            </a>

            <!-- Status Indicator Pill (Desktop) -->
            <div class="hidden lg:flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/60 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>Disponibles para Proyectos Q4/2026</span>
            </div>

            <!-- Navigation Links (Desktop) -->
            <div class="hidden md:flex items-center space-x-6 text-sm font-medium text-slate-300">
                <a href="#servicios" class="hover:text-white transition-colors">Soluciones</a>
                <a href="#ecosistema" class="hover:text-white transition-colors">Ecosistema App</a>
                <a href="#romita-ai" class="hover:text-brand-indigo transition-colors flex items-center gap-1.5">
                    <i class="ph-fill ph-sparkle text-brand-violet"></i>
                    <span>Romita AI</span>
                </a>
                <a href="#cotizador" class="hover:text-white transition-colors">Cotizador</a>
                <a href="#proceso" class="hover:text-white transition-colors">Método</a>
                <a href="#faq" class="hover:text-white transition-colors">FAQ</a>
            </div>

            <!-- Action Controls -->
            <div class="flex items-center gap-2 sm:gap-3">
                
                <!-- Quick Command Palette Trigger (Cmd/Ctrl + K) -->
                <button onclick="openCommandPalette()" class="hidden sm:flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 text-xs border border-white/10 transition-colors cursor-pointer" title="Abrir paleta de comandos (Ctrl+K)">
                    <i class="ph-bold ph-magnifying-glass text-slate-400"></i>
                    <kbd class="font-mono text-[10px] bg-dark-surface px-1.5 py-0.5 rounded border border-white/10 text-slate-400">⌘K</kbd>
                </button>

                <!-- Client Portal Link -->
                <a href="index.php?module=auth&action=login" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-full text-slate-200 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-all hover:scale-105" title="Acceso al Portal de Clientes">
                    <i class="ph ph-user-circle text-base text-brand-indigo"></i>
                    <span class="hidden sm:inline">Portal Clientes</span>
                </a>

                <!-- Primary CTA Button -->
                <a href="#cotizador" class="inline-flex items-center gap-1.5 px-4 sm:px-5 py-2 text-xs sm:text-sm font-semibold rounded-full text-white bg-gradient-to-r from-brand-indigo via-brand-violet to-brand-pink hover:opacity-95 shadow-glow-indigo transition-all hover:scale-105 active:scale-95">
                    <span>Iniciar Proyecto</span>
                    <i class="ph-bold ph-arrow-right text-xs"></i>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-full flex items-center justify-center bg-white/5 text-slate-200 border border-white/10 focus:outline-none">
                    <i class="ph-bold ph-list text-xl" id="menuIcon"></i>
                </button>
            </div>
        </nav>
    </header>

    <!-- Mobile Drawer Navigation -->
    <div id="mobileDrawer" class="fixed inset-0 z-40 bg-dark-base/95 backdrop-blur-2xl hidden flex-col justify-between p-6 pt-28 transition-all">
        <div class="space-y-4 text-center">
            <a href="#servicios" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo border-b border-white/5">Soluciones y Servicios</a>
            <a href="#ecosistema" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo border-b border-white/5">Ecosistema & Plataforma</a>
            <a href="#romita-ai" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-brand-violet border-b border-white/5 flex items-center justify-center gap-2">
                <i class="ph-fill ph-sparkle"></i> Romita AI Engine
            </a>
            <a href="#cotizador" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo border-b border-white/5">Calculadora / Cotizador</a>
            <a href="#proceso" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo border-b border-white/5">El Método Roma</a>
            <a href="#faq" onclick="toggleMobileMenu()" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo border-b border-white/5">Preguntas Frecuentes</a>
            <a href="catalogo" class="block py-3 text-lg font-semibold text-slate-200 hover:text-brand-indigo">Catálogo Público de Servicios</a>
        </div>
        <div class="space-y-3 pt-6 border-t border-white/10">
            <a href="index.php?module=auth&action=login" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-white/10 text-white font-medium">
                <i class="ph ph-user-circle text-xl"></i> Acceder a mi Portal de Cliente
            </a>
            <a href="https://wa.me/<?php echo $phone_cleaned; ?>?text=Hola%20Roma%20Agencia,%20quisiera%20conversar%20sobre%20un%20proyecto" target="_blank" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold shadow-glow-emerald">
                <i class="ph-fill ph-whatsapp-logo text-xl"></i> Conversar por WhatsApp
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HERO SECTION: THE AGENCY OPERATING SYSTEM -->
    <!-- ========================================================================= -->
    <main class="relative z-10 pt-32 sm:pt-40 lg:pt-44 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            
            <!-- Supertag Pill -->
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass-panel border border-white/10 text-xs sm:text-sm font-medium text-slate-200 mb-8 shadow-inner animate-float">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-indigo opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-brand-indigo"></span>
                </span>
                <span class="bg-gradient-to-r from-brand-indigo via-brand-violet to-brand-pink bg-clip-text text-transparent font-bold">ROMA AGENCY OS</span>
                <span class="text-slate-500">•</span>
                <span class="text-slate-300">La Nueva Era de Branding, Software e Inteligencia Artificial</span>
            </div>

            <!-- Giant Hero Headline -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-5xl mx-auto leading-[1.1] mb-6">
                Construimos Marcas de Autor y <br class="hidden md:block"/>
                <span class="bg-gradient-to-r from-brand-indigo via-purple-300 to-brand-emerald bg-clip-text text-transparent">
                    Plataformas Digitales de Alto Rendimiento
                </span>
            </h1>

            <!-- Subtitle -->
            <p class="max-w-3xl mx-auto text-base sm:text-xl text-slate-400 font-normal leading-relaxed mb-10">
                No somos una agencia tradicional lenta. Integramos <strong class="text-slate-200 font-semibold">dirección creativa de élite</strong>, <strong class="text-slate-200 font-semibold">ingeniería de software a medida</strong>, <strong class="text-slate-200 font-semibold">producción audiovisual cinematográfica</strong> y <strong class="text-brand-violet font-semibold">Romita AI</strong> para acelerar el crecimiento de empresas extraordinarias.
            </p>

            <!-- Dual CTA Row -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-xl mx-auto mb-16">
                <a href="#cotizador" class="w-full sm:w-auto px-8 py-4 rounded-full bg-gradient-to-r from-brand-indigo via-brand-violet to-brand-pink text-white font-bold text-base shadow-glow-indigo hover:shadow-2xl hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-3">
                    <i class="ph-bold ph-calculator text-lg"></i>
                    <span>Cotizar Proyecto en Línea</span>
                </a>

                <a href="https://wa.me/<?php echo $phone_cleaned; ?>?text=Hola%20Roma%20Agencia,%20me%20gustar%C3%ADa%20agendar%20una%20reuni%C3%B3n%20diagn%C3%B3stico" target="_blank" class="w-full sm:w-auto px-8 py-4 rounded-full glass-panel hover:bg-white/10 text-slate-200 hover:text-white font-semibold text-base border border-white/10 hover:border-emerald-500/50 hover:shadow-glow-emerald transition-all flex items-center justify-center gap-3 group">
                    <i class="ph-fill ph-whatsapp-logo text-emerald-400 text-xl group-hover:scale-110 transition-transform"></i>
                    <span>WhatsApp Directo</span>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300">&lt; 5m</span>
                </a>
            </div>

            <!-- Live Proof Metric Pills -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto mb-16">
                <div class="glass-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl sm:text-3xl font-extrabold text-white font-mono">+180</div>
                    <div class="text-xs text-slate-400 mt-1">Proyectos Desplegados</div>
                </div>
                <div class="glass-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400 font-mono">99.4%</div>
                    <div class="text-xs text-slate-400 mt-1">Tasa de Satisfacción</div>
                </div>
                <div class="glass-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl sm:text-3xl font-extrabold text-brand-indigo font-mono">4.8x</div>
                    <div class="text-xs text-slate-400 mt-1">Retorno (ROI) Promedio</div>
                </div>
                <div class="glass-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl sm:text-3xl font-extrabold text-brand-violet font-mono">24/7</div>
                    <div class="text-xs text-slate-400 mt-1">Romita AI & Soporte Cloud</div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- CENTERPIECE: ROMA AGENCY COMMAND HUB (INTERACTIVE APP UI) -->
            <!-- ========================================================================= -->
            <div id="ecosistema" class="mt-8 mx-auto max-w-6xl relative">
                
                <!-- Radiant Glow Underneath Mockup -->
                <div class="absolute -inset-1.5 bg-gradient-to-r from-brand-indigo via-brand-violet to-brand-emerald rounded-3xl blur-2xl opacity-25 group-hover:opacity-40 transition duration-1000"></div>

                <!-- Window Container -->
                <div class="relative rounded-2xl md:rounded-3xl shadow-app bg-dark-surface border border-white/10 overflow-hidden text-left">
                    
                    <!-- App Chrome Window Bar -->
                    <div class="bg-dark-base/90 border-b border-white/10 px-4 py-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500/80 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                            <span class="ml-2 text-xs font-mono text-slate-400 hidden sm:inline">roma-os / studio-suite v2.5</span>
                        </div>

                        <!-- Interactive Tab Controls -->
                        <div class="flex items-center gap-1 overflow-x-auto py-0.5 no-scrollbar max-w-full">
                            <button onclick="switchAppTab('branding')" id="tab-btn-branding" class="tab-btn active px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 border border-transparent transition-all flex items-center gap-1.5 whitespace-nowrap">
                                <i class="ph-fill ph-palette"></i> <span>Brand System</span>
                            </button>
                            <button onclick="switchAppTab('tech')" id="tab-btn-tech" class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 border border-transparent hover:text-white transition-all flex items-center gap-1.5 whitespace-nowrap">
                                <i class="ph-fill ph-code"></i> <span>Web & SaaS</span>
                            </button>
                            <button onclick="switchAppTab('audiovisual')" id="tab-btn-audiovisual" class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 border border-transparent hover:text-white transition-all flex items-center gap-1.5 whitespace-nowrap">
                                <i class="ph-fill ph-video-camera"></i> <span>Audiovisual Lab</span>
                            </button>
                            <button onclick="switchAppTab('romita')" id="tab-btn-romita" class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 border border-transparent hover:text-white transition-all flex items-center gap-1.5 whitespace-nowrap">
                                <i class="ph-fill ph-sparkle text-brand-violet"></i> <span>Romita AI</span>
                            </button>
                            <button onclick="switchAppTab('portal')" id="tab-btn-portal" class="tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 border border-transparent hover:text-white transition-all flex items-center gap-1.5 whitespace-nowrap">
                                <i class="ph-fill ph-kanban"></i> <span>Portal Clientes</span>
                            </button>
                        </div>

                        <div class="hidden sm:flex items-center gap-2 text-xs font-mono text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>LIVE ENGINE</span>
                        </div>
                    </div>

                    <!-- App Canvas Body Area -->
                    <div class="p-5 sm:p-8 bg-dark-surface/95 min-h-[460px]">
                        
                        <!-- ================= TAB 1: BRAND SYSTEM ================= -->
                        <div id="tab-panel-branding" class="space-y-6">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-white/5">
                                <div>
                                    <div class="text-xs font-mono uppercase text-brand-indigo tracking-wider">Identidad Visual & Sistema de Diseño</div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">Manual Corporativo & Design Tokens</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-md bg-white/5 text-xs font-mono text-slate-300 border border-white/10">Version 3.2 Released</span>
                                    <button onclick="copyToClipboard('#6366F1', 'Token copiado al portapapeles')" class="px-3 py-1 rounded-md bg-brand-indigo/20 text-brand-indigo hover:bg-brand-indigo hover:text-white text-xs font-medium transition-colors flex items-center gap-1">
                                        <i class="ph ph-copy"></i> Copiar Tokens
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Palette Generator Card -->
                                <div class="glass-panel rounded-2xl p-5 border border-white/5 space-y-4">
                                    <div class="text-xs font-mono text-slate-400 uppercase">Tokens de Color Primarios</div>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-base border border-white/5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-600 shadow-md"></div>
                                                <div>
                                                    <div class="text-xs font-semibold text-white">Electric Indigo</div>
                                                    <div class="text-[10px] font-mono text-slate-400">#6366F1 • Primary CTA</div>
                                                </div>
                                            </div>
                                            <span class="text-xs font-mono text-emerald-400">AA+ 8.2</span>
                                        </div>
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-base border border-white/5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-emerald-500 shadow-md"></div>
                                                <div>
                                                    <div class="text-xs font-semibold text-white">Emerald Glow</div>
                                                    <div class="text-[10px] font-mono text-slate-400">#10B981 • Conversion</div>
                                                </div>
                                            </div>
                                            <span class="text-xs font-mono text-emerald-400">AAA 11.4</span>
                                        </div>
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-base border border-white/5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-violet-600 shadow-md"></div>
                                                <div>
                                                    <div class="text-xs font-semibold text-white">Romita Violet</div>
                                                    <div class="text-[10px] font-mono text-slate-400">#8B5CF6 • AI Magic</div>
                                                </div>
                                            </div>
                                            <span class="text-xs font-mono text-emerald-400">AA+ 7.9</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Geometry / Grid Logo Construction -->
                                <div class="glass-panel rounded-2xl p-5 border border-white/5 space-y-4">
                                    <div class="flex justify-between items-center text-xs font-mono text-slate-400">
                                        <span class="uppercase">Geometría de Construcción</span>
                                        <span class="text-brand-indigo font-bold">1:1.618 Golden Ratio</span>
                                    </div>
                                    <div class="h-44 rounded-xl bg-dark-base border border-white/5 flex items-center justify-center relative overflow-hidden group">
                                        <div class="absolute inset-0 app-grid-bg opacity-30"></div>
                                        <!-- Geometric circles -->
                                        <div class="absolute w-32 h-32 rounded-full border border-brand-indigo/30"></div>
                                        <div class="absolute w-20 h-20 rounded-full border border-brand-violet/40"></div>
                                        <div class="absolute w-44 h-44 rounded-full border border-dashed border-white/10"></div>
                                        <!-- Emblem -->
                                        <div class="relative z-10 w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-indigo to-brand-violet flex items-center justify-center shadow-glow-indigo text-white text-2xl font-black">
                                            R
                                        </div>
                                    </div>
                                    <div class="text-xs text-slate-400 leading-relaxed">
                                        Construcción matemática modular con proporciones áureas adaptables a favicon de 16px y vallas de 10 metros.
                                    </div>
                                </div>

                                <!-- Deliverables Checklist -->
                                <div class="glass-panel rounded-2xl p-5 border border-white/5 space-y-3">
                                    <div class="text-xs font-mono text-slate-400 uppercase">Entregables de Marca</div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex items-center gap-2 text-slate-200">
                                            <i class="ph-bold ph-check-circle text-emerald-400 text-sm"></i>
                                            <span>Manual de Identidad Digital (Figma + PDF)</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-200">
                                            <i class="ph-bold ph-check-circle text-emerald-400 text-sm"></i>
                                            <span>Licencias tipográficas y glifos custom</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-200">
                                            <i class="ph-bold ph-check-circle text-emerald-400 text-sm"></i>
                                            <span>Papelería corporativa & Merchandising</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-200">
                                            <i class="ph-bold ph-check-circle text-emerald-400 text-sm"></i>
                                            <span>Kit para Redes Sociales & Plantillas</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-200">
                                            <i class="ph-bold ph-check-circle text-emerald-400 text-sm"></i>
                                            <span>Renders 3D en alta resolución (.OBJ/.GLTF)</span>
                                        </div>
                                    </div>
                                    <div class="pt-2">
                                        <a href="#cotizador" class="w-full py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white text-xs font-semibold flex items-center justify-center gap-2 border border-white/10 transition-colors">
                                            <span>Cotizar Branding Completo</span>
                                            <i class="ph ph-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= TAB 2: WEB & SAAS TECH ================= -->
                        <div id="tab-panel-tech" class="space-y-6 hidden">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-white/5">
                                <div>
                                    <div class="text-xs font-mono uppercase text-brand-emerald tracking-wider">Ingeniería de Software & Arquitectura Cloud</div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">Plataformas Web Ultrarrápidas & PWA</h3>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-mono text-xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Edge CDN: 18ms
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                <!-- Code Editor Mockup -->
                                <div class="md:col-span-7 bg-dark-base rounded-2xl p-4 border border-white/10 font-mono text-xs text-slate-300 overflow-x-auto">
                                    <div class="flex items-center justify-between text-[11px] text-slate-500 pb-3 mb-3 border-b border-white/5">
                                        <span>deploy_pipeline.config.ts</span>
                                        <span class="text-emerald-400">✓ build:passed (1.2s)</span>
                                    </div>
                                    <pre class="code-syntax"><span class="text-purple-400">export default</span> <span class="text-blue-400">defineAgencyConfig</span>({
  <span class="text-amber-300">target</span>: <span class="text-emerald-300">'enterprise-production'</span>,
  <span class="text-amber-300">performance</span>: {
    <span class="text-slate-400">lcp_optimization</span>: <span class="text-cyan-300">'fetchpriority=high'</span>,
    <span class="text-slate-400">asset_compression</span>: <span class="text-emerald-300">'brotli + webp'</span>,
    <span class="text-slate-400">security_headers</span>: [<span class="text-emerald-300">'HSTS'</span>, <span class="text-emerald-300">'CSP'</span>, <span class="text-emerald-300">'SameSite=Lax'</span>]
  },
  <span class="text-amber-300">integrations</span>: [
    <span class="text-emerald-300">'MercadoPago Gateway'</span>,
    <span class="text-emerald-300">'Romita AI Chat Assistant'</span>,
    <span class="text-emerald-300">'Client Portal Sync Engine'</span>
  ]
});</pre>
                                </div>

                                <!-- Lighthouse Scores -->
                                <div class="md:col-span-5 space-y-4">
                                    <div class="glass-panel rounded-2xl p-5 border border-white/5">
                                        <div class="text-xs font-mono uppercase text-slate-400 mb-3">Google Lighthouse Score</div>
                                        <div class="grid grid-cols-4 gap-2 text-center">
                                            <div class="p-2 rounded-xl bg-dark-base border border-emerald-500/20">
                                                <div class="text-lg font-black text-emerald-400 font-mono">100</div>
                                                <div class="text-[9px] text-slate-400 uppercase mt-0.5">Perf</div>
                                            </div>
                                            <div class="p-2 rounded-xl bg-dark-base border border-emerald-500/20">
                                                <div class="text-lg font-black text-emerald-400 font-mono">100</div>
                                                <div class="text-[9px] text-slate-400 uppercase mt-0.5">Acc</div>
                                            </div>
                                            <div class="p-2 rounded-xl bg-dark-base border border-emerald-500/20">
                                                <div class="text-lg font-black text-emerald-400 font-mono">100</div>
                                                <div class="text-[9px] text-slate-400 uppercase mt-0.5">Best</div>
                                            </div>
                                            <div class="p-2 rounded-xl bg-dark-base border border-emerald-500/20">
                                                <div class="text-lg font-black text-emerald-400 font-mono">100</div>
                                                <div class="text-[9px] text-slate-400 uppercase mt-0.5">SEO</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="glass-panel rounded-2xl p-4 border border-white/5 space-y-2 text-xs">
                                        <div class="flex items-center justify-between text-slate-300">
                                            <span>First Contentful Paint (FCP)</span>
                                            <span class="font-mono text-emerald-400">0.4s</span>
                                        </div>
                                        <div class="flex items-center justify-between text-slate-300">
                                            <span>Largest Contentful Paint (LCP)</span>
                                            <span class="font-mono text-emerald-400">0.8s</span>
                                        </div>
                                        <div class="flex items-center justify-between text-slate-300">
                                            <span>Cumulative Layout Shift (CLS)</span>
                                            <span class="font-mono text-emerald-400">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= TAB 3: AUDIOVISUAL LAB ================= -->
                        <div id="tab-panel-audiovisual" class="space-y-6 hidden">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-white/5">
                                <div>
                                    <div class="text-xs font-mono uppercase text-brand-pink tracking-wider">Producción Audiovisual & Spots Cinematográficos</div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">Storytelling & Formatos de Alto Impacto</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="toggleVideoAspect('16-9')" id="aspect-btn-169" class="px-2.5 py-1 rounded bg-white/10 text-white text-xs font-mono">16:9 Cinema</button>
                                    <button onclick="toggleVideoAspect('9-16')" id="aspect-btn-916" class="px-2.5 py-1 rounded bg-white/5 text-slate-400 hover:text-white text-xs font-mono">9:16 Reels</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                                <!-- Cinema Video Mockup Player -->
                                <div class="md:col-span-7">
                                    <div id="videoMockContainer" class="relative rounded-2xl overflow-hidden bg-dark-base border border-white/10 aspect-video flex items-center justify-center transition-all duration-300">
                                        <div class="absolute inset-0 bg-cover bg-center opacity-60" style="background-image: url('assets/img/login_slide_1.jpg');"></div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-dark-base via-transparent to-transparent"></div>
                                        
                                        <!-- Play Button Overlay -->
                                        <div class="relative z-10 w-16 h-16 rounded-full bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white text-2xl shadow-glow-indigo cursor-pointer hover:scale-110 transition-transform">
                                            <i class="ph-fill ph-play ml-1"></i>
                                        </div>

                                        <!-- Timeline Scrubber -->
                                        <div class="absolute bottom-3 inset-x-4 flex items-center gap-3 z-10">
                                            <span class="text-[10px] font-mono text-slate-300">01:24</span>
                                            <div class="flex-1 h-1.5 rounded-full bg-white/20 overflow-hidden">
                                                <div class="h-full w-2/3 bg-brand-pink rounded-full"></div>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-300">02:30</span>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-brand-pink/20 text-brand-pink border border-brand-pink/30">4K HDR</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Audio waveform & Specs -->
                                <div class="md:col-span-5 space-y-4 text-xs">
                                    <div class="glass-panel rounded-2xl p-5 border border-white/5 space-y-3">
                                        <div class="text-xs font-mono uppercase text-slate-400">Especificaciones de Edición</div>
                                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                            <span class="text-slate-300">Cámaras</span>
                                            <span class="font-mono text-white font-semibold">Cinema RAW / Sony FX6 / Dron 4K</span>
                                        </div>
                                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                            <span class="text-slate-300">Color Grading</span>
                                            <span class="font-mono text-brand-indigo font-semibold">DaVinci Resolve Studio LUTs</span>
                                        </div>
                                        <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                            <span class="text-slate-300">Audio Master</span>
                                            <span class="font-mono text-emerald-400 font-semibold">Dolby 5.1 / Normalizado Redes</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300">Entregables</span>
                                            <span class="font-mono text-purple-300 font-semibold">Master ProRes + H.265 Web</span>
                                        </div>
                                    </div>

                                    <div class="glass-panel rounded-2xl p-4 border border-white/5 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <i class="ph-bold ph-waveform text-brand-pink text-2xl animate-pulse"></i>
                                            <div>
                                                <div class="font-semibold text-white">Diseño Sonoro & Foley</div>
                                                <div class="text-[10px] text-slate-400">Banda sonora y locución profesional</div>
                                            </div>
                                        </div>
                                        <span class="text-emerald-400 font-mono">100% Licenciado</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= TAB 4: ROMITA AI INTELLIGENCE ================= -->
                        <div id="tab-panel-romita" class="space-y-6 hidden">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-white/5">
                                <div>
                                    <div class="text-xs font-mono uppercase text-brand-violet tracking-wider">Agente Inteligente Autónomo Roma</div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">Romita AI • Copiloto de Crecimiento & Ventas</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-full bg-brand-violet/20 border border-brand-violet/30 text-purple-200 text-xs font-mono flex items-center gap-1.5">
                                        <i class="ph-fill ph-sparkle text-brand-violet"></i> Gemini 2.5 Flash Ultra
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                <!-- Chat Terminal Area -->
                                <div class="md:col-span-8 bg-dark-base rounded-2xl p-5 border border-white/10 flex flex-col justify-between h-[340px]">
                                    
                                    <!-- Messages feed -->
                                    <div id="aiChatFeed" class="space-y-4 overflow-y-auto pr-2 text-xs">
                                        <div class="flex items-start gap-3">
                                            <img src="assets/img/romita-avatar.png" alt="Romita" class="w-7 h-7 rounded-full bg-brand-violet p-0.5 object-cover flex-shrink-0" onerror="this.src='assets/img/icon-192x192.png'">
                                            <div class="bg-dark-card border border-white/10 rounded-2xl rounded-tl-none p-3.5 text-slate-200 max-w-lg leading-relaxed">
                                                ¡Hola! Soy <strong class="text-brand-violet">Romita AI</strong>, la inteligencia artificial de Roma Agencia. ¿Qué deseas proyectar o automatizar hoy para tu marca?
                                            </div>
                                        </div>

                                        <div id="aiSimulatedUserMsg" class="flex items-start justify-end gap-3 hidden">
                                            <div class="bg-gradient-to-r from-brand-indigo to-brand-violet text-white rounded-2xl rounded-tr-none p-3.5 max-w-md" id="aiUserText">
                                                Generar estrategia de captación de clientes de alto valor.
                                            </div>
                                        </div>

                                        <div id="aiSimulatedBotResponse" class="flex items-start gap-3 hidden">
                                            <img src="assets/img/romita-avatar.png" alt="Romita" class="w-7 h-7 rounded-full bg-brand-violet p-0.5 object-cover flex-shrink-0" onerror="this.src='assets/img/icon-192x192.png'">
                                            <div class="bg-dark-card border border-brand-violet/30 rounded-2xl rounded-tl-none p-3.5 text-slate-200 max-w-lg leading-relaxed" id="aiBotText">
                                                Analizando sector... He configurado un embudo de 3 etapas: <br/>
                                                1. <strong>Audiovisual Hero Reel</strong> segmentado por intención de compra.<br/>
                                                2. <strong>Landing de Ultra-Conversión</strong> con tiempo de carga &lt; 0.6s.<br/>
                                                3. <strong>Bot de WhatsApp Romita</strong> para calificar al prospecto y agendar reunión en menos de 2 minutos.
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick Prompt Pills -->
                                    <div class="pt-3 border-t border-white/5">
                                        <div class="text-[11px] text-slate-400 mb-2 font-mono">Prueba un comando con Romita:</div>
                                        <div class="flex flex-wrap gap-2">
                                            <button onclick="simulateAiPrompt('Estrategia de Ventas Q4')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-brand-violet/20 border border-white/10 text-slate-300 hover:text-white text-xs transition-colors">
                                                ✦ Estrategia de Ventas Q4
                                            </button>
                                            <button onclick="simulateAiPrompt('Guión de Spot de Alto Impacto')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-brand-violet/20 border border-white/10 text-slate-300 hover:text-white text-xs transition-colors">
                                                ✦ Guión de Spot Viral
                                            </button>
                                            <button onclick="simulateAiPrompt('Integrar Bot en WhatsApp')" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-brand-violet/20 border border-white/10 text-slate-300 hover:text-white text-xs transition-colors">
                                                ✦ Asistente en WhatsApp
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Capabilities Card -->
                                <div class="md:col-span-4 space-y-4">
                                    <div class="glass-panel rounded-2xl p-5 border border-white/5 space-y-3 text-xs">
                                        <div class="text-xs font-mono uppercase text-slate-400">Automatizaciones Nativas</div>
                                        <div class="space-y-2.5">
                                            <div class="flex items-center gap-2 text-slate-300">
                                                <i class="ph-bold ph-check text-brand-emerald"></i>
                                                <span>Cierre de ventas 24/7 en WhatsApp</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-300">
                                                <i class="ph-bold ph-check text-brand-emerald"></i>
                                                <span>Generación de contenido de autor</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-300">
                                                <i class="ph-bold ph-check text-brand-emerald"></i>
                                                <span>Auditoría de marca y métricas en vivo</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-300">
                                                <i class="ph-bold ph-check text-brand-emerald"></i>
                                                <span>Conexión directa a CRM y Pagos</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-gradient-to-br from-brand-violet/20 to-brand-indigo/10 border border-brand-violet/30 text-xs">
                                        <div class="font-bold text-white mb-1">¿Deseas a Romita en tu empresa?</div>
                                        <div class="text-slate-300 text-[11px] mb-3">La integramos a medida en tus propios canales en menos de 7 días.</div>
                                        <a href="#cotizador" class="inline-flex items-center gap-1.5 font-semibold text-brand-violet hover:text-white transition-colors">
                                            <span>Solicitar implementación</span>
                                            <i class="ph ph-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= TAB 5: CLIENT PORTAL ================= -->
                        <div id="tab-panel-portal" class="space-y-6 hidden">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-white/5">
                                <div>
                                    <div class="text-xs font-mono uppercase text-brand-emerald tracking-wider">Tu Propio Portal de Cliente Roma</div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white mt-1">Transparencia Total & Sprints en Tiempo Real</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="index.php?module=auth&action=login" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition-colors flex items-center gap-1.5 shadow-glow-emerald">
                                        <i class="ph ph-sign-in"></i> Iniciar Sesión en Portal
                                    </a>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Sprint Column: In Progress -->
                                <div class="bg-dark-base rounded-2xl p-4 border border-white/5 space-y-3 text-xs">
                                    <div class="flex items-center justify-between pb-2 border-b border-white/5">
                                        <span class="font-bold text-amber-300 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-amber-400"></span> En Producción (Sprint 2)
                                        </span>
                                        <span class="font-mono text-slate-500 text-[10px]">2 Tareas</span>
                                    </div>

                                    <div class="p-3 rounded-xl bg-dark-card border border-white/5 space-y-2">
                                        <div class="font-semibold text-white">Desarrollo de Landing Page</div>
                                        <div class="text-[11px] text-slate-400">Integración de pasarela de pago y catálogo dinámico.</div>
                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-blue-500/20 text-blue-300">Desarrollo Web</span>
                                            <span class="text-slate-400 text-[10px]">85%</span>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded-xl bg-dark-card border border-white/5 space-y-2">
                                        <div class="font-semibold text-white">Postproducción de Reels</div>
                                        <div class="text-[11px] text-slate-400">Efectos de sonido y color grading 4K.</div>
                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-pink-500/20 text-pink-300">Audiovisual</span>
                                            <span class="text-slate-400 text-[10px]">60%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sprint Column: For Review & 1-Click Approval -->
                                <div class="bg-dark-base rounded-2xl p-4 border border-white/5 space-y-3 text-xs">
                                    <div class="flex items-center justify-between pb-2 border-b border-white/5">
                                        <span class="font-bold text-brand-indigo flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-brand-indigo"></span> Listo para Tu Aprobación
                                        </span>
                                        <span class="font-mono text-slate-500 text-[10px]">1 Entregable</span>
                                    </div>

                                    <div class="p-3.5 rounded-xl bg-dark-card border border-brand-indigo/30 space-y-3">
                                        <div class="flex items-start justify-between">
                                            <div class="font-semibold text-white">Manual de Marca & Logotipo Oficial</div>
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-purple-500/20 text-purple-300">Branding</span>
                                        </div>
                                        <div class="text-[11px] text-slate-400">Versiones finales en vectorial (.SVG, .AI, .PDF). Haz clic para aprobar:</div>
                                        
                                        <!-- Interactive demo button -->
                                        <button onclick="demoApproveDeliverable(this)" class="w-full py-2 rounded-lg bg-gradient-to-r from-brand-indigo to-brand-violet hover:opacity-90 text-white font-semibold text-xs flex items-center justify-center gap-2 transition-all">
                                            <i class="ph-bold ph-check"></i>
                                            <span>Aprobar Entregable (1 Clic)</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Sprint Column: Delivered / Vault -->
                                <div class="bg-dark-base rounded-2xl p-4 border border-white/5 space-y-3 text-xs">
                                    <div class="flex items-center justify-between pb-2 border-b border-white/5">
                                        <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Bóveda de Archivos
                                        </span>
                                        <span class="font-mono text-slate-500 text-[10px]">Descarga 24/7</span>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-card border border-white/5">
                                            <div class="flex items-center gap-2 text-slate-200">
                                                <i class="ph-bold ph-file-zip text-amber-400 text-base"></i>
                                                <span>Logos_Vector_Pack.zip</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-400">42 MB</span>
                                        </div>
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-card border border-white/5">
                                            <div class="flex items-center gap-2 text-slate-200">
                                                <i class="ph-bold ph-file-video text-rose-400 text-base"></i>
                                                <span>Spot_Comercial_4K.mov</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-400">1.8 GB</span>
                                        </div>
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-dark-card border border-white/5">
                                            <div class="flex items-center gap-2 text-slate-200">
                                                <i class="ph-bold ph-file-pdf text-blue-400 text-base"></i>
                                                <span>Contrato_Garantia.pdf</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-400">820 KB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ========================================================================= -->
    <!-- INDUSTRY / CLIENT SECTORS MARQUEE -->
    <!-- ========================================================================= -->
    <section class="py-12 border-y border-white/5 bg-dark-base/50 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-6">
            <div class="text-xs uppercase font-mono tracking-widest text-slate-500">Sectores de Alto Rendimiento que Impulsamos</div>
        </div>
        <div class="flex overflow-x-hidden space-x-12 opacity-70 hover:opacity-100 transition-opacity">
            <div class="flex space-x-12 animate-[marquee_25s_linear_infinite] whitespace-nowrap text-sm sm:text-base font-semibold text-slate-400">
                <span class="flex items-center gap-2"><i class="ph-bold ph-bank text-brand-indigo"></i> FINTECH & BANCA DIGITAL</span>
                <span class="text-slate-600">•</span>
                <span class="flex items-center gap-2"><i class="ph-bold ph-shopping-bag text-brand-pink"></i> E-COMMERCE & RETAIL GLOBAL</span>
                <span class="text-slate-600">•</span>
                <span class="flex items-center gap-2"><i class="ph-bold ph-buildings text-brand-emerald"></i> REAL ESTATE DE LUJO</span>
                <span class="text-slate-600">•</span>
                <span class="flex items-center gap-2"><i class="ph-bold ph-fork-knife text-amber-400"></i> GASTRONOMÍA & HOSPITALITY</span>
                <span class="text-slate-600">•</span>
                <span class="flex items-center gap-2"><i class="ph-bold ph-cpu text-brand-violet"></i> STARTUPS & SOFTWARE SAAS</span>
                <span class="text-slate-600">•</span>
                <span class="flex items-center gap-2"><i class="ph-bold ph-heartbeat text-cyan-400"></i> SALUD & BIOTECNOLOGÍA</span>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- CORE PILLARS: THE BENTO GRID ARCHITECTURE -->
    <!-- ========================================================================= -->
    <section id="servicios" class="py-24 sm:py-32 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto mb-16 sm:mb-20">
                <div class="text-xs uppercase font-mono tracking-widest text-brand-indigo font-bold mb-3">Capacidades & Pilares Centrales</div>
                <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">
                    Una Agencia Completa. Cero Fricción.
                </h2>
                <p class="mt-4 text-base sm:text-lg text-slate-400">
                    No contrates 5 proveedores desconectados. En Roma unificamos diseño, código, audiovisual y marketing bajo un mismo estándar de calidad impecable.
                </p>
            </div>

            <!-- Bento Grid 3-Columns Layout -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- CARD 1 (Branding - Large 2 cols on tablet/desktop) -->
                <div class="md:col-span-2 glass-panel glass-panel-hover rounded-3xl p-8 sm:p-10 relative overflow-hidden group">
                    <div class="relative z-10 max-w-xl">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-indigo to-brand-violet flex items-center justify-center text-white text-2xl shadow-glow-indigo mb-6">
                            <i class="ph-fill ph-palette"></i>
                        </div>
                        <span class="text-xs font-mono uppercase text-brand-indigo font-semibold tracking-wider">Pilar 01 • Dirección Creativa</span>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-white mt-2 mb-4">
                            Desarrollo de Marca & Identidad Visual de Autor
                        </h3>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed mb-6">
                            Tu marca no es solo un logo; es la percepción de valor que te permite cobrar más. Creamos universos visuales memorables: desde el naming y la arquitectura conceptual hasta manuales de marca interactivos, diseño de packaging y sistemas de diseño para producto digital.
                        </p>
                        
                        <div class="flex flex-wrap gap-2 pt-2">
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">Naming Estratégico</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">Sistemas de Identidad</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">Manuales en Figma</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">Packaging de Lujo</span>
                            <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">Rebranding Corporativo</span>
                        </div>
                    </div>

                    <!-- Decorative Graphic Background -->
                    <div class="absolute right-0 bottom-0 top-0 w-1/3 opacity-20 pointer-events-none hidden sm:block bg-gradient-to-l from-brand-indigo/30 to-transparent"></div>
                </div>

                <!-- CARD 2 (Web & SaaS Tech) -->
                <div class="glass-panel glass-panel-hover rounded-3xl p-8 sm:p-10 relative overflow-hidden flex flex-col justify-between group">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-500 to-cyan-500 flex items-center justify-center text-white text-2xl shadow-glow-emerald mb-6">
                            <i class="ph-fill ph-code"></i>
                        </div>
                        <span class="text-xs font-mono uppercase text-emerald-400 font-semibold tracking-wider">Pilar 02 • Ingeniería</span>
                        <h3 class="text-xl sm:text-2xl font-bold text-white mt-2 mb-3">
                            Desarrollo Web, E-commerce & SaaS
                        </h3>
                        <p class="text-slate-300 text-sm leading-relaxed mb-4">
                            Sitios web veloces, tiendas e-commerce de alta conversión y plataformas web personalizadas con integración a pasarelas de pago y CRM.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-white/5 flex items-center justify-between text-xs font-mono text-emerald-400">
                        <span>Puntuación Lighthouse: 100</span>
                        <i class="ph-bold ph-arrow-up-right text-base"></i>
                    </div>
                </div>

                <!-- CARD 3 (Audiovisual & Motion) -->
                <div class="glass-panel glass-panel-hover rounded-3xl p-8 sm:p-10 relative overflow-hidden flex flex-col justify-between group">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-rose-500 to-brand-pink flex items-center justify-center text-white text-2xl shadow-md mb-6">
                            <i class="ph-fill ph-film-slate"></i>
                        </div>
                        <span class="text-xs font-mono uppercase text-brand-pink font-semibold tracking-wider">Pilar 03 • Contenido</span>
                        <h3 class="text-xl sm:text-2xl font-bold text-white mt-2 mb-3">
                            Producción Audiovisual & Motion Graphics
                        </h3>
                        <p class="text-slate-300 text-sm leading-relaxed mb-4">
                            Comerciales en 4K, reels diseñados para retener la atención, fotografía de producto en estudio y animación gráfica para elevar el estatus de tu marca.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-white/5 flex items-center justify-between text-xs font-mono text-brand-pink">
                        <span>Formato Cine & Redes</span>
                        <i class="ph-bold ph-play-circle text-base"></i>
                    </div>
                </div>

                <!-- CARD 4 (Romita AI Suite - Wide 2 cols) -->
                <div id="romita-ai" class="md:col-span-2 glass-panel glass-panel-hover rounded-3xl p-8 sm:p-10 relative overflow-hidden group border-brand-violet/20">
                    <div class="relative z-10 max-w-xl">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-violet to-purple-800 flex items-center justify-center text-white text-2xl shadow-glow-violet mb-6">
                            <i class="ph-fill ph-sparkle"></i>
                        </div>
                        <span class="text-xs font-mono uppercase text-brand-violet font-semibold tracking-wider">Pilar 04 • Inteligencia Artificial</span>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-white mt-2 mb-4">
                            Romita AI: Automatización y Asistentes Virtuales 24/7
                        </h3>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed mb-6">
                            Entrenamos modelos de inteligencia artificial con el conocimiento de tu negocio para responder consultas, calificar prospectos, cotizar servicios y cerrar ventas en WhatsApp y web sin descanso.
                        </p>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="p-3 rounded-xl bg-dark-base/80 border border-white/5">
                                <div class="text-brand-violet font-bold text-xs">WhatsApp Bot 24/7</div>
                                <div class="text-[11px] text-slate-400">Respuesta inmediata</div>
                            </div>
                            <div class="p-3 rounded-xl bg-dark-base/80 border border-white/5">
                                <div class="text-brand-violet font-bold text-xs">Calificación de Leads</div>
                                <div class="text-[11px] text-slate-400">Filtrado automático</div>
                            </div>
                            <div class="p-3 rounded-xl bg-dark-base/80 border border-white/5">
                                <div class="text-brand-violet font-bold text-xs">Sincronización CRM</div>
                                <div class="text-[11px] text-slate-400">Datos en tiempo real</div>
                            </div>
                        </div>
                    </div>

                    <!-- Ambient purple glow -->
                    <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-brand-violet/20 blur-3xl pointer-events-none"></div>
                </div>

                <!-- CARD 5 (Marketing & Performance) -->
                <div class="md:col-span-3 glass-panel glass-panel-hover rounded-3xl p-8 sm:p-10 relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8 group">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-xl">
                                <i class="ph-fill ph-chart-polar"></i>
                            </div>
                            <span class="text-xs font-mono uppercase text-amber-400 font-semibold tracking-wider">Pilar 05 • Escalamiento & Ventas</span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">Marketing de Rendimiento, Ads & Embudo de Adquisición</h3>
                        <p class="text-slate-300 text-sm leading-relaxed">
                            No gastes presupuesto a ciegas. Configuramos campañas publicitarias en Meta Ads, Google y TikTok Ads con métricas claras y atribución transparente, diseñadas para generar un retorno de inversión saludable y predecible.
                        </p>
                    </div>

                    <div class="flex-shrink-0 flex items-center gap-4">
                        <a href="#cotizador" class="px-6 py-3.5 rounded-full bg-white text-dark-base hover:bg-slate-200 font-bold text-sm transition-all hover:scale-105 active:scale-95 shadow-xl flex items-center gap-2">
                            <span>Crear Estrategia</span>
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- INTERACTIVE ESTIMATOR & WHATSAPP QUOTE GENERATOR (APP TOOL) -->
    <!-- ========================================================================= -->
    <section id="cotizador" class="py-24 sm:py-32 relative bg-dark-surface/60 border-t border-white/5">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
                <div class="text-xs uppercase font-mono tracking-widest text-brand-emerald font-bold mb-3">Herramienta Interactiva</div>
                <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">
                    Calculadora & Cotizador de Proyecto
                </h2>
                <p class="mt-3 text-slate-400 text-sm sm:text-base">
                    Selecciona las soluciones que tu empresa requiere para calcular el alcance estimado y enviar tu requerimiento directo a WhatsApp.
                </p>
            </div>

            <div class="glass-panel rounded-3xl p-6 sm:p-10 border border-white/10 shadow-2xl relative">
                
                <form id="quoteCalculatorForm" onsubmit="event.preventDefault(); submitCustomQuote();" class="space-y-8">
                    
                    <!-- Step 1: Services Selection -->
                    <div>
                        <label class="block text-sm font-bold text-white mb-3">1. Selecciona las áreas requeridas para tu proyecto:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            
                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Desarrollo de Marca & Identidad" checked onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-brand-indigo peer-checked:bg-brand-indigo/10 peer-checked:shadow-glow-indigo transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-brand-indigo flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-palette"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Branding & Identidad</div>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Desarrollo Web / Plataforma" checked onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 peer-checked:shadow-glow-emerald transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-code"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Web / E-commerce / SaaS</div>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Producción Audiovisual (Spots/Reels)" onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-brand-pink peer-checked:bg-brand-pink/10 transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-pink-500/20 text-brand-pink flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-film-slate"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Producción Audiovisual</div>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Agente Inteligente Romita AI" onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-brand-violet peer-checked:bg-brand-violet/10 peer-checked:shadow-glow-violet transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-purple-500/20 text-brand-violet flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-sparkle"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Romita AI WhatsApp Bot</div>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Marketing de Rendimiento & Pauta" onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-chart-line-up"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Campañas & Meta/Google Ads</div>
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="checkbox" name="service_item" value="Portal de Clientes & Soporte VIP" onchange="updateQuoteCalculation()" class="peer sr-only">
                                <div class="p-4 rounded-2xl bg-dark-base border border-white/10 peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 transition-all flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-lg">
                                        <i class="ph-fill ph-shield-check"></i>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-200">Soporte Continuo & SLA</div>
                                </div>
                            </label>

                        </div>
                    </div>

                    <!-- Step 2: Scale & Timeline -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-white mb-2">2. Etapa actual de tu empresa:</label>
                            <select id="companyStage" onchange="updateQuoteCalculation()" class="w-full bg-dark-base border border-white/10 rounded-xl px-4 py-3 text-slate-200 text-sm focus:border-brand-indigo focus:ring-1 focus:ring-brand-indigo outline-none">
                                <option value="Startup / Nueva Marca">Nueva Marca / Lanzamiento al Mercado</option>
                                <option value="Empresa en Crecimiento (Scale-up)" selected>Empresa en Crecimiento (Buscando Escalar)</option>
                                <option value="Corporativo Consolidado">Corporativo / Empresa Consolidada</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-white mb-2">3. Tiempo ideal de lanzamiento:</label>
                            <select id="launchTimeline" onchange="updateQuoteCalculation()" class="w-full bg-dark-base border border-white/10 rounded-xl px-4 py-3 text-slate-200 text-sm focus:border-brand-indigo focus:ring-1 focus:ring-brand-indigo outline-none">
                                <option value="Sprints Express (15 a 20 días)">Sprints Express (15 a 20 días)</option>
                                <option value="Estándar Ágil (30 a 45 días)" selected>Estándar Ágil (30 a 45 días)</option>
                                <option value="Plan Estratégico Integral (60+ días)">Plan Estratégico Integral (60+ días)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 3: Client Data & Live Summary -->
                    <div class="p-6 rounded-2xl bg-dark-base/80 border border-white/10 space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pb-3 border-b border-white/5">
                            <div>
                                <span class="text-xs font-mono uppercase text-slate-400">Resumen Estimado del Paquete:</span>
                                <div id="selectedServicesCount" class="text-sm font-bold text-white mt-0.5">2 Servicios seleccionados</div>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] font-mono text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">
                                    Incluye Acceso a Portal de Clientes Roma
                                </span>
                            </div>
                        </div>

                        <!-- Name & Phone Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="clientNameInput" class="block text-xs font-medium text-slate-300 mb-1">Tu Nombre o Empresa:</label>
                                <input type="text" id="clientNameInput" placeholder="Ej. Carlos Mendoza / Empresa SAC" class="w-full bg-dark-card border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:border-brand-indigo outline-none">
                            </div>
                            <div>
                                <label for="clientContactInput" class="block text-xs font-medium text-slate-300 mb-1">WhatsApp o Correo:</label>
                                <input type="text" id="clientContactInput" placeholder="Ej. +51 987 654 321" class="w-full bg-dark-card border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:border-brand-indigo outline-none">
                            </div>
                        </div>

                        <!-- CTA Button to Send to WhatsApp -->
                        <div class="pt-2">
                            <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold text-base shadow-glow-emerald hover:shadow-2xl transition-all flex items-center justify-center gap-3 active:scale-[0.99] cursor-pointer">
                                <i class="ph-fill ph-whatsapp-logo text-2xl"></i>
                                <span>Enviar Propuesta y Consultar Disponibilidad por WhatsApp</span>
                            </button>
                            <p class="text-center text-[11px] text-slate-500 mt-2">
                                Sin spam ni compromisos. Recibirás respuesta directa de nuestro equipo directivo en menos de 15 minutos.
                            </p>
                        </div>
                    </div>

                </form>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- THE CLIENT EXPERIENCE: THE ROMA METHOD -->
    <!-- ========================================================================= -->
    <section id="proceso" class="py-24 sm:py-32 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto mb-16 sm:mb-20">
                <div class="text-xs uppercase font-mono tracking-widest text-brand-violet font-bold mb-3">Garantía de Entrega</div>
                <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">
                    El Método Roma: Cómo Trabajamos Contigo
                </h2>
                <p class="mt-4 text-slate-400 text-sm sm:text-base">
                    Un proceso iterativo, transparente y predecible. Sabes exactamente qué día se entrega cada pieza y puedes seguirlo desde tu móvil.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">
                
                <!-- Step 1 -->
                <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/5 space-y-4 relative">
                    <div class="text-3xl font-extrabold font-mono text-brand-indigo">01</div>
                    <h3 class="text-lg font-bold text-white">Inmersión & Blueprint</h3>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed">
                        Auditamos tu negocio, definimos los objetivos comerciales y trazamos el plan de trabajo con fechas de entrega fijadas en contrato.
                    </p>
                    <div class="text-[11px] font-mono text-slate-500 pt-2 border-t border-white/5">Sprint: Días 1 a 5</div>
                </div>

                <!-- Step 2 -->
                <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/5 space-y-4 relative">
                    <div class="text-3xl font-extrabold font-mono text-brand-violet">02</div>
                    <h3 class="text-lg font-bold text-white">Arquitectura & Concepto</h3>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed">
                        Creamos los prototipos visuales, wireframes web, guiones y moodboards. Nada se produce sin tu validación previa.
                    </p>
                    <div class="text-[11px] font-mono text-slate-500 pt-2 border-t border-white/5">Sprint: Días 6 a 15</div>
                </div>

                <!-- Step 3 -->
                <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/5 space-y-4 relative">
                    <div class="text-3xl font-extrabold font-mono text-brand-emerald">03</div>
                    <h3 class="text-lg font-bold text-white">Producción & Portal Live</h3>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed">
                        Desarrollamos el código, rodamos las piezas audiovisuales y te damos acceso a tu Portal de Clientes para aprobar avances con 1 clic.
                    </p>
                    <div class="text-[11px] font-mono text-slate-500 pt-2 border-t border-white/5">Sprint: Días 16 a 30</div>
                </div>

                <!-- Step 4 -->
                <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-white/5 space-y-4 relative">
                    <div class="text-3xl font-extrabold font-mono text-amber-400">04</div>
                    <h3 class="text-lg font-bold text-white">Lanzamiento & Escala</h3>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed">
                        Despliegue en producción, entrega de archivos maestros en la nube y optimización de pautas publicitarias con Romita AI.
                    </p>
                    <div class="text-[11px] font-mono text-slate-500 pt-2 border-t border-white/5">Garantía Activa Post-Lanzamiento</div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- SOCIAL PROOF & CLIENT REVIEWS -->
    <!-- ========================================================================= -->
    <section id="testimonios" class="py-24 sm:py-32 relative bg-dark-surface/40 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="text-xs uppercase font-mono tracking-widest text-brand-emerald font-bold mb-3">Casos de Éxito & Reputación</div>
                <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">
                    Marcas que Han Escalado con Roma
                </h2>
                <p class="mt-3 text-slate-400 text-sm sm:text-base">
                    Resultados tangibles, marcas respetadas y relaciones a largo plazo.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Review 1 -->
                <div class="glass-panel rounded-3xl p-8 border border-white/5 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex text-amber-400 text-sm">
                            <i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i>
                        </div>
                        <p class="text-slate-200 text-sm leading-relaxed italic">
                            "Multiplicamos por 3.8 nuestras ventas digitales en menos de 90 días. El rediseño de marca y la plataforma web que crearon nos posicionó en el segmento premium de inmediato."
                        </p>
                    </div>
                    <div class="pt-4 border-t border-white/5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-white text-sm">
                            JM
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">Javier Morales</div>
                            <div class="text-[11px] text-slate-400">CEO & Fundador • FinTech Andina</div>
                        </div>
                    </div>
                </div>

                <!-- Review 2 -->
                <div class="glass-panel rounded-3xl p-8 border border-white/5 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex text-amber-400 text-sm">
                            <i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i>
                        </div>
                        <p class="text-slate-200 text-sm leading-relaxed italic">
                            "El Portal de Clientes es una maravilla. Nunca más tuvimos que buscar un archivo en correos o WhatsApp. Toda la producción de video y entregables estaban disponibles en un clic."
                        </p>
                    </div>
                    <div class="pt-4 border-t border-white/5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-violet-600 flex items-center justify-center font-bold text-white text-sm">
                            LR
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">Lorena Rodríguez</div>
                            <div class="text-[11px] text-slate-400">Directora de Marketing • Grupo Nova Retail</div>
                        </div>
                    </div>
                </div>

                <!-- Review 3 -->
                <div class="glass-panel rounded-3xl p-8 border border-white/5 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex text-amber-400 text-sm">
                            <i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i>
                        </div>
                        <p class="text-slate-200 text-sm leading-relaxed italic">
                            "Implementar Romita AI en nuestro WhatsApp fue el mejor acierto del año. Atendemos a clientes de madrugada y cerramos ventas sin intervención humana directa."
                        </p>
                    </div>
                    <div class="pt-4 border-t border-white/5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center font-bold text-white text-sm">
                            AL
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">Andrés Lozano</div>
                            <div class="text-[11px] text-slate-400">COO • Urban Living Real Estate</div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- MODERN NATIVE DETAILS FAQ ACCORDION -->
    <!-- ========================================================================= -->
    <section id="faq" class="py-24 sm:py-32 relative">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-2xl mx-auto mb-16">
                <div class="text-xs uppercase font-mono tracking-widest text-brand-indigo font-bold mb-3">Transparencia Total</div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                    Preguntas Frecuentes
                </h2>
                <p class="mt-3 text-slate-400 text-sm">
                    Todo lo que necesitas saber antes de iniciar un proyecto con Roma Agencia.
                </p>
            </div>

            <div class="space-y-4">
                
                <details name="faq" class="glass-panel rounded-2xl p-6 border border-white/5 group">
                    <summary class="flex justify-between items-center cursor-pointer list-none text-white font-semibold text-base sm:text-lg focus:outline-none">
                        <span>¿Quién es el dueño del código fuente, diseños y archivos finales?</span>
                        <i class="ph-bold ph-caret-down text-slate-400 faq-icon transition-transform text-lg"></i>
                    </summary>
                    <p class="mt-4 text-slate-300 text-sm leading-relaxed pt-3 border-t border-white/5">
                        Tú eres 100% el propietario legal de todo el material desarrollado: código fuente, archivos editables en Figma (.fig), vectores (.ai, .svg), grabaciones en 4K y bases de datos. No cobramos tarifas ocultas por entrega de archivos.
                    </p>
                </details>

                <details name="faq" class="glass-panel rounded-2xl p-6 border border-white/5 group">
                    <summary class="flex justify-between items-center cursor-pointer list-none text-white font-semibold text-base sm:text-lg focus:outline-none">
                        <span>¿Cómo funciona el acceso al Portal de Clientes?</span>
                        <i class="ph-bold ph-caret-down text-slate-400 faq-icon transition-transform text-lg"></i>
                    </summary>
                    <p class="mt-4 text-slate-300 text-sm leading-relaxed pt-3 border-t border-white/5">
                        Al formalizar el proyecto, te entregamos credenciales personales para acceder a tu plataforma web y móvil. Desde allí podrás ver las tareas en progreso, aprobar entregables con un solo clic, chatear con el equipo asignado y descargar tus facturas y archivos.
                    </p>
                </details>

                <details name="faq" class="glass-panel rounded-2xl p-6 border border-white/5 group">
                    <summary class="flex justify-between items-center cursor-pointer list-none text-white font-semibold text-base sm:text-lg focus:outline-none">
                        <span>¿Cuánto tiempo tarda el desarrollo de un proyecto completo?</span>
                        <i class="ph-bold ph-caret-down text-slate-400 faq-icon transition-transform text-lg"></i>
                    </summary>
                    <p class="mt-4 text-slate-300 text-sm leading-relaxed pt-3 border-t border-white/5">
                        Depende del alcance elegido. Un rediseño de marca o landing page suele tomar entre 15 y 25 días útiles. Una plataforma web a medida o producción audiovisual completa toma entre 30 y 45 días. Todas las fechas quedan estipuladas con exactitud en el cronograma inicial.
                    </p>
                </details>

                <details name="faq" class="glass-panel rounded-2xl p-6 border border-white/5 group">
                    <summary class="flex justify-between items-center cursor-pointer list-none text-white font-semibold text-base sm:text-lg focus:outline-none">
                        <span>¿Qué formas y condiciones de pago aceptan?</span>
                        <i class="ph-bold ph-caret-down text-slate-400 faq-icon transition-transform text-lg"></i>
                    </summary>
                    <p class="mt-4 text-slate-300 text-sm leading-relaxed pt-3 border-t border-white/5">
                        Trabajamos habitualmente con un esquema de 50% de anticipo para iniciar y 50% contra entrega final aprobada. Aceptamos transferencias bancarias directas, tarjetas de crédito mediante pasarela segura y pagos en dólares (USD) o moneda local. Emitimos comprobante fiscal electrónico.
                    </p>
                </details>

                <details name="faq" class="glass-panel rounded-2xl p-6 border border-white/5 group">
                    <summary class="flex justify-between items-center cursor-pointer list-none text-white font-semibold text-base sm:text-lg focus:outline-none">
                        <span>¿Qué soporte recibo después del lanzamiento?</span>
                        <i class="ph-bold ph-caret-down text-slate-400 faq-icon transition-transform text-lg"></i>
                    </summary>
                    <p class="mt-4 text-slate-300 text-sm leading-relaxed pt-3 border-t border-white/5">
                        Todos nuestros proyectos incluyen un periodo de garantía de 30 a 60 días sin costo para correcciones, ajustes o soporte técnico. Además, ofrecemos planes mensuales de acompañamiento continuo, soporte en la nube y mantenimiento con Romita AI.
                    </p>
                </details>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- FINAL CONVERSION HERO & CALL TO ACTION -->
    <!-- ========================================================================= -->
    <section class="py-20 sm:py-28 relative overflow-hidden">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            
            <div class="glass-panel rounded-3xl p-8 sm:p-16 border border-white/10 relative overflow-hidden shadow-2xl">
                <!-- Glowing ambient orb -->
                <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-indigo/30 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-brand-emerald/20 blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <span class="text-xs font-mono uppercase text-brand-emerald tracking-widest font-bold block mb-3">
                        ✦ Plazas Limitadas Q4/2026
                    </span>
                    <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight max-w-2xl mx-auto leading-tight mb-6">
                        ¿Listo para convertir tu marca en un referente indiscutible?
                    </h2>
                    <p class="text-slate-300 text-base sm:text-lg max-w-xl mx-auto mb-10">
                        Agenda una sesión de diagnóstico estratégico sin costo y evaluemos el potencial de tu proyecto hoy mismo.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="https://wa.me/<?php echo $phone_cleaned; ?>?text=Hola%20Roma%20Agencia,%20deseo%20agendar%20una%20sesi%C3%B3n%20diagn%C3%B3stico" target="_blank" class="w-full sm:w-auto px-8 py-4 rounded-full bg-emerald-500 hover:bg-emerald-400 text-dark-base font-extrabold text-base shadow-glow-emerald transition-all hover:scale-105 active:scale-95 flex items-center justify-center gap-2">
                            <i class="ph-fill ph-whatsapp-logo text-xl"></i>
                            <span>Hablar con un Director Creativo</span>
                        </a>

                        <a href="catalogo" class="w-full sm:w-auto px-8 py-4 rounded-full glass-panel hover:bg-white/10 text-white font-semibold text-base border border-white/10 transition-all flex items-center justify-center gap-2">
                            <i class="ph ph-shopping-bag"></i>
                            <span>Ver Catálogo Completo</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- FOOTER -->
    <!-- ========================================================================= -->
    <footer class="border-t border-white/5 bg-dark-base/90 pt-16 pb-12 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10 mb-12">
                
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-brand-indigo to-brand-violet flex items-center justify-center text-white text-base font-black">
                            R
                        </div>
                        <span class="text-xl font-black text-white"><?php echo $site_name; ?></span>
                    </div>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed max-w-sm">
                        Estudio integral de branding de autor, desarrollo de software cloud y producción audiovisual. La infraestructura digital para empresas que lideran su industria.
                    </p>
                    <div class="text-xs text-slate-500 font-mono">
                        &copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. Todos los derechos reservados.
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-mono uppercase text-slate-200 tracking-wider mb-4">Soluciones</h4>
                    <ul class="space-y-2.5 text-xs text-slate-400">
                        <li><a href="#servicios" class="hover:text-white transition-colors">Desarrollo de Marca</a></li>
                        <li><a href="#servicios" class="hover:text-white transition-colors">Ingeniería Web & SaaS</a></li>
                        <li><a href="#servicios" class="hover:text-white transition-colors">Producción Audiovisual</a></li>
                        <li><a href="#romita-ai" class="hover:text-white transition-colors">Romita AI WhatsApp</a></li>
                        <li><a href="#servicios" class="hover:text-white transition-colors">Marketing de Rendimiento</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-mono uppercase text-slate-200 tracking-wider mb-4">Ecosistema</h4>
                    <ul class="space-y-2.5 text-xs text-slate-400">
                        <li><a href="catalogo" class="hover:text-white transition-colors">Catálogo Público</a></li>
                        <li><a href="#cotizador" class="hover:text-white transition-colors">Cotizador en Línea</a></li>
                        <li><a href="index.php?module=auth&action=login" class="hover:text-white transition-colors">Portal de Clientes</a></li>
                        <li><a href="#proceso" class="hover:text-white transition-colors">El Método Roma</a></li>
                        <li><a href="#faq" class="hover:text-white transition-colors">Preguntas Frecuentes</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-mono uppercase text-slate-200 tracking-wider mb-4">Contacto Directo</h4>
                    <ul class="space-y-2.5 text-xs text-slate-400">
                        <li class="flex items-center gap-2">
                            <i class="ph-fill ph-whatsapp-logo text-emerald-400"></i>
                            <a href="https://wa.me/<?php echo $phone_cleaned; ?>" target="_blank" class="hover:text-white"><?php echo $company_phone; ?></a>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="ph-fill ph-envelope text-brand-indigo"></i>
                            <a href="mailto:<?php echo $company_email; ?>" class="hover:text-white"><?php echo $company_email; ?></a>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="ph-fill ph-shield-check text-slate-500"></i>
                            <span>SLA 99.9% Uptime</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- MOBILE FIXED BOTTOM ACTION DOCK (APP STYLE) -->
    <!-- ========================================================================= -->
    <div class="md:hidden fixed bottom-3 inset-x-4 z-40">
        <div class="glass-panel rounded-full p-2 flex items-center justify-between shadow-2xl border border-white/10">
            <a href="#cotizador" class="flex-1 py-2 text-center text-xs font-bold text-white bg-gradient-to-r from-brand-indigo to-brand-violet rounded-full flex items-center justify-center gap-1.5 shadow-md">
                <i class="ph-bold ph-calculator"></i> Cotizar
            </a>
            <a href="https://wa.me/<?php echo $phone_cleaned; ?>" target="_blank" class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center text-lg shadow-glow-emerald mx-2">
                <i class="ph-fill ph-whatsapp-logo"></i>
            </a>
            <a href="index.php?module=auth&action=login" class="flex-1 py-2 text-center text-xs font-semibold text-slate-200 bg-white/5 hover:bg-white/10 rounded-full flex items-center justify-center gap-1.5 border border-white/10">
                <i class="ph ph-user-circle text-sm"></i> Portal
            </a>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- COMMAND PALETTE (CTRL+K DIALOG MODAL) -->
    <!-- ========================================================================= -->
    <dialog id="commandDialog" class="p-0 rounded-2xl bg-dark-surface border border-white/15 text-white max-w-lg w-full shadow-2xl focus:outline-none overflow-hidden m-auto">
        <div class="p-3 border-b border-white/10 flex items-center gap-3 bg-dark-base">
            <i class="ph-bold ph-magnifying-glass text-slate-400 text-lg"></i>
            <input type="text" id="cmdInput" oninput="filterCommandPalette(this.value)" placeholder="Escribe para buscar (ej. cotizar, branding, portal, whatsapp)..." class="w-full bg-transparent text-white text-sm outline-none placeholder:text-slate-500">
            <kbd class="text-[10px] font-mono bg-white/5 px-2 py-0.5 rounded text-slate-400">ESC</kbd>
        </div>
        <div class="p-2 max-h-72 overflow-y-auto space-y-1 text-xs" id="cmdResults">
            <a href="#cotizador" onclick="closeCommandPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-bold ph-calculator text-brand-emerald"></i> Cotizador Inteligente</span>
                <span class="text-[10px] text-slate-500 font-mono">Calcular</span>
            </a>
            <a href="#servicios" onclick="closeCommandPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-bold ph-palette text-brand-indigo"></i> Desarrollo de Marca & Branding</span>
                <span class="text-[10px] text-slate-500 font-mono">Soluciones</span>
            </a>
            <a href="#ecosistema" onclick="closeCommandPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-bold ph-code text-cyan-400"></i> Ingeniería Web & SaaS</span>
                <span class="text-[10px] text-slate-500 font-mono">Tecnología</span>
            </a>
            <a href="#romita-ai" onclick="closeCommandPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-bold ph-sparkle text-brand-violet"></i> Romita AI WhatsApp Copilot</span>
                <span class="text-[10px] text-slate-500 font-mono">Inteligencia</span>
            </a>
            <a href="index.php?module=auth&action=login" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-bold ph-sign-in text-slate-300"></i> Iniciar Sesión en Portal de Clientes</span>
                <span class="text-[10px] text-slate-500 font-mono">Acceso</span>
            </a>
            <a href="https://wa.me/<?php echo $phone_cleaned; ?>" target="_blank" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-white/5 transition-colors">
                <span class="flex items-center gap-2"><i class="ph-fill ph-whatsapp-logo text-emerald-400"></i> Contactar por WhatsApp</span>
                <span class="text-[10px] text-slate-500 font-mono">Ventas</span>
            </a>
        </div>
    </dialog>

    <!-- Toast Notification Overlay -->
    <div id="toastNotification" class="fixed bottom-20 right-6 z-50 glass-panel rounded-2xl px-4 py-3 border border-white/10 text-white text-xs shadow-2xl flex items-center gap-2 transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none">
        <i class="ph-bold ph-check-circle text-emerald-400 text-base" id="toastIcon"></i>
        <span id="toastMessage">Acción completada con éxito.</span>
    </div>

    <!-- ========================================================================= -->
    <!-- JAVASCRIPT APP INTERACTIONS -->
    <!-- ========================================================================= -->
    <script>
        // 1. Tab Switcher for App Mockup
        function switchAppTab(tabId) {
            // Update button styles
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('text-slate-400');
            });
            const activeBtn = document.getElementById('tab-btn-' + tabId);
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.classList.remove('text-slate-400');
            }

            // Hide all tab panels
            const panels = ['branding', 'tech', 'audiovisual', 'romita', 'portal'];
            panels.forEach(p => {
                const el = document.getElementById('tab-panel-' + p);
                if (el) el.classList.add('hidden');
            });

            // Show selected panel
            const target = document.getElementById('tab-panel-' + tabId);
            if (target) {
                target.classList.remove('hidden');
            }
        }

        // 2. Audiovisual Aspect Ratio Switcher
        function toggleVideoAspect(aspect) {
            const container = document.getElementById('videoMockContainer');
            const btn169 = document.getElementById('aspect-btn-169');
            const btn916 = document.getElementById('aspect-btn-916');

            if (aspect === '9-16') {
                container.classList.remove('aspect-video');
                container.style.aspectRatio = '9/14';
                container.style.maxWidth = '280px';
                container.style.margin = '0 auto';
                btn916.classList.replace('bg-white/5', 'bg-white/10');
                btn916.classList.replace('text-slate-400', 'text-white');
                btn169.classList.replace('bg-white/10', 'bg-white/5');
                btn169.classList.replace('text-white', 'text-slate-400');
            } else {
                container.style.aspectRatio = '';
                container.style.maxWidth = '100%';
                container.classList.add('aspect-video');
                btn169.classList.replace('bg-white/5', 'bg-white/10');
                btn169.classList.replace('text-slate-400', 'text-white');
                btn916.classList.replace('bg-white/10', 'bg-white/5');
                btn916.classList.replace('text-white', 'text-slate-400');
            }
        }

        // 3. Romita AI Chat Simulation
        function simulateAiPrompt(promptText) {
            const userBox = document.getElementById('aiSimulatedUserMsg');
            const userText = document.getElementById('aiUserText');
            const botBox = document.getElementById('aiSimulatedBotResponse');
            const botText = document.getElementById('aiBotText');

            userText.textContent = promptText;
            userBox.classList.remove('hidden');
            botBox.classList.add('hidden');

            setTimeout(() => {
                botBox.classList.remove('hidden');
                if (promptText.includes('Ventas')) {
                    botText.innerHTML = `<strong>Estrategia Roma Q4 configurada:</strong><br/>1. Audiencia B2B de alto ticket analizada.<br/>2. Landing page con micro-animaciones y prueba social verificada.<br/>3. Activación de campañas multicanal con retargeting dinámico.`;
                } else if (promptText.includes('Guión')) {
                    botText.innerHTML = `<strong>Guión Spot Viral de 30s generado:</strong><br/>• 0-3s Hook: "¿Sigues perdiendo horas en procesos que la IA puede resolver en 5 segundos?"<br/>• 4-20s Demostración del producto con estética cinematográfica.<br/>• 21-30s CTA claro con enlace al catálogo.`;
                } else {
                    botText.innerHTML = `<strong>Integración WhatsApp lista:</strong> Romita se conectará a tu número para responder en &lt; 2 segundos, registrar los datos en tu CRM y avisarte cuando un cliente esté listo para pagar.`;
                }
            }, 600);
        }

        // 4. Portal Demo Approval
        function demoApproveDeliverable(btn) {
            btn.innerHTML = `<i class="ph-bold ph-check-circle text-emerald-400 text-lg"></i> <span>¡Aprobado con Éxito! Notificando a Roma</span>`;
            btn.classList.replace('from-brand-indigo', 'from-emerald-600');
            btn.classList.replace('to-brand-violet', 'to-teal-600');
            showToast('Entregable aprobado con éxito en el portal.');
        }

        // 5. Quote Calculator Calculation & WhatsApp Generator
        function updateQuoteCalculation() {
            const checkedBoxes = document.querySelectorAll('input[name="service_item"]:checked');
            const countLabel = document.getElementById('selectedServicesCount');
            if (countLabel) {
                countLabel.textContent = `${checkedBoxes.length} Servicios seleccionados`;
            }
        }

        function submitCustomQuote() {
            const checked = Array.from(document.querySelectorAll('input[name="service_item"]:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                showToast('Por favor selecciona al menos un servicio.');
                return;
            }

            const stage = document.getElementById('companyStage').value;
            const timeline = document.getElementById('launchTimeline').value;
            const clientName = document.getElementById('clientNameInput').value.trim() || 'No especificado';
            const clientContact = document.getElementById('clientContactInput').value.trim() || 'No especificado';

            let msg = `Hola Roma Agencia! Vengo desde su Landing Page y deseo cotizar un proyecto:\n\n`;
            msg += `👤 Cliente / Empresa: ${clientName}\n`;
            msg += `📞 Contacto: ${clientContact}\n`;
            msg += `🏢 Etapa: ${stage}\n`;
            msg += `⏱️ Tiempo deseado: ${timeline}\n\n`;
            msg += `🛠️ Servicios de Interés:\n`;
            checked.forEach(s => {
                msg += `• ${s}\n`;
            });
            msg += `\n¿Podríamos coordinar una llamada o enviarme una propuesta preliminar? Gracias!`;

            const phone = '<?php echo $phone_cleaned; ?>';
            const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
            window.open(url, '_blank');
        }

        // 6. Mobile Drawer
        function toggleMobileMenu() {
            const drawer = document.getElementById('mobileDrawer');
            drawer.classList.toggle('hidden');
            drawer.classList.toggle('flex');
        }

        // 7. Command Palette (Cmd + K)
        const cmdDialog = document.getElementById('commandDialog');
        function openCommandPalette() {
            cmdDialog.showModal();
            setTimeout(() => document.getElementById('cmdInput').focus(), 50);
        }
        function closeCommandPalette() {
            cmdDialog.close();
        }

        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (cmdDialog.open) {
                    closeCommandPalette();
                } else {
                    openCommandPalette();
                }
            }
        });

        // Click outside modal to close
        cmdDialog.addEventListener('click', (e) => {
            const rect = cmdDialog.getBoundingClientRect();
            if (
                e.clientX < rect.left ||
                e.clientX > rect.right ||
                e.clientY < rect.top ||
                e.clientY > rect.bottom
            ) {
                closeCommandPalette();
            }
        });

        function filterCommandPalette(q) {
            const items = document.querySelectorAll('.cmd-item');
            const search = q.toLowerCase();
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? 'flex' : 'none';
            });
        }

        // 8. Toast Helper
        function showToast(message) {
            const toast = document.getElementById('toastNotification');
            const msgEl = document.getElementById('toastMessage');
            if (toast && msgEl) {
                msgEl.textContent = message;
                toast.classList.remove('translate-y-24', 'opacity-0');
                setTimeout(() => {
                    toast.classList.add('translate-y-24', 'opacity-0');
                }, 3000);
            }
        }

        function copyToClipboard(text, successMsg) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(successMsg || 'Copiado al portapapeles');
            });
        }
    </script>
</body>
</html>
