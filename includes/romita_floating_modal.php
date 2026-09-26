<?php
// includes/romita_floating_modal.php
// Romita AI Global Floating Assistant & Command Palette (Tecla R)
if (!isset($_SESSION['user_id'])) return;
?>

<!-- Floating Trigger Button (FAB) -->
<div id="romita-fab-container" class="romita-fab-container">
    <button type="button" id="romita-fab-btn" class="romita-fab-btn" onclick="toggleRomitaGlobalModal()" aria-label="Abrir Romita IA">
        <span class="romita-fab-glow"></span>
        <div class="romita-fab-avatar">
            <i class="ph-bold ph-sparkle"></i>
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
        <div class="rg-dialog-laser"></div>
        
        <!-- App Header (Native macOS / Modern SaaS style) -->
        <div class="rg-header">
            <div class="rg-header-left">
                <div class="rg-avatar-badge">
                    <i class="ph-bold ph-sparkle"></i>
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
            <div class="rg-header-laser"></div>
        </div>

        <!-- Specialties Selector (Segmented App Bar - Clean, Zero Emojis) -->
        <div class="rg-specialties-container">
            <div class="rg-specialties-bar">
                <button type="button" class="rg-spec-tab active" data-spec="director_360" onclick="setRomitaSpecialty('director_360', this)">
                    <i class="ph ph-compass"></i>
                    <span>Directora 360°</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="community_manager" onclick="setRomitaSpecialty('community_manager', this)">
                    <i class="ph ph-chat-circle-dots"></i>
                    <span>Senior CM</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="branding" onclick="setRomitaSpecialty('branding', this)">
                    <i class="ph ph-palette"></i>
                    <span>Branding</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="marketing" onclick="setRomitaSpecialty('marketing', this)">
                    <i class="ph ph-trend-up"></i>
                    <span>Marketing</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="seo" onclick="setRomitaSpecialty('seo', this)">
                    <i class="ph ph-magnifying-glass"></i>
                    <span>SEO</span>
                </button>
            </div>
        </div>

        <!-- Chat Scroll Area -->
        <div class="rg-chat-body" id="romita-chat-messages">
            <!-- Starter Welcome & Suggestions -->
            <div id="romita-welcome-view" class="rg-welcome-view">
                <div class="rg-welcome-orb">
                    <div class="rg-orb-glow"></div>
                    <div class="rg-orb-icon">
                        <i class="ph-bold ph-sparkle"></i>
                    </div>
                </div>
                <h3 class="rg-welcome-title">¿En qué podemos colaborar hoy?</h3>
                <p class="rg-welcome-subtitle" id="romita-welcome-desc">
                    Asistente de inteligencia conectada a Calendarios, Marcas, Web, Audiovisual y Pizarras.
                </p>

                <div class="rg-suggestions-grid">
                    <button type="button" class="rg-suggestion-card" onclick="sendRomitaQuickPrompt('¿Qué proyectos tenemos activos actualmente en la agencia?')">
                        <span class="rg-card-icon"><i class="ph ph-kanban"></i></span>
                        <div class="rg-card-text">
                            <strong>Proyectos activos</strong>
                            <small>Resumen de producción de la agencia</small>
                        </div>
                    </button>
                    <button type="button" class="rg-suggestion-card" onclick="sendRomitaQuickPrompt('Dame 3 ideas creativas de contenido con gancho para las marcas de este mes')">
                        <span class="rg-card-icon"><i class="ph ph-lightbulb"></i></span>
                        <div class="rg-card-text">
                            <strong>Ideas de contenido</strong>
                            <small>Estrategia con ganchos para el mes</small>
                        </div>
                    </button>
                    <button type="button" class="rg-suggestion-card" onclick="sendRomitaQuickPrompt('¿Cómo podemos estructurar un embudo de ventas TOFU-MOFU-BOFU de alta conversión?')">
                        <span class="rg-card-icon"><i class="ph ph-funnel"></i></span>
                        <div class="rg-card-text">
                            <strong>Embudo de conversión</strong>
                            <small>Estructura estratégica TOFU-MOFU-BOFU</small>
                        </div>
                    </button>
                    <button type="button" class="rg-suggestion-card" onclick="sendRomitaQuickPrompt('Revisa las mejores prácticas de SEO para optimizar las páginas de servicios')">
                        <span class="rg-card-icon"><i class="ph ph-chart-line-up"></i></span>
                        <div class="rg-card-text">
                            <strong>Optimización SEO</strong>
                            <small>Directrices para páginas de servicios</small>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Input Bar (Modern Floating App Style) -->
        <div class="rg-footer">
            <div class="rg-input-box" id="romita-chat-input-box">
                <textarea id="romita-chat-input" class="rg-textarea" rows="1" placeholder="Escribe tu consulta a Romita..." onkeydown="handleRomitaInputKeydown(event)" oninput="autoGrowRomitaTextarea(this)"></textarea>
                <div class="rg-input-actions">
                    <button type="button" id="btn-romita-send" class="rg-send-btn" onclick="sendRomitaMessage()" aria-label="Enviar mensaje">
                        <i class="ph-bold ph-arrow-up"></i>
                    </button>
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
    background: transparent;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    outline: none;
    box-shadow: 0 10px 28px -6px rgba(99, 102, 241, 0.5);
}

