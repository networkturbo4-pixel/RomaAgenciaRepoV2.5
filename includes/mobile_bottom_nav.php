<?php
// includes/mobile_bottom_nav.php
// Bottom Navigation Bar Dinámico con Botón Central Sobresaliente de Workspace para Móviles

if (empty($_SESSION['user_id']) || !empty($is_popup) || !empty($is_public)) {
    return;
}

$perms = $_SESSION['user_permissions'] ?? [];
$role_id = (int)($_SESSION['role_id'] ?? 0);
$role_name = $_SESSION['user_role'] ?? '';
$is_admin = ($role_id === 1 || in_array('config', $perms) || strtolower($role_name) === 'administrador');

$curr_mod = $current_module ?? 'dashboard';
$curr_act = $current_action ?? 'index';

// -------------------------------------------------------------------------
// 1. CONFIGURACIÓN DINÁMICA DE ITEMS LATERALES SEGÚN PERMISOS
// -------------------------------------------------------------------------

// --- LADO IZQUIERDO ---
$left_items = [];

// Slot 1: Inicio / Dashboard (o módulo principal asignado)
if (in_array('dashboard', $perms) || $is_admin) {
    $left_items[] = [
        'id' => 'dashboard',
        'title' => 'Inicio',
        'url' => 'index.php?module=dashboard&action=index',
        'icon' => 'ph ph-squares-four',
        'active' => ($curr_mod === 'dashboard')
    ];
} elseif (in_array('workspace', $perms)) {
    $left_items[] = [
        'id' => 'workspace',
        'title' => 'Inicio',
        'url' => 'index.php?module=workspace&action=index',
        'icon' => 'ph ph-squares-four',
        'active' => ($curr_mod === 'workspace')
    ];
}

// Slot 2: Calendario (o Tareas / Chat / Mensajes si no tiene acceso a calendario)
if (in_array('calendar', $perms) || in_array('reuniones', $perms) || $is_admin) {
    $left_items[] = [
        'id' => 'calendar',
        'title' => 'Calendario',
        'url' => 'index.php?module=calendar&action=index',
        'icon' => 'ph ph-calendar-blank',
        'active' => in_array($curr_mod, ['calendar', 'reuniones'])
    ];
} elseif (in_array('task_manager', $perms) || in_array('tasks', $perms)) {
    $left_items[] = [
        'id' => 'task_manager',
        'title' => 'Tareas',
        'url' => 'index.php?module=task_manager&action=index',
        'icon' => 'ph ph-check-square-offset',
        'active' => in_array($curr_mod, ['task_manager', 'tasks'])
    ];
} elseif (in_array('chat_moderno', $perms)) {
    $left_items[] = [
        'id' => 'chat_moderno',
        'title' => 'Chat',
        'url' => 'index.php?module=chat_moderno&action=index',
        'icon' => 'ph ph-chats-circle',
        'active' => ($curr_mod === 'chat_moderno')
    ];
} elseif (in_array('mensajes', $perms)) {
    $left_items[] = [
        'id' => 'mensajes',
        'title' => 'Mensajes',
        'url' => 'index.php?module=mensajes&action=index',
        'icon' => 'ph ph-chat-circle-dots',
        'active' => ($curr_mod === 'mensajes')
    ];
}

// --- LADO DERECHO ---
$right_items = [];

// Slot 3: Notas de Pago (o Finanzas / Cotizaciones / Clientes según permisos)
if ($is_admin || in_array('admin', $perms)) {
    $right_items[] = [
        'id' => 'payment_notes',
        'title' => 'Notas Pago',
        'url' => 'index.php?module=admin&action=payment_notes',
        'icon' => 'ph ph-receipt',
        'active' => ($curr_mod === 'admin' && in_array($curr_act, ['payment_notes', 'payment_note_webview']))
    ];
} elseif (in_array('quotes', $perms)) {
    $right_items[] = [
        'id' => 'quotes',
        'title' => 'Cotizaciones',
        'url' => 'index.php?module=quotes&action=index',
        'icon' => 'ph ph-file-text',
        'active' => ($curr_mod === 'quotes')
    ];
} elseif (in_array('work_orders', $perms)) {
    $right_items[] = [
        'id' => 'work_orders',
        'title' => 'Órdenes',
        'url' => 'index.php?module=work_orders&action=index',
        'icon' => 'ph ph-clipboard-text',
        'active' => ($curr_mod === 'work_orders')
    ];
} elseif (in_array('clients', $perms)) {
    $right_items[] = [
        'id' => 'clients',
        'title' => 'Clientes',
        'url' => 'index.php?module=clients&action=index',
        'icon' => 'ph ph-users',
        'active' => ($curr_mod === 'clients')
    ];
}

