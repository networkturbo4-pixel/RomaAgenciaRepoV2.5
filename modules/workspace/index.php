<?php
// modules/workspace/index.php
require_once 'includes/header.php';
?>
<style>
/* Workspace Modern Design System */
.workspace-container {
    padding: var(--space-6);
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family);
    animation: fadeIn 0.5s ease-out;
}

.workspace-header {
    margin-bottom: var(--space-8);
    text-align: left;
}

.workspace-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: var(--space-2);
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    color: transparent !important;
    letter-spacing: -0.02em;
    display: inline-block;
}

.workspace-header p {
    color: var(--color-text);
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Grid Layout */
.workspace-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-6);
}

/* Modern Card */
.workspace-card {
    position: relative;
    background: var(--bg-surface);
    border-radius: 20px;
    padding: var(--space-6);
    text-decoration: none;
    color: var(--color-title);
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    z-index: 1;
}

[data-theme="dark"] .workspace-card {
    background: #0a0a0a;
    border: 1px solid #262626;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.workspace-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0) 100%);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.4s ease;
}

.workspace-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-color);
}

[data-theme="dark"] .workspace-card:hover {
    background: #111111;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
    border-color: rgba(99, 102, 241, 0.6);
}

.workspace-card:hover::before {
    opacity: 1;
}

/* Icons */
.workspace-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: var(--space-4);
    color: white;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    transition: transform 0.4s ease;
}

.workspace-card:hover .workspace-card-icon {
    transform: scale(1.1) rotate(5deg);
}

.workspace-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: var(--space-2);
    color: var(--color-title);
}

.workspace-card-desc {
    font-size: 0.9rem;
    color: var(--color-text);
    line-height: 1.5;
}

.icon-tasks { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.icon-romita { background: linear-gradient(135deg, #4f46e5, #ec4899); }
.icon-app { background: linear-gradient(135deg, #71717a, #52525b); }
.icon-brand { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.icon-web { background: linear-gradient(135deg, #52525b, #3f3f46); }
.icon-audio { background: linear-gradient(135deg, #52525b, #3f3f46); }

/* Disabled Card State */
.workspace-card.is-disabled {
    cursor: not-allowed;
    opacity: 0.5;
    filter: grayscale(0.6);
    user-select: none;
}

.workspace-card.is-disabled:hover {
    transform: none !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
    border-color: var(--border-color) !important;
}

[data-theme="dark"] .workspace-card.is-disabled:hover {
    background: #0a0a0a !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
    border-color: #262626 !important;
}

.workspace-card.is-disabled:hover .workspace-card-icon {
    transform: none !important;
}

.workspace-card.is-disabled::before {
    display: none !important;
}

.workspace-card-badge-disabled {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    background: rgba(255, 255, 255, 0.08);
    color: var(--color-text, #71717a);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .workspace-card-badge-disabled {
    background: rgba(255, 255, 255, 0.04);
    color: #71717a;
    border-color: #262626;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .workspace-grid {
        grid-template-columns: 1fr;
    }
    .workspace-header h1 {
        font-size: 2rem;
    }
}
</style>

<?php
    $favicon_url = !empty($global_settings['favicon']) ? $global_settings['favicon'] : '';
    $user_first_name = isset($_SESSION['user_name']) ? htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) : 'Usuario';
?>
<div class="workspace-container">
    <div class="workspace-header">
        <h1>Workspace</h1>
    </div>

    <div class="workspace-grid">
        
        <!-- Tareas y Objetivos Diarios (Activo) -->
        <a href="index.php?module=task_manager&action=index" class="workspace-card" style="animation-delay: 0.05s;">
            <div class="workspace-card-icon icon-tasks">
                <i class="ph ph-check-square-offset"></i>
            </div>
            <h3 class="workspace-card-title">Tareas & Objetivos</h3>
            <p class="workspace-card-desc">Control de tareas diarias, semanales, evaluación de objetivos y proyectos activos.</p>
        </a>

        <!-- Romita IA (Activo) -->
        <a href="index.php?module=romita&action=index" class="workspace-card" style="animation-delay: 0.1s;">
            <div class="workspace-card-icon icon-romita">
                <i class="ph ph-sparkle"></i>
            </div>
            <h3 class="workspace-card-title" style="background: linear-gradient(90deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;">Romita IA</h3>
            <p class="workspace-card-desc">Asistente inteligente con IA para creación de contenidos, estrategias y automatización.</p>
        </a>

        <!-- Desarrollo de Marca (Activo) -->
        <a href="index.php?module=desarrollo_marca&action=index" class="workspace-card" style="animation-delay: 0.15s;">
            <div class="workspace-card-icon icon-brand">
                <i class="ph ph-paint-brush-broad"></i>
            </div>
            <h3 class="workspace-card-title">Desarrollo de Marca</h3>
            <p class="workspace-card-desc">Gestión de identidad visual, manuales de marca y assets corporativos.</p>
        </a>

        <!-- App (Desactivado) -->
        <div class="workspace-card is-disabled" style="animation-delay: 0.2s;">
            <span class="workspace-card-badge-disabled"><i class="ph ph-lock-key"></i> Desactivado</span>
            <div class="workspace-card-icon icon-app">
                <i class="ph ph-device-mobile"></i>
            </div>
            <h3 class="workspace-card-title">App</h3>
            <p class="workspace-card-desc">Accede a las configuraciones y desarrollo de la aplicación móvil.</p>
        </div>

        <!-- Desarrollo Web (Desactivado) -->
        <div class="workspace-card is-disabled" style="animation-delay: 0.25s;">
            <span class="workspace-card-badge-disabled"><i class="ph ph-lock-key"></i> Desactivado</span>
            <div class="workspace-card-icon icon-web">
                <i class="ph ph-browser"></i>
            </div>
            <h3 class="workspace-card-title">Desarrollo Web</h3>
            <p class="workspace-card-desc">Proyectos web, sitios corporativos, e-commerce y landing pages.</p>
        </div>

        <!-- Audiovisual (Desactivado) -->
        <div class="workspace-card is-disabled" style="animation-delay: 0.3s;">
            <span class="workspace-card-badge-disabled"><i class="ph ph-lock-key"></i> Desactivado</span>
            <div class="workspace-card-icon icon-audio">
                <i class="ph ph-video-camera"></i>
            </div>
            <h3 class="workspace-card-title">Audiovisual</h3>
            <p class="workspace-card-desc">Producción de videos, fotografía, edición y material multimedia.</p>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
