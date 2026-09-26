<?php
// includes/romita_floating_modal.php
// Romita AI Global Floating Assistant & Command Palette (Tecla R)
if (!isset($_SESSION['user_id'])) return;

$currentUserName = $_SESSION['user_name'] ?? '';
if (empty($currentUserName) && isset($db) && $db instanceof PDO) {
    try {
        $stmtU = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $currentUserName = $stmtU->fetchColumn() ?: '';
    } catch (Exception $e) {}
}
$romitaFirstName = !empty($currentUserName) ? explode(' ', trim($currentUserName))[0] : 'Colega';

// Saludo dinámico según la hora del día
$currentHour = (int)date('G');
if ($currentHour >= 5 && $currentHour < 12) {
    $timeGreeting = 'Buenos días';
} elseif ($currentHour >= 12 && $currentHour < 19) {
    $timeGreeting = 'Buenas tardes';
} else {
    $timeGreeting = 'Buenas noches';
}
?>

<!-- Floating Trigger Button (FAB) -->
<div id="romita-fab-container" class="romita-fab-container">
    <button type="button" id="romita-fab-btn" class="romita-fab-btn" onclick="toggleRomitaGlobalModal()" aria-label="Abrir Romita IA">
        <span class="romita-fab-glow"></span>
        <div class="romita-fab-avatar">
            <img src="assets/img/romita-avatar.png" alt="Romita" class="romita-avatar-img">
        </div>
        <span class="romita-fab-pulse"></span>
        <span class="romita-fab-tooltip">
            <span>Pregúntale a Romita</span>
            <kbd>R</kbd>
        </span>
    </button>
</div>

<!-- Global Modal Overlay / Command Palette Spotlight -->
<div id="romita-global-overlay" class="romita-global-overlay" style="display: none;" onclick="handleRomitaOverlayClick(event)">
    <div id="romita-global-dialog" class="romita-global-dialog" onclick="event.stopPropagation()">
        
        <!-- App Header (Native macOS / Modern SaaS style) -->
        <div class="rg-header">
            <div class="rg-header-left">
                <div class="rg-avatar-badge">
                    <img src="assets/img/romita-avatar.png" alt="Romita" class="romita-avatar-img">
                </div>
                <div class="rg-meta">
                    <div class="rg-name-row">
                        <span class="rg-title">Romita</span>
                        <span class="rg-version-tag">AI</span>
                        <span class="rg-online-badge">
                            <span class="rg-pulse-dot"></span>
                            <span>En línea</span>
                        </span>
                    </div>
                    <div class="rg-screen-pill" id="romita-screen-pill">
                        <i class="ph ph-browsers"></i>
                        <span>Detectando contexto...</span>
                    </div>
                </div>
            </div>

            <div class="rg-header-actions">
                <button type="button" class="rg-icon-btn" id="btn-romita-toggle-layout" onclick="toggleRomitaLayoutMode()" title="Alternar panel lateral / centrado">
                    <i class="ph ph-sidebar-simple" id="rg-layout-icon"></i>
                </button>
                <button type="button" class="rg-icon-btn" onclick="clearRomitaCurrentChat()" title="Nueva conversación">
                    <i class="ph ph-plus"></i>
                </button>
                <button type="button" class="rg-icon-btn rg-close-btn" onclick="closeRomitaGlobalModal()" title="Cerrar (Esc)">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>

        <!-- Chat Scroll Area -->
        <div class="rg-chat-body" id="romita-chat-messages">
            <!-- Starter Welcome & Suggestions (Estilo Imagen 1: Avatar circular con halo + Tipografía limpia + 4 Cards con icono abajo) -->
            <div id="romita-welcome-view" class="rg-welcome-view">
                <div class="rg-welcome-bot-circle">
                    <div class="rg-bot-circle-glow"></div>
                    <img src="assets/img/romita-avatar.png" alt="Romita" class="rg-bot-circle-img">
                </div>
                <div class="rg-welcome-heading-wrap">
                    <h2 class="rg-welcome-greeting">¡<?= $timeGreeting ?>, <?= htmlspecialchars($romitaFirstName) ?>!</h2>
                    <h1 class="rg-welcome-main-question">¿En qué <span class="rg-accent-text">colaboramos hoy?</span></h1>
                </div>
                <p class="rg-welcome-subtitle" id="romita-welcome-desc">
                    Asistente de inteligencia conectada a Calendarios, Marcas, Web, Audiovisual y Pizarras.
                </p>

                <div class="rg-examples-section">
                    <div class="rg-examples-label">SUGERENCIAS PARA COMENZAR</div>
                    <div class="rg-examples-grid" id="romita-welcome-grid">
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('¿Qué proyectos tenemos activos actualmente en la agencia?')">
                            <span class="rg-example-text">¿Qué proyectos tenemos activos actualmente en la agencia?</span>
                            <span class="rg-example-icon"><i class="ph ph-kanban"></i></span>
                        </button>
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('Dame 3 ideas creativas de contenido con gancho para las marcas de este mes')">
                            <span class="rg-example-text">Dame 3 ideas creativas de contenido con gancho para las marcas de este mes</span>
                            <span class="rg-example-icon"><i class="ph ph-lightbulb"></i></span>
                        </button>
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('¿Cómo podemos estructurar un embudo de ventas TOFU-MOFU-BOFU de alta conversión?')">
                            <span class="rg-example-text">¿Cómo podemos estructurar un embudo de ventas TOFU-MOFU-BOFU de alta conversión?</span>
                            <span class="rg-example-icon"><i class="ph ph-funnel"></i></span>
                        </button>
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('Revisa las mejores prácticas de SEO para optimizar las páginas de servicios')">
                            <span class="rg-example-text">Revisa las mejores prácticas de SEO para optimizar las páginas de servicios</span>
                            <span class="rg-example-icon"><i class="ph ph-chart-line-up"></i></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Bar (Estilo Imagen 2 y 3: Input box con selector de especialidad y menú emergente) -->
        <div class="rg-footer">
            <div class="rg-input-box" id="romita-chat-input-box">
                <!-- Slash Commands Autocomplete Menu -->
                <div class="rg-slash-menu" id="rg-slash-menu" style="display:none;" onclick="event.stopPropagation()">
                    <div class="rg-slash-header">
                        <span class="rg-slash-title"><i class="ph-bold ph-lightning"></i> Comandos Rápidos</span>
                        <span class="rg-slash-tip"><kbd>↑</kbd> <kbd>↓</kbd> navegar <kbd>Enter</kbd> seleccionar <kbd>Esc</kbd> cerrar</span>
                    </div>
                    <div class="rg-slash-items" id="rg-slash-items">
                        <button type="button" class="rg-slash-item active" data-cmd="/tarea" data-prompt="Estructura un plan de acción con tareas concretas para el Kanban del proyecto actual..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#2563eb;"><i class="ph-bold ph-kanban"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/tarea</span>
                                <span class="rg-slash-desc">Planificar entregables y crear tareas para el Kanban con 1 clic</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/reunion" data-prompt="Diseña una agenda ejecutiva y estructura de minuta para una reunión de alineación con el cliente..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#7c3aed;"><i class="ph-bold ph-calendar-plus"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/reunion</span>
                                <span class="rg-slash-desc">Agendar reunión de trabajo y generar estructura de minuta</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/whatsapp" data-prompt="Redacta un mensaje persuasivo y cordial para enviar por WhatsApp al cliente resumiendo..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#16a34a;"><i class="ph-bold ph-whatsapp-logo"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/whatsapp</span>
                                <span class="rg-slash-desc">Redactar mensaje o minuta lista para enviar por WhatsApp</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/brief" data-prompt="Inicia una entrevista guiada paso a paso para levantar los requerimientos del brief..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#f59e0b;"><i class="ph-bold ph-notepad"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/brief</span>
                                <span class="rg-slash-desc">Levantamiento de brief conversacional guiado paso a paso</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/auditoria" data-prompt="Realiza una auditoría completa de calidad (QA) y checklist técnico antes de la entrega..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#ec4899;"><i class="ph-bold ph-shield-check"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/auditoria</span>
                                <span class="rg-slash-desc">Checklist de control de calidad y revisión pre-entrega</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/campaña" data-prompt="Estructura una campaña publicitaria en Meta Ads con embudo TOFU-MOFU-BOFU..." onclick="selectRomitaSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#06b6d4;"><i class="ph-bold ph-funnel"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/campaña</span>
                                <span class="rg-slash-desc">Estructurar campaña de pauta con ganchos y públicos</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="rg-input-top-row">
                    <span class="rg-input-sparkle"><i class="ph ph-sparkle"></i></span>
                    <textarea id="romita-chat-input" class="rg-textarea" rows="1" placeholder="Pregúntale a Romita, escribe una solicitud o usa / para comandos..." onkeydown="handleRomitaInputKeydown(event)" oninput="handleRomitaInputChanged(this)"></textarea>
                </div>
                <!-- In-Composer Thinking / Generating Indicator Row -->
                <div class="rg-input-generating-row" id="rg-input-generating-row">
                    <div class="rg-thinking-header">
                        <div class="rg-thinking-sparkle-pill">
                            <i class="ph-bold ph-sparkle"></i>
                        </div>
                        <span id="rg-neural-status-text" class="rg-thinking-status-text">Romita está procesando el contexto...</span>
                        <div class="rg-thinking-waveform" aria-hidden="true">
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                        </div>
                    </div>
                    <div class="rg-thinking-laser-track">
                        <div class="rg-thinking-laser-bar"></div>
                    </div>
                </div>
                <div class="rg-input-toolbar">
                    <div class="rg-toolbar-left">
                        <div class="rg-context-pill-composer" id="romita-screen-pill-composer" title="Contexto activo de pantalla">
                            <i class="ph ph-browsers"></i>
                            <span id="rg-composer-context-label">Detectando...</span>
                        </div>
                        <div class="rg-specialty-picker-wrap">
                            <button type="button" class="rg-specialty-pill-btn" id="rg-specialty-trigger-btn" onclick="toggleRomitaSpecialtyMenu(event)" aria-expanded="false" title="Cambiar especialidad de Romita">
                                <i class="ph ph-compass" id="rg-trigger-icon"></i>
                                <span id="rg-trigger-label">Directora 360°</span>
                                <i class="ph-bold ph-caret-down rg-caret"></i>
                            </button>
                            <!-- Menú Emergente de Especialidades (Estilo Imagen 3) -->
                            <div class="rg-specialties-popover" id="rg-specialties-popover" style="display:none;" onclick="event.stopPropagation()">
                                <button type="button" class="rg-popover-item active" data-spec="director_360" onclick="selectRomitaSpecialty('director_360', 'Directora 360°', 'ph-compass')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-compass"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Directora 360°</span>
                                        <span class="rg-popover-item-desc">Visión integral y coordinación de proyectos</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="community_manager" onclick="selectRomitaSpecialty('community_manager', 'Senior CM', 'ph-chat-circle-dots')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-chat-circle-dots"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Senior CM</span>
                                        <span class="rg-popover-item-desc">Copywriting, redes y tono de voz</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="branding" onclick="selectRomitaSpecialty('branding', 'Branding', 'ph-palette')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-palette"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Branding</span>
                                        <span class="rg-popover-item-desc">Manual de marca y dirección visual</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="marketing" onclick="selectRomitaSpecialty('marketing', 'Marketing & Growth', 'ph-trend-up')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-trend-up"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Marketing & Growth</span>
                                        <span class="rg-popover-item-desc">Embudos, pauta digital y conversión</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="seo" onclick="selectRomitaSpecialty('seo', 'Especialista SEO', 'ph-magnifying-glass')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-magnifying-glass"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Especialista SEO</span>
                                        <span class="rg-popover-item-desc">Keywords, indexación y optimización web</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="rg-toolbar-right">
                        <div class="rg-citation-badge" title="Inteligencia conectada a la base de datos de Roma Agencia">
                            <span class="rg-citation-dot"></span>
                            <span>DB Roma</span>
                        </div>
                        <button type="button" id="btn-romita-mic" class="rg-mic-btn" onclick="toggleRomitaVoiceRecognition()" title="Dictar por voz a Romita (Español)">
                            <i class="ph ph-microphone"></i>
                        </button>
                        <button type="button" id="btn-romita-send" class="rg-send-btn-round" onclick="sendRomitaMessage()" aria-label="Enviar mensaje">
                            <i class="ph-bold ph-arrow-up"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="rg-footer-hints">
                <span class="rg-hint"><kbd>Enter</kbd> Enviar</span>
                <span class="rg-hint"><kbd>Shift + Enter</kbd> Salto de línea</span>
                <span class="rg-hint"><kbd>R</kbd> Abrir</span>
                <span class="rg-hint"><kbd>Esc</kbd> Ocultar</span>
            </div>
        </div>

    </div>
</div>

<style>
/* ==========================================================================
   ROMITA GLOBAL FLOATING ASSISTANT & COMMAND PALETTE (MODERN NATIVE APP STYLE)
   ========================================================================== */

/* Floating Action Button (FAB) */
.romita-fab-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 990;
    pointer-events: auto;
    transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s;
}

/* Ocultar la burbuja automáticamente si hay cualquier modal o ventana superpuesta abierta */
body:has(.modal-overlay.active) .romita-fab-container,
body:has(#post-modal.active) .romita-fab-container,
body:has(.modal.show) .romita-fab-container,
body:has(.modal.in) .romita-fab-container,
body:has(.swal2-container) .romita-fab-container,
body.modal-open .romita-fab-container,
body.romita-modal-open .romita-fab-container,
body.swal2-shown .romita-fab-container,
body.has-active-modal .romita-fab-container,
.modal-overlay.active ~ * .romita-fab-container,
#romita-global-overlay:not([style*="display: none"]):not([style*="display:none"]) ~ * .romita-fab-container,
.romita-fab-container.is-hidden-by-modal {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    transform: scale(0.6) translateY(24px) !important;
}

.romita-fab-btn {
    position: relative;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #0a0f1d;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    outline: none;
    box-shadow: 0 10px 28px -6px rgba(29, 78, 216, 0.5);
}

.romita-fab-btn:hover {
    transform: scale(1.06) translateY(-2px);
    box-shadow: 0 16px 36px -6px rgba(29, 78, 216, 0.65);
}

.romita-fab-btn:active {
    transform: scale(0.96);
}

.romita-fab-glow {
    position: absolute;
    inset: -2px;
    border-radius: 18px;
    background: linear-gradient(135deg, #1d4ed8, #0a0f1d 40%, #38bdf8);
    filter: blur(8px);
    opacity: 0.55;
    transition: opacity 0.3s ease;
    animation: romitaGlowPulse 3s infinite alternate;
}

.romita-fab-btn:hover .romita-fab-glow {
    opacity: 0.85;
    filter: blur(12px);
}

@keyframes romitaGlowPulse {
    0% { transform: scale(0.98); opacity: 0.45; }
    100% { transform: scale(1.05); opacity: 0.8; }
}

.romita-fab-avatar {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 15px;
    background: #0a0f1d;
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    z-index: 2;
}

.romita-fab-avatar img.romita-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 15px;
    display: block;
}

.romita-fab-pulse {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #0f172a;
    z-index: 3;
    box-shadow: 0 0 8px #10b981;
}

/* Tooltip on hover */
.romita-fab-tooltip {
    position: absolute;
    right: calc(100% + 12px);
    top: 50%;
    transform: translateY(-50%) translateX(6px);
    background: #0f172a;
    color: #f1f5f9;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.romita-fab-tooltip kbd {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 0.68rem;
    font-family: inherit;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.romita-fab-btn:hover .romita-fab-tooltip {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}

/* Modal Overlay */
.romita-global-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 10, 15, 0.72);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    z-index: 99995;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    animation: rgFadeIn 0.2s ease-out;
}

@keyframes rgFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Dialog - Spotlight Mode (App Native Window) */
.romita-global-dialog {
    position: relative;
    width: 940px;
    max-width: 95vw;
    height: 90vh;
    max-height: 92vh;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 25px 65px -15px rgba(0, 0, 0, 0.25), 0 0 1px 1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: rgScaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1), height 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .romita-global-dialog {
    background: #111116;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 30px 80px -15px rgba(0, 0, 0, 0.85), 0 0 0 1px rgba(255, 255, 255, 0.06), 0 0 60px rgba(99, 102, 241, 0.08);
}

