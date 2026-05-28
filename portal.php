<?php
// portal.php
session_start();
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Fetch Global Settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$global_settings = [];
foreach ($settings_raw as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

$site_name = $global_settings['site_name'] ?? 'Portal de Cliente';
$logo = $global_settings['logo_light'] ?? '';
$primary_color = $global_settings['primary_color'] ?? '#4f46e5';

// If DNI is in URL, auto-fill it
$urlDni = $_GET['dni'] ?? '';

// Check if already logged in as a client
$client_id = $_SESSION['client_portal_id'] ?? null;

if (!$client_id) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal de Cliente | <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="theme-color" content="<?php echo $primary_color; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/drive.css?v=<?php echo filemtime('assets/css/drive.css'); ?>">
    <style>
        :root {
            --portal-primary: <?php echo $primary_color; ?>;
            --portal-primary-contrast: var(--portal-primary);
            --portal-primary-bg: color-mix(in srgb, var(--portal-primary), transparent 90%);
            --portal-bg: #f8fafc;
            --portal-surface: #ffffff;
            --portal-text: #0f172a;
            --portal-muted: #64748b;
            --portal-border: #e2e8f0;
            --portal-radius: 24px;
            --safe-area-bottom: env(safe-area-inset-bottom, 20px);
            
            --color-blue: #3b82f6;
            --color-blue-bg: rgba(59, 130, 246, 0.1);
            --color-orange: #f59e0b;
            --color-orange-bg: rgba(245, 158, 11, 0.1);
            --color-green: #10b981;
            --color-green-bg: rgba(16, 185, 129, 0.1);
            --color-red: #ef4444;
            --color-red-bg: rgba(239, 68, 68, 0.1);
            --color-purple: #8b5cf6;
            --color-purple-bg: rgba(139, 92, 246, 0.1);
            --color-pink: #ec4899;
            --color-pink-bg: rgba(236, 72, 153, 0.1);
        }

        [data-theme="dark"] {
            --portal-bg: #070709;
            --portal-surface: #141417;
            --portal-text: #ffffff;
            --portal-muted: #9ca3af;
            --portal-border: #27272a;
            --portal-primary-contrast: color-mix(in srgb, var(--portal-primary), white 40%);
            --portal-primary-bg: color-mix(in srgb, var(--portal-primary), transparent 85%);
            
            --color-blue: #60a5fa;
            --color-blue-bg: rgba(96, 165, 250, 0.15);
            --color-orange: #fbbf24;
            --color-orange-bg: rgba(251, 191, 36, 0.15);
            --color-green: #34d399;
            --color-green-bg: rgba(52, 211, 153, 0.15);
            --color-red: #f87171;
            --color-red-bg: rgba(248, 113, 113, 0.15);
            --color-purple: #a78bfa;
            --color-purple-bg: rgba(167, 139, 250, 0.15);
            --color-pink: #f472b6;
            --color-pink-bg: rgba(244, 114, 182, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --portal-bg: #070709;
                --portal-surface: #141417;
                --portal-text: #ffffff;
                --portal-muted: #9ca3af;
                --portal-border: #27272a;
                --portal-primary-contrast: color-mix(in srgb, var(--portal-primary), white 40%);
                --portal-primary-bg: color-mix(in srgb, var(--portal-primary), transparent 85%);
                
                --color-blue: #60a5fa;
                --color-blue-bg: rgba(96, 165, 250, 0.15);
                --color-orange: #fbbf24;
                --color-orange-bg: rgba(251, 191, 36, 0.15);
                --color-green: #34d399;
                --color-green-bg: rgba(52, 211, 153, 0.15);
                --color-red: #f87171;
                --color-red-bg: rgba(248, 113, 113, 0.15);
                --color-purple: #a78bfa;
                --color-purple-bg: rgba(167, 139, 250, 0.15);
                --color-pink: #f472b6;
                --color-pink-bg: rgba(244, 114, 182, 0.15);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--portal-bg);
            color: var(--portal-text);
            overflow-x: hidden;
            overflow-y: auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Views */
        .view {
            display: none;
            width: 100%;
            min-height: 100vh;
            padding-bottom: calc(80px + var(--safe-area-bottom));
            animation: fadeIn 0.3s ease;
        }
        .view.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--portal-surface);
            border-top: 1px solid var(--portal-border);
            display: flex;
            justify-content: space-around;
            padding: 10px 0 calc(10px + var(--safe-area-bottom)) 0;
            z-index: 100;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            display: none;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--portal-muted);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            padding: 5px 10px;
            border-radius: 12px;
        }

        .nav-item i {
            font-size: 1.5rem;
            transition: transform 0.2s;
        }

        .nav-item.active {
            color: var(--portal-primary);
        }

        .nav-item.active i {
            transform: scale(1.1);
            font-weight: bold;
        }

        /* Header */
        .portal-header {
            padding: 2rem 1.5rem 1rem 1.5rem;
            position: sticky;
            top: 0;
            background: var(--portal-bg);
            z-index: 90;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .portal-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--portal-text);
        }

        /* Cards & Content */
        .content-padding {
            padding: 0 1.5rem;
        }

        .card {
            background: var(--portal-surface);
            border-radius: var(--portal-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--portal-border);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-top: 0.5rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--portal-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Payment Note Card */
        .payment-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-badge.paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-badge.late { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Generic UI Utils */
        .btn {
            background: var(--portal-primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.1s;
        }
        .btn:active { transform: scale(0.98); }

        .loader {
            border: 3px solid var(--portal-border);
            border-radius: 50%;
            border-top: 3px solid var(--portal-primary);
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Fullscreen Viewer */
        .file-viewer-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .file-viewer-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .viewer-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            padding-top: calc(1.5rem + env(safe-area-inset-top, 0px));
        }
        .viewer-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow: hidden;
        }
        .viewer-content iframe, .viewer-content img {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>

<!-- Home View -->
<div id="view-home" class="view active">
    <div class="portal-header" style="padding-bottom: 0; align-items: flex-start;">
        <div>
            <h1 class="portal-title" style="font-size: 2rem; color: var(--portal-text); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                ¡Hola, <span id="client-name-display" style="color: var(--portal-primary-contrast);">Cargando...</span>!
            </h1>
            <p style="color: var(--portal-muted); font-size: 0.95rem; font-weight: 500;">Aquí tienes un resumen de tu cuenta hoy.</p>
        </div>
        <div style="display:flex; gap: 10px; align-items:center;">
            <div onclick="toggleTheme()" style="width: 40px; height: 40px; border-radius: 50%; background: var(--portal-surface); color: var(--portal-text); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; border: 1px solid var(--portal-border); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <i class="ph ph-moon" id="theme-icon"></i>
            </div>
            <div id="client-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--portal-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem;">
                C
            </div>
        </div>
    </div>

    <div class="content-padding" style="margin-top: 2rem;">
        
        <!-- TOP CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
            
            <!-- Card 1: Meses/Proyectos -->
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="width: 45px; height: 45px; border-radius: 12px; background: var(--portal-primary-bg); color: var(--portal-primary-contrast); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="ph ph-rocket-launch"></i>
                    </div>
                    <span style="background: var(--portal-primary-bg); color: var(--portal-primary-contrast); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">En Progreso</span>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 2.5rem; margin-top: 0; color: var(--portal-text);" id="home-proyectos-count">0</div>
                    <div class="stat-label" style="text-transform: none; color: var(--portal-muted); font-size: 0.95rem; font-weight: 500;">Meses Activos</div>
                </div>
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--portal-border); padding-top: 1rem;">
                    <a href="#" onclick="switchView('projects'); return false;" style="color: var(--portal-primary-contrast); text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 4px;">
                        Ver detalles <i class="ph ph-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Card 2: Diseños Activos -->
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="width: 45px; height: 45px; border-radius: 12px; background: var(--color-orange-bg); color: var(--color-orange); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="ph ph-paint-brush"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 2.5rem; margin-top: 0; color: var(--portal-text);" id="home-disenos-count">0</div>
                    <div class="stat-label" style="text-transform: none; color: var(--portal-muted); font-size: 0.95rem; font-weight: 500;">Diseños Activos</div>
                </div>
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--portal-border); padding-top: 1rem;">
                    <a href="#" onclick="switchView('designs'); return false;" style="color: var(--color-orange); text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 4px;">
                        Ir a diseños <i class="ph ph-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Card 3: Saldo Pendiente -->
            <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="width: 45px; height: 45px; border-radius: 12px; background: var(--color-green-bg); color: var(--color-green); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="ph ph-money"></i>
                    </div>
                    <span id="home-unpaid-badge" style="background: var(--color-red-bg); color: var(--color-red); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: none;">0 Por pagar</span>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 2.5rem; margin-top: 0; color: var(--portal-text);" id="home-saldo">S/ 0.00</div>
                    <div class="stat-label" style="text-transform: none; color: var(--portal-muted); font-size: 0.95rem; font-weight: 500;">Saldo Pendiente</div>
                </div>
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--portal-border); padding-top: 1rem;">
                    <a href="#" onclick="switchView('payments'); return false;" style="color: var(--color-green); text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 4px;">
                        Pagar ahora <i class="ph ph-arrow-right"></i>
                    </a>
                </div>
            </div>

        </div>

        <!-- ACCESOS DIRECTOS -->
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--portal-text); margin-bottom: 1.5rem;">Accesos Directos</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            
            <div class="card" style="margin-bottom: 0; cursor: pointer; transition: transform 0.2s; padding: 1.25rem;" onclick="switchView('projects')" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--portal-primary-bg); color: var(--portal-primary-contrast); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem;">
                    <i class="ph ph-kanban"></i>
                </div>
                <div style="font-weight: 700; color: var(--portal-text); font-size: 1.05rem; margin-bottom: 0.25rem;">Meses Activos</div>
                <div style="color: var(--portal-muted); font-size: 0.85rem;">Gestiona tus entregables</div>
            </div>

            <div class="card" style="margin-bottom: 0; cursor: pointer; transition: transform 0.2s; padding: 1.25rem;" onclick="switchView('payments')" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--color-green-bg); color: var(--color-green); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem;">
                    <i class="ph ph-receipt"></i>
                </div>
                <div style="font-weight: 700; color: var(--portal-text); font-size: 1.05rem; margin-bottom: 0.25rem;">Facturación</div>
                <div style="color: var(--portal-muted); font-size: 0.85rem;">Consulta tus facturas</div>
            </div>

            <div class="card" style="margin-bottom: 0; cursor: pointer; transition: transform 0.2s; padding: 1.25rem;" onclick="switchView('drive')" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--color-purple-bg); color: var(--color-purple); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem;">
                    <i class="ph ph-folder-open"></i>
                </div>
                <div style="font-weight: 700; color: var(--portal-text); font-size: 1.05rem; margin-bottom: 0.25rem;">Archivos</div>
                <div style="color: var(--portal-muted); font-size: 0.85rem;">Biblioteca de recursos</div>
            </div>

            <div class="card" style="margin-bottom: 0; cursor: pointer; transition: transform 0.2s; padding: 1.25rem;" onclick="switchView('support')" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--color-pink-bg); color: var(--color-pink); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem;">
                    <i class="ph ph-headset"></i>
                </div>
                <div style="font-weight: 700; color: var(--portal-text); font-size: 1.05rem; margin-bottom: 0.25rem;">Soporte</div>
                <div style="color: var(--portal-muted); font-size: 0.85rem;">Obtén ayuda experta</div>
            </div>

        </div>
    </div>