// Slot 4: Ajustes / Configuración (o Perfil de Usuario si no tiene acceso a config)
if ($is_admin || in_array('config', $perms)) {
    $right_items[] = [
        'id' => 'config',
        'title' => 'Ajustes',
        'url' => 'index.php?module=config&action=index',
        'icon' => 'ph ph-gear-six',
        'active' => ($curr_mod === 'config')
    ];
} else {
    // Si es un diseñador o usuario sin permisos de configuración, le damos acceso directo a su Perfil
    $right_items[] = [
        'id' => 'profile',
        'title' => 'Mi Perfil',
        'url' => 'javascript:void(0);',
        'onclick' => 'if(typeof openProfileModal === "function") openProfileModal();',
        'icon' => 'ph ph-user-circle',
        'active' => false
    ];
}

// -------------------------------------------------------------------------
// 2. APLICACIONES DEL WORKSPACE PARA EL BOTTOM SHEET CENTRAL
// -------------------------------------------------------------------------
$ws_apps = [];

// Tareas & Objetivos
if (in_array('task_manager', $perms) || in_array('tasks', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Tareas & Metas',
        'desc' => 'Control de tareas y proyectos',
        'url' => 'index.php?module=task_manager&action=index',
        'icon' => 'ph ph-check-square-offset',
        'color' => '#10b981',
        'badge' => 'Agilidad'
    ];
}

// Calendario
if (in_array('calendar', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Calendario',
        'desc' => 'Eventos y entregas',
        'url' => 'index.php?module=calendar&action=index',
        'icon' => 'ph ph-calendar-blank',
        'color' => '#f43f5e',
        'badge' => 'Agenda'
    ];
}

// Reuniones
if (in_array('reuniones', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Reuniones',
        'desc' => 'Google Meet y citas',
        'url' => 'index.php?module=reuniones&action=index',
        'icon' => 'ph ph-video-camera',
        'color' => '#06b6d4',
        'badge' => 'Meet'
    ];
}

// Pizarras / Estudio Creativo
if (in_array('pizarras', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Pizarras',
        'desc' => 'Canvas y bocetos colaborativos',
        'url' => 'index.php?module=pizarras&action=index',
        'icon' => 'ph ph-chalkboard-simple',
        'color' => '#10b981',
        'badge' => 'Canvas'
    ];
}

// Month Board
if (in_array('month_board', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Month Board',
        'desc' => 'Planificación mensual de contenidos',
        'url' => 'index.php?module=month_board&action=index',
        'icon' => 'ph ph-kanban',
        'color' => '#8b5cf6',
        'badge' => 'Social'
    ];
}

// Audiovisual
if (in_array('audiovisual', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Audiovisual',
        'desc' => 'Producción y guiones de video',
        'url' => 'index.php?module=audiovisual&action=index',
        'icon' => 'ph ph-film-slate',
        'color' => '#ea580c',
        'badge' => 'Video'
    ];
}

// Romita IA (Gemini)
if (in_array('romita', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Romita IA',
        'desc' => 'Asistente de redacción y creatividad',
        'url' => 'index.php?module=romita&action=index',
        'icon' => 'ph ph-sparkle',
        'color' => '#6366f1',
        'badge' => 'Gemini'
    ];
}

