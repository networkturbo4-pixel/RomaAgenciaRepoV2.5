<?php
// modules/forms/builder.php — Modern App-style Form Builder
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
require_once 'includes/header.php';
?>

<style>
/* App Builder Container & Navigation */
.fb-builder-wrapper {
    position: relative;
    max-width: 740px;
    margin: 0 auto;
    padding: 0.5rem 1rem 5rem;
}

/* App Sticky Topbar */
.fb-app-topbar {
    position: sticky;
    top: 0;
    z-index: 40;
    background: color-mix(in srgb, var(--bg-surface) 85%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border-color);
    margin: -1.5rem -1rem 1.5rem;
    padding: 0.75rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.fb-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.fb-btn-back {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--color-title);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 1.1rem;
    transition: all 0.15s ease;
}

.fb-btn-back:hover {
    background: var(--bg-surface);
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.fb-topbar-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.fb-status-pill {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 0.2rem 0.55rem;
    border-radius: 20px;
}

.fb-pill-active { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.fb-pill-draft { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

/* Segmented View Switcher (Tabs) */
.fb-segmented-tabs {
    display: flex;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    padding: 3px;
    border-radius: 10px;
    gap: 2px;
}

.fb-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-muted);
    border: none;
    background: transparent;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.fb-tab-btn:hover {
    color: var(--color-title);
}

.fb-tab-btn.active {
    background: var(--bg-surface);
    color: var(--primary-color);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

/* Topbar Actions */
.fb-topbar-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.fb-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.9rem;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: 9px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--color-title);
    cursor: pointer;
    transition: all 0.15s ease;
}

.fb-btn-action:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.fb-btn-primary {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
}

.fb-btn-primary:hover {
    filter: brightness(1.08);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
}

/* Title & Description Header Card */
.fb-header-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
    position: relative;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    border-top: 5px solid var(--primary-color);
}

.fb-title-input {
    width: 100%;
    border: none;
    border-bottom: 2px solid transparent;
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--color-title);
    background: transparent;
    font-family: inherit;
    padding: 0.25rem 0;
    outline: none;
    transition: border-color 0.2s ease;
}

.fb-title-input:focus {
    border-bottom-color: var(--primary-color);
}

.fb-desc-input {
    width: 100%;
    border: none;
    border-bottom: 1px solid transparent;
    font-size: 0.875rem;
    color: var(--text-muted);
    background: transparent;
    font-family: inherit;
    padding: 0.4rem 0 0.2rem;
    outline: none;
    margin-top: 0.4rem;
    resize: none;
    transition: border-color 0.2s ease;
}

.fb-desc-input:focus {
    border-bottom-color: var(--border-color);
}

/* Question Cards */
.fb-question-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    margin-bottom: 1rem;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    overflow: hidden;
}

.fb-question-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}

.fb-question-card.active {
    border-color: var(--primary-color);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--primary-color) 12%, transparent);
    border-left: 4px solid var(--primary-color);
}

.fb-card-drag-bar {
    display: flex;
    justify-content: center;
    padding: 6px 0 2px;
    color: var(--text-muted);
    cursor: grab;
    opacity: 0.4;
    transition: opacity 0.15s;
}

.fb-question-card:hover .fb-card-drag-bar,
.fb-question-card.active .fb-card-drag-bar {
    opacity: 0.9;
}

.fb-card-content {
    padding: 0.75rem 1.5rem 1.25rem;
}

/* Collapsed vs Active view */
.fb-question-card:not(.active) .fb-active-only {
    display: none !important;
}

.fb-question-card.active .fb-collapsed-only {
    display: none !important;
}

/* Active Editing Header */
.fb-q-header-row {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 1rem;
}

.fb-q-label-input {
    flex: 1;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--color-title);
    border: 1px solid transparent;
    background: var(--bg-color);
    border-radius: 9px;
    font-family: inherit;
    padding: 0.6rem 0.85rem;
    outline: none;
    transition: all 0.15s ease;
}

.fb-q-label-input:focus {
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 10%, transparent);
}

.fb-type-select-styled {
    padding: 0.6rem 0.85rem;
    border: 1px solid var(--border-color);
    border-radius: 9px;
    font-size: 0.8125rem;
    font-weight: 500;
    font-family: inherit;
    background: var(--bg-color);
    color: var(--color-title);
    min-width: 170px;
    cursor: pointer;
    outline: none;
    transition: border-color 0.15s;
}

.fb-type-select-styled:focus {
    border-color: var(--primary-color);
}

/* Section Card */
.fb-section-card {
    border-top: 4px solid var(--primary-color);
}

.fb-section-badge {
    background: var(--primary-color);
    color: #ffffff;
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px 6px 0 0;
    font-size: 0.72rem;
    font-weight: 700;
    position: absolute;
    top: -24px;
    left: 12px;
    letter-spacing: 0.3px;
}

.fb-section-title-input {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--color-title);
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    font-family: inherit;
    padding: 0.4rem 0;
    width: 100%;
    outline: none;
}

.fb-section-title-input:focus {
    border-bottom-color: var(--primary-color);
}

.fb-section-desc-input {
    font-size: 0.85rem;
    color: var(--text-muted);
    border: none;
    border-bottom: 1px solid transparent;
    background: transparent;
    font-family: inherit;
    padding: 0.3rem 0;
    width: 100%;
    outline: none;
    margin-top: 0.25rem;
}

.fb-section-desc-input:focus {
    border-bottom-color: var(--border-color);
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
    gap: 0.6rem;
}

.fb-opt-indicator {
    width: 18px;
    height: 18px;
    border: 2px solid var(--border-color);
    border-radius: 50%;
    flex-shrink: 0;
}

.fb-opt-indicator.checkbox-style {
    border-radius: 4px;
}

.fb-opt-input {
    flex: 1;
    border: 1px solid transparent;
    border-bottom: 1px solid var(--border-color);
    padding: 0.4rem 0.5rem;
    font-size: 0.85rem;
    background: transparent;
    font-family: inherit;
    color: var(--color-title);
    outline: none;
    transition: all 0.15s ease;
}