@keyframes rgScaleUp {
    from { transform: scale(0.97) translateY(10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

/* Drawer Mode (Snapped to right side) */
.romita-global-dialog.drawer-mode {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 520px;
    max-width: 100vw;
    height: 100vh !important;
    max-height: 100vh !important;
    border-radius: 0;
    border-right: none;
    border-top: none;
    border-bottom: none;
    animation: rgSlideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.romita-global-dialog.drawer-mode .rg-chat-body {
    padding: 16px 14px;
    overflow-x: hidden !important;
}

.romita-global-dialog.drawer-mode .rg-examples-grid,
.romita-global-dialog.drawer-mode .rg-suggestions-grid {
    grid-template-columns: 1fr !important;
    max-width: 100% !important;
    gap: 8px;
}

@keyframes rgSlideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

/* Header */
.rg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    flex-shrink: 0;
}

[data-theme="dark"] .rg-header {
    background: #14141a;
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.rg-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rg-avatar-badge {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #0a0f1d;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 10px rgba(29, 78, 216, 0.35);
    border: 1px solid rgba(56, 189, 248, 0.25);
    overflow: hidden;
    flex-shrink: 0;
}

.rg-avatar-badge img.romita-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
    display: block;
}

.rg-meta {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.rg-name-row {
    display: flex;
    align-items: center;
    gap: 7px;
}

.rg-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .rg-title {
    color: #f8fafc;
}

.rg-version-tag {
    font-size: 0.65rem;
    font-weight: 700;
    color: #2563eb;
    background: rgba(37, 99, 235, 0.1);
    border: 1px solid rgba(37, 99, 235, 0.25);
    padding: 1px 5px;
    border-radius: 5px;
    line-height: 1;
    letter-spacing: 0.04em;
}

[data-theme="dark"] .rg-version-tag {
    color: #60a5fa;
    background: rgba(37, 99, 235, 0.2);
    border-color: rgba(56, 189, 248, 0.35);
}

.rg-online-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.66rem;
    font-weight: 600;
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    padding: 2px 7px;
    border-radius: 9999px;
}

.rg-pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
}

.rg-screen-pill {
    font-size: 0.72rem;
    font-weight: 500;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 8px;
    border-radius: 6px;
    max-width: 360px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

[data-theme="dark"] .rg-screen-pill {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

.rg-screen-pill i {
    color: #2563eb;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.rg-header-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.rg-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    cursor: pointer;
    transition: all 0.18s ease;
}

.rg-icon-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #e2e8f0;
}

[data-theme="dark"] .rg-icon-btn {
    color: #94a3b8;
}

[data-theme="dark"] .rg-icon-btn:hover {
    background: rgba(255, 255, 255, 0.07);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.08);
}

.rg-close-btn:hover {
    background: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
}

/* Chat Scroll Feed */
.rg-chat-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden !important;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scroll-behavior: smooth;
}

.rg-chat-body::-webkit-scrollbar {
    width: 5px;
}
.rg-chat-body::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 9999px;
}
[data-theme="dark"] .rg-chat-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.12);
}

/* ==========================================================================
   WELCOME HERO VIEW (Estilo Imagen 1: Bot circular con halo + Tipografía limpia + 4 Cards)
   ========================================================================== */
.rg-welcome-view {
    margin: auto 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem 0.5rem 1rem 0.5rem;
    gap: 0.5rem;
    animation: rgWelcomeFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rgWelcomeFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 1. Bot Avatar Circular con Halo Azul/Celeste */
.rg-welcome-bot-circle {
    position: relative;
    width: 76px;
    height: 76px;
    margin: 0 auto 8px auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rg-bot-circle-glow {
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(0, 168, 255, 0.45) 0%, rgba(14, 165, 233, 0.2) 50%, transparent 75%);
    filter: blur(14px);
    pointer-events: none;
    animation: rgBotHaloPulse 3.5s infinite alternate ease-in-out;
}

@keyframes rgBotHaloPulse {
    0% { transform: scale(0.92); opacity: 0.55; }
    100% { transform: scale(1.15); opacity: 0.95; filter: blur(18px); }
}

.rg-bot-circle-img {
    position: relative;
    width: 68px;
    height: 68px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(56, 189, 248, 0.45);
    box-shadow: 0 8px 24px rgba(10, 15, 29, 0.45), 0 0 14px rgba(0, 168, 255, 0.35);
    background: #0a0f1d;
    z-index: 2;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
}

.rg-welcome-bot-circle:hover .rg-bot-circle-img {
    transform: scale(1.06);
    box-shadow: 0 12px 28px rgba(10, 15, 29, 0.6), 0 0 20px rgba(56, 189, 248, 0.5);
}

/* 2. Heading Typography */
.rg-welcome-heading-wrap {
    text-align: center;
    margin-bottom: 2px;
}

.rg-welcome-greeting {
    margin: 0;
    font-size: 1.32rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .rg-welcome-greeting {
    color: #f1f5f9;
}

.rg-welcome-main-question {
    margin: 4px 0 0 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.025em;
}

[data-theme="dark"] .rg-welcome-main-question {
    color: #f8fafc;
}

.rg-accent-text {
    background: linear-gradient(135deg, #2563eb 0%, #0284c7 50%, #38bdf8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.rg-welcome-subtitle {
    margin: 0;
    max-width: 520px;
    font-size: 0.84rem;
    color: #64748b;
    line-height: 1.5;
}

[data-theme="dark"] .rg-welcome-subtitle {
    color: #94a3b8;
}

/* 3. Sugerencias para comenzar (4 Cards: Texto arriba, Icono abajo - Imagen 1) */
.rg-examples-section {
    width: 100%;
    max-width: 820px;
    margin: 1.25rem auto 0 auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rg-examples-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #94a3b8;
    text-transform: uppercase;
    text-align: left;
    padding-left: 2px;
}

[data-theme="dark"] .rg-examples-label {
    color: #64748b;
}

.rg-examples-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    width: 100%;
}

@media (max-width: 768px) {
    .rg-examples-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 500px) {
    .rg-examples-grid {
        grid-template-columns: 1fr;
    }
}

.rg-example-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 110px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    text-align: left;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

[data-theme="dark"] .rg-example-card {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.07);
}

.rg-example-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .rg-example-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(56, 189, 248, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}

.rg-example-text {
    font-size: 0.81rem;
    font-weight: 500;
    line-height: 1.45;
    color: #1e293b;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

[data-theme="dark"] .rg-example-text {
    color: #e2e8f0;
}

.rg-example-icon {
    font-size: 1.15rem;
    color: #64748b;
    margin-top: 12px;
    display: flex;
    align-items: center;
    transition: color 0.2s ease, transform 0.2s ease;
}

[data-theme="dark"] .rg-example-icon {
    color: #94a3b8;
}

.rg-example-card:hover .rg-example-icon {
    color: #2563eb;
    transform: scale(1.1);
}

[data-theme="dark"] .rg-example-card:hover .rg-example-icon {
    color: #38bdf8;
}

/* Compatibilidad transicional con estructura antigua de tarjetas */
.rg-suggestions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    width: 100%;
}
.rg-suggestion-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 110px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
[data-theme="dark"] .rg-suggestion-card {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.07);
}
.rg-suggestion-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    transform: translateY(-2px);
}
[data-theme="dark"] .rg-suggestion-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(56, 189, 248, 0.4);
}
.rg-card-icon {
    font-size: 1.15rem;
    color: #64748b;
    margin-top: 10px;
    display: flex;
    align-items: center;
}
.rg-card-text strong {
    font-size: 0.81rem;
    font-weight: 500;
    line-height: 1.45;
    color: #1e293b;
    display: block;
}
[data-theme="dark"] .rg-card-text strong { color: #f1f5f9; }
.rg-card-text small {
    display: none;
}

/* Chat Messages */
.rg-msg {
    display: flex;
    gap: 12px;
    animation: rgMsgIn 0.22s ease-out;
}

@keyframes rgMsgIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.rg-msg-user {
    align-self: flex-end;
    flex-direction: row-reverse;
    max-width: 85%;
}

.rg-msg-ai {
    align-self: flex-start;
    width: 100%;
    max-width: 100%;
}

.rg-msg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    overflow: hidden;
}

.rg-msg-ai .rg-msg-avatar {
    background: #0a0f1d;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(29, 78, 216, 0.35);
    border: 1px solid rgba(56, 189, 248, 0.25);
}

.rg-msg-avatar img.rg-msg-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
    display: block;
}

.rg-msg-user .rg-msg-avatar {
    background: #0f172a;
    color: #ffffff;
}

[data-theme="dark"] .rg-msg-user .rg-msg-avatar {
    background: #1e293b;
}

.rg-msg-content-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    max-width: 100%;
    flex: 1;
}

.rg-msg-bubble {
    padding: 12px 18px;
    border-radius: 16px;
    font-size: 0.86rem;
    line-height: 1.55;
    word-break: break-word;
    box-sizing: border-box;
    max-width: 100%;
    min-width: 0;
}

.rg-msg-user .rg-msg-bubble {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 2px 10px rgba(29, 78, 216, 0.25);
}

.rg-msg-ai .rg-msg-bubble {
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

[data-theme="dark"] .rg-msg-ai .rg-msg-bubble {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
    box-shadow: none;
}

/* Action button under AI message (Copy, etc.) */
.rg-msg-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-left: 2px;
}

.rg-copy-bubble-btn {
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 0.72rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.rg-copy-bubble-btn:hover {
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .rg-copy-bubble-btn:hover {
    color: #818cf8;
}

/* Markdown styling inside AI bubble */
.rg-msg-bubble p { margin: 0 0 8px 0; }
.rg-msg-bubble p:last-child { margin-bottom: 0; }
.rg-msg-bubble ul, .rg-msg-bubble ol { margin: 4px 0 8px 18px; padding: 0; }
.rg-msg-bubble li { margin-bottom: 4px; }
.rg-msg-bubble strong { font-weight: 700; color: inherit; }
.rg-msg-bubble pre, .rg-msg-bubble code {
    background: rgba(0, 0, 0, 0.08);
    padding: 2px 6px;
    border-radius: 5px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82rem;
}
[data-theme="dark"] .rg-msg-bubble pre, [data-theme="dark"] .rg-msg-bubble code {
    background: rgba(0, 0, 0, 0.4);
}
.rg-msg-bubble pre {
    padding: 12px;
    overflow-x: auto;
    margin: 8px 0;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

/* Code Blocks Modern Styling */
.code-block-wrapper {
    margin: 10px 0;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
}
[data-theme="dark"] .code-block-wrapper {
    border-color: rgba(255, 255, 255, 0.08);
}
.code-block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e1f2f;
    color: #94a3b8;
    padding: 5px 12px;
    font-size: 0.72rem;
    font-weight: 600;
}
.btn-copy-code {
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.72rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.btn-copy-code:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

/* --- TABLAS MODERNAS RESPONSIVAS Y CON TOOLBAR EN ROMITA MODAL --- */
.romita-table-container {
    margin: 14px 0;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    transition: all 0.25s ease;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
[data-theme="dark"] .romita-table-container {
    border-color: rgba(255, 255, 255, 0.08);
    background: #161726;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);
}
.romita-table-container:hover {
    border-color: rgba(99, 102, 241, 0.35);
}
.romita-table-container.is-fullscreen {
    position: fixed;
    top: 1.5rem;
    left: 1.5rem;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 99999999;
    margin: 0;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.7);
    background: #ffffff;
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    border: 1px solid rgba(99, 102, 241, 0.3);
}
[data-theme="dark"] .romita-table-container.is-fullscreen {
    background: #11121d;
    border-color: rgba(99, 102, 241, 0.4);
}
.romita-table-container.is-fullscreen .table-responsive-wrapper {
    max-height: none !important;
    flex: 1;
    height: 100%;
}
.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.76rem;
    gap: 0.6rem;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
}
[data-theme="dark"] .table-toolbar {
    background: #1c1d2e;
    border-color: rgba(255, 255, 255, 0.08);
}
.table-toolbar-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    flex-wrap: wrap;
}
[data-theme="dark"] .table-toolbar-left {
    color: #94a3b8;
}
.table-info-badge {
    background: #ffffff;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    color: #334155;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
[data-theme="dark"] .table-info-badge {
    background: #141522;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}
.table-scroll-hint {
    font-size: 0.72rem;
    color: #6366f1;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(99, 102, 241, 0.08);
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
}
[data-theme="dark"] .table-scroll-hint {
    color: #a5b4fc;
    background: rgba(99, 102, 241, 0.18);
}
.table-scroll-hint i {
    animation: tableHintNudge 1.6s ease-in-out infinite;
}
@keyframes tableHintNudge {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(3px); }
}
.table-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: auto;
    flex-shrink: 0;
}
.btn-table-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.73rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.btn-table-action .btn-text-short {
    display: none;
}
.btn-table-action .btn-text-full {
    display: inline;
}
[data-theme="dark"] .btn-table-action {
    background: #25273c;
    border-color: rgba(255, 255, 255, 0.12);
    color: #cbd5e1;
}
.btn-table-action:hover {
    border-color: #6366f1;
    color: #4f46e5;
    background: #f1f5f9;
}
[data-theme="dark"] .btn-table-action:hover {
    border-color: #818cf8;
    color: #ffffff;
    background: rgba(99, 102, 241, 0.2);
}
.table-responsive-wrapper {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 520px;
    width: 100%;
    position: relative;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(99, 102, 241, 0.4) rgba(0, 0, 0, 0.04);
}
.table-responsive-wrapper::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.table-responsive-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.04);
}
[data-theme="dark"] .table-responsive-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.25);
}
.table-responsive-wrapper::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.4);
    border-radius: 6px;
}
.table-responsive-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.7);
}
.romita-table-container table,
.rg-msg-bubble table {
    width: 100% !important;
    min-width: 100% !important;
    border-collapse: collapse !important;
    border-spacing: 0 !important;
    font-size: 0.82rem;
    text-align: left;
    margin: 0 !important;
    table-layout: auto !important;
}
.romita-table-container thead,
.rg-msg-bubble thead {
    position: sticky;
    top: 0;
    z-index: 10;
}
.romita-table-container th,
.rg-msg-bubble th {
    padding: 10px 14px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #1d4ed8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: #f1f5f9;
    border-bottom: 2px solid #e2e8f0;
    border-right: 1px solid rgba(0, 0, 0, 0.05);
    white-space: nowrap;
    user-select: none;
    transition: background 0.15s ease, color 0.15s ease;
    vertical-align: middle;
}
[data-theme="dark"] .romita-table-container th,
[data-theme="dark"] .rg-msg-bubble th {
    color: #60a5fa;
    background: #181926;
    border-bottom-color: rgba(37, 99, 235, 0.35);
    border-right-color: rgba(255, 255, 255, 0.05);
}
.romita-table-container th:last-child,
.rg-msg-bubble th:last-child {
    border-right: none;
}
.romita-table-container th:hover,
.rg-msg-bubble th:hover {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}
[data-theme="dark"] .romita-table-container th:hover,
[data-theme="dark"] .rg-msg-bubble th:hover {
    background: rgba(37, 99, 235, 0.25);
    color: #93c5fd;
}
.romita-table-container td,
.rg-msg-bubble td {
    padding: 10px 14px;
    font-size: 0.81rem;
    color: inherit;
    border-bottom: 1px solid #f1f5f9;
    border-right: 1px solid rgba(0, 0, 0, 0.04);
    line-height: 1.55;
    vertical-align: top;
    background: transparent;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    min-width: 140px;
}
[data-theme="dark"] .romita-table-container td,
[data-theme="dark"] .rg-msg-bubble td {
    border-bottom-color: rgba(255, 255, 255, 0.04);
    border-right-color: rgba(255, 255, 255, 0.03);
}
.romita-table-container tr:last-child td,
.rg-msg-bubble tr:last-child td {
    border-bottom: none;
}
.romita-table-container td:last-child,
.rg-msg-bubble td:last-child {
    border-right: none;
}
.romita-table-container tbody tr:hover td,
.rg-msg-bubble tbody tr:hover td {
    background: rgba(37, 99, 235, 0.035);
}
[data-theme="dark"] .romita-table-container tbody tr:hover td,
[data-theme="dark"] .rg-msg-bubble tbody tr:hover td {
    background: rgba(37, 99, 235, 0.08);
}

/* Dynamic Minimum Column Widths - ZERO OVERLAP */
.romita-table-container th.col-compact,
.romita-table-container td.col-compact {
    min-width: 120px;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}
.romita-table-container th.col-medium,
.romita-table-container td.col-medium {
    min-width: 190px;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}
.romita-table-container th.col-wide,
.romita-table-container td.col-wide {
    min-width: 280px;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    line-height: 1.55;
}