</div>

<!-- Payments View -->
<div id="view-payments" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Mis Pagos</h1>
    </div>
    <div class="content-padding" id="payments-list">
        <div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>
    </div>
</div>

<!-- Projects View -->
<div id="view-projects" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Mis Proyectos</h1>
    </div>
    <div class="content-padding" id="projects-list">
        <div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>
    </div>
</div>

<!-- Designs View -->
<div id="view-designs" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Mis Diseños</h1>
    </div>
    <div class="content-padding" id="designs-list">
        <div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>
    </div>
</div>

<!-- Detailed Project/Design View -->
<div id="view-project-details" class="view" style="background: var(--portal-surface);">
    <div class="portal-header" style="border-bottom: 1px solid var(--portal-border); padding-bottom: 1rem; position: sticky; top: 0; background: var(--portal-surface); z-index: 90;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="btn-icon" onclick="backToList()" style="background: none; border: none; font-size: 1.5rem; color: var(--portal-text); cursor: pointer; padding: 0;">
                <i class="ph ph-arrow-left"></i>
            </button>
            <h1 class="portal-title" style="font-size: 1.2rem; margin: 0;" id="detail-top-title">Detalle</h1>
        </div>
    </div>
    
    <div style="padding: 2rem 1.5rem; text-align: center;">
        <div id="detail-logo" style="width: 80px; height: 80px; margin: 0 auto 1rem auto; background: var(--portal-bg); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--portal-primary); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <!-- Image or Initials -->
        </div>
        <h2 id="detail-title" style="font-size: 1.5rem; margin-bottom: 0.25rem;">Cargando...</h2>
        <p id="detail-subtitle" style="color: var(--portal-muted); font-size: 0.9rem; margin-bottom: 1rem;"></p>
        
        <div id="detail-status" style="display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
            ESTADO
        </div>
    </div>
    
    <div class="content-padding">
        <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ph ph-image-square"></i>
                </div>
                <div>
                    <div class="stat-label" id="detail-metric-label">Posts Mensuales</div>
                    <div style="font-weight: 700; font-size: 1.2rem;" id="detail-metric-value">0</div>
                </div>
            </div>
        </div>

        <h3 style="font-size: 1rem; margin: 1.5rem 0 1rem 0;">Equipo Asignado</h3>
        <div id="detail-team" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;">
            <!-- Avatars will be injected here -->
        </div>
        
        <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem;" id="detail-actions">
            <!-- Action buttons -->
        </div>
    </div>