.fb-opt-input:focus {
    border-bottom-color: var(--primary-color);
    background: var(--bg-color);
    border-radius: 6px 6px 0 0;
}

.fb-opt-delete-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1.1rem;
    padding: 0.2rem;
    border-radius: 6px;
    opacity: 0.5;
    transition: all 0.15s ease;
}

.fb-opt-delete-btn:hover {
    opacity: 1;
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

.fb-add-opt-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-top: 0.65rem;
}

.fb-btn-link {
    color: var(--primary-color);
    background: none;
    border: none;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    font-size: inherit;
    font-family: inherit;
    text-decoration: none;
}

.fb-btn-link:hover {
    text-decoration: underline;
}

/* Card Action Footer */
.fb-card-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-surface) 60%, var(--bg-color));
}

.fb-card-icon-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
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
    background: var(--bg-surface);
    border-color: var(--border-color);
    color: var(--color-title);
}

.fb-card-icon-btn.btn-delete:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
}

.fb-v-divider {
    width: 1px;
    height: 20px;
    background: var(--border-color);
    margin: 0 0.35rem;
}

/* Modern iOS Switch Toggle */
.app-switch-label {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-title);
    cursor: pointer;
    user-select: none;
}

.app-switch {
    position: relative;
    width: 38px;
    height: 22px;
    display: inline-block;
}

.app-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.app-switch-slider {
    position: absolute;
    inset: 0;
    background: var(--border-color);
    border-radius: 22px;
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.app-switch-slider:before {
    content: '';
    position: absolute;
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background: #ffffff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.app-switch input:checked + .app-switch-slider {
    background: var(--primary-color);
}

.app-switch input:checked + .app-switch-slider:before {
    transform: translateX(16px);
}

/* Floating Component Palette Dock */
.fb-palette-dock {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 3px;
    background: color-mix(in srgb, var(--bg-surface) 90%, transparent);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 6px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    z-index: 35;
    transition: left 0.15s ease;
}

.fb-palette-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 9px;
    color: var(--text-muted);
    font-size: 1.25rem;
    transition: all 0.15s ease;
    position: relative;
}

.fb-palette-btn:hover {
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    transform: scale(1.05);
}

.fb-palette-btn[title]:hover::after {
    content: attr(title);
    position: absolute;
    left: 48px;
    background: var(--color-title);
    color: var(--bg-surface);
    font-size: 0.72rem;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    white-space: nowrap;
    font-weight: 600;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 50;
}

/* Settings Tab Panel */
.fb-settings-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.fb-setting-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.fb-setting-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.fb-setting-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.fb-setting-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-title);
}

.fb-setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.fb-setting-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.fb-setting-info h4 {
    margin: 0 0 0.15rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-title);
}

.fb-setting-info p {
    margin: 0;
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Interactive Device Preview Modal */
.fb-preview-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
}

.fb-preview-modal-overlay.active {
    display: flex;
}

.fb-preview-container {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    width: 100%;
    max-width: 460px;
    height: 90vh;
    max-height: 820px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
    overflow: hidden;
    animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.fb-preview-top {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-color);
}

.fb-preview-top h3 {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--color-title);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.fb-preview-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f8fafc;
}
[data-theme="dark"] .fb-preview-body {
    background: #0f172a;
}

/* Realistic Smartphone Mockup Frame */
.fb-phone-frame {
    background: var(--bg-surface);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    overflow: hidden;
}

.fb-phone-header {
    background: var(--primary-color);
    padding: 1.5rem;
    color: #ffffff;
}

.fb-phone-header h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
}

.fb-phone-header p {
    margin: 0.4rem 0 0;
    font-size: 0.82rem;
    opacity: 0.9;
}

.fb-phone-content {
    padding: 1.25rem;
}

/* Mobile responsive adjustments */
@media (max-width: 920px) {
    .fb-palette-dock {
        position: fixed;
        bottom: 0;
        left: 0 !important;
        right: 0;
        top: auto;
        transform: none;
        flex-direction: row;
        justify-content: center;
        border-radius: 0;
        padding: 6px 12px;
        border-left: none;
        border-right: none;
        border-bottom: none;
        overflow-x: auto;
    }
    .fb-palette-btn[title]:hover::after {
        display: none;
    }
    .fb-builder-wrapper {
        padding-bottom: 6rem;
    }
    .fb-app-topbar {
        flex-wrap: wrap;
    }
}
</style>

<!-- Topbar Navigation -->
<div class="fb-app-topbar">
    <div class="fb-topbar-left">
        <a href="index.php?module=forms&action=index" class="fb-btn-back" title="Volver a formularios">
            <i class="ph-bold ph-arrow-left"></i>
        </a>
        <div>
            <h2 class="fb-topbar-title">
                <?php echo $formData ? htmlspecialchars($formData['title']) : 'Nuevo Formulario'; ?>
                <span class="fb-status-pill <?php echo ($formData && $formData['status']==='active') ? 'fb-pill-active' : 'fb-pill-draft'; ?>" id="topbarStatusBadge">
                    <?php echo ($formData && $formData['status']==='active') ? 'Publicado' : 'Borrador'; ?>
                </span>
            </h2>
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
        <button type="button" class="fb-btn-action" onclick="toggleDevicePreview()" title="Vista previa interactiva">
            <i class="ph-bold ph-eye"></i> <span class="d-none d-md-inline">Vista Previa</span>
        </button>
        <button type="button" class="fb-btn-action" onclick="saveForm('draft')" title="Guardar como borrador">
            <i class="ph-bold ph-floppy-disk"></i> <span class="d-none d-md-inline">Borrador</span>
        </button>
        <button type="button" class="fb-btn-action fb-btn-primary" onclick="saveForm('active')" title="Publicar formulario">
            <i class="ph-bold ph-rocket-launch"></i> <span>Publicar</span>
        </button>
    </div>
</div>