/* ==========================================================================
   FUTURISTIC AI GENERATING ANIMATIONS (NEURAL CORE & LASER SCAN)
   ========================================================================== */

/* 1. Header & Dialog Laser Scans - Desactivados (Sin iluminación en divisores) */
.rg-header-laser,
.rg-dialog-laser {
    display: none !important;
    opacity: 0 !important;
    height: 0 !important;
    pointer-events: none !important;
    visibility: hidden !important;
}

/* 2. Dialog Animated Perimeter Aura while Generating (Azul con negro y tonos celestes) */
.romita-global-dialog.is-generating {
    border-color: rgba(37, 99, 235, 0.65) !important;
    animation: rgModalPerimeterAura 2.2s infinite alternate ease-in-out !important;
}

@keyframes rgModalPerimeterAura {
    0% {
        box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.75),
                    0 0 22px rgba(29, 78, 216, 0.45),
                    0 0 45px rgba(56, 189, 248, 0.2),
                    0 30px 80px -10px rgba(10, 15, 29, 0.85);
    }
    50% {
        box-shadow: 0 0 0 2.5px rgba(56, 189, 248, 0.85),
                    0 0 28px rgba(56, 189, 248, 0.4),
                    0 0 55px rgba(29, 78, 216, 0.35),
                    0 32px 85px -10px rgba(10, 15, 29, 0.9);
    }
    100% {
        box-shadow: 0 0 0 2px rgba(11, 19, 43, 0.9),
                    0 0 24px rgba(29, 78, 216, 0.5),
                    0 0 50px rgba(56, 189, 248, 0.18),
                    0 34px 90px -10px rgba(10, 15, 29, 0.95);
    }
}

/* 3. Holographic Chromatic Avatar Aura (Azul, Negro y Celeste) */
.rg-avatar-generating {
    position: relative;
    z-index: 2;
}

.rg-avatar-generating::before {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 12px;
    background: conic-gradient(from 0deg, #1d4ed8 0%, #0a0f1d 25%, #2563eb 50%, #38bdf8 75%, #1d4ed8 100%);
    animation: rgChromaticSpin 2s linear infinite;
    z-index: -1;
    filter: blur(3px);
    opacity: 0.95;
}

@keyframes rgChromaticSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 4. Modern Executive AI Thinking Indicator */
.rg-thinking-bubble,
.rg-neural-bubble {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(240, 246, 255, 0.92) 100%) !important;
    border: 1px solid rgba(37, 99, 235, 0.22) !important;
    border-radius: 16px !important;
    padding: 12px 16px 10px 16px !important;
    box-shadow: 0 4px 20px -2px rgba(10, 15, 29, 0.07), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
    width: fit-content !important;
    min-width: 260px !important;
    max-width: 440px !important;
    box-sizing: border-box !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: all 0.25s ease;
}

[data-theme="dark"] .rg-thinking-bubble,
[data-theme="dark"] .rg-neural-bubble {
    background: linear-gradient(135deg, rgba(10, 15, 29, 0.88) 0%, rgba(15, 23, 42, 0.92) 100%) !important;
    border-color: rgba(56, 189, 248, 0.25) !important;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45), 0 0 20px rgba(29, 78, 216, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.05) !important;
}