// Google Drive
$ws_apps[] = [
    'title' => 'Google Drive',
    'desc' => 'Archivos y carpetas en la nube',
    'url' => 'javascript:void(0);',
    'onclick' => 'closeMobileWorkspaceSheet(); if(typeof DriveExplorer !== "undefined") { DriveExplorer.openGlobalModal(); } else { window.location.href="index.php?module=drive&action=index"; }',
    'icon' => 'ph ph-google-drive-logo',
    'color' => '#3b82f6',
    'badge' => 'Drive'
];

// Base de Conocimiento
if (in_array('knowledge_base', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Base Conocimiento',
        'desc' => 'Documentación y guías',
        'url' => 'index.php?module=knowledge_base&action=index',
        'icon' => 'ph ph-book-open',
        'color' => '#0ea5e9',
        'badge' => 'Docs'
    ];
}

// Formularios
if (in_array('forms', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Formularios',
        'desc' => 'Briefs y encuestas dinámicas',
        'url' => 'index.php?module=forms&action=index',
        'icon' => 'ph ph-textbox',
        'color' => '#059669',
        'badge' => 'Brief'
    ];
}

// Herramientas
if (in_array('herramientas', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Herramientas',
        'desc' => 'Generadores y utilidades de agencia',
        'url' => 'index.php?module=herramientas&action=index',
        'icon' => 'ph ph-wrench',
        'color' => '#f59e0b',
        'badge' => 'Tools'
    ];
}

// Desarrollo de Marca
if (in_array('desarrollo_marca', $perms) || $is_admin) {
    $ws_apps[] = [
        'title' => 'Marca & Manuales',
        'desc' => 'Identidad corporativa y brandbooks',
        'url' => 'index.php?module=desarrollo_marca&action=index',
        'icon' => 'ph ph-paint-brush-broad',
        'color' => '#ec4899',
        'badge' => 'Brand'
    ];
}
?>

<!-- =========================================================================
     ESTILOS DEL MOBILE BOTTOM NAVIGATION BAR & WORKSPACE SHEET
     ========================================================================= -->
<style>
/* Solo visible en dispositivos móviles / pantallas pequeñas */
@media (min-width: 769px) {
    .mobile-bottom-navbar,
    .mobile-ws-sheet-overlay,
    .mobile-ws-sheet {
        display: none !important;
    }
}