</div>

<!-- Drive View -->
<div id="view-drive" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Mis Archivos</h1>
    </div>
    <div class="content-padding" id="drive-list">
        <div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>
    </div>
</div>

<!-- Support View -->
<div id="view-support" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Soporte</h1>
    </div>
    <div class="content-padding">
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <i class="ph ph-whatsapp-logo" style="font-size: 4rem; color: #25D366; margin-bottom: 1rem;"></i>
            <h2 style="margin-bottom: 0.5rem;">¿Necesitas ayuda?</h2>
            <p style="color: var(--portal-muted); margin-bottom: 2rem;">Contáctanos directamente por WhatsApp para cualquier consulta sobre tus proyectos o pagos.</p>
            <a href="https://wa.me/51998289752" target="_blank" class="btn" style="background: #25D366; text-decoration: none;">
                <i class="ph ph-chat-circle-text"></i> Enviar Mensaje
            </a>
        </div>
        
        <button class="btn" style="background: var(--portal-surface); color: var(--portal-text); border: 1px solid var(--portal-border); margin-top: 1rem;" onclick="logout()">
            <i class="ph ph-sign-out"></i> Cerrar Sesión
        </button>
    </div>
</div>

<!-- Bottom Navigation -->
<div class="bottom-nav" id="bottom-nav" style="display: flex;">
    <div class="nav-item active" onclick="switchView('home')" data-target="home">
        <i class="ph ph-house"></i>
        <span>Inicio</span>
    </div>
    <div class="nav-item" onclick="switchView('payments')" data-target="payments">
        <i class="ph ph-receipt"></i>
        <span>Pagos</span>
    </div>
    <div class="nav-item" onclick="switchView('projects')" data-target="projects">
        <i class="ph ph-kanban"></i>
        <span>Proyectos</span>
    </div>
    <div class="nav-item" onclick="switchView('designs')" data-target="designs">
        <i class="ph ph-paint-brush"></i>
        <span>Diseños</span>
    </div>
    <div class="nav-item" onclick="switchView('drive')" data-target="drive">
        <i class="ph ph-folder-open"></i>
        <span>Archivos</span>
    </div>
    <div class="nav-item" onclick="switchView('support')" data-target="support">
        <i class="ph ph-headset"></i>
        <span>Soporte</span>
    </div>
