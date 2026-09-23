<?php
// includes/header.php
require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure user is logged in to see the layout
if (!isset($_SESSION['user_id']) && empty($is_public)) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$current_module = $_GET['module'] ?? 'dashboard';
$current_action = $_GET['action'] ?? ($action ?? 'index');
$is_popup = !empty($is_popup) || (isset($_GET['popup']) && $_GET['popup'] == '1');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <?php
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $protocol = $is_https ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if (!empty($global_settings['site_url'])) {
            $sys_base_url = rtrim($global_settings['site_url'], '/');
            if ($is_https && strpos($sys_base_url, 'http://') === 0) {
                $sys_base_url = 'https://' . substr($sys_base_url, 7);
            }
        } else {
            $sys_base_url = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '');
        }
    ?>
    <base href="<?php echo htmlspecialchars(rtrim($sys_base_url, '/') . '/'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $site_name_display = $global_settings['site_name'] ?? 'ROMA SaaS';
        $final_page_title = !empty($page_title) ? $page_title : $site_name_display;
        $meta_description = !empty($og_tags['description']) ? $og_tags['description'] : ($global_settings['site_description'] ?? '');
    ?>
    <title><?php echo htmlspecialchars($final_page_title); ?></title>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>

    <!-- Open Graph / WhatsApp / Facebook / Telegram Meta Tags -->
    <?php if (!empty($og_tags)): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($og_tags['title'] ?? $final_page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_tags['description'] ?? ''); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($og_tags['type'] ?? 'website'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($og_tags['url'] ?? $sys_base_url); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_name_display); ?>">
    <?php if (!empty($og_tags['image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($og_tags['image']); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($og_tags['image']); ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($og_tags['title'] ?? $final_page_title); ?>">
    <?php endif; ?>

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="<?php echo !empty($og_tags['image']) ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_tags['title'] ?? $final_page_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_tags['description'] ?? ''); ?>">
    <?php if (!empty($og_tags['image'])): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_tags['image']); ?>">
    <?php endif; ?>
    <?php else: ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($site_name_display); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_name_display); ?>">
    <?php if (!empty($global_settings['logo_light'])): ?>
        <?php $default_og_logo = (strpos($global_settings['logo_light'], 'http') === 0) ? $global_settings['logo_light'] : rtrim($sys_base_url, '/') . '/' . ltrim($global_settings['logo_light'], '/'); ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($default_og_logo); ?>">
        <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($default_og_logo); ?>">
    <?php endif; ?>
    <?php endif; ?>
    <!-- Anti-FOUC Script for Dark Mode -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            // Sidebar is always collapsed on desktop now
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
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Tagify CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />

    <!-- Web App Manifest -->
    <link rel="manifest" href="manifest.php">

    <link rel="stylesheet" href="assets/css/variables.css?v=<?php echo file_exists('assets/css/variables.css') ? filemtime('assets/css/variables.css') : '1'; ?>">
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo file_exists('assets/css/global.css') ? filemtime('assets/css/global.css') : '1'; ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo file_exists('assets/css/components.css') ? filemtime('assets/css/components.css') : '1'; ?>">
    <link rel="stylesheet" href="assets/css/profile-modal.css?v=<?php echo file_exists('assets/css/profile-modal.css') ? filemtime('assets/css/profile-modal.css') : '1'; ?>">
    <link rel="stylesheet" href="assets/css/notifications.css?v=<?php echo file_exists('assets/css/notifications.css') ? filemtime('assets/css/notifications.css') : '1'; ?>">
    <script>
        window.CURRENT_USER_ID = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;

        // Auto-inyectar CSRF Token en todas las llamadas jQuery AJAX
        if (typeof jQuery !== 'undefined') {
            jQuery.ajaxPrefilter(function(options, originalOptions, xhr) {
                if (!options.crossDomain && !['GET', 'HEAD', 'OPTIONS'].includes(options.type.toUpperCase())) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', window.CSRF_TOKEN);
                }
            });
        }

        // Auto-inyectar CSRF Token en peticiones fetch nativas del navegador
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(url, config) {
                config = config || {};
                const method = (config.method || 'GET').toUpperCase();
                if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                    config.headers = config.headers || {};
                    if (config.headers instanceof Headers) {
                        if (!config.headers.has('X-CSRF-TOKEN')) {
                            config.headers.append('X-CSRF-TOKEN', window.CSRF_TOKEN);
                        }
                    } else if (Array.isArray(config.headers)) {
                        config.headers.push(['X-CSRF-TOKEN', window.CSRF_TOKEN]);
                    } else {
                        if (!config.headers['X-CSRF-TOKEN']) {
                            config.headers['X-CSRF-TOKEN'] = window.CSRF_TOKEN;
                        }
                    }
                }
                return originalFetch(url, config);
            };
        })();
    </script>
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
<body class="<?php echo $is_popup ? 'is-popup' : ''; ?>">