<div class="fb-builder-wrapper">
    <!-- View 1: Form Canvas (Editor) -->
    <div id="viewEditor">
        <!-- Form Header Card (Title & Description) -->
        <div class="fb-header-card">
            <input type="text" class="fb-title-input" id="formTitle" placeholder="Título del formulario..." value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>" autocomplete="off">
            <textarea class="fb-desc-input" id="formDesc" rows="1" placeholder="Añade una descripción o instrucciones para tus clientes..." autocomplete="off"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
        </div>

        <!-- Dynamic Fields List Container -->
        <div id="fieldsList"></div>

        <!-- Quick Add Field Button -->
        <div style="text-align: center; margin-top: 1rem;">
            <button type="button" class="fb-btn-action" onclick="addField('text')" style="padding: 0.7rem 1.5rem; border-radius: 12px; font-weight: 600;">
                <i class="ph-bold ph-plus-circle" style="color: var(--primary-color); font-size: 1.1rem;"></i> Añadir pregunta
            </button>
        </div>
    </div>

    <!-- View 2: Settings Panel -->
    <div id="viewSettings" class="fb-settings-panel" style="display: none;">
        <!-- General Settings -->
        <div class="fb-setting-card">
            <div class="fb-setting-header">
                <div class="fb-setting-icon"><i class="ph-bold ph-sliders-horizontal"></i></div>
                <h3 class="fb-setting-title">Preferencias Generales</h3>
            </div>
            
            <div class="fb-setting-row">
                <div class="fb-setting-info">
                    <h4>Mostrar logotipo</h4>
                    <p>Muestra el logo de la agencia en la cabecera del formulario.</p>
                </div>
                <label class="app-switch">
                    <input type="checkbox" id="settShowLogo" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['show_logo'] ?? true) ? 'checked' : ''; ?>>
                    <span class="app-switch-slider"></span>
                </label>
            </div>

            <div class="fb-setting-row">
                <div class="fb-setting-info">
                    <h4>Solicitar nombre del cliente</h4>
                    <p>Añade un campo obligatorio para el nombre al inicio del formulario.</p>
                </div>
                <label class="app-switch">
                    <input type="checkbox" id="settRequireName" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['require_name'] ?? true) ? 'checked' : ''; ?>>
                    <span class="app-switch-slider"></span>
                </label>
            </div>

            <div class="fb-setting-row">
                <div class="fb-setting-info">
                    <h4>Solicitar correo electrónico</h4>
                    <p>Añade un campo obligatorio para el email de contacto.</p>
                </div>
                <label class="app-switch">
                    <input type="checkbox" id="settRequireEmail" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['require_email'] ?? true) ? 'checked' : ''; ?>>
                    <span class="app-switch-slider"></span>
                </label>
            </div>
        </div>

        <!-- Form Flow / Multi-step -->
        <div class="fb-setting-card">
            <div class="fb-setting-header">
                <div class="fb-setting-icon"><i class="ph-bold ph-steps"></i></div>
                <h3 class="fb-setting-title">Flujo de Navegación</h3>
            </div>

            <div class="fb-setting-row">
                <div class="fb-setting-info">
                    <h4>Formulario Multi-paso (Paso a paso)</h4>
                    <p>Divide el formulario en páginas interactivas usando los divisores de sección.</p>
                </div>
                <label class="app-switch">
                    <input type="checkbox" id="settMultiStep" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['multi_step'] ?? false) ? 'checked' : ''; ?>>
                    <span class="app-switch-slider"></span>
                </label>
            </div>
        </div>
    </div>
</div>

<!-- Floating Component Palette Dock -->
<div class="fb-palette-dock" id="gfToolbar">
    <button type="button" class="fb-palette-btn" onclick="addField('text')" title="Texto Corto"><i class="ph-bold ph-text-aa"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('textarea')" title="Párrafo"><i class="ph-bold ph-text-align-left"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('select')" title="Opción Múltiple"><i class="ph-bold ph-radio-button"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('checkbox')" title="Casillas de Verificación"><i class="ph-bold ph-check-square"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('dropdown')" title="Lista Desplegable"><i class="ph-bold ph-caret-down"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('file')" title="Subida de Archivos"><i class="ph-bold ph-upload-simple"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('date')" title="Fecha"><i class="ph-bold ph-calendar-blank"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('range')" title="Escala de Puntuación"><i class="ph-bold ph-dots-three-outline"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('number_range')" title="Rango Numérico"><i class="ph-bold ph-arrows-out-line-horizontal"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('color')" title="Paleta de Colores"><i class="ph-bold ph-palette"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('icon_card')" title="Cards Interactivas"><i class="ph-bold ph-cards"></i></button>
    <button type="button" class="fb-palette-btn" onclick="addField('divider')" title="Nueva Sección"><i class="ph-bold ph-equals"></i></button>
</div>

<!-- Interactive Device Preview Modal -->
<div class="fb-preview-modal-overlay" id="previewOverlay" onclick="if(event.target===this)toggleDevicePreview()">
    <div class="fb-preview-container">
        <div class="fb-preview-top">
            <h3><i class="ph-bold ph-device-mobile"></i> Vista Previa en Móvil</h3>
            <button type="button" class="fb-card-icon-btn" onclick="toggleDevicePreview()"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="fb-preview-body">
            <div class="fb-phone-frame" id="phonePreview"></div>
        </div>
    </div>
</div>

<script>
const FORM_ID = '<?php echo $id; ?>';
let fields = <?php echo $formData ? ($formData['fields_json'] ?: '[]') : '[]'; ?>;
let activeIdx = null, dragSrcIdx = null;

const TYPE_MAP = {
    text: 'Respuesta corta',
    textarea: 'Párrafo',
    email: 'Email',
    phone: 'Teléfono',
    date: 'Fecha',
    select: 'Varias opciones',
    checkbox: 'Casillas',
    dropdown: 'Desplegable',
    file: 'Subir archivos',
    range: 'Escala lineal',
    number_range: 'Rango numérico',
    color: 'Color',
    icon_card: 'Cards con icono',
    divider: 'Sección'
};

