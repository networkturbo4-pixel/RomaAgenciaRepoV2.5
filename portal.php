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

        .task-tab { flex: 1; padding: 0.75rem; background: transparent; border: none; color: var(--portal-muted); font-weight: 600; font-size: 0.9rem; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .task-tab.active { background: var(--portal-surface); color: var(--portal-text); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .subtask-item { background: var(--portal-bg); padding: 1rem; border-radius: 8px; display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; }
        .subtask-checkbox { width: 20px; height: 20px; border: 2px solid var(--portal-muted); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;}
        .subtask-checkbox.checked { background: #10b981; border-color: #10b981; color: white; }
        
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
            <h1 class="portal-title" style="font-size: clamp(1.4rem, 5vw, 2rem); color: var(--portal-text); margin-bottom: 0.5rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ¡Hola, <span id="client-name-display" style="color: var(--portal-primary-contrast);">Cargando...</span>!&nbsp;👋
            </h1>
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
                    <div id="home-total-descuento" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--portal-border);">
                        <div style="font-size: 1.1rem; font-weight: 700; color: var(--portal-text);" id="home-total-descuento-value">S/ 0.00</div>
                        <div style="font-size: 0.8rem; color: var(--portal-muted); display: flex; align-items: center; gap: 4px;">
                            Precio Total
                            <span id="home-discount-badge" style="background: rgba(239,68,68,0.15); color: #ef4444; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; display: none;"></span>
                        </div>
                    </div>
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
            <h1 class="portal-title" style="font-size: 1.2rem; margin: 0;" id="detail-top-title">Editar Tarea</h1>
        </div>
    </div>
    
    <div style="padding: 1.5rem;">
        <!-- Tabs -->
        <div style="display: flex; gap: 0.5rem; background: var(--portal-bg); padding: 5px; border-radius: 12px; margin-bottom: 1.5rem; overflow-x: auto;">
            <button id="tab-btn-detalles" onclick="switchTaskTab('detalles')" class="task-tab active">Detalles</button>
            <button id="tab-btn-subtareas" onclick="switchTaskTab('subtareas')" class="task-tab">Subtareas</button>
            <button id="tab-btn-archivos" onclick="switchTaskTab('archivos')" class="task-tab">Archivos</button>
            <button id="tab-btn-avances" onclick="switchTaskTab('avances')" class="task-tab">Avances</button>
        </div>

        <!-- Tab: Detalles -->
        <div id="tab-detalles" class="task-tab-content active">
            <h3 style="font-size: 0.85rem; color: var(--portal-muted); margin-bottom: 0.5rem;">Título</h3>
            <div id="detail-title" style="font-size: 1.1rem; font-weight: 600; background: var(--portal-bg); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"></div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="font-size: 0.85rem; color: var(--portal-muted); margin-bottom: 0.5rem;">Prioridad</h3>
                    <div id="detail-status" style="display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
                        MEDIA
                    </div>
                </div>
                <div>
                    <h3 style="font-size: 0.85rem; color: var(--portal-muted); margin-bottom: 0.5rem;">Fecha y Hora Límite</h3>
                    <div id="detail-subtitle" style="background: var(--portal-bg); padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.9rem;"></div>
                </div>
            </div>

            <h3 style="font-size: 0.85rem; color: var(--portal-muted); margin-bottom: 0.5rem;">Descripción</h3>
            <div id="detail-description" style="background: var(--portal-bg); padding: 1rem; border-radius: 8px; font-size: 0.9rem; min-height: 100px;"></div>

            <h3 style="font-size: 0.85rem; color: var(--portal-muted); margin: 1.5rem 0 0.5rem 0;">Equipo Asignado</h3>
            <div id="detail-team" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;">
                <!-- Avatars will be injected here -->
            </div>
        </div>

        <!-- Tab: Subtareas -->
        <div id="tab-subtareas" class="task-tab-content" style="display: none;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Desglose de Tareas</h3>
            <div id="detail-subtasks-list" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <!-- Subtasks injected here -->
            </div>
        </div>

        <!-- Tab: Archivos -->
        <div id="tab-archivos" class="task-tab-content" style="display: none;">
            <div style="background: var(--portal-bg); border: 1px solid var(--portal-border); border-radius: 12px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="color: #10b981; font-weight: 600; margin-bottom: 0.25rem;">Conexión a Drive</h3>
                        <div style="font-size: 0.85rem; color: var(--portal-muted); margin-bottom: 0.5rem;">Accede a la carpeta raíz del proyecto y sus archivos finales.</div>
                        <div id="detail-drive-status" style="font-size: 0.85rem; font-weight: 600;">
                            <i class="ph ph-check-circle"></i> Conectado a carpeta
                        </div>
                    </div>
                    <div id="detail-actions">
                        <!-- Botón Ver Archivos -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Avances -->
        <div id="tab-avances" class="task-tab-content" style="display: none;">
            <div id="detail-advances-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem;">
                <!-- Advances injected here -->
            </div>
        </div>
    </div>
</div>

<!-- Project Detail View (Meses) -->
<div id="view-project-detail-simple" class="view" style="background: var(--portal-surface);">
    <div class="portal-header" style="border-bottom: 1px solid var(--portal-border); padding-bottom: 1rem; position: sticky; top: 0; background: var(--portal-surface); z-index: 90;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="btn-icon" onclick="switchView('projects')" style="background: none; border: none; font-size: 1.5rem; color: var(--portal-text); cursor: pointer; padding: 0;">
                <i class="ph ph-arrow-left"></i>
            </button>
            <h1 class="portal-title" style="font-size: 1.2rem; margin: 0;">Proyecto</h1>
        </div>
    </div>
    
    <div style="padding: 2rem 1.5rem; text-align: center;">
        <div id="proj-logo" style="width: 80px; height: 80px; margin: 0 auto 1rem auto; background: var(--portal-bg); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--portal-primary); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        </div>
        <h2 id="proj-title" style="font-size: 1.5rem; margin-bottom: 0.25rem;">Cargando...</h2>
        <p id="proj-subtitle" style="color: var(--portal-muted); font-size: 0.9rem; margin-bottom: 1rem;"></p>
        
        <div id="proj-status" style="display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
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
                    <div class="stat-label">Posts en el Mes</div>
                    <div style="font-weight: 700; font-size: 1.2rem;" id="proj-metric-value">0</div>
                </div>
            </div>
        </div>

        <h3 style="font-size: 1rem; margin: 1.5rem 0 1rem 0;">Equipo Asignado</h3>
        <div id="proj-team" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;">
        </div>
        
        <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem;" id="proj-actions">
        </div>
    </div>
</div>

<!-- Drive View -->
<div id="view-drive" class="view">
    <div class="portal-header">
        <h1 class="portal-title">Mis Archivos</h1>
    </div>
    
    <div class="drive-toolbar">
        <div class="drive-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" id="drive-search-input" placeholder="Buscar archivos y carpetas..." oninput="filterDriveItems()">
        </div>
        <div class="drive-view-toggles">
            <button class="drive-view-btn active" id="btn-view-grid" onclick="setDriveView('grid')" title="Vista Cuadrícula">
                <i class="ph ph-squares-four"></i>
            </button>
            <button class="drive-view-btn" id="btn-view-list" onclick="setDriveView('list')" title="Vista Lista">
                <i class="ph ph-list-dashes"></i>
            </button>
        </div>
    </div>

    <div class="content-padding" id="drive-list">
        <div style="text-align: center; padding: 2rem;"><div class="loader" style="margin: 0 auto;"></div></div>
    </div>
</div>

<!-- Drive Selection Bar -->
<div class="drive-selection-bar" id="drive-selection-bar">
    <div class="drive-selection-info">
        <div class="drive-selection-count" id="drive-selection-count">0</div>
        <div class="drive-selection-label">selec.</div>
    </div>
    <div class="drive-selection-actions">
        <button class="drive-action-btn" id="btn-drive-rename" onclick="renameSelected()" style="display:none;"><i class="ph ph-pencil-simple"></i> Nombre</button>
        <button class="drive-action-btn" id="btn-drive-copyurl" onclick="copyLinkSelected()" style="display:none;"><i class="ph ph-link"></i> Copiar URL</button>
        <button class="drive-action-btn" onclick="moveSelectedFiles()"><i class="ph ph-folder-notch-open"></i> Mover</button>
        <button class="drive-action-btn" onclick="downloadSelectedFiles()"><i class="ph ph-download-simple"></i> Descargar</button>
        <button class="drive-action-btn danger" onclick="deleteSelectedFiles()"><i class="ph ph-trash"></i> Eliminar</button>
        <button class="drive-action-btn" id="btn-drive-info" onclick="infoSelected()" style="display:none;"><i class="ph ph-info"></i> Info</button>
    </div>
    <button class="drive-action-btn close-sel" onclick="clearDriveSelection()"><i class="ph ph-x"></i></button>
</div>

<!-- Move Folder Modal -->
<div class="share-modal-overlay" id="modal-move-folder">
    <div class="share-modal">
        <div class="share-modal-header">
            <div class="share-modal-title">Mover a...</div>
            <i class="ph ph-x" style="cursor: pointer; font-size: 1.2rem; color: var(--portal-muted);" onclick="closeMoveModal()"></i>
        </div>
        <div id="move-folder-tree" style="max-height: 300px; overflow-y: auto; margin-bottom: 1rem;">
            <!-- Tree loaded via AJAX -->
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <button class="btn" style="background: transparent; color: var(--portal-text); border: 1px solid var(--portal-border);" onclick="closeMoveModal()">Cancelar</button>
            <button class="btn btn-primary" id="btn-confirm-move" disabled>Mover Aquí</button>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="share-modal-overlay" id="modal-share">
    <div class="share-modal">
        <div class="share-modal-header">
            <div class="share-modal-title">Compartir Archivo</div>
            <i class="ph ph-x" style="cursor: pointer; font-size: 1.2rem; color: var(--portal-muted);" onclick="closeShareModal()"></i>
        </div>
        <div style="margin-bottom: 1rem; color: var(--portal-muted); font-size: 0.9rem;">
            Cualquier persona con este enlace podrá ver y descargar el archivo.
        </div>
        <div class="share-input-group">
            <input type="text" id="share-link-input" readonly>
            <button onclick="copyShareLink()">Copiar</button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="portal-toast" class="portal-toast">
    <div class="portal-toast-icon" id="toast-icon"><i class="ph ph-check-circle"></i></div>
    <div class="portal-toast-msg" id="toast-msg"></div>
</div>

<!-- Rename Modal -->
<div class="share-modal-overlay" id="modal-rename">
    <div class="share-modal">
        <div class="share-modal-header">
            <div class="share-modal-title">Cambiar nombre</div>
            <i class="ph ph-x" style="cursor: pointer; font-size: 1.2rem; color: var(--portal-muted);" onclick="closeRenameModal()"></i>
        </div>
        <div style="margin-top: 1rem;">
            <label style="font-size: 0.8rem; color: var(--portal-muted); display: block; margin-bottom: 0.5rem;">Nuevo nombre</label>
            <input type="text" id="rename-input" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 10px; border: 1px solid var(--portal-border); background: var(--portal-bg); color: var(--portal-text); font-size: 0.95rem; outline: none; box-sizing: border-box;" autocomplete="off">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <button class="btn" style="background: transparent; color: var(--portal-text); border: 1px solid var(--portal-border);" onclick="closeRenameModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmRename()">Guardar</button>
        </div>
    </div>
</div>

<!-- Info Modal -->
<div class="share-modal-overlay" id="modal-info">
    <div class="share-modal">
        <div class="share-modal-header">
            <div class="share-modal-title">Información</div>
            <i class="ph ph-x" style="cursor: pointer; font-size: 1.2rem; color: var(--portal-muted);" onclick="closeInfoModal()"></i>
        </div>
        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <div>
                <div style="font-size: 0.75rem; color: var(--portal-muted); margin-bottom: 0.25rem;">Nombre</div>
                <div id="info-name" style="font-weight: 600; font-size: 0.95rem; word-break: break-all;"></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--portal-muted); margin-bottom: 0.25rem;">Tipo</div>
                <div id="info-type" style="font-weight: 600; font-size: 0.95rem;"></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--portal-muted); margin-bottom: 0.25rem;">ID de Drive</div>
                <div id="info-id" style="font-size: 0.85rem; font-family: monospace; background: var(--portal-bg); padding: 0.5rem 0.75rem; border-radius: 8px; word-break: break-all;"></div>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
            <button class="btn" style="background: transparent; color: var(--portal-text); border: 1px solid var(--portal-border);" onclick="closeInfoModal()">Cerrar</button>
        </div>
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
        <div style="display: flex; gap: 15px; align-items: center;">
            <i class="ph ph-share-network" id="viewer-share" style="color: white; font-size: 1.5rem; cursor: pointer; display: none;" title="Compartir"></i>
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
            let totalConDescuento = 0;
            let hasAnyDiscount = false;
            let maxDiscountPercent = 0;
            data.payments.forEach(p => {
                let status = p.status.toUpperCase();
                let balance = parseFloat(p.total) || 0;
                const discountPct = parseFloat(p.discount_percent) || 0;
                
                // Track total with discount (p.total already includes discount)
                totalConDescuento += parseFloat(p.total) || 0;
                if (discountPct > 0) {
                    hasAnyDiscount = true;
                    if (discountPct > maxDiscountPercent) maxDiscountPercent = discountPct;
                }
                
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

                // Subtract abonos (advances/deposits) from balance
                const abonos = typeof p.abonos_json === 'string' ? JSON.parse(p.abonos_json || '[]') : (p.abonos_json || []);
                if (abonos && abonos.length > 0) {
                    const totalAbonos = abonos.reduce((sum, a) => sum + parseFloat(a.monto || 0), 0);
                    balance = Math.max(0, balance - totalAbonos);
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
            
            // Show total with discount
            const totalDescuentoEl = document.getElementById('home-total-descuento-value');
            if (totalDescuentoEl) {
                totalDescuentoEl.innerText = `S/ ${totalConDescuento.toFixed(2)}`;
            }
            const discBadge = document.getElementById('home-discount-badge');
            if (discBadge && hasAnyDiscount) {
                discBadge.innerText = `-${maxDiscountPercent}%`;
                discBadge.style.display = 'inline-block';
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
                
                // Calculate saldo pendiente subtracting abonos
                let montoTotal = parseFloat(p.total) || 0;
                let saldoPendiente = montoTotal;
                const abonos = typeof p.abonos_json === 'string' ? JSON.parse(p.abonos_json || '[]') : (p.abonos_json || []);
                if (abonos && abonos.length > 0) {
                    const totalAbonos = abonos.reduce((sum, a) => sum + parseFloat(a.monto || 0), 0);
                    saldoPendiente = Math.max(0, montoTotal - totalAbonos);
                }
                if (isPaid) saldoPendiente = 0;

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
                                <div style="font-weight: 700;">S/ ${montoTotal.toFixed(2)}</div>
                            </div>
                            <div style="text-align: right;">
                                <div class="stat-label">Fecha</div>
                                <div>${p.start_date}</div>
                            </div>
                        </div>
                        ${saldoPendiente !== montoTotal ? `
                        <div style="display:flex; justify-content:space-between; margin-top:0.25rem;">
                            <div>
                                <div class="stat-label" style="color: ${isPaid ? 'var(--portal-primary)' : '#ef4444'}; font-weight: 700;">Saldo Pendiente</div>
                                <div style="font-weight: 800; font-size: 1.1rem; color: ${isPaid ? 'var(--portal-primary)' : '#ef4444'};">S/ ${saldoPendiente.toFixed(2)}</div>
                            </div>
                        </div>
                        ` : ''}
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
            
            const isRoot = (folderId === null);

            function renderFilesGroup(files) {
                if (files.length === 0) return '<div style="color:var(--portal-muted); font-size: 0.95rem; text-align: center; padding: 2rem 0;">No hay carpetas en esta sección.</div>';
                let groupHtml = '<div class="drive-grid-container" style="background: transparent; padding: 0;"><div class="drive-grid" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1.5rem;">';
                files.forEach(f => {
                    const isImg = f.mimeType.startsWith('image/');
                    const isVideo = f.mimeType.startsWith('video/');
                    const isFolder = f.mimeType === 'application/vnd.google-apps.folder';
                    
                    const safeName = f.name.replace(/'/g, "\\'");
                    const safeNameLower = f.name.toLowerCase().replace(/"/g, "&quot;");
                    const clickAction = `handleDriveItemClick(event, '${f.id}', ${isFolder}, '${safeName}', '${f.webViewLink}', '${f.webContentLink}')`;
                    
                    if (isFolder) {
                        groupHtml += `
                            <div class="drive-item" data-name="${safeNameLower}" data-id="${f.id}" data-type="folder" data-view="${f.webViewLink}" onclick="${clickAction}">
                                <input type="checkbox" class="drive-item-checkbox" value="${f.id}" onclick="toggleDriveSelection(event)">
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
                        
                        if (isImg || (f.hasThumbnail && f.thumbnailLink)) {
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
                        
                        groupHtml += `
                            <div class="drive-item" data-name="${safeNameLower}" data-id="${f.id}" data-type="file" data-view="${f.webViewLink}" data-download="${f.webContentLink}" onclick="${clickAction}">
                                <input type="checkbox" class="drive-item-checkbox" value="${f.id}" onclick="toggleDriveSelection(event)">
                                <div class="file-icon" style="overflow:hidden; border: none; background: transparent;">
                                    ${iconHtml}
                                </div>
                                <div class="item-name" title="${f.name}">${f.name}</div>
                            </div>
                        `;
                    }
                });
                groupHtml += '</div></div>';
                return groupHtml;
            }

            html += renderFilesGroup(data.files);
            container.innerHTML = html;
        } else {
            container.innerHTML = `<div style="text-align:center; color:var(--portal-muted); padding: 2rem;">${data.error}</div>`;
        }
    });
}

function openProjectDetails(projectId) {
    const project = window.currentProjects.find(p => p.id == projectId);
    if(!project) return;
    
    const logoContainer = document.getElementById('proj-logo');
    if (project.brand_logo) {
        logoContainer.innerHTML = `<img src="${project.brand_logo}" style="width:100%; height:100%; object-fit:cover;">`;
    } else {
        logoContainer.innerHTML = project.brand_name.charAt(0);
    }
    
    document.getElementById('proj-title').innerText = project.brand_name;
    document.getElementById('proj-subtitle').innerText = project.month_name;
    
    let statusClass = 'pending';
    let statusText = (project.project_status || 'Pendiente').toUpperCase();
    if(statusText === 'TERMINADO' || statusText === 'APROBADO') statusClass = 'paid';
    
    document.getElementById('proj-status').className = `status-badge ${statusClass}`;
    document.getElementById('proj-status').innerText = statusText;
    
    document.getElementById('proj-metric-value').innerText = project.post_count || 0;
    
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
    document.getElementById('proj-team').innerHTML = teamHtml;
    
    // Actions
    let actionsHtml = `
        <button class="btn" onclick="openViewer('public_board.php?id=${project.id}', 'Tablero: ${project.brand_name}', '')">
            <i class="ph ph-kanban"></i> Ver Tablero de Posts
        </button>
    `;
    if (project.drive_folder_id) {
        actionsHtml += `
            <button class="btn" style="background: white; color: #10b981; border: 1px solid #10b981;" onclick="openDriveFolder('${project.drive_folder_id}', '${project.month_name}')">
                <i class="ph ph-folder-open"></i> Ver Archivos en Drive
            </button>
        `;
    }
    document.getElementById('proj-actions').innerHTML = actionsHtml;
    
    // Show view
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.getElementById('view-project-detail-simple').classList.add('active');
}

function switchTaskTab(tabName) {
    document.querySelectorAll('.task-tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.task-tab-content').forEach(el => el.style.display = 'none');
    document.getElementById('tab-btn-' + tabName).classList.add('active');
    document.getElementById('tab-' + tabName).style.display = 'block';
}

function openDesignDetails(designId) {
    const design = window.currentDesigns.find(d => d.id == designId);
    if(!design) return;
    
    document.getElementById('detail-top-title').innerText = 'Detalles de Tarea';
    
    // Switch to first tab
    switchTaskTab('detalles');
    
    document.getElementById('detail-title').innerText = design.name || 'Sin título';
    document.getElementById('detail-subtitle').innerText = design.due_date ? `${design.due_date.substring(0, 16)}` : 'Sin fecha de entrega';
    
    document.getElementById('detail-description').innerHTML = design.description || '<span style="color:var(--portal-muted)">Sin descripción</span>';
    
    let statusClass = 'pending';
    if(design.priority === 'alta') statusClass = 'late';
    if(design.priority === 'baja') statusClass = 'paid';
    
    document.getElementById('detail-status').className = `status-badge ${statusClass}`;
    document.getElementById('detail-status').innerText = design.priority ? design.priority.toUpperCase() : 'MEDIA';
    
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
    
    // Subtasks
    let stHtml = '';
    if (design.subtasks && design.subtasks.length > 0) {
        design.subtasks.forEach(st => {
            const isComp = st.is_completed == 1;
            stHtml += `
                <div class="subtask-item">
                    <div class="subtask-checkbox ${isComp ? 'checked' : ''}">
                        ${isComp ? '<i class="ph ph-check"></i>' : ''}
                    </div>
                    <div style="flex:1; ${isComp ? 'text-decoration: line-through; color: var(--portal-muted);' : ''}">
                        ${st.title}
                    </div>
                </div>
            `;
        });
    } else {
        stHtml = `
            <div style="text-align: center; padding: 2rem; color: var(--portal-muted);">
                <i class="ph ph-copy" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                <div>No hay subtareas registradas.</div>
            </div>
        `;
    }
    document.getElementById('detail-subtasks-list').innerHTML = stHtml;
    
    // Drive
    const driveStatus = document.getElementById('detail-drive-status');
    const actions = document.getElementById('detail-actions');
    if (design.drive_folder_id) {
        driveStatus.innerHTML = '<i class="ph ph-check-circle"></i> Conectado a carpeta (ID: '+design.drive_folder_id.substring(0,8)+'...)';
        driveStatus.style.color = '#10b981';
        actions.innerHTML = `<button class="btn" style="background: #10b981; color: white;" onclick="openDriveFolder('${design.drive_folder_id}', '${design.name.replace(/'/g, "\\'")}')"><i class="ph ph-folder"></i> Abrir Carpeta</button>`;
    } else {
        driveStatus.innerHTML = '<i class="ph ph-x-circle"></i> Sin conexión a Drive';
        driveStatus.style.color = 'var(--portal-muted)';
        actions.innerHTML = '';
    }
    
    // Advances
    let advHtml = '';
    if (design.advances && design.advances.length > 0) {
        design.advances.forEach(adv => {
            const safeName = (adv.file_name || 'Avance').replace(/'/g, "\\'");
            // Extract Google Drive file ID to generate thumbnail
            let thumbUrl = adv.file_path;
            const driveMatch = adv.file_path.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (driveMatch) {
                thumbUrl = 'https://drive.google.com/thumbnail?id=' + driveMatch[1] + '&sz=w400';
            }
            advHtml += `
                <div class="drive-item" onclick="openViewer('${adv.file_path}', '${safeName}', '')" style="cursor: pointer; background: var(--portal-bg); padding: 0.5rem; border-radius: 8px;">
                    <div style="overflow:hidden; width: 100%; height: 120px; border-radius: 4px; margin-bottom: 0.5rem; background: var(--portal-surface); display: flex; align-items: center; justify-content: center;">
                        <img src="${thumbUrl}" style="width:100%;height:100%;object-fit:cover; border-radius: 4px;" onerror="this.outerHTML='<i class=\\'ph ph-image\\' style=\\'font-size:2rem; color:var(--portal-muted)\\'></i>'">
                    </div>
                    <div style="font-size: 0.75rem; color: var(--portal-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${safeName}">${safeName}</div>
                </div>
            `;
        });
    } else {
        advHtml = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--portal-muted);">
                No hay avances subidos todavía.
            </div>
        `;
    }
    document.getElementById('detail-advances-list').innerHTML = advHtml;
    
    // Show view
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.getElementById('view-project-details').classList.add('active');
}

function openDriveFolder(folderId, folderName) {
    // Activate drive view without triggering loadDrive() root
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.getElementById('view-drive').classList.add('active');
    document.querySelector('.nav-item[data-target="drive"]')?.classList.add('active');
    
    // Reset breadcrumb path and navigate directly to the folder
    portalDrivePath = [{id: null, name: 'Raíz'}];
    navigateToFolder(folderId, folderName || 'Carpeta');
}

function backToList() {
    switchView('designs');
}

function openViewer(fileId, viewUrl, title, downloadUrl) {
    // If it was called from old advances (3 args), shift arguments
    if (arguments.length === 3) {
        downloadUrl = title;
        title = viewUrl;
        viewUrl = fileId;
        fileId = null;
    }

    document.getElementById('viewer-title').innerText = title;
    
    if (downloadUrl) {
        document.getElementById('viewer-download').href = downloadUrl;
        document.getElementById('viewer-download').style.display = 'block';
    } else {
        document.getElementById('viewer-download').style.display = 'none';
    }

    if (fileId && viewUrl) {
        document.getElementById('viewer-share').style.display = 'block';
        document.getElementById('viewer-share').onclick = function() {
            // Open share modal
            document.getElementById('share-link-input').value = viewUrl;
            document.getElementById('modal-share').classList.add('active');
            
            // Optionally, we could call ajax_portal.php?action=share_drive_item to make it public if it wasn't
            fetch('ajax_portal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=share_drive_item&file_id=' + fileId
            });
        };
    } else {
        document.getElementById('viewer-share').style.display = 'none';
    }
    
    // Embed URL format for Google Drive
    let embedUrl = viewUrl;
    if (viewUrl && viewUrl.includes('drive.google.com/file/d/')) {
        embedUrl = viewUrl.replace(/\/view.*$/, '/preview');
    }
    
    if (embedUrl) {
        document.getElementById('viewer-content').innerHTML = `<iframe src="${embedUrl}" allowfullscreen></iframe>`;
    } else {
        document.getElementById('viewer-content').innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:white; gap:1rem;">
                <i class="ph ph-file-x" style="font-size: 4rem;"></i>
                <p>Vista previa no disponible para este formato.</p>
                ${downloadUrl ? `<a href="${downloadUrl}" target="_blank" class="btn btn-primary"><i class="ph ph-download-simple"></i> Descargar Archivo</a>` : ''}
            </div>
        `;
    }
    document.getElementById('file-viewer').classList.add('active');
}

function closeViewer() {
    document.getElementById('file-viewer').classList.remove('active');
    document.getElementById('viewer-content').innerHTML = '';
}

/* File Manager Advanced Functions */

function setDriveView(type) {
    const container = document.querySelector('.drive-grid-container');
    if (!container) return;
    
    if (type === 'list') {
        container.classList.add('list-view');
        document.getElementById('btn-view-list').classList.add('active');
        document.getElementById('btn-view-grid').classList.remove('active');
    } else {
        container.classList.remove('list-view');
        document.getElementById('btn-view-grid').classList.add('active');
        document.getElementById('btn-view-list').classList.remove('active');
    }
}

function filterDriveItems() {
    const term = document.getElementById('drive-search-input').value.toLowerCase();
    const items = document.querySelectorAll('.drive-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        if (name.includes(term)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

let selectedDriveItems = [];

function toggleDriveSelection(event) {
    event.stopPropagation(); // prevent opening file/folder
    const checkbox = event.target;
    const val = checkbox.value;
    const parentItem = checkbox.closest('.drive-item');
    
    if (checkbox.checked) {
        if (!selectedDriveItems.includes(val)) selectedDriveItems.push(val);
        parentItem.classList.add('selected');
    } else {
        selectedDriveItems = selectedDriveItems.filter(id => id !== val);
        parentItem.classList.remove('selected');
    }
    updateSelectionBar();
}

function updateSelectionBar() {
    const bar = document.getElementById('drive-selection-bar');
    const count = document.getElementById('drive-selection-count');
    const renameBtn = document.getElementById('btn-drive-rename');
    const copyUrlBtn = document.getElementById('btn-drive-copyurl');
    const infoBtn = document.getElementById('btn-drive-info');
    const isSingle = selectedDriveItems.length === 1;
    
    if (selectedDriveItems.length > 0) {
        count.innerText = selectedDriveItems.length;
        bar.classList.add('active');
        if (renameBtn) renameBtn.style.display = isSingle ? 'flex' : 'none';
        if (copyUrlBtn) copyUrlBtn.style.display = isSingle ? 'flex' : 'none';
        if (infoBtn) infoBtn.style.display = isSingle ? 'flex' : 'none';
    } else {
        bar.classList.remove('active');
    }
}

function handleDriveItemClick(event, id, isFolder, safeName, webViewLink, webContentLink) {
    if (event.target.tagName.toLowerCase() === 'input') return; // handled by toggleDriveSelection
    
    if (selectedDriveItems.length > 0) {
        const itemElem = document.querySelector(`.drive-item[data-id="${id}"]`);
        if (itemElem) {
            const cb = itemElem.querySelector('.drive-item-checkbox');
            cb.checked = !cb.checked;
            toggleDriveSelection({ target: cb, stopPropagation: () => {} });
        }
        return;
    }
    
    if (isFolder) {
        navigateToFolder(id, safeName);
    } else {
        openViewer(id, webViewLink, safeName, webContentLink);
    }
}

// --- Toast Notification ---
let toastTimer = null;
function showToast(message, type = 'success') {
    const toast = document.getElementById('portal-toast');
    const icon = document.getElementById('toast-icon');
    const msg = document.getElementById('toast-msg');
    
    const icons = {
        success: '<i class="ph ph-check-circle"></i>',
        error: '<i class="ph ph-warning-circle"></i>',
        info: '<i class="ph ph-info"></i>',
        warning: '<i class="ph ph-warning"></i>'
    };
    
    icon.innerHTML = icons[type] || icons.success;
    icon.className = 'portal-toast-icon ' + type;
    msg.innerText = message;
    
    toast.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

// --- Copy Link ---
function copyLinkSelected() {
    if (selectedDriveItems.length !== 1) return;
    const id = selectedDriveItems[0];
    const item = document.querySelector(`.drive-item[data-id="${id}"]`);
    if (item) {
        const viewLink = item.getAttribute('data-view');
        if (viewLink) {
            navigator.clipboard.writeText(viewLink).then(() => {
                showToast('Vínculo copiado al portapapeles', 'success');
            }).catch(err => {
                console.error('Error copiando al portapapeles: ', err);
                showToast('No se pudo copiar el vínculo', 'error');
            });
        } else {
            showToast('No hay vínculo disponible', 'warning');
        }
    }
}

// --- Rename ---
let renameFileId = null;

function renameSelected() {
    if (selectedDriveItems.length !== 1) return;
    renameFileId = selectedDriveItems[0];
    const item = document.querySelector(`.drive-item[data-id="${renameFileId}"]`);
    if (!item) return;
    
    const currentName = item.querySelector('.item-name').innerText;
    const input = document.getElementById('rename-input');
    input.value = currentName;
    document.getElementById('modal-rename').classList.add('active');
    setTimeout(() => { input.focus(); input.select(); }, 200);
}

function closeRenameModal() {
    document.getElementById('modal-rename').classList.remove('active');
    renameFileId = null;
}

function confirmRename() {
    if (!renameFileId) return;
    const newName = document.getElementById('rename-input').value.trim();
    if (!newName) {
        showToast('El nombre no puede estar vacío', 'warning');
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'rename_drive_item');
    formData.append('file_id', renameFileId);
    formData.append('new_name', newName);
    
    fetch('ajax_portal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Nombre cambiado correctamente', 'success');
            closeRenameModal();
            clearDriveSelection();
            loadDrive(portalDrivePath[portalDrivePath.length - 1].id);
        } else {
            showToast('Error: ' + (data.error || 'Desconocido'), 'error');
        }
    }).catch(err => {
        showToast('Error de conexión', 'error');
    });
}

// --- Info ---
function infoSelected() {
    if (selectedDriveItems.length !== 1) return;
    const id = selectedDriveItems[0];
    const item = document.querySelector(`.drive-item[data-id="${id}"]`);
    if (item) {
        const name = item.querySelector('.item-name').innerText;
        const isFolder = item.getAttribute('data-type') === 'folder';
        document.getElementById('info-name').innerText = name;
        document.getElementById('info-type').innerText = isFolder ? '📁 Carpeta' : '📄 Archivo';
        document.getElementById('info-id').innerText = id;
        document.getElementById('modal-info').classList.add('active');
    }
}

function closeInfoModal() {
    document.getElementById('modal-info').classList.remove('active');
}

function clearDriveSelection() {
    selectedDriveItems = [];
    document.querySelectorAll('.drive-item-checkbox').forEach(cb => {
        cb.checked = false;
        cb.closest('.drive-item').classList.remove('selected');
    });
    updateSelectionBar();
}

function deleteSelectedFiles() {
    if (selectedDriveItems.length === 0) return;
    if (!confirm(`¿Estás seguro de que deseas eliminar ${selectedDriveItems.length} elemento(s)?`)) return;
    
    const formData = new URLSearchParams();
    formData.append('action', 'delete_drive_items');
    formData.append('item_ids', JSON.stringify(selectedDriveItems));
    
    fetch('ajax_portal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Elementos eliminados', 'success');
            clearDriveSelection();
            loadDrive(portalDrivePath[portalDrivePath.length - 1].id);
        } else {
            showToast('Error eliminando: ' + (data.error || 'Desconocido'), 'error');
        }
    });
}

function downloadSelectedFiles() {
    if (selectedDriveItems.length === 0) return;
    // We open a new tab for each download URL. 
    // Browsers might block multiple popups, so we might need a zip on backend, 
    // but for now we try to open them individually if not too many.
    if (selectedDriveItems.length > 5) {
        showToast('Máximo 5 archivos a la vez', 'warning');
        return;
    }
    selectedDriveItems.forEach(id => {
        const item = document.querySelector(`.drive-item[data-id="${id}"]`);
        if (item) {
            const dlUrl = item.getAttribute('data-download');
            if (dlUrl) {
                window.open(dlUrl, '_blank');
            }
        }
    });
    clearDriveSelection();
}

function moveSelectedFiles() {
    if (selectedDriveItems.length === 0) return;
    loadMoveFolderTree();
    document.getElementById('modal-move-folder').classList.add('active');
}

function closeMoveModal() {
    document.getElementById('modal-move-folder').classList.remove('active');
}

let moveTargetFolderId = null;

function loadMoveFolderTree() {
    const container = document.getElementById('move-folder-tree');
    container.innerHTML = '<div class="loader" style="margin: 0 auto;"></div>';
    
    fetch('ajax_portal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_drive_folders_tree'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let html = '<div style="display:flex; flex-direction:column; gap:0.5rem;">';
            data.folders.forEach(f => {
                html += `
                    <div style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem; border-radius:6px; cursor:pointer; transition:0.2s;" 
                         class="move-tree-item"
                         onclick="selectMoveTarget('${f.id}', this)">
                        <i class="ph ph-folder" style="color:var(--portal-primary);"></i>
                        <span style="color:var(--portal-text); font-size:0.9rem;">${f.name}</span>
                        <span style="margin-left:auto; font-size:0.75rem; color:var(--portal-muted);">${f.category}</span>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="color:var(--portal-muted);">Error cargando carpetas.</div>';
        }
    });
}

function selectMoveTarget(folderId, element) {
    document.querySelectorAll('.move-tree-item').forEach(el => el.style.background = 'transparent');
    element.style.background = 'rgba(59,130,246,0.1)';
    moveTargetFolderId = folderId;
    document.getElementById('btn-confirm-move').disabled = false;
}

document.getElementById('btn-confirm-move').addEventListener('click', function() {
    if (!moveTargetFolderId || selectedDriveItems.length === 0) return;
    
    const formData = new URLSearchParams();
    formData.append('action', 'move_drive_items');
    formData.append('item_ids', JSON.stringify(selectedDriveItems));
    formData.append('new_parent_id', moveTargetFolderId);
    
    const btn = this;
    btn.innerText = 'Moviendo...';
    btn.disabled = true;
    
    fetch('ajax_portal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerText = 'Mover Aquí';
        btn.disabled = false;
        closeMoveModal();
        if (data.success) {
            clearDriveSelection();
            loadDrive(portalDrivePath[portalDrivePath.length - 1].id); // reload current
        } else {
            alert('Error moviendo: ' + (data.error || 'Desconocido'));
        }
    });
});

function closeShareModal() {
    document.getElementById('modal-share').classList.remove('active');
}

function copyShareLink() {
    const input = document.getElementById('share-link-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Enlace copiado al portapapeles');
        closeShareModal();
    });
}
</script>
</body>
</html>