<div class="app-container">
    <?php if (!$is_popup): ?>
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
            <a href="index.php?module=dashboard&action=index" class="nav-item <?php echo $current_module === 'dashboard' ? 'active' : ''; ?>" data-title="Dashboard">
                <i class="ph ph-squares-four"></i>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('workspace', $perms) || in_array('dashboard', $perms)): ?>
            <a href="index.php?module=workspace&action=index" class="nav-item <?php echo in_array($current_module, ['workspace', 'desarrollo_marca', 'audiovisual', 'project_board', 'month_board', 'knowledge_base', 'forms', 'calendar']) ? 'active' : ''; ?>" data-title="Workspace">
                <i class="ph ph-briefcase"></i>
                <span>Workspace</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('mensajes', $perms)): ?>
            <a href="index.php?module=mensajes&action=index" class="nav-item <?php echo $current_module === 'mensajes' ? 'active' : ''; ?>" style="display:flex; justify-content:space-between; align-items:center;" data-title="Mensajes">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="ph ph-chat-circle-dots"></i> <span>Mensajes</span>
                </div>
                <span id="globalMsgBadge" style="display:none; align-items:center; justify-content:center; background:var(--msg-primary, #e83f6f); color:white; font-size:10px; font-weight:bold; width:18px; height:18px; min-width:18px; min-height:18px; flex:0 0 18px; border-radius:50%;">0</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('clients', $perms)): ?>
            <a href="index.php?module=clients&action=index" class="nav-item <?php echo $current_module === 'clients' ? 'active' : ''; ?>" data-title="Clientes">
                <i class="ph ph-users"></i>
                <span>Clientes</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('suppliers', $perms)): ?>
            <a href="index.php?module=suppliers&action=index" class="nav-item <?php echo $current_module === 'suppliers' ? 'active' : ''; ?>" data-title="Proveedores">
                <i class="ph ph-buildings"></i>
                <span>Proveedores</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('quotes', $perms)): ?>
            <a href="index.php?module=quotes&action=index" class="nav-item <?php echo $current_module === 'quotes' ? 'active' : ''; ?>" data-title="Cotizaciones">
                <i class="ph ph-file-text"></i>
                <span>Cotizaciones</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('services', $perms)): ?>
            <a href="index.php?module=services&action=index" class="nav-item <?php echo $current_module === 'services' ? 'active' : ''; ?>" data-title="Servicios">
                <i class="ph ph-package"></i>
                <span>Servicios</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('work_orders', $perms)): ?>
            <a href="index.php?module=work_orders&action=index" class="nav-item <?php echo $current_module === 'work_orders' ? 'active' : ''; ?>" data-title="Órdenes de Servicio">
                <i class="ph ph-clipboard-text"></i>
                <span>Órdenes de Servicio</span>
            </a>
            <?php endif; ?>


            <?php if (in_array('contracts', $perms)): ?>
            <a href="index.php?module=contracts&action=index" class="nav-item <?php echo $current_module === 'contracts' ? 'active' : ''; ?>" data-title="Contratos">
                <i class="ph ph-signature"></i>
                <span>Contratos</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('admin', $perms)): ?>
            <a href="index.php?module=admin&action=finances" class="nav-item <?php echo ($current_module === 'admin' && $current_action === 'finances') ? 'active' : ''; ?>" data-title="Finanzas">
                <i class="ph ph-chart-line-up"></i>
                <span>Finanzas</span>
            </a>

            <a href="index.php?module=admin&action=payment_notes" class="nav-item <?php echo ($current_module === 'admin' && in_array($current_action, ['payment_notes', 'payment_note_webview'])) ? 'active' : ''; ?>" data-title="Notas de Pago">
                <i class="ph ph-receipt"></i>
                <span>Notas de Pago</span>
            </a>

            <a href="index.php?module=admin&action=rrhh" class="nav-item <?php echo ($current_module === 'admin' && $current_action === 'rrhh') ? 'active' : ''; ?>" data-title="Recursos Humanos">
                <i class="ph ph-users-three"></i>
                <span>Recursos Humanos</span>
            </a>
            <?php endif; ?>

        </nav>

        <!-- Sidebar Bottom: Profile -->
        <div class="sidebar-bottom" style="margin-top: auto; padding: 1rem 0; display: flex; flex-direction: column; gap: 0.25rem; position: relative;">
            
            <style>
                [data-theme="dark"] .theme-switch-knob { left: 16px !important; background: var(--primary-color) !important; }
            </style>

            <!-- Notification Bell (Aligned with collapsed sidebar) -->
            <button class="nav-item notif-bell-btn" id="desktopNotifBtn" data-title="Notificaciones" type="button" style="border: none; background: transparent; cursor: pointer;">
                <i class="ph ph-bell"></i>
                <span class="notif-badge" id="notifBadgeDesktop" style="display: none;">0</span>
            </button>

            <!-- User Info Card -->
            <div class="sidebar-profile-card" id="profileCardToggle" style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-surface); padding: 0.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); cursor: pointer; margin: 0.5rem var(--space-3) 0 var(--space-3); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden; width: 100%;">
                    <div id="sidebar-avatar" style="width: 32px; height: 32px; flex-shrink: 0; background: var(--primary-color); color: white; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; margin: 0 auto;">
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
                    <div class="sidebar-profile-info" style="display: flex; flex-direction: column; overflow: hidden; white-space: nowrap;">
                        <span style="font-weight: 600; font-size: 0.85rem; text-overflow: ellipsis; overflow: hidden; color: var(--text-main);"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                        <span style="color: var(--text-muted); font-size: 0.7rem; text-overflow: ellipsis; overflow: hidden;"><?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="sidebar-profile-options" style="color: var(--text-muted); padding: 0 0.25rem;">
                    <i class="ph ph-dots-three" style="font-size: 1.25rem;"></i>
                </div>
            </div>
            
            <!-- Modern Profile Popover Menu -->
            <div class="profile-popover" id="profilePopover">
                <div class="popover-header">
                    <div class="popover-avatar">
                        <?php if ($userAv): ?>
                            <img src="<?php echo htmlspecialchars($userAv); ?>">
                        <?php else: ?>
                            <?php echo substr($_SESSION['user_name'] ?? 'U', 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div class="popover-user-info">
                        <span class="popover-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                        <span class="popover-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? $_SESSION['user_role'] ?? ''); ?></span>
                    </div>
                </div>
                
                <div class="popover-divider"></div>
                
                <button class="popover-item" onclick="if(typeof openProfileModal === 'function') openProfileModal(); document.getElementById('profilePopover').classList.remove('active');">
                    <i class="ph ph-user-circle"></i> Editar perfil
                </button>
                
                <button class="popover-item theme-toggle-btn" style="justify-content: space-between;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <i class="ph ph-moon dark-icon"></i>
                        <i class="ph ph-sun light-icon" style="display: none;"></i>
                        <span>Modo oscuro</span>
                    </div>
                    <div class="theme-switch-track" style="width: 32px; height: 18px; background: var(--border-color); border-radius: 18px; position: relative;">
                        <div class="theme-switch-knob" style="width: 14px; height: 14px; background: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.2);"></div>
                    </div>
                </button>

                <?php if (in_array('conexiones', $perms)): ?>
                <a href="index.php?module=conexiones&action=index" class="popover-item">
                    <i class="ph ph-plugs-connected"></i> Conexiones
                </a>
                <?php endif; ?>

                <?php if (in_array('config', $perms)): ?>
                <a href="index.php?module=config&action=index" class="popover-item">
                    <i class="ph ph-gear"></i> Configuración
                </a>
                <?php endif; ?>

                <div class="popover-divider"></div>

                <a href="index.php?module=auth&action=logout" class="popover-item text-danger">
                    <i class="ph ph-sign-out"></i> Cerrar sesión
                </a>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const profileCard = document.getElementById('profileCardToggle');
                    const profilePopover = document.getElementById('profilePopover');
                    
                    if (profileCard && profilePopover) {
                        profileCard.addEventListener('click', (e) => {
                            e.stopPropagation();
                            profilePopover.classList.toggle('active');
                        });
                        
                        document.addEventListener('click', (e) => {
                            if (!profilePopover.contains(e.target) && !profileCard.contains(e.target)) {
                                profilePopover.classList.remove('active');
                            }
                        });
                    }
                });
            </script>
        </div>
    </aside>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (!$is_popup): ?>
        <!-- Fixed Mobile Header -->
        <div class="mobile-topbar d-md-none">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button class="btn-icon" id="mobile-menu-toggle" style="border: none; background: transparent; padding: 0.35rem; color: var(--text-main); cursor: pointer;">
                    <i class="ph ph-list-dashes" style="font-size: 1.4rem;"></i>
                </button>
                <?php if(!empty($global_settings['logo_light']) && !empty($global_settings['logo_dark'])): ?>
                    <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" class="brand-logo-light" alt="Logo" style="max-height: 22px; object-fit: contain;">
                    <img src="<?php echo htmlspecialchars($global_settings['logo_dark']); ?>" class="brand-logo-dark" alt="Logo" style="max-height: 22px; object-fit: contain;">
                <?php elseif(!empty($global_settings['logo_light'])): ?>
                    <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo" style="max-height: 22px; object-fit: contain;">
                <?php else: ?>
                    <span style="font-weight: 800; font-size: 1.1rem; color: var(--primary-color); letter-spacing: -0.5px;">
                        <?php echo htmlspecialchars($global_settings['site_name'] ?? 'ROMA SaaS'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <button class="btn-icon" onclick="window.dispatchEvent(new CustomEvent('toggle-command-palette'));" title="Buscar (Ctrl+K)" style="border: none; background: transparent; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; font-size: 1.25rem;">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
                <button class="btn-icon" onclick="if(typeof DriveExplorer !== 'undefined') DriveExplorer.openGlobalModal()" title="Archivos" style="border: none; background: transparent; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; font-size: 1.15rem;">
                    <i class="ph ph-google-drive-logo" style="color: #3b82f6;"></i>
                </button>
                <button class="notif-bell-btn" id="mobileNotifBtn" title="Notificaciones" type="button">
                    <i class="ph ph-bell"></i>
                    <span class="notif-badge" id="notifBadgeMobile" style="display: none;">0</span>
                </button>
            </div>
        </div>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Notification Popover Panel -->
        <div class="notif-popover" id="notifPopover">
            <div class="notif-header">
                <div class="notif-header-title">
                    <i class="ph ph-bell-simple" style="color: var(--primary-color); font-size: 1.15rem;"></i>
                    <span>Notificaciones</span>
                    <span class="notif-header-count" id="notifCountText">0 nuevas</span>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <button class="notif-mark-all-btn" id="notifMarkAllBtn" type="button" title="Marcar todas como leídas">
                        Marcar leídas
                    </button>
                </div>
            </div>

            <!-- Web Push Opt-in Banner (Visible only if not yet allowed) -->
            <div class="notif-push-banner" id="notifPushBanner" style="display: none;">
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <i class="ph ph-broadcast" style="color: var(--primary-color); font-size: 1.1rem;"></i>
                    <span>¿Recibir alertas en este dispositivo?</span>
                </div>
                <button type="button" onclick="requestPushFromBanner()">Activar</button>
            </div>

            <!-- Scrollable Notification List -->
            <ul class="notif-list" id="notifList">
                <!-- Rendered dynamically by notifications.js -->
            </ul>
        </div>
        <?php endif; ?>

        <!-- Global Toast Container & Modern Notification Engine -->
        <style>
            #global-toast-container {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 999999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
                max-width: 400px;
            }
            .app-toast-item {
                background: var(--bg-surface, #ffffff);
                color: var(--text-main, #0f172a);
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 16px;
                box-shadow: 0 12px 35px -5px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.04);
                padding: 12px 16px;
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 0.88rem;
                font-weight: 500;
                transform: translateY(20px) scale(0.96);
                opacity: 0;
                transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
                pointer-events: auto;
                cursor: pointer;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }
            .app-toast-item.toast-visible {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            .app-toast-item.toast-hiding {
                transform: translateY(-15px) scale(0.95);
                opacity: 0;
            }
            .app-toast-icon-wrap {
                width: 34px;
                height: 34px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                font-size: 1.25rem;
            }
            .app-toast-close {
                margin-left: auto;
                color: var(--text-muted, #94a3b8);
                font-size: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 4px;
                border-radius: 6px;
                transition: background 0.15s ease;
            }
            .app-toast-close:hover {
                background: var(--primary-bg, rgba(0,0,0,0.05));
                color: var(--text-main, #0f172a);
            }
            @media (max-width: 768px) {
                #global-toast-container {
                    bottom: auto;
                    top: 16px;
                    left: 50%;
                    right: auto;
                    transform: translateX(-50%);
                    width: calc(100% - 32px);
                    max-width: 420px;
                }
                .app-toast-item {
                    transform: translateY(-20px) scale(0.96);
                }
                .app-toast-item.toast-visible {
                    transform: translateY(0) scale(1);
                }
            }
        </style>
        <div id="global-toast-container"></div>
        <script>
            window.showToast = function(msg, type = 'info', duration = 3500) {
                const container = document.getElementById('global-toast-container');
                if (!container) return;
                const toast = document.createElement('div');
                toast.className = 'app-toast-item';
                
                let icon = 'ph-info';
                let accent = 'var(--primary-color, #4f46e5)';
                let bgWrap = 'var(--primary-bg, rgba(79, 70, 229, 0.12))';
                
                if (type === 'success') { 
                    icon = 'ph-check'; 
                    accent = '#10b981'; 
                    bgWrap = 'rgba(16, 185, 129, 0.14)'; 
                } else if (type === 'error') { 
                    icon = 'ph-warning-circle'; 
                    accent = '#ef4444'; 
                    bgWrap = 'rgba(239, 68, 68, 0.14)'; 
                } else if (type === 'warning') { 
                    icon = 'ph-warning'; 
                    accent = '#f59e0b'; 
                    bgWrap = 'rgba(245, 158, 11, 0.14)'; 
                }

                toast.innerHTML = `
                    <div class="app-toast-icon-wrap" style="background: ${bgWrap}; color: ${accent};">
                        <i class="ph-bold ${icon}"></i>
                    </div>
                    <div style="flex: 1; line-height: 1.35;">${msg}</div>
                    <span class="app-toast-close" title="Cerrar"><i class="ph ph-x"></i></span>
                `;
                
                container.appendChild(toast);
                
                const closeToast = () => {
                    toast.classList.remove('toast-visible');
                    toast.classList.add('toast-hiding');
                    setTimeout(() => toast.remove(), 280);
                };

                const closeBtn = toast.querySelector('.app-toast-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        closeToast();
                    });
                }
                toast.addEventListener('click', closeToast);

                requestAnimationFrame(() => {
                    toast.classList.add('toast-visible');
                });
                
                if (duration > 0) {
                    setTimeout(closeToast, duration);
                }
            };
        </script>

        <!-- Dynamic Content -->
        <div class="content-wrapper" <?php if($is_popup && empty($allow_scroll)) echo 'style="padding:0; height:100vh; overflow:hidden;"'; elseif($is_popup) echo 'style="padding:0; min-height:100vh;"'; ?>>
