<?php
// modules/forms/builder.php — Modern App-Style Form Studio with Cover Banners
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php?module=auth&action=login"); 
    exit(); 
}
$id = $_GET['id'] ?? '';
$formData = null;
if (!empty($id)) { 
    $stmt = $db->prepare("SELECT * FROM form_templates WHERE id = ?"); 
    $stmt->execute([$id]); 
    $formData = $stmt->fetch(PDO::FETCH_ASSOC); 
}
$primaryColor = $global_settings['primary_color'] ?? '#4f46e5';
$logoLight = $global_settings['logo_light'] ?? '';
$publicToken = $formData['public_token'] ?? '';
$shortToken = $publicToken ? substr($publicToken, 0, 8) : '';
$publicUrl = $shortToken ? "f/" . urlencode($shortToken) : '';

$settings = json_decode($formData['settings_json'] ?? '{}', true) ?: [];
$viewStyle = $settings['view_style'] ?? 'hero_cover';
$coverPreset = $settings['cover_image'] ?? 'nebula';
$welcomeScreen = $settings['welcome_screen'] ?? false;
$showLogo = $settings['show_logo'] ?? true;
$reqName = $settings['require_name'] ?? true;
$reqEmail = $settings['require_email'] ?? true;
$multiStep = $settings['multi_step'] ?? false;

require_once 'includes/header.php';
?>

<style>
/* App Theme Tokens */
:root {
    --app-bg: #f4f4f6;
    --app-surface: #ffffff;
    --app-surface-sub: #f8fafc;
    --app-border: #e4e4e7;
    --app-border-hover: #cbd5e1;
    --app-text: #09090b;
    --app-text-muted: #71717a;
    --app-input: #f4f4f6;
    --app-accent: <?php echo htmlspecialchars($primaryColor); ?>;
    --app-accent-light: color-mix(in srgb, var(--app-accent) 12%, transparent);
    --app-accent-glow: color-mix(in srgb, var(--app-accent) 25%, transparent);
}

[data-theme="dark"] {
    --app-bg: #000000 !important; /* Fondo Negro Puro */
    --app-surface: #0e0e12;
    --app-surface-sub: #141419;
    --app-border: rgba(255, 255, 255, 0.08);
    --app-border-hover: rgba(255, 255, 255, 0.18);
    --app-text: #ffffff;
    --app-text-muted: #8e8e93;
    --app-input: #16161c;
    --app-accent-light: color-mix(in srgb, var(--app-accent) 16%, transparent);
    --app-accent-glow: color-mix(in srgb, var(--app-accent) 30%, transparent);
}

/* Layout Lock — Elimina el doble deslizador vertical dejando un único scroll fluido */
html, body {
    height: 100% !important;
    overflow: hidden !important;
}

.app-container {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
}

.main-content {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    min-height: 0 !important;
}

.content-wrapper {
    padding: 0 !important;
    margin: 0 !important;
    flex: 1 !important;
    min-height: 0 !important;
    height: 100% !important;
    max-height: 100% !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* Scrollbar estilizada y discreta */
.content-wrapper::-webkit-scrollbar {
    width: 6px;
}
.content-wrapper::-webkit-scrollbar-track {
    background: transparent;
}
.content-wrapper::-webkit-scrollbar-thumb {
    background: var(--app-border);
    border-radius: 999px;
}
.content-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--app-border-hover);
}

/* Base Viewport */
.fb-viewport {
    min-height: 100%;
    background-color: var(--app-bg);
    margin: 0;
    padding-bottom: 5rem;
    position: relative;
    color: var(--app-text);
}

/* App Header Topbar */
.fb-app-topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: color-mix(in srgb, var(--app-bg) 88%, transparent);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid var(--app-border);
    padding: 0.75rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.fb-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.fb-btn-back {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    color: var(--app-text);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 1.1rem;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.fb-btn-back:hover {
    background: var(--app-accent-light);
    border-color: var(--app-accent);
    color: var(--app-accent);
}

.fb-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.fb-topbar-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-text);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 260px;
}

.fb-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 0.2rem 0.65rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.fb-status-pill::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.fb-pill-active { 
    background: rgba(16, 185, 129, 0.12); 
    color: #10b981; 
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.fb-pill-active::before { background: #10b981; box-shadow: 0 0 6px #10b981; }

.fb-pill-draft { 
    background: rgba(245, 158, 11, 0.12); 
    color: #f59e0b; 
    border: 1px solid rgba(245, 158, 11, 0.25);
}
.fb-pill-draft::before { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }

/* Segmented Tab Switcher */
.fb-segmented-tabs {
    display: flex;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    padding: 3px;
    border-radius: 12px;
    gap: 3px;
}

.fb-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--app-text-muted);
    border: none;
    background: transparent;
    border-radius: 9px;
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
}

.fb-tab-btn:hover { color: var(--app-text); }
.fb-tab-btn.active {
    background: var(--app-surface-sub);
    color: var(--app-text);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--app-border);
}

/* Actions */
.fb-topbar-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.fb-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.52rem 0.95rem;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: 10px;
    border: 1px solid var(--app-border);
    background: var(--app-surface);
    color: var(--app-text);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    text-decoration: none;
}

.fb-btn-action:hover {
    border-color: var(--app-border-hover);
    background: var(--app-surface-sub);
}

.fb-btn-primary {
    background: var(--app-accent);
    border-color: var(--app-accent);
    color: #ffffff;
    box-shadow: 0 4px 14px var(--app-accent-glow);
}

.fb-btn-primary:hover {
    filter: brightness(1.1);
    color: #ffffff;
    transform: translateY(-1px);
}

/* App Studio Layout (Canvas + Integrated Toolbox) */
.fb-studio-layout {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 1.75rem;
    max-width: 1180px;
    margin: 0 auto;
    padding: 2rem 1.5rem 5rem;
}

.fb-canvas-column {
    flex: 1;
    max-width: 740px;
    min-width: 0;
}

/* Integrated Studio Sidebar Toolbox */
.fb-studio-sidebar {
    width: 250px;
    position: sticky;
    top: 75px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 18px;
    padding: 1.25rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex-shrink: 0;
}

.fb-toolbox-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--app-border);
}

.fb-toolbox-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--app-text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.fb-toolbox-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.fb-tool-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.75rem 0.5rem;
    border-radius: 12px;
    border: 1px solid var(--app-border);
    background: var(--app-surface-sub);
    color: var(--app-text);
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s ease;
    text-align: center;
}

.fb-tool-btn i {
    font-size: 1.35rem;
    color: var(--app-accent);
}

.fb-tool-btn:hover {
    border-color: var(--app-accent);
    background: var(--app-accent-light);
    transform: translateY(-2px);
}

/* Header Form Title Card with Cover Banner */
.fb-header-card {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 20px;
    margin-bottom: 1.25rem;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* Visual Cover Banner */
.fb-cover-banner {
    height: 160px;
    width: 100%;
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 1.25rem 1.75rem;
    background-size: cover;
    background-position: center;
    transition: all 0.25s ease;
}

.fb-cover-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.6) 100%);
    pointer-events: none;
}

.fb-cover-actions {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    gap: 8px;
    z-index: 10;
}

.fb-btn-cover-edit {
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}

.fb-btn-cover-edit:hover {
    background: rgba(0, 0, 0, 0.85);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-1px);
}

.fb-brand-avatar-float {
    position: relative;
    z-index: 5;
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: var(--app-surface);
    border: 2px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
    margin-bottom: -32px;
    overflow: hidden;
}

.fb-brand-avatar-float img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
}

.fb-brand-avatar-float i {
    font-size: 1.85rem;
    color: var(--app-accent);
}

.fb-header-content {
    padding: 2.25rem 1.85rem 1.65rem;
}

.fb-title-input {
    width: 100%;
    border: none;
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--app-text);
    background: transparent;
    font-family: inherit;
    padding: 0.25rem 0;
    outline: none;
    line-height: 1.25;
}

.fb-title-input::placeholder { color: var(--app-text-muted); opacity: 0.5; }

.fb-desc-input {
    width: 100%;
    border: none;
    font-size: 0.875rem;
    color: var(--app-text-muted);
    background: transparent;
    font-family: inherit;
    padding: 0.5rem 0 0;
    outline: none;
    margin-top: 0.4rem;
    resize: none;
    line-height: 1.5;
}

.fb-desc-input::placeholder { color: var(--app-text-muted); opacity: 0.5; }

/* Question Cards */
.fb-question-card {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    margin-bottom: 1rem;
    position: relative;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.fb-question-card:hover {
    border-color: var(--app-border-hover);
}

.fb-question-card.active {
    border-color: var(--app-accent);
    box-shadow: 0 0 0 1px var(--app-accent), 0 8px 24px var(--app-accent-glow);
}

.fb-card-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.25rem 0;
    user-select: none;
}

.fb-card-number-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--app-text-muted);
    font-family: monospace;
    background: var(--app-surface-sub);
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid var(--app-border);
}

.fb-card-drag-handle {
    display: inline-flex;
    align-items: center;
    color: var(--app-text-muted);
    cursor: grab;
    font-size: 1rem;
    opacity: 0.4;
    transition: opacity 0.15s;
    padding: 2px 6px;
}

.fb-question-card:hover .fb-card-drag-handle,
.fb-question-card.active .fb-card-drag-handle {
    opacity: 0.9;
}

.fb-card-content {
    padding: 0.85rem 1.35rem 1.25rem;
}

/* Active Header Row */
.fb-q-header-row {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    margin-bottom: 1.15rem;
}

.fb-q-label-input {
    flex: 1;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-text);
    border: 1px solid var(--app-border);
    background: var(--app-surface-sub);
    border-radius: 10px;
    font-family: inherit;
    padding: 0.65rem 0.95rem;
    outline: none;
    transition: all 0.15s ease;
}

.fb-q-label-input:focus {
    border-color: var(--app-accent);
    box-shadow: 0 0 0 2px var(--app-accent-light);
}