const ICON_LIST = [
    'ph-star','ph-heart','ph-lightning','ph-rocket','ph-globe','ph-paint-brush','ph-megaphone',
    'ph-camera','ph-video-camera','ph-music-note','ph-code','ph-chat-circle','ph-envelope',
    'ph-phone','ph-map-pin','ph-clock','ph-calendar','ph-chart-line','ph-shopping-cart',
    'ph-truck','ph-users','ph-user','ph-gear','ph-shield-check','ph-trophy','ph-flag','ph-sparkle'
];

function uid() { return 'f_' + Math.random().toString(36).substr(2, 9); }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function switchTab(tab) {
    const isEditor = (tab === 'editor');
    document.getElementById('viewEditor').style.display = isEditor ? 'block' : 'none';
    document.getElementById('viewSettings').style.display = isEditor ? 'none' : 'flex';
    document.getElementById('tabBtnEditor').classList.toggle('active', isEditor);
    document.getElementById('tabBtnSettings').classList.toggle('active', !isEditor);
    document.getElementById('gfToolbar').style.display = isEditor ? 'flex' : 'none';
}

function addField(type) {
    const defs = {
        text: { l: 'Pregunta sin título', p: 'Texto de respuesta corta' },
        textarea: { l: 'Pregunta sin título', p: 'Texto de respuesta larga' },
        email: { l: 'Correo electrónico', p: 'tu@email.com' },
        phone: { l: 'Teléfono', p: '+51 999 999 999' },
        date: { l: 'Fecha', p: '' },
        select: { l: 'Pregunta sin título', p: '', o: ['Opción 1'] },
        checkbox: { l: 'Pregunta sin título', p: '', o: ['Opción 1'] },
        dropdown: { l: 'Pregunta sin título', p: '', o: ['Opción 1'] },
        file: { l: 'Subir archivo', p: '' },
        range: { l: 'Escala de satisfacción', p: '' },
        number_range: { l: 'Rango de edad', p: '' },
        color: { l: 'Elige tus colores preferidos', p: '' },
        icon_card: { l: 'Elige una opción', p: '' },
        divider: { l: 'Sección sin título', p: '' }
    };
    const d = defs[type];
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
    
    fields.push(field);
    activeIdx = fields.length - 1;
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

function renderFields() {
    const list = document.getElementById('fieldsList');
    list.innerHTML = '';
    let numSections = 1;
    fields.forEach(f => { if (f.type === 'divider') numSections++; });
    let currentSection = 1;

    fields.forEach((f, i) => {
        const card = document.createElement('div');
        card.className = 'fb-question-card' + (i === activeIdx ? ' active' : '') + (f.type === 'divider' ? ' fb-section-card' : '');
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

        card.addEventListener('dragstart', e => { dragSrcIdx = i; card.style.opacity = '0.4'; e.dataTransfer.effectAllowed = 'move'; });
        card.addEventListener('dragend', () => { card.style.opacity = '1'; dragSrcIdx = null; });
        card.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
        card.addEventListener('drop', e => {
            e.preventDefault();
            if (dragSrcIdx === null || dragSrcIdx === i) return;
            const m = fields.splice(dragSrcIdx, 1)[0];
            fields.splice(i, 0, m);
            activeIdx = i;
            renderFields();
        });

        let bodyHtml = '';
        let badgeHtml = '';

        if (f.type === 'divider') {
            currentSection++;
            badgeHtml = `<div class="fb-section-badge">Sección ${currentSection} de ${numSections}</div>`;
            card.style.marginTop = '28px';
            bodyHtml = `
                <input class="fb-section-title-input" value="${esc(f.label)}" placeholder="Sección sin título" onfocus="setActive(${i})" oninput="fields[${i}].label=this.value" autocomplete="off">
                <input class="fb-section-desc-input" value="${esc(f.description||'')}" placeholder="Descripción de la sección (opcional)" oninput="fields[${i}].description=this.value" autocomplete="off">
            `;
        } else if (i === activeIdx) {
            // Active Editing Mode
            let typeOpts = '';
            ['text','textarea','select','checkbox','dropdown','email','phone','date','file','range','number_range','color','icon_card'].forEach(t => {
                typeOpts += `<option value="${t}" ${f.type===t?'selected':''}>${TYPE_MAP[t]}</option>`;
            });
            bodyHtml = `
                <div class="fb-q-header-row">
                    <input class="fb-q-label-input" value="${esc(f.label)}" placeholder="Escribe tu pregunta..." oninput="fields[${i}].label=this.value" autocomplete="off">
                    <select class="fb-type-select-styled" onchange="fields[${i}].type=this.value;if(['select','checkbox','dropdown'].includes(this.value)&&!fields[${i}].options)fields[${i}].options=['Opción 1'];if(this.value==='color'&&!fields[${i}].color_options)fields[${i}].color_options=['#4f46e5'];if(this.value==='icon_card'&&!fields[${i}].icon_options)fields[${i}].icon_options=[{icon:'ph-star',text:'Opción'}];renderFields()">
                        ${typeOpts}
                    </select>
                </div>
            `;
            bodyHtml += renderFieldContent(f, i);
        } else {
            // Collapsed Preview Mode
            bodyHtml = `
                <div class="fb-collapsed-only">
                    <div style="font-size: 0.92rem; font-weight: 600; color: var(--color-title); margin-bottom: 0.35rem;">
                        ${esc(f.label)}${f.required ? '<span style="color:#ef4444; margin-left: 3px;">*</span>' : ''}
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted);">${renderCollapsedPreview(f)}</div>
                </div>
            `;
        }

        card.innerHTML = `${badgeHtml}<div class="fb-card-drag-bar"><i class="ph-bold ph-dots-six-vertical"></i></div><div class="fb-card-content">${bodyHtml}</div>`;

        // Footer for active cards
        if (i === activeIdx) {
            const footerHtml = (f.type !== 'divider') ? `
            <div class="fb-card-footer fb-active-only">
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
            <div class="fb-card-footer fb-active-only">
                <button type="button" class="fb-card-icon-btn" onclick="event.stopPropagation();dupField(${i})" title="Duplicar"><i class="ph-bold ph-copy"></i></button>
                <button type="button" class="fb-card-icon-btn btn-delete" onclick="event.stopPropagation();delField(${i})" title="Eliminar"><i class="ph-bold ph-trash"></i></button>
            </div>`;
            card.innerHTML += footerHtml;
        }
        list.appendChild(card);
    });
}

function renderFieldContent(f, i) {
    if (f.type === 'text' || f.type === 'email' || f.type === 'phone') {
        return `<div style="border-bottom: 1px solid var(--border-color); padding: 0.4rem 0; font-size: 0.85rem; color: var(--text-muted); max-width: 60%;">${f.type==='text' ? 'Texto de respuesta corta' : (f.type==='email' ? 'correo@ejemplo.com' : '+51 999 999 999')}</div>`;
    }
    if (f.type === 'textarea') {
        return `<div style="border-bottom: 1px solid var(--border-color); padding: 0.4rem 0; font-size: 0.85rem; color: var(--text-muted); max-width: 80%;">Texto de respuesta larga (párrafo)</div>`;
    }
    if (f.type === 'date') {
        return `<div style="display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border-color); padding: 0.4rem 0; font-size: 0.85rem; color: var(--text-muted); max-width: 50%;"><i class="ph-bold ph-calendar-blank"></i> Día / Mes / Año</div>`;
    }
    if (f.type === 'file') {
        if (typeof f.file_max_count === 'undefined') f.file_max_count = 1;
        if (typeof f.file_max_size === 'undefined') f.file_max_size = 10;
        const types = f.file_types || [];
        let html = `<div class="fb-active-only" style="display:flex; flex-direction:column; gap:10px; margin-bottom:1rem; padding:12px; background:var(--bg-color); border-radius:12px; border:1px solid var(--border-color)">`;
        html += `<div>
            <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.78rem; font-weight:600; color:var(--color-title); cursor:pointer; margin-bottom:8px">
                <input type="checkbox" ${f.file_restrict?'checked':''} onchange="event.stopPropagation();fields[${i}].file_restrict=this.checked;renderFields()"> Permitir solo tipos de archivo específicos
            </label>
            <div style="display:${f.file_restrict?'flex':'none'}; flex-wrap:wrap; gap:8px; padding:8px; background:var(--bg-surface); border-radius:8px; border:1px solid var(--border-color)">
                ${['Documento','PDF','Imagen','Video','Audio'].map(t=>`
                    <label style="font-size:0.75rem; display:flex; align-items:center; gap:4px; cursor:pointer; color:var(--color-title)"><input type="checkbox" ${types.includes(t)?'checked':''} onchange="event.stopPropagation();if(this.checked){fields[${i}].file_types=(fields[${i}].file_types||[]);fields[${i}].file_types.push('${t}');}else{fields[${i}].file_types=fields[${i}].file_types.filter(x=>x!=='${t}');}renderFields()"> ${t}</label>
                `).join('')}
            </div>
        </div>`;
        html += `<div style="display:flex; gap:1rem; flex-wrap:wrap">
            <div style="flex:1; min-width:140px">
                <label style="display:block; font-size:0.72rem; font-weight:600; color:var(--text-muted); margin-bottom:4px">Cantidad máxima</label>
                <select style="width:100%; padding:6px 8px; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-surface); color:var(--color-title)" onchange="event.stopPropagation();fields[${i}].file_max_count=parseInt(this.value);renderFields()">
                    ${[1,5,10].map(v=>`<option value="${v}" ${f.file_max_count===v?'selected':''}>${v} archivo(s)</option>`).join('')}
                </select>
            </div>
            <div style="flex:1; min-width:140px">
                <label style="display:block; font-size:0.72rem; font-weight:600; color:var(--text-muted); margin-bottom:4px">Tamaño máximo</label>
                <select style="width:100%; padding:6px 8px; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-surface); color:var(--color-title)" onchange="event.stopPropagation();fields[${i}].file_max_size=parseInt(this.value);renderFields()">
                    ${[1,10,25,50,100].map(v=>`<option value="${v}" ${f.file_max_size===v?'selected':''}>${v} MB</option>`).join('')}
                </select>
            </div>
        </div></div>`;
        html += `<div style="border:2px dashed var(--border-color); border-radius:12px; padding:1.5rem; text-align:center; color:var(--text-muted); font-size:0.85rem;"><i class="ph-bold ph-cloud-arrow-up" style="font-size:1.8rem; color:var(--primary-color); display:block; margin-bottom:0.4rem;"></i>Zona de subida de archivos</div>`;
        return html;
    }
    if (f.type === 'select' || f.type === 'checkbox' || f.type === 'dropdown') {
        if (typeof f.is_multi === 'undefined') f.is_multi = (f.type === 'checkbox');
        const isCheck = f.is_multi;
        const isDrop = f.type === 'dropdown';
        let html = `<div class="fb-active-only" style="margin-bottom:0.6rem;"><label style="display:flex; align-items:center; gap:0.4rem; font-size:0.78rem; font-weight:500; color:var(--text-muted); cursor:pointer"><input type="checkbox" ${f.is_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].is_multi=this.checked;renderFields()"> Permitir selección múltiple</label></div>`;
        html += '<div class="fb-options-list">';
        (f.options || []).forEach((o, oi) => {
            html += `<div class="fb-opt-item">
                <div class="${isDrop?'':'fb-opt-indicator'} ${isCheck?'checkbox-style':''}" style="${isDrop?'font-size:0.85rem; color:var(--text-muted); width:20px; text-align:center; font-weight:600':''}">${isDrop?(oi+1)+'.':''}</div>
                <input class="fb-opt-input" value="${esc(o)}" oninput="fields[${i}].options[${oi}]=this.value" onclick="event.stopPropagation()" autocomplete="off">
                <button type="button" class="fb-opt-delete-btn" onclick="event.stopPropagation();fields[${i}].options.splice(${oi},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `</div><div class="fb-add-opt-row"><div class="${isDrop?'':'fb-opt-indicator'} ${isCheck?'checkbox-style':''}"></div> <button type="button" class="fb-btn-link" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Opción '+((fields[${i}].options||[]).length+1));renderFields()">+ Añadir opción</button> ${isDrop?'':`o <button type="button" class="fb-btn-link" style="color:var(--text-muted)" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Otro');renderFields()">añadir "Otro"</button>`}</div>`;
        return html;
    }
    if (f.type === 'range') {
        const mn = f.range_min || 1, mx = Math.min(f.range_max || 5, 10), lMin = f.range_label_min || '', lMax = f.range_label_max || '';
        let dots = '';
        for (let n = mn; n <= mx; n++) dots += `<div style="width:30px; height:30px; border:2px solid var(--border-color); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:var(--color-title); font-weight:600;">${n}</div>`;
        let html = `<div class="fb-active-only" style="display:flex; gap:0.5rem; margin-bottom:0.75rem; flex-wrap:wrap">
            <div style="flex:1; min-width:60px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Mín</label><input type="number" value="${mn}" min="0" max="10" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" onchange="fields[${i}].range_min=Math.max(0,Math.min(10,parseInt(this.value)||0));renderFields()"></div>
            <div style="flex:1; min-width:60px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Máx</label><input type="number" value="${mx}" min="1" max="10" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" onchange="fields[${i}].range_max=Math.max(1,Math.min(10,parseInt(this.value)||5));renderFields()"></div>
            <div style="flex:2; min-width:100px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Etiqueta mín</label><input value="${esc(lMin)}" placeholder="ej: Malo" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" oninput="fields[${i}].range_label_min=this.value"></div>
            <div style="flex:2; min-width:100px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Etiqueta máx</label><input value="${esc(lMax)}" placeholder="ej: Excelente" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" oninput="fields[${i}].range_label_max=this.value"></div>
        </div>`;
        html += `<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap">${lMin?`<span style="font-size:0.78rem; color:var(--text-muted)">${esc(lMin)}</span>`:''}<div style="display:flex; gap:6px; flex-wrap:wrap">${dots}</div>${lMax?`<span style="font-size:0.78rem; color:var(--text-muted)">${esc(lMax)}</span>`:''}</div>`;
        return html;
    }
    if (f.type === 'number_range') {
        const nrMin = f.nr_min ?? 18, nrMax = f.nr_max ?? 65, nrStep = f.nr_step ?? 1;
        let html = `<div class="fb-active-only" style="display:flex; gap:0.5rem; margin-bottom:0.75rem; flex-wrap:wrap">
            <div style="flex:1; min-width:70px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Desde</label><input type="number" value="${nrMin}" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" onchange="fields[${i}].nr_min=parseInt(this.value)"></div>
            <div style="flex:1; min-width:70px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Hasta</label><input type="number" value="${nrMax}" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" onchange="fields[${i}].nr_max=parseInt(this.value)"></div>
            <div style="flex:1; min-width:70px"><label style="font-size:0.7rem; font-weight:600; color:var(--text-muted)">Paso</label><input type="number" value="${nrStep}" min="1" style="width:100%; padding:0.4rem; border:1px solid var(--border-color); border-radius:8px; font-size:0.8rem; background:var(--bg-color); color:var(--color-title)" onchange="fields[${i}].nr_step=parseInt(this.value)||1"></div>
        </div>`;
        html += `<div style="display:flex; align-items:center; gap:8px"><span style="font-size:0.8rem; color:var(--text-muted); font-weight:600">${nrMin}</span><div style="flex:1; height:6px; background:var(--border-color); border-radius:3px; position:relative"><div style="position:absolute; left:0; top:0; height:100%; width:45%; background:var(--primary-color); border-radius:3px"></div></div><span style="font-size:0.8rem; color:var(--text-muted); font-weight:600">${nrMax}</span></div>`;
        return html;
    }
    if (f.type === 'color') {
        const colors = f.color_options || ['#4f46e5'];
        let html = `<div class="fb-active-only" style="margin-bottom:0.5rem;"><span style="font-size:0.78rem; color:var(--text-muted);">Muestras de color seleccionables para el cliente:</span></div>`;
        html += '<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center">';
        colors.forEach((c, ci) => {
            html += `<div style="position:relative; display:flex; flex-direction:column; align-items:center; gap:3px">
                <input type="color" value="${c}" style="width:42px; height:42px; border:2px solid var(--border-color); border-radius:12px; cursor:pointer; padding:2px; background:var(--bg-surface);" onchange="event.stopPropagation();fields[${i}].color_options[${ci}]=this.value;renderFields()">
                <span style="font-size:0.65rem; color:var(--text-muted); font-family:monospace">${c}</span>
                <button type="button" style="position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:#ef4444; color:white; border:none; cursor:pointer; font-size:0.7rem; display:flex; align-items:center; justify-content:center;" onclick="event.stopPropagation();fields[${i}].color_options.splice(${ci},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `<button type="button" style="width:42px; height:42px; border:2px dashed var(--border-color); border-radius:12px; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--primary-color)" onclick="event.stopPropagation();if(!fields[${i}].color_options)fields[${i}].color_options=['#4f46e5'];fields[${i}].color_options.push('#'+Math.floor(Math.random()*16777215).toString(16).padStart(6,'0'));renderFields()" title="Añadir color"><i class="ph-bold ph-plus"></i></button></div>`;
        return html;
    }
    if (f.type === 'icon_card') {
        const opts = f.icon_options || [];
        let html = `<div class="fb-active-only" style="margin-bottom:0.6rem;"><label style="display:flex; align-items:center; gap:0.4rem; font-size:0.78rem; font-weight:500; color:var(--text-muted); cursor:pointer"><input type="checkbox" ${f.icon_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].icon_multi=this.checked;renderFields()"> Permitir selección múltiple</label></div>`;
        html += '<div style="display:flex; flex-direction:column; gap:8px">';
        opts.forEach((o, oi) => {
            let iconPicker = `<select style="width:46px; padding:4px; border:1px solid var(--border-color); border-radius:8px; font-size:1rem; background:var(--bg-color); color:var(--color-title); cursor:pointer; text-align:center" onchange="event.stopPropagation();fields[${i}].icon_options[${oi}].icon=this.value;renderFields()">`;
            ICON_LIST.forEach(ic => { iconPicker += `<option value="${ic}" ${o.icon===ic?'selected':''}>${ic.replace('ph-','')}</option>`; });
            iconPicker += '</select>';
            html += `<div style="display:flex; align-items:center; gap:8px; border:1.5px solid var(--border-color); border-radius:12px; padding:8px 12px; background:var(--bg-color)">
                <div style="width:36px; height:36px; background:color-mix(in srgb,var(--primary-color) 12%,transparent); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--primary-color); flex-shrink:0"><i class="ph-bold ${o.icon}"></i></div>
                <input value="${esc(o.text)}" style="flex:1; border:none; outline:none; font-size:0.85rem; background:transparent; color:var(--color-title); font-weight:500;" oninput="fields[${i}].icon_options[${oi}].text=this.value" onclick="event.stopPropagation()" autocomplete="off">
                ${iconPicker}
                <button type="button" class="fb-opt-delete-btn" onclick="event.stopPropagation();fields[${i}].icon_options.splice(${oi},1);renderFields()"><i class="ph-bold ph-x"></i></button>
            </div>`;
        });
        html += `</div><div style="margin-top:8px"><button type="button" class="fb-btn-link" onclick="event.stopPropagation();if(!fields[${i}].icon_options)fields[${i}].icon_options=[{icon:'ph-star',text:'Opción 1'}];fields[${i}].icon_options.push({icon:ICON_LIST[Math.floor(Math.random()*ICON_LIST.length)],text:'Opción '+(fields[${i}].icon_options.length+1)});renderFields()">+ Añadir Card</button></div>`;
        return html;
    }
    return '';
}

function renderCollapsedPreview(f) {
    if (f.type === 'text' || f.type === 'email' || f.type === 'phone') return `<input disabled placeholder="${esc(f.placeholder||'Texto de respuesta corta')}" style="width:100%; max-width:280px; border:none; border-bottom:1px solid var(--border-color); background:transparent; padding:0.2rem 0; font-size:0.8rem; color:var(--text-muted)">`;
    if (f.type === 'textarea') return `<input disabled placeholder="Texto de respuesta larga" style="width:100%; max-width:380px; border:none; border-bottom:1px solid var(--border-color); background:transparent; padding:0.2rem 0; font-size:0.8rem; color:var(--text-muted)">`;
    if (f.type === 'date') return `<span style="font-size:0.8rem; color:var(--text-muted);"><i class="ph ph-calendar-blank"></i> Día / Mes / Año</span>`;
    if (f.type === 'file') return '<span style="font-size:0.8rem; color:var(--text-muted);"><i class="ph ph-cloud-arrow-up"></i> Zona de subida de archivos</span>';
    if (f.type === 'select') return (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.4rem; margin:0.2rem 0;"><span style="width:12px; height:12px; border:2px solid var(--border-color); border-radius:50%; display:inline-block;"></span><span style="font-size:0.82rem;">${esc(o)}</span></div>`).join('');
    if (f.type === 'checkbox') return (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.4rem; margin:0.2rem 0;"><span style="width:12px; height:12px; border:2px solid var(--border-color); border-radius:3px; display:inline-block;"></span><span style="font-size:0.82rem;">${esc(o)}</span></div>`).join('');
    if (f.type === 'dropdown') return `<div style="padding:4px 8px; border:1px solid var(--border-color); border-radius:6px; display:inline-flex; align-items:center; gap:12px; font-size:0.8rem; color:var(--text-muted);"><span>1. ${esc(f.options?.[0]||'Opciones')}</span><i class="ph ph-caret-down"></i></div>`;
    if (f.type === 'range') return `<span style="font-size:0.8rem; color:var(--text-muted);">Escala de ${f.range_min||1} a ${f.range_max||5}</span>`;
    if (f.type === 'number_range') return `<span style="font-size:0.8rem; color:var(--text-muted);">Rango numérico: ${f.nr_min??18} - ${f.nr_max??65}</span>`;
    if (f.type === 'color') return `<div style="display:flex; gap:4px;">${(f.color_options||['#4f46e5']).map(c=>`<div style="width:16px; height:16px; border-radius:50%; background:${c}; border:1px solid var(--border-color);"></div>`).join('')}</div>`;
    if (f.type === 'icon_card') return `<span style="font-size:0.8rem; color:var(--text-muted);">${(f.icon_options||[]).length} cards interactivas configuradas</span>`;
    return '';
}

function dupField(idx) {
    const clone = JSON.parse(JSON.stringify(fields[idx]));
    clone.id = uid();
    fields.splice(idx + 1, 0, clone);
    activeIdx = idx + 1;
    renderFields();
}

function delField(idx) {
    fields.splice(idx, 1);
    if (activeIdx >= fields.length) activeIdx = fields.length - 1;
    if (activeIdx < 0) activeIdx = null;
    renderFields();
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
        multi_step: document.getElementById('settMultiStep').checked
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
                setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 2000);
            }
        } else {
            alert(data.error || 'Error al guardar el formulario.');
            btn.innerHTML = orig;
            btn.disabled = false;
        }
    } catch (e) {
        alert('Error de conexión al servidor.');
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}

// Deselect active card on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('.fb-question-card') && !e.target.closest('.fb-palette-dock') && !e.target.closest('.fb-app-topbar') && !e.target.closest('#viewSettings')) {
        activeIdx = null;
        renderFields();
    }
});

