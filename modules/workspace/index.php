<?php
// modules/workspace/index.php
require_once 'includes/header.php';
?>
<style>
/* ==========================================================================
   WORKSPACE MODERN APP STORE / LAUNCHPAD DESIGN SYSTEM
   ========================================================================== */

.workspace-container {
    padding: 2.25rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
    animation: wsFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes wsFadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header App Style */
.workspace-header {
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.workspace-header-main {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.workspace-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--primary-color, #4f46e5);
}

.workspace-title-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.workspace-header h1 {
    margin: 0;
    font-size: 2.25rem;
    font-weight: 900;
    letter-spacing: -0.8px;
    color: var(--text-main, #0f172a);
    line-height: 1.15;
}

[data-theme="dark"] .workspace-header h1 {
    color: #ffffff;
}

.ws-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: color-mix(in srgb, var(--primary-color, #4f46e5) 12%, transparent);
    color: var(--primary-color, #4f46e5);
    border: 1px solid color-mix(in srgb, var(--primary-color, #4f46e5) 25%, transparent);
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.22rem 0.65rem;
    border-radius: 9999px;
}

.workspace-header p {
    margin: 0;
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
    font-weight: 500;
}

/* Search Box Launchpad Style */
.workspace-search-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 280px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.workspace-search-box:focus-within {
    width: 320px;
}

.workspace-search-box .search-icon {
    position: absolute;
    left: 1.05rem;
    color: var(--text-muted, #94a3b8);
    font-size: 1.05rem;
    pointer-events: none;
    transition: color 0.2s;
}

.workspace-search-box:focus-within .search-icon {
    color: var(--primary-color, #4f46e5);
}

.ws-search-input {
    width: 100%;
    padding: 0.65rem 2.4rem 0.65rem 2.65rem;
    border-radius: 9999px;
    background: var(--bg-surface, #ffffff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a);
    font-size: 0.88rem;
    font-weight: 500;
    outline: none;
    transition: all 0.25s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .ws-search-input {
    background: #141416;
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.ws-search-input:focus {
    border-color: var(--primary-color, #4f46e5);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color, #4f46e5) 15%, transparent);
}

.ws-clear-btn {
    position: absolute;
    right: 0.75rem;
    background: transparent;
    border: none;
    color: var(--text-muted, #94a3b8);
    font-size: 0.95rem;
    cursor: pointer;
    padding: 0.2rem;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.ws-clear-btn:hover {
    color: var(--text-main, #0f172a);
}

/* ==========================================================================
   APP GRID & APP TILE CARDS (APPLE BENTO LAUNCHER)
   ========================================================================== */

.workspace-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
}

.workspace-card {
    position: relative;
    background: var(--bg-surface, #ffffff);
    border: 1.5px solid var(--border-color, rgba(226, 232, 240, 0.85));
    border-radius: 22px;
    padding: 1.35rem 1.25rem;
    text-decoration: none;
    color: var(--text-main, #0f172a);
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.04);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    overflow: hidden;
    user-select: none;
    cursor: pointer;
}

[data-theme="dark"] .workspace-card {
    background: #131417;
    border-color: rgba(255, 255, 255, 0.07);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}

.workspace-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px -6px rgba(0, 0, 0, 0.1);
    border-color: color-mix(in srgb, var(--primary-color, #4f46e5) 45%, transparent);
}

[data-theme="dark"] .workspace-card:hover {
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
    border-color: rgba(255, 255, 255, 0.2);
    background: #17181c;
}

.workspace-card:active {
    transform: scale(0.97);
}

/* Card Top Row */
.workspace-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
}

/* Squircle App Icons */
.workspace-card-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #ffffff;
    flex-shrink: 0;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.workspace-card:hover .workspace-card-icon {
    transform: scale(1.08) rotate(3deg);
}

/* Distinct Vivid Gradients with Ambient Glow */
.icon-tasks {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    box-shadow: 0 8px 20px -3px rgba(99, 102, 241, 0.45);
}
.icon-romita {
    background: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
    box-shadow: 0 8px 20px -3px rgba(236, 72, 153, 0.45);
}
.icon-brand {
    background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
    box-shadow: 0 8px 20px -3px rgba(14, 165, 233, 0.45);
}
.icon-kb {
    background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
    box-shadow: 0 8px 20px -3px rgba(139, 92, 246, 0.45);
}
.icon-meetings {
    background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    box-shadow: 0 8px 20px -3px rgba(6, 182, 212, 0.45);
}
.icon-whiteboard {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 8px 20px -3px rgba(16, 185, 129, 0.45);
}
.icon-tools {
    background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
    box-shadow: 0 8px 20px -3px rgba(245, 158, 11, 0.45);
}
.icon-audio {
    background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
    box-shadow: 0 8px 20px -3px rgba(239, 68, 68, 0.45);
}
.icon-app {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    box-shadow: 0 6px 14px -3px rgba(100, 116, 139, 0.3);
}
.icon-web {
    background: linear-gradient(135deg, #52525b 0%, #3f3f46 100%);
    box-shadow: 0 6px 14px -3px rgba(82, 82, 91, 0.3);
}

/* Action Chip (Launch Arrow) */
.app-launch-chip {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--bg-body, #f1f5f9);
    border: 1px solid var(--border-color, rgba(0,0,0,0.06));
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.22s ease;
}

[data-theme="dark"] .app-launch-chip {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
}

.workspace-card:hover .app-launch-chip {
    background: var(--primary-color, #4f46e5);
    color: #ffffff;
    border-color: var(--primary-color, #4f46e5);
    transform: translate(2px, -2px);
}

/* Card Content */
.workspace-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
}

.workspace-card-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin: 0;
    letter-spacing: -0.3px;
    line-height: 1.25;
    transition: color 0.2s;
}

[data-theme="dark"] .workspace-card-title {
    color: #ffffff;
}

.workspace-card:hover .workspace-card-title {
    color: var(--primary-color, #4f46e5);
}

.workspace-card-desc {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Card Footer / Tag Row */
.workspace-card-footer {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding-top: 0.65rem;
    border-top: 1px solid var(--border-color, rgba(0,0,0,0.05));
}

[data-theme="dark"] .workspace-card-footer {
    border-top-color: rgba(255, 255, 255, 0.06);
}

.ws-app-tag {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #94a3b8);
    background: var(--bg-body, #f8fafc);
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid var(--border-color, rgba(0,0,0,0.06));
}

[data-theme="dark"] .ws-app-tag {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.06);
}

/* Disabled Card */
.workspace-card.is-disabled {
    opacity: 0.65;
    cursor: not-allowed;
    background: var(--bg-body, #f8fafc);
}

[data-theme="dark"] .workspace-card.is-disabled {
    background: #0f1012;
    opacity: 0.45;
}

.workspace-card.is-disabled:hover {
    transform: none !important;
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.04) !important;
    border-color: var(--border-color, rgba(226, 232, 240, 0.85)) !important;
}

.workspace-card.is-disabled:hover .workspace-card-icon {
    transform: none !important;
}

.ws-badge-locked {
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
    padding: 0.22rem 0.6rem;
    border-radius: 9999px;
    border: 1px solid rgba(148, 163, 184, 0.25);
}

/* Empty State */
.ws-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-surface, #ffffff);
    border-radius: 22px;
    border: 1.5px dashed var(--border-color, #e2e8f0);
}

[data-theme="dark"] .ws-empty-state {
    background: #131417;
    border-color: rgba(255, 255, 255, 0.1);
}

.ws-empty-icon {
    font-size: 2.2rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 0.5rem;
}

/* ==========================================================================
   RESPONSIVE LAYOUT (2 COLUMNS AS REQUESTED)
   ========================================================================== */

@media (max-width: 768px) {
    .workspace-container {
        padding: 1.25rem 0.85rem;
    }

    .workspace-header {
        margin-bottom: 1.25rem;
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .workspace-header h1 {
        font-size: 1.75rem;
    }

    .workspace-search-box {
        width: 100% !important;
    }

    /* 2 COLUMNS EXACTLY ON MOBILE */
    .workspace-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
    }

    .workspace-card {
        padding: 1rem 0.85rem !important;
        border-radius: 18px !important;
        gap: 0.75rem !important;
    }

    .workspace-card-icon {
        width: 44px !important;
        height: 44px !important;
        font-size: 22px !important;
        border-radius: 13px !important;
    }

    .app-launch-chip {
        width: 26px !important;
        height: 26px !important;
        font-size: 0.8rem !important;
        border-radius: 8px !important;
    }

    .workspace-card-title {
        font-size: 0.88rem !important;
        line-height: 1.2 !important;
    }

    .workspace-card-desc {
        font-size: 0.72rem !important;
        line-height: 1.3 !important;
        -webkit-line-clamp: 2 !important;
    }

    .workspace-card-footer {
        padding-top: 0.45rem !important;
    }

    .ws-app-tag {
        font-size: 0.62rem !important;
        padding: 0.15rem 0.45rem !important;
    }

    .ws-badge-locked {
        font-size: 0.62rem !important;
        padding: 0.15rem 0.45rem !important;
    }
}

@media (max-width: 380px) {
    .workspace-grid {
        gap: 0.55rem !important;
    }
    .workspace-card {
        padding: 0.85rem 0.7rem !important;
    }
    .workspace-card-icon {
        width: 38px !important;
        height: 38px !important;
        font-size: 19px !important;
    }
}
</style>

<?php
    $perms = $_SESSION['user_permissions'] ?? [];

    $active_apps_count = 0;
    if (in_array('task_manager', $perms)) $active_apps_count++;
    if (in_array('romita', $perms)) $active_apps_count++;
    if (in_array('desarrollo_marca', $perms)) $active_apps_count++;
    if (in_array('knowledge_base', $perms)) $active_apps_count++;
    if (in_array('reuniones', $perms)) $active_apps_count++;
    if (in_array('pizarras', $perms)) $active_apps_count++;
    if (in_array('herramientas', $perms)) $active_apps_count++;
    if (in_array('audiovisual', $perms)) $active_apps_count++;
?>

<div class="workspace-container">
    <div class="workspace-header">
        <div class="workspace-header-main">
            <span class="workspace-kicker"><i class="ph-fill ph-squares-four"></i> Centro de Aplicaciones</span>
            <div class="workspace-title-row">
                <h1>Workspace</h1>
                <span class="ws-count-badge"><?php echo $active_apps_count; ?> activas</span>
            </div>
            <p>Acceso rápido a todas las herramientas y módulos de tu agencia</p>
        </div>
        <div class="workspace-search-box">
            <i class="ph-bold ph-magnifying-glass search-icon"></i>
            <input type="text" id="wsAppSearch" class="ws-search-input" placeholder="Buscar aplicación..." oninput="filterWorkspaceApps(this.value)">
            <button type="button" id="wsSearchClear" class="ws-clear-btn" onclick="clearWsSearch()" title="Limpiar"><i class="ph-bold ph-x"></i></button>
        </div>
    </div>

    <div class="workspace-grid" id="workspaceGrid">
        
        <!-- Tareas y Objetivos Diarios (Activo) -->
        <?php if (in_array('task_manager', $perms)): ?>
        <a href="index.php?module=task_manager&action=index" class="workspace-card" data-title="Tareas & Objetivos" data-desc="Control de tareas diarias semanales metas proyectos activos">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-tasks">
                    <i class="ph-bold ph-check-square-offset"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Tareas & Objetivos</h3>
                <p class="workspace-card-desc">Control de tareas diarias, metas y proyectos activos.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Gestión</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Romita IA (Activo) -->
        <?php if (in_array('romita', $perms)): ?>
        <a href="index.php?module=romita&action=index" class="workspace-card" data-title="Romita IA" data-desc="Asistente inteligente con IA creación contenidos estrategias automatización">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-romita">
                    <i class="ph-bold ph-sparkle"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title" style="background: linear-gradient(135deg, #818cf8, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;">Romita IA</h3>
                <p class="workspace-card-desc">Asistente IA para creación de contenidos y estrategias.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Inteligencia Artificial</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Desarrollo de Marca (Activo) -->
        <?php if (in_array('desarrollo_marca', $perms)): ?>
        <a href="index.php?module=desarrollo_marca&action=index" class="workspace-card" data-title="Desarrollo de Marca" data-desc="Identidad visual manuales de marca assets corporativos branding">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-brand">
                    <i class="ph-bold ph-paint-brush-broad"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Desarrollo de Marca</h3>
                <p class="workspace-card-desc">Identidad visual, manuales de marca y assets corporativos.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Branding</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Audiovisual (Activo) -->
        <?php if (in_array('audiovisual', $perms)): ?>
        <a href="index.php?module=audiovisual&action=index" class="workspace-card" data-title="Audiovisual" data-desc="Producción de videos rodajes edición fotografía reels multimedia">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-audio">
                    <i class="ph-bold ph-film-strip"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Audiovisual</h3>
                <p class="workspace-card-desc">Producción de videos, rodajes, edición y multimedia.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Producción</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Base de Conocimiento (Activo) -->
        <?php if (in_array('knowledge_base', $perms)): ?>
        <a href="index.php?module=knowledge_base&action=index" class="workspace-card" data-title="Base de Conocimiento" data-desc="Manuales de procesos tutoriales video guías clientes estandarización">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-kb">
                    <i class="ph-bold ph-book-open"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Base de Conocimiento</h3>
                <p class="workspace-card-desc">Manuales de procesos, tutoriales y guías para clientes.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Documentación</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Reuniones (Activo) -->
        <?php if (in_array('reuniones', $perms)): ?>
        <a href="index.php?module=reuniones&action=index" class="workspace-card" data-title="Reuniones" data-desc="Salas de videollamadas grabaciones enlaces interactivos clientes equipo">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-meetings">
                    <i class="ph-bold ph-video-camera"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Reuniones</h3>
                <p class="workspace-card-desc">Salas de videollamadas, grabaciones y enlaces interactivos.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Videollamadas</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Pizarras (Activo) -->
        <?php if (in_array('pizarras', $perms)): ?>
        <a href="index.php?module=pizarras&action=index" class="workspace-card" data-title="Pizarras" data-desc="Pizarras colaborativas infinitas diagramas notas visuales tiempo real">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-whiteboard">
                    <i class="ph-bold ph-chalkboard"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Pizarras</h3>
                <p class="workspace-card-desc">Lienzos infinitos, diagramas y notas visuales en vivo.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Colaboración</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Herramientas (Activo) -->
        <?php if (in_array('herramientas', $perms)): ?>
        <a href="index.php?module=herramientas&action=index" class="workspace-card" data-title="Herramientas" data-desc="Calculadoras generadores de enlaces utilidades herramientas marketing">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-tools">
                    <i class="ph-bold ph-wrench"></i>
                </div>
                <div class="app-launch-chip">
                    <i class="ph-bold ph-arrow-up-right"></i>
                </div>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Herramientas</h3>
                <p class="workspace-card-desc">Calculadoras, generadores de enlaces y utilidades de agencia.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Utilidades</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- App (Desactivado) -->
        <div class="workspace-card is-disabled" data-title="App Móvil" data-desc="Accede a configuraciones desarrollo aplicacion movil">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-app">
                    <i class="ph-bold ph-device-mobile"></i>
                </div>
                <span class="ws-badge-locked"><i class="ph-bold ph-lock-key"></i> Próximamente</span>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">App Móvil</h3>
                <p class="workspace-card-desc">Configuración y desarrollo de la aplicación móvil.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Móvil</span>
            </div>
        </div>

        <!-- Desarrollo Web (Desactivado) -->
        <div class="workspace-card is-disabled" data-title="Desarrollo Web" data-desc="Proyectos web sitios corporativos ecommerce landing pages">
            <div class="workspace-card-top">
                <div class="workspace-card-icon icon-web">
                    <i class="ph-bold ph-browser"></i>
                </div>
                <span class="ws-badge-locked"><i class="ph-bold ph-lock-key"></i> Próximamente</span>
            </div>
            <div class="workspace-card-content">
                <h3 class="workspace-card-title">Desarrollo Web</h3>
                <p class="workspace-card-desc">Sitios corporativos, tiendas online y landing pages.</p>
            </div>
            <div class="workspace-card-footer">
                <span class="ws-app-tag">Web & E-com</span>
            </div>
        </div>

        <!-- Empty state -->
        <div id="ws-empty-state" class="ws-empty-state" style="display: none;">
            <div class="ws-empty-icon"><i class="ph-bold ph-magnifying-glass"></i></div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">No se encontraron aplicaciones</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Prueba escribiendo otro término de búsqueda.</p>
        </div>

    </div>
</div>

<script>
function filterWorkspaceApps(query) {
    const q = (query || '').toLowerCase().trim();
    const clearBtn = document.getElementById('wsSearchClear');
    if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';

    const cards = document.querySelectorAll('.workspace-card');
    let visibleCount = 0;
    cards.forEach(card => {
        const title = (card.dataset.title || card.querySelector('.workspace-card-title')?.innerText || '').toLowerCase();
        const desc = (card.dataset.desc || card.querySelector('.workspace-card-desc')?.innerText || '').toLowerCase();
        const matches = title.includes(q) || desc.includes(q);
        card.style.display = matches ? 'flex' : 'none';
        if (matches) visibleCount++;
    });

    const emptyState = document.getElementById('ws-empty-state');
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function clearWsSearch() {
    const input = document.getElementById('wsAppSearch');
    if (input) input.value = '';
    filterWorkspaceApps('');
}
</script>

<?php require_once 'includes/footer.php'; ?>