.rg-thinking-header {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.rg-thinking-sparkle-pill {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: linear-gradient(135deg, #0a0f1d 0%, #1e40af 100%);
    color: #38bdf8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
    border: 1px solid rgba(56, 189, 248, 0.3);
    position: relative;
    overflow: hidden;
}

.rg-thinking-sparkle-pill i {
    animation: rgSparkleRotate 3.5s ease-in-out infinite;
}

@keyframes rgSparkleRotate {
    0% { transform: rotate(0deg) scale(0.9); }
    50% { transform: rotate(180deg) scale(1.1); filter: drop-shadow(0 0 4px #38bdf8); }
    100% { transform: rotate(360deg) scale(0.9); }
}

.rg-thinking-status-text,
.roc-status-msg {
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.35;
    flex: 1;
    min-width: 0;
    background: linear-gradient(90deg, #1d4ed8 0%, #38bdf8 40%, #0284c7 70%, #1d4ed8 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: rgTextShimmer 2.4s linear infinite;
    letter-spacing: -0.01em;
    transition: opacity 0.2s ease;
}

[data-theme="dark"] .rg-thinking-status-text,
[data-theme="dark"] .roc-status-msg {
    background: linear-gradient(90deg, #60a5fa 0%, #38bdf8 40%, #ffffff 70%, #60a5fa 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

@keyframes rgTextShimmer {
    0% { background-position: 200% center; }
    100% { background-position: -200% center; }
}

.rg-thinking-waveform {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    height: 16px;
    flex-shrink: 0;
    padding-left: 4px;
}

.rg-wave-bar {
    width: 3px;
    height: 6px;
    background: #2563eb;
    border-radius: 99px;
    animation: rgWaveEqualizer 1.1s ease-in-out infinite alternate;
}

[data-theme="dark"] .rg-wave-bar {
    background: #38bdf8;
    box-shadow: 0 0 6px rgba(56, 189, 248, 0.4);
}

.rg-wave-bar:nth-child(1) { animation-delay: 0.0s; height: 8px; }
.rg-wave-bar:nth-child(2) { animation-delay: 0.2s; height: 14px; }
.rg-wave-bar:nth-child(3) { animation-delay: 0.4s; height: 10px; }
.rg-wave-bar:nth-child(4) { animation-delay: 0.15s; height: 6px; }

@keyframes rgWaveEqualizer {
    0% { transform: scaleY(0.4); opacity: 0.5; }
    100% { transform: scaleY(1.3); opacity: 1; }
}

.rg-thinking-laser-track {
    width: 100%;
    height: 2.5px;
    border-radius: 99px;
    background: rgba(37, 99, 235, 0.1);
    margin-top: 9px;
    overflow: hidden;
    position: relative;
}

[data-theme="dark"] .rg-thinking-laser-track {
    background: rgba(255, 255, 255, 0.06);
}

.rg-thinking-laser-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 45%;
    border-radius: 99px;
    background: linear-gradient(90deg, transparent 0%, #1d4ed8 30%, #38bdf8 70%, transparent 100%);
    animation: rgLaserProgress 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    box-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

@keyframes rgLaserProgress {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(110%); }
    100% { transform: translateX(260%); }
}

/* Fallback & Legacy compatibility */
.romita-chat-orbital-card {
    display: none !important;
}

/* ==========================================================================
   ROMA ACTIONS: CARDS DE ACCIONES CON 1 CLIC (AGENTIC CARDS)
   ========================================================================== */
.romita-action-card {
    margin: 16px 0;
    border-radius: 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    width: 100%;
    box-sizing: border-box;
}

[data-theme="dark"] .romita-action-card {
    background: #141624;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
}

.romita-action-card:hover {
    border-color: rgba(37, 99, 235, 0.35);
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.08);
}

.rac-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}

[data-theme="dark"] .rac-header {
    background: #1a1d30;
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

.rac-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rac-icon-pill {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}

.rac-icon-kanban { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.rac-icon-meeting { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
.rac-icon-whatsapp { background: rgba(34, 197, 94, 0.12); color: #16a34a; }

[data-theme="dark"] .rac-icon-kanban { background: rgba(56, 189, 248, 0.18); color: #38bdf8; }
[data-theme="dark"] .rac-icon-meeting { background: rgba(168, 85, 247, 0.18); color: #c084fc; }
[data-theme="dark"] .rac-icon-whatsapp { background: rgba(34, 197, 94, 0.18); color: #4ade80; }

.rac-header-titles {
    display: flex;
    flex-direction: column;
}

.rac-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

[data-theme="dark"] .rac-title {
    color: #f8fafc;
}

.rac-sub {
    font-size: 0.72rem;
    color: #64748b;
}

[data-theme="dark"] .rac-sub {
    color: #94a3b8;
}

.rac-chip-status {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(37, 99, 235, 0.08);
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .rac-chip-status {
    background: rgba(56, 189, 248, 0.12);
    color: #38bdf8;
}

.rac-chip-wa {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
}

[data-theme="dark"] .rac-chip-wa {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.rac-body {
    padding: 12px 14px;
}

.rac-tasks-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rac-task-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

[data-theme="dark"] .rac-task-item {
    background: #18192a;
    border-color: rgba(255, 255, 255, 0.06);
}

.rac-task-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

[data-theme="dark"] .rac-task-item:hover {
    background: #1f2238;
}

.rac-task-check {
    width: 17px;
    height: 17px;
    margin-top: 2px;
    accent-color: #2563eb;
    cursor: pointer;
    flex-shrink: 0;
}

.rac-task-content {
    flex: 1;
    min-width: 0;
}

.rac-task-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.rac-task-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
}

[data-theme="dark"] .rac-task-title {
    color: #f1f5f9;
}

.rac-task-tags {
    display: flex;
    align-items: center;
    gap: 6px;
}

.rac-badge {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.rac-badge-urgent {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

[data-theme="dark"] .rac-badge-urgent {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.rac-badge-date {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
}

[data-theme="dark"] .rac-badge-date {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

.rac-task-desc {
    margin: 3px 0 0 0;
    font-size: 0.74rem;
    color: #64748b;
    line-height: 1.35;
}

[data-theme="dark"] .rac-task-desc {
    color: #94a3b8;
}

.rac-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 14px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
}

[data-theme="dark"] .rac-footer {
    background: #1a1d30;
    border-top-color: rgba(255, 255, 255, 0.05);
}

.btn-rac-execute {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 7px 14px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    transition: all 0.15s ease;
    text-decoration: none;
}

.btn-rac-execute:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
    color: #ffffff;
}

.btn-rac-meeting {
    background: #7c3aed;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
}

.btn-rac-meeting:hover {
    background: #6d28d9;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);
}

.btn-rac-wa {
    background: #16a34a;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
}

.btn-rac-wa:hover {
    background: #15803d;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
}

.btn-rac-copy {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 6px 12px;
    border-radius: 7px;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

[data-theme="dark"] .btn-rac-copy {
    background: #23263d;
    border-color: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
}

.btn-rac-copy:hover {
    border-color: #2563eb;
    color: #2563eb;
}

.rac-link-kanban {
    font-size: 0.74rem;
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.rac-link-kanban:hover {
    text-decoration: underline;
}

.rac-success-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.25);
    color: #16a34a;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    width: 100%;
    box-sizing: border-box;
}

[data-theme="dark"] .rac-success-banner {
    background: rgba(34, 197, 94, 0.18);
    color: #4ade80;
}

.rac-btn-view {
    margin-left: auto;
    background: #ffffff;
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 0.72rem;
    text-decoration: none;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .rac-btn-view {
    background: #161827;
    color: #4ade80;
}

.rac-meeting-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.rac-detail-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-size: 0.78rem;
}

.rac-detail-row .rac-label {
    color: #64748b;
    font-weight: 500;
    min-width: 85px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.rac-detail-row .rac-value {
    color: #1e293b;
    flex: 1;
}

[data-theme="dark"] .rac-detail-row .rac-value {
    color: #f1f5f9;
}

.rac-highlight-date {
    color: #7c3aed !important;
    font-weight: 700;
}

.rac-meet-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}

.rac-wa-balloon {
    background: #e7f8ee;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.79rem;
    line-height: 1.5;
    color: #14532d;
    max-height: 180px;
    overflow-y: auto;
}

[data-theme="dark"] .rac-wa-balloon {
    background: #0f2e1e;
    border-color: rgba(34, 197, 94, 0.25);
    color: #bbf7d0;
}

/* Slash Commands Popover Menu */
.rg-slash-menu {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 14px;
    right: 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
    z-index: 1000;
    overflow: hidden;
    animation: rgFadeIn 0.2s ease-out;
}

[data-theme="dark"] .rg-slash-menu {
    background: #161827;
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
}

.rg-slash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.72rem;
    color: #64748b;
}

[data-theme="dark"] .rg-slash-header {
    background: #1c1e30;
    border-bottom-color: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
}

.rg-slash-title {
    font-weight: 700;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .rg-slash-title {
    color: #38bdf8;
}

.rg-slash-tip kbd {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 0.68rem;
}

[data-theme="dark"] .rg-slash-tip kbd {
    background: #252840;
    border-color: rgba(255, 255, 255, 0.1);
}

.rg-slash-items {
    max-height: 240px;
    overflow-y: auto;
    padding: 4px;
}

.rg-slash-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 7px 10px;
    border-radius: 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s ease;
}

.rg-slash-item:hover,
.rg-slash-item.active {
    background: #f1f5f9;
}

[data-theme="dark"] .rg-slash-item:hover,
[data-theme="dark"] .rg-slash-item.active {
    background: #22263d;
}

.rg-slash-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.88rem;
    flex-shrink: 0;
}

.rg-slash-text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.rg-slash-name {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f172a;
}

[data-theme="dark"] .rg-slash-name {
    color: #f8fafc;
}

.rg-slash-desc {
    font-size: 0.71rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .rg-slash-desc {
    color: #94a3b8;
}

/* Microphone Button */
.rg-mic-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    flex-shrink: 0;
}

[data-theme="dark"] .rg-mic-btn {
    background: #1e2030;
    border-color: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

.rg-mic-btn:hover {
    color: #2563eb;
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.06);
}

[data-theme="dark"] .rg-mic-btn:hover {
    color: #38bdf8;
    border-color: #38bdf8;
}

.rg-mic-btn.is-recording {
    background: #ef4444 !important;
    border-color: #dc2626 !important;
    color: #ffffff !important;
    animation: rgMicPulse 1.4s infinite ease-in-out;
    box-shadow: 0 0 14px rgba(239, 68, 68, 0.5);
}

@keyframes rgMicPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
    50% { transform: scale(1.08); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}

/* 8. Input Box Scanning State & In-Composer Generating Indicator */
.rg-input-generating-row {
    display: none;
    flex-direction: column;
    width: 100%;
    padding: 3px 0 6px 0;
    gap: 8px;
    animation: rgFadeIn 0.22s ease-out;
}

.rg-input-box.is-generating .rg-input-top-row {
    display: none !important;
}

.rg-input-box.is-generating .rg-input-generating-row {
    display: flex !important;
}

@keyframes rgFadeIn {
    from { opacity: 0; transform: translateY(3px); }
    to { opacity: 1; transform: translateY(0); }
}

.rg-input-box.is-generating {
    border-color: rgba(29, 78, 216, 0.5) !important;
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.18), 0 0 16px rgba(56, 189, 248, 0.2) !important;
    animation: rgInputScan 2.5s infinite alternate ease-in-out;
}

@keyframes rgInputScan {
    0% { border-color: rgba(29, 78, 216, 0.35); box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.12); }
    100% { border-color: rgba(56, 189, 248, 0.7); box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.25), 0 0 20px rgba(56, 189, 248, 0.25); }
}

/* Footer & Input Bar */
.rg-footer {
    padding: 12px 18px 14px 18px;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-shrink: 0;
}

[data-theme="dark"] .rg-footer {
    background: #14141a;
    border-top-color: rgba(255, 255, 255, 0.06);
}

.rg-footer {
    padding: 12px 18px 14px 18px;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-shrink: 0;
    position: relative;
    overflow: visible !important;
}

[data-theme="dark"] .rg-footer {
    background: #14141a;
    border-top-color: rgba(255, 255, 255, 0.06);
}

.rg-input-box {
    position: relative;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 12px 14px 10px 14px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    overflow: visible !important;
}

[data-theme="dark"] .rg-input-box {
    background: #14151b;
    border-color: rgba(255, 255, 255, 0.09);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.45);
}

.rg-input-box:focus-within {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15), 0 6px 24px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .rg-input-box:focus-within {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25), 0 8px 30px rgba(0, 0, 0, 0.6);
}

.rg-input-top-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    width: 100%;
}

.rg-input-sparkle {
    font-size: 1.05rem;
    color: #2563eb;
    margin-top: 1px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .rg-input-sparkle {
    color: #38bdf8;
}

.rg-textarea {
    flex: 1;
    width: 100%;
    border: none;
    background: transparent;
    resize: none;
    outline: none;
    font-size: 0.88rem;
    line-height: 1.5;
    color: #0f172a;
    font-family: inherit;
    min-height: 28px;
    max-height: 130px;
    padding: 0 0 8px 0;
}

[data-theme="dark"] .rg-textarea {
    color: #f8fafc;
}

.rg-textarea::placeholder {
    color: #94a3b8;
    font-size: 0.85rem;
}

/* Toolbar inferior dentro del cajón (Estilo Imagen 1 y 2) */
.rg-input-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: visible !important;
}

[data-theme="dark"] .rg-input-toolbar {
    border-top-color: rgba(255, 255, 255, 0.06);
}

.rg-toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
}

.rg-toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* Context pill in composer */
.rg-context-pill-composer {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    font-size: 0.74rem;
    font-weight: 500;
    color: #64748b;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: default;
    user-select: none;
}

[data-theme="dark"] .rg-context-pill-composer {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

.rg-context-pill-composer i {
    color: #2563eb;
    font-size: 0.82rem;
    flex-shrink: 0;
}

[data-theme="dark"] .rg-context-pill-composer i {
    color: #38bdf8;
}

/* Specialty Pill Trigger Button (Recuadro rojo Imagen 2) */
.rg-specialty-picker-wrap {
    position: relative;
    display: inline-block;
}

.rg-specialty-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    color: #1e293b;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    user-select: none;
}

[data-theme="dark"] .rg-specialty-pill-btn {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

.rg-specialty-pill-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .rg-specialty-pill-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(56, 189, 248, 0.35);
}

.rg-specialty-pill-btn i#rg-trigger-icon {
    color: #2563eb;
    font-size: 0.95rem;
    flex-shrink: 0;
}

[data-theme="dark"] .rg-specialty-pill-btn i#rg-trigger-icon {
    color: #38bdf8;
}

.rg-specialty-pill-btn .rg-caret {
    font-size: 0.68rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.rg-specialty-pill-btn.is-active .rg-caret {
    transform: rotate(180deg);
}

/* Specialties Popover Menu (Estilo Imagen 3: Popup desplegable oscuro y elegante) */
.rg-specialties-popover {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 0;
    min-width: 290px;
    max-width: 340px;
    background: #14161e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 6px;
    box-shadow: 0 20px 48px -6px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    z-index: 999999 !important;
    display: flex;
    flex-direction: column;
    gap: 3px;
    animation: rgPopoverFadeUp 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rgPopoverFadeUp {
    from { opacity: 0; transform: translateY(6px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.rg-popover-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    width: 100%;
    color: #e2e8f0;
    transition: background 0.15s ease, transform 0.1s ease;
}

.rg-popover-item:hover {
    background: rgba(255, 255, 255, 0.08);
}

.rg-popover-item.active {
    background: rgba(37, 99, 235, 0.2);
}

.rg-popover-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    color: #94a3b8;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.rg-popover-item:hover .rg-popover-item-icon,
.rg-popover-item.active .rg-popover-item-icon {
    background: #1d4ed8;
    color: #ffffff;
    box-shadow: 0 0 12px rgba(29, 78, 216, 0.5);
}

.rg-popover-item-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
    overflow: hidden;
}

.rg-popover-item-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: #f1f5f9;
}

.rg-popover-item-desc {
    font-size: 0.71rem;
    color: #94a3b8;
    line-height: 1.3;
}

.rg-popover-item-check {
    width: 18px;
    font-size: 0.95rem;
    color: #38bdf8;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.rg-popover-item.active .rg-popover-item-check {
    opacity: 1;
}

/* Citation / DB Badge */
.rg-citation-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 9999px;
    background: rgba(37, 99, 235, 0.08);
    border: 1px solid rgba(37, 99, 235, 0.2);
    font-size: 0.73rem;
    font-weight: 600;
    color: #2563eb;
    cursor: default;
    user-select: none;
}

[data-theme="dark"] .rg-citation-badge {
    background: rgba(37, 99, 235, 0.15);
    border-color: rgba(56, 189, 248, 0.25);
    color: #60a5fa;
}

.rg-citation-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #38bdf8;
    box-shadow: 0 0 8px #38bdf8;
}

/* Round Send Button (Estilo Imagen 1 y 2) */
.rg-send-btn-round {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}

[data-theme="dark"] .rg-send-btn-round {
    background: #1e293b;
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.rg-send-btn-round:hover:not(:disabled) {
    background: #1d4ed8;
    transform: scale(1.08);
    box-shadow: 0 4px 14px rgba(29, 78, 216, 0.4);
}

[data-theme="dark"] .rg-send-btn-round:hover:not(:disabled) {
    background: #2563eb;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.5);
}

.rg-send-btn-round:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
}

/* Backwards-compatibility send btn */
.rg-send-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #1d4ed8;
    color: #ffffff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.rg-send-btn:hover:not(:disabled) {
    background: #1e40af;
    transform: scale(1.05);
}

.rg-send-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
}

/* Footer Hints */
.rg-footer-hints {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.rg-hint {
    font-size: 0.7rem;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.rg-hint kbd {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 0.65rem;
    font-family: inherit;
    font-weight: 600;
}

[data-theme="dark"] .rg-hint kbd {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
    color: #cbd5e1;
}

/* ==========================================================================
   RESPONSIVE DESIGN (MOBILE APP EXPERIENCE & TABLET ADAPTATION)
   ========================================================================== */

@media (max-width: 640px) {
    /* Overlay Fullscreen */
    .romita-global-overlay {
        padding: 0 !important;
        align-items: stretch !important;
        justify-content: stretch !important;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        background: rgba(8, 10, 15, 0.95);
    }

    /* Dialog Edge-to-Edge Native Mobile App */
    .romita-global-dialog {
        width: 100% !important;
        max-width: 100% !important;
        height: 100% !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-radius: 0 !important;
        border: none !important;
        margin: 0 !important;
        box-shadow: none !important;
        animation: rgMobileSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes rgMobileSlideUp {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .rg-dialog-laser {
        border-radius: 0 !important;
    }

    /* Iluminación perimetral interna en móviles cuando está generando (Azul, Negro y Celeste) */
    .romita-global-dialog.is-generating {
        box-shadow: inset 0 0 0 2px rgba(29, 78, 216, 0.9),
                    inset 0 0 20px rgba(29, 78, 216, 0.45),
                    inset 0 0 45px rgba(56, 189, 248, 0.2) !important;
        animation: rgMobilePerimeterAura 2s infinite alternate ease-in-out !important;
    }

    @keyframes rgMobilePerimeterAura {
        0% {
            box-shadow: inset 0 0 0 2px rgba(29, 78, 216, 0.85),
                        inset 0 0 16px rgba(29, 78, 216, 0.45),
                        inset 0 0 35px rgba(56, 189, 248, 0.2);
        }
        50% {
            box-shadow: inset 0 0 0 2.5px rgba(56, 189, 248, 0.9),
                        inset 0 0 22px rgba(56, 189, 248, 0.5),
                        inset 0 0 45px rgba(29, 78, 216, 0.3);
        }
        100% {
            box-shadow: inset 0 0 0 2px rgba(10, 15, 29, 0.95),
                        inset 0 0 18px rgba(29, 78, 216, 0.5),
                        inset 0 0 35px rgba(56, 189, 248, 0.18);
        }
    }

    /* Header en Mobile */
    .rg-header {
        padding: 10px 14px;
        padding-top: max(10px, env(safe-area-inset-top));
        border-bottom-width: 1px;
        gap: 8px;
    }

    .rg-avatar-badge {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        font-size: 1.05rem;
    }

    .rg-name-row {
        gap: 6px;
    }

    .rg-title {
        font-size: 0.92rem;
    }

    .rg-version-tag {
        font-size: 0.6rem;
        padding: 1px 4px;
    }

    .rg-online-badge {
        font-size: 0.62rem;
        padding: 1px 6px;
    }

    .rg-screen-pill {
        max-width: 150px;
        font-size: 0.66rem;
        padding: 1px 6px;
    }

    /* En móviles se oculta el botón de alternar barra lateral fija (no aplica a pantallas táctiles estrechas) */
    #btn-romita-toggle-layout {
        display: none !important;
    }

    .rg-icon-btn {
        width: 36px;
        height: 36px;
        font-size: 1.15rem;
    }

    /* Vista de Bienvenida en Móvil */
    .rg-welcome-view {
        padding: 1rem 0.25rem 0.5rem 0.25rem;
        gap: 0.35rem;
    }

    .rg-welcome-bot-circle {
        width: 64px;
        height: 64px;
        margin-bottom: 4px;
    }

    .rg-bot-circle-img {
        width: 56px;
        height: 56px;
    }

    .rg-welcome-greeting {
        font-size: 1.12rem;
    }

    .rg-welcome-main-question {
        font-size: 1.22rem;
    }

    .rg-welcome-subtitle {
        font-size: 0.78rem;
    }

    .rg-examples-section {
        margin-top: 0.75rem;
    }

    .rg-examples-grid,
    .rg-suggestions-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
        width: 100% !important;
    }

    .rg-example-card,
    .rg-suggestion-card {
        padding: 11px 12px;
        min-height: 85px;
    }

    /* Footer e Input en Mobile */
    .rg-footer {
        padding: 8px 10px;
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        gap: 0;
        overflow: visible !important;
    }

    /* En móviles se ocultan los atajos de teclado físicos (Enter, Shift+Enter, R, Esc) para ganar espacio y limpieza */
    .rg-footer-hints {
        display: none !important;
    }

    .rg-input-box {
        padding: 8px 10px 8px 12px;
        border-radius: 14px;
        overflow: visible !important;
    }

    .rg-textarea {
        font-size: 0.88rem;
        line-height: 1.4;
        max-height: 100px;
    }

    .rg-context-pill-composer {
        display: none !important; /* En móviles se oculta el pill de módulo para dar espacio al selector de especialidad */
    }

    .rg-specialties-popover {
        left: -4px;
        right: -4px;
        min-width: calc(100vw - 28px);
        max-width: calc(100vw - 28px);
    }

    .rg-send-btn-round {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }

    /* FAB Botón en Mobile */
    .romita-fab-container {
        bottom: 18px;
        right: 18px;
    }

    .romita-fab-btn {
        width: 46px;
        height: 46px;
        border-radius: 14px;
    }

    /* Tablas en responsive móvil */
    .romita-table-container {
        margin: 10px -8px;
        border-radius: 10px;
    }
    .table-toolbar {
        padding: 0.5rem 0.65rem;
    }
    .table-info-badge {
        font-size: 0.68rem;
        padding: 0.15rem 0.45rem;
    }
    .table-scroll-hint {
        font-size: 0.68rem;
    }
    .btn-table-action {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }
    .btn-table-action .btn-text-full {
        display: none;
    }
    .btn-table-action .btn-text-short {
        display: inline;
    }
    .romita-table-container th,
    .rg-msg-bubble th {
        padding: 0.6rem 0.75rem;
        font-size: 0.68rem;
    }
    .romita-table-container td,
    .rg-msg-bubble td {
        padding: 0.65rem 0.75rem;
        font-size: 0.76rem;
        white-space: normal !important;
        overflow-wrap: break-word !important;
    }
    .romita-table-container th.col-wide,
    .romita-table-container td.col-wide {
        min-width: 200px;
        white-space: normal !important;
        overflow-wrap: break-word !important;
    }
    .romita-table-container th.col-medium,
    .romita-table-container td.col-medium {
        min-width: 150px;
        white-space: normal !important;
        overflow-wrap: break-word !important;
    }
    .romita-table-container th.col-compact,
    .romita-table-container td.col-compact {
        min-width: 95px;
        white-space: normal !important;
        overflow-wrap: break-word !important;
    }
}

@media (min-width: 641px) and (max-width: 850px) {
    .romita-global-overlay {
        padding: 0.75rem;
    }
    .romita-global-dialog {
        width: 98vw;
        max-width: 98vw;
        height: 94vh;
        max-height: 95vh;
    }
    .rg-thinking-bubble {
        min-width: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
}
</style>

<!-- Marked.js para tablas y Markdown interactivo -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
// ==========================================================================
// ROMITA GLOBAL FLOATING ASSISTANT CONTROLLER (TECLA R / SPOTLIGHT)
// ==========================================================================

let romitaCurrentChatId = null;
let romitaSpecialty = 'director_360';
let romitaIsDrawerMode = false;
let romitaIsLoading = false;
let romitaStatusInterval = null;
const romitaUserName = <?= json_encode($romitaFirstName) ?>;
const romitaTimeGreeting = <?= json_encode($timeGreeting) ?>;

const romitaSpecialtyMeta = {
    'director_360': { label: 'Directora 360°', icon: 'ph-compass' },
    'community_manager': { label: 'Senior CM', icon: 'ph-chat-circle-dots' },
    'branding': { label: 'Branding', icon: 'ph-palette' },
    'marketing': { label: 'Marketing & Growth', icon: 'ph-trend-up' },
    'seo': { label: 'Especialista SEO', icon: 'ph-magnifying-glass' }
};

// 1. Get Screen Context (Conciencia Situacional)
function getRomitaScreenContext() {
    const params = new URLSearchParams(window.location.search);
    const module = params.get('module') || 'dashboard';
    const action = params.get('action') || 'index';
    const id = params.get('id') || 0;

    const moduleNames = {
        'dashboard': 'Dashboard General',
        'calendar': 'Calendario de Contenidos',
        'month_board': 'Tablero de Mes (Calendario)',
        'desarrollo_marca': 'Desarrollo de Marca',
        'audiovisual': 'Producción Audiovisual',
        'pizarras': 'Pizarras Colaborativas',
        'projects': 'Proyectos Web & Sistema',
        'project_board': 'Tablero de Proyecto',
        'clients': 'Módulo de Clientes',
        'quotes': 'Cotizaciones',
        'work_orders': 'Órdenes de Servicio',
        'contracts': 'Contratos',
        'reuniones': 'Agenda & Reuniones',
        'task_manager': 'Tareas & Objetivos',
        'tasks': 'Tareas del Equipo',
        'services': 'Catálogo de Servicios',
        'suppliers': 'Proveedores & Aliados',
        'knowledge_base': 'Base de Conocimiento',
        'mensajes': 'Mensajes Internos',
        'chat': 'Chat de la Agencia',
        'forms': 'Formularios',
        'conexiones': 'Conexiones & APIs',
        'romita': 'Romita IA Workspace',
        'whatsapp': 'WhatsApp CRM',
        'drive': 'Drive & Archivos',
        'admin': 'Administración del Sistema'
    };

    const friendlyName = moduleNames[module] || module;
    const detail = id ? ` #${id}` : '';
    return {
        module: module,
        action: action,
        id: id,
        name: friendlyName,
        detail: detail,
        label: `${friendlyName}${detail}`
    };
}

// 2. Open / Close / Toggle Modal
function toggleRomitaGlobalModal() {
    const overlay = document.getElementById('romita-global-overlay');
    if (!overlay) return;
    if (overlay.style.display === 'none' || overlay.style.display === '') {
        openRomitaGlobalModal();
    } else {
        closeRomitaGlobalModal();
    }
}

function openRomitaGlobalModal() {
    const overlay = document.getElementById('romita-global-overlay');
    if (!overlay) return;

    overlay.style.display = 'flex';
    document.body.classList.add('romita-modal-open');
    const fab = document.getElementById('romita-fab-container');
    if (fab) fab.classList.add('is-hidden-by-modal');

    updateRomitaScreenPill();

    // Actualizar bienvenida contextual si no hay mensajes activos en pantalla
    const welcomeView = document.getElementById('romita-welcome-view');
    if (!romitaCurrentChatId || welcomeView) {
        if (romitaSpecialty === 'director_360') {
            renderModuleWelcome();
        } else {
            renderSpecialtyWelcome(romitaSpecialty);
        }
    }

    const input = document.getElementById('romita-chat-input');
    if (input) {
        setTimeout(() => input.focus(), 120);
    }
}

function closeRomitaGlobalModal() {
    const overlay = document.getElementById('romita-global-overlay');
    if (overlay) overlay.style.display = 'none';
    document.body.classList.remove('romita-modal-open');
    const fab = document.getElementById('romita-fab-container');
    if (fab) fab.classList.remove('is-hidden-by-modal');
}

function handleRomitaOverlayClick(e) {
    if (e.target.id === 'romita-global-overlay') {
        closeRomitaGlobalModal();
    }
}

// 3. Layout Mode Toggle (Spotlight vs Drawer)
function toggleRomitaLayoutMode() {
    if (window.innerWidth <= 640) return; // En móviles no aplica modo lateral dock
    const dialog = document.getElementById('romita-global-dialog');
    const icon = document.getElementById('rg-layout-icon');
    if (!dialog) return;

    romitaIsDrawerMode = !romitaIsDrawerMode;
    if (romitaIsDrawerMode) {
        dialog.classList.add('drawer-mode');
        if (icon) icon.className = 'ph ph-corners-in';
    } else {
        dialog.classList.remove('drawer-mode');
        if (icon) icon.className = 'ph ph-sidebar-simple';
    }
}

// 4. Update Screen Indicator (Zero Emojis, Clean Phosphor Icon)
function updateRomitaScreenPill() {
    const pill = document.getElementById('romita-screen-pill');
    const composerPill = document.getElementById('rg-composer-context-label');
    const ctx = getRomitaScreenContext();
    if (pill) {
        pill.innerHTML = `<i class="ph ph-browsers"></i> <span>${ctx.label}</span>`;
    }
    if (composerPill) {
        composerPill.textContent = ctx.name;
    }
}

// 5. Specialty Selection & Dropdown Popover Controller (Estilo Imagen 2 y 3)
function toggleRomitaSpecialtyMenu(e) {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    const popover = document.getElementById('rg-specialties-popover');
    const trigger = document.getElementById('rg-specialty-trigger-btn');
    if (!popover) return;
    const isVisible = popover.style.display !== 'none';
    if (isVisible) {
        popover.style.display = 'none';
        if (trigger) {
            trigger.classList.remove('is-active');
            trigger.setAttribute('aria-expanded', 'false');
        }
    } else {
        popover.style.display = 'flex';
        if (trigger) {
            trigger.classList.add('is-active');
            trigger.setAttribute('aria-expanded', 'true');
        }
    }
}

function closeRomitaSpecialtyMenu() {
    const popover = document.getElementById('rg-specialties-popover');
    const trigger = document.getElementById('rg-specialty-trigger-btn');
    if (popover) popover.style.display = 'none';
    if (trigger) {
        trigger.classList.remove('is-active');
        trigger.setAttribute('aria-expanded', 'false');
    }
}

function selectRomitaSpecialty(spec, label, iconClass) {
    romitaSpecialty = spec;
    
    // Actualizar texto e icono del botón en el toolbar del input (recuadro rojo imagen 2)
    const labelEl = document.getElementById('rg-trigger-label');
    const iconEl = document.getElementById('rg-trigger-icon');
    if (labelEl) labelEl.textContent = label;
    if (iconEl) iconEl.className = 'ph ' + iconClass;
    
    // Actualizar estado activo en el popover
    document.querySelectorAll('.rg-popover-item').forEach(item => {
        if (item.getAttribute('data-spec') === spec) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    closeRomitaSpecialtyMenu();

    // Cambiar de especialidad crea un nuevo chat fresco con el rol correspondiente
    romitaCurrentChatId = null;
    if (spec === 'director_360') {
        renderModuleWelcome();
    } else {
        renderSpecialtyWelcome(spec);
    }
}

function setRomitaSpecialty(spec, btn) {
    const meta = romitaSpecialtyMeta[spec] || { label: 'Directora 360°', icon: 'ph-compass' };
    selectRomitaSpecialty(spec, meta.label, meta.icon);
}

// Cerrar menú emergente si se hace clic fuera
document.addEventListener('click', function(e) {
    const popover = document.getElementById('rg-specialties-popover');
    const trigger = document.getElementById('rg-specialty-trigger-btn');
    if (popover && popover.style.display !== 'none') {
        if (!popover.contains(e.target) && !trigger.contains(e.target)) {
            closeRomitaSpecialtyMenu();
        }
    }
});

// Bienvenida Contextualizada y Personalizada por Módulo
function renderModuleWelcome() {
    const chatContainer = document.getElementById('romita-chat-messages');
    if (!chatContainer) return;
    const ctx = getRomitaScreenContext();

    const moduleConfigs = {
        'services': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Servicios?`,
            desc: 'Consulta la oferta comercial de Roma Agencia, estructura entregables profesionales y optimiza la propuesta de valor.',
            cards: [
                { icon: 'ph-sparkle', label: 'Optimizar descripción', sub: 'Mejora el copy y propuesta de valor de un servicio', prompt: 'Ayúdame a redactar una descripción persuasiva y comercial para un servicio de la agencia' },
                { icon: 'ph-package', label: 'Estructurar entregables', sub: 'Desglose claro de fases y alcances', prompt: '¿Cómo estructurar de forma clara y profesional los entregables para un servicio creativo?' },
                { icon: 'ph-target', label: 'Diferencial de servicio', sub: 'Argumentos de valor frente a competidores', prompt: '¿Cuáles son los factores diferenciales clave que debemos resaltar en nuestros servicios?' },
                { icon: 'ph-lightbulb', label: 'Nuevos paquetes', sub: 'Ideas de innovación y upsells para clientes', prompt: 'Propón 3 ideas de nuevos paquetes de servicios digitales para ofrecer a nuestros clientes' }
            ]
        },
        'calendar': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en el Calendario?`,
            desc: 'Planificación mensual de publicaciones, copies persuasivos, ganchos virales y pilares de contenido.',
            cards: [
                { icon: 'ph-lightning', label: '5 Ganchos para Reels', sub: 'Fórmulas de retención para primeros 3 segundos', prompt: 'Dame 5 ganchos magnéticos para Reels de nuestras marcas de este mes' },
                { icon: 'ph-calendar-plus', label: 'Estructura mensual', sub: 'Balance de pilares de venta, valor y engagement', prompt: '¿Cómo balancear los pilares de contenido (educación, entretenimiento, venta) para el calendario del mes?' },
                { icon: 'ph-chats-circle', label: 'Historias interactivas', sub: 'Stickers y dinámicas para engagement', prompt: 'Propón 4 ideas de historias de Instagram para disparar respuestas y mensajes directos' },
                { icon: 'ph-sparkle', label: 'Copy de alto impacto', sub: 'Estructura Hook + Body + CTA', prompt: 'Redacta un copy persuasivo con estructura AIDA para una publicación de Instagram' }
            ]
        },
        'month_board': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en el Tablero de Mes?`,
            desc: 'Supervisión y curaduría de la parrilla de contenidos, copies por plataforma y estados de aprobación.',
            cards: [
                { icon: 'ph-lightning', label: 'Ganchos de retención', sub: 'Aperturas magnéticas para publicaciones', prompt: 'Dame 5 ganchos magnéticos para los posts de este mes' },
                { icon: 'ph-article', label: 'Copywriting para Carrusel', sub: 'Secuencia de diapositivas educativas', prompt: 'Diseña la estructura de copy para un carrusel educativo de 6 diapositivas' },
                { icon: 'ph-check-circle', label: 'Checklist de publicación', sub: 'Validación de formatos, hashtags y enlaces', prompt: '¿Qué checklist de calidad debemos revisar antes de aprobar y programar un post?' },
                { icon: 'ph-chart-line-up', label: 'Análisis de engagement', sub: 'Formatos con mayor alcance orgánico', prompt: '¿Qué tipo de contenidos generan mayor alcance e interacción en Instagram actualmente?' }
            ]
        },
        'quotes': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Cotizaciones?`,
            desc: 'Estructuración de presupuestos, alcance de proyectos, términos comerciales y seguimiento a prospectos.',
            cards: [
                { icon: 'ph-file-text', label: 'Estructura de propuesta', sub: 'Redacción clara de alcance y metodología', prompt: '¿Cómo estructurar una propuesta comercial irresistible y profesional para un cliente?' },
                { icon: 'ph-shield-check', label: 'Condiciones y términos', sub: 'Cláusulas de entregas, revisiones y plazos', prompt: '¿Qué cláusulas y términos de revisiones recomiendas incluir en una cotización comercial?' },
                { icon: 'ph-arrows-clockwise', label: 'Seguimiento comercial', sub: 'Mensaje persuasivo para leads pendientes', prompt: 'Redacta un mensaje diplomático y persuasivo para hacer seguimiento a una cotización enviada' },
                { icon: 'ph-handshake', label: 'Manejo de objeciones', sub: 'Respuestas a dudas de clientes', prompt: '¿Cómo responder con valor y elegancia cuando un cliente pide descuento o dice que está fuera de presupuesto?' }
            ]
        },
        'work_orders': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Órdenes de Servicio?`,
            desc: 'Gestión operativa de OTs, alcance técnico, requerimientos iniciales y entregables coordinados.',
            cards: [
                { icon: 'ph-clipboard-text', label: 'Checklist de inicio', sub: 'Insumos requeridos del cliente', prompt: '¿Qué requerimientos e insumos mínimos debemos solicitar al cliente para iniciar la OT?' },
                { icon: 'ph-timer', label: 'Cronograma por hitos', sub: 'Planificación de entregas y fases', prompt: '¿Cómo definir un cronograma eficiente de entregas por hitos para una orden de trabajo?' },
                { icon: 'ph-check-circle', label: 'Control de calidad', sub: 'Validación antes del cierre de OT', prompt: 'Checklist de revisión y control de calidad antes de dar por completada una orden de trabajo' },
                { icon: 'ph-users-three', label: 'Coordinación de equipo', sub: 'Alineación de diseño, web y audiovisual', prompt: '¿Cómo coordinar las tareas entre diseñador, community manager y audiovisual para cumplir plazos?' }
            ]
        },
        'desarrollo_marca': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Desarrollo de Marca?`,
            desc: 'Branding estratégico, arquetipos, personalidad verbal, conceptos visuales y diseño de identidad.',
            cards: [
                { icon: 'ph-paint-brush', label: 'Arquetipo de marca', sub: 'Definición de personalidad según Jung', prompt: '¿Cómo definir el arquetipo de personalidad y tono de voz para una marca en desarrollo?' },
                { icon: 'ph-book-open', label: 'Manifiesto de marca', sub: 'Narrativa de propósito y valores', prompt: 'Ayúdame a redactar un manifiesto inspirador y memorable para una marca de la agencia' },
                { icon: 'ph-palette', label: 'Dirección estética', sub: 'Conceptos visuales y moodboard', prompt: '¿Cómo estructurar los conceptos visuales y la dirección creativa de un nuevo branding?' },
                { icon: 'ph-file-code', label: 'Capítulos de Brandbook', sub: 'Contenido esencial de manual de marca', prompt: '¿Cuáles son los capítulos esenciales que debe contener un manual de identidad corporativa completo?' }
            ]
        },
        'audiovisual': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Audiovisual?`,
            desc: 'Producción de video, guiones de alto impacto, escaletas de rodaje y técnicas de retención en edición.',
            cards: [
                { icon: 'ph-video-camera', label: 'Guión para Reel / TikTok', sub: 'Gancho, desarrollo y remate en 30s', prompt: 'Redacta un guión dinámico de 30 segundos para Reel/TikTok con indicaciones de plano y texto en pantalla' },
                { icon: 'ph-list-numbers', label: 'Escaleta de rodaje', sub: 'Planificación de planos y escenas', prompt: '¿Cómo estructurar una escaleta de rodaje eficiente para una jornada de grabación con un cliente?' },
                { icon: 'ph-film-strip', label: 'Ritmo y retención', sub: 'Técnicas de corte para retener audiencia', prompt: '¿Qué técnicas de ritmo de edición y cortes dinámicos recomiendas para mantener alta la retención en video?' },
                { icon: 'ph-sparkle', label: 'Dirección de arte en video', sub: 'Iluminación, encuadre y estética', prompt: 'Propón 3 conceptos visuales creativos para un video promocional de alto impacto' }
            ]
        },
        'pizarras': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Pizarras?`,
            desc: 'Lluvia de ideas creativa, diagramas de flujo, mapas mentales y conceptualización de campañas.',
            cards: [
                { icon: 'ph-lightbulb', label: 'Lluvia de ideas', sub: '5 conceptos disruptivos para campañas', prompt: 'Genera una lluvia de 5 ideas creativas e innovadoras para una campaña integral de marketing' },
                { icon: 'ph-git-fork', label: 'Diagrama de procesos', sub: 'Mapeo paso a paso de atención', prompt: '¿Cómo estructurar un diagrama de flujo para el proceso de atención y entrega al cliente?' },
                { icon: 'ph-columns', label: 'Matriz de priorización', sub: 'Impacto vs Esfuerzo en el tablero', prompt: 'Explica cómo utilizar una matriz Impacto vs Esfuerzo para ordenar las ideas de la pizarra' },
                { icon: 'ph-projector-screen', label: 'Conceptos de campaña', sub: 'Ejes temáticos y lemas comerciales', prompt: 'Propón 3 ejes temáticos y eslogans potentes para una campaña publicitaria' }
            ]
        },
        'task_manager': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Tareas?`,
            desc: 'Organización operativa, priorización de pendientes, resolución de cuellos de botella y enfoque.',
            cards: [
                { icon: 'ph-checks', label: 'Priorización Eisenhower', sub: 'Urgente vs Importante para el día', prompt: '¿Cómo priorizar mis tareas del día usando la matriz urgente vs importante para ser más productivo?' },
                { icon: 'ph-fire', label: 'Destrabar cuellos de botella', sub: 'Plan de acción para tareas acumuladas', prompt: 'Tengo varias tareas acumuladas del equipo, ¿cuál es la mejor estrategia para destrabar el flujo?' },
                { icon: 'ph-clock-countdown', label: 'Bloques de enfoque', sub: 'Técnica Time-Blocking sin pausa', prompt: '¿Cómo implementar Time-Blocking para completar entregas de diseño y contenido sin distracciones?' },
                { icon: 'ph-flag', label: 'Criterios de entrega (DoD)', sub: 'Estándar para cerrar tareas con calidad', prompt: '¿Qué criterios de aceptación o Definition of Done (DoD) debemos aplicar antes de cerrar una tarea?' }
            ]
        },
        'tasks': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Tareas?`,
            desc: 'Organización operativa, priorización de pendientes, resolución de cuellos de botella y enfoque.',
            cards: [
                { icon: 'ph-checks', label: 'Priorización Eisenhower', sub: 'Urgente vs Importante para el día', prompt: '¿Cómo priorizar mis tareas del día usando la matriz urgente vs importante para ser más productivo?' },
                { icon: 'ph-fire', label: 'Destrabar pendientes', sub: 'Plan de acción para entregas del día', prompt: 'Tengo varias tareas acumuladas del equipo, ¿cuál es la mejor estrategia para destrabar el flujo?' },
                { icon: 'ph-clock-countdown', label: 'Bloques de enfoque', sub: 'Técnica Time-Blocking para producción', prompt: '¿Cómo implementar Time-Blocking para completar entregas de diseño y contenido sin distracciones?' },
                { icon: 'ph-flag', label: 'Criterios de calidad', sub: 'Validación antes de marcar completada', prompt: '¿Qué criterios de aceptación o Definition of Done (DoD) debemos aplicar antes de cerrar una tarea?' }
            ]
        },
        'knowledge_base': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en la Base de Conocimiento?`,
            desc: 'Manuales oficiales, SOPs, directrices de atención, tutoriales y normativas de Roma Agencia.',
            cards: [
                { icon: 'ph-book-bookmark', label: 'Consultar procedimientos', sub: 'SOPs y lineamientos de la agencia', prompt: '¿Cuáles son los pasos del procedimiento oficial para coordinar una entrega con un cliente?' },
                { icon: 'ph-file-plus', label: 'Documentar un nuevo SOP', sub: 'Estructura paso a paso para el equipo', prompt: 'Ayúdame a redactar un Procedimiento Operativo Estándar (SOP) claro y paso a paso para el equipo' },
                { icon: 'ph-seal-check', label: 'Estándares de calidad', sub: 'Cultura y protocolos profesionales', prompt: '¿Cuáles son las directrices de comunicación y puntualidad que rigen en Roma Agencia?' },
                { icon: 'ph-magnifying-glass', label: 'Resolución de incidentes', sub: 'Guía ante retrasos o contratiempos', prompt: '¿Cómo actuar ante un retraso en la entrega de material por parte de un cliente según nuestros procesos?' }
            ]
        },
        'reuniones': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Agenda & Reuniones?`,
            desc: 'Preparación de minutas ejecutivas, agendas de alineación, preguntas de descubrimiento y acuerdos.',
            cards: [
                { icon: 'ph-notepad', label: 'Agenda ejecutiva de 30m', sub: 'Estructura puntual sin rodeos', prompt: 'Diseña una agenda ejecutiva de 30 minutos para una reunión de alineación con un cliente' },
                { icon: 'ph-check-square', label: 'Plantilla de minuta', sub: 'Registro de acuerdos y responsables', prompt: 'Dame una plantilla concisa para levantar la minuta de una reunión con acuerdos, tareas y fechas límite' },
                { icon: 'ph-chats', label: 'Preguntas para Briefing', sub: 'Descubrimiento de necesidades', prompt: '¿Cuáles son las 6 preguntas clave que debemos hacer en una reunión de onboarding con un cliente nuevo?' },
                { icon: 'ph-target', label: 'Seguimiento de acuerdos', sub: 'Mensaje resumen post-reunión', prompt: 'Redacta un mensaje cordial para enviar por WhatsApp o correo resumiendo los acuerdos de la reunión' }
            ]
        },
        'clients': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Clientes?`,
            desc: 'Fidelización de cuentas, comunicación estratégica, onboarding y satisfacción de clientes.',
            cards: [
                { icon: 'ph-hand-waving', label: 'Onboarding de cliente', sub: 'Bienvenida y recopilación de accesos', prompt: '¿Cómo diseñar un proceso de onboarding cálido y profesional para recibir a un cliente nuevo?' },
                { icon: 'ph-star', label: 'Estrategia de fidelización', sub: 'Retención y valor continuo', prompt: '¿Qué iniciativas podemos implementar para fidelizar a los clientes recurrentes de la agencia?' },
                { icon: 'ph-bell-ringing', label: 'Comunicación preventiva', sub: 'Aviso de tiempos y avances', prompt: 'Redacta una plantilla para informar al cliente sobre el estado de su proyecto con proactividad' },
                { icon: 'ph-chat-teardrop-dots', label: 'Recopilar feedback', sub: 'Encuesta de satisfacción y testimonios', prompt: '¿Cómo pedir retroalimentación y testimonios a un cliente satisfecho sin incomodarlo?' }
            ]
        },
        'projects': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en Proyectos?`,
            desc: 'Dirección de proyectos web, sistemas, despliegue técnico y coordinación entre áreas.',
            cards: [
                { icon: 'ph-kanban', label: 'Estado del proyecto', sub: 'Monitoreo de fases y entregables', prompt: '¿Cómo estructurar un reporte de avance semanal del proyecto para el cliente?' },
                { icon: 'ph-browsers', label: 'Auditoría UX / UI', sub: 'Checklist de usabilidad y navegación', prompt: '¿Qué elementos de usabilidad y experiencia de usuario debemos auditar en un sitio web antes del lanzamiento?' },
                { icon: 'ph-bug', label: 'Matriz de incidencias (QA)', sub: 'Control y reporte de pruebas', prompt: '¿Cómo estructurar una hoja de control de QA para registrar errores y correcciones web?' },
                { icon: 'ph-rocket-launch', label: 'Checklist de lanzamiento', sub: 'Pase a producción sin riesgos', prompt: 'Checklist técnico completo para publicar un sitio web en producción' }
            ]
        },
        'project_board': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en el Tablero de Proyectos?`,
            desc: 'Supervisión visual de tarjetas, estados de desarrollo y avance de entregables.',
            cards: [
                { icon: 'ph-kanban', label: 'Avance del sprint', sub: 'Seguimiento de entregas pendientes', prompt: '¿Cómo destrabar tareas en proceso que llevan varios días sin actualizarse?' },
                { icon: 'ph-flag', label: 'Hitos clave', sub: 'Prioridades del sprint actual', prompt: '¿Cómo priorizar los hitos críticos para garantizar la entrega a tiempo del proyecto?' },
                { icon: 'ph-chats-circle', label: 'Actualización al cliente', sub: 'Resumen conciso de avance', prompt: 'Redacta un mensaje breve para informarle al cliente sobre el avance del tablero de desarrollo' },
                { icon: 'ph-shield-check', label: 'Validación técnica', sub: 'Aceptación de requerimientos', prompt: '¿Cuáles son los pasos para validar que un requerimiento técnico cumple con el alcance acordado?' }
            ]
        },
        'dashboard': {
            title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en el Dashboard?`,
            desc: 'Asistente de inteligencia conectada a Calendarios, Marcas, Web, Audiovisual y Pizarras.',
            cards: [
                { icon: 'ph-kanban', label: 'Proyectos activos', sub: 'Resumen de producción de la agencia', prompt: '¿Qué proyectos tenemos activos actualmente en la agencia?' },
                { icon: 'ph-lightbulb', label: 'Ideas de contenido', sub: 'Estrategia con ganchos para el mes', prompt: 'Dame 3 ideas creativas de contenido con gancho para las marcas de este mes' },
                { icon: 'ph-funnel', label: 'Embudo de conversión', sub: 'Estructura estratégica TOFU-MOFU-BOFU', prompt: '¿Cómo podemos estructurar un embudo de ventas TOFU-MOFU-BOFU de alta conversión?' },
                { icon: 'ph-chart-line-up', label: 'Optimización SEO', sub: 'Directrices para páginas de servicios', prompt: 'Revisa las mejores prácticas de SEO para optimizar las páginas de servicios' }
            ]
        }
    };

    const cfg = moduleConfigs[ctx.module] || {
        title: `¡Hola, ${romitaUserName}! ¿Qué haremos hoy en ${ctx.name}?`,
        desc: `Asistente de inteligencia conectada a ${ctx.name} y al ecosistema de Roma Agencia.`,
        cards: moduleConfigs['dashboard'].cards
    };

    chatContainer.innerHTML = `
        <div id="romita-welcome-view" class="rg-welcome-view">
            <div class="rg-welcome-bot-circle">
                <div class="rg-bot-circle-glow"></div>
                <img src="assets/img/romita-avatar.png" alt="Romita" class="rg-bot-circle-img">
            </div>
            <div class="rg-welcome-heading-wrap">
                <h2 class="rg-welcome-greeting">¡${romitaTimeGreeting}, ${romitaUserName}!</h2>
                <h1 class="rg-welcome-main-question">¿En qué <span class="rg-accent-text">colaboramos hoy?</span></h1>
            </div>
            <p class="rg-welcome-subtitle" id="romita-welcome-desc">
                ${cfg.desc}
            </p>

            <div class="rg-examples-section">
                <div class="rg-examples-label">SUGERENCIAS PARA COMENZAR</div>
                <div class="rg-examples-grid" id="romita-welcome-grid">
                    ${cfg.cards.map(c => `
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('${c.prompt.replace(/'/g, "\\'")}')">
                            <span class="rg-example-text">${c.prompt}</span>
                            <span class="rg-example-icon"><i class="ph ${c.icon}"></i></span>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    const input = document.getElementById('romita-chat-input');
    if (input) {
        input.value = '';
        input.style.height = 'auto';
    }
}