.fb-type-select-styled {
    padding: 0.65rem 0.95rem;
    border: 1px solid var(--app-border);
    border-radius: 10px;
    font-size: 0.8125rem;
    font-weight: 600;
    font-family: inherit;
    background: var(--app-surface-sub);
    color: var(--app-text);
    min-width: 170px;
    cursor: pointer;
    outline: none;
    transition: all 0.15s ease;
}

.fb-type-select-styled:focus {
    border-color: var(--app-accent);
}

/* Card Action Footer */
.fb-card-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 0.75rem 1.35rem;
    border-top: 1px solid var(--app-border);
    background: var(--app-surface-sub);
}

.fb-card-icon-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--app-text-muted);
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.05rem;
    transition: all 0.15s ease;
}

.fb-card-icon-btn:hover {
    background: var(--app-surface);
    border-color: var(--app-border);
    color: var(--app-text);
}

.fb-card-icon-btn.btn-delete:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.2);
}

.fb-v-divider {
    width: 1px;
    height: 18px;
    background: var(--app-border);
    margin: 0 0.35rem;
}

/* iOS Switch */
.app-switch-label {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--app-text);
    cursor: pointer;
    user-select: none;
}

.app-switch {
    position: relative;
    width: 36px;
    height: 20px;
    display: inline-block;
}

.app-switch input { opacity: 0; width: 0; height: 0; }

.app-switch-slider {
    position: absolute;
    inset: 0;
    background: var(--app-border);
    border-radius: 20px;
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.app-switch-slider:before {
    content: '';
    position: absolute;
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background: #ffffff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.app-switch input:checked + .app-switch-slider {
    background: var(--app-accent);
}

.app-switch input:checked + .app-switch-slider:before {
    transform: translateX(16px);
}

/* Inter-Card Insert Divider */
.fb-insert-divider {
    position: relative;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: -6px 0 6px;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.fb-insert-divider:hover { opacity: 1; }

.fb-insert-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 1.5px;
    background: var(--app-accent);
    opacity: 0.5;
}

.fb-insert-btn {
    position: relative;
    z-index: 2;
    background: var(--app-surface);
    border: 1.5px solid var(--app-accent);
    color: var(--app-accent);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    cursor: pointer;
    transition: transform 0.15s ease;
}

.fb-insert-btn:hover {
    transform: scale(1.15);
    background: var(--app-accent);
    color: #ffffff;
}

/* Options Editor */
.fb-options-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.fb-opt-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.fb-opt-indicator {
    width: 18px;
    height: 18px;
    border: 2px solid var(--app-border);
    border-radius: 50%;
    flex-shrink: 0;
}

.fb-opt-indicator.checkbox-style { border-radius: 5px; }

.fb-opt-input {
    flex: 1;
    border: 1px solid var(--app-border);
    background: var(--app-surface-sub);
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    font-size: 0.85rem;
    font-family: inherit;
    color: var(--app-text);
    outline: none;
    transition: border-color 0.15s;
}

.fb-opt-input:focus {
    border-color: var(--app-accent);
}

.fb-opt-delete-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--app-text-muted);
    font-size: 1.1rem;
    padding: 0.25rem;
    border-radius: 6px;
    opacity: 0.5;
    transition: all 0.15s ease;
}

.fb-opt-delete-btn:hover {
    opacity: 1;
    color: #ef4444;
}

.fb-add-opt-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--app-text-muted);
    margin-top: 0.75rem;
}

.fb-btn-link {
    color: var(--app-accent);
    background: none;
    border: none;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    font-size: inherit;
    font-family: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.fb-btn-link:hover { text-decoration: underline; }

/* Settings Panel */
.fb-settings-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-width: 740px;
    margin: 0 auto;
}

.fb-setting-card {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 18px;
    padding: 1.5rem 1.75rem;
}

.fb-setting-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-bottom: 1.25rem;
}

.fb-setting-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--app-accent-light);
    color: var(--app-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.fb-setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 0;
    border-bottom: 1px solid var(--app-border);
    gap: 1rem;
}

.fb-setting-row:last-child { border-bottom: none; }

.fb-setting-info h4 { margin: 0 0 0.2rem; font-size: 0.85rem; font-weight: 600; color: var(--app-text); }
.fb-setting-info p { margin: 0; font-size: 0.75rem; color: var(--app-text-muted); line-height: 1.4; }

/* Cover Modal */
.fb-cover-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 1100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.fb-cover-modal-overlay.active { display: flex; }

.fb-cover-modal {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 22px;
    width: 100%;
    max-width: 540px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    overflow: hidden;
}

.fb-cover-modal-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.fb-cover-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
    padding: 1.5rem;
}

.fb-cover-preset-card {
    height: 85px;
    border-radius: 12px;
    border: 2px solid var(--app-border);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
    padding: 8px 12px;
    transition: all 0.15s ease;
}

.fb-cover-preset-card:hover {
    border-color: var(--app-accent);
    transform: scale(1.03);
}

.fb-cover-preset-card.selected {
    border-color: var(--app-accent);
    box-shadow: 0 0 0 2px var(--app-accent);
}

.fb-cover-preset-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #ffffff;
    text-shadow: 0 1px 4px rgba(0,0,0,0.8);
}

/* Toast */
.fb-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    color: var(--app-text);
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-size: 0.8125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 100;
    opacity: 0;
    transform: translateY(12px);
    transition: all 0.22s ease;
    pointer-events: none;
}
.fb-toast.show { opacity: 1; transform: translateY(0); }

/* Device Preview Modal */
.fb-preview-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 1050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
}

.fb-preview-modal-overlay.active { display: flex; }

.fb-preview-container {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 28px;
    width: 100%;
    max-width: 440px;
    height: 90vh;
    max-height: 820px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.fb-preview-top {
    padding: 0.95rem 1.35rem;
    border-bottom: 1px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--app-surface-sub);
}

.fb-preview-top h3 {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--app-text);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.fb-preview-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    background: var(--app-bg);
}

.fb-phone-frame {
    background: var(--app-surface);
    border-radius: 24px;
    border: 1px solid var(--app-border);
    overflow: hidden;
}

.fb-phone-notch {
    height: 18px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: var(--app-surface);
    padding-top: 6px;
}

.fb-phone-island {
    width: 80px;
    height: 10px;
    background: var(--app-border);
    border-radius: 9999px;
}

.fb-phone-content { padding: 1.35rem; }

/* Responsive */
@media (max-width: 1040px) {
    .fb-studio-sidebar { display: none; }
    .fb-studio-layout { padding-top: 1.25rem; }
}
@media (max-width: 768px) {
    .fb-viewport { padding-top: 52px; }
}
</style>