.romita-fab-btn:hover {
    transform: scale(1.06) translateY(-2px);
    box-shadow: 0 16px 36px -6px rgba(99, 102, 241, 0.65);
}

.romita-fab-btn:active {
    transform: scale(0.96);
}

.romita-fab-glow {
    position: absolute;
    inset: -2px;
    border-radius: 18px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
    filter: blur(8px);
    opacity: 0.5;
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
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #9333ea 100%);
    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.35rem;
    z-index: 2;
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

.romita-global-dialog.drawer-mode .rg-specialties-container {
    padding: 8px 12px;
}

.romita-global-dialog.drawer-mode .rg-specialties-bar {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 3px;
    padding: 3px;
    overflow: hidden;
}

.romita-global-dialog.drawer-mode .rg-spec-tab {
    padding: 6px 2px;
    font-size: 0.72rem;
    gap: 4px;
    justify-content: center;
}

.romita-global-dialog.drawer-mode .rg-spec-tab span {
    font-size: 0.69rem;
}

.romita-global-dialog.drawer-mode .rg-chat-body {
    padding: 16px 14px;
    overflow-x: hidden !important;
}

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
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.15rem;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.4);
    flex-shrink: 0;
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
    color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 1px 5px;
    border-radius: 5px;
    line-height: 1;
    letter-spacing: 0.04em;
}

[data-theme="dark"] .rg-version-tag {
    color: #a5b4fc;
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.25);
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
    color: #6366f1;
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

/* Specialties Bar (Segmented Control - Native SaaS App) */
.rg-specialties-container {
    padding: 10px 18px 8px 18px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}

[data-theme="dark"] .rg-specialties-container {
    background: #14141a;
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.rg-specialties-bar {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 11px;
    border: 1px solid #e2e8f0;
    overflow-x: auto;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}

.rg-specialties-bar::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

[data-theme="dark"] .rg-specialties-bar {
    background: rgba(0, 0, 0, 0.35);
    border-color: rgba(255, 255, 255, 0.07);
}

.rg-spec-tab {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 11px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

[data-theme="dark"] .rg-spec-tab {
    color: #94a3b8;
}

.rg-spec-tab:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.4);
}

[data-theme="dark"] .rg-spec-tab:hover {
    color: #f1f5f9;
    background: rgba(255, 255, 255, 0.05);
}

.rg-spec-tab i {
    font-size: 0.95rem;
    opacity: 0.85;
    transition: transform 0.2s ease;
}