</div>

<!-- File Viewer Overlay -->
<div class="file-viewer-overlay" id="file-viewer">
    <div class="viewer-header">
        <div style="font-weight: 600; font-size: 1.1rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 70%;" id="viewer-title">Archivo</div>
        <div style="display: flex; gap: 15px;">
            <a href="#" id="viewer-download" target="_blank" style="color: white; font-size: 1.5rem;"><i class="ph ph-download-simple"></i></a>
            <i class="ph ph-x" style="font-size: 1.5rem; cursor: pointer;" onclick="closeViewer()"></i>
        </div>
    </div>
    <div class="viewer-content" id="viewer-content">
        <!-- Iframe or Img injected here -->
    </div>
</div>

<script>
let portalData = {};

// Theme Initialization
const savedTheme = localStorage.getItem('portal-theme');
if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    document.documentElement.setAttribute('data-theme', 'dark');
} else {
    document.documentElement.setAttribute('data-theme', 'light');
}

function updateThemeIcon() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const icon = document.getElementById('theme-icon');
    if (icon) {
        if (isDark) {
            icon.classList.remove('ph-moon');
            icon.classList.add('ph-sun');
        } else {
            icon.classList.remove('ph-sun');
            icon.classList.add('ph-moon');
        }
    }
}
updateThemeIcon();

function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('portal-theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('portal-theme', 'dark');
    }
    updateThemeIcon();
}

function switchView(viewName) {
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    
    document.getElementById('view-' + viewName).classList.add('active');
    document.querySelector(`.nav-item[data-target="${viewName}"]`)?.classList.add('active');
    
    if (viewName === 'payments') loadPayments();
    if (viewName === 'projects') loadProjects();
    if (viewName === 'designs') loadDesigns();
    if (viewName === 'drive') loadDrive();
}

function logout() {
    fetch('ajax_portal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=logout'
    }).then(() => window.location.reload());
}

// Initial Load
loadDashboard();

