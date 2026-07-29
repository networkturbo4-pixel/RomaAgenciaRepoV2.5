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
$is_popup = isset($_GET['popup']) && $_GET['popup'] == '1';
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
            <a href="index.php?module=workspace&action=index" class="nav-item <?php echo $current_module === 'workspace' ? 'active' : ''; ?>" data-title="Workspace">
                <i class="ph ph-briefcase"></i>
                <span>Workspace</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('task_manager', $perms)): ?>
            <a href="index.php?module=task_manager&action=index" class="nav-item <?php echo $current_module === 'task_manager' ? 'active' : ''; ?>" data-title="Gestor de Tareas">
                <i class="ph ph-kanban"></i>
                <span>Gestor de Tareas</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('romita', $perms)): ?>
            <a href="index.php?module=romita&action=index" class="nav-item <?php echo $current_module === 'romita' ? 'active' : ''; ?>" data-title="Romita IA">
                <i class="ph ph-sparkle"></i>
                <span style="background: linear-gradient(90deg, #4f46e5, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700;">Romita IA</span>
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
            <?php if (in_array('whatsapp', $perms)): ?>
            <a href="index.php?module=whatsapp&action=index" class="nav-item <?php echo $current_module === 'whatsapp' ? 'active' : ''; ?>" data-title="WhatsApp">
                <i class="ph ph-whatsapp-logo"></i>
                <span>WhatsApp</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('clients', $perms)): ?>
            <a href="index.php?module=clients&action=index" class="nav-item <?php echo $current_module === 'clients' ? 'active' : ''; ?>" data-title="Clientes">
                <i class="ph ph-users"></i>
                <span>Clientes</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('projects', $perms)): ?>
            <a href="index.php?module=projects&action=index" class="nav-item <?php echo $current_module === 'projects' ? 'active' : ''; ?>" data-title="Proyectos">
                <i class="ph ph-folders"></i>
                <span>Proyectos</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('reuniones', $perms)): ?>
            <a href="index.php?module=reuniones&action=index" class="nav-item <?php echo $current_module === 'reuniones' ? 'active' : ''; ?>" data-title="Reuniones">
                <i class="ph ph-video-camera"></i>
                <span>Reuniones</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('pizarras', $perms)): ?>
            <a href="index.php?module=pizarras&action=index" class="nav-item <?php echo $current_module === 'pizarras' ? 'active' : ''; ?>" data-title="Pizarras">
                <i class="ph ph-chalkboard"></i>
                <span>Pizarras</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('quotes', $perms) || in_array('services', $perms)): ?>
            <div class="nav-dropdown <?php echo in_array($current_module, ['quotes', 'services']) ? 'active' : ''; ?>">
                <button class="nav-item dropdown-toggle" data-title="Área Comercial">
                    <div style="display:flex; align-items:center; gap:var(--space-3);">
                        <i class="ph ph-storefront"></i>
                        <span>Área Comercial</span>
                    </div>
                    <i class="ph ph-caret-down dropdown-icon"></i>
                </button>
                <div class="nav-dropdown-menu">
                    <?php if (in_array('quotes', $perms)): ?>
                    <a href="index.php?module=quotes&action=index" class="dropdown-item <?php echo $current_module === 'quotes' ? 'active' : ''; ?>">Cotizaciones</a>
                    <?php endif; ?>
                    <?php if (in_array('services', $perms)): ?>
                    <a href="index.php?module=services&action=index" class="dropdown-item <?php echo $current_module === 'services' ? 'active' : ''; ?>">Servicios</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('work_orders', $perms)): ?>
            <a href="index.php?module=work_orders&action=index" class="nav-item <?php echo $current_module === 'work_orders' ? 'active' : ''; ?>" data-title="Órdenes de Servicio">
                <i class="ph ph-clipboard-text"></i>
                <span>Órdenes de Servicio</span>
            </a>
            <?php endif; ?>
            <?php if (in_array('calendar', $perms)): ?>
            <a href="index.php?module=calendar&action=index" class="nav-item <?php echo $current_module === 'calendar' ? 'active' : ''; ?>" data-title="Calendario">
                <i class="ph ph-calendar"></i>
                <span>Calendario</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array('community', $perms)): ?>
            <a href="index.php?module=community&action=index" class="nav-item <?php echo $current_module === 'community' ? 'active' : ''; ?>" data-title="Community">
                <i class="ph ph-calendar-check"></i>
                <span>Community</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('forms', $perms)): ?>
            <a href="index.php?module=forms&action=index" class="nav-item <?php echo $current_module === 'forms' ? 'active' : ''; ?>" data-title="Formularios">
                <i class="ph ph-note-pencil"></i>
                <span>Formularios</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('herramientas', $perms)): ?>
            <a href="index.php?module=herramientas&action=index" class="nav-item <?php echo $current_module === 'herramientas' ? 'active' : ''; ?>" data-title="Herramientas">
                <i class="ph ph-wrench"></i>
                <span>Herramientas</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('contracts', $perms)): ?>
            <a href="index.php?module=contracts&action=index" class="nav-item <?php echo $current_module === 'contracts' ? 'active' : ''; ?>" data-title="Contratos">
                <i class="ph ph-signature"></i>
                <span>Contratos</span>
            </a>
            <?php endif; ?>

            <?php if (in_array('admin', $perms)): ?>
            <div class="nav-dropdown <?php echo ($current_module === 'admin') ? 'active' : ''; ?>">
                <button class="nav-item dropdown-toggle" data-title="Administración">
                    <div style="display:flex; align-items:center; gap:var(--space-3);">
                        <i class="ph ph-briefcase"></i>
                        <span>Administración</span>
                    </div>
                    <i class="ph ph-caret-down dropdown-icon"></i>
                </button>
                <div class="nav-dropdown-menu">
                    <a href="index.php?module=admin&action=finances" class="dropdown-item <?php echo ($current_module === 'admin' && ($action ?? '') === 'finances') ? 'active' : ''; ?>">Finanzas</a>
                    <a href="index.php?module=admin&action=payment_notes" class="dropdown-item <?php echo ($current_module === 'admin' && ($action ?? '') === 'payment_notes') ? 'active' : ''; ?>">Notas de Pago</a>
                    <a href="index.php?module=admin&action=rrhh" class="dropdown-item <?php echo ($current_module === 'admin' && ($action ?? '') === 'rrhh') ? 'active' : ''; ?>">RRHH</a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('client_portal', $perms)): ?>
            <a href="index.php?module=client_portal&action=index" class="nav-item <?php echo $current_module === 'client_portal' ? 'active' : ''; ?>" data-title="Portal de Cliente">
                <i class="ph ph-app-window"></i>
                <span>Portal de Cliente</span>
            </a>
            <?php endif; ?>

        </nav>

        <!-- Sidebar Bottom: Profile -->
        <div class="sidebar-bottom" style="margin-top: auto; padding: 1rem 0; display: flex; flex-direction: column; gap: 0.25rem; position: relative;">
            
            <style>
                [data-theme="dark"] .theme-switch-knob { left: 16px !important; background: var(--primary-color) !important; }
            </style>

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

    <!-- Main Content -->
    <main class="main-content">
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
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button class="btn-icon" onclick="if(typeof DriveExplorer !== 'undefined') DriveExplorer.openGlobalModal()" title="Archivos" style="border: none; background: transparent; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; font-size: 1.1rem;">
                    <i class="ph ph-google-drive-logo" style="color: #3b82f6;"></i>
                </button>
                <button class="btn-icon push-subscribe-btn" onclick="if(window.subscribeToPush) subscribeToPush(); else alert('Notificaciones no soportadas');" title="Notificaciones" style="border: none; background: transparent; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; font-size: 1.1rem;">
                    <i class="ph ph-bell"></i>
                </button>
            </div>
        </div>

        <!-- Desktop sidebar collapse toggle removed -->

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <?php endif; ?>

        <!-- Global Toast Container -->
        <div id="global-toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;"></div>
        <script>
            window.showToast = function(msg, type = 'info') {
                const container = document.getElementById('global-toast-container');
                if (!container) return;
                const toast = document.createElement('div');
                
                let icon = 'ph-info';
                let bgColor = '#3b82f6'; // info blue
                if (type === 'success') { icon = 'ph-check-circle'; bgColor = '#10b981'; }
                if (type === 'error') { icon = 'ph-warning-circle'; bgColor = '#ef4444'; }
                if (type === 'warning') { icon = 'ph-warning'; bgColor = '#f59e0b'; }

                toast.style.cssText = `
                    background: var(--bg-surface, #fff);
                    color: var(--text-main, #1e293b);
                    border-left: 4px solid ${bgColor};
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                    padding: 12px 20px;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-size: 0.9rem;
                    font-weight: 500;
                    transform: translateX(120%);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    pointer-events: auto;
                    max-width: 350px;
                `;
                
                toast.innerHTML = `<i class="ph ${icon}" style="font-size: 1.4rem; color: ${bgColor};"></i> <div>${msg}</div>`;
                
                container.appendChild(toast);
                
                // Animate in
                requestAnimationFrame(() => {
                    toast.style.transform = 'translateX(0)';
                    toast.style.opacity = '1';
                });
                
                // Animate out and remove
                setTimeout(() => {
                    toast.style.transform = 'translateX(120%)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            };
        </script>

        <!-- Dynamic Content -->
        <div class="content-wrapper" <?php if($is_popup) echo 'style="padding:0; height:100vh; overflow:hidden;"'; ?>>