.rg-spec-tab.active {
    background: #ffffff;
    color: #4f46e5 !important;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .rg-spec-tab.active {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.rg-spec-tab.active i {
    color: #6366f1;
    opacity: 1;
    transform: scale(1.08);
}

[data-theme="dark"] .rg-spec-tab.active i {
    color: #818cf8;
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

/* Welcome View */
.rg-welcome-view {
    margin: auto 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem 0.5rem;
    gap: 0.75rem;
}

.rg-welcome-orb {
    position: relative;
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.35rem;
}

.rg-orb-glow {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.45) 0%, rgba(139, 92, 246, 0.15) 70%, transparent 100%);
    filter: blur(8px);
}

.rg-orb-icon {
    position: relative;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.4rem;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.5);
}

.rg-welcome-title {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.025em;
}

[data-theme="dark"] .rg-welcome-title {
    color: #f8fafc;
}

.rg-welcome-subtitle {
    margin: 0;
    max-width: 540px;
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.55;
}

[data-theme="dark"] .rg-welcome-subtitle {
    color: #94a3b8;
}

.rg-suggestions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    width: 100%;
    max-width: 700px;
    margin-top: 1.25rem;
}

@media (max-width: 600px) {
    .rg-suggestions-grid {
        grid-template-columns: 1fr;
    }
}

.rg-suggestion-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
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
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .rg-suggestion-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
}

.rg-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

[data-theme="dark"] .rg-card-icon {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.rg-suggestion-card:hover .rg-card-icon {
    background: #6366f1;
    color: #ffffff;
}

.rg-card-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}

.rg-card-text strong {
    font-size: 0.8rem;
    font-weight: 600;
    color: #0f172a;
}

[data-theme="dark"] .rg-card-text strong {
    color: #f1f5f9;
}