function loadDashboard() {
    fetch('ajax_portal.php?action=get_dashboard')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('client-name-display').innerText = data.client.name.split(' ')[0];
            document.getElementById('client-avatar').innerText = data.client.name.charAt(0).toUpperCase();
            
            // Calculate pending
            let saldo = 0;
            let unpaidCount = 0;
            data.payments.forEach(p => {
                let status = p.status.toUpperCase();
                let balance = parseFloat(p.total) || 0;
                
                // Detailed parsing if schedule exists
                const cronograma = typeof p.schedule_json === 'string' ? JSON.parse(p.schedule_json) : p.schedule_json;
                if (cronograma && cronograma.length > 0) {
                    let pend = 0;
                    cronograma.forEach(c => {
                        if (c.estado !== 'pagado') {
                            pend += parseFloat(c.monto || 0);
                        }
                    });
                    balance = pend;
                } else if (status === 'PAGADO') {
                    balance = 0;
                }
                saldo += balance;
                if (balance > 0) unpaidCount++;
            });
            document.getElementById('home-saldo').innerText = `S/ ${saldo.toFixed(2)}`;
            const badge = document.getElementById('home-unpaid-badge');
            if (badge) {
                if (unpaidCount > 0) {
                    badge.innerText = `${unpaidCount} Por pagar`;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            document.getElementById('home-proyectos-count').innerText = data.projects.length;
            const homeArchivosCount = document.getElementById('home-archivos-count');
            if (homeArchivosCount) homeArchivosCount.innerText = data.folders.length;
            document.getElementById('home-disenos-count').innerText = (data.designTasks || []).length;
        }
    });
}

function loadPayments() {
    const container = document.getElementById('payments-list');
    fetch('ajax_portal.php?action=get_dashboard')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.payments.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">No tienes notas de pago registradas.</div>';
                return;
            }
            
            let html = '';
            data.payments.forEach(p => {
                const isPaid = p.status.toUpperCase() === 'PAGADO';
                const badgeClass = isPaid ? 'paid' : 'pending';
                const url = `index.php?module=admin&action=payment_note_webview&token=${p.public_token}&view=public`;
                
                html += `
                    <div class="card payment-card" onclick="openViewer('${url}', '${p.note_code}', '')">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight: 700; color: var(--portal-primary);">${p.note_code}</span>
                            <span class="status-badge ${badgeClass}">${p.status}</span>
                        </div>
                        <div style="font-size: 0.9rem;">${p.company_name}</div>
                        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid var(--portal-border);">
                            <div>
                                <div class="stat-label">Total</div>
                                <div style="font-weight: 700;">S/ ${parseFloat(p.total).toFixed(2)}</div>
                            </div>
                            <div style="text-align: right;">
                                <div class="stat-label">Fecha</div>
                                <div>${p.start_date}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    });
}

function loadProjects() {
    const container = document.getElementById('projects-list');
    fetch('ajax_portal.php?action=get_dashboard')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.projects.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">No tienes proyectos activos.</div>';
                return;
            }
            
            let html = '';
            // Store projects globally
            window.currentProjects = data.projects;
            
            data.projects.forEach(p => {
                html += `
                    <div class="card" onclick="openProjectDetails(${p.id})" style="display:flex; align-items:center; justify-content:space-between; cursor: pointer;">
                        <div style="display:flex; align-items:center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; background: var(--portal-bg); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; overflow: hidden;">
                                ${p.brand_logo ? `<img src="${p.brand_logo}" style="width:100%; height:100%; object-fit:cover;">` : p.brand_name.charAt(0)}
                            </div>
                            <div>
                                <div style="font-weight: 700;">${p.brand_name}</div>
                                <div style="font-size: 0.8rem; color: var(--portal-muted);">${p.month_name}</div>
                            </div>
                        </div>
                        <i class="ph ph-caret-right" style="color: var(--portal-muted);"></i>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    });
}

