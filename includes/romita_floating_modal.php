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
                <textarea id="romita-chat-input" class="rg-textarea" rows="1" placeholder="Escribe tu consulta o pide una recomendación estratégica..." onkeydown="handleRomitaInputKeydown(event)" oninput="autoGrowRomitaTextarea(this)"></textarea>
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
body.swal2-shown .romita-fab-container,
body.has-active-modal .romita-fab-container,
.modal-overlay.active ~ * .romita-fab-container,
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
.rg-msg-bubble table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
    font-size: 0.78rem;
    border-radius: 8px;
    overflow: hidden;
}
.rg-msg-bubble th, .rg-msg-bubble td {
    padding: 7px 10px;
    border: 1px solid #e2e8f0;
    text-align: left;
}
[data-theme="dark"] .rg-msg-bubble th, [data-theme="dark"] .rg-msg-bubble td {
    border-color: rgba(255, 255, 255, 0.08);
}
.rg-msg-bubble th {
    background: rgba(99, 102, 241, 0.08);
    font-weight: 700;
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
}

[data-theme="dark"] .rg-neural-bubble {
    background: rgba(18, 18, 24, 0.75) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 0 0 25px rgba(99, 102, 241, 0.12);
}

.romita-chat-orbital-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 290px;
    max-width: 480px;
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
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
</style>

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
    updateRomitaScreenPill();

    const input = document.getElementById('romita-chat-input');
    if (input) {
        setTimeout(() => input.focus(), 100);
    }
}

function closeRomitaGlobalModal() {
    const overlay = document.getElementById('romita-global-overlay');
    if (overlay) overlay.style.display = 'none';
}

function handleRomitaOverlayClick(e) {
    if (e.target.id === 'romita-global-overlay') {
        closeRomitaGlobalModal();
    }
}

// 3. Layout Mode Toggle (Spotlight vs Drawer)
function toggleRomitaLayoutMode() {
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
    if (btn) btn.classList.add('active');

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

// 8. Markdown Parser
function renderRomitaMarkdown(text) {
    if (!text) return '';
    let out = text;

    // Remove raw json blocks from visual render if any
    out = out.replace(/```json:calendar_plan[\s\S]*?```/g, '<div class="alert alert-info" style="font-size:0.8rem; margin:8px 0;"><i class="ph ph-calendar-check"></i> Plan de calendario estructurado generado.</div>');

    // Code blocks
    out = out.replace(/```([a-z]*)\n([\s\S]*?)```/g, function(match, lang, code) {
        return '<pre><code>' + code.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code></pre>';
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
                    <div class="rg-msg-bubble">${renderRomitaMarkdown(data.response)}</div>
                    <div class="rg-msg-actions">
                        <button type="button" class="rg-copy-bubble-btn" onclick="copyRomitaMessage(this)" title="Copiar respuesta">
                            <i class="ph ph-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            `;
            chatContainer.appendChild(aiDiv);
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