<div class="fb-viewport">
    <!-- Topbar Navigation -->
    <div class="fb-app-topbar">
        <div class="fb-topbar-left">
            <a href="index.php?module=forms&action=index" class="fb-btn-back" title="Volver a formularios">
                <i class="ph-bold ph-arrow-left"></i>
            </a>
            <div class="fb-title-wrap">
                <h2 class="fb-topbar-title" id="topbarTitleText">
                    <?php echo $formData ? htmlspecialchars($formData['title']) : 'Nuevo Formulario'; ?>
                </h2>
                <span class="fb-status-pill <?php echo ($formData && $formData['status']==='active') ? 'fb-pill-active' : 'fb-pill-draft'; ?>" id="topbarStatusBadge">
                    <?php echo ($formData && $formData['status']==='active') ? 'Publicado' : 'Borrador'; ?>
                </span>
            </div>
        </div>

        <!-- Segmented Tab Switcher -->
        <div class="fb-segmented-tabs">
            <button type="button" class="fb-tab-btn active" id="tabBtnEditor" onclick="switchTab('editor')">
                <i class="ph-bold ph-faders"></i> Editor
            </button>
            <button type="button" class="fb-tab-btn" id="tabBtnSettings" onclick="switchTab('settings')">
                <i class="ph-bold ph-gear"></i> Configuración
            </button>
        </div>

        <!-- Topbar Actions -->
        <div class="fb-topbar-actions">
            <?php if($publicUrl): ?>
            <button type="button" class="fb-btn-action" onclick="copyPublicLink()" title="Copiar enlace para clientes">
                <i class="ph-bold ph-link-simple"></i> <span class="d-none d-lg-inline">Copiar Enlace</span>
            </button>
            <?php endif; ?>
            <button type="button" class="fb-btn-action" onclick="toggleDevicePreview()" title="Vista previa interactiva">
                <i class="ph-bold ph-device-mobile"></i> <span class="d-none d-md-inline">Vista Previa</span>
            </button>
            <button type="button" class="fb-btn-action" onclick="saveForm('draft')" title="Guardar como borrador">
                <i class="ph-bold ph-floppy-disk"></i> <span class="d-none d-md-inline">Borrador</span>
            </button>
            <button type="button" class="fb-btn-action fb-btn-primary" onclick="saveForm('active')" title="Publicar formulario">
                <i class="ph-bold ph-rocket-launch"></i> <span>Publicar</span>
            </button>
        </div>
    </div>

    <!-- Main Studio Workspace -->
    <div class="fb-studio-layout">
        <!-- Canvas Column -->
        <div class="fb-canvas-column">
            <!-- View 1: Editor -->
            <div id="viewEditor">
                <!-- Header Card with Visual Cover -->
                <div class="fb-header-card">
                    <!-- Dynamic Cover Banner -->
                    <div class="fb-cover-banner" id="headerCoverBanner">
                        <div class="fb-cover-overlay"></div>
                        <div class="fb-cover-actions">
                            <button type="button" class="fb-btn-cover-edit" onclick="openCoverModal()">
                                <i class="ph-bold ph-image"></i> Cambiar Portada
                            </button>
                        </div>
                        <div class="fb-brand-avatar-float">
                            <?php if($logoLight): ?>
                                <img src="<?php echo htmlspecialchars($logoLight); ?>" alt="Logo">
                            <?php else: ?>
                                <i class="ph-bold ph-shield-check"></i>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="fb-header-content">
                        <input type="text" class="fb-title-input" id="formTitle" placeholder="Título del formulario..." value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>" autocomplete="off" oninput="updateHeaderStats()">
                        <textarea class="fb-desc-input" id="formDesc" rows="1" placeholder="Añade una descripción o instrucciones para tus clientes..." autocomplete="off"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Dynamic Fields Container -->
                <div id="fieldsList"></div>

                <!-- Empty State -->
                <div id="fbEmptyState" style="display: none; text-align: center; padding: 3rem 1.5rem; background: var(--app-surface); border: 1.5px dashed var(--app-border); border-radius: 18px; margin-bottom: 1.5rem;">
                    <i class="ph-bold ph-cards" style="font-size: 2.2rem; color: var(--app-accent); display: inline-block; margin-bottom: 0.75rem;"></i>
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem;">Tu formulario está vacío</h3>
                    <p style="font-size: 0.8125rem; color: var(--app-text-muted); margin-bottom: 1.25rem;">Haz clic en cualquiera de las herramientas a la derecha para añadir una pregunta.</p>
                </div>

                <!-- Bottom Add Button -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <button type="button" class="fb-btn-action" onclick="addField('text')" style="padding: 0.75rem 1.75rem; border-radius: 12px; font-weight: 700; background: var(--app-surface);">
                        <i class="ph-bold ph-plus-circle" style="color: var(--app-accent); font-size: 1.15rem;"></i> Añadir Pregunta
                    </button>
                </div>
            </div>

            <!-- View 2: Settings -->
            <div id="viewSettings" class="fb-settings-panel" style="display: none;">
                <!-- Visual Style & Cover Settings -->
                <div class="fb-setting-card">
                    <div class="fb-setting-header">
                        <div class="fb-setting-icon"><i class="ph-bold ph-palette"></i></div>
                        <div>
                            <h3 style="margin:0; font-size:0.95rem; font-weight:700;">Estilo y Apariencia</h3>
                            <p style="margin:2px 0 0; font-size:0.75rem; color:var(--app-text-muted);">Configura la portada visual y el modo de presentación</p>
                        </div>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Estilo de Vista</h4>
                            <p>Elige cómo se desplegará el formulario para el usuario.</p>
                        </div>
                        <select id="settViewStyle" style="padding: 6px 12px; border: 1px solid var(--app-border); border-radius: 9px; background: var(--app-surface-sub); color: var(--app-text); font-weight: 600;">
                            <option value="hero_cover" <?php echo $viewStyle==='hero_cover'?'selected':''; ?>>🌟 Portada Visual (Hero Cover)</option>
                            <option value="slides" <?php echo $viewStyle==='slides'?'selected':''; ?>>🎯 Diapositivas (Tipo Typeform)</option>
                            <option value="minimal" <?php echo $viewStyle==='minimal'?'selected':''; ?>>📱 Minimal App</option>
                        </select>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Pantalla de Bienvenida (Welcome Screen)</h4>
                            <p>Muestra una portada inicial de presentación con botón "Comenzar Formulario".</p>
                        </div>
                        <label class="app-switch">
                            <input type="checkbox" id="settWelcomeScreen" <?php echo $welcomeScreen ? 'checked' : ''; ?>>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Portada Visual</h4>
                            <p>Preset actual para la cabecera panorámica.</p>
                        </div>
                        <button type="button" class="fb-btn-action" onclick="openCoverModal()">
                            <i class="ph-bold ph-image"></i> Cambiar Portada
                        </button>
                    </div>
                </div>

                <!-- General Preferences -->
                <div class="fb-setting-card">
                    <div class="fb-setting-header">
                        <div class="fb-setting-icon"><i class="ph-bold ph-sliders-horizontal"></i></div>
                        <div>
                            <h3 style="margin:0; font-size:0.95rem; font-weight:700;">Preferencias de Captura</h3>
                            <p style="margin:2px 0 0; font-size:0.75rem; color:var(--app-text-muted);">Campos obligatorios iniciales</p>
                        </div>
                    </div>
                    
                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Mostrar logotipo de la agencia</h4>
                            <p>Muestra el avatar con el logo en la portada del formulario.</p>
                        </div>
                        <label class="app-switch">
                            <input type="checkbox" id="settShowLogo" <?php echo $showLogo ? 'checked' : ''; ?>>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Solicitar nombre del cliente</h4>
                            <p>Añade un campo obligatorio para el nombre completo.</p>
                        </div>
                        <label class="app-switch">
                            <input type="checkbox" id="settRequireName" <?php echo $reqName ? 'checked' : ''; ?>>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Solicitar correo electrónico</h4>
                            <p>Añade un campo obligatorio para el email de contacto.</p>
                        </div>
                        <label class="app-switch">
                            <input type="checkbox" id="settRequireEmail" <?php echo $reqEmail ? 'checked' : ''; ?>>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>

                    <div class="fb-setting-row">
                        <div class="fb-setting-info">
                            <h4>Formulario Multi-paso (Wizard)</h4>
                            <p>Divide el formulario en páginas usando los divisores de sección.</p>
                        </div>
                        <label class="app-switch">
                            <input type="checkbox" id="settMultiStep" <?php echo $multiStep ? 'checked' : ''; ?>>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integrated Studio Sidebar Toolbox (Desktop) -->
        <div class="fb-studio-sidebar" id="studioSidebar">
            <div class="fb-toolbox-header">
                <span class="fb-toolbox-title"><i class="ph-bold ph-wrench"></i> Herramientas</span>
            </div>
            <div class="fb-toolbox-grid">
                <div class="fb-tool-btn" onclick="addField('text')">
                    <i class="ph-bold ph-text-aa"></i>
                    <span>Texto</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('textarea')">
                    <i class="ph-bold ph-text-align-left"></i>
                    <span>Párrafo</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('select')">
                    <i class="ph-bold ph-radio-button"></i>
                    <span>Opciones</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('checkbox')">
                    <i class="ph-bold ph-check-square"></i>
                    <span>Casillas</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('dropdown')">
                    <i class="ph-bold ph-caret-down"></i>
                    <span>Desplegable</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('file')">
                    <i class="ph-bold ph-upload-simple"></i>
                    <span>Archivos</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('date')">
                    <i class="ph-bold ph-calendar-blank"></i>
                    <span>Fecha</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('range')">
                    <i class="ph-bold ph-dots-three-outline"></i>
                    <span>Escala</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('number_range')">
                    <i class="ph-bold ph-arrows-out-line-horizontal"></i>
                    <span>Rango</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('color')">
                    <i class="ph-bold ph-palette"></i>
                    <span>Colores</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('icon_card')">
                    <i class="ph-bold ph-cards"></i>
                    <span>Cards</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('image_compare')" title="Comparativa con imágenes o iconos">
                    <i class="ph-bold ph-scales"></i>
                    <span>Comparativa</span>
                </div>
                <div class="fb-tool-btn" onclick="addField('divider')">
                    <i class="ph-bold ph-equals"></i>
                    <span>Sección</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cover Selector Modal -->
<div class="fb-cover-modal-overlay" id="coverModalOverlay" onclick="if(event.target===this)closeCoverModal()">
    <div class="fb-cover-modal">
        <div class="fb-cover-modal-header">
            <h3 style="margin:0; font-size:1rem; font-weight:700;"><i class="ph-bold ph-image"></i> Selecciona una Portada Visual</h3>
            <button type="button" class="fb-card-icon-btn" onclick="closeCoverModal()"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="fb-cover-grid">
            <div class="fb-cover-preset-card" id="preset_nebula" onclick="selectCover('nebula')" style="background: radial-gradient(circle at 20% 20%, #4338ca 0%, transparent 40%), radial-gradient(circle at 80% 80%, #7c3aed 0%, transparent 40%), radial-gradient(circle at 50% 50%, #1e1b4b 0%, #09090b 100%);">
                <span class="fb-cover-preset-title">🌌 Deep Nebula</span>
            </div>
            <div class="fb-cover-preset-card" id="preset_cyber" onclick="selectCover('cyber')" style="background: radial-gradient(circle at 80% 20%, #0ea5e9 0%, transparent 45%), radial-gradient(circle at 20% 80%, #10b981 0%, transparent 45%), linear-gradient(135deg, #020617 0%, #0f172a 100%);">
                <span class="fb-cover-preset-title">⚡ Cyber Glow</span>
            </div>
            <div class="fb-cover-preset-card" id="preset_velvet" onclick="selectCover('velvet')" style="background: radial-gradient(circle at 75% 30%, #e11d48 0%, transparent 40%), radial-gradient(circle at 25% 70%, #9333ea 0%, transparent 40%), linear-gradient(135deg, #18181b 0%, #09090b 100%);">
                <span class="fb-cover-preset-title">🔮 Velvet Obsidian</span>
            </div>
            <div class="fb-cover-preset-card" id="preset_geometry" onclick="selectCover('geometry')" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #020617 100%); background-image: radial-gradient(rgba(255,255,255,0.12) 1px, transparent 1px); background-size: 14px 14px;">
                <span class="fb-cover-preset-title">📐 Geometry Carbon</span>
            </div>
            <div class="fb-cover-preset-card" id="preset_sunset" onclick="selectCover('sunset')" style="background: radial-gradient(circle at 80% 20%, #f59e0b 0%, transparent 45%), radial-gradient(circle at 20% 80%, #ec4899 0%, transparent 45%), linear-gradient(135deg, #18181b 0%, #050505 100%);">
                <span class="fb-cover-preset-title">🌅 Sunset Aura</span>
            </div>
            <div class="fb-cover-preset-card" id="preset_abstract" onclick="selectCover('abstract')" style="background: url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80') center/cover;">
                <span class="fb-cover-preset-title">🎨 Abstract 3D</span>
            </div>
        </div>
        <div style="padding: 0 1.5rem 1.5rem;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--app-text-muted); margin-bottom:4px;">O ingresa la URL de una imagen propia:</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="customCoverUrlInput" placeholder="https://ejemplo.com/imagen.jpg" style="flex:1; padding:7px 12px; border:1px solid var(--app-border); border-radius:9px; background:var(--app-surface-sub); color:var(--app-text); font-size:0.8rem; outline:none;">
                <button type="button" class="fb-btn-action fb-btn-primary" onclick="applyCustomCover()" style="padding:7px 14px; font-size:0.8rem;">Aplicar</button>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Device Preview Modal -->