function loadDesigns() {
    const container = document.getElementById('designs-list');
    fetch('ajax_portal.php?action=get_dashboard')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (!data.designTasks || data.designTasks.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">No tienes tareas de diseño activas.</div>';
                return;
            }
            
            let html = '';
            window.currentDesigns = data.designTasks;
            
            data.designTasks.forEach(dt => {
                let badgeClass = 'pending';
                if(dt.priority === 'alta') badgeClass = 'late';
                if(dt.status === 'Terminado') badgeClass = 'paid';
                
                html += `
                    <div class="card" onclick="openDesignDetails(${dt.id})" style="display:flex; flex-direction:column; gap: 0.5rem; cursor:pointer;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight: 700; color: var(--portal-text);">${dt.name}</span>
                            <span class="status-badge ${badgeClass}">${dt.status}</span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--portal-muted);">Prioridad: <strong style="text-transform:uppercase;">${dt.priority}</strong></div>
                        ${dt.due_date ? `<div style="font-size: 0.8rem; color: var(--portal-muted);">Entrega: ${new Date(dt.due_date).toLocaleDateString()}</div>` : ''}
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    });
}

let portalDrivePath = [{id: null, name: 'Raíz'}];

function navigateToFolder(folderId, folderName) {
    const idx = portalDrivePath.findIndex(p => p.id === folderId);
    if (idx !== -1) {
        portalDrivePath = portalDrivePath.slice(0, idx + 1);
    } else {
        portalDrivePath.push({id: folderId, name: folderName});
    }
    loadDrive(folderId);
}

function loadDrive(folderId = null) {
    if (folderId === null) {
        portalDrivePath = [{id: null, name: 'Raíz'}];
    }

    const container = document.getElementById('drive-list');
    container.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>';
    
    let url = 'ajax_portal.php?action=get_drive_files';
    if (folderId) url += '&folder_id=' + folderId;
    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let html = '';
            
            // Breadcrumbs UI
            if (portalDrivePath.length > 1) {
                html += '<div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.5rem; overflow-x:auto; padding-bottom:0.5rem; white-space:nowrap;">';
                portalDrivePath.forEach((p, idx) => {
                    const isLast = idx === portalDrivePath.length - 1;
                    const folderIdArg = p.id === null ? 'null' : `'${p.id}'`;
                    if (!isLast) {
                        html += `<div style="cursor:pointer; color:var(--portal-primary); font-weight:600; padding:0.25rem 0.5rem; border-radius:6px; transition:0.2s; background:rgba(59,130,246,0.1);" onclick="navigateToFolder(${folderIdArg}, '${p.name}')">${p.name}</div>`;
                        html += `<i class="ph ph-caret-right" style="color:var(--portal-muted);"></i>`;
                    } else {
                        html += `<div style="font-weight:600; color:var(--portal-text); padding:0.25rem 0.5rem;">${p.name}</div>`;
                    }
                });
                html += '</div>';
            }

            if (data.files.length === 0) {
                container.innerHTML = html + '<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">Carpeta vacía.</div>';
                return;
            }
            
            html += '<div class="drive-grid-container" style="background: transparent; padding: 0;"><div class="drive-grid" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">';
            data.files.forEach(f => {
                const isImg = f.mimeType.startsWith('image/');
                const isVideo = f.mimeType.startsWith('video/');
                const isFolder = f.mimeType === 'application/vnd.google-apps.folder';
                
                const safeName = f.name.replace(/'/g, "\\'");
                const clickAction = isFolder ? `navigateToFolder('${f.id}', '${safeName}')` : `openViewer('${f.webViewLink}', '${safeName}', '${f.webContentLink}')`;
                
                if (isFolder) {
                    html += `
                        <div class="drive-item" onclick="${clickAction}">
                            <div class="folder-icon">
                                <div class="folder-back"></div>
                                <div class="folder-tab folder-tab-1"></div>
                                <div class="folder-tab folder-tab-2"></div>
                                <div class="folder-tab folder-tab-3"></div>
                                <div class="folder-paper"></div>
                                <div class="folder-front"></div>
                            </div>
                            <div class="item-name" title="${f.name}">${f.name}</div>
                        </div>
                    `;
                } else {
                    let iconHtml = '';
                    const isPdf = f.mimeType.includes('pdf');
                    const isPs = f.mimeType.includes('photoshop') || f.name.toLowerCase().endsWith('.psd');
                    const isAi = f.mimeType.includes('illustrator') || f.name.toLowerCase().endsWith('.ai');
                    const isZip = f.mimeType.includes('zip') || f.mimeType.includes('rar') || f.mimeType.includes('tar') || f.name.toLowerCase().endsWith('.zip');
                    
                    if (isImg) {
                        let thumbUrl = f.thumbnailLink ? f.thumbnailLink.replace('=s220', '=s400') : f.iconLink;
                        iconHtml = `<img src="${thumbUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:7px;">`;
                    } else if (isPs) {
                        iconHtml = `<div style="width:100%;height:100%;background:#001e36;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#31a8ff;font-weight:800;font-size:2.2rem;font-family:Arial, sans-serif;letter-spacing:-2px;padding-right:3px;">Ps</div>`;
                    } else if (isAi) {
                        iconHtml = `<div style="width:100%;height:100%;background:#330000;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#ff9a00;font-weight:800;font-size:2.2rem;font-family:Arial, sans-serif;letter-spacing:-2px;padding-right:3px;">Ai</div>`;
                    } else if (isPdf) {
                        iconHtml = `<div style="width:100%;height:100%;background:#fff;border-radius:7px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#f40f02;"><i class="ph-fill ph-file-pdf" style="font-size:3rem;"></i></div>`;
                    } else if (isZip) {
                        iconHtml = `<div style="width:100%;height:100%;background:#f59e0b;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;"><i class="ph-fill ph-file-archive" style="font-size:2.5rem;"></i></div>`;
                    } else if (isVideo) {
                        iconHtml = '<i class="ph ph-video-camera" style="font-size:2.5rem; color: #ef4444;"></i>';
                    } else {
                        iconHtml = '<i class="ph ph-file-text" style="font-size:2.5rem; color: #94a3b8;"></i>';
                    }
                    
                    html += `
                        <div class="drive-item" onclick="${clickAction}">
                            <div class="file-icon" style="overflow:hidden; border: none; background: transparent;">
                                ${iconHtml}
                            </div>
                            <div class="item-name" title="${f.name}">${f.name}</div>
                        </div>
                    `;
                }
            });
            html += '</div></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">${data.error}</div>`;
        }
    });
}