function renderSpecialtyWelcome(spec) {
    const chatContainer = document.getElementById('romita-chat-messages');
    if (!chatContainer) return;

    const specialtyData = {
        'director_360': {
            title: 'Directora Estratégica 360°',
            desc: 'Visión global que coordina branding, web, contenido y performance en toda la agencia.',
            cards: [
                { icon: 'ph-kanban', label: 'Proyectos activos', sub: 'Resumen de producción de la agencia', prompt: '¿Qué proyectos tenemos activos actualmente en la agencia?' },
                { icon: 'ph-lightbulb', label: 'Ideas de contenido', sub: 'Estrategia con ganchos para el mes', prompt: 'Dame 3 ideas creativas de contenido con gancho para las marcas de este mes' },
                { icon: 'ph-funnel', label: 'Embudo de conversión', sub: 'Estructura estratégica TOFU-MOFU-BOFU', prompt: '¿Cómo podemos estructurar un embudo de ventas TOFU-MOFU-BOFU de alta conversión?' },
                { icon: 'ph-chart-line-up', label: 'Optimización SEO', sub: 'Directrices para páginas de servicios', prompt: 'Revisa las mejores prácticas de SEO para optimizar las páginas de servicios' }
            ]
        },
        'community_manager': {
            title: 'Senior Community Manager',
            desc: 'Especialista en copys magnéticos, ganchos virales, calendarios y dinámicas de engagement.',
            cards: [
                { icon: 'ph-lightning', label: '5 Ganchos para Reels', sub: 'Fórmulas de retención para primeros 3 segundos', prompt: 'Dame 5 ganchos magnéticos para Reels de nuestras marcas este mes' },
                { icon: 'ph-calendar-plus', label: 'Estructura de Calendario', sub: 'Equilibrio de pilares de contenido', prompt: '¿Cómo estructurar un calendario de 12 posts balanceando venta, valor y engagement?' },
                { icon: 'ph-chats-circle', label: 'Dinámicas de Engagement', sub: 'Stickers interactivos y preguntas en historias', prompt: 'Propón 4 ideas de historias interactivas para aumentar mensajes directos y respuestas' },
                { icon: 'ph-target', label: 'Llamados a la Acción (CTA)', sub: 'Fórmulas persuasivas que no suenan a spam', prompt: 'Dame 5 fórmulas de Call to Action (CTA) de alta conversión para publicaciones' }
            ]
        },
        'branding': {
            title: 'Especialista en Branding',
            desc: 'Construcción de identidad, arquetipos de marca, tono de voz y coherencia visual.',
            cards: [
                { icon: 'ph-paint-brush-broad', label: 'Arquetipo de Marca', sub: 'Definición de personalidad y valores', prompt: '¿Cómo definir el arquetipo de personalidad para una de nuestras marcas?' },
                { icon: 'ph-megaphone', label: 'Tono y Voz de Marca', sub: 'Guía de comunicación y vocabulario clave', prompt: 'Estructura una guía de tono de voz: qué decimos, cómo lo decimos y qué evitamos' },
                { icon: 'ph-eye', label: 'Auditoría de Identidad', sub: 'Revisión de consistencia visual', prompt: '¿Qué elementos debemos auditar para garantizar coherencia en manual de marca?' },
                { icon: 'ph-book-open', label: 'Storytelling Corporativo', sub: 'Narrativa del origen y propuesta de valor', prompt: '¿Cómo redactar un manifiesto de marca inspirador y memorable?' }
            ]
        },
        'marketing': {
            title: 'Growth Marketing & Conversión',
            desc: 'Embudos de adquisición, pauta publicitaria (Ads), métricas de rendimiento y CRO.',
            cards: [
                { icon: 'ph-funnel', label: 'Embudo de Ventas (Funnels)', sub: 'Flujo completo de lead a cliente recurrente', prompt: 'Diseña un embudo de ventas TOFU-MOFU-BOFU con oferta gancho y retargeting' },
                { icon: 'ph-currency-dollar', label: 'Estrategia de Meta Ads', sub: 'Estructura de campañas ABO/CBO y audiencias', prompt: '¿Cómo estructurar una campaña de Meta Ads rentable para captar clientes calificados?' },
                { icon: 'ph-chart-pie-slice', label: 'Optimización de CRO', sub: 'Mejora de conversión en páginas de aterrizaje', prompt: '¿Cuáles son los 5 puntos críticos para aumentar la tasa de conversión en una landing page?' },
                { icon: 'ph-arrows-clockwise', label: 'Reactivación de Clientes', sub: 'Estrategia de remarketing por WhatsApp/Email', prompt: 'Crea una secuencia de 3 mensajes para reactivar cotizaciones o leads antiguos' }
            ]
        },
        'seo': {
            title: 'Especialista en SEO',
            desc: 'Posicionamiento orgánico en Google, intención de búsqueda y arquitectura web.',
            cards: [
                { icon: 'ph-magnifying-glass', label: 'Keyword Research', sub: 'Palabras clave con alta intención comercial', prompt: '¿Cómo investigar palabras clave transaccionales para los servicios de la agencia?' },
                { icon: 'ph-article', label: 'Optimización On-Page', sub: 'Estructura de H1, H2, meta title y URLs limpias', prompt: 'Dame una checklist de optimización SEO On-Page para un artículo o servicio' },
                { icon: 'ph-tree-structure', label: 'Arquitectura de Contenidos', sub: 'Topic clusters y enlazado interno estratégico', prompt: 'Explica cómo armar una estrategia de Topic Clusters para posicionar en Google' },
                { icon: 'ph-speedometer', label: 'SEO Técnico Básico', sub: 'Velocidad, Core Web Vitals y schema markup', prompt: '¿Qué aspectos técnicos de SEO debemos auditar antes de lanzar una página web?' }
            ]
        }
    };

    const data = specialtyData[spec] || specialtyData['director_360'];

    chatContainer.innerHTML = `
        <div id="romita-welcome-view" class="rg-welcome-view">
            <div class="rg-welcome-bot-circle">
                <div class="rg-bot-circle-glow"></div>
                <img src="assets/img/romita-avatar.png" alt="Romita" class="rg-bot-circle-img">
            </div>
            <div class="rg-welcome-heading-wrap">
                <h2 class="rg-welcome-greeting">¡${romitaTimeGreeting}, ${romitaUserName}!</h2>
                <h1 class="rg-welcome-main-question">Modo <span class="rg-accent-text">${data.title}</span></h1>
            </div>
            <p class="rg-welcome-subtitle" id="romita-welcome-desc">
                ${data.desc}
            </p>
            <div class="rg-examples-section">
                <div class="rg-examples-label">SUGERENCIAS PARA COMENZAR</div>
                <div class="rg-examples-grid" id="romita-welcome-grid">
                    ${data.cards.map(c => `
                        <button type="button" class="rg-example-card" onclick="sendRomitaQuickPrompt('${c.prompt.replace(/'/g, "\\'")}')">
                            <span class="rg-example-text">${c.prompt}</span>
                            <span class="rg-example-icon"><i class="ph ${c.icon}"></i></span>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>
    `;

    const input = document.getElementById('romita-chat-input');
    if (input) {
        input.value = '';
        input.style.height = 'auto';
        input.focus();
    }
}

// 6. Quick Prompts
function sendRomitaQuickPrompt(text) {
    const input = document.getElementById('romita-chat-input');
    if (input) {
        input.value = text;
        sendRomitaMessage();
    }
}

// 7. Auto-grow Textarea & Slash Commands Controller
function autoGrowRomitaTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function handleRomitaInputChanged(el) {
    autoGrowRomitaTextarea(el);
    const val = el.value.trim();
    const menu = document.getElementById('rg-slash-menu');
    if (!menu) return;

    if (val.startsWith('/')) {
        menu.style.display = 'block';
        const filter = val.toLowerCase();
        const items = menu.querySelectorAll('.rg-slash-item');
        let hasVisible = false;
        items.forEach(item => {
            const cmd = item.getAttribute('data-cmd') || '';
            const desc = item.textContent.toLowerCase();
            if (cmd.startsWith(filter) || desc.includes(filter.replace('/', ''))) {
                item.style.display = 'flex';
                hasVisible = true;
            } else {
                item.style.display = 'none';
            }
        });

        // Set first visible item as active
        items.forEach(it => it.classList.remove('active'));
        const firstVisible = Array.from(items).find(it => it.style.display !== 'none');
        if (firstVisible) firstVisible.classList.add('active');

        if (!hasVisible) {
            menu.style.display = 'none';
        }
    } else {
        menu.style.display = 'none';
    }
}

function handleRomitaInputKeydown(e) {
    const menu = document.getElementById('rg-slash-menu');
    const isMenuOpen = menu && menu.style.display !== 'none';

    if (isMenuOpen) {
        const visibleItems = Array.from(menu.querySelectorAll('.rg-slash-item')).filter(it => it.style.display !== 'none');
        let activeIdx = visibleItems.findIndex(it => it.classList.contains('active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (visibleItems.length > 0) {
                if (activeIdx >= 0) visibleItems[activeIdx].classList.remove('active');
                activeIdx = (activeIdx + 1) % visibleItems.length;
                visibleItems[activeIdx].classList.add('active');
                visibleItems[activeIdx].scrollIntoView({ block: 'nearest' });
            }
            return;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (visibleItems.length > 0) {
                if (activeIdx >= 0) visibleItems[activeIdx].classList.remove('active');
                activeIdx = (activeIdx - 1 + visibleItems.length) % visibleItems.length;
                visibleItems[activeIdx].classList.add('active');
                visibleItems[activeIdx].scrollIntoView({ block: 'nearest' });
            }
            return;
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (visibleItems.length > 0 && activeIdx >= 0) {
                e.preventDefault();
                selectRomitaSlashCommand(visibleItems[activeIdx]);
                return;
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            menu.style.display = 'none';
            return;
        }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendRomitaMessage();
    }
}

function selectRomitaSlashCommand(btn) {
    const prompt = btn.getAttribute('data-prompt') || '';
    const input = document.getElementById('romita-chat-input');
    const menu = document.getElementById('rg-slash-menu');
    if (menu) menu.style.display = 'none';
    if (input) {
        input.value = prompt;
        autoGrowRomitaTextarea(input);
        input.focus();
    }
}

// 7.5 Reconocimiento de Voz Nativo (Web Speech API)
let romitaSpeechRecognition = null;
let romitaIsListening = false;

function toggleRomitaVoiceRecognition() {
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    const micBtn = document.getElementById('btn-romita-mic');
    const input = document.getElementById('romita-chat-input');

    if (!SpeechRec) {
        alert('Tu navegador no soporta reconocimiento de voz nativo. Te recomendamos usar Google Chrome o Microsoft Edge.');
        return;
    }

    if (romitaIsListening && romitaSpeechRecognition) {
        romitaSpeechRecognition.stop();
        return;
    }

    try {
        romitaSpeechRecognition = new SpeechRec();
        romitaSpeechRecognition.lang = 'es-PE';
        romitaSpeechRecognition.continuous = true;
        romitaSpeechRecognition.interimResults = true;

        romitaSpeechRecognition.onstart = function() {
            romitaIsListening = true;
            if (micBtn) {
                micBtn.classList.add('is-recording');
                micBtn.innerHTML = '<i class="ph-fill ph-microphone"></i>';
                micBtn.title = 'Escuchando... Haz clic para detener';
            }
        };

        romitaSpeechRecognition.onresult = function(event) {
            let finalTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                }
            }
            if (finalTranscript && input) {
                const cur = input.value.trim();
                input.value = cur ? (cur + ' ' + finalTranscript.trim()) : finalTranscript.trim();
                autoGrowRomitaTextarea(input);
            }
        };

        romitaSpeechRecognition.onerror = function(event) {
            console.warn('Speech recognition error:', event.error);
            stopRomitaVoiceRecognition();
        };

        romitaSpeechRecognition.onend = function() {
            stopRomitaVoiceRecognition();
        };

        romitaSpeechRecognition.start();
    } catch (e) {
        console.warn('Error starting speech:', e);
        stopRomitaVoiceRecognition();
    }
}

function stopRomitaVoiceRecognition() {
    romitaIsListening = false;
    const micBtn = document.getElementById('btn-romita-mic');
    if (micBtn) {
        micBtn.classList.remove('is-recording');
        micBtn.innerHTML = '<i class="ph ph-microphone"></i>';
        micBtn.title = 'Dictar por voz a Romita (Español)';
    }
}

// 7.6 Helpers de Roma Actions (Cards interactivas)
function escapeRomitaHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderRomitaTaskActionCard(jsonContent) {
    try {
        const data = JSON.parse(jsonContent.trim());
        const tasks = data.tasks || [];
        if (!tasks.length) return '';

        const cardId = 'rac-' + Math.random().toString(36).substr(2, 9);
        const encodedData = encodeURIComponent(JSON.stringify(tasks));

        let tasksHtml = '';
        tasks.forEach((t, i) => {
            const urgentBadge = t.is_urgent ? '<span class="rac-badge rac-badge-urgent"><i class="ph-bold ph-warning"></i> Urgente</span>' : '';
            const dueBadge = t.due_date ? `<span class="rac-badge rac-badge-date"><i class="ph ph-calendar"></i> ${escapeRomitaHtml(t.due_date)}</span>` : '';
            
            tasksHtml += `
                <label class="rac-task-item" for="${cardId}-t-${i}">
                    <input type="checkbox" id="${cardId}-t-${i}" class="rac-task-check" checked data-task-index="${i}" onchange="updateRomitaActionTaskCount('${cardId}')">
                    <div class="rac-task-content">
                        <div class="rac-task-header">
                            <span class="rac-task-title">${escapeRomitaHtml(t.title)}</span>
                            <div class="rac-task-tags">
                                ${urgentBadge}
                                ${dueBadge}
                            </div>
                        </div>
                        ${t.description ? `<p class="rac-task-desc">${escapeRomitaHtml(t.description)}</p>` : ''}
                    </div>
                </label>
            `;
        });

        return `
            <div class="romita-action-card rac-tasks-card" id="${cardId}" data-raw-tasks="${encodedData}">
                <div class="rac-header">
                    <div class="rac-header-left">
                        <span class="rac-icon-pill rac-icon-kanban"><i class="ph-bold ph-kanban"></i></span>
                        <div class="rac-header-titles">
                            <strong class="rac-title">Acción: Crear tareas en Kanban</strong>
                            <span class="rac-sub" id="${cardId}-sub">${tasks.length} tareas listas para asignar</span>
                        </div>
                    </div>
                    <span class="rac-chip-status"><i class="ph-bold ph-sparkle"></i> IA Copilot</span>
                </div>
                <div class="rac-body">
                    <div class="rac-tasks-list">
                        ${tasksHtml}
                    </div>
                </div>
                <div class="rac-footer">
                    <button type="button" class="btn-rac-execute" onclick="executeRomitaCreateTasks('${cardId}')">
                        <i class="ph-bold ph-plus-circle"></i> <span class="btn-text">Insertar ${tasks.length} tareas en el Kanban</span>
                    </button>
                    <a href="index.php?module=tasks" target="_blank" class="rac-link-kanban" title="Abrir módulo de tareas">
                        Ir al Kanban <i class="ph ph-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        `;
    } catch (e) {
        console.warn('Error parsing create_tasks action:', e);
        return `<pre><code>${jsonContent}</code></pre>`;
    }
}