<div class="fb-preview-modal-overlay" id="previewOverlay" onclick="if(event.target===this)toggleDevicePreview()">
    <div class="fb-preview-container">
        <div class="fb-preview-top">
            <h3><i class="ph-bold ph-device-mobile"></i> Vista Previa en Móvil</h3>
            <button type="button" class="fb-card-icon-btn" onclick="toggleDevicePreview()"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="fb-preview-body">
            <div class="fb-phone-frame" id="phonePreview">
                <div class="fb-phone-notch"><div class="fb-phone-island"></div></div>
                <div id="phoneInnerContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
<div class="fb-toast" id="fbToast">
    <i class="ph-bold ph-check-circle" style="color: #10b981; font-size: 1.1rem;"></i>
    <span id="fbToastMsg">Acción completada</span>
</div>

<script>
const FORM_ID = '<?php echo $id; ?>';
const PUBLIC_URL = '<?php echo $publicUrl; ?>';
let fields = <?php echo $formData ? ($formData['fields_json'] ?: '[]') : '[]'; ?>;
let activeIdx = null, dragSrcIdx = null;
let currentCover = '<?php echo $coverPreset; ?>';

const COVER_STYLES = {
    nebula: 'radial-gradient(circle at 20% 20%, #4338ca 0%, transparent 40%), radial-gradient(circle at 80% 80%, #7c3aed 0%, transparent 40%), radial-gradient(circle at 50% 50%, #1e1b4b 0%, #09090b 100%)',
    cyber: 'radial-gradient(circle at 80% 20%, #0ea5e9 0%, transparent 45%), radial-gradient(circle at 20% 80%, #10b981 0%, transparent 45%), linear-gradient(135deg, #020617 0%, #0f172a 100%)',
    velvet: 'radial-gradient(circle at 75% 30%, #e11d48 0%, transparent 40%), radial-gradient(circle at 25% 70%, #9333ea 0%, transparent 40%), linear-gradient(135deg, #18181b 0%, #09090b 100%)',
    geometry: 'linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #020617 100%)',
    sunset: 'radial-gradient(circle at 80% 20%, #f59e0b 0%, transparent 45%), radial-gradient(circle at 20% 80%, #ec4899 0%, transparent 45%), linear-gradient(135deg, #18181b 0%, #050505 100%)',
    abstract: "url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80') center/cover"
};

const TYPE_MAP = {
    text: 'Texto Corto',
    textarea: 'Párrafo',
    email: 'Correo Electrónico',
    phone: 'Teléfono',
    date: 'Fecha',
    select: 'Varias Opciones',
    checkbox: 'Casillas',
    dropdown: 'Menú Desplegable',
    file: 'Subir Archivos',
    range: 'Escala Lineal',
    number_range: 'Rango Numérico',
    color: 'Paleta de Colores',
    icon_card: 'Cards con Icono',
    image_compare: 'Comparativa Visual',
    divider: 'Nueva Sección'
};

const ICON_LIST = [
    'ph-star','ph-heart','ph-lightning','ph-rocket','ph-globe','ph-paint-brush','ph-megaphone',
    'ph-camera','ph-video-camera','ph-music-note','ph-code','ph-chat-circle','ph-envelope',
    'ph-phone','ph-map-pin','ph-clock','ph-calendar','ph-chart-line','ph-shopping-cart',
    'ph-truck','ph-users','ph-user','ph-gear','ph-shield-check','ph-trophy','ph-flag','ph-sparkle'
];