function openProjectDetails(projectId) {
    const project = window.currentProjects.find(p => p.id == projectId);
    if(!project) return;
    
    document.getElementById('detail-top-title').innerText = 'Proyecto';
    
    const logoContainer = document.getElementById('detail-logo');
    if (project.brand_logo) {
        logoContainer.innerHTML = `<img src="${project.brand_logo}" style="width:100%; height:100%; object-fit:cover;">`;
    } else {
        logoContainer.innerHTML = project.brand_name.charAt(0);
    }
    
    document.getElementById('detail-title').innerText = project.brand_name;
    document.getElementById('detail-subtitle').innerText = project.month_name;
    
    let statusClass = 'pending';
    let statusText = (project.project_status || 'Pendiente').toUpperCase();
    if(statusText === 'TERMINADO' || statusText === 'APROBADO') statusClass = 'paid';
    
    document.getElementById('detail-status').className = `status-badge ${statusClass}`;
    document.getElementById('detail-status').innerText = statusText;
    
    document.getElementById('detail-metric-label').innerText = 'Posts en el Mes';
    document.getElementById('detail-metric-value').innerText = project.post_count || 0;
    
    // Team
    let teamHtml = '';
    if (project.team && project.team.length > 0) {
        project.team.forEach(u => {
            if (u.avatar) {
                teamHtml += `<img src="${u.avatar}" style="width:40px; height:40px; border-radius:50%; object-fit:cover;" title="${u.name}">`;
            } else {
                teamHtml += `<div style="width:40px; height:40px; border-radius:50%; background:var(--portal-primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:bold;" title="${u.name}">${u.name.charAt(0)}</div>`;
            }
        });
    } else {
        teamHtml = '<span style="color:var(--portal-muted); font-size:0.85rem;">No hay equipo asignado</span>';
    }
    document.getElementById('detail-team').innerHTML = teamHtml;
    
    // Actions
    let actionsHtml = `
        <button class="btn" onclick="openViewer('public_board.php?id=${project.id}', 'Tablero: ${project.brand_name}', '')">
            <i class="ph ph-kanban"></i> Ver Tablero de Posts
        </button>
    `;
    if (project.drive_folder_id) {
        actionsHtml += `
            <button class="btn" style="background: white; color: #10b981; border: 1px solid #10b981;" onclick="switchView('drive')">
                <i class="ph ph-folder-open"></i> Ver Archivos en Drive
            </button>
        `;
    }
    document.getElementById('detail-actions').innerHTML = actionsHtml;
    
    // Show view
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.getElementById('view-project-details').classList.add('active');
}