// Auto-expand textarea
document.getElementById('formDesc').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

renderFields();

// Interactive Device Preview
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
    const phone = document.getElementById('phonePreview');
    if (!phone) return;
    const title = document.getElementById('formTitle').value || 'Formulario sin título';
    const desc = document.getElementById('formDesc').value || '';
    const showLogo = document.getElementById('settShowLogo').checked;
    const reqName = document.getElementById('settRequireName').checked;
    const reqEmail = document.getElementById('settRequireEmail').checked;

    let logo = showLogo && LOGO_URL ? `<img src="${LOGO_URL}" style="max-height: 28px; margin-bottom: 0.75rem; filter: brightness(0) invert(1);">` : '';
    let h = '';

    if (reqName) {
        h += `<div style="margin-bottom: 1rem;"><label style="font-size: 0.8rem; font-weight: 600; color: var(--color-title); display: block; margin-bottom: 4px;">Tu Nombre <span style="color:#ef4444">*</span></label><input style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); font-size:0.85rem;" placeholder="Nombre completo" disabled></div>`;
    }
    if (reqEmail) {
        h += `<div style="margin-bottom: 1rem;"><label style="font-size: 0.8rem; font-weight: 600; color: var(--color-title); display: block; margin-bottom: 4px;">Tu Correo Electrónico <span style="color:#ef4444">*</span></label><input style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); font-size:0.85rem;" placeholder="correo@ejemplo.com" disabled></div>`;
    }

    fields.forEach(f => {
        if (f.type === 'divider') {
            h += `<hr style="border:none; border-top:1px solid var(--border-color); margin:1.25rem 0 0.75rem;"><h4 style="margin:0 0 0.5rem; font-size:0.95rem; font-weight:700; color:var(--color-title);">${esc(f.label)}</h4>`;
            return;
        }
        const req = f.required ? '<span style="color:#ef4444">*</span>' : '';
        let inp = '';
        if (['text','email','phone','date'].includes(f.type)) {
            inp = `<input style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); font-size:0.85rem;" placeholder="${esc(f.placeholder)}" disabled>`;
        } else if (f.type === 'textarea') {
            inp = `<textarea style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); font-size:0.85rem; height:60px;" placeholder="${esc(f.placeholder)}" disabled></textarea>`;
        } else if (f.type === 'select') {
            inp = (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--color-title); margin:0.3rem 0;"><input type="radio" disabled> ${esc(o)}</div>`).join('');
        } else if (f.type === 'checkbox') {
            inp = (f.options||[]).map(o => `<div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--color-title); margin:0.3rem 0;"><input type="checkbox" disabled> ${esc(o)}</div>`).join('');
        } else if (f.type === 'dropdown') {
            inp = `<select style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); font-size:0.85rem;" disabled><option>Selecciona una opción</option>${(f.options||[]).map(o=>`<option>${esc(o)}</option>`).join('')}</select>`;
        } else if (f.type === 'file') {
            inp = `<div style="border:2px dashed var(--border-color); border-radius:10px; padding:1.2rem; text-align:center; color:var(--text-muted); font-size:0.8rem;"><i class="ph-bold ph-cloud-arrow-up" style="font-size:1.5rem; display:block; margin-bottom:0.3rem;"></i>Subir archivos</div>`;
        } else if (f.type === 'range') {
            const mn = f.range_min||1, mx = Math.min(f.range_max||5, 10);
            let dots = '';
            for (let n = mn; n <= mx; n++) dots += `<div style="width:24px; height:24px; border:2px solid var(--border-color); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; color:var(--color-title); font-weight:600;">${n}</div>`;
            inp = `<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap">${f.range_label_min?`<span style="font-size:0.75rem; color:var(--text-muted)">${esc(f.range_label_min)}</span>`:''}<div style="display:flex; gap:4px;">${dots}</div>${f.range_label_max?`<span style="font-size:0.75rem; color:var(--text-muted)">${esc(f.range_label_max)}</span>`:''}</div>`;
        } else if (f.type === 'number_range') {
            inp = `<div style="display:flex; gap:8px; align-items:center;"><span style="font-size:0.8rem; font-weight:600;">${f.nr_min??18}</span><div style="flex:1; height:4px; background:var(--border-color); border-radius:2px;"></div><span style="font-size:0.8rem; font-weight:600;">${f.nr_max??65}</span></div>`;
        } else if (f.type === 'color') {
            const colors = f.color_options || ['#4f46e5'];
            inp = `<div style="display:flex; gap:6px; flex-wrap:wrap;">${colors.map(c=>`<div style="width:28px; height:28px; border-radius:50%; background:${c}; border:2px solid var(--border-color);"></div>`).join('')}</div>`;
        } else if (f.type === 'icon_card') {
            inp = (f.icon_options||[]).map(o => `<div style="display:flex; align-items:center; gap:8px; border:1px solid var(--border-color); border-radius:10px; padding:8px 12px; margin:4px 0;"><i class="ph-bold ${o.icon}" style="color:var(--primary-color);"></i><span style="font-size:0.85rem; font-weight:500;">${esc(o.text)}</span></div>`).join('');
        }

        h += `<div style="margin-bottom: 1rem;"><label style="font-size: 0.82rem; font-weight: 600; color: var(--color-title); display: block; margin-bottom: 4px;">${esc(f.label)} ${req}</label>${inp}</div>`;
    });

    phone.innerHTML = `
        <div class="fb-phone-header">
            ${logo}
            <h2>${esc(title)}</h2>
            ${desc ? `<p>${esc(desc)}</p>` : ''}
        </div>
        <div class="fb-phone-content">
            ${h}
            <button type="button" style="width:100%; padding:0.75rem; background:var(--primary-color); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:0.85rem; margin-top:1rem; cursor:not-allowed;" disabled>
                Enviar Formulario
            </button>
        </div>
    `;
}

// Position toolbar floating next to the canvas
function positionToolbar() {
    const wrap = document.querySelector('.fb-builder-wrapper');
    const tb = document.getElementById('gfToolbar');
    if (!wrap || !tb) return;
    const rect = wrap.getBoundingClientRect();
    const leftPos = rect.right + 18;
    if (leftPos + 60 > window.innerWidth) {
        tb.style.cssText = 'position:fixed; bottom:0; left:0; right:0; top:auto; transform:none; flex-direction:row; justify-content:center; border-radius:0; padding:6px 12px; border-left:none; border-right:none; border-bottom:none; z-index:35; display:flex;';
        return;
    }
    tb.style.cssText = '';
    tb.style.left = leftPos + 'px';
}

positionToolbar();
window.addEventListener('resize', positionToolbar);
setTimeout(positionToolbar, 100);
</script>

<?php require_once 'includes/footer.php'; ?>