function uid() { return 'f_' + Math.random().toString(36).substr(2, 9); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function showToast(msg) {
    const t = document.getElementById('fbToast');
    document.getElementById('fbToastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function updateCoverDisplay() {
    const banner = document.getElementById('headerCoverBanner');
    if (!banner) return;
    if (COVER_STYLES[currentCover]) {
        banner.style.background = COVER_STYLES[currentCover];
    } else if (currentCover.startsWith('http') || currentCover.startsWith('data:')) {
        banner.style.background = `url('${currentCover}') center/cover`;
    } else {
        banner.style.background = COVER_STYLES.nebula;
    }
}
updateCoverDisplay();

function openCoverModal() {
    document.querySelectorAll('.fb-cover-preset-card').forEach(c => c.classList.remove('selected'));
    const currCard = document.getElementById('preset_' + currentCover);
    if (currCard) currCard.classList.add('selected');
    document.getElementById('coverModalOverlay').classList.add('active');
}

function closeCoverModal() {
    document.getElementById('coverModalOverlay').classList.remove('active');
}

function selectCover(preset) {
    currentCover = preset;
    updateCoverDisplay();
    closeCoverModal();
    showToast('Portada actualizada');
}

function applyCustomCover() {
    const url = document.getElementById('customCoverUrlInput').value.trim();
    if (!url) return;
    currentCover = url;
    updateCoverDisplay();
    closeCoverModal();
    showToast('Portada personalizada aplicada');
}

function copyPublicLink() {
    if (!PUBLIC_URL) return;
    const basePath = window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/$/, '');
    const full = window.location.origin + (basePath ? basePath : '') + '/' + PUBLIC_URL;
    navigator.clipboard.writeText(full).then(() => {
        showToast('¡Enlace corto copiado: ' + full + '!');
    }).catch(() => {
        prompt('Copia este enlace corto:', full);
    });
}

function switchTab(tab) {
    const isEditor = (tab === 'editor');
    document.getElementById('viewEditor').style.display = isEditor ? 'block' : 'none';
    document.getElementById('viewSettings').style.display = isEditor ? 'none' : 'block';
    document.getElementById('tabBtnEditor').classList.toggle('active', isEditor);
    document.getElementById('tabBtnSettings').classList.toggle('active', !isEditor);
    const sb = document.getElementById('studioSidebar');
    if (sb) sb.style.display = isEditor ? 'flex' : 'none';
}

function addField(type, atIdx = null) {
    const defs = {
        text: { l: 'Pregunta sin título', p: 'Texto de respuesta corta' },
        textarea: { l: 'Pregunta sin título', p: 'Texto de respuesta larga' },
        email: { l: 'Correo electrónico', p: 'tu@email.com' },
        phone: { l: 'Teléfono', p: '+51 999 999 999' },
        date: { l: 'Fecha', p: '' },
        select: { l: 'Pregunta sin título', p: '', o: ['Opción 1', 'Opción 2'] },
        checkbox: { l: 'Pregunta sin título', p: '', o: ['Opción 1', 'Opción 2'] },
        dropdown: { l: 'Pregunta sin título', p: '', o: ['Opción 1', 'Opción 2'] },
        file: { l: 'Subir archivo', p: '' },
        range: { l: 'Escala de satisfacción', p: '' },
        number_range: { l: 'Rango numérico', p: '' },
        color: { l: 'Elige tus colores preferidos', p: '' },
        icon_card: { l: 'Elige una opción', p: '' },
        image_compare: { l: '¿Cuál es la opción correcta?', p: '' },
        divider: { l: 'Sección sin título', p: '' }
    };
    const d = defs[type] || { l: 'Pregunta sin título', p: '' };
    const field = {
        id: uid(),
        type,
        label: d.l,
        placeholder: d.p,
        required: false,
        width: 'full',
        options: d.o ? [...d.o] : undefined,
        description: ''
    };
    if (type === 'range') { field.range_min = 1; field.range_max = 5; field.range_label_min = 'Bajo'; field.range_label_max = 'Alto'; }
    if (type === 'number_range') { field.nr_min = 18; field.nr_max = 65; field.nr_step = 1; }
    if (type === 'color') { field.color_options = ['#4f46e5', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6']; field.color_multi = true; }
    if (type === 'icon_card') { field.icon_options = [{ icon: 'ph-star', text: 'Opción 1' }, { icon: 'ph-rocket', text: 'Opción 2' }]; field.icon_multi = false; }
    if (type === 'image_compare') {
        field.compare_options = [
            { id: uid(), opt_type: 'image', image: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80', icon: 'ph-check-circle', title: 'Propuesta A', desc: 'Diseño conceptual moderno', is_correct: true },
            { id: uid(), opt_type: 'image', image: 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=600&q=80', icon: 'ph-sparkle', title: 'Propuesta B', desc: 'Enfoque alternativo', is_correct: false }
        ];
        field.compare_multi = false;
    }
    
    if (atIdx !== null && atIdx >= 0 && atIdx <= fields.length) {
        fields.splice(atIdx, 0, field);
        activeIdx = atIdx;
    } else {
        fields.push(field);
        activeIdx = fields.length - 1;
    }
    
    renderFields();
    
    setTimeout(() => {
        const el = document.querySelector(`[data-idx="${activeIdx}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 50);
}

function setActive(idx) {
    if (activeIdx === idx) return;
    activeIdx = idx;
    renderFields();
}

function updateHeaderStats() {
    const titleVal = document.getElementById('formTitle').value.trim();
    const titleTextEl = document.getElementById('topbarTitleText');
    if (titleTextEl) {
        titleTextEl.textContent = titleVal || 'Nuevo Formulario';
    }
}

function renderFields() {
    const list = document.getElementById('fieldsList');
    const emptyState = document.getElementById('fbEmptyState');
    
    updateHeaderStats();

    if (fields.length === 0) {
        list.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }
    if (emptyState) emptyState.style.display = 'none';

    list.innerHTML = '';
    let numSections = 1;
    fields.forEach(f => { if (f.type === 'divider') numSections++; });
    let currentSection = 1;
    let questionCounter = 0;

    fields.forEach((f, i) => {
        // Inter-card Insert Divider
        const dividerDiv = document.createElement('div');
        dividerDiv.className = 'fb-insert-divider';
        dividerDiv.innerHTML = `
            <div class="fb-insert-line"></div>
            <button type="button" class="fb-insert-btn" onclick="addField('text', ${i})" title="Insertar pregunta aquí">
                <i class="ph-bold ph-plus"></i>
            </button>
        `;
        list.appendChild(dividerDiv);

        const card = document.createElement('div');
        card.className = 'fb-question-card' + (i === activeIdx ? ' active' : '');
        card.dataset.idx = i;
        card.draggable = true;

        card.addEventListener('click', (e) => {
            if (e.target.closest('input,select,textarea,button,label')) {
                if (activeIdx !== i) {
                    document.querySelectorAll('.fb-question-card.active').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    activeIdx = i;
                }
                return;
            }
            if (activeIdx !== i) { activeIdx = i; renderFields(); }
        });

        card.addEventListener('dragstart', e => { 
            dragSrcIdx = i; 
            card.style.opacity = '0.4'; 
            e.dataTransfer.effectAllowed = 'move'; 
        });
        card.addEventListener('dragend', () => { 
            card.style.opacity = '1'; 
            dragSrcIdx = null; 
        });
        card.addEventListener('dragover', e => { 
            e.preventDefault(); 
            e.dataTransfer.dropEffect = 'move'; 
        });
        card.addEventListener('drop', e => {
            e.preventDefault();
            if (dragSrcIdx === null || dragSrcIdx === i) return;
            const m = fields.splice(dragSrcIdx, 1)[0];
            fields.splice(i, 0, m);
            activeIdx = i;
            renderFields();
        });

        let bodyHtml = '';

        if (f.type === 'divider') {
            currentSection++;
            bodyHtml = `
                <div style="font-size: 0.72rem; font-weight: 700; color: var(--app-accent); text-transform: uppercase; margin-bottom: 0.4rem;">
                    Sección ${currentSection} de ${numSections}
                </div>
                <input style="font-size: 1.2rem; font-weight: 800; color: var(--app-text); border: none; background: transparent; width: 100%; outline: none;" value="${esc(f.label)}" placeholder="Título de la Sección..." onfocus="setActive(${i})" oninput="fields[${i}].label=this.value" autocomplete="off">
                <input style="font-size: 0.85rem; color: var(--app-text-muted); border: none; background: transparent; width: 100%; outline: none; margin-top: 0.25rem;" value="${esc(f.description||'')}" placeholder="Descripción de la sección (opcional)..." oninput="fields[${i}].description=this.value" autocomplete="off">
            `;
        } else {
            questionCounter++;
            const qNumStr = questionCounter < 10 ? '0' + questionCounter : questionCounter;

            if (i === activeIdx) {
                // Active Editing Mode
                let typeOpts = '';
                ['text','textarea','select','checkbox','dropdown','email','phone','date','file','range','number_range','color','icon_card','image_compare'].forEach(t => {
                    typeOpts += `<option value="${t}" ${f.type===t?'selected':''}>${TYPE_MAP[t]}</option>`;
                });
                bodyHtml = `
                    <div class="fb-q-header-row">
                        <input class="fb-q-label-input" value="${esc(f.label)}" placeholder="Escribe tu pregunta..." oninput="fields[${i}].label=this.value" autocomplete="off">
                        <select class="fb-type-select-styled" onchange="changeFieldType(${i}, this.value)">
                            ${typeOpts}
                        </select>
                    </div>
                `;
                bodyHtml += renderFieldContent(f, i);
            } else {
                // Collapsed Preview Mode
                bodyHtml = `
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--app-text); margin-bottom: 0.25rem;">
                                ${esc(f.label)}${f.required ? '<span style="color:#ef4444; margin-left: 3px;">*</span>' : ''}
                            </div>
                            <div style="font-size: 0.8125rem; color: var(--app-text-muted);">${renderCollapsedPreview(f)}</div>
                        </div>
                        <span style="font-size: 0.72rem; font-weight: 600; color: var(--app-text-muted); background: var(--app-surface-sub); border: 1px solid var(--app-border); padding: 0.25rem 0.6rem; border-radius: 6px; white-space: nowrap;">
                            ${TYPE_MAP[f.type] || f.type}
                        </span>
                    </div>
                `;
            }
        }

        const topBarHtml = `
            <div class="fb-card-top-bar">
                <span class="fb-card-number-badge">${f.type === 'divider' ? 'SECCIÓN' : (questionCounter < 10 ? '0' + questionCounter : questionCounter)}</span>
                <div class="fb-card-drag-handle" title="Arrastrar para reordenar"><i class="ph-bold ph-dots-six-vertical"></i></div>
            </div>
        `;

        card.innerHTML = `${topBarHtml}<div class="fb-card-content">${bodyHtml}</div>`;

        // Footer for active cards
        if (i === activeIdx) {
            const footerHtml = (f.type !== 'divider') ? `
            <div class="fb-card-footer">
                <button type="button" class="fb-card-icon-btn" onclick="event.stopPropagation();dupField(${i})" title="Duplicar pregunta"><i class="ph-bold ph-copy"></i></button>
                <button type="button" class="fb-card-icon-btn btn-delete" onclick="event.stopPropagation();delField(${i})" title="Eliminar"><i class="ph-bold ph-trash"></i></button>
                <div class="fb-v-divider"></div>
                <label class="app-switch-label" onclick="event.stopPropagation()">
                    <span>Obligatorio</span>
                    <span class="app-switch">
                        <input type="checkbox" ${f.required?'checked':''} onchange="fields[${i}].required=this.checked">
                        <span class="app-switch-slider"></span>
                    </span>
                </label>
            </div>` : `
            <div class="fb-card-footer">
                <button type="button" class="fb-card-icon-btn" onclick="event.stopPropagation();dupField(${i})" title="Duplicar"><i class="ph-bold ph-copy"></i></button>
                <button type="button" class="fb-card-icon-btn btn-delete" onclick="event.stopPropagation();delField(${i})" title="Eliminar"><i class="ph-bold ph-trash"></i></button>
            </div>`;
            card.innerHTML += footerHtml;
        }
        list.appendChild(card);
    });
}

function changeFieldType(idx, newType) {
    fields[idx].type = newType;
    if (['select','checkbox','dropdown'].includes(newType) && (!fields[idx].options || fields[idx].options.length === 0)) {
        fields[idx].options = ['Opción 1', 'Opción 2'];
    }
    if (newType === 'color' && !fields[idx].color_options) {
        fields[idx].color_options = ['#4f46e5', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6'];
    }
    if (newType === 'icon_card' && !fields[idx].icon_options) {
        fields[idx].icon_options = [{icon:'ph-star',text:'Opción 1'}, {icon:'ph-rocket',text:'Opción 2'}];
    }
    if (newType === 'image_compare' && !fields[idx].compare_options) {
        fields[idx].compare_options = [
            { id: uid(), opt_type: 'image', image: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80', icon: 'ph-check-circle', title: 'Propuesta A', desc: 'Diseño conceptual moderno', is_correct: true },
            { id: uid(), opt_type: 'image', image: 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=600&q=80', icon: 'ph-sparkle', title: 'Propuesta B', desc: 'Enfoque alternativo', is_correct: false }
        ];
        fields[idx].compare_multi = false;
    }
    renderFields();
}

function renderFieldContent(f, i) {
    if (f.type === 'text' || f.type === 'email' || f.type === 'phone') {
        const ph = f.type==='text' ? 'Texto de respuesta corta' : (f.type==='email' ? 'correo@ejemplo.com' : '+51 999 999 999');
        return `<div style="border-bottom: 1px solid var(--app-border); padding: 0.5rem 0; font-size: 0.85rem; color: var(--app-text-muted); max-width: 65%;">${ph}</div>`;
    }
    if (f.type === 'textarea') {
        return `<div style="border-bottom: 1px solid var(--app-border); padding: 0.5rem 0; font-size: 0.85rem; color: var(--app-text-muted); max-width: 85%;">Texto de respuesta larga (párrafo)</div>`;
    }
    if (f.type === 'date') {
        return `<div style="display: flex; align-items: center; gap: 0.6rem; border-bottom: 1px solid var(--app-border); padding: 0.5rem 0; font-size: 0.85rem; color: var(--app-text-muted); max-width: 50%;"><i class="ph-bold ph-calendar-blank" style="color: var(--app-accent);"></i> Día / Mes / Año</div>`;
    }
    if (f.type === 'file') {
        if (typeof f.file_max_count === 'undefined') f.file_max_count = 1;
        if (typeof f.file_max_size === 'undefined') f.file_max_size = 10;
        const types = f.file_types || [];
        let html = `<div style="display:flex; flex-direction:column; gap:10px; margin-bottom:1.15rem; padding:12px; background:var(--app-surface-sub); border-radius:12px; border:1px solid var(--app-border)">`;
        html += `<div>
            <label style="display:flex; align-items:center; gap:0.55rem; font-size:0.78rem; font-weight:600; color:var(--app-text); cursor:pointer; margin-bottom:8px">
                <input type="checkbox" ${f.file_restrict?'checked':''} onchange="event.stopPropagation();fields[${i}].file_restrict=this.checked;renderFields()"> Permitir solo tipos de archivo específicos
            </label>
            <div style="display:${f.file_restrict?'flex':'none'}; flex-wrap:wrap; gap:8px; padding:10px; background:var(--app-surface); border-radius:10px; border:1px solid var(--app-border)">
                ${['Documento','PDF','Imagen','Video','Audio'].map(t=>`
                    <label style="font-size:0.75rem; font-weight:500; display:flex; align-items:center; gap:5px; cursor:pointer; color:var(--app-text)"><input type="checkbox" ${types.includes(t)?'checked':''} onchange="event.stopPropagation();if(this.checked){fields[${i}].file_types=(fields[${i}].file_types||[]);fields[${i}].file_types.push('${t}');}else{fields[${i}].file_types=fields[${i}].file_types.filter(x=>x!=='${t}');}renderFields()"> ${t}</label>
                `).join('')}
            </div>
        </div>`;
        html += `<div style="display:flex; gap:1rem; flex-wrap:wrap">
            <div style="flex:1; min-width:140px">
                <label style="display:block; font-size:0.72rem; font-weight:700; color:var(--app-text-muted); margin-bottom:4px; text-transform:uppercase;">Cantidad máxima</label>
                <select style="width:100%; padding:7px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface); color:var(--app-text)" onchange="event.stopPropagation();fields[${i}].file_max_count=parseInt(this.value);renderFields()">
                    ${[1,5,10].map(v=>`<option value="${v}" ${f.file_max_count===v?'selected':''}>${v} archivo(s)</option>`).join('')}
                </select>
            </div>
            <div style="flex:1; min-width:140px">
                <label style="display:block; font-size:0.72rem; font-weight:700; color:var(--app-text-muted); margin-bottom:4px; text-transform:uppercase;">Tamaño máximo</label>
                <select style="width:100%; padding:7px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface); color:var(--app-text)" onchange="event.stopPropagation();fields[${i}].file_max_size=parseInt(this.value);renderFields()">
                    ${[1,10,25,50,100].map(v=>`<option value="${v}" ${f.file_max_size===v?'selected':''}>${v} MB</option>`).join('')}
                </select>
            </div>
        </div></div>`;
        html += `<div style="border:2px dashed var(--app-border); border-radius:14px; padding:1.75rem; text-align:center; color:var(--app-text-muted); font-size:0.85rem;"><i class="ph-bold ph-cloud-arrow-up" style="font-size:2rem; color:var(--app-accent); display:block; margin-bottom:0.4rem;"></i>Zona de subida de archivos</div>`;
        return html;
    }
    if (f.type === 'select' || f.type === 'checkbox' || f.type === 'dropdown') {
        if (typeof f.is_multi === 'undefined') f.is_multi = (f.type === 'checkbox');
        const isCheck = f.is_multi;
        const isDrop = f.type === 'dropdown';
        let html = `<div style="margin-bottom:0.75rem;"><label style="display:flex; align-items:center; gap:0.45rem; font-size:0.78rem; font-weight:600; color:var(--app-text-muted); cursor:pointer"><input type="checkbox" ${f.is_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].is_multi=this.checked;renderFields()"> Permitir selección múltiple</label></div>`;
        html += '<div class="fb-options-list">';
        (f.options || []).forEach((o, oi) => {
            html += `<div class="fb-opt-item">
                <div class="${isDrop?'':'fb-opt-indicator'} ${isCheck?'checkbox-style':''}" style="${isDrop?'font-size:0.85rem; color:var(--app-text-muted); width:20px; text-align:center; font-weight:600':''}">${isDrop?(oi+1)+'.':''}</div>
                <input class="fb-opt-input" value="${esc(o)}" oninput="fields[${i}].options[${oi}]=this.value" onclick="event.stopPropagation()" autocomplete="off">
                <button type="button" class="fb-opt-delete-btn" onclick="event.stopPropagation();fields[${i}].options.splice(${oi},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `</div><div class="fb-add-opt-row"><div class="${isDrop?'':'fb-opt-indicator'} ${isCheck?'checkbox-style':''}"></div> <button type="button" class="fb-btn-link" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Opción '+((fields[${i}].options||[]).length+1));renderFields()"><i class="ph-bold ph-plus"></i> Añadir opción</button> ${isDrop?'':`o <button type="button" class="fb-btn-link" style="color:var(--app-text-muted)" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Otro');renderFields()">añadir "Otro"</button>`}</div>`;
        return html;
    }
    if (f.type === 'range') {
        const mn = f.range_min || 1, mx = Math.min(f.range_max || 5, 10), lMin = f.range_label_min || '', lMax = f.range_label_max || '';
        let dots = '';
        for (let n = mn; n <= mx; n++) dots += `<div style="width:32px; height:32px; border:1px solid var(--app-border); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.85rem; color:var(--app-text); font-weight:700; background:var(--app-surface-sub);">${n}</div>`;
        let html = `<div style="display:flex; gap:0.65rem; margin-bottom:0.85rem; flex-wrap:wrap">
            <div style="flex:1; min-width:65px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Mín</label><input type="number" value="${mn}" min="0" max="10" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" onchange="fields[${i}].range_min=Math.max(0,Math.min(10,parseInt(this.value)||0));renderFields()"></div>
            <div style="flex:1; min-width:65px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Máx</label><input type="number" value="${mx}" min="1" max="10" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" onchange="fields[${i}].range_max=Math.max(1,Math.min(10,parseInt(this.value)||5));renderFields()"></div>
            <div style="flex:2; min-width:110px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Etiqueta mín</label><input value="${esc(lMin)}" placeholder="ej: Bajo" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" oninput="fields[${i}].range_label_min=this.value"></div>
            <div style="flex:2; min-width:110px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Etiqueta máx</label><input value="${esc(lMax)}" placeholder="ej: Excelente" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" oninput="fields[${i}].range_label_max=this.value"></div>
        </div>`;
        html += `<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">${lMin?`<span style="font-size:0.78rem; font-weight:600; color:var(--app-text-muted)">${esc(lMin)}</span>`:''}<div style="display:flex; gap:6px; flex-wrap:wrap">${dots}</div>${lMax?`<span style="font-size:0.78rem; font-weight:600; color:var(--app-text-muted)">${esc(lMax)}</span>`:''}</div>`;
        return html;
    }
    if (f.type === 'number_range') {
        const nrMin = f.nr_min ?? 18, nrMax = f.nr_max ?? 65, nrStep = f.nr_step ?? 1;
        let html = `<div style="display:flex; gap:0.65rem; margin-bottom:0.85rem; flex-wrap:wrap">
            <div style="flex:1; min-width:75px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Desde</label><input type="number" value="${nrMin}" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" onchange="fields[${i}].nr_min=parseInt(this.value)"></div>
            <div style="flex:1; min-width:75px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Hasta</label><input type="number" value="${nrMax}" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" onchange="fields[${i}].nr_max=parseInt(this.value)"></div>
            <div style="flex:1; min-width:75px"><label style="font-size:0.7rem; font-weight:700; color:var(--app-text-muted); text-transform:uppercase;">Paso</label><input type="number" value="${nrStep}" min="1" style="width:100%; padding:0.45rem; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface-sub); color:var(--app-text)" onchange="fields[${i}].nr_step=parseInt(this.value)||1"></div>
        </div>`;
        html += `<div style="display:flex; align-items:center; gap:10px"><span style="font-size:0.85rem; color:var(--app-text-muted); font-weight:700">${nrMin}</span><div style="flex:1; height:6px; background:var(--app-border); border-radius:99px; position:relative"><div style="position:absolute; left:0; top:0; height:100%; width:50%; background:var(--app-accent); border-radius:99px"></div></div><span style="font-size:0.85rem; color:var(--app-text-muted); font-weight:700">${nrMax}</span></div>`;
        return html;
    }
    if (f.type === 'color') {
        const colors = f.color_options || ['#4f46e5'];
        let html = `<div style="margin-bottom:0.6rem;"><span style="font-size:0.78rem; font-weight:600; color:var(--app-text-muted);">Muestras de color seleccionables para el cliente:</span></div>`;
        html += '<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center">';
        colors.forEach((c, ci) => {
            html += `<div style="position:relative; display:flex; flex-direction:column; align-items:center; gap:4px">
                <input type="color" value="${c}" style="width:40px; height:40px; border:2px solid var(--app-border); border-radius:10px; cursor:pointer; padding:2px; background:var(--app-surface);" onchange="event.stopPropagation();fields[${i}].color_options[${ci}]=this.value;renderFields()">
                <span style="font-size:0.65rem; color:var(--app-text-muted); font-family:monospace">${c}</span>
                <button type="button" style="position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:#ef4444; color:white; border:none; cursor:pointer; font-size:0.7rem; display:flex; align-items:center; justify-content:center;" onclick="event.stopPropagation();fields[${i}].color_options.splice(${ci},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `<button type="button" style="width:40px; height:40px; border:1px dashed var(--app-border); border-radius:10px; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--app-accent)" onclick="event.stopPropagation();if(!fields[${i}].color_options)fields[${i}].color_options=['#4f46e5'];fields[${i}].color_options.push('#'+Math.floor(Math.random()*16777215).toString(16).padStart(6,'0'));renderFields()" title="Añadir color"><i class="ph-bold ph-plus"></i></button></div>`;
        return html;
    }
    if (f.type === 'icon_card') {
        const opts = f.icon_options || [];
        let html = `<div style="margin-bottom:0.75rem;"><label style="display:flex; align-items:center; gap:0.45rem; font-size:0.78rem; font-weight:600; color:var(--app-text-muted); cursor:pointer"><input type="checkbox" ${f.icon_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].icon_multi=this.checked;renderFields()"> Permitir selección múltiple</label></div>`;
        html += '<div style="display:flex; flex-direction:column; gap:8px">';
        opts.forEach((o, oi) => {
            let iconPicker = `<select style="width:48px; padding:5px; border:1px solid var(--app-border); border-radius:8px; font-size:1rem; background:var(--app-surface-sub); color:var(--app-text); cursor:pointer; text-align:center" onchange="event.stopPropagation();fields[${i}].icon_options[${oi}].icon=this.value;renderFields()">`;
            ICON_LIST.forEach(ic => { iconPicker += `<option value="${ic}" ${o.icon===ic?'selected':''}>${ic.replace('ph-','')}</option>`; });
            iconPicker += '</select>';
            html += `<div style="display:flex; align-items:center; gap:10px; border:1px solid var(--app-border); border-radius:12px; padding:8px 12px; background:var(--app-surface-sub)">
                <div style="width:36px; height:36px; background:var(--app-accent-light); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--app-accent); flex-shrink:0"><i class="ph-bold ${o.icon}"></i></div>
                <input value="${esc(o.text)}" style="flex:1; border:none; outline:none; font-size:0.875rem; background:transparent; color:var(--app-text); font-weight:600;" oninput="fields[${i}].icon_options[${oi}].text=this.value" onclick="event.stopPropagation()" autocomplete="off">
                ${iconPicker}
                <button type="button" class="fb-opt-delete-btn" onclick="event.stopPropagation();fields[${i}].icon_options.splice(${oi},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `</div><div style="margin-top:10px"><button type="button" class="fb-btn-link" onclick="event.stopPropagation();if(!fields[${i}].icon_options)fields[${i}].icon_options=[{icon:'ph-star',text:'Opción 1'}];fields[${i}].icon_options.push({icon:ICON_LIST[Math.floor(Math.random()*ICON_LIST.length)],text:'Opción '+(fields[${i}].icon_options.length+1)});renderFields()"><i class="ph-bold ph-plus"></i> Añadir Card</button></div>`;
        return html;
    }
    if (f.type === 'image_compare') {
        const opts = f.compare_options || [];
        const isMulti = !!f.compare_multi;
        let html = `
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.85rem; padding-bottom:0.6rem; border-bottom:1px solid var(--app-border); flex-wrap:wrap; gap:8px;">
                <label style="display:flex; align-items:center; gap:0.45rem; font-size:0.78rem; font-weight:600; color:var(--app-text); cursor:pointer">
                    <input type="checkbox" ${isMulti ? 'checked' : ''} onchange="event.stopPropagation();fields[${i}].compare_multi=this.checked;renderFields()">
                    Permitir selección múltiple
                </label>
                <span style="font-size:0.72rem; color:var(--app-text-muted);"><i class="ph-bold ph-check-circle" style="color:#10b981;"></i> Marca qué opción es la correcta con el botón verde</span>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
        `;

        opts.forEach((o, oi) => {
            const isImg = (o.opt_type || 'image') === 'image';
            const isCorr = !!o.is_correct;
            const letter = String.fromCharCode(65 + oi);

            let iconPicker = `<select style="padding:6px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.82rem; background:var(--app-surface); color:var(--app-text); cursor:pointer;" onchange="event.stopPropagation();fields[${i}].compare_options[${oi}].icon=this.value;renderFields()">`;
            ICON_LIST.forEach(ic => { iconPicker += `<option value="${ic}" ${o.icon===ic?'selected':''}>${ic.replace('ph-','')}</option>`; });
            iconPicker += '</select>';

            html += `
                <div style="border:1.5px solid ${isCorr ? '#10b981' : 'var(--app-border)'}; border-radius:14px; padding:12px 14px; background:var(--app-surface-sub); transition:all 0.2s ease;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:8px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:0.72rem; font-weight:800; background:var(--app-surface); border:1px solid var(--app-border); padding:3px 8px; border-radius:6px; color:var(--app-text);">Opción ${letter}</span>
                            
                            <div style="display:inline-flex; border:1px solid var(--app-border); border-radius:8px; overflow:hidden;">
                                <button type="button" style="padding:4px 10px; font-size:0.75rem; font-weight:600; border:none; cursor:pointer; background:${isImg?'var(--app-accent)':'var(--app-surface)'}; color:${isImg?'#ffffff':'var(--app-text-muted)'};" onclick="event.stopPropagation();fields[${i}].compare_options[${oi}].opt_type='image';renderFields()">
                                    <i class="ph-bold ph-image"></i> Imagen
                                </button>
                                <button type="button" style="padding:4px 10px; font-size:0.75rem; font-weight:600; border:none; cursor:pointer; background:${!isImg?'var(--app-accent)':'var(--app-surface)'}; color:${!isImg?'#ffffff':'var(--app-text-muted)'};" onclick="event.stopPropagation();fields[${i}].compare_options[${oi}].opt_type='icon';renderFields()">
                                    <i class="ph-bold ph-sparkle"></i> Ícono
                                </button>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" style="padding:5px 12px; font-size:0.72rem; font-weight:700; border-radius:8px; cursor:pointer; border:1px solid ${isCorr?'#10b981':'var(--app-border)'}; background:${isCorr?'#10b981':'var(--app-surface)'}; color:${isCorr?'#ffffff':'var(--app-text-muted)'}; display:flex; align-items:center; gap:5px; transition:all 0.15s ease;" onclick="event.stopPropagation();${!isMulti ? `fields[${i}].compare_options.forEach(x=>x.is_correct=false);` : ''}fields[${i}].compare_options[${oi}].is_correct=!${isCorr};renderFields()" title="Marcar como opción correcta">
                                <i class="ph-bold ${isCorr?'ph-check-circle':'ph-circle'}"></i> ${isCorr ? 'Opción Correcta ✓' : 'Marcar Correcta'}
                            </button>
                            
                            <button type="button" class="fb-opt-delete-btn" onclick="event.stopPropagation();fields[${i}].compare_options.splice(${oi},1);renderFields()" title="Eliminar"><i class="ph-bold ph-x"></i></button>
                        </div>
                    </div>

                    ${isImg ? `
                        <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                            <div style="width:64px; height:52px; border-radius:8px; overflow:hidden; border:1px solid var(--app-border); flex-shrink:0; background:var(--app-surface); display:flex; align-items:center; justify-content:center;">
                                ${o.image ? `<img src="${esc(o.image)}" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'">` : '<i class="ph-bold ph-image" style="color:var(--app-text-muted); font-size:1.3rem;"></i>'}
                            </div>
                            <input value="${esc(o.image||'')}" placeholder="https://ejemplo.com/imagen.jpg (URL de la imagen)" style="flex:1; padding:7px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface); color:var(--app-text); outline:none;" oninput="fields[${i}].compare_options[${oi}].image=this.value" autocomplete="off">
                        </div>
                    ` : `
                        <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                            <div style="width:42px; height:42px; border-radius:10px; background:var(--app-accent-light); color:var(--app-accent); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
                                <i class="ph-bold ${o.icon || 'ph-star'}"></i>
                            </div>
                            <div style="flex:1;">${iconPicker}</div>
                        </div>
                    `}

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <input value="${esc(o.title||'')}" placeholder="Título de la opción (ej: Propuesta A)" style="padding:7px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.85rem; font-weight:600; background:var(--app-surface); color:var(--app-text); outline:none;" oninput="fields[${i}].compare_options[${oi}].title=this.value" autocomplete="off">
                        <input value="${esc(o.desc||'')}" placeholder="Descripción opcional..." style="padding:7px 10px; border:1px solid var(--app-border); border-radius:8px; font-size:0.8rem; background:var(--app-surface); color:var(--app-text); outline:none;" oninput="fields[${i}].compare_options[${oi}].desc=this.value" autocomplete="off">
                    </div>
                </div>
            `;
        });

        html += `
            </div>
            <div style="margin-top:12px;">
                <button type="button" class="fb-btn-link" onclick="event.stopPropagation();if(!fields[${i}].compare_options)fields[${i}].compare_options=[];const nextIdx=(fields[${i}].compare_options.length);fields[${i}].compare_options.push({id:uid(),opt_type:'image',image:'',icon:'ph-star',title:'Opción '+String.fromCharCode(65+nextIdx),desc:'',is_correct:false});renderFields()">
                    <i class="ph-bold ph-plus-circle"></i> Añadir Opción Comparativa
                </button>
            </div>
        `;
        return html;
    }
    return '';
}

function renderCollapsedPreview(f) {
    if (f.type === 'text' || f.type === 'email' || f.type === 'phone') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">${f.placeholder || 'Texto de respuesta corta'}</span>`;
    if (f.type === 'textarea') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">Párrafo de respuesta larga</span>`;
    if (f.type === 'date') return `<span style="font-size:0.8rem; color:var(--app-text-muted);"><i class="ph-bold ph-calendar-blank"></i> Día / Mes / Año</span>`;
    if (f.type === 'file') return '<span style="font-size:0.8rem; color:var(--app-text-muted);"><i class="ph-bold ph-cloud-arrow-up"></i> Subida de archivos</span>';
    if (f.type === 'select') return (f.options||[]).map(o => `<div style="display:inline-flex; align-items:center; gap:0.4rem; margin-right:0.75rem;"><span style="width:8px; height:8px; border:2px solid var(--app-border); border-radius:50%; display:inline-block;"></span><span style="font-size:0.8rem;">${esc(o)}</span></div>`).join('');
    if (f.type === 'checkbox') return (f.options||[]).map(o => `<div style="display:inline-flex; align-items:center; gap:0.4rem; margin-right:0.75rem;"><span style="width:8px; height:8px; border:2px solid var(--app-border); border-radius:2px; display:inline-block;"></span><span style="font-size:0.8rem;">${esc(o)}</span></div>`).join('');
    if (f.type === 'dropdown') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">${(f.options||[]).length} opciones disponibles</span>`;
    if (f.type === 'range') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">Escala de ${f.range_min||1} a ${f.range_max||5}</span>`;
    if (f.type === 'number_range') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">Rango: ${f.nr_min??18} - ${f.nr_max??65}</span>`;
    if (f.type === 'color') return `<div style="display:inline-flex; gap:4px;">${(f.color_options||['#4f46e5']).map(c=>`<div style="width:12px; height:12px; border-radius:50%; background:${c};"></div>`).join('')}</div>`;
    if (f.type === 'icon_card') return `<span style="font-size:0.8rem; color:var(--app-text-muted);">${(f.icon_options||[]).length} cards configuradas</span>`;
    if (f.type === 'image_compare') return `<span style="font-size:0.8rem; color:var(--app-text-muted);"><i class="ph-bold ph-scales"></i> Comparativa: ${(f.compare_options||[]).length} opciones</span>`;
    return '';
}

function dupField(idx) {
    const clone = JSON.parse(JSON.stringify(fields[idx]));
    clone.id = uid();
    fields.splice(idx + 1, 0, clone);
    activeIdx = idx + 1;
    renderFields();
    showToast('Pregunta duplicada');
}

function delField(idx) {
    fields.splice(idx, 1);
    if (activeIdx >= fields.length) activeIdx = fields.length - 1;
    if (activeIdx < 0) activeIdx = null;
    renderFields();
    showToast('Pregunta eliminada');
}

async function saveForm(status) {
    const title = document.getElementById('formTitle').value.trim();
    if (!title) {
        alert('El título del formulario es obligatorio.');
        document.getElementById('formTitle').focus();
        return;
    }
    const fd = new FormData();
    fd.append('id', FORM_ID);
    fd.append('title', title);
    fd.append('description', document.getElementById('formDesc').value);
    fd.append('fields_json', JSON.stringify(fields));
    fd.append('settings_json', JSON.stringify({
        show_logo: document.getElementById('settShowLogo').checked,
        require_name: document.getElementById('settRequireName').checked,
        require_email: document.getElementById('settRequireEmail').checked,
        multi_step: document.getElementById('settMultiStep').checked,
        view_style: document.getElementById('settViewStyle').value,
        welcome_screen: document.getElementById('settWelcomeScreen').checked,
        cover_image: currentCover
    }));
    fd.append('status', status);

    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Guardando...';
    btn.disabled = true;

    try {
        const res = await fetch('index.php?module=forms&action=ajax_save_template', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                btn.innerHTML = '<i class="ph-bold ph-check"></i> ¡Guardado!';
                const badge = document.getElementById('topbarStatusBadge');
                if (badge) {
                    badge.className = 'fb-status-pill ' + (status === 'active' ? 'fb-pill-active' : 'fb-pill-draft');
                    badge.textContent = (status === 'active' ? 'Publicado' : 'Borrador');
                }
                showToast(status === 'active' ? '¡Publicado con éxito!' : 'Borrador guardado');
                setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 2000);
            }
        } else {
            alert(data.error || 'Error al guardar.');
            btn.innerHTML = orig;
            btn.disabled = false;
        }
    } catch (e) {
        alert('Error de conexión.');
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}

// Deselect active card on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('.fb-question-card') && !e.target.closest('.fb-studio-sidebar') && !e.target.closest('.fb-app-topbar') && !e.target.closest('#viewSettings') && !e.target.closest('.fb-insert-divider') && !e.target.closest('.fb-cover-modal-overlay')) {
        if (activeIdx !== null) {
            activeIdx = null;
            renderFields();
        }
    }
});

// Auto-expand textarea
document.getElementById('formDesc').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

renderFields();

// Device Preview
const LOGO_URL = '<?php echo htmlspecialchars($logoLight); ?>';

function toggleDevicePreview() {
    const o = document.getElementById('previewOverlay');
    if (o.classList.contains('active')) {
        o.classList.remove('active');
        return;
    }
    renderPreview();
    o.classList.add('active');
}

function renderPreview() {
    const phoneContent = document.getElementById('phoneInnerContent');
    if (!phoneContent) return;
    const title = document.getElementById('formTitle').value || 'Formulario sin título';
    const desc = document.getElementById('formDesc').value || '';
    const showLogo = document.getElementById('settShowLogo').checked;
    const reqName = document.getElementById('settRequireName').checked;
    const reqEmail = document.getElementById('settRequireEmail').checked;

    let logo = showLogo && LOGO_URL ? `<img src="${LOGO_URL}" style="max-height: 28px; margin-bottom: 0.75rem; filter: brightness(0) invert(1);">` : '';
    let h = '';

    if (reqName) {
        h += `<div style="margin-bottom: 1rem;"><label style="font-size: 0.8rem; font-weight: 700; color: var(--app-text); display: block; margin-bottom: 4px;">Tu Nombre <span style="color:#ef4444">*</span></label><input style="width:100%; padding:0.6rem; border:1px solid var(--app-border); border-radius:10px; background:var(--app-surface-sub); font-size:0.85rem;" placeholder="Nombre completo" disabled></div>`;
    }
    if (reqEmail) {
        h += `<div style="margin-bottom: 1rem;"><label style="font-size: 0.8rem; font-weight: 700; color: var(--app-text); display: block; margin-bottom: 4px;">Tu Correo Electrónico <span style="color:#ef4444">*</span></label><input style="width:100%; padding:0.6rem; border:1px solid var(--app-border); border-radius:10px; background:var(--app-surface-sub); font-size:0.85rem;" placeholder="correo@ejemplo.com" disabled></div>`;
    }

    fields.forEach((f, idx) => {
        if (f.type === 'divider') {
            h += `<hr style="border:none; border-top:1px solid var(--app-border); margin:1.25rem 0 0.75rem;"><h4 style="margin:0 0 0.5rem; font-size:0.95rem; font-weight:700; color:var(--app-text);">${esc(f.label)}</h4>`;
            return;
        }
        const req = f.required ? '<span style="color:#ef4444">*</span>' : '';
        let inp = '';
        if (['text','email','phone','date'].includes(f.type)) {
            inp = `<input style="width:100%; padding:0.6rem; border:1px solid var(--app-border); border-radius:10px; background:var(--app-surface-sub); font-size:0.85rem;" placeholder="${esc(f.placeholder)}" disabled>`;
        } else if (f.type === 'textarea') {
            inp = `<textarea style="width:100%; padding:0.6rem; border:1px solid var(--app-border); border-radius:10px; background:var(--app-surface-sub); font-size:0.85rem; height:60px;" placeholder="${esc(f.placeholder)}" disabled></textarea>`;
        } else if (f.type === 'select') {
            inp = (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--app-text); margin:0.35rem 0;"><input type="radio" disabled> ${esc(o)}</div>`).join('');
        } else if (f.type === 'checkbox') {
            inp = (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--app-text); margin:0.35rem 0;"><input type="checkbox" disabled> ${esc(o)}</div>`).join('');
        } else if (f.type === 'dropdown') {
            inp = `<select style="width:100%; padding:0.6rem; border:1px solid var(--app-border); border-radius:10px; background:var(--app-surface-sub); font-size:0.85rem;" disabled><option>Selecciona una opción</option>${(f.options||[]).map(o=>`<option>${esc(o)}</option>`).join('')}</select>`;
        } else if (f.type === 'file') {
            inp = `<div style="border:2px dashed var(--app-border); border-radius:12px; padding:1.2rem; text-align:center; color:var(--app-text-muted); font-size:0.8rem;"><i class="ph-bold ph-cloud-arrow-up" style="font-size:1.6rem; color:var(--app-accent); display:block; margin-bottom:0.3rem;"></i>Subir archivos</div>`;
        } else if (f.type === 'range') {
            const mn = f.range_min||1, mx = Math.min(f.range_max||5, 10);
            let dots = '';
            for (let n = mn; n <= mx; n++) dots += `<div style="width:24px; height:24px; border:1px solid var(--app-border); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:0.75rem; color:var(--app-text); font-weight:700;">${n}</div>`;
            inp = `<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap">${f.range_label_min?`<span style="font-size:0.72rem; color:var(--app-text-muted)">${esc(f.range_label_min)}</span>`:''}<div style="display:flex; gap:4px;">${dots}</div>${f.range_label_max?`<span style="font-size:0.72rem; color:var(--app-text-muted)">${esc(f.range_label_max)}</span>`:''}</div>`;
        } else if (f.type === 'number_range') {
            inp = `<div style="display:flex; gap:8px; align-items:center;"><span style="font-size:0.8rem; font-weight:700;">${f.nr_min??18}</span><div style="flex:1; height:4px; background:var(--app-border); border-radius:2px;"></div><span style="font-size:0.8rem; font-weight:700;">${f.nr_max??65}</span></div>`;
        } else if (f.type === 'color') {
            const colors = f.color_options || ['#4f46e5'];
            inp = `<div style="display:flex; gap:6px; flex-wrap:wrap;">${colors.map(c=>`<div style="width:24px; height:24px; border-radius:50%; background:${c};"></div>`).join('')}</div>`;
        } else if (f.type === 'icon_card') {
            inp = (f.icon_options||[]).map(o => `<div style="display:flex; align-items:center; gap:8px; border:1px solid var(--app-border); border-radius:10px; padding:8px 10px; margin:4px 0;"><i class="ph-bold ${o.icon}" style="color:var(--app-accent);"></i><span style="font-size:0.82rem; font-weight:600;">${esc(o.text)}</span></div>`).join('');
        }

        h += `<div style="margin-bottom: 1.15rem;"><label style="font-size: 0.82rem; font-weight: 700; color: var(--app-text); display: block; margin-bottom: 4px;">${esc(f.label)} ${req}</label>${inp}</div>`;
    });

    const coverBg = COVER_STYLES[currentCover] || COVER_STYLES.nebula;

    phoneContent.innerHTML = `
        <div style="height: 110px; width:100%; background: ${coverBg}; position:relative; display:flex; align-items:flex-end; padding:10px 14px;">
            <div style="width:40px; height:40px; border-radius:12px; background:var(--app-surface); border:1.5px solid var(--app-border); display:flex; align-items:center; justify-content:center; margin-bottom:-18px; box-shadow:0 4px 12px rgba(0,0,0,0.3);">
                ${logo ? logo : '<i class="ph-bold ph-shield-check" style="color:var(--app-accent); font-size:1.2rem;"></i>'}
            </div>
        </div>
        <div style="padding: 1.5rem 1rem 0.5rem;">
            <h2 style="margin:0; font-size:1.15rem; font-weight:800;">${esc(title)}</h2>
            ${desc ? `<p style="margin:4px 0 0; font-size:0.75rem; color:var(--app-text-muted);">${esc(desc)}</p>` : ''}
        </div>
        <div class="fb-phone-content">
            ${h}
            <button type="button" style="width:100%; padding:0.8rem; background:var(--app-accent); color:#fff; border:none; border-radius:12px; font-weight:700; font-size:0.875rem; margin-top:1rem; cursor:not-allowed;" disabled>
                Enviar Formulario
            </button>
        </div>
    `;
}
</script>

<?php require_once 'includes/footer.php'; ?>