function openDesignDetails(designId) {
    const design = window.currentDesigns.find(d => d.id == designId);
    if(!design) return;
    
    document.getElementById('detail-top-title').innerText = 'Diseño';
    
    const logoContainer = document.getElementById('detail-logo');
    logoContainer.innerHTML = '<i class="ph ph-paint-brush"></i>';
    
    document.getElementById('detail-title').innerText = design.name;
    document.getElementById('detail-subtitle').innerText = design.due_date ? `Entrega: ${new Date(design.due_date).toLocaleDateString()}` : 'Sin fecha de entrega';
    
    let statusClass = 'pending';
    if(design.priority === 'alta') statusClass = 'late';
    if(design.status === 'Terminado') statusClass = 'paid';
    
    document.getElementById('detail-status').className = `status-badge ${statusClass}`;
    document.getElementById('detail-status').innerText = design.status;
    
    document.getElementById('detail-metric-label').innerText = 'Prioridad';
    document.getElementById('detail-metric-value').innerText = design.priority.toUpperCase();
    
    // Team
    let teamHtml = '';
    if (design.team && design.team.length > 0) {
        design.team.forEach(u => {
            if (u.avatar) {
                teamHtml += `<img src="${u.avatar}" style="width:40px; height:40px; border-radius:50%; object-fit:cover;" title="${u.name}">`;
            } else {
                teamHtml += `<div style="width:40px; height:40px; border-radius:50%; background:var(--portal-primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:bold;" title="${u.name}">${u.name.charAt(0)}</div>`;
            }
        });
    } else {
        teamHtml = '<span style="color:var(--portal-muted); font-size:0.85rem;">No hay equipo asignado</span>';
    }
    document.getElementById('detail-team').innerHTML = teamHtml;
    
    // Actions
    let actionsHtml = '';
    if (design.description) {
        actionsHtml += `<div style="text-align:left; background:var(--portal-bg); padding:1rem; border-radius:12px; margin-bottom:1rem; font-size:0.9rem;">${design.description}</div>`;
    }
    
    if (design.subtasks && design.subtasks.length > 0) {
        actionsHtml += `<h4 style="margin-bottom:0.5rem; color:var(--portal-text); font-size:1rem;">Subtareas</h4>`;
        actionsHtml += `<div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">`;
        design.subtasks.forEach(st => {
            const isCompleted = st.is_completed == 1;
            actionsHtml += `
                <div style="display:flex; align-items:center; gap:0.75rem; background:var(--portal-surface); border:1px solid var(--portal-border); padding:0.75rem 1rem; border-radius:8px;">
                    <i class="ph ${isCompleted ? 'ph-check-circle' : 'ph-circle'}" style="color: ${isCompleted ? '#10b981' : 'var(--portal-muted)'}; font-size:1.25rem;"></i>
                    <span style="${isCompleted ? 'text-decoration:line-through; color:var(--portal-muted);' : 'color:var(--portal-text);'} flex:1; font-size:0.95rem;">${st.title}</span>
                </div>
            `;
        });
        actionsHtml += `</div>`;
    }

    if (design.external_links && design.external_links.length > 0) {
        actionsHtml += `<h4 style="margin-bottom:0.5rem; color:var(--portal-text); font-size:1rem;">Enlaces de Referencia</h4>`;
        actionsHtml += `<div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">`;
        design.external_links.forEach(link => {
            actionsHtml += `
                <a href="${link.url}" target="_blank" style="display:flex; align-items:center; gap:0.75rem; background:var(--portal-surface); border:1px solid var(--portal-border); padding:0.75rem 1rem; border-radius:8px; text-decoration:none; color:var(--portal-primary); transition:all 0.2s;">
                    <i class="ph ph-link" style="font-size:1.25rem;"></i>
                    <span style="flex:1; font-size:0.95rem;">${link.title || link.url}</span>
                </a>
            `;
        });
        actionsHtml += `</div>`;
    }
    
    if (design.drive_folder_id) {
        actionsHtml += `
        <button class="btn" onclick="openDriveFolder('${design.drive_folder_id}')" style="background:var(--portal-primary); color:white; width:100%; justify-content:center;">
            <i class="ph ph-folder-open"></i> Archivos del Diseño
        </button>
        `;
    }
    document.getElementById('detail-actions').innerHTML = actionsHtml;
    
    // Show view
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.getElementById('view-project-details').classList.add('active');
}

function backToList() {
    // Determine if we came from projects or designs by checking the top title
    const title = document.getElementById('detail-top-title').innerText;
    if (title === 'Diseño') {
        switchView('designs');
    } else {
        switchView('projects');
    }
}

function openViewer(viewUrl, title, downloadUrl) {
    document.getElementById('viewer-title').innerText = title;
    
    if (downloadUrl) {
        document.getElementById('viewer-download').href = downloadUrl;
        document.getElementById('viewer-download').style.display = 'block';
    } else {
        document.getElementById('viewer-download').style.display = 'none';
    }
    
    // Embed URL format for Google Drive
    let embedUrl = viewUrl;
    if (viewUrl.includes('drive.google.com/file/d/')) {
        embedUrl = viewUrl.replace(/\/view.*$/, '/preview');
    }
    
    document.getElementById('viewer-content').innerHTML = `<iframe src="${embedUrl}" allowfullscreen></iframe>`;
    document.getElementById('file-viewer').classList.add('active');
}

function closeViewer() {
    document.getElementById('file-viewer').classList.remove('active');
    document.getElementById('viewer-content').innerHTML = '';
}
</script>
</body>
</html>