.rg-card-text small {
    font-size: 0.72rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .rg-card-text small {
    color: #94a3b8;
}

/* Chat Messages */
.rg-msg {
    display: flex;
    gap: 12px;
    max-width: 88%;
    animation: rgMsgIn 0.22s ease-out;
}

@keyframes rgMsgIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.rg-msg-user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.rg-msg-ai {
    align-self: flex-start;
}

.rg-msg-avatar {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}

.rg-msg-ai .rg-msg-avatar {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

.rg-msg-user .rg-msg-avatar {
    background: #0f172a;
    color: #ffffff;
}

[data-theme="dark"] .rg-msg-user .rg-msg-avatar {
    background: #334155;
}

.rg-msg-content-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.rg-msg-bubble {
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 0.86rem;
    line-height: 1.55;
    word-break: break-word;
}

.rg-msg-user .rg-msg-bubble {
    background: #4f46e5;
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.25);
}

.rg-msg-ai .rg-msg-bubble {
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
}

[data-theme="dark"] .rg-msg-ai .rg-msg-bubble {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
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
    margin: 12px 0;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    transition: all 0.25s ease;
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
    top: 1rem;
    left: 1rem;
    right: 1rem;
    bottom: 1rem;
    z-index: 9999999;
    margin: 0;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.65);
    background: #ffffff;
    display: flex;
    flex-direction: column;
}
[data-theme="dark"] .romita-table-container.is-fullscreen {
    background: #11121d;
}
.romita-table-container.is-fullscreen .table-responsive-wrapper {
    max-height: none !important;
    flex: 1;
}
.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.8rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.76rem;
    gap: 0.75rem;
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
}
[data-theme="dark"] .table-toolbar-left {
    color: #94a3b8;
}
.table-info-badge {
    background: #ffffff;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    color: #334155;
}
[data-theme="dark"] .table-info-badge {
    background: #141522;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}
.table-scroll-hint {
    font-size: 0.7rem;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.table-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-table-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
[data-theme="dark"] .btn-table-action {
    background: #25273c;
    border-color: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
}
.btn-table-action:hover {
    border-color: #6366f1;
    color: #6366f1;
}
.table-responsive-wrapper {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 480px;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}
.table-responsive-wrapper::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.table-responsive-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.03);
}
[data-theme="dark"] .table-responsive-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}
.table-responsive-wrapper::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 4px;
}
.table-responsive-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.7);
}
.rg-msg-bubble table {
    width: 100%;
    min-width: 580px;
    border-collapse: collapse;
    font-size: 0.8rem;
    text-align: left;
    margin: 0 !important;
}
.rg-msg-bubble thead {
    background: rgba(99, 102, 241, 0.06);
    position: sticky;
    top: 0;
    z-index: 2;
}
[data-theme="dark"] .rg-msg-bubble thead {
    background: rgba(99, 102, 241, 0.14);
}
.rg-msg-bubble th {
    padding: 0.65rem 0.85rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #4338ca;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 2px solid rgba(99, 102, 241, 0.18);
    border-right: 1px solid rgba(0, 0, 0, 0.04);
    white-space: nowrap;
    user-select: none;
    transition: background 0.15s ease, color 0.15s ease;
}
[data-theme="dark"] .rg-msg-bubble th {
    color: #a5b4fc;
    border-bottom-color: rgba(99, 102, 241, 0.35);
    border-right-color: rgba(255, 255, 255, 0.05);
}
.rg-msg-bubble th:hover {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
[data-theme="dark"] .rg-msg-bubble th:hover {
    background: rgba(99, 102, 241, 0.25);
    color: #c7d2fe;
}
.rg-msg-bubble td {
    padding: 0.6rem 0.85rem;
    font-size: 0.79rem;
    color: inherit;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    border-right: 1px solid rgba(0, 0, 0, 0.04);
    line-height: 1.45;
}
[data-theme="dark"] .rg-msg-bubble td {
    border-bottom-color: rgba(255, 255, 255, 0.05);
    border-right-color: rgba(255, 255, 255, 0.03);
}
.rg-msg-bubble tr:last-child td {
    border-bottom: none;
}
.rg-msg-bubble tbody tr:hover {
    background: rgba(99, 102, 241, 0.035);
}
[data-theme="dark"] .rg-msg-bubble tbody tr:hover {
    background: rgba(99, 102, 241, 0.08);
}

/* ==========================================================================
   FUTURISTIC AI GENERATING ANIMATIONS (NEURAL CORE & LASER SCAN)
   ========================================================================== */

/* 1. Header Laser Scan (Traveling beam when generating) */
.rg-header {
    position: relative;
}

.rg-header-laser {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 2.5px;
    background: linear-gradient(90deg, transparent 0%, #6366f1 20%, #38bdf8 50%, #ec4899 80%, transparent 100%);
    background-size: 250% 100%;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 10;
}

.romita-global-dialog.is-generating .rg-header-laser {
    opacity: 1;
    animation: rgLaserBeam 1.8s infinite linear;
}

@keyframes rgLaserBeam {
    0% { background-position: 250% 0; }
    100% { background-position: -250% 0; }
}

/* Top Dialog Laser Scan */
.rg-dialog-laser {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent 0%, #38bdf8 30%, #818cf8 60%, #ec4899 85%, transparent 100%);
    background-size: 200% 100%;
    opacity: 0;
    pointer-events: none;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    transition: opacity 0.3s ease;
    z-index: 15;
}

.romita-global-dialog.is-generating .rg-dialog-laser {
    opacity: 1;
    animation: rgDialogLaserScan 1.6s infinite linear;
}

@keyframes rgDialogLaserScan {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* 2. Dialog Animated Multi-Color Perimeter Aura while Generating */
.romita-global-dialog.is-generating {
    border-color: rgba(99, 102, 241, 0.75) !important;
    animation: rgModalPerimeterAura 2.2s infinite alternate ease-in-out !important;
}

@keyframes rgModalPerimeterAura {
    0% {
        box-shadow: 0 0 0 2.5px rgba(99, 102, 241, 0.85),
                    0 0 25px rgba(168, 85, 247, 0.65),
                    0 0 55px rgba(56, 189, 248, 0.45),
                    0 30px 80px -10px rgba(0, 0, 0, 0.85);
    }
    50% {
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.95),
                    0 0 35px rgba(56, 189, 248, 0.75),
                    0 0 70px rgba(168, 85, 247, 0.55),
                    0 32px 85px -10px rgba(0, 0, 0, 0.9);
    }
    100% {
        box-shadow: 0 0 0 2.5px rgba(236, 72, 153, 0.9),
                    0 0 40px rgba(236, 72, 153, 0.75),
                    0 0 85px rgba(99, 102, 241, 0.55),
                    0 34px 90px -10px rgba(0, 0, 0, 0.95);
    }
}

/* 3. Holographic Chromatic Avatar Aura */
.rg-avatar-generating {
    position: relative;
    z-index: 2;
}

.rg-avatar-generating::before {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 11px;
    background: conic-gradient(from 0deg, #6366f1, #06b6d4, #a855f7, #ec4899, #6366f1);
    animation: rgChromaticSpin 2s linear infinite;
    z-index: -1;
    filter: blur(3px);
    opacity: 0.9;
}

@keyframes rgChromaticSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 4. Quantum Orbital Atom Generating Card (Modal Chat) */
.rg-neural-bubble {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(99, 102, 241, 0.3) !important;
    border-radius: 16px !important;
    padding: 12px 18px !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    max-width: 100% !important;
    box-sizing: border-box !important;
}

[data-theme="dark"] .rg-neural-bubble {
    background: rgba(18, 18, 24, 0.75) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 0 0 25px rgba(99, 102, 241, 0.12);
}

.romita-chat-orbital-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    width: 100%;
    max-width: 480px;
    box-sizing: border-box;
}

.romita-orbital-core {
    position: relative;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.roc-nucleus {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #ec4899);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.82rem;
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.85);
    z-index: 2;
    animation: rocNucleusPulse 1.4s infinite alternate ease-in-out;
}

@keyframes rocNucleusPulse {
    0% { transform: scale(0.92); filter: drop-shadow(0 0 4px #6366f1); }
    100% { transform: scale(1.08); filter: drop-shadow(0 0 12px #ec4899); }
}

.roc-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 1.5px solid transparent;
    border-top-color: #38bdf8;
    border-right-color: #8b5cf6;
}

.roc-ring-1 {
    animation: rocSpinRing1 1.3s infinite linear;
}

.roc-ring-2 {
    inset: 3px;
    border-top-color: #ec4899;
    border-left-color: #4f46e5;
    animation: rocSpinRing2 1.8s infinite linear reverse;
}

.roc-pulse {
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.25);
    filter: blur(4px);
    animation: rocPulseGlow 1.8s infinite ease-out;
}

@keyframes rocSpinRing1 {
    0% { transform: rotate(0deg) scale(1); }
    50% { transform: rotate(180deg) scale(1.08); }
    100% { transform: rotate(360deg) scale(1); }
}

@keyframes rocSpinRing2 {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes rocPulseGlow {
    0% { transform: scale(0.8); opacity: 0.8; }
    100% { transform: scale(1.5); opacity: 0; }
}

.roc-chat-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    min-width: 0;
}

.roc-status-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.roc-status-ping {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #38bdf8;
    box-shadow: 0 0 10px #38bdf8;
    animation: rocStatusPing 1.2s infinite ease-in-out;
    flex-shrink: 0;
}

@keyframes rocStatusPing {
    0%, 100% { transform: scale(0.85); opacity: 0.4; }
    50% { transform: scale(1.3); opacity: 1; filter: drop-shadow(0 0 6px #38bdf8); }
}

.roc-status-msg {
    font-size: 0.8rem;
    font-weight: 700;
    color: #6366f1;
    letter-spacing: -0.01em;
    white-space: normal;
    word-break: break-word;
    line-height: 1.35;
    transition: opacity 0.2s ease;
}

[data-theme="dark"] .roc-status-msg {
    color: #a5b4fc;
}

.roc-shimmer-telemetry {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.roc-shimmer-bar {
    height: 6px;
    border-radius: 999px;
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, rgba(56, 189, 248, 0.5) 40%, rgba(236, 72, 153, 0.45) 60%, rgba(99, 102, 241, 0.15) 100%);
    background-size: 200% 100%;
    animation: rocBarScan 1.6s infinite linear;
}

.roc-shimmer-bar.b1 { width: 92%; }
.roc-shimmer-bar.b2 { width: 65%; animation-delay: 0.25s; }

@keyframes rocBarScan {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* 8. Input Box Scanning State */
.rg-input-box.is-generating {
    border-color: rgba(99, 102, 241, 0.5) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18), 0 0 20px rgba(99, 102, 241, 0.2) !important;
    animation: rgInputScan 2.5s infinite alternate ease-in-out;
}

@keyframes rgInputScan {
    0% { border-color: rgba(99, 102, 241, 0.35); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.12); }
    100% { border-color: rgba(56, 189, 248, 0.7); box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.25), 0 0 22px rgba(56, 189, 248, 0.25); }
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

.rg-input-box {
    position: relative;
    display: flex;
    align-items: flex-end;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 8px 10px 8px 14px;
    transition: all 0.2s ease;
}

[data-theme="dark"] .rg-input-box {
    background: rgba(0, 0, 0, 0.35);
    border-color: rgba(255, 255, 255, 0.1);
}

.rg-input-box:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] .rg-input-box:focus-within {
    border-color: #818cf8;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.rg-textarea {
    width: 100%;
    border: none;
    background: transparent;
    resize: none;
    outline: none;
    font-size: 0.86rem;
    line-height: 1.45;
    color: #0f172a;
    font-family: inherit;
    max-height: 120px;
    padding: 3px 0;
}

[data-theme="dark"] .rg-textarea {
    color: #f8fafc;
}

.rg-textarea::placeholder {
    color: #94a3b8;
    font-size: 0.83rem;
}

.rg-input-actions {
    display: flex;
    align-items: center;
    margin-left: 8px;
}

.rg-send-btn {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #4f46e5;
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
    background: #4338ca;
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

    /* Iluminación perimetral interna en móviles cuando está generando */
    .romita-global-dialog.is-generating {
        box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.9),
                    inset 0 0 20px rgba(56, 189, 248, 0.45),
                    inset 0 0 45px rgba(236, 72, 153, 0.25) !important;
        animation: rgMobilePerimeterAura 2s infinite alternate ease-in-out !important;
    }

    @keyframes rgMobilePerimeterAura {
        0% {
            box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.9),
                        inset 0 0 16px rgba(99, 102, 241, 0.45),
                        inset 0 0 35px rgba(168, 85, 247, 0.25);
        }
        50% {
            box-shadow: inset 0 0 0 2.5px rgba(56, 189, 248, 0.95),
                        inset 0 0 24px rgba(56, 189, 248, 0.6),
                        inset 0 0 50px rgba(168, 85, 247, 0.3);
        }
        100% {
            box-shadow: inset 0 0 0 2px rgba(236, 72, 153, 0.9),
                        inset 0 0 20px rgba(236, 72, 153, 0.5),
                        inset 0 0 40px rgba(99, 102, 241, 0.25);
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

    /* Barra de Especialidades Deslizable y Fluida */
    .rg-specialties-container {
        padding: 6px 10px;
    }

    .rg-specialties-bar {
        display: flex;
        gap: 5px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        padding: 3px;
        scrollbar-width: none;
    }

    .rg-spec-tab {
        flex: 0 0 auto !important;
        scroll-snap-align: start;
        padding: 6px 12px;
        font-size: 0.72rem;
        gap: 5px;
        white-space: nowrap;
    }

    .rg-spec-tab i {
        font-size: 0.85rem;
    }

    /* Cuerpo del Chat en Mobile */
    .rg-chat-body {
        padding: 12px 10px;
        gap: 12px;
        min-height: 0;
    }

    .rg-msg {
        max-width: 95%;
        gap: 8px;
    }

    .rg-msg-avatar {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        font-size: 0.82rem;
    }

    .rg-avatar-generating::before {
        inset: -2px;
        filter: blur(2px);
    }

    .rg-msg-bubble {
        padding: 10px 13px;
        font-size: 0.84rem;
        line-height: 1.48;
        border-radius: 14px;
    }

    /* Tarjeta de Generación Atom Orbital Adaptada al 100% sin Desborde */
    .rg-neural-bubble {
        padding: 8px 10px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .romita-chat-orbital-card {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        gap: 10px !important;
        box-sizing: border-box !important;
    }

    .romita-orbital-core {
        width: 32px !important;
        height: 32px !important;
        flex-shrink: 0 !important;
    }

    .roc-nucleus {
        width: 18px !important;
        height: 18px !important;
        font-size: 0.7rem !important;
    }

    .roc-chat-details {
        gap: 5px !important;
        min-width: 0 !important;
        flex: 1 !important;
    }

    .roc-status-row {
        gap: 6px !important;
    }

    .roc-status-msg {
        font-size: 0.75rem !important;
        white-space: normal !important;
        line-height: 1.25 !important;
        overflow: visible !important;
        text-overflow: clip !important;
        word-break: break-word !important;
    }

    .roc-shimmer-telemetry {
        gap: 4px !important;
        width: 100% !important;
    }

    .roc-shimmer-bar {
        height: 5px !important;
    }

    .roc-shimmer-bar.b1 { width: 90% !important; }
    .roc-shimmer-bar.b2 { width: 62% !important; }

    /* Vista de Bienvenida en Móvil */
    .rg-welcome-view {
        padding: 1rem 0.25rem;
        gap: 0.5rem;
    }

    .rg-welcome-title {
        font-size: 1.1rem;
    }

    .rg-welcome-subtitle {
        font-size: 0.78rem;
    }

    .rg-suggestions-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
        width: 100% !important;
        margin-top: 0.75rem !important;
    }

    .rg-suggestion-card {
        padding: 9px 11px;
        gap: 10px;
    }

    /* Footer e Input en Mobile */
    .rg-footer {
        padding: 8px 10px;
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        gap: 0;
    }

    /* En móviles se ocultan los atajos de teclado físicos (Enter, Shift+Enter, R, Esc) para ganar espacio y limpieza */
    .rg-footer-hints {
        display: none !important;
    }

    .rg-input-box {
        padding: 6px 8px 6px 12px;
        border-radius: 12px;
    }

    .rg-textarea {
        font-size: 0.88rem;
        line-height: 1.4;
        max-height: 100px;
    }

    .rg-send-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
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
    .romita-chat-orbital-card {
        min-width: 0 !important;
        max-width: 100% !important;
    }
    .roc-status-msg {
        white-space: normal !important;
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
        'task_manager': 'Tareas & Objetivos',
        'mensajes': 'Mensajes Internos',
        'knowledge_base': 'Base de Conocimiento',
        'forms': 'Formularios'
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
    if (!pill) return;
    const ctx = getRomitaScreenContext();
    pill.innerHTML = `<i class="ph ph-browsers"></i> <span>${ctx.label}</span>`;
}

// 5. Specialty Selection & Automatic Fresh Chat Creation
function setRomitaSpecialty(spec, btn) {
    romitaSpecialty = spec;
    document.querySelectorAll('.rg-spec-tab').forEach(c => c.classList.remove('active'));
    if (btn) {
        btn.classList.add('active');
        try {
            btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        } catch(e) {}
    }

    // Cambiar de pestaña crea un nuevo chat automáticamente
    romitaCurrentChatId = null;
    renderSpecialtyWelcome(spec);
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
            <div class="rg-welcome-orb">
                <div class="rg-orb-glow"></div>
                <div class="rg-orb-icon">
                    <i class="ph-bold ph-sparkle"></i>
                </div>
            </div>
            <h3 class="rg-welcome-title">${data.title}</h3>
            <p class="rg-welcome-subtitle" id="romita-welcome-desc">
                ${data.desc}
            </p>
            <div class="rg-suggestions-grid">
                ${data.cards.map(c => `
                    <button type="button" class="rg-suggestion-card" onclick="sendRomitaQuickPrompt('${c.prompt.replace(/'/g, "\\'")}')">
                        <span class="rg-card-icon"><i class="ph ${c.icon}"></i></span>
                        <div class="rg-card-text">
                            <strong>${c.label}</strong>
                            <small>${c.sub}</small>
                        </div>
                    </button>
                `).join('')}
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

// 7. Auto-grow Textarea
function autoGrowRomitaTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function handleRomitaInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendRomitaMessage();
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

// 8. Markdown Parser Avanzado con soporte para Tablas, Listas y Código
function renderRomitaMarkdown(text) {
    if (!text) return '';
    let out = preprocessMarkdownTables(text);

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
        const colsCount = table.querySelectorAll('tr:first-child th, tr:first-child td').length;

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
                        <i class="ph ph-file-csv"></i> <span>Copiar para Excel</span>
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

    // Append Futuristic Neural Thought Indicator
    const typingDiv = document.createElement('div');
    typingDiv.className = 'rg-msg rg-msg-ai';
    typingDiv.id = 'romita-typing-indicator';
    typingDiv.innerHTML = `
        <div class="rg-msg-avatar rg-avatar-generating"><i class="ph-bold ph-sparkle"></i></div>
        <div class="rg-msg-content-wrap">
            <div class="rg-msg-bubble rg-neural-bubble">
                <div class="romita-chat-orbital-card">
                    <div class="romita-orbital-core">
                        <div class="roc-pulse"></div>
                        <div class="roc-ring roc-ring-1"></div>
                        <div class="roc-ring roc-ring-2"></div>
                        <div class="roc-nucleus">
                            <i class="ph-bold ph-sparkle"></i>
                        </div>
                    </div>
                    <div class="roc-chat-details">
                        <div class="roc-status-row">
                            <span class="roc-status-ping"></span>
                            <span id="rg-neural-status-text" class="roc-status-msg">Romita está procesando el contexto...</span>
                        </div>
                        <div class="roc-shimmer-telemetry">
                            <div class="roc-shimmer-bar b1"></div>
                            <div class="roc-shimmer-bar b2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    chatContainer.appendChild(typingDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;

    romitaIsLoading = true;

    // Window and input active generating effects
    const dialog = document.getElementById('romita-global-dialog');
    if (dialog) dialog.classList.add('is-generating');

    const inputBox = document.getElementById('romita-chat-input-box');
    if (inputBox) inputBox.classList.add('is-generating');

    const sendBtn = document.getElementById('btn-romita-send');
    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
    }

    // Dynamic futuristic status phrase cycling
    const statusPhrases = [
        'Romita está procesando el contexto...',
        'Consultando base de proyectos y ecosistema...',
        'Analizando pilares estratégicos y métricas...',
        'Sintetizando propuesta de alto impacto...',
        'Generando respuesta final...'
    ];
    let phraseIdx = 0;
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
                <div class="rg-msg-avatar"><i class="ph-bold ph-sparkle"></i></div>
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
    }
}

// 10. Clear Chat
function clearRomitaCurrentChat() {
    romitaCurrentChatId = null;
    renderSpecialtyWelcome(romitaSpecialty);
}

// 11. Global Keyboard Shortcut Listener (Tecla R / Escape)
document.addEventListener('keydown', function(e) {
    // Escape cierra Romita si está abierto
    if (e.key === 'Escape') {
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