function renderRomitaMeetingActionCard(jsonContent) {
    try {
        const data = JSON.parse(jsonContent.trim());
        const cardId = 'rac-m-' + Math.random().toString(36).substr(2, 9);
        const encodedData = encodeURIComponent(JSON.stringify(data));

        return `
            <div class="romita-action-card rac-meeting-card" id="${cardId}" data-raw-meeting="${encodedData}">
                <div class="rac-header">
                    <div class="rac-header-left">
                        <span class="rac-icon-pill rac-icon-meeting"><i class="ph-bold ph-calendar-plus"></i></span>
                        <div class="rac-header-titles">
                            <strong class="rac-title">Acción: Agendar Reunión</strong>
                            <span class="rac-sub">Programación en agenda de Roma</span>
                        </div>
                    </div>
                    <span class="rac-chip-status"><i class="ph-bold ph-calendar-check"></i> Agenda</span>
                </div>
                <div class="rac-body">
                    <div class="rac-meeting-details">
                        <div class="rac-detail-row">
                            <span class="rac-label"><i class="ph ph-notepad"></i> Motivo:</span>
                            <span class="rac-value"><strong>${escapeRomitaHtml(data.motivo || 'Sesión de trabajo')}</strong></span>
                        </div>
                        <div class="rac-detail-row">
                            <span class="rac-label"><i class="ph ph-clock"></i> Fecha y Hora:</span>
                            <span class="rac-value rac-highlight-date">${escapeRomitaHtml(data.fecha_hora || 'Pendiente por coordinar')}</span>
                        </div>
                        ${data.meet_link ? `
                        <div class="rac-detail-row">
                            <span class="rac-label"><i class="ph ph-video-camera"></i> Meet:</span>
                            <span class="rac-value"><a href="${escapeRomitaHtml(data.meet_link)}" target="_blank" class="rac-meet-link">${escapeRomitaHtml(data.meet_link)}</a></span>
                        </div>
                        ` : ''}
                        ${data.resumen ? `
                        <div class="rac-detail-row">
                            <span class="rac-label"><i class="ph ph-text-align-left"></i> Resumen:</span>
                            <span class="rac-value">${escapeRomitaHtml(data.resumen)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="rac-footer">
                    <button type="button" class="btn-rac-execute btn-rac-meeting" onclick="executeRomitaScheduleMeeting('${cardId}')">
                        <i class="ph-bold ph-calendar-plus"></i> <span class="btn-text">Guardar en Agenda de Reuniones</span>
                    </button>
                    <a href="index.php?module=reuniones" target="_blank" class="rac-link-kanban" title="Abrir agenda de reuniones">
                        Ver Agenda <i class="ph ph-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        `;
    } catch (e) {
        return `<pre><code>${jsonContent}</code></pre>`;
    }
}

function renderRomitaWhatsappActionCard(jsonContent) {
    try {
        const data = JSON.parse(jsonContent.trim());
        const cardId = 'rac-w-' + Math.random().toString(36).substr(2, 9);
        const msg = data.message || '';
        const recipient = data.recipient_name || 'Cliente';
        const waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(msg);
        const safeEncodedMsg = encodeURIComponent(msg);

        return `
            <div class="romita-action-card rac-whatsapp-card" id="${cardId}">
                <div class="rac-header">
                    <div class="rac-header-left">
                        <span class="rac-icon-pill rac-icon-whatsapp"><i class="ph-bold ph-whatsapp-logo"></i></span>
                        <div class="rac-header-titles">
                            <strong class="rac-title">Mensaje listo para WhatsApp</strong>
                            <span class="rac-sub">Para: ${escapeRomitaHtml(recipient)}</span>
                        </div>
                    </div>
                    <span class="rac-chip-status rac-chip-wa"><i class="ph-bold ph-paper-plane-tilt"></i> WhatsApp</span>
                </div>
                <div class="rac-body">
                    <div class="rac-wa-balloon">
                        <div class="rac-wa-balloon-inner">
                            ${escapeRomitaHtml(msg).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
                <div class="rac-footer">
                    <a href="${waUrl}" target="_blank" class="btn-rac-execute btn-rac-wa">
                        <i class="ph-bold ph-whatsapp-logo"></i> <span class="btn-text">Enviar por WhatsApp</span>
                    </a>
                    <button type="button" class="btn-rac-copy" onclick="copyActionCardText(this, '${safeEncodedMsg}')">
                        <i class="ph ph-copy"></i> Copiar texto
                    </button>
                </div>
            </div>
        `;
    } catch (e) {
        return `<pre><code>${jsonContent}</code></pre>`;
    }
}

function updateRomitaActionTaskCount(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;
    const checks = card.querySelectorAll('.rac-task-check:checked');
    const total = card.querySelectorAll('.rac-task-check').length;
    const subEl = document.getElementById(cardId + '-sub');
    if (subEl) subEl.innerText = `${checks.length} de ${total} tareas seleccionadas`;
    const btn = card.querySelector('.btn-rac-execute .btn-text');
    if (btn) btn.innerText = `Insertar ${checks.length} tareas en el Kanban`;
}

function copyActionCardText(btn, encodedText) {
    const text = decodeURIComponent(encodedText);
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-check"></i> ¡Copiado!';
        btn.style.color = '#16a34a';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.color = '';
        }, 1800);
    });
}

