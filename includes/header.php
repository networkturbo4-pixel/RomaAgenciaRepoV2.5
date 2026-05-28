<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure user is logged in to see the layout
if (!isset($_SESSION['user_id']) && empty($is_public)) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$current_module = $_GET['module'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($global_settings['site_name'] ?? 'ROMA SaaS'); ?></title>
    <!-- Anti-FOUC Script for Dark Mode -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            if (localStorage.getItem('sidebar_collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        })();
    </script>
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Load selected fonts -->
    <?php
        $fonts = array_unique([
            $global_settings['font_titles'] ?? 'Inter',
            $global_settings['font_text'] ?? 'Inter',
            $global_settings['font_links'] ?? 'Inter',
            $global_settings['font_buttons'] ?? 'Inter'
        ]);
        foreach($fonts as $font) {
            $font_url = str_replace(' ', '+', $font);
            echo "<link href='https://fonts.googleapis.com/css2?family={$font_url}:wght@300;400;500;600;700&display=swap' rel='stylesheet'>\n";
        }
    ?>

    <!-- Quill.js CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <!-- Web App Manifest -->
    <link rel="manifest" href="manifest.php">

    <link rel="stylesheet" href="assets/css/variables.css?v=<?php echo filemtime('assets/css/variables.css'); ?>">
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo filemtime('assets/css/global.css'); ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo filemtime('assets/css/components.css'); ?>">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>;
            --primary-contrast: var(--primary-color);
            --primary-bg: color-mix(in srgb, var(--primary-color), transparent 90%);
            --secondary-color: <?php echo htmlspecialchars($global_settings['secondary_color'] ?? '#10b981'); ?>;
            --warning-color: <?php echo htmlspecialchars($global_settings['accent_color'] ?? '#f59e0b'); ?>;
            
            /* Text & Element Colors (Light) */
            --color-title: <?php echo htmlspecialchars($global_settings['color_title_light'] ?? '#0f172a'); ?>;
            --color-text: <?php echo htmlspecialchars($global_settings['color_text_light'] ?? '#64748b'); ?>;
            --color-link: <?php echo htmlspecialchars($global_settings['color_link_light'] ?? '#4f46e5'); ?>;
            --color-link-hover: <?php echo htmlspecialchars($global_settings['color_link_hover_light'] ?? '#4338ca'); ?>;
            --color-btn-bg: <?php echo htmlspecialchars($global_settings['color_btn_bg_light'] ?? '#4f46e5'); ?>;
            --color-btn-hover: <?php echo htmlspecialchars($global_settings['color_btn_hover_light'] ?? '#4338ca'); ?>;
            --color-btn-text: <?php echo htmlspecialchars($global_settings['color_btn_light'] ?? '#ffffff'); ?>;

            --font-family: '<?php echo htmlspecialchars($global_settings['font_text'] ?? 'Inter'); ?>', sans-serif;
        }

        [data-theme="dark"] {
            --primary-contrast: color-mix(in srgb, var(--primary-color), white 40%);
            --primary-bg: color-mix(in srgb, var(--primary-color), transparent 85%);
            /* Text & Element Colors (Dark) */
            --color-title: <?php echo htmlspecialchars($global_settings['color_title_dark'] ?? '#ffffff'); ?>;
            --color-text: <?php echo htmlspecialchars($global_settings['color_text_dark'] ?? '#9ca3af'); ?>;
            --color-link: <?php echo htmlspecialchars($global_settings['color_link_dark'] ?? '#60a5fa'); ?>;
            --color-link-hover: <?php echo htmlspecialchars($global_settings['color_link_hover_dark'] ?? '#93c5fd'); ?>;
            --color-btn-bg: <?php echo htmlspecialchars($global_settings['color_btn_bg_dark'] ?? '#4f46e5'); ?>;
            --color-btn-hover: <?php echo htmlspecialchars($global_settings['color_btn_hover_dark'] ?? '#4338ca'); ?>;
            --color-btn-text: <?php echo htmlspecialchars($global_settings['color_btn_dark'] ?? '#ffffff'); ?>;
        }

        h1, h2, h3, h4, h5, h6, .sidebar-header {
            font-family: '<?php echo htmlspecialchars($global_settings['font_titles'] ?? 'Inter'); ?>', sans-serif !important;
        }
        a, .nav-item {
            font-family: '<?php echo htmlspecialchars($global_settings['font_links'] ?? 'Inter'); ?>', sans-serif !important;
        }
        .btn {
            font-family: '<?php echo htmlspecialchars($global_settings['font_buttons'] ?? 'Inter'); ?>', sans-serif !important;
        }

        /* Quill Editor Dark Mode Contrast Fixes */
        [data-theme="dark"] .ql-snow .ql-stroke { stroke: #cbd5e1; }
        [data-theme="dark"] .ql-snow .ql-fill { fill: #cbd5e1; }
        [data-theme="dark"] .ql-snow .ql-picker { color: #cbd5e1; }
        [data-theme="dark"] .ql-snow.ql-toolbar button:hover .ql-stroke,
        [data-theme="dark"] .ql-snow .ql-toolbar button:hover .ql-stroke { stroke: var(--primary-color); }
        [data-theme="dark"] .ql-snow.ql-toolbar button:hover .ql-fill,
        [data-theme="dark"] .ql-snow .ql-toolbar button:hover .ql-fill { fill: var(--primary-color); }
        [data-theme="dark"] .ql-toolbar.ql-snow { border-color: var(--border-color); }
        [data-theme="dark"] .ql-container.ql-snow { border-color: var(--border-color); color: var(--text-color); }
        [data-theme="dark"] .ql-snow .ql-picker-options { background-color: var(--card-bg); border-color: var(--border-color); }
        [data-theme="dark"] .ql-snow .ql-editor.ql-blank::before { color: var(--text-muted); }
    </style>
    <!-- Fancybox CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css"/>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header d-none d-md-flex" style="justify-content: center;">
            <?php if(!empty($global_settings['logo_light']) && !empty($global_settings['logo_dark'])): ?>
                <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" class="brand-logo-light brand-logo-full" alt="Logo" style="max-height: 24px; object-fit: contain;">
                <img src="<?php echo htmlspecialchars($global_settings['logo_dark']); ?>" class="brand-logo-dark brand-logo-full" alt="Logo" style="max-height: 24px; object-fit: contain;">
            <?php elseif(!empty($global_settings['logo_light'])): ?>
                <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" class="brand-logo-full" alt="Logo" style="max-height: 24px; object-fit: contain;">
            <?php else: ?>
                <span class="brand-text-full" style="font-weight: 800; font-size: 1.25rem; color: var(--primary-color); letter-spacing: -0.5px;">
                    <?php echo htmlspecialchars($global_settings['site_name'] ?? 'ROMA SaaS'); ?>
                </span>
            <?php endif; ?>

            <?php if(!empty($global_settings['logo_collapsed'])): ?>
                <img src="<?php echo htmlspecialchars($global_settings['logo_collapsed']); ?>" class="brand-logo-collapsed" alt="Logo" style="max-height: 24px; object-fit: contain; display: none;">
            <?php else: ?>
                <span class="brand-text-collapsed" style="font-weight: 800; font-size: 1.5rem; color: var(--primary-color); display: none;">
                    <?php echo substr(htmlspecialchars($global_settings['site_name'] ?? 'R'), 0, 1); ?>
                </span>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <?php $perms = $_SESSION['user_permissions'] ?? []; ?>
            <?php if (in_array('dashboard', $perms)): ?>
            <a href="index.php?module=dashboard&action=index" class="nav-item <?php echo $current_module === 'dashboard' ? 'active' : ''; ?>">
                <i class="ph ph-squares-four"></i>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('clients', $perms)): ?>
            <a href="index.php?module=clients&action=index" class="nav-item <?php echo $current_module === 'clients' ? 'active' : ''; ?>">
                <i class="ph ph-users"></i>
                <span>Clientes</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('quotes', $perms)): ?>
            <a href="index.php?module=quotes&action=index" class="nav-item <?php echo $current_module === 'quotes' ? 'active' : ''; ?>">
                <i class="ph ph-file-text"></i>
                <span>Cotizaciones</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('services', $perms)): ?>
            <a href="index.php?module=services&action=index" class="nav-item <?php echo $current_module === 'services' ? 'active' : ''; ?>">
                <i class="ph ph-package"></i>
                <span>Servicios</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('work_orders', $perms)): ?>
            <a href="index.php?module=work_orders&action=index" class="nav-item <?php echo $current_module === 'work_orders' ? 'active' : ''; ?>">
                <i class="ph ph-clipboard-text"></i>
                <span>Órdenes de Servicio</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('calendar', $perms)): ?>
            <a href="index.php?module=calendar&action=index" class="nav-item <?php echo $current_module === 'calendar' ? 'active' : ''; ?>">
                <i class="ph ph-calendar"></i>
                <span>Calendario</span>
            </a>
            <a href="index.php?module=community&action=index" class="nav-item <?php echo $current_module === 'community' ? 'active' : ''; ?>">
                <i class="ph ph-calendar-check"></i>
                <span>Community</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('tasks', $perms)): ?>
            <a href="index.php?module=tasks&action=index" class="nav-item <?php echo $current_module === 'tasks' ? 'active' : ''; ?>">
                <i class="ph ph-kanban"></i>
                <span>Centro de Tareas</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('design_tasks', $perms)): ?>
            <a href="index.php?module=design_tasks&action=index" class="nav-item <?php echo $current_module === 'design_tasks' ? 'active' : ''; ?>">
                <i class="ph ph-paint-brush-broad"></i>
                <span>Diseño Gráfico</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('forms', $perms)): ?>
            <a href="index.php?module=forms&action=index" class="nav-item <?php echo $current_module === 'forms' ? 'active' : ''; ?>">
                <i class="ph ph-note-pencil"></i>
                <span>Formularios</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('chat', $perms)): ?>
            <a href="index.php?module=chat&action=index" class="nav-item <?php echo $current_module === 'chat' ? 'active' : ''; ?>">
                <i class="ph ph-chat-circle-dots"></i>
                <span>Chat</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('admin', $perms)): ?>
            <a href="index.php?module=admin&action=payment_notes" class="nav-item <?php echo ($current_module === 'admin' && ($action ?? '') === 'payment_notes') ? 'active' : ''; ?>">
                <i class="ph ph-receipt"></i>
                <span>Nota de Pago</span>
            </a>
            <a href="index.php?module=admin&action=rrhh" class="nav-item <?php echo ($current_module === 'admin' && ($action ?? '') === 'rrhh') ? 'active' : ''; ?>">
                <i class="ph ph-users-three"></i>
                <span>RRHH</span>
            </a>
            <a href="index.php?module=client_portal&action=index" class="nav-item <?php echo $current_module === 'client_portal' ? 'active' : ''; ?>">
                <i class="ph ph-app-window"></i>
                <span>Portal de Cliente</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('config', $perms)): ?>
            <a href="index.php?module=config&action=index" class="nav-item <?php echo $current_module === 'config' ? 'active' : ''; ?>">
                <i class="ph ph-gear"></i>
                <span>Configuración</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Mobile Sidebar Footer Logo -->
        <div class="sidebar-footer d-md-none" style="padding: 2rem 1rem; text-align: center; margin-top: auto; border-top: 1px solid var(--border-color);">
            <?php if(!empty($global_settings['logo_light']) && !empty($global_settings['logo_dark'])): ?>
                <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" class="brand-logo-light" alt="Logo" style="height: 28px; object-fit: contain;">
                <img src="<?php echo htmlspecialchars($global_settings['logo_dark']); ?>" class="brand-logo-dark" alt="Logo" style="height: 28px; object-fit: contain;">
            <?php elseif(!empty($global_settings['logo_light'])): ?>
                <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo" style="height: 28px; object-fit: contain;">
            <?php else: ?>
                <span style="font-weight: 800; font-size: 1.25rem; color: var(--primary-color); letter-spacing: -0.5px;">
                    <?php echo htmlspecialchars($global_settings['site_name'] ?? 'ROMA SaaS'); ?>
                </span>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <!-- Mobile Menu Toggle -->
            <div class="d-md-none">
                <button class="btn-icon" id="mobile-menu-toggle" style="border: none; background: transparent; padding: 0.5rem; color: var(--text-main); cursor: pointer;">
                    <i class="ph ph-list" style="font-size: 1.75rem;"></i>
                </button>
            </div>
            
            <!-- Mobile Centered Logo (Hidden per user request, moved to sidebar footer) -->
            <div class="topbar-mobile-logo d-none" style="position: absolute; left: 50%; transform: translateX(-50%); display: none; align-items: center; justify-content: center;">
            </div>

            <div class="d-none d-md-block">
                <button class="btn-icon" id="desktop-menu-toggle" style="border: none; background: transparent; padding: 0.5rem; color: var(--text-main); cursor: pointer;">
                    <i class="ph ph-list" style="font-size: 1.75rem;"></i>
                </button>
            </div> <!-- Spacer for desktop -->

            <div class="topbar-actions">
                <!-- Global Drive Button -->
                <button class="btn-icon" onclick="if(typeof DriveExplorer !== 'undefined') DriveExplorer.openGlobalModal()" title="Explorador de Archivos" style="border: none; background: transparent; cursor: pointer; color: var(--text-main); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="ph ph-google-drive-logo" style="color: #3b82f6;"></i>
                </button>

                <!-- Push Subscribe Button -->
                <button class="btn-icon push-subscribe-btn" onclick="if(window.subscribeToPush) subscribeToPush(); else alert('Notificaciones no soportadas');" title="Activar Notificaciones" style="border: none; background: transparent; cursor: pointer; color: var(--text-main); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="ph ph-bell"></i>
                </button>

                <!-- Theme Toggle Button -->
                <button class="btn-icon theme-toggle-btn" title="Cambiar Tema" style="border: none; background: transparent; cursor: pointer; color: var(--text-main); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="ph ph-moon dark-icon"></i>
                    <i class="ph ph-sun light-icon" style="display: none;"></i>
                </button>

                <div class="user-info d-flex align-items-center" style="gap: var(--space-2);">
                    <div class="user-details d-none d-md-block" style="text-align: right;">
                        <span style="display: block; font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                        <span style="color: var(--text-muted); font-size: 0.75rem;"><?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?></span>
                    </div>
                    <div id="topbar-avatar" style="width: 36px; height: 36px; background: var(--primary-color); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; overflow: hidden;" onclick="openProfileModal()">
                        <?php
                        $stmtAv = $db->prepare('SELECT avatar FROM users WHERE id = ?');
                        $stmtAv->execute([$_SESSION['user_id']]);
                        $userAv = $stmtAv->fetchColumn();
                        if ($userAv): ?>
                            <img src="<?php echo htmlspecialchars($userAv); ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?php echo substr($_SESSION['user_name'] ?? 'U', 0, 1); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="index.php?module=auth&action=logout" class="btn btn-outline" title="Cerrar Sesión" style="display:flex; align-items:center; justify-content:center; padding: 0.5rem;">
                    <i class="ph ph-sign-out" style="font-size: 1.25rem;"></i>
                </a>
            </div>
        </header>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Dynamic Content -->
        <div class="content-wrapper">