@media (max-width: 768px) {
    /* Ajuste en el body/main para no tapar contenido detrás de la barra */
    body {
        padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
    }

    /* BARRA INFERIOR FLOTANTE */
    .mobile-bottom-navbar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 64px;
        background: rgba(255, 255, 255, 0.88);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        backdrop-filter: blur(20px) saturate(180%);
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-around;
        padding: 0 0.5rem;
        padding-bottom: env(safe-area-inset-bottom, 0px);
        z-index: 9998;
        user-select: none;
    }

    [data-theme="dark"] .mobile-bottom-navbar {
        background: rgba(17, 24, 39, 0.92);
        border-top: 1px solid rgba(255, 255, 255, 0.09);
        box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.45);
    }

    /* ITEMS LATERALES */
    .mobile-nav-slot {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        color: var(--text-muted, #64748b);
        text-decoration: none;
        padding: 6px 2px;
        min-width: 0;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-tap-highlight-color: transparent;
        position: relative;
    }

    .mobile-nav-slot:active {
        transform: scale(0.92);
    }

    .mobile-nav-slot i {
        font-size: 1.42rem;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .mobile-nav-slot .nav-label {
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: -0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 68px;
    }

    .mobile-nav-slot.active {
        color: var(--primary-color, #4f46e5);
        font-weight: 700;
    }

    .mobile-nav-slot.active i {
        transform: translateY(-1px);
    }

    .mobile-nav-slot.active::after {
        content: '';
        position: absolute;
        bottom: 2px;
        width: 14px;
        height: 3px;
        border-radius: 999px;
        background: var(--primary-color, #4f46e5);
    }

    /* BOTÓN CENTRAL SOBRESALIENTE (FAB WORKSPACE) */
    .mobile-fab-center-wrap {
        flex: 0 0 68px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 9999;
    }

    .mobile-fab-btn {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color, #4f46e5) 0%, #7c3aed 100%);
        color: #ffffff;
        border: 4px solid var(--bg-surface, #ffffff);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        cursor: pointer;
        box-shadow: 0 8px 24px color-mix(in srgb, var(--primary-color, #4f46e5) 45%, transparent),
                    0 2px 8px rgba(0, 0, 0, 0.15);
        transform: translateY(-16px);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
        -webkit-tap-highlight-color: transparent;
    }

    [data-theme="dark"] .mobile-fab-btn {
        border-color: #111827;
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5),
                    0 2px 10px rgba(0, 0, 0, 0.6);
    }

    .mobile-fab-btn:active {
        transform: translateY(-12px) scale(0.94);
    }

    .mobile-fab-btn.is-opened {
        transform: translateY(-16px) rotate(45deg);
        background: #ef4444;
        box-shadow: 0 8px 24px rgba(239, 68, 68, 0.45);
    }

    .mobile-fab-label {
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--primary-color, #4f46e5);
        margin-top: -12px;
        pointer-events: none;
    }

    /* OVERLAY Y BOTTOM SHEET DE WORKSPACE */
    .mobile-ws-sheet-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        -webkit-backdrop-filter: blur(6px);
        backdrop-filter: blur(6px);
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.3s;
    }

    .mobile-ws-sheet-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .mobile-ws-sheet {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        max-height: 82vh;
        background: var(--bg-surface, #ffffff);
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        border-top: 1px solid var(--border-color, rgba(0,0,0,0.08));
        box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.22);
        z-index: 10001;
        display: flex;
        flex-direction: column;
        transform: translateY(105%);
        transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
        padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
    }

    [data-theme="dark"] .mobile-ws-sheet {
        background: #111827;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 -12px 45px rgba(0, 0, 0, 0.65);
    }

    .mobile-ws-sheet.active {
        transform: translateY(0);
    }

    /* Barra de arrastre (Handle) */
    .mobile-ws-handle-bar {
        width: 100%;
        display: flex;
        justify-content: center;
        padding: 0.65rem 0 0.35rem 0;
        cursor: pointer;
    }

    .mobile-ws-handle {
        width: 44px;
        height: 5px;
        border-radius: 999px;
        background: var(--border-color, #cbd5e1);
        opacity: 0.8;
    }

    /* Cabecera del Sheet */
    .mobile-ws-sheet-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 1.25rem 0.85rem 1.25rem;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.06));
    }

    .mobile-ws-sheet-title {
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }

    .mobile-ws-sheet-title .ws-logo-pill {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary-color, #4f46e5), #7c3aed);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        box-shadow: 0 4px 10px color-mix(in srgb, var(--primary-color) 35%, transparent);
    }

    .mobile-ws-sheet-title h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--color-title, #0f172a);
        letter-spacing: -0.02em;
    }

    .mobile-ws-sheet-title p {
        margin: 0;
        font-size: 0.75rem;
        color: var(--text-muted, #64748b);
    }

    .mobile-ws-close-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bg-body, #f1f5f9);
        border: 1px solid var(--border-color, rgba(0,0,0,0.06));
        color: var(--text-muted, #64748b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
    }

    [data-theme="dark"] .mobile-ws-close-btn {
        background: rgba(255, 255, 255, 0.06);
    }

    /* Grid de Aplicaciones */
    .mobile-ws-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        padding: 1.15rem 1rem 0.5rem 1rem;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    @media (max-width: 360px) {
        .mobile-ws-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .mobile-ws-app-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 0.85rem 0.45rem;
        border-radius: 16px;
        background: var(--bg-body, #f8fafc);
        border: 1px solid var(--border-color, rgba(0,0,0,0.05));
        text-decoration: none;
        color: var(--text-main, #0f172a);
        position: relative;
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    [data-theme="dark"] .mobile-ws-app-item {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.06);
    }

    .mobile-ws-app-item:active {
        transform: scale(0.94);
        background: color-mix(in srgb, var(--primary-color) 8%, transparent);
    }

    .mobile-ws-app-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        color: #ffffff;
        margin-bottom: 0.5rem;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
    }

    .mobile-ws-app-name {
        font-size: 11.5px;
        font-weight: 700;
        color: var(--color-title, #0f172a);
        line-height: 1.2;
        margin-bottom: 2px;
        max-width: 90px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mobile-ws-app-tag {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted, #64748b);
        letter-spacing: 0.03em;
    }

    /* Pie de página del Sheet: Ir a Workspace completo */
    .mobile-ws-sheet-footer {
        padding: 0.75rem 1rem 0 1rem;
    }

    .mobile-ws-footer-link {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.75rem;
        border-radius: 12px;
        background: color-mix(in srgb, var(--primary-color, #4f46e5) 10%, transparent);
        color: var(--primary-color, #4f46e5);
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        border: 1px solid color-mix(in srgb, var(--primary-color, #4f46e5) 25%, transparent);
    }
}
</style>

<!-- =========================================================================
     BARRA INFERIOR DE NAVEGACIÓN MÓVIL (BOTTOM NAVBAR)
     ========================================================================= -->
<nav class="mobile-bottom-navbar" id="mobileBottomNavbar">
    <!-- Items Izquierda (Dinámicos por Permisos) -->
    <?php foreach ($left_items as $item): ?>
        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
           class="mobile-nav-slot <?php echo !empty($item['active']) ? 'active' : ''; ?>"
           <?php if(!empty($item['onclick'])) echo 'onclick="' . htmlspecialchars($item['onclick']) . '"'; ?>
           title="<?php echo htmlspecialchars($item['title']); ?>">
            <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
            <span class="nav-label"><?php echo htmlspecialchars($item['title']); ?></span>
        </a>
    <?php endforeach; ?>

    <!-- Botón Central Sobresaliente: Workspace Actions Launcher -->
    <div class="mobile-fab-center-wrap">
        <button type="button" class="mobile-fab-btn" id="mobileFabWorkspaceBtn" aria-label="Abrir aplicaciones del Workspace" title="Aplicaciones del Workspace">
            <i class="ph ph-squares-four" id="mobileFabIcon"></i>
        </button>
        <span class="mobile-fab-label">Apps</span>
    </div>

    <!-- Items Derecha (Dinámicos por Permisos) -->
    <?php foreach ($right_items as $item): ?>
        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
           class="mobile-nav-slot <?php echo !empty($item['active']) ? 'active' : ''; ?>"
           <?php if(!empty($item['onclick'])) echo 'onclick="' . htmlspecialchars($item['onclick']) . '"'; ?>
           title="<?php echo htmlspecialchars($item['title']); ?>">
            <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
            <span class="nav-label"><?php echo htmlspecialchars($item['title']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- =========================================================================
     MODAL BOTTOM SHEET: APLICACIONES DEL WORKSPACE
     ========================================================================= -->
<div class="mobile-ws-sheet-overlay" id="mobileWsOverlay" onclick="closeMobileWorkspaceSheet()"></div>

<div class="mobile-ws-sheet" id="mobileWsSheet" role="dialog" aria-modal="true" aria-labelledby="wsSheetTitle">
    <!-- Drag Handle -->
    <div class="mobile-ws-handle-bar" onclick="closeMobileWorkspaceSheet()">
        <span class="mobile-ws-handle"></span>
    </div>

    <!-- Header -->
    <div class="mobile-ws-sheet-header">
        <div class="mobile-ws-sheet-title">
            <div class="ws-logo-pill">
                <i class="ph ph-squares-four"></i>
            </div>
            <div>
                <h3 id="wsSheetTitle">Workspace</h3>
                <p>Aplicaciones y herramientas rápidas</p>
            </div>
        </div>
        <button type="button" class="mobile-ws-close-btn" onclick="closeMobileWorkspaceSheet()" aria-label="Cerrar">
            <i class="ph ph-x"></i>
        </button>
    </div>

    <!-- Apps Grid -->
    <div class="mobile-ws-grid">
        <?php foreach ($ws_apps as $app): ?>
            <a href="<?php echo htmlspecialchars($app['url']); ?>" 
               class="mobile-ws-app-item"
               <?php if(!empty($app['onclick'])) echo 'onclick="' . htmlspecialchars($app['onclick']) . '"'; ?>>
                <div class="mobile-ws-app-icon" style="background: <?php echo htmlspecialchars($app['color']); ?>;">
                    <i class="<?php echo htmlspecialchars($app['icon']); ?>"></i>
                </div>
                <span class="mobile-ws-app-name"><?php echo htmlspecialchars($app['title']); ?></span>
                <span class="mobile-ws-app-tag"><?php echo htmlspecialchars($app['badge']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Footer: Link al Workspace General -->
    <div class="mobile-ws-sheet-footer">
        <a href="index.php?module=workspace&action=index" class="mobile-ws-footer-link" onclick="closeMobileWorkspaceSheet()">
            <i class="ph ph-arrow-right"></i>
            <span>Ver Centro de Workspace Completo</span>
        </a>
    </div>
</div>

<!-- =========================================================================
     SCRIPT DE INTERACCIÓN Y CONTROL DEL BOTTOM SHEET
     ========================================================================= -->
<script>
function toggleMobileWorkspaceSheet() {
    const sheet = document.getElementById('mobileWsSheet');
    const overlay = document.getElementById('mobileWsOverlay');
    const fabBtn = document.getElementById('mobileFabWorkspaceBtn');
    const fabIcon = document.getElementById('mobileFabIcon');

    if (!sheet || !overlay) return;

    const isOpen = sheet.classList.contains('active');

    if (isOpen) {
        closeMobileWorkspaceSheet();
    } else {
        openMobileWorkspaceSheet();
    }
}

function openMobileWorkspaceSheet() {
    const sheet = document.getElementById('mobileWsSheet');
    const overlay = document.getElementById('mobileWsOverlay');
    const fabBtn = document.getElementById('mobileFabWorkspaceBtn');
    const fabIcon = document.getElementById('mobileFabIcon');

    if (sheet && overlay) {
        sheet.classList.add('active');
        overlay.classList.add('active');
        if (fabBtn) fabBtn.classList.add('is-opened');
        if (fabIcon) {
            fabIcon.classList.remove('ph-squares-four');
            fabIcon.classList.add('ph-x');
        }
        document.body.style.overflow = 'hidden'; // Evitar scroll del fondo mientras está abierto
    }
}

function closeMobileWorkspaceSheet() {
    const sheet = document.getElementById('mobileWsSheet');
    const overlay = document.getElementById('mobileWsOverlay');
    const fabBtn = document.getElementById('mobileFabWorkspaceBtn');
    const fabIcon = document.getElementById('mobileFabIcon');

    if (sheet && overlay) {
        sheet.classList.remove('active');
        overlay.classList.remove('active');
        if (fabBtn) fabBtn.classList.remove('is-opened');
        if (fabIcon) {
            fabIcon.classList.remove('ph-x');
            fabIcon.classList.add('ph-squares-four');
        }
        document.body.style.overflow = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const fabBtn = document.getElementById('mobileFabWorkspaceBtn');
    if (fabBtn) {
        fabBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMobileWorkspaceSheet();
        });
    }

    // Cerrar al presionar tecla Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeMobileWorkspaceSheet();
        }
    });

    // Soporte para deslizar hacia abajo (Swipe down to close)
    let touchStartY = 0;
    const sheet = document.getElementById('mobileWsSheet');
    if (sheet) {
        sheet.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        sheet.addEventListener('touchmove', (e) => {
            const touchCurrentY = e.touches[0].clientY;
            const diff = touchCurrentY - touchStartY;
            // Si desliza hacia abajo más de 70px en la parte superior del sheet, cerrar
            if (diff > 70 && sheet.scrollTop <= 0) {
                closeMobileWorkspaceSheet();
            }
        }, { passive: true });
    }
});
</script>