async function executeRomitaCreateTasks(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;

    const rawData = card.getAttribute('data-raw-tasks');
    if (!rawData) return;

    const allTasks = JSON.parse(decodeURIComponent(rawData));
    const checkboxes = card.querySelectorAll('.rac-task-check:checked');
    const selectedIndices = Array.from(checkboxes).map(c => parseInt(c.getAttribute('data-task-index')));
    const selectedTasks = allTasks.filter((_, i) => selectedIndices.includes(i));

    if (!selectedTasks.length) {
        alert('Por favor selecciona al menos una tarea para crear.');
        return;
    }

    const btn = card.querySelector('.btn-rac-execute');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Creando en Kanban...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'tool_create_tasks');
        formData.append('tasks', JSON.stringify(selectedTasks));

        const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            card.querySelectorAll('.rac-task-check').forEach(c => c.disabled = true);
            const footer = card.querySelector('.rac-footer');
            if (footer) {
                footer.innerHTML = `
                    <div class="rac-success-banner">
                        <i class="ph-fill ph-check-circle"></i>
                        <span>¡${data.count} ${data.count === 1 ? 'tarea creada' : 'tareas creadas'} exitosamente!</span>
                        <a href="index.php?module=tasks" target="_blank" class="rac-btn-view">
                            Abrir Kanban <i class="ph ph-arrow-up-right"></i>
                        </a>
                    </div>
                `;
            }
        } else {
            alert(data.error || 'Error al crear tareas');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    } catch (err) {
        alert('Error de conexión');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

async function executeRomitaScheduleMeeting(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;

    const rawData = card.getAttribute('data-raw-meeting');
    if (!rawData) return;
    const meetingData = JSON.parse(decodeURIComponent(rawData));

    const btn = card.querySelector('.btn-rac-execute');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando reunión...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'tool_schedule_meeting');
        formData.append('motivo', meetingData.motivo || '');
        formData.append('fecha_hora', meetingData.fecha_hora || '');
        formData.append('meet_link', meetingData.meet_link || '');
        formData.append('resumen', meetingData.resumen || '');
        if (meetingData.brand_id) formData.append('brand_id', meetingData.brand_id);

        const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const footer = card.querySelector('.rac-footer');
            if (footer) {
                footer.innerHTML = `
                    <div class="rac-success-banner">
                        <i class="ph-fill ph-check-circle"></i>
                        <span>¡Reunión agendada exitosamente!</span>
                        <a href="index.php?module=reuniones" target="_blank" class="rac-btn-view">
                            Ver en Agenda <i class="ph ph-arrow-up-right"></i>
                        </a>
                    </div>
                `;
            }
        } else {
            alert(data.error || 'Error al agendar reunión');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    } catch (err) {
        alert('Error de conexión');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

// Preprocesador para reparar tablas Markdown que omitan fila separadora
function preprocessMarkdownTables(text) {
    if (!text || !text.includes('|')) return text;
    const lines = text.split('\n');
    const result = [];
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        result.push(line);
        
        const trimmed = line.trim();
        const isPipeRow = trimmed.startsWith('|') && trimmed.endsWith('|');
        
        if (isPipeRow && i + 1 < lines.length) {
            const nextTrimmed = lines[i + 1].trim();
            const nextIsPipeRow = nextTrimmed.startsWith('|') && nextTrimmed.endsWith('|');
            const nextIsSeparator = /^\|(\s*:?-+:?\s*\|)+$/.test(nextTrimmed);
            
            const prevTrimmed = i > 0 ? lines[i - 1].trim() : '';
            const prevIsPipeRow = prevTrimmed.startsWith('|') && prevTrimmed.endsWith('|');
            
            // Si es la primera fila de una tabla y no hay fila separadora debajo
            if (!prevIsPipeRow && nextIsPipeRow && !nextIsSeparator) {
                const colCount = trimmed.split('|').length - 2;
                if (colCount > 1) {
                    result.push('|' + ' :--- |'.repeat(colCount));
                }
            }
        }
    }
    return result.join('\n');
}

// 8. Markdown Parser Avanzado con soporte para Acciones Agénticas, Tablas, Listas y Código
function renderRomitaMarkdown(text) {
    if (!text) return '';
    let out = preprocessMarkdownTables(text);

    // Interceptar bloques agénticos de Romita Action antes de marked
    out = out.replace(/```romita-action:create_tasks\s*([\s\S]*?)```/g, function(match, jsonContent) {
        return renderRomitaTaskActionCard(jsonContent);
    });

    out = out.replace(/```romita-action:schedule_meeting\s*([\s\S]*?)```/g, function(match, jsonContent) {
        return renderRomitaMeetingActionCard(jsonContent);
    });

    out = out.replace(/```romita-action:whatsapp_message\s*([\s\S]*?)```/g, function(match, jsonContent) {
        return renderRomitaWhatsappActionCard(jsonContent);
    });

    // Si marked.js está cargado, usar su motor completo GFM
    if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
        try {
            out = out.replace(/```json:calendar_plan[\s\S]*?```/g, '<div class="alert alert-info" style="font-size:0.8rem; margin:8px 0;"><i class="ph ph-calendar-check"></i> Plan de calendario estructurado generado.</div>');
            return marked.parse(out);
        } catch (e) {
            console.warn('Error al parsear con marked:', e);
        }
    }

    // Parser nativo de contingencia con soporte para tablas
    out = out.replace(/```json:calendar_plan[\s\S]*?```/g, '<div class="alert alert-info" style="font-size:0.8rem; margin:8px 0;"><i class="ph ph-calendar-check"></i> Plan de calendario estructurado generado.</div>');

    // Tablas Markdown
    out = out.replace(/((?:^[ \t]*\|[^\n]+\|[ \t]*(?:\r?\n|$))+)/gm, function(tableBlock) {
        const rows = tableBlock.trim().split('\n').map(r => r.trim()).filter(r => r.length > 0);
        if (rows.length < 2) return tableBlock;

        let tableHtml = '<table>';
        let hasHeader = false;
        let inBody = false;

        for (let r = 0; r < rows.length; r++) {
            const rowStr = rows[r];
            if (/^\|(\s*:?-+:?\s*\|)+$/.test(rowStr)) {
                hasHeader = true;
                continue;
            }

            let cells = rowStr.split('|').map(c => c.trim());
            if (rowStr.startsWith('|')) cells.shift();
            if (rowStr.endsWith('|')) cells.pop();

            if (!hasHeader && r === 0 && rows.length > 1 && /^\|(\s*:?-+:?\s*\|)+$/.test(rows[1])) {
                tableHtml += '<thead><tr>';
                cells.forEach(c => {
                    let formatted = c.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\*([^*]+)\*/g, '<em>$1</em>');
                    tableHtml += `<th>${formatted}</th>`;
                });
                tableHtml += '</tr></thead>';
                tableHtml += '<tbody>';
                inBody = true;
            } else {
                if (!inBody) {
                    tableHtml += '<tbody>';
                    inBody = true;
                }
                tableHtml += '<tr>';
                cells.forEach(c => {
                    let formatted = c.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\*([^*]+)\*/g, '<em>$1</em>');
                    tableHtml += `<td>${formatted}</td>`;
                });
                tableHtml += '</tr>';
            }
        }
        if (inBody) tableHtml += '</tbody>';
        tableHtml += '</table>';
        return tableHtml;
    });

    // Code blocks
    out = out.replace(/```([a-z]*)\n([\s\S]*?)```/g, function(match, lang, code) {
        const cleanLang = lang ? ` class="language-${lang}"` : '';
        return `<pre><code${cleanLang}>` + code.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code></pre>';
    });

    // Inline code
    out = out.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bold & Italic
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');

    // Headers
    out = out.replace(/^### (.*$)/gim, '<h4 style="margin:10px 0 4px 0; font-size:0.95rem; font-weight:700; color:inherit;">$1</h4>');
    out = out.replace(/^## (.*$)/gim, '<h3 style="margin:12px 0 6px 0; font-size:1.05rem; font-weight:700; color:inherit;">$1</h3>');
    out = out.replace(/^# (.*$)/gim, '<h2 style="margin:14px 0 8px 0; font-size:1.15rem; font-weight:800; color:inherit;">$1</h2>');

    // Lists
    out = out.replace(/^\s*[-•]\s+(.*)$/gim, '<li>$1</li>');
    out = out.replace(/(<li>.*<\/li>)/gim, '<ul>$1</ul>');
    out = out.replace(/<\/ul>\s*<ul>/g, '');

    // Line breaks
    out = out.replace(/\n\n/g, '<br><br>');
    out = out.replace(/\n/g, '<br>');

    return out;
}

// Copiar tabla como TSV (directamente compatible con Excel / Google Sheets)
function copyTableToClipboard(btn) {
    const container = btn.closest('.romita-table-container');
    if (!container) return;
    const table = container.querySelector('table');
    if (!table) return;

    let tsv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(r => {
        let cols = [];
        r.querySelectorAll('th, td').forEach(c => {
            let text = c.innerText.trim().replace(/\r?\n|\r/g, ' ').replace(/\t/g, ' ');
            cols.push(text);
        });
        tsv.push(cols.join('\t'));
    });

    const textToCopy = tsv.join('\n');
    navigator.clipboard.writeText(textToCopy).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check" style="color:#10b981;"></i> <span>¡Copiado para Excel!</span>';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2200);
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = textToCopy;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Tabla copiada al portapapeles');
    });
}

// Alternar pantalla completa para tablas anchas
function toggleTableFullscreen(btn) {
    const container = btn.closest('.romita-table-container');
    if (!container) return;
    
    container.classList.toggle('is-fullscreen');
    const icon = btn.querySelector('i');
    if (container.classList.contains('is-fullscreen')) {
        if (icon) icon.className = 'ph ph-corners-in';
        document.body.style.overflow = 'hidden';
    } else {
        if (icon) icon.className = 'ph ph-arrows-out-simple';
        document.body.style.overflow = '';
    }
}

// Copiar snippet de bloque de código
function copySnippet(btn) {
    const code = btn.closest('.code-block-wrapper').querySelector('code');
    if (!code) return;
    navigator.clipboard.writeText(code.innerText).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check" style="color:#10b981;"></i> Copiado';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

// Procesar formateo avanzado para bloques de código y tablas interactivas
function processRomitaAssistantFormatting(container) {
    if (!container) return;

    // 1. Estilizar bloques de código normales
    const codeBlocks = container.querySelectorAll('pre code');
    codeBlocks.forEach(code => {
        const pre = code.parentElement;
        if (!pre || pre.parentElement.classList.contains('code-block-wrapper') || pre.style.display === 'none') return;

        let lang = 'CÓDIGO';
        const classes = code.className ? code.className.split(' ') : [];
        for (let c of classes) {
            if (c.startsWith('language-')) {
                lang = c.replace('language-', '').toUpperCase();
                break;
            }
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';
        wrapper.innerHTML = `
            <div class="code-block-header">
                <span>${lang}</span>
                <button type="button" class="btn-copy-code" onclick="copySnippet(this)">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
        `;
        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);
    });

    // 2. Tablas ordenables, responsivas con toolbar y exportación para Excel
    const tables = container.querySelectorAll('table');
    tables.forEach(table => {
        if (table.closest('.romita-table-container')) return;

        const rowsCount = table.querySelectorAll('tbody tr').length || Math.max(0, table.querySelectorAll('tr').length - 1);
        const firstRow = table.querySelector('thead tr') || table.querySelector('tr');
        const colsCount = firstRow ? firstRow.querySelectorAll('th, td').length : 0;

        const card = document.createElement('div');
        card.className = 'romita-table-container';
        card.innerHTML = `
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <i class="ph ph-table"></i>
                    <span class="table-info-badge">${rowsCount} filas • ${colsCount} columnas</span>
                    <span class="table-scroll-hint"><i class="ph ph-arrows-left-right"></i> Desliza</span>
                </div>
                <div class="table-toolbar-actions">
                    <button type="button" class="btn-table-action" onclick="copyTableToClipboard(this)" title="Copiar como tabla (compatible con Excel / Google Sheets)">
                        <i class="ph ph-file-csv"></i> <span class="btn-text-full">Copiar para Excel</span><span class="btn-text-short">Excel</span>
                    </button>
                    <button type="button" class="btn-table-action" onclick="toggleTableFullscreen(this)" title="Pantalla completa">
                        <i class="ph ph-arrows-out-simple"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive-wrapper"></div>
        `;

        table.parentNode.insertBefore(card, table);
        const scrollWrap = card.querySelector('.table-responsive-wrapper');
        scrollWrap.appendChild(table);

        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            const titleText = (header.textContent || '').trim().toLowerCase();
            let colClass = '';
            if (titleText.includes('copy') || titleText.includes('descrip') || titleText.includes('especific') || titleText.includes('guion') || titleText.includes('texto') || titleText.includes('contenido')) {
                colClass = 'col-wide';
            } else if (titleText.includes('gancho') || titleText.includes('hook') || titleText.includes('concepto') || titleText.includes('pilar') || titleText.includes('idea') || titleText.includes('objetivo')) {
                colClass = 'col-medium';
            } else if ((titleText.includes('fecha') || titleText.includes('día') || titleText.includes('dia') || titleText === 'id' || titleText.includes('estado') || titleText.includes('n°')) && !titleText.includes('anuncio') && !titleText.includes('formato')) {
                colClass = 'col-compact';
            }
            
            if (colClass) {
                header.classList.add(colClass);
                const allRows = table.querySelectorAll('tbody tr, tr');
                allRows.forEach(r => {
                    if (r.children[index] && r.children[index].tagName !== 'TH') {
                        r.children[index].classList.add(colClass);
                    }
                });
            }

            header.style.cursor = 'pointer';
            header.title = 'Clic para ordenar por esta columna';
            header.addEventListener('click', () => {
                const tbody = table.querySelector('tbody') || table;
                const rows = Array.from(tbody.querySelectorAll('tr:nth-child(n+2)'));
                const isAsc = header.classList.contains('asc');
                
                headers.forEach(h => { h.classList.remove('asc', 'desc'); h.innerHTML = h.innerHTML.replace(' 🔼','').replace(' 🔽',''); });
                header.classList.add(isAsc ? 'desc' : 'asc');
                header.innerHTML += isAsc ? ' 🔽' : ' 🔼';
                
                rows.sort((a, b) => {
                    const aCol = a.children[index] ? a.children[index].textContent.trim() : '';
                    const bCol = b.children[index] ? b.children[index].textContent.trim() : '';
                    if(!isNaN(aCol) && !isNaN(bCol) && aCol !== '' && bCol !== '') return isAsc ? bCol - aCol : aCol - bCol;
                    return isAsc ? bCol.localeCompare(aCol) : aCol.localeCompare(bCol);
                });
                
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });
}

// Helper to copy message text to clipboard
function copyRomitaMessage(btn) {
    const bubble = btn.closest('.rg-msg-content-wrap').querySelector('.rg-msg-bubble');
    if (!bubble) return;
    const text = bubble.innerText;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check"></i> Copiado';
        btn.style.color = '#10b981';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.style.color = '';
        }, 1800);
    });
}

// 9. Send Message
async function sendRomitaMessage() {
    if (romitaIsLoading) return;
    const input = document.getElementById('romita-chat-input');
    if (!input) return;
    const message = input.value.trim();
    if (!message) return;

    // Clear input
    input.value = '';
    input.style.height = 'auto';

    // Hide welcome view
    const welcome = document.getElementById('romita-welcome-view');
    if (welcome) welcome.style.display = 'none';

    // Append User Message
    const chatContainer = document.getElementById('romita-chat-messages');
    const userDiv = document.createElement('div');
    userDiv.className = 'rg-msg rg-msg-user';
    userDiv.innerHTML = `
        <div class="rg-msg-avatar"><i class="ph ph-user"></i></div>
        <div class="rg-msg-content-wrap">
            <div class="rg-msg-bubble">${message.replace(/\n/g, '<br>')}</div>
        </div>
    `;
    chatContainer.appendChild(userDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;

    romitaIsLoading = true;

    // Window and in-composer active generating effects
    const dialog = document.getElementById('romita-global-dialog');
    if (dialog) dialog.classList.add('is-generating');

    const inputBox = document.getElementById('romita-chat-input-box');
    if (inputBox) inputBox.classList.add('is-generating');

    const sendBtn = document.getElementById('btn-romita-send');
    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
    }

    // Dynamic futuristic status phrase cycling (shown directly in composer)
    const statusPhrases = [
        'Romita está procesando el contexto...',
        'Consultando base de proyectos y ecosistema...',
        'Analizando pilares estratégicos y métricas...',
        'Sintetizando propuesta de alto impacto...',
        'Generando respuesta final...'
    ];
    let phraseIdx = 0;
    const initialStatusEl = document.getElementById('rg-neural-status-text');
    if (initialStatusEl) initialStatusEl.innerText = statusPhrases[0];

    if (romitaStatusInterval) clearInterval(romitaStatusInterval);
    romitaStatusInterval = setInterval(() => {
        phraseIdx = (phraseIdx + 1) % statusPhrases.length;
        const statusEl = document.getElementById('rg-neural-status-text');
        if (statusEl) {
            statusEl.style.opacity = '0';
            setTimeout(() => {
                if (statusEl) {
                    statusEl.innerText = statusPhrases[phraseIdx];
                    statusEl.style.opacity = '1';
                }
            }, 180);
        }
    }, 2200);

    // Build context
    const ctx = getRomitaScreenContext();
    const formData = new FormData();
    formData.append('action', 'chat');
    formData.append('message', message);
    formData.append('specialty', romitaSpecialty);
    formData.append('current_module', ctx.module);
    formData.append('entity_id', ctx.id);
    if (romitaCurrentChatId) {
        formData.append('chat_id', romitaCurrentChatId);
    }

    try {
        const response = await fetch('ajax/ajax_romita.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        // Remove typing indicator & stop phrase cycling
        if (romitaStatusInterval) {
            clearInterval(romitaStatusInterval);
            romitaStatusInterval = null;
        }
        const tInd = document.getElementById('romita-typing-indicator');
        if (tInd) tInd.remove();

        if (data.success && data.response) {
            romitaCurrentChatId = data.chat_id || romitaCurrentChatId;
            const aiDiv = document.createElement('div');
            aiDiv.className = 'rg-msg rg-msg-ai';
            aiDiv.innerHTML = `
                <div class="rg-msg-avatar"><img src="assets/img/romita-avatar.png" alt="Romita" class="rg-msg-avatar-img"></div>
                <div class="rg-msg-content-wrap">
                    <div class="rg-msg-bubble markdown-body">${renderRomitaMarkdown(data.response)}</div>
                    <div class="rg-msg-actions">
                        <button type="button" class="rg-copy-bubble-btn" onclick="copyRomitaMessage(this)" title="Copiar respuesta">
                            <i class="ph ph-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            `;
            chatContainer.appendChild(aiDiv);
            processRomitaAssistantFormatting(aiDiv);
        } else {
            const errDiv = document.createElement('div');
            errDiv.className = 'rg-msg rg-msg-ai';
            errDiv.innerHTML = `
                <div class="rg-msg-avatar" style="background:#ef4444;"><i class="ph ph-warning-circle"></i></div>
                <div class="rg-msg-content-wrap">
                    <div class="rg-msg-bubble" style="border-color:#ef4444; color:#ef4444;">${data.error || 'Error al conectar con Romita.'}</div>
                </div>
            `;
            chatContainer.appendChild(errDiv);
        }
    } catch (err) {
        if (romitaStatusInterval) {
            clearInterval(romitaStatusInterval);
            romitaStatusInterval = null;
        }
        const tInd = document.getElementById('romita-typing-indicator');
        if (tInd) tInd.remove();

        const errDiv = document.createElement('div');
        errDiv.className = 'rg-msg rg-msg-ai';
        errDiv.innerHTML = `
            <div class="rg-msg-avatar" style="background:#ef4444;"><i class="ph ph-warning-circle"></i></div>
            <div class="rg-msg-content-wrap">
                <div class="rg-msg-bubble" style="border-color:#ef4444; color:#ef4444;">Error de conexión con el servidor.</div>
            </div>
        `;
        chatContainer.appendChild(errDiv);
    } finally {
        romitaIsLoading = false;
        if (romitaStatusInterval) {
            clearInterval(romitaStatusInterval);
            romitaStatusInterval = null;
        }
        if (dialog) dialog.classList.remove('is-generating');
        if (inputBox) inputBox.classList.remove('is-generating');
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="ph-bold ph-arrow-up"></i>';
        }
        chatContainer.scrollTop = chatContainer.scrollHeight;
        if (input) input.focus();
    }
}

// 10. Clear Chat
function clearRomitaCurrentChat() {
    romitaCurrentChatId = null;
    if (romitaSpecialty === 'director_360') {
        renderModuleWelcome();
    } else {
        renderSpecialtyWelcome(romitaSpecialty);
    }
}

// 11. Global Keyboard Shortcut Listener (Tecla R / Escape)
document.addEventListener('keydown', function(e) {
    // Escape cierra Romita o la vista de pantalla completa de tablas
    if (e.key === 'Escape') {
        const fullTable = document.querySelector('.romita-table-container.is-fullscreen');
        if (fullTable) {
            fullTable.classList.remove('is-fullscreen');
            const fsIcon = fullTable.querySelector('.btn-table-action[onclick*="toggleTableFullscreen"] i');
            if (fsIcon) fsIcon.className = 'ph ph-arrows-out-simple';
            document.body.style.overflow = '';
            return;
        }
        const overlay = document.getElementById('romita-global-overlay');
        if (overlay && overlay.style.display !== 'none') {
            closeRomitaGlobalModal();
        }
        return;
    }

    // Comprobar si el foco está en un campo de texto / editor
    const activeEl = document.activeElement;
    const isEditing = activeEl && (
        activeEl.tagName === 'INPUT' ||
        activeEl.tagName === 'TEXTAREA' ||
        activeEl.tagName === 'SELECT' ||
        activeEl.isContentEditable ||
        (activeEl.getAttribute && activeEl.getAttribute('contenteditable') === 'true') ||
        activeEl.classList.contains('ck-editor__editable') ||
        activeEl.classList.contains('note-editable') ||
        activeEl.classList.contains('ql-editor') ||
        activeEl.closest('[contenteditable="true"]')
    );

    // Si el usuario está escribiendo texto, nunca interceptar la letra 'r'
    if (isEditing) {
        return;
    }

    // Preservar comandos de sistema como Ctrl+R (recargar), Alt+R, Cmd+R
    if (e.ctrlKey || e.altKey || e.metaKey) {
        return;
    }

    // Al presionar R o r se abre / alterna Romita
    if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        toggleRomitaGlobalModal();
    }
});
</script>
