<?php
// modules/brand_guidelines/edit.php
require_once 'includes/header.php';
require_once 'modules/brand_guidelines/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$guideline = null;

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$id]);
    $guideline = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$guideline) {
        echo "<script>window.location.href = 'index.php?module=brand_guidelines&action=index';</script>";
        exit();
    }
}

// Fetch all clients
$stmtClients = $db->query("SELECT id, name FROM clients ORDER BY name ASC");
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

// Decode JSON fields with defaults
$variations = !empty($guideline['logo_variations_json']) ? json_decode($guideline['logo_variations_json'], true) : [];
if (!is_array($variations)) $variations = [];

$icons = !empty($guideline['icons_json']) ? json_decode($guideline['icons_json'], true) : [];
if (!is_array($icons)) $icons = [];

$colors = !empty($guideline['colors_json']) ? json_decode($guideline['colors_json'], true) : [
    ['name' => 'Color Principal', 'role' => 'Primario', 'hex' => '#4F46E5', 'rgb' => '79, 70, 229', 'cmyk' => '66, 69, 0, 10', 'pantone' => ''],
    ['name' => 'Acento', 'role' => 'Acento', 'hex' => '#EC4899', 'rgb' => '236, 72, 153', 'cmyk' => '0, 69, 35, 7', 'pantone' => ''],
    ['name' => 'Oscuro / Texto', 'role' => 'Texto', 'hex' => '#0F172A', 'rgb' => '15, 23, 42', 'cmyk' => '64, 45, 0, 84', 'pantone' => ''],
    ['name' => 'Fondo Claro', 'role' => 'Fondo', 'hex' => '#F8FAFC', 'rgb' => '248, 250, 252', 'cmyk' => '2, 1, 0, 1', 'pantone' => '']
];
if (!is_array($colors) || empty($colors)) {
    $colors = [
        ['name' => 'Color Principal', 'role' => 'Primario', 'hex' => '#4F46E5', 'rgb' => '79, 70, 229', 'cmyk' => '66, 69, 0, 10', 'pantone' => ''],
        ['name' => 'Acento', 'role' => 'Acento', 'hex' => '#EC4899', 'rgb' => '236, 72, 153', 'cmyk' => '0, 69, 35, 7', 'pantone' => '']
    ];
}

$fonts = !empty($guideline['fonts_json']) ? json_decode($guideline['fonts_json'], true) : [
    ['role' => 'Titular / Headings', 'name' => 'Inter', 'weights' => '700, 800', 'usage' => 'Títulos principales, encabezados y destacados.'],
    ['role' => 'Cuerpo / Body', 'name' => 'Inter', 'weights' => '400, 500', 'usage' => 'Párrafos de texto corrido, tablas y descripciones.']
];
if (!is_array($fonts)) $fonts = [];

$incorrectUses = !empty($guideline['incorrect_uses_json']) ? json_decode($guideline['incorrect_uses_json'], true) : [
    ['title' => 'No distorsionar ni estirar', 'desc' => 'Mantén siempre la proporción original del isotipo y logotipo sin deformarlo en ningún eje.'],
    ['title' => 'No alterar la paleta cromática', 'desc' => 'Usa únicamente los colores oficiales aprobados en esta guía corporativa.'],
    ['title' => 'No agregar sombras o degradados no autorizados', 'desc' => 'Evita biseles, sombras paralelas duras o efectos 3D ajenos al manual.'],
    ['title' => 'No rotar el logotipo', 'desc' => 'El logo siempre debe colocarse en posición horizontal estándar.']
];
if (!is_array($incorrectUses)) $incorrectUses = [];

$applications = !empty($guideline['applications_json']) ? json_decode($guideline['applications_json'], true) : [];
if (!is_array($applications)) $applications = [];

$values = !empty($guideline['values_json']) ? json_decode($guideline['values_json'], true) : [];
if (!is_array($values)) $values = [];

$baseUrl = bg_get_base_url();
$isEdit = $id > 0;

// System colors
$sysPrimary = !empty($global_settings['primary_color']) ? $global_settings['primary_color'] : '#262ecf';
$sysSecondary = !empty($global_settings['secondary_color']) ? $global_settings['secondary_color'] : '#081116';
?>

<style>
/* ==========================================================================
   BRAND GUIDELINES CREATIVE STUDIO APP DESIGN SYSTEM
   ========================================================================== */
:root {
    --bge-primary: <?php echo $sysPrimary; ?>;
    --bge-primary-hover: color-mix(in srgb, <?php echo $sysPrimary; ?> 85%, black);
    --bge-secondary: <?php echo $sysSecondary; ?>;
}

.bge-container {
    padding: 1.5rem 2rem 5rem;
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', -apple-system, BlinkMacSystemFont, sans-serif);
    animation: bgeFade 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes bgeFade {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* App Studio Workspace Header */
.bge-header {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 22px;
    padding: 1.25rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .bge-header {
    background: rgba(18, 22, 34, 0.88);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
}

.bge-header-left {
    display: flex;
    align-items: center;
    gap: 1.15rem;
}

.bge-back-btn {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-main, #0f172a);
    font-size: 1.25rem;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .bge-back-btn {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.bge-back-btn:hover {
    background: var(--bge-primary, #262ecf);
    color: #ffffff !important;
    border-color: var(--bge-primary, #262ecf);
    transform: translateX(-3px);
    box-shadow: 0 4px 14px -2px color-mix(in srgb, var(--bge-primary, #262ecf) 45%, transparent);
}

.bge-title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.6px;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bge-title {
    color: #ffffff;
}

.bge-studio-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 10%, transparent);
    color: var(--bge-primary, #262ecf);
    border: 1px solid color-mix(in srgb, var(--bge-primary, #262ecf) 25%, transparent);
}

[data-theme="dark"] .bge-studio-status-pill {
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 20%, transparent);
    color: #818cf8;
    border-color: rgba(129, 140, 248, 0.3);
}

.bge-status-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
    animation: bgePulseDot 2s infinite ease-in-out;
}

@keyframes bgePulseDot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.25); opacity: 0.6; }
}

.bge-subtitle {
    margin: 0.2rem 0 0 0;
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
}

.bge-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.bge-btn-preview {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--bg-surface, #ffffff);
    border: 1.5px solid var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a) !important;
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.65rem 1.15rem;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .bge-btn-preview {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff !important;
}

.bge-btn-preview:hover {
    border-color: var(--bge-primary, #262ecf);
    color: var(--bge-primary, #262ecf) !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px -4px rgba(0, 0, 0, 0.1);
}

.bge-aspect-pill {
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 14%, transparent);
    color: var(--bge-primary, #262ecf);
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
}

.bge-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    background: var(--color-btn-bg, var(--bge-primary, #262ecf));
    color: var(--color-btn-text, #ffffff) !important;
    font-weight: 700;
    font-size: 0.92rem;
    padding: 0.72rem 1.45rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px -2px color-mix(in srgb, var(--bge-primary, #262ecf) 45%, transparent);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.bge-btn-save:hover {
    transform: translateY(-2px);
    background: var(--color-btn-hover, var(--bge-primary-hover, #1f25a6));
    box-shadow: 0 8px 24px -3px color-mix(in srgb, var(--bge-primary, #262ecf) 65%, transparent);
}

.bge-kbd-shortcut {
    display: inline-block;
    padding: 2px 6px;
    font-size: 0.72rem;
    font-family: inherit;
    font-weight: 700;
    line-height: 1;
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 255, 255, 0.22);
    border-radius: 6px;
    margin-left: 0.25rem;
}

/* App Segmented Tabs Dock Bar */
.bge-tabs-dock-wrapper {
    position: relative;
    margin-bottom: 2rem;
}

.bge-tabs-bar {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 18px;
    padding: 0.5rem;
    overflow-x: auto;
    scrollbar-width: none;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .bge-tabs-bar {
    background: rgba(18, 22, 34, 0.88);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.3);
}

.bge-tabs-bar::-webkit-scrollbar {
    display: none;
}

.bge-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 1.15rem;
    border-radius: 12px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text-muted, #64748b);
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    user-select: none;
}

.bge-tab-step-badge {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.06);
    color: var(--text-muted, #64748b);
    transition: all 0.2s;
}

[data-theme="dark"] .bge-tab-step-badge {
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

.bge-tab-btn:hover {
    color: var(--text-main, #0f172a);
    background: rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .bge-tab-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.05);
}

.bge-tab-btn.active {
    background: var(--bge-primary, #262ecf);
    color: #ffffff !important;
    font-weight: 700;
    box-shadow: 0 4px 18px -2px color-mix(in srgb, var(--bge-primary, #262ecf) 50%, transparent);
    border-color: transparent;
}

.bge-tab-btn.active .bge-tab-step-badge {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Form Sections */
.bge-section-panel {
    display: none;
    animation: bgeTabFade 0.25s ease;
}

.bge-section-panel.active {
    display: block;
}

@keyframes bgeTabFade {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.bge-card-panel {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .bge-card-panel {
    background: #141721;
    border-color: rgba(255, 255, 255, 0.08);
}

.bge-panel-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin: 0 0 0.35rem 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

[data-theme="dark"] .bge-panel-title {
    color: #ffffff;
}

.bge-panel-desc {
    font-size: 0.88rem;
    color: var(--text-muted, #64748b);
    margin: 0 0 1.5rem 0;
}

/* Form Inputs Grid */
.bge-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.25rem;
}

.bge-field-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.bge-field-group.full {
    grid-column: 1 / -1;
}

.bge-label {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
}

.bge-input, .bge-select, .bge-textarea {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.92rem;
    color: var(--text-main, #0f172a);
    outline: none;
    transition: all 0.2s;
    font-family: inherit;
}

[data-theme="dark"] .bge-input,
[data-theme="dark"] .bge-select,
[data-theme="dark"] .bge-textarea {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.bge-input:focus, .bge-select:focus, .bge-textarea:focus {
    border-color: var(--bge-primary, #262ecf);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--bge-primary, #262ecf) 18%, transparent);
}

/* Upload Dropzones */
.bge-upload-dropzone {
    border: 2px dashed var(--border-color, #cbd5e1);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    background: var(--bg-body, #f8fafc);
    cursor: pointer;
    transition: all 0.25s;
    position: relative;
    overflow: hidden;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

[data-theme="dark"] .bge-upload-dropzone {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.1);
}

.bge-upload-dropzone:hover {
    border-color: var(--bge-primary, #262ecf);
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 4%, transparent);
}

.bge-dropzone-icon {
    font-size: 2.2rem;
    color: var(--bge-primary, #262ecf);
}

.bge-preview-box {
    max-height: 120px;
    max-width: 100%;
    object-fit: contain;
    margin-bottom: 0.5rem;
}

/* General List Item (Used for Variations, Icons, etc.) */
.bge-color-item {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.85rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .bge-color-item {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}

.bge-color-swatch-picker {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.bge-color-swatch-picker input[type="color"] {
    position: absolute;
    top: -10px;
    left: -10px;
    width: 80px;
    height: 80px;
    opacity: 0;
    cursor: pointer;
}

.bge-btn-add-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--bg-body, #f8fafc);
    border: 1.5px dashed var(--border-color, #cbd5e1);
    color: var(--text-main, #0f172a);
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.75rem 1.25rem;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    justify-content: center;
}

[data-theme="dark"] .bge-btn-add-item {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.bge-btn-add-item:hover {
    border-color: var(--bge-primary, #262ecf);
    color: var(--bge-primary, #262ecf);
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 8%, transparent);
}

/* ==========================================================================
   STUDIO COLOR CARDS GRID & INTERACTIVE SWATCHES (Tab 4)
   ========================================================================== */
#colorsContainer.bge-colors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

#colorsContainer .bge-studio-color-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 0;
    margin-bottom: 0;
    box-shadow: 0 6px 20px -4px rgba(0, 0, 0, 0.04);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

[data-theme="dark"] #colorsContainer .bge-studio-color-card {
    background: #141724;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 20px -4px rgba(0, 0, 0, 0.3);
}

#colorsContainer .bge-studio-color-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px -6px rgba(0, 0, 0, 0.12);
    border-color: color-mix(in srgb, var(--bge-primary, #262ecf) 45%, transparent);
}

/* Card Top: Swatch Canvas */
.bge-card-swatch-top {
    height: 125px;
    width: 100%;
    position: relative;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    transition: background-color 0.2s ease;
    user-select: none;
}

.bge-color-hidden-picker {
    position: absolute;
    top: 0;
    left: 0;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.bge-swatch-overlay-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    z-index: 2;
}

.bge-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.bge-swatch-del-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
}

.bge-swatch-del-btn:hover {
    background: #ef4444;
    color: #ffffff;
    transform: scale(1.1);
}

.bge-swatch-center-info {
    text-align: center;
    z-index: 2;
}

.bge-contrast-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.65rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(6px);
    color: #ffffff;
}

.bge-swatch-overlay-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    z-index: 2;
}

.bge-copy-hex-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-family: monospace;
    font-weight: 800;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
}

.bge-copy-hex-pill:hover {
    background: rgba(0, 0, 0, 0.85);
    transform: translateY(-1px);
}

.bge-swatch-picker-hint {
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* 5 Tonal Tints Strip */
.bge-card-tint-bar {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    height: 24px;
    width: 100%;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

[data-theme="dark"] .bge-card-tint-bar {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.bge-tint-cell {
    height: 100%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.15s;
}

.bge-tint-cell:hover {
    transform: scaleY(1.3);
    z-index: 5;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.bge-tint-pct {
    font-size: 0.6rem;
    font-weight: 800;
    opacity: 0;
    color: #0f172a;
    transition: opacity 0.15s;
    text-shadow: 0 0 2px rgba(255,255,255,0.8);
}

.bge-tint-cell:hover .bge-tint-pct {
    opacity: 1;
}

/* Card Body */
.bge-card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex: 1;
}

.bge-card-title-row {
    display: flex;
    gap: 0.75rem;
}

.bge-field-col {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.bge-micro-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* 2x2 Code Chips Grid */
.bge-code-chips-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

.bge-code-chip {
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.45rem 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    position: relative;
    transition: all 0.15s;
}

[data-theme="dark"] .bge-code-chip {
    background: #182032 !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
}

.bge-code-chip:focus-within {
    border-color: var(--bge-primary, #262ecf);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--bge-primary, #262ecf) 25%, transparent);
}

.bge-code-chip-label {
    font-size: 0.68rem;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    flex-shrink: 0;
    width: 48px;
    letter-spacing: 0.5px;
}

[data-theme="dark"] .bge-code-chip-label {
    color: #cbd5e1 !important;
}

.bge-code-chip-input {
    flex: 1;
    background: transparent;
    border: none;
    font-family: monospace;
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    outline: none;
    min-width: 0;
    padding: 0;
}

[data-theme="dark"] .bge-code-chip-input {
    color: #ffffff !important;
}

.bge-code-chip-copy {
    background: transparent;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 0.9rem;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    border-radius: 4px;
}

[data-theme="dark"] .bge-code-chip-copy {
    color: #94a3b8 !important;
}

.bge-code-chip-copy:hover {
    color: var(--bge-primary, #262ecf) !important;
    transform: scale(1.18);
}

/* Dedicated Add Color Studio Card */
.bge-add-color-studio-tile {
    border: 2px dashed var(--border-color, #cbd5e1);
    border-radius: 20px;
    min-height: 280px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    text-align: center;
    cursor: pointer;
    background: var(--bg-body, #f8fafc);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    gap: 0.85rem;
}

[data-theme="dark"] .bge-add-color-studio-tile {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.12);
}

.bge-add-color-studio-tile:hover {
    border-color: var(--bge-primary, #262ecf);
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 6%, transparent);
    transform: translateY(-4px);
    box-shadow: 0 12px 30px -4px color-mix(in srgb, var(--bge-primary, #262ecf) 20%, transparent);
}

.bge-add-tile-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 12%, transparent);
    color: var(--bge-primary, #262ecf);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    transition: all 0.25s ease;
}

.bge-add-color-studio-tile:hover .bge-add-tile-icon {
    background: var(--bge-primary, #262ecf);
    color: #ffffff;
    transform: rotate(90deg) scale(1.1);
}

.bge-add-tile-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bge-add-tile-title {
    color: #ffffff;
}

.bge-add-tile-sub {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
    max-width: 200px;
    line-height: 1.4;
}

/* Floating Bottom Studio Dock */
.bge-bottom-dock {
    position: sticky;
    bottom: 1.5rem;
    z-index: 100;
    margin-top: 2.5rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    padding: 0.85rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.12);
    flex-wrap: wrap;
}

[data-theme="dark"] .bge-bottom-dock {
    background: rgba(18, 22, 34, 0.92);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.55);
}

.bge-dock-step-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.bge-dock-step-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 12%, transparent);
    color: var(--bge-primary, #262ecf);
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bge-dock-step-sub {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
}

.bge-dock-step-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bge-dock-step-title {
    color: #ffffff;
}

.bge-dock-nav-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.bge-dock-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 1.15rem;
    border-radius: 12px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .bge-dock-btn {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.bge-dock-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none !important;
}

.bge-dock-btn:not(:disabled):hover {
    border-color: var(--bge-primary, #262ecf);
    color: var(--bge-primary, #262ecf);
    transform: translateY(-2px);
}

.bge-dock-btn-next {
    background: var(--bge-primary, #262ecf);
    color: #ffffff !important;
    border-color: var(--bge-primary, #262ecf);
    box-shadow: 0 4px 14px -2px color-mix(in srgb, var(--bge-primary, #262ecf) 40%, transparent);
}

.bge-dock-btn-next:hover {
    background: var(--bge-primary-hover, #1f25a6);
}

/* Google Drive Buttons & Modal Styles */
.bge-drive-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: #1a73e8;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 0.45rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(26, 115, 232, 0.35);
}
.bge-drive-btn:hover {
    background: #1557b0;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(26, 115, 232, 0.5);
}
.bge-drive-btn-row {
    background: rgba(26, 115, 232, 0.12);
    color: #1a73e8;
    border: 1px solid rgba(26, 115, 232, 0.3);
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: all 0.2s;
}
.bge-drive-btn-row:hover {
    background: #1a73e8;
    color: white;
}
[data-theme="dark"] .bge-drive-btn-row {
    background: rgba(26, 115, 232, 0.2);
    color: #60a5fa;
    border-color: rgba(96, 165, 250, 0.3);
}
[data-theme="dark"] .bge-drive-btn-row:hover {
    background: #1a73e8;
    color: white;
}

/* Modal Overlay & Dialog */
.gdrive-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8px);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.gdrive-modal-dialog {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    max-width: 850px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.5);
    animation: gdriveFade 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes gdriveFade {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
[data-theme="dark"] .gdrive-modal-dialog {
    background: #141722;
    border-color: rgba(255, 255, 255, 0.1);
}
.gdrive-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
[data-theme="dark"] .gdrive-modal-header {
    border-color: rgba(255, 255, 255, 0.08);
}
.gdrive-header-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.gdrive-icon-logo {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(26, 115, 232, 0.12);
    color: #1a73e8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.gdrive-tabs-nav {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem 1.75rem;
    background: var(--bg-body, #f8fafc);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
[data-theme="dark"] .gdrive-tabs-nav {
    background: #0d0f17;
    border-color: rgba(255, 255, 255, 0.06);
}
.gdrive-tab-link {
    padding: 0.6rem 1rem;
    border-radius: 10px;
    border: none;
    background: transparent;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s;
}
.gdrive-tab-link.active {
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
[data-theme="dark"] .gdrive-tab-link.active {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}
.gdrive-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 1.75rem;
    display: flex;
    flex-direction: column;
}
.gdrive-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.gdrive-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
}
.gdrive-crumb-item {
    cursor: pointer;
    color: #1a73e8;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.gdrive-crumb-item:hover {
    text-decoration: underline;
}
.gdrive-search-input {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 10px;
    padding: 0.5rem 0.85rem;
    font-size: 0.85rem;
    color: var(--text-main, #0f172a);
    outline: none;
    width: 240px;
}
[data-theme="dark"] .gdrive-search-input {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}
.gdrive-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 1rem;
}
.gdrive-item-card {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 0.85rem 0.65rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    min-height: 140px;
}
[data-theme="dark"] .gdrive-item-card {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}
.gdrive-item-card:hover {
    border-color: #1a73e8;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(26, 115, 232, 0.2);
}
.gdrive-item-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}
.gdrive-item-thumb {
    width: 100%;
    height: 80px;
    object-fit: contain;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
}
.gdrive-item-name {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
[data-theme="dark"] .gdrive-item-name {
    color: #e2e8f0;
}
.gdrive-select-btn {
    margin-top: 0.5rem;
    background: #1a73e8;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
}
.gdrive-select-btn:hover {
    background: #1557b0;
}


/* Switch toggle */
.bge-switch-label {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    cursor: pointer;
    user-select: none;
}

.bge-switch-toggle {
    width: 48px;
    height: 26px;
    background: #cbd5e1;
    border-radius: 9999px;
    position: relative;
    transition: background 0.3s;
}

.bge-switch-toggle::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

input[type="checkbox"]:checked + .bge-switch-toggle {
    background: var(--bge-primary, #262ecf);
}

input[type="checkbox"]:checked + .bge-switch-toggle::after {
    transform: translateX(22px);
}

/* Typography Switcher & Google Fonts Autocomplete */
.bge-font-tab-btn {
    color: var(--text-muted, #64748b);
    background: transparent;
    transition: all 0.2s;
}
.bge-font-tab-btn.active {
    background: white;
    color: var(--bge-primary, #262ecf);
    box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
[data-theme="dark"] .bge-font-tab-btn.active {
    background: #1e293b;
    color: #818cf8;
}
.bge-quick-font-pill {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-main, #334155);
    cursor: pointer;
    transition: all 0.15s;
}
.bge-quick-font-pill:hover {
    border-color: var(--bge-primary, #262ecf);
    color: var(--bge-primary, #262ecf);
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 6%, transparent);
    transform: translateY(-1px);
}
.bge-font-autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.18);
    max-height: 250px;
    overflow-y: auto;
    margin-top: 4px;
}
[data-theme="dark"] .bge-font-autocomplete-dropdown {
    background: #181d2a;
    border-color: rgba(255,255,255,0.12);
}
.bge-font-option-item {
    padding: 0.65rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    transition: background 0.15s;
}
[data-theme="dark"] .bge-font-option-item {
    border-bottom-color: rgba(255,255,255,0.05);
}
.bge-font-option-item:hover {
    background: color-mix(in srgb, var(--bge-primary, #262ecf) 8%, transparent);
}
.bge-font-option-name {
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--text-main, #0f172a);
}
[data-theme="dark"] .bge-font-option-name {
    color: #f8fafc;
}
.bge-font-option-meta {
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.bge-font-badge {
    background: rgba(0,0,0,0.05);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    text-transform: capitalize;
}
[data-theme="dark"] .bge-font-badge {
    background: rgba(255,255,255,0.08);
    color: #cbd5e1;
}

/* Responsive Media Queries */
@media (max-width: 900px) {
    .bge-container {
        padding: 1rem 1rem 3rem !important;
    }
    .bge-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        padding: 1rem 1.25rem;
    }
    .bge-header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .bge-tabs-bar {
        padding: 0.4rem;
        gap: 0.35rem;
    }
    .bge-tab-btn {
        padding: 0.5rem 0.85rem;
        font-size: 0.82rem;
    }
    .bge-form-grid {
        grid-template-columns: 1fr !important;
    }
    .bge-logo-upload-grid {
        grid-template-columns: 1fr !important;
    }
}
@media (max-width: 600px) {
    .bge-container {
        padding: 0.75rem 0.5rem 2.5rem !important;
    }
    .bge-card-panel {
        padding: 1.25rem 1rem !important;
        border-radius: 14px !important;
    }
    .bge-title {
        font-size: 1.35rem;
    }
    .bge-btn-save {
        width: 100%;
        justify-content: center;
    }
    .bge-header-actions {
        flex-direction: column;
    }
    .bge-header-actions a, .bge-header-actions button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="bge-container">
    <form id="brandGuidelineForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <!-- App Studio Workspace Header -->
        <div class="bge-header">
            <div class="bge-header-left">
                <a href="index.php?module=brand_guidelines&action=index" class="bge-back-btn" title="Volver al listado">
                    <i class="ph-bold ph-arrow-left"></i>
                </a>
                <div>
                    <div style="display:flex; align-items:center; gap:0.65rem; flex-wrap:wrap;">
                        <h1 class="bge-title"><?php echo $isEdit ? htmlspecialchars($guideline['brand_name']) : 'Nuevo Manual de Marca'; ?></h1>
                        <span class="bge-studio-status-pill">
                            <span class="bge-status-live-dot"></span>
                            <span>Studio Editor Pro</span>
                        </span>
                    </div>
                    <p class="bge-subtitle">Configura los elementos de identidad, logos, variaciones, paleta cromática y reglas de uso.</p>
                </div>
            </div>

            <div class="bge-header-actions">
                <?php if ($isEdit): ?>
                <a href="index.php?module=brand_guidelines&action=view&slug=<?php echo urlencode($guideline['slug']); ?>" 
                   target="_blank" 
                   class="bge-btn-preview" 
                   title="Abrir vista pública responsive 16:9">
                    <i class="ph-bold ph-arrow-square-out"></i>
                    <span>Previsualizar en Vivo</span>
                    <span class="bge-aspect-pill">16:9</span>
                </a>
                <?php endif; ?>
                <button type="submit" class="bge-btn-save" id="saveSubmitBtn">
                    <i class="ph-bold ph-floppy-disk"></i>
                    <span><?php echo $isEdit ? 'Guardar Cambios' : 'Crear Manual'; ?></span>
                    <kbd class="bge-kbd-shortcut">Ctrl+S</kbd>
                </button>
            </div>
        </div>

        <!-- App Studio Segmented Navigation Tabs Dock -->
        <div class="bge-tabs-dock-wrapper">
            <div class="bge-tabs-bar" id="bgeTabsBar">
                <button type="button" class="bge-tab-btn active" data-step="1" data-name="Datos Generales" onclick="switchBgeTab('general', this)">
                    <span class="bge-tab-step-badge">01</span>
                    <i class="ph-bold ph-identification-card"></i>
                    <span>Datos Generales</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="2" data-name="Logos & Variaciones" onclick="switchBgeTab('logos', this)">
                    <span class="bge-tab-step-badge">02</span>
                    <i class="ph-bold ph-paint-brush-broad"></i>
                    <span>Logos & Variaciones</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="3" data-name="Iconografía" onclick="switchBgeTab('icons', this)">
                    <span class="bge-tab-step-badge">03</span>
                    <i class="ph-bold ph-app-window"></i>
                    <span>Iconografía</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="4" data-name="Paleta Cromática" onclick="switchBgeTab('colors', this)">
                    <span class="bge-tab-step-badge">04</span>
                    <i class="ph-bold ph-palette"></i>
                    <span>Paleta Cromática</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="5" data-name="Tipografías" onclick="switchBgeTab('typography', this)">
                    <span class="bge-tab-step-badge">05</span>
                    <i class="ph-bold ph-text-t"></i>
                    <span>Tipografías</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="6" data-name="Normas de Uso" onclick="switchBgeTab('rules', this)">
                    <span class="bge-tab-step-badge">06</span>
                    <i class="ph-bold ph-shield-check"></i>
                    <span>Normas de Uso</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="7" data-name="Aplicaciones" onclick="switchBgeTab('mockups', this)">
                    <span class="bge-tab-step-badge">07</span>
                    <i class="ph-bold ph-image-square"></i>
                    <span>Aplicaciones</span>
                </button>
                <button type="button" class="bge-tab-btn" data-step="8" data-name="Enlace & Privacidad" onclick="switchBgeTab('privacy', this)">
                    <span class="bge-tab-step-badge">08</span>
                    <i class="ph-bold ph-share-network"></i>
                    <span>Enlace & Privacidad</span>
                </button>
            </div>
        </div>

        <!-- ================= TAB 1: DATOS GENERALES ================= -->
        <div class="bge-section-panel active" id="tab-general">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-identification-card" style="color: #ec4899;"></i> Identidad de la Marca</h2>
                <p class="bge-panel-desc">Define el nombre oficial de la marca, cliente vinculado, slogan y esencia filosófica.</p>

                <div class="bge-form-grid">
                    <div class="bge-field-group">
                        <label class="bge-label">Nombre de la Marca *</label>
                        <input type="text" name="brand_name" id="bgeBrandName" class="bge-input" 
                               placeholder="Ej: Roma Agencia Creativa" 
                               value="<?php echo htmlspecialchars($guideline['brand_name'] ?? ''); ?>"
                               oninput="autoGenerateSlug(this.value)">
                    </div>

                    <div class="bge-field-group">
                        <label class="bge-label">Cliente Asociado (Opcional)</label>
                        <select name="client_id" class="bge-select">
                            <option value="">-- Sin cliente asignado (Marca propia o libre) --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($guideline['client_id'] ?? 0) == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bge-field-group full">
                        <label class="bge-label">Slogan o Tagline</label>
                        <input type="text" name="tagline" class="bge-input" 
                               placeholder="Ej: Impulsamos el crecimiento exponencial de tu marca" 
                               value="<?php echo htmlspecialchars($guideline['tagline'] ?? ''); ?>">
                    </div>

                    <div class="bge-field-group full">
                        <label class="bge-label">Acerca de la Marca / Historia</label>
                        <textarea name="description" class="bge-textarea" rows="3" 
                                  placeholder="Breve reseña del propósito y la historia de la marca..."><?php echo htmlspecialchars($guideline['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="bge-field-group">
                        <label class="bge-label">Misión</label>
                        <textarea name="mission" class="bge-textarea" rows="3" 
                                  placeholder="¿Cuál es la misión principal de la marca?"><?php echo htmlspecialchars($guideline['mission'] ?? ''); ?></textarea>
                    </div>

                    <div class="bge-field-group">
                        <label class="bge-label">Visión</label>
                        <textarea name="vision" class="bge-textarea" rows="3" 
                                  placeholder="¿Hacia dónde se proyecta la marca a futuro?"><?php echo htmlspecialchars($guideline['vision'] ?? ''); ?></textarea>
                    </div>

                    <div class="bge-field-group full">
                        <label class="bge-label">Tono de Voz y Personalidad</label>
                        <input type="text" name="tone_of_voice" class="bge-input" 
                               placeholder="Ej: Innovador, Cercano, Disruptivo, Profesional, Empático" 
                               value="<?php echo htmlspecialchars($guideline['tone_of_voice'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: LOGOS & VARIACIONES ================= -->
        <div class="bge-section-panel" id="tab-logos">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-paint-brush-broad" style="color: #ec4899;"></i> Logotipo Principal e Isotipo</h2>
                <p class="bge-panel-desc">Sube los archivos principales del logotipo en alta resolución (SVG, PNG transparente, WebP).</p>

                <div class="bge-form-grid">
                    <!-- Logo Principal -->
                    <div class="bge-field-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                            <label class="bge-label" style="margin-bottom:0;">Logo Principal (Fondo Claro) *</label>
                            <button type="button" class="bge-drive-btn" onclick="openDriveModal('logo_primary_url', 'preview_logo_primary')">
                                <i class="ph-bold ph-google-drive-logo"></i> Google Drive
                            </button>
                        </div>
                        <input type="hidden" name="logo_primary_url" id="logo_primary_url" value="<?php echo htmlspecialchars($guideline['logo_primary'] ?? ''); ?>">
                        <div class="bge-upload-dropzone" onclick="document.getElementById('file_logo_primary').click()">
                            <?php 
                            $hasPrimary = !empty($guideline['logo_primary']);
                            $primarySrc = $hasPrimary ? bg_asset_url($guideline['logo_primary']) : '';
                            ?>
                            <img src="<?php echo htmlspecialchars($primarySrc); ?>" id="preview_logo_primary" class="bge-preview-box" style="<?php echo $hasPrimary ? '' : 'display:none;'; ?>">
                            <div id="dropzone_info_primary" style="<?php echo $hasPrimary ? 'display:none;' : ''; ?>">
                                <i class="ph-bold ph-cloud-arrow-up bge-dropzone-icon"></i>
                                <div style="font-size: 0.88rem; font-weight: 700; margin-top:0.25rem;">Haz clic para subir archivo local</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">SVG, PNG transparente, WebP o JPG</div>
                            </div>
                            <?php if ($hasPrimary): ?>
                                <span style="font-size: 0.78rem; font-weight: 700; color: #10b981;">Logo cargado correctamente</span>
                            <?php endif; ?>
                            <input type="file" id="file_logo_primary" name="logo_primary" accept=".png,.svg,.webp,.jpg,.jpeg" style="display:none;" onchange="previewUpload(this, 'preview_logo_primary')">
                        </div>
                    </div>

                    <!-- Logo para Fondo Oscuro -->
                    <div class="bge-field-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                            <label class="bge-label" style="margin-bottom:0;">Logo Fondo Oscuro (Opcional)</label>
                            <button type="button" class="bge-drive-btn" onclick="openDriveModal('logo_primary_dark_url', 'preview_logo_primary_dark')">
                                <i class="ph-bold ph-google-drive-logo"></i> Google Drive
                            </button>
                        </div>
                        <input type="hidden" name="logo_primary_dark_url" id="logo_primary_dark_url" value="<?php echo htmlspecialchars($guideline['logo_primary_dark'] ?? ''); ?>">
                        <div class="bge-upload-dropzone" style="background: #0f172a;" onclick="document.getElementById('file_logo_primary_dark').click()">
                            <?php 
                            $hasDark = !empty($guideline['logo_primary_dark']);
                            $darkSrc = $hasDark ? bg_asset_url($guideline['logo_primary_dark']) : '';
                            ?>
                            <img src="<?php echo htmlspecialchars($darkSrc); ?>" id="preview_logo_primary_dark" class="bge-preview-box" style="<?php echo $hasDark ? '' : 'display:none;'; ?>">
                            <div id="dropzone_info_dark" style="<?php echo $hasDark ? 'display:none;' : ''; ?>">
                                <i class="ph-bold ph-moon bge-dropzone-icon" style="color: #cbd5e1;"></i>
                                <div style="font-size: 0.88rem; font-weight: 700; color: white; margin-top:0.25rem;">Versión fondo oscuro / negativo</div>
                                <div style="font-size: 0.75rem; color: #94a3b8;">PNG blanco o SVG con colores invertidos</div>
                            </div>
                            <?php if ($hasDark): ?>
                                <span style="font-size: 0.78rem; font-weight: 700; color: #10b981;">Versión oscura cargada</span>
                            <?php endif; ?>
                            <input type="file" id="file_logo_primary_dark" name="logo_primary_dark" accept=".png,.svg,.webp,.jpg,.jpeg" style="display:none;" onchange="previewUpload(this, 'preview_logo_primary_dark')">
                        </div>
                    </div>

                    <!-- Isotipo o Símbolo Solo -->
                    <div class="bge-field-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                            <label class="bge-label" style="margin-bottom:0;">Isotipo / Símbolo Solo (Opcional)</label>
                            <button type="button" class="bge-drive-btn" onclick="openDriveModal('logo_symbol_url', 'preview_logo_symbol')">
                                <i class="ph-bold ph-google-drive-logo"></i> Google Drive
                            </button>
                        </div>
                        <input type="hidden" name="logo_symbol_url" id="logo_symbol_url" value="<?php echo htmlspecialchars($guideline['logo_symbol'] ?? ''); ?>">
                        <div class="bge-upload-dropzone" onclick="document.getElementById('file_logo_symbol').click()">
                            <?php 
                            $hasSymbol = !empty($guideline['logo_symbol']);
                            $symbolSrc = $hasSymbol ? bg_asset_url($guideline['logo_symbol']) : '';
                            ?>
                            <img src="<?php echo htmlspecialchars($symbolSrc); ?>" id="preview_logo_symbol" class="bge-preview-box" style="<?php echo $hasSymbol ? '' : 'display:none;'; ?>">
                            <div id="dropzone_info_symbol" style="<?php echo $hasSymbol ? 'display:none;' : ''; ?>">
                                <i class="ph-bold ph-shapes bge-dropzone-icon"></i>
                                <div style="font-size: 0.88rem; font-weight: 700; margin-top:0.25rem;">Sube el isotipo / monograma</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Elemento gráfico sin el texto</div>
                            </div>
                            <?php if ($hasSymbol): ?>
                                <span style="font-size: 0.78rem; font-weight: 700; color: #10b981;">Isotipo cargado</span>
                            <?php endif; ?>
                            <input type="file" id="file_logo_symbol" name="logo_symbol" accept=".png,.svg,.webp,.jpg,.jpeg" style="display:none;" onchange="previewUpload(this, 'preview_logo_symbol')">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variaciones Dinámicas -->
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-squares-four" style="color: #ec4899;"></i> Variaciones del Logotipo</h2>
                <p class="bge-panel-desc">Agrega versiones secundarias: horizontal, vertical, sello, monocromático en negro o escala de grises.</p>

                <div id="variationsContainer">
                    <?php foreach ($variations as $idx => $v): ?>
                        <div class="bge-color-item" id="variation_row_<?php echo $idx; ?>">
                            <div style="width: 60px; height: 60px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
                                <?php if (!empty($v['url'])): ?>
                                    <img src="<?php echo htmlspecialchars(bg_asset_url($v['url'])); ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                <?php else: ?>
                                    <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.5rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <input type="text" class="bge-input var-name" placeholder="Nombre (ej. Versión Vertical)" value="<?php echo htmlspecialchars($v['name'] ?? ''); ?>">
                                <input type="text" class="bge-input var-desc" placeholder="Uso recomendado" value="<?php echo htmlspecialchars($v['desc'] ?? ''); ?>">
                                <input type="hidden" class="var-url" value="<?php echo htmlspecialchars($v['url'] ?? ''); ?>">
                            </div>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.var-url', 'img')" title="Elegir o subir a Google Drive">
                                    <i class="ph-bold ph-google-drive-logo"></i> Drive
                                </button>
                                <input type="file" name="variation_file_<?php echo $idx; ?>" accept="image/*,.svg" style="max-width: 120px; font-size: 0.75rem;">
                            </div>
                            <button type="button" class="btn-icon danger" onclick="removeVariation(<?php echo $idx; ?>)" title="Eliminar variación" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addVariationRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Nueva Variación de Logo
                </button>
            </div>
        </div>

        <!-- ================= TAB 3: ICONOGRAFÍA ================= -->
        <div class="bge-section-panel" id="tab-icons">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-app-window" style="color: #ec4899;"></i> Iconografía & Elementos Digitales</h2>
                <p class="bge-panel-desc">Sube los favicons, iconos para aplicaciones móviles, avatares o sets de pictogramas de la marca.</p>

                <div id="iconsContainer">
                    <?php if (empty($icons)): ?>
                        <!-- Default Icon Rows -->
                        <div class="bge-color-item" id="icon_row_0">
                            <div style="width: 50px; height: 50px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
                                <i class="ph-bold ph-app-window" style="color: var(--text-muted); font-size: 1.35rem;"></i>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <input type="text" class="bge-input ico-name" placeholder="Nombre (ej. Favicon Web)" value="Favicon Oficial">
                                <input type="text" class="bge-input ico-desc" placeholder="Especificación (ej. 32x32px / 64x64px)" value="Icono para pestañas del navegador">
                                <input type="hidden" class="ico-url" value="">
                            </div>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.ico-url', 'img')" title="Elegir o subir a Google Drive">
                                    <i class="ph-bold ph-google-drive-logo"></i> Drive
                                </button>
                                <input type="file" name="icon_file_0" accept="image/*,.svg,.ico" style="max-width: 120px; font-size: 0.75rem;">
                            </div>
                            <button type="button" class="btn-icon danger" onclick="removeIcon(0)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($icons as $idx => $ico): ?>
                            <div class="bge-color-item" id="icon_row_<?php echo $idx; ?>">
                                <div style="width: 50px; height: 50px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
                                    <?php if (!empty($ico['url'])): ?>
                                        <img src="<?php echo htmlspecialchars(bg_asset_url($ico['url'])); ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <i class="ph-bold ph-app-window" style="color: var(--text-muted); font-size: 1.35rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <input type="text" class="bge-input ico-name" placeholder="Nombre del Icono" value="<?php echo htmlspecialchars($ico['name'] ?? ''); ?>">
                                    <input type="text" class="bge-input ico-desc" placeholder="Descripción de uso" value="<?php echo htmlspecialchars($ico['desc'] ?? ''); ?>">
                                    <input type="hidden" class="ico-url" value="<?php echo htmlspecialchars($ico['url'] ?? ''); ?>">
                                </div>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.ico-url', 'img')" title="Elegir o subir a Google Drive">
                                        <i class="ph-bold ph-google-drive-logo"></i> Drive
                                    </button>
                                    <input type="file" name="icon_file_<?php echo $idx; ?>" accept="image/*,.svg,.ico" style="max-width: 120px; font-size: 0.75rem;">
                                </div>
                                <button type="button" class="btn-icon danger" onclick="removeIcon(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addIconRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Nuevo Icono o Pictograma
                </button>
            </div>
        </div>

        <!-- ================= TAB 4: PALETA DE COLORES ================= -->
        <div class="bge-section-panel" id="tab-colors">
            <div class="bge-card-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h2 class="bge-panel-title"><i class="ph-bold ph-palette" style="color: #ec4899;"></i> Paleta Cromática Corporativa</h2>
                        <p class="bge-panel-desc" style="margin-bottom:0;">Selecciona el color visualmente y el sistema calculará automáticamente los códigos RGB, CMYK y tonalidades monocromáticas.</p>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                        <button type="button" class="bge-btn-preview" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="extractColorsFromCurrentLogo()">
                            <i class="ph-bold ph-magic-wand"></i> Auto-extraer desde Logo
                        </button>
                        <button type="button" class="bge-btn-save" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="addColorRow()">
                            <i class="ph-bold ph-plus-circle"></i> Agregar Color
                        </button>
                    </div>
                </div>

                <div id="colorsContainer" class="bge-colors-grid">
                    <?php foreach ($colors as $idx => $col): 
                        $hex = strtoupper($col['hex'] ?? '#262ECF');
                        if (strpos($hex, '#') !== 0) $hex = '#' . $hex;
                        $role = $col['role'] ?? 'Primario';
                    ?>
                        <div class="bge-color-item bge-studio-color-card" id="color_row_<?php echo $idx; ?>">
                            <!-- Swatch Canvas Top -->
                            <div class="bge-card-swatch-top" id="swatch_box_<?php echo $idx; ?>" style="background-color: <?php echo $hex; ?>;" onclick="document.getElementById('picker_<?php echo $idx; ?>').click()">
                                <input type="color" id="picker_<?php echo $idx; ?>" class="bge-color-hidden-picker" value="<?php echo $hex; ?>" oninput="updateColorValues(<?php echo $idx; ?>, this.value)" onchange="updateColorValues(<?php echo $idx; ?>, this.value)">
                                
                                <div class="bge-swatch-overlay-top">
                                    <span class="bge-role-badge" id="role_badge_<?php echo $idx; ?>">
                                        <i class="ph-bold ph-tag"></i> <span><?php echo htmlspecialchars($role); ?></span>
                                    </span>
                                    <div class="bge-swatch-actions-right">
                                        <button type="button" class="bge-swatch-del-btn" onclick="event.stopPropagation(); removeColor(<?php echo $idx; ?>)" title="Eliminar color">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="bge-swatch-center-info">
                                    <span class="bge-contrast-pill" id="contrast_badge_<?php echo $idx; ?>">
                                        <i class="ph-bold ph-sparkle"></i> Contrast: Auto
                                    </span>
                                </div>

                                <div class="bge-swatch-overlay-bottom">
                                    <button type="button" class="bge-copy-hex-pill" onclick="event.stopPropagation(); copyColorText(document.getElementById('hex_val_<?php echo $idx; ?>').value, this)" title="Copiar código HEX">
                                        <i class="ph-bold ph-copy"></i> <span id="hex_display_<?php echo $idx; ?>"><?php echo $hex; ?></span>
                                    </button>
                                    <span class="bge-swatch-picker-hint"><i class="ph-bold ph-paint-brush"></i> Clic para editar</span>
                                </div>
                            </div>

                            <!-- 5-Step Tonal Tint Strip -->
                            <div class="bge-card-tint-bar" id="tint_bar_<?php echo $idx; ?>">
                                <!-- Generated dynamically on page load and live color updates -->
                            </div>

                            <!-- Card Body -->
                            <div class="bge-card-body">
                                <div class="bge-card-title-row">
                                    <div class="bge-field-col" style="flex: 1.4;">
                                        <label class="bge-micro-label"><i class="ph-bold ph-text-aa"></i> Nombre</label>
                                        <input type="text" class="bge-input col-name" value="<?php echo htmlspecialchars($col['name'] ?? ''); ?>" placeholder="Nombre del Color">
                                    </div>
                                    <div class="bge-field-col" style="flex: 1;">
                                        <label class="bge-micro-label"><i class="ph-bold ph-tag"></i> Rol</label>
                                        <select class="bge-select col-role" onchange="updateRoleBadge(<?php echo $idx; ?>, this.value)">
                                            <option value="Primario" <?php echo ($role === 'Primario') ? 'selected' : ''; ?>>Primario</option>
                                            <option value="Secundario" <?php echo ($role === 'Secundario') ? 'selected' : ''; ?>>Secundario</option>
                                            <option value="Acento" <?php echo ($role === 'Acento') ? 'selected' : ''; ?>>Acento</option>
                                            <option value="Fondo" <?php echo ($role === 'Fondo') ? 'selected' : ''; ?>>Fondo</option>
                                            <option value="Texto" <?php echo ($role === 'Texto') ? 'selected' : ''; ?>>Texto</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- 2x2 Code Chips Grid -->
                                <div class="bge-code-chips-grid">
                                    <div class="bge-code-chip">
                                        <span class="bge-code-chip-label">HEX</span>
                                        <input type="text" class="bge-code-chip-input col-hex" id="hex_val_<?php echo $idx; ?>" value="<?php echo $hex; ?>" oninput="updateColorFromHex(<?php echo $idx; ?>, this.value)">
                                        <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('hex_val_<?php echo $idx; ?>').value, this)" title="Copiar HEX">
                                            <i class="ph-bold ph-copy"></i>
                                        </button>
                                    </div>

                                    <div class="bge-code-chip">
                                        <span class="bge-code-chip-label">RGB</span>
                                        <input type="text" class="bge-code-chip-input col-rgb" id="rgb_val_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($col['rgb'] ?? ''); ?>" placeholder="79, 70, 229">
                                        <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('rgb_val_<?php echo $idx; ?>').value, this)" title="Copiar RGB">
                                            <i class="ph-bold ph-copy"></i>
                                        </button>
                                    </div>

                                    <div class="bge-code-chip">
                                        <span class="bge-code-chip-label">CMYK</span>
                                        <input type="text" class="bge-code-chip-input col-cmyk" id="cmyk_val_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($col['cmyk'] ?? ''); ?>" placeholder="C:66 M:69 Y:0 K:10">
                                        <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('cmyk_val_<?php echo $idx; ?>').value, this)" title="Copiar CMYK">
                                            <i class="ph-bold ph-copy"></i>
                                        </button>
                                    </div>

                                    <div class="bge-code-chip">
                                        <span class="bge-code-chip-label">PANTONE</span>
                                        <input type="text" class="bge-code-chip-input col-pantone" value="<?php echo htmlspecialchars($col['pantone'] ?? ''); ?>" placeholder="PMS 286 C">
                                        <button type="button" class="bge-code-chip-copy" onclick="copyColorText(this.previousElementSibling.value, this)" title="Copiar Pantone">
                                            <i class="ph-bold ph-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Add Color Studio Tile -->
                    <div class="bge-add-color-studio-tile" onclick="addColorRow()">
                        <div class="bge-add-tile-icon">
                            <i class="ph-bold ph-plus"></i>
                        </div>
                        <div class="bge-add-tile-title">Agregar Nuevo Color</div>
                        <div class="bge-add-tile-sub">Calcula automáticamente RGB, CMYK y tonalidades</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: TIPOGRAFÍAS ================= -->
        <div class="bge-section-panel" id="tab-typography">
            <div class="bge-card-panel">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                    <div>
                        <h2 class="bge-panel-title"><i class="ph-bold ph-text-t" style="color: #ec4899;"></i> Tipografías Corporativas</h2>
                        <p class="bge-panel-desc">Define las fuentes principales y secundarias de la marca. Puedes elegir directamente de <strong>Google Fonts</strong> (catálogo oficial con +1,900 fuentes) o <strong>subir tu archivo de tipografía / Google Drive</strong>.</p>
                    </div>
                    <span style="font-size:0.8rem; background:rgba(236,72,153,0.1); color:#ec4899; padding:0.4rem 0.85rem; border-radius:999px; font-weight:700; display:inline-flex; align-items:center; gap:0.4rem;">
                        <i class="ph-bold ph-sparkle"></i> Renderizado en Vivo 16:9
                    </span>
                </div>

                <div id="fontsContainer">
                    <?php foreach ($fonts as $idx => $f): 
                        $fSource = in_array($f['source'] ?? '', ['google', 'custom']) ? $f['source'] : (!empty($f['file_url']) ? 'custom' : 'google');
                        $fName = !empty($f['name']) ? $f['name'] : ($idx === 0 ? 'Inter' : 'Roboto');
                        $fRole = $f['role'] ?? ($idx === 0 ? 'Titulares y Marca' : 'Cuerpo de Texto y Párrafos');
                        $fWeights = $f['weights'] ?? ($idx === 0 ? 'Regular 400, SemiBold 600, Bold 700' : 'Regular 400, Medium 500');
                        $fUsage = $f['usage'] ?? '';
                        $fFileUrl = $f['file_url'] ?? '';
                        $fCategory = $f['category'] ?? 'sans-serif';
                    ?>
                        <div class="bge-color-item bge-font-item-card" id="font_row_<?php echo $idx; ?>" style="flex-direction: column; align-items: stretch; gap: 1rem; border-left: 4px solid #ec4899; padding: 1.25rem;">
                            <input type="hidden" class="fnt-source" value="<?php echo $fSource; ?>">
                            <input type="hidden" class="fnt-category" value="<?php echo htmlspecialchars($fCategory); ?>">
                            <input type="hidden" class="fnt-file-url" value="<?php echo htmlspecialchars($fFileUrl); ?>">

                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                                <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
                                    <span style="font-weight: 800; font-size: 1rem; color: #ec4899;">Tipografía #<span class="fnt-num"><?php echo $idx + 1; ?></span></span>
                                    
                                    <!-- SEGMENTED SWITCH: GOOGLE FONTS vs SUBIR / DRIVE -->
                                    <div class="bge-font-type-switch" style="display:inline-flex; background:rgba(0,0,0,0.06); padding:3px; border-radius:10px; gap:3px;">
                                        <button type="button" class="bge-font-tab-btn <?php echo $fSource === 'google' ? 'active' : ''; ?>" onclick="switchFontSource(this, 'google')" style="padding:5px 14px; border-radius:8px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                            <i class="ph-bold ph-google-logo"></i> Google Fonts
                                        </button>
                                        <button type="button" class="bge-font-tab-btn <?php echo $fSource === 'custom' ? 'active' : ''; ?>" onclick="switchFontSource(this, 'custom')" style="padding:5px 14px; border-radius:8px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                            <i class="ph-bold ph-upload-simple"></i> Subir / Drive
                                        </button>
                                    </div>
                                </div>

                                <button type="button" class="btn-icon danger" onclick="removeFont(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.15rem;" title="Eliminar Tipografía">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </div>

                            <!-- GOOGLE FONTS CONTROLS -->
                            <div class="fnt-panel-google" style="<?php echo $fSource === 'custom' ? 'display:none;' : ''; ?>">
                                <div style="position:relative;">
                                    <label class="bge-label"><i class="ph-bold ph-magnifying-glass"></i> Buscar y Seleccionar en Google Fonts</label>
                                    <input type="text" class="bge-input fnt-name" value="<?php echo htmlspecialchars($fName); ?>" placeholder="Escribe para buscar... Ej: Inter, Montserrat, Poppins, Outfit..." onfocus="showFontAutocomplete(this)" oninput="filterFontAutocomplete(this)">
                                    <div class="bge-font-autocomplete-dropdown" style="display:none;"></div>
                                </div>

                                <!-- Quick popular suggestions -->
                                <div style="margin-top:0.5rem; display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">Populares:</span>
                                    <?php foreach(['Inter', 'Montserrat', 'Poppins', 'Outfit', 'Plus Jakarta Sans', 'Playfair Display', 'Roboto', 'Lato', 'Cinzel', 'DM Sans'] as $popFont): ?>
                                    <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, '<?php echo $popFont; ?>')"><?php echo $popFont; ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- CUSTOM UPLOAD / DRIVE CONTROLS -->
                            <div class="fnt-panel-custom" style="<?php echo $fSource === 'google' ? 'display:none;' : ''; ?>">
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; align-items:end;">
                                    <div>
                                        <label class="bge-label">Nombre de la Tipografía</label>
                                        <input type="text" class="bge-input fnt-name-custom" value="<?php echo htmlspecialchars($fName); ?>" placeholder="Ej: Centra No2, Gotham, Helvetica Neue..." oninput="updateCustomFontName(this)">
                                    </div>
                                    <div>
                                        <label class="bge-label">Archivo de Fuente (.ttf, .otf, .woff, .woff2) o Google Drive</label>
                                        <div style="display:flex; gap:0.5rem;">
                                            <label class="bge-drive-btn-row" style="cursor:pointer; flex:1; justify-content:center; padding:0.55rem 0.8rem; background:rgba(236,72,153,0.08); border-color:rgba(236,72,153,0.3); color:#ec4899;">
                                                <i class="ph-bold ph-upload-simple"></i> Subir Archivo
                                                <input type="file" class="fnt-file-input" accept=".ttf,.otf,.woff,.woff2" style="display:none;" onchange="handleFontFileSelected(this)">
                                            </label>
                                            <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForFont(this)" title="Elegir fuente desde Google Drive" style="padding:0.55rem 0.85rem;">
                                                <i class="ph-bold ph-google-drive-logo"></i> Google Drive
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="fnt-file-status" style="margin-top:0.4rem; font-size:0.8rem; color:var(--text-muted);">
                                    <?php if (!empty($fFileUrl)): ?>
                                        <span style="color:#10b981; font-weight:700;"><i class="ph-bold ph-check-circle"></i> Archivo vinculado: <?php echo htmlspecialchars(basename($fFileUrl)); ?></span>
                                    <?php else: ?>
                                        <span>Formatos soportados: TTF, OTF, WOFF, WOFF2. Si no subes archivo se usará el nombre para fuentes instaladas en el sistema.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- SHARED FIELDS: ROL, PESOS, RECOMENDACIONES -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                                <div>
                                    <label class="bge-label">Jerarquía / Rol en la Marca</label>
                                    <input type="text" class="bge-input fnt-role" value="<?php echo htmlspecialchars($fRole); ?>" placeholder="Ej: Titulares Primarios, Subtítulos, Párrafos">
                                </div>
                                <div>
                                    <label class="bge-label">Pesos Utilizados</label>
                                    <input type="text" class="bge-input fnt-weights" value="<?php echo htmlspecialchars($fWeights); ?>" placeholder="Ej: Regular 400, SemiBold 600, Bold 700">
                                </div>
                            </div>

                            <div>
                                <label class="bge-label">Recomendaciones de Uso & Contexto</label>
                                <input type="text" class="bge-input fnt-usage" value="<?php echo htmlspecialchars($fUsage); ?>" placeholder="Ej: Utilizar en mayúsculas para titulares principales. Textos corridos en Regular 400...">
                            </div>

                            <!-- LIVE INTERACTIVE SPECIMEN PREVIEW -->
                            <div class="fnt-live-specimen-wrap" style="background: var(--bg-surface); border: 1.5px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:0.5rem;">
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#ec4899;">Muestra Tipográfica en Vivo</span>
                                        <span class="fnt-badge-display" style="font-size:0.72rem; padding:2px 8px; border-radius:6px; background:rgba(236,72,153,0.12); color:#ec4899; font-weight:700;"><?php echo htmlspecialchars($fName); ?></span>
                                    </div>
                                    <span style="font-size:0.75rem; color:var(--text-muted);"><i class="ph-bold ph-pencil-simple"></i> Puedes editar la frase de prueba debajo</span>
                                </div>

                                <div class="fnt-live-specimen" style="font-family: '<?php echo htmlspecialchars($fName); ?>', sans-serif;">
                                    <div style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.35rem; line-height:1.2;">Aa Bb Cc Dd Ee Ff Gg Hh 1234567890</div>
                                    <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.85rem;">
                                        ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz (Á, É, Í, Ó, Ú, Ñ, &amp;, @, €, $)
                                    </div>
                                    <input type="text" class="fnt-test-input" value="Roma Agencia — Diseñando marcas memorables e identidades visuales únicas." placeholder="Escribe aquí para probar la fuente..." style="width:100%; background:transparent; border:1px dashed var(--border-color); border-radius:8px; padding:0.5rem 0.75rem; font-size:1.1rem; color:var(--text-main); font-family:inherit; outline:none;">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addFontRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Otra Fuente
                </button>
            </div>
        </div>

        <!-- ================= TAB 6: NORMAS DE USO ================= -->
        <div class="bge-section-panel" id="tab-rules">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-shield-check" style="color: #ec4899;"></i> Área de Seguridad y Tamaños Mínimos</h2>
                <p class="bge-panel-desc">Establece el margen de protección indispensable para asegurar la legibilidad del logotipo.</p>

                <div class="bge-form-grid">
                    <div class="bge-field-group">
                        <label class="bge-label">Área de Seguridad (Clear Space)</label>
                        <textarea name="safe_zone_rules" class="bge-textarea" rows="3" placeholder="Ej: El espacio de protección alrededor del logotipo debe ser igual a la altura de la 'X' del isotipo en todos sus cuatro lados."><?php echo htmlspecialchars($guideline['safe_zone_rules'] ?? ''); ?></textarea>
                    </div>

                    <div class="bge-field-group">
                        <label class="bge-label">Tamaños Mínimos Recomendados</label>
                        <textarea name="min_size_rules" class="bge-textarea" rows="3" placeholder="Ej: Digital: 60px de ancho. Impresión offset: 25mm de ancho. Para formatos menores, utilizar únicamente el isotipo."><?php echo htmlspecialchars($guideline['min_size_rules'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Usos Prohibidos -->
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-prohibit" style="color: #ef4444;"></i> Usos Incorrectos del Logotipo</h2>
                <p class="bge-panel-desc">Indica de forma clara y visual las malas prácticas que deben evitarse al aplicar la identidad.</p>

                <div id="incorrectUsesContainer">
                    <?php foreach ($incorrectUses as $idx => $u): ?>
                        <div class="bge-color-item" id="incorrect_row_<?php echo $idx; ?>">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items:center; justify-content:center; font-size: 22px; flex-shrink: 0;">
                                <i class="ph-bold ph-x-circle"></i>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 2fr; gap: 0.75rem;">
                                <input type="text" class="bge-input inc-title" value="<?php echo htmlspecialchars($u['title'] ?? ''); ?>" placeholder="Título de prohibición (ej. No deformar)">
                                <input type="text" class="bge-input inc-desc" value="<?php echo htmlspecialchars($u['desc'] ?? ''); ?>" placeholder="Explicación del uso incorrecto...">
                            </div>
                            <button type="button" class="btn-icon danger" onclick="removeIncorrect(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addIncorrectRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Otra Prohibición de Uso
                </button>
            </div>
        </div>

        <!-- ================= TAB 7: APLICACIONES & MOCKUPS ================= -->
        <div class="bge-section-panel" id="tab-mockups">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-image-square" style="color: #ec4899;"></i> Aplicaciones de Marca y Mockups</h2>
                <p class="bge-panel-desc">Sube imágenes reales o mockups de cómo se aplica la marca en papelería, redes sociales, packaging o tarjetas.</p>

                <div id="applicationsContainer">
                    <?php foreach ($applications as $idx => $app): ?>
                        <div class="bge-color-item" id="app_row_<?php echo $idx; ?>">
                            <div style="width: 70px; height: 70px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
                                <?php if (!empty($app['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars(bg_asset_url($app['image_url'])); ?>" style="max-width:100%; max-height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.8rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1.5fr; gap: 0.75rem;">
                                <input type="text" class="bge-input app-title" value="<?php echo htmlspecialchars($app['title'] ?? ''); ?>" placeholder="Título (ej. Tarjetas de Presentación)">
                                <input type="text" class="bge-input app-desc" value="<?php echo htmlspecialchars($app['desc'] ?? ''); ?>" placeholder="Descripción de la aplicación">
                                <input type="hidden" class="app-url" value="<?php echo htmlspecialchars($app['image_url'] ?? ''); ?>">
                            </div>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.app-url', 'img')" title="Elegir o subir a Google Drive">
                                    <i class="ph-bold ph-google-drive-logo"></i> Drive
                                </button>
                                <input type="file" name="application_file_<?php echo $idx; ?>" accept="image/*" style="max-width: 120px; font-size: 0.75rem;">
                            </div>
                            <button type="button" class="btn-icon danger" onclick="removeApp(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addAppRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Aplicación / Mockup
                </button>
            </div>
        </div>

        <!-- ================= TAB 8: ENLACE & PRIVACIDAD ================= -->
        <div class="bge-section-panel" id="tab-privacy">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-share-network" style="color: #ec4899;"></i> Enlace Corto y Modo de Compartir</h2>
                <p class="bge-panel-desc">Configura la URL amigable y define si el manual estará disponible públicamente o protegido con contraseña/PIN.</p>

                <div class="bge-form-grid">
                    <!-- Slug amigable -->
                    <div class="bge-field-group full">
                        <label class="bge-label">Enlace Corto Amigable (Slug) *</label>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="background:var(--bg-body); border:1px solid var(--border-color); border-radius:12px; padding:0.75rem 1rem; font-family:monospace; color:var(--text-muted); font-size:0.88rem;">
                                <?php echo htmlspecialchars($baseUrl); ?>/b/
                            </span>
                            <input type="text" name="slug" id="bgeSlug" class="bge-input" 
                                   placeholder="nombre-de-marca" 
                                   value="<?php echo htmlspecialchars($guideline['slug'] ?? ''); ?>" 
                                   onchange="verifySlug(this.value)" 
                                   style="font-family: monospace; font-weight:700; flex:1;">
                        </div>
                        <span id="slugFeedback" style="font-size: 0.78rem; margin-top: 0.25rem;"></span>
                    </div>

                    <!-- Modo de Privacidad -->
                    <div class="bge-field-group full" style="margin-top: 0.5rem;">
                        <label class="bge-label">Modo de Acceso</label>
                        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; margin-top:0.35rem;">
                            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
                                <input type="radio" name="is_public" value="1" <?php echo ($guideline['is_public'] ?? 1) == 1 ? 'checked' : ''; ?> onchange="togglePasswordGroup(false)">
                                <span style="font-weight:700;"><i class="ph-bold ph-globe" style="color:#10b981;"></i> Modo Público</span>
                                <span style="font-size:0.8rem; color:var(--text-muted);">(Cualquiera con el enlace puede ver el manual)</span>
                            </label>

                            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
                                <input type="radio" name="is_public" value="0" <?php echo ($guideline['is_public'] ?? 1) == 0 ? 'checked' : ''; ?> onchange="togglePasswordGroup(true)">
                                <span style="font-weight:700;"><i class="ph-bold ph-lock-key" style="color:#f59e0b;"></i> Modo Privado con PIN / Contraseña</span>
                                <span style="font-size:0.8rem; color:var(--text-muted);">(Protegido para clientes o uso interno)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contraseña / PIN si es privado -->
                    <div class="bge-field-group" id="passwordGroup" style="<?php echo ($guideline['is_public'] ?? 1) == 0 ? '' : 'display:none;'; ?>">
                        <label class="bge-label">PIN o Contraseña de Acceso</label>
                        <input type="text" name="access_password" class="bge-input" 
                               placeholder="Ej: 1234 o Marca2026*" 
                               value="<?php echo htmlspecialchars($guideline['access_password'] ?? ''); ?>">
                        <span style="font-size:0.75rem; color:var(--text-muted);">Los visitantes deberán ingresar este código para desbloquear el manual.</span>
                    </div>

                    <!-- Permitir Descarga de Assets -->
                    <div class="bge-field-group full" style="margin-top: 0.5rem;">
                        <label class="bge-switch-label">
                            <input type="checkbox" name="allow_asset_download" value="1" <?php echo ($guideline['allow_asset_download'] ?? 1) == 1 ? 'checked' : ''; ?> style="display:none;">
                            <div class="bge-switch-toggle"></div>
                            <div>
                                <span style="font-weight:700; display:block;">Permitir descarga de archivos a los visitantes</span>
                                <span style="font-size:0.8rem; color:var(--text-muted);">Habilita botones en el manual para que diseñadores o clientes descarguen el SVG/PNG del logo.</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Floating Studio Bottom Dock -->
        <div class="bge-bottom-dock">
            <div class="bge-dock-step-info">
                <span class="bge-dock-step-circle" id="dockStepNum">1</span>
                <div>
                    <div class="bge-dock-step-sub">Paso activo</div>
                    <div class="bge-dock-step-title" id="dockStepTitle">Datos Generales</div>
                </div>
            </div>

            <div class="bge-dock-nav-group">
                <button type="button" class="bge-dock-btn" id="dockPrevBtn" onclick="navigateStep(-1)" disabled>
                    <i class="ph-bold ph-arrow-left"></i> <span>Paso Anterior</span>
                </button>
                <button type="button" class="bge-dock-btn bge-dock-btn-next" id="dockNextBtn" onclick="navigateStep(1)">
                    <span>Siguiente Paso</span> <i class="ph-bold ph-arrow-right"></i>
                </button>
            </div>

            <div class="bge-dock-save-group">
                <button type="button" class="bge-btn-save bge-dock-save-btn" onclick="document.getElementById('brandGuidelineForm').requestSubmit()">
                    <i class="ph-bold ph-floppy-disk"></i>
                    <span>Guardar Cambios</span>
                    <kbd class="bge-kbd-shortcut">Ctrl+S</kbd>
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ---------------- STUDIO STEP NAVIGATION ----------------
const BGE_STEPS = [
    { id: 'general', num: 1, title: 'Datos Generales' },
    { id: 'logos', num: 2, title: 'Logos & Variaciones' },
    { id: 'icons', num: 3, title: 'Iconografía' },
    { id: 'colors', num: 4, title: 'Paleta Cromática' },
    { id: 'typography', num: 5, title: 'Tipografías' },
    { id: 'rules', num: 6, title: 'Normas de Uso' },
    { id: 'mockups', num: 7, title: 'Aplicaciones & Mockups' },
    { id: 'privacy', num: 8, title: 'Enlace & Privacidad' }
];

let currentStepIndex = 0;

// Switch tabs smoothly with active step indicator sync
function switchBgeTab(tabId, btn) {
    const cleanId = String(tabId).replace(/^tab-/, '');
    const idx = BGE_STEPS.findIndex(s => s.id === cleanId);
    if (idx !== -1) {
        currentStepIndex = idx;
    }

    document.querySelectorAll('.bge-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.bge-section-panel').forEach(p => p.classList.remove('active'));

    const matchBtn = btn || document.querySelector(`.bge-tab-btn[onclick*="'${cleanId}'"]`);
    if (matchBtn) {
        matchBtn.classList.add('active');
        try { matchBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); } catch(e){}
    }

    const panel = document.getElementById('tab-' + cleanId) || document.getElementById(tabId);
    if (panel) {
        panel.classList.add('active');
    }

    updateBottomDock();
}
window.switchTab = switchBgeTab;
window.switchBgeTab = switchBgeTab;

function updateBottomDock() {
    const step = BGE_STEPS[currentStepIndex] || BGE_STEPS[0];
    const numEl = document.getElementById('dockStepNum');
    const titleEl = document.getElementById('dockStepTitle');
    const prevBtn = document.getElementById('dockPrevBtn');
    const nextBtn = document.getElementById('dockNextBtn');

    if (numEl) numEl.textContent = step.num;
    if (titleEl) titleEl.textContent = step.title;

    if (prevBtn) {
        prevBtn.disabled = (currentStepIndex === 0);
    }
    if (nextBtn) {
        if (currentStepIndex === BGE_STEPS.length - 1) {
            nextBtn.innerHTML = '<span>Finalizar & Guardar</span> <i class="ph-bold ph-check"></i>';
            nextBtn.onclick = function() { document.getElementById('brandGuidelineForm').requestSubmit(); };
        } else {
            nextBtn.innerHTML = '<span>Siguiente Paso</span> <i class="ph-bold ph-arrow-right"></i>';
            nextBtn.onclick = function() { navigateStep(1); };
        }
    }
}

function navigateStep(direction) {
    const newIdx = currentStepIndex + direction;
    if (newIdx >= 0 && newIdx < BGE_STEPS.length) {
        switchBgeTab(BGE_STEPS[newIdx].id);
        window.scrollTo({ top: 120, behavior: 'smooth' });
    }
}

// Global shortcut: Ctrl+S / Cmd+S to submit form
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        const form = document.getElementById('brandGuidelineForm');
        if (form) form.requestSubmit();
    }
});

// Auto generate slug from brand name
function autoGenerateSlug(text) {
    const slugInput = document.getElementById('bgeSlug');
    // Only generate if user hasn't typed a custom slug or it's a new guideline
    <?php if (!$isEdit): ?>
    let clean = text.toLowerCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
    slugInput.value = clean;
    <?php endif; ?>
}

// Verify slug availability via AJAX
function verifySlug(slug) {
    const feedback = document.getElementById('slugFeedback');
    if (!slug) return;

    fetch('modules/brand_guidelines/ajax.php?action=check_slug', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ slug: slug, current_id: '<?php echo $id; ?>' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.available) {
            feedback.innerHTML = '<span style="color:#10b981;"><i class="ph-bold ph-check-circle"></i> Enlace disponible: /b/' + data.slug + '</span>';
        } else {
            feedback.innerHTML = '<span style="color:#ef4444;"><i class="ph-bold ph-warning-circle"></i> Enlace en uso. Sugerido: /b/' + data.suggested + '</span>';
        }
    });
}

// Toggle password input visibility
function togglePasswordGroup(show) {
    const group = document.getElementById('passwordGroup');
    if (show) {
        group.style.display = 'flex';
    } else {
        group.style.display = 'none';
    }
}

// Preview Upload Images
function previewUpload(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            // Auto extract colors if uploading primary logo or symbol
            if (previewId === 'preview_logo_primary' || previewId === 'preview_logo_symbol') {
                extractColorsFromImageSrc(e.target.result, function(palette) {
                    applyExtractedPalette(palette);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '🎨 Paleta extraída del logotipo',
                            text: 'Se han configurado automáticamente los colores corporativos.',
                            timer: 2200,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                });
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ---------------- AUTOMATIC COLOR EXTRACTION FROM LOGO ----------------
function extractColorsFromImageSrc(imgSrc, callback) {
    if (!imgSrc) return;
    const img = new Image();
    img.crossOrigin = "Anonymous";
    img.onload = function() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const maxDim = 120;
            let w = img.naturalWidth || img.width || 120;
            let h = img.naturalHeight || img.height || 120;
            if (w > maxDim || h > maxDim) {
                if (w > h) {
                    h = Math.round((h * maxDim) / w);
                    w = maxDim;
                } else {
                    w = Math.round((w * maxDim) / h);
                    h = maxDim;
                }
            }
            canvas.width = Math.max(1, w);
            canvas.height = Math.max(1, h);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            
            const colorCounts = {};
            for (let i = 0; i < imgData.length; i += 4) {
                const a = imgData[i + 3];
                if (a < 120) continue; // transparent
                const r = imgData[i];
                const g = imgData[i + 1];
                const b = imgData[i + 2];
                // Ignore near-white / transparent background canvas
                if (r > 240 && g > 240 && b > 240) continue;
                // Ignore near-black noise
                if (r < 18 && g < 18 && b < 18) continue;

                // Quantize to step of 16 for robust grouping
                const qr = Math.min(255, Math.round(r / 16) * 16);
                const qg = Math.min(255, Math.round(g / 16) * 16);
                const qb = Math.min(255, Math.round(b / 16) * 16);
                const key = `${qr},${qg},${qb}`;
                colorCounts[key] = (colorCounts[key] || 0) + 1;
            }

            const entries = Object.keys(colorCounts).map(k => {
                const [r, g, b] = k.split(',').map(Number);
                const count = colorCounts[k];
                const max = Math.max(r, g, b);
                const min = Math.min(r, g, b);
                const sat = max === 0 ? 0 : (max - min) / max;
                // Score prioritizes rich/saturated brand colors over dull grays
                const score = count * (0.35 + sat * 0.65);
                return { r, g, b, count, score };
            });

            entries.sort((a, b) => b.score - a.score);

            const distinctColors = [];
            for (const item of entries) {
                const isFar = distinctColors.every(c => {
                    const dist = Math.sqrt(
                        Math.pow(item.r - c.r, 2) +
                        Math.pow(item.g - c.g, 2) +
                        Math.pow(item.b - c.b, 2)
                    );
                    return dist > 55;
                });
                if (isFar) {
                    distinctColors.push(item);
                    if (distinctColors.length >= 3) break;
                }
            }

            function rgbToHexStr(r, g, b) {
                return '#' + [r, g, b].map(x => {
                    const h = Math.min(255, Math.max(0, Math.round(x))).toString(16);
                    return h.length === 1 ? '0' + h : h;
                }).join('').toUpperCase();
            }

            let primHex = distinctColors[0] ? rgbToHexStr(distinctColors[0].r, distinctColors[0].g, distinctColors[0].b) : '#262ECF';
            let secHex = distinctColors[1] ? rgbToHexStr(distinctColors[1].r, distinctColors[1].g, distinctColors[1].b) : null;
            let acceHex = distinctColors[2] ? rgbToHexStr(distinctColors[2].r, distinctColors[2].g, distinctColors[2].b) : null;

            if (!secHex) {
                const pRgb = hexToRgb(primHex);
                secHex = rgbToHexStr(pRgb.r * 0.65, pRgb.g * 0.65, pRgb.b * 0.65);
            }
            if (!acceHex) {
                const pRgb = hexToRgb(primHex);
                acceHex = rgbToHexStr(Math.min(255, pRgb.b + 30), Math.min(255, pRgb.r + 20), Math.min(255, pRgb.g + 40));
            }

            const palette = [
                { role: 'Primario', name: 'Color Primario', hex: primHex },
                { role: 'Secundario', name: 'Color Secundario', hex: secHex },
                { role: 'Acento', name: 'Color Acento', hex: acceHex },
                { role: 'Texto', name: 'Texto Principal', hex: '#0F172A' },
                { role: 'Fondo', name: 'Fondo Claro', hex: '#F8FAFC' }
            ];

            if (typeof callback === 'function') {
                callback(palette);
            }
        } catch (err) {
            console.warn("Could not extract colors from image:", err);
        }
    };
    img.src = imgSrc;
}

function applyExtractedPalette(palette) {
    if (!palette || !palette.length) return;
    
    // Ensure we have at least palette.length cards
    while (document.querySelectorAll('#colorsContainer .bge-studio-color-card').length < palette.length) {
        addColorRow();
    }
    
    const updatedCards = document.querySelectorAll('#colorsContainer .bge-studio-color-card');
    palette.forEach((item, idx) => {
        if (updatedCards[idx]) {
            const cardId = updatedCards[idx].id.replace('color_row_', '');
            
            const roleSelect = updatedCards[idx].querySelector('.col-role');
            if (roleSelect) {
                roleSelect.value = item.role;
                updateRoleBadge(cardId, item.role);
            }
            const nameInput = updatedCards[idx].querySelector('.col-name');
            if (nameInput) {
                nameInput.value = item.name;
            }
            updateColorValues(cardId, item.hex);
        }
    });
}

function extractColorsFromCurrentLogo() {
    let logoSrc = null;
    const previewPri = document.getElementById('preview_logo_primary');
    if (previewPri && previewPri.src && !previewPri.src.includes('data:image/svg+xml') && previewPri.style.display !== 'none') {
        logoSrc = previewPri.src;
    }
    if (!logoSrc) {
        const drivePri = document.getElementById('drive_logo_primary');
        if (drivePri && drivePri.value) logoSrc = drivePri.value;
    }
    if (!logoSrc) {
        const previewSym = document.getElementById('preview_logo_symbol');
        if (previewSym && previewSym.src && !previewSym.src.includes('data:image/svg+xml') && previewSym.style.display !== 'none') {
            logoSrc = previewSym.src;
        }
    }
    if (!logoSrc) {
        Swal.fire({
            icon: 'info',
            title: 'Sube un Logotipo',
            text: 'Primero sube o selecciona un logotipo en la pestaña Logotipos para extraer sus colores automáticamente.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    Swal.fire({
        title: 'Extrayendo colores...',
        text: 'Analizando las frecuencias cromáticas del logotipo',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            extractColorsFromImageSrc(logoSrc, (palette) => {
                applyExtractedPalette(palette);
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: '¡Paleta extraída con éxito!',
                    text: 'Se han configurado automáticamente los 5 colores corporativos según tu logotipo.',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        }
    });
}

// ---------------- COLOR STUDIO BUILDER LOGIC ----------------
let colorCounter = <?php echo count($colors) + 10; ?>;

function hexToRgb(hex) {
    if (!hex) return { r: 0, g: 0, b: 0 };
    hex = hex.replace('#', '');
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    const r = parseInt(hex.substring(0, 2), 16) || 0;
    const g = parseInt(hex.substring(2, 4), 16) || 0;
    const b = parseInt(hex.substring(4, 6), 16) || 0;
    return { r, g, b };
}

function calculateCmyk(r, g, b) {
    const rNorm = r / 255;
    const gNorm = g / 255;
    const bNorm = b / 255;
    const k = 1 - Math.max(rNorm, gNorm, bNorm);
    let c = 0, m = 0, y = 0;
    if (k < 1) {
        c = Math.round(((1 - rNorm - k) / (1 - k)) * 100);
        m = Math.round(((1 - gNorm - k) / (1 - k)) * 100);
        y = Math.round(((1 - bNorm - k) / (1 - k)) * 100);
    }
    const kPercent = Math.round(k * 100);
    return `C:${c} M:${m} Y:${y} K:${kPercent}`;
}

function getContrastColor(hex) {
    const { r, g, b } = hexToRgb(hex);
    const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return lum > 0.55 ? '#0f172a' : '#ffffff';
}

function generateTints(hex) {
    const { r, g, b } = hexToRgb(hex);
    const factors = [1, 0.8, 0.6, 0.4, 0.2];
    return factors.map(factor => {
        const tr = Math.round(r + (255 - r) * (1 - factor));
        const tg = Math.round(g + (255 - g) * (1 - factor));
        const tb = Math.round(b + (255 - b) * (1 - factor));
        return '#' + [tr, tg, tb].map(x => x.toString(16).padStart(2, '0')).join('').toUpperCase();
    });
}

function updateColorValues(idx, hex) {
    if (!hex) return;
    hex = hex.toUpperCase();
    if (!hex.startsWith('#')) hex = '#' + hex;

    // Swatch background
    const swatch = document.getElementById('swatch_box_' + idx);
    if (swatch) swatch.style.backgroundColor = hex;

    // Inputs & labels
    const hexInput = document.getElementById('hex_val_' + idx);
    if (hexInput) hexInput.value = hex;

    const picker = document.getElementById('picker_' + idx);
    if (picker && picker.value.toUpperCase() !== hex) picker.value = hex;

    const hexDisplay = document.getElementById('hex_display_' + idx);
    if (hexDisplay) hexDisplay.textContent = hex;

    const { r, g, b } = hexToRgb(hex);
    const rgbInput = document.getElementById('rgb_val_' + idx);
    if (rgbInput) rgbInput.value = `${r}, ${g}, ${b}`;

    const cmykInput = document.getElementById('cmyk_val_' + idx);
    if (cmykInput) cmykInput.value = calculateCmyk(r, g, b);

    // Contrast badge
    const contrastBadge = document.getElementById('contrast_badge_' + idx);
    if (contrastBadge) {
        const textColor = getContrastColor(hex);
        contrastBadge.style.color = textColor;
        contrastBadge.style.borderColor = textColor === '#ffffff' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.2)';
        contrastBadge.style.background = textColor === '#ffffff' ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.6)';
        contrastBadge.innerHTML = `<i class="ph-bold ph-sparkle"></i> Contrast: ${textColor === '#ffffff' ? 'Light text' : 'Dark text'}`;
    }

    // Refresh tint strip
    const tintBar = document.getElementById('tint_bar_' + idx);
    if (tintBar) {
        const tints = generateTints(hex);
        tintBar.innerHTML = tints.map((t, ti) => {
            const pct = Math.round([100, 80, 60, 40, 20][ti]);
            return `<div class="bge-tint-cell" style="background-color: ${t};" onclick="copyColorText('${t}', this)" title="Tonalidad ${pct}%: ${t} (Clic para copiar)">
                <span class="bge-tint-pct">${pct}%</span>
            </div>`;
        }).join('');
    }
}

function updateColorFromHex(idx, hex) {
    if (!hex.startsWith('#')) hex = '#' + hex;
    if (hex.length === 7) {
        updateColorValues(idx, hex);
    }
}

function updateRoleBadge(idx, role) {
    const badge = document.getElementById('role_badge_' + idx);
    if (badge) {
        badge.innerHTML = `<i class="ph-bold ph-tag"></i> <span>${role}</span>`;
    }
}

function copyColorText(text, btn) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check" style="color:#10b981;"></i> <span>¡Copiado!</span>`;
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 1500);
    }).catch(() => {
        const temp = document.createElement('textarea');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<i class="ph-bold ph-check" style="color:#10b981;"></i> <span>¡Copiado!</span>`;
        setTimeout(() => { btn.innerHTML = originalHtml; }, 1500);
    });
}

function addColorRow() {
    colorCounter++;
    const container = document.getElementById('colorsContainer');
    const newIdx = colorCounter;
    const defaultHex = '#262ECF';
    const defaultRole = 'Acento';

    const card = document.createElement('div');
    card.className = 'bge-color-item bge-studio-color-card';
    card.id = 'color_row_' + newIdx;
    card.innerHTML = `
        <div class="bge-card-swatch-top" id="swatch_box_${newIdx}" style="background-color: ${defaultHex};" onclick="document.getElementById('picker_${newIdx}').click()">
            <input type="color" id="picker_${newIdx}" class="bge-color-hidden-picker" value="${defaultHex}" oninput="updateColorValues(${newIdx}, this.value)" onchange="updateColorValues(${newIdx}, this.value)">
            
            <div class="bge-swatch-overlay-top">
                <span class="bge-role-badge" id="role_badge_${newIdx}">
                    <i class="ph-bold ph-tag"></i> <span>${defaultRole}</span>
                </span>
                <div class="bge-swatch-actions-right">
                    <button type="button" class="bge-swatch-del-btn" onclick="event.stopPropagation(); removeColor(${newIdx})" title="Eliminar color">
                        <i class="ph-bold ph-trash"></i>
                    </button>
                </div>
            </div>

            <div class="bge-swatch-center-info">
                <span class="bge-contrast-pill" id="contrast_badge_${newIdx}">
                    <i class="ph-bold ph-sparkle"></i> Contrast: Light text
                </span>
            </div>

            <div class="bge-swatch-overlay-bottom">
                <button type="button" class="bge-copy-hex-pill" onclick="event.stopPropagation(); copyColorText(document.getElementById('hex_val_${newIdx}').value, this)" title="Copiar código HEX">
                    <i class="ph-bold ph-copy"></i> <span id="hex_display_${newIdx}">${defaultHex}</span>
                </button>
                <span class="bge-swatch-picker-hint"><i class="ph-bold ph-paint-brush"></i> Clic para editar</span>
            </div>
        </div>

        <div class="bge-card-tint-bar" id="tint_bar_${newIdx}"></div>

        <div class="bge-card-body">
            <div class="bge-card-title-row">
                <div class="bge-field-col" style="flex: 1.4;">
                    <label class="bge-micro-label"><i class="ph-bold ph-text-aa"></i> Nombre</label>
                    <input type="text" class="bge-input col-name" value="Nuevo Color" placeholder="Ej: Acento Vibrante">
                </div>
                <div class="bge-field-col" style="flex: 1;">
                    <label class="bge-micro-label"><i class="ph-bold ph-tag"></i> Rol</label>
                    <select class="bge-select col-role" onchange="updateRoleBadge(${newIdx}, this.value)">
                        <option value="Primario">Primario</option>
                        <option value="Secundario">Secundario</option>
                        <option value="Acento" selected>Acento</option>
                        <option value="Fondo">Fondo</option>
                        <option value="Texto">Texto</option>
                    </select>
                </div>
            </div>

            <div class="bge-code-chips-grid">
                <div class="bge-code-chip">
                    <span class="bge-code-chip-label">HEX</span>
                    <input type="text" class="bge-code-chip-input col-hex" id="hex_val_${newIdx}" value="${defaultHex}" oninput="updateColorFromHex(${newIdx}, this.value)">
                    <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('hex_val_${newIdx}').value, this)" title="Copiar HEX">
                        <i class="ph-bold ph-copy"></i>
                    </button>
                </div>
                <div class="bge-code-chip">
                    <span class="bge-code-chip-label">RGB</span>
                    <input type="text" class="bge-code-chip-input col-rgb" id="rgb_val_${newIdx}" value="38, 46, 207" placeholder="38, 46, 207">
                    <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('rgb_val_${newIdx}').value, this)" title="Copiar RGB">
                        <i class="ph-bold ph-copy"></i>
                    </button>
                </div>
                <div class="bge-code-chip">
                    <span class="bge-code-chip-label">CMYK</span>
                    <input type="text" class="bge-code-chip-input col-cmyk" id="cmyk_val_${newIdx}" value="C:82 M:78 Y:0 K:19" placeholder="C:82 M:78 Y:0 K:19">
                    <button type="button" class="bge-code-chip-copy" onclick="copyColorText(document.getElementById('cmyk_val_${newIdx}').value, this)" title="Copiar CMYK">
                        <i class="ph-bold ph-copy"></i>
                    </button>
                </div>
                <div class="bge-code-chip">
                    <span class="bge-code-chip-label">PANTONE</span>
                    <input type="text" class="bge-code-chip-input col-pantone" placeholder="PMS 286 C">
                    <button type="button" class="bge-code-chip-copy" onclick="copyColorText(this.previousElementSibling.value, this)" title="Copiar Pantone">
                        <i class="ph-bold ph-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

    const addTile = container.querySelector('.bge-add-color-studio-tile');
    if (addTile) {
        container.insertBefore(card, addTile);
    } else {
        container.appendChild(card);
    }

    updateColorValues(newIdx, defaultHex);
}

function removeColor(idx) {
    const row = document.getElementById('color_row_' + idx);
    if (row) row.remove();
}

// ---------------- VARIATIONS BUILDER ----------------
let variationCounter = <?php echo count($variations) + 10; ?>;

function addVariationRow() {
    variationCounter++;
    const container = document.getElementById('variationsContainer');
    const idx = variationCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'variation_row_' + idx;
    row.innerHTML = `
        <div style="width: 60px; height: 60px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
            <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <input type="text" class="bge-input var-name" placeholder="Nombre (ej. Versión Monocromática)">
            <input type="text" class="bge-input var-desc" placeholder="Uso recomendado">
            <input type="hidden" class="var-url" value="">
        </div>
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.var-url', 'img')" title="Elegir o subir a Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i> Drive
            </button>
            <input type="file" name="variation_file_${idx}" accept="image/*,.svg" style="max-width: 120px; font-size: 0.75rem;">
        </div>
        <button type="button" class="btn-icon danger" onclick="removeVariation(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
            <i class="ph-bold ph-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeVariation(idx) {
    const row = document.getElementById('variation_row_' + idx);
    if (row) row.remove();
}

// ---------------- ICONS BUILDER ----------------
let iconCounter = <?php echo count($icons) + 10; ?>;

function addIconRow() {
    iconCounter++;
    const container = document.getElementById('iconsContainer');
    const idx = iconCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'icon_row_' + idx;
    row.innerHTML = `
        <div style="width: 50px; height: 50px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
            <i class="ph-bold ph-app-window" style="color: var(--text-muted); font-size: 1.35rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <input type="text" class="bge-input ico-name" placeholder="Nombre (ej. App Icon iOS)">
            <input type="text" class="bge-input ico-desc" placeholder="Descripción de uso">
            <input type="hidden" class="ico-url" value="">
        </div>
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.ico-url', 'img')" title="Elegir o subir a Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i> Drive
            </button>
            <input type="file" name="icon_file_${idx}" accept="image/*,.svg,.ico" style="max-width: 120px; font-size: 0.75rem;">
        </div>
        <button type="button" class="btn-icon danger" onclick="removeIcon(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
            <i class="ph-bold ph-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeIcon(idx) {
    const row = document.getElementById('icon_row_' + idx);
    if (row) row.remove();
}

// ---------------- FONTS BUILDER & GOOGLE FONTS / DRIVE INTEGRATION ----------------
let fontCounter = <?php echo count($fonts) + 10; ?>;
window.googleFontsCatalog = [];

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Fetch Google Fonts catalog in background once
function initGoogleFontsCatalog() {
    if (window.googleFontsCatalog.length > 0) return;
    fetch('modules/brand_guidelines/ajax.php?action=google_fonts&limit=400')
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.fonts)) {
                window.googleFontsCatalog = data.fonts;
            }
        })
        .catch(err => console.warn('Could not prefetch Google fonts catalog:', err));
}
initGoogleFontsCatalog();

// Switch between Google Fonts mode and Custom / Drive mode
function switchFontSource(btn, mode) {
    const row = btn.closest('.bge-color-item');
    if (!row) return;

    // Toggle active state on buttons
    row.querySelectorAll('.bge-font-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Update hidden source
    const srcInput = row.querySelector('.fnt-source');
    if (srcInput) srcInput.value = mode;

    const panelGoogle = row.querySelector('.fnt-panel-google');
    const panelCustom = row.querySelector('.fnt-panel-custom');

    const nameGoogle = row.querySelector('.fnt-name');
    const nameCustom = row.querySelector('.fnt-name-custom');

    if (mode === 'google') {
        if (panelGoogle) panelGoogle.style.display = 'block';
        if (panelCustom) panelCustom.style.display = 'none';
        if (nameCustom && nameCustom.value && nameGoogle) {
            nameGoogle.value = nameCustom.value;
        }
        if (nameGoogle && nameGoogle.value) {
            applyGoogleFontPreview(row, nameGoogle.value);
        }
    } else {
        if (panelGoogle) panelGoogle.style.display = 'none';
        if (panelCustom) panelCustom.style.display = 'block';
        if (nameGoogle && nameGoogle.value && nameCustom && !nameCustom.value) {
            nameCustom.value = nameGoogle.value;
        }
        const fileUrl = row.querySelector('.fnt-file-url')?.value;
        const fontName = nameCustom?.value || nameGoogle?.value || 'Inter';
        if (fileUrl) {
            applyCustomFontPreview(row, fontName, fileUrl);
        } else {
            updateSpecimenFont(row, fontName);
        }
    }
}

// Autocomplete for Google Fonts
function showFontAutocomplete(input) {
    filterFontAutocomplete(input);
}

function filterFontAutocomplete(input) {
    const row = input.closest('.bge-color-item');
    if (!row) return;
    const dropdown = row.querySelector('.bge-font-autocomplete-dropdown');
    if (!dropdown) return;

    const q = input.value.trim().toLowerCase();
    let matches = [];

    if (window.googleFontsCatalog.length > 0) {
        if (!q) {
            matches = window.googleFontsCatalog.slice(0, 20);
        } else {
            matches = window.googleFontsCatalog.filter(f => f.family.toLowerCase().includes(q)).slice(0, 20);
        }
    } else {
        const fallback = ['Inter', 'Montserrat', 'Poppins', 'Outfit', 'Plus Jakarta Sans', 'Playfair Display', 'Roboto', 'Open Sans', 'Lato', 'Cinzel', 'DM Sans', 'Raleway', 'Nunito', 'Oswald'];
        matches = fallback.filter(f => !q || f.toLowerCase().includes(q)).map(f => ({ family: f, category: 'sans-serif', variants: ['regular', '700'] }));
    }

    if (matches.length === 0) {
        dropdown.innerHTML = '<div style="padding:0.75rem 1rem; color:var(--text-muted); font-size:0.82rem;">No se encontraron fuentes con ese nombre.</div>';
        dropdown.style.display = 'block';
        return;
    }

    dropdown.innerHTML = '';
    matches.forEach(item => {
        const div = document.createElement('div');
        div.className = 'bge-font-option-item';
        div.innerHTML = `
            <div class="bge-font-option-name" style="font-family:'${item.family}', sans-serif;">${item.family}</div>
            <div class="bge-font-option-meta">
                <span class="bge-font-badge">${item.category || 'fuente'}</span>
                <span>${(item.variants || []).length} pesos</span>
            </div>
        `;
        div.onmousedown = function(e) {
            e.preventDefault();
            selectGoogleFont(input, item);
        };
        dropdown.appendChild(div);
    });
    dropdown.style.display = 'block';
}

// Close dropdown on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.fnt-panel-google')) {
        document.querySelectorAll('.bge-font-autocomplete-dropdown').forEach(d => d.style.display = 'none');
    }
});

function selectGoogleFont(inputOrRow, fontData) {
    const row = inputOrRow.closest ? inputOrRow.closest('.bge-color-item') : inputOrRow;
    if (!row) return;

    const nameInput = row.querySelector('.fnt-name');
    const customNameInput = row.querySelector('.fnt-name-custom');
    const catInput = row.querySelector('.fnt-category');
    const weightsInput = row.querySelector('.fnt-weights');
    const dropdown = row.querySelector('.bge-font-autocomplete-dropdown');

    const family = typeof fontData === 'string' ? fontData : fontData.family;
    const category = typeof fontData === 'object' && fontData.category ? fontData.category : 'sans-serif';

    if (nameInput) nameInput.value = family;
    if (customNameInput) customNameInput.value = family;
    if (catInput) catInput.value = category;
    if (dropdown) dropdown.style.display = 'none';

    if (weightsInput && (!weightsInput.value || weightsInput.value === '400, 600' || weightsInput.value === '400, 600, 700')) {
        weightsInput.value = 'Regular 400, SemiBold 600, Bold 700';
    }

    applyGoogleFontPreview(row, family);
}

function selectQuickFont(btn, fontName) {
    const row = btn.closest('.bge-color-item');
    if (!row) return;
    selectGoogleFont(row, { family: fontName, category: 'sans-serif' });
}

function updateCustomFontName(input) {
    const row = input.closest('.bge-color-item');
    if (!row) return;
    const name = input.value.trim() || 'Inter';
    const mainName = row.querySelector('.fnt-name');
    if (mainName) mainName.value = name;
    updateSpecimenFont(row, name);
}

function updateSpecimenFont(row, fontName) {
    const specimen = row.querySelector('.fnt-live-specimen');
    const badge = row.querySelector('.fnt-badge-display');
    if (specimen) specimen.style.fontFamily = `"${fontName}", sans-serif`;
    if (badge) badge.textContent = fontName;
}

// Injects Google Font link tag into head and updates specimen
function applyGoogleFontPreview(row, family) {
    if (!family) return;
    const cleanFamily = family.trim();
    const linkId = 'gfont_live_' + cleanFamily.replace(/[^a-zA-Z0-9]/g, '_');

    if (!document.getElementById(linkId)) {
        const link = document.createElement('link');
        link.id = linkId;
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(cleanFamily)}:wght@300;400;500;600;700;800;900&display=swap`;
        document.head.appendChild(link);
    }

    updateSpecimenFont(row, cleanFamily);
}

// Loads custom font into browser FontFace registry for real-time live preview
function applyCustomFontPreview(row, familyName, url) {
    if (!familyName || !url) return;
    try {
        const safeFamily = 'Custom_' + familyName.replace(/[^a-zA-Z0-9]/g, '_');
        const fontFace = new FontFace(safeFamily, `url('${url}')`);
        fontFace.load().then(loadedFace => {
            document.fonts.add(loadedFace);
            const specimen = row.querySelector('.fnt-live-specimen');
            const badge = row.querySelector('.fnt-badge-display');
            if (specimen) specimen.style.fontFamily = `"${safeFamily}", sans-serif`;
            if (badge) badge.textContent = familyName;
        }).catch(err => {
            console.warn('Could not load custom font face:', err);
            updateSpecimenFont(row, familyName);
        });
    } catch (e) {
        console.warn(e);
        updateSpecimenFont(row, familyName);
    }
}

// Local font file upload handler
function handleFontFileSelected(fileInput) {
    const row = fileInput.closest('.bge-color-item');
    if (!row || !fileInput.files || !fileInput.files[0]) return;

    const file = fileInput.files[0];
    const objectUrl = URL.createObjectURL(file);
    const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, ' ');

    const statusEl = row.querySelector('.fnt-file-status');
    if (statusEl) {
        statusEl.innerHTML = `<span style="color:#10b981; font-weight:700;"><i class="ph-bold ph-check-circle"></i> Archivo listo para guardar: ${escapeHtml(file.name)}</span>`;
    }

    const nameCustom = row.querySelector('.fnt-name-custom');
    const nameInput = row.querySelector('.fnt-name');
    if (nameCustom && (!nameCustom.value || nameCustom.value === 'Inter' || nameCustom.value === 'Roboto')) {
        nameCustom.value = cleanName;
    }
    if (nameInput) {
        nameInput.value = nameCustom ? nameCustom.value : cleanName;
    }

    const fontDisplayName = nameCustom ? nameCustom.value : cleanName;
    applyCustomFontPreview(row, fontDisplayName, objectUrl);
}

// Open Google Drive modal for fonts
function openDriveModalForFont(btn) {
    const parentRow = btn.closest('.bge-color-item');
    if (parentRow) {
        currentDriveTargetInput = parentRow.querySelector('.fnt-file-url');
        currentDriveTargetImg = null;
        showDriveModal();
    }
}

function addFontRow() {
    fontCounter++;
    const container = document.getElementById('fontsContainer');
    const idx = fontCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item bge-font-item-card';
    row.id = 'font_row_' + idx;
    row.style = 'flex-direction: column; align-items: stretch; gap: 1rem; border-left: 4px solid #ec4899; padding: 1.25rem;';
    row.innerHTML = `
        <input type="hidden" class="fnt-source" value="google">
        <input type="hidden" class="fnt-category" value="sans-serif">
        <input type="hidden" class="fnt-file-url" value="">

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
                <span style="font-weight: 800; font-size: 1rem; color: #ec4899;">Tipografía Adicional</span>
                <div class="bge-font-type-switch" style="display:inline-flex; background:rgba(0,0,0,0.06); padding:3px; border-radius:10px; gap:3px;">
                    <button type="button" class="bge-font-tab-btn active" onclick="switchFontSource(this, 'google')" style="padding:5px 14px; border-radius:8px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="ph-bold ph-google-logo"></i> Google Fonts
                    </button>
                    <button type="button" class="bge-font-tab-btn" onclick="switchFontSource(this, 'custom')" style="padding:5px 14px; border-radius:8px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="ph-bold ph-upload-simple"></i> Subir / Drive
                    </button>
                </div>
            </div>
            <button type="button" class="btn-icon danger" onclick="removeFont(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.15rem;" title="Eliminar Tipografía">
                <i class="ph-bold ph-trash"></i>
            </button>
        </div>

        <!-- GOOGLE FONTS CONTROLS -->
        <div class="fnt-panel-google">
            <div style="position:relative;">
                <label class="bge-label"><i class="ph-bold ph-magnifying-glass"></i> Buscar y Seleccionar en Google Fonts</label>
                <input type="text" class="bge-input fnt-name" value="Outfit" placeholder="Escribe para buscar... Ej: Inter, Montserrat, Poppins, Outfit..." onfocus="showFontAutocomplete(this)" oninput="filterFontAutocomplete(this)">
                <div class="bge-font-autocomplete-dropdown" style="display:none;"></div>
            </div>

            <div style="margin-top:0.5rem; display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">Populares:</span>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Inter')">Inter</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Montserrat')">Montserrat</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Poppins')">Poppins</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Outfit')">Outfit</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Plus Jakarta Sans')">Plus Jakarta Sans</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Playfair Display')">Playfair Display</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Roboto')">Roboto</button>
                <button type="button" class="bge-quick-font-pill" onclick="selectQuickFont(this, 'Cinzel')">Cinzel</button>
            </div>
        </div>

        <!-- CUSTOM UPLOAD / DRIVE CONTROLS -->
        <div class="fnt-panel-custom" style="display:none;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; align-items:end;">
                <div>
                    <label class="bge-label">Nombre de la Tipografía</label>
                    <input type="text" class="bge-input fnt-name-custom" value="Outfit" placeholder="Ej: Centra No2, Gotham..." oninput="updateCustomFontName(this)">
                </div>
                <div>
                    <label class="bge-label">Archivo (.ttf, .otf, .woff, .woff2) o Google Drive</label>
                    <div style="display:flex; gap:0.5rem;">
                        <label class="bge-drive-btn-row" style="cursor:pointer; flex:1; justify-content:center; padding:0.55rem 0.8rem; background:rgba(236,72,153,0.08); border-color:rgba(236,72,153,0.3); color:#ec4899;">
                            <i class="ph-bold ph-upload-simple"></i> Subir Archivo
                            <input type="file" class="fnt-file-input" accept=".ttf,.otf,.woff,.woff2" style="display:none;" onchange="handleFontFileSelected(this)">
                        </label>
                        <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForFont(this)" title="Elegir desde Google Drive" style="padding:0.55rem 0.85rem;">
                            <i class="ph-bold ph-google-drive-logo"></i> Google Drive
                        </button>
                    </div>
                </div>
            </div>
            <div class="fnt-file-status" style="margin-top:0.4rem; font-size:0.8rem; color:var(--text-muted);">
                <span>Formatos soportados: TTF, OTF, WOFF, WOFF2.</span>
            </div>
        </div>

        <!-- SHARED FIELDS -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <div>
                <label class="bge-label">Jerarquía / Rol en la Marca</label>
                <input type="text" class="bge-input fnt-role" value="Secundaria / Destacados" placeholder="Rol">
            </div>
            <div>
                <label class="bge-label">Pesos Utilizados</label>
                <input type="text" class="bge-input fnt-weights" value="Regular 400, Bold 700" placeholder="Pesos">
            </div>
        </div>

        <div>
            <label class="bge-label">Recomendaciones de Uso & Contexto</label>
            <input type="text" class="bge-input fnt-usage" placeholder="Aplicación recomendada...">
        </div>

        <!-- SPECIMEN -->
        <div class="fnt-live-specimen-wrap" style="background: var(--bg-surface); border: 1.5px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:0.5rem;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#ec4899;">Muestra Tipográfica en Vivo</span>
                    <span class="fnt-badge-display" style="font-size:0.72rem; padding:2px 8px; border-radius:6px; background:rgba(236,72,153,0.12); color:#ec4899; font-weight:700;">Outfit</span>
                </div>
                <span style="font-size:0.75rem; color:var(--text-muted);"><i class="ph-bold ph-pencil-simple"></i> Puedes editar la frase debajo</span>
            </div>
            <div class="fnt-live-specimen" style="font-family: 'Outfit', sans-serif;">
                <div style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.35rem; line-height:1.2;">Aa Bb Cc Dd Ee Ff Gg Hh 1234567890</div>
                <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.85rem;">
                    ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz (Á, É, Í, Ó, Ú, Ñ, &amp;, @, €, $)
                </div>
                <input type="text" class="fnt-test-input" value="Roma Agencia — Diseñando marcas memorables e identidades visuales únicas." placeholder="Escribe aquí para probar la fuente..." style="width:100%; background:transparent; border:1px dashed var(--border-color); border-radius:8px; padding:0.5rem 0.75rem; font-size:1.1rem; color:var(--text-main); font-family:inherit; outline:none;">
            </div>
        </div>
    `;
    container.appendChild(row);
    applyGoogleFontPreview(row, 'Outfit');
}

function removeFont(idx) {
    const row = document.getElementById('font_row_' + idx);
    if (row) row.remove();
}

// ---------------- INCORRECT USES BUILDER ----------------
let incCounter = <?php echo count($incorrectUses) + 10; ?>;

function addIncorrectRow() {
    incCounter++;
    const container = document.getElementById('incorrectUsesContainer');
    const idx = incCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'incorrect_row_' + idx;
    row.innerHTML = `
        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items:center; justify-content:center; font-size: 22px; flex-shrink: 0;">
            <i class="ph-bold ph-x-circle"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 2fr; gap: 0.75rem;">
            <input type="text" class="bge-input inc-title" placeholder="Título de prohibición">
            <input type="text" class="bge-input inc-desc" placeholder="Explicación del uso incorrecto...">
        </div>
        <button type="button" class="btn-icon danger" onclick="removeIncorrect(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
            <i class="ph-bold ph-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeIncorrect(idx) {
    const row = document.getElementById('incorrect_row_' + idx);
    if (row) row.remove();
}

// ---------------- APPLICATIONS BUILDER ----------------
let appCounter = <?php echo count($applications) + 10; ?>;

function addAppRow() {
    appCounter++;
    const container = document.getElementById('applicationsContainer');
    const idx = appCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'app_row_' + idx;
    row.innerHTML = `
        <div style="width: 70px; height: 70px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0; overflow:hidden;">
            <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.8rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1.5fr; gap: 0.75rem;">
            <input type="text" class="bge-input app-title" placeholder="Título (ej. Packaging)">
            <input type="text" class="bge-input app-desc" placeholder="Descripción de la aplicación">
            <input type="hidden" class="app-url" value="">
        </div>
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <button type="button" class="bge-drive-btn-row" onclick="openDriveModalForRow(this, '.app-url', 'img')" title="Elegir o subir a Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i> Drive
            </button>
            <input type="file" name="application_file_${idx}" accept="image/*" style="max-width: 120px; font-size: 0.75rem;">
        </div>
        <button type="button" class="btn-icon danger" onclick="removeApp(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
            <i class="ph-bold ph-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeApp(idx) {
    const row = document.getElementById('app_row_' + idx);
    if (row) row.remove();
}

// ---------------- FORM SUBMISSION VIA AJAX ----------------
document.getElementById('brandGuidelineForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const brandNameInput = document.getElementById('bgeBrandName');
    const slugInput = document.getElementById('bgeSlug');
    const brandName = brandNameInput ? brandNameInput.value.trim() : '';

    if (!brandName) {
        switchTab('identidad');
        if (brandNameInput) {
            brandNameInput.focus();
            brandNameInput.style.borderColor = '#ef4444';
            setTimeout(() => { brandNameInput.style.borderColor = ''; }, 3000);
        }
        Swal.fire({
            icon: 'warning',
            title: 'Nombre de Marca requerido',
            text: 'Por favor ingresa el nombre de la marca en la pestaña Identidad antes de guardar.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }

    const saveBtn = document.getElementById('saveSubmitBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="animation: spin 1s infinite linear;"></i> Guardando...';

    // Compile dynamic JSON structures
    // 1. Variations
    const varItems = [];
    document.querySelectorAll('#variationsContainer .bge-color-item').forEach(el => {
        const name = el.querySelector('.var-name')?.value.trim();
        const desc = el.querySelector('.var-desc')?.value.trim();
        const url = el.querySelector('.var-url')?.value.trim();
        if (name) {
            varItems.push({ name, desc, url });
        }
    });

    // 2. Icons
    const icoItems = [];
    document.querySelectorAll('#iconsContainer .bge-color-item').forEach(el => {
        const name = el.querySelector('.ico-name')?.value.trim();
        const desc = el.querySelector('.ico-desc')?.value.trim();
        const url = el.querySelector('.ico-url')?.value.trim();
        if (name) {
            icoItems.push({ name, desc, url });
        }
    });

    // 3. Colors
    const colItems = [];
    document.querySelectorAll('#colorsContainer .bge-color-item').forEach(el => {
        const name = el.querySelector('.col-name')?.value.trim();
        const role = el.querySelector('.col-role')?.value.trim();
        const hex = el.querySelector('.col-hex')?.value.trim();
        const rgb = el.querySelector('.col-rgb')?.value.trim();
        const cmyk = el.querySelector('.col-cmyk')?.value.trim();
        const pantone = el.querySelector('.col-pantone')?.value.trim();
        if (hex) {
            colItems.push({ name, role, hex, rgb, cmyk, pantone });
        }
    });

    // 4. Fonts
    const fontItems = [];
    const fontFiles = [];
    document.querySelectorAll('#fontsContainer .bge-color-item').forEach((el, idx) => {
        const source = el.querySelector('.fnt-source')?.value || 'google';
        const name = (source === 'custom' ? el.querySelector('.fnt-name-custom')?.value : el.querySelector('.fnt-name')?.value)?.trim() || el.querySelector('.fnt-name')?.value?.trim();
        const role = el.querySelector('.fnt-role')?.value.trim();
        const weights = el.querySelector('.fnt-weights')?.value.trim();
        const usage = el.querySelector('.fnt-usage')?.value.trim();
        const category = el.querySelector('.fnt-category')?.value.trim() || 'sans-serif';
        const fileUrl = el.querySelector('.fnt-file-url')?.value.trim() || '';

        const fileInput = el.querySelector('.fnt-file-input');
        if (fileInput && fileInput.files && fileInput.files[0]) {
            fontFiles.push({ key: 'font_file_' + idx, file: fileInput.files[0] });
        }

        if (name) {
            fontItems.push({
                source: source,
                name: name,
                role: role,
                weights: weights,
                usage: usage,
                category: category,
                file_url: fileUrl
            });
        }
    });

    // 5. Incorrect Uses
    const incItems = [];
    document.querySelectorAll('#incorrectUsesContainer .bge-color-item').forEach(el => {
        const title = el.querySelector('.inc-title')?.value.trim();
        const desc = el.querySelector('.inc-desc')?.value.trim();
        if (title) {
            incItems.push({ title, desc });
        }
    });

    // 6. Applications
    const appItems = [];
    document.querySelectorAll('#applicationsContainer .bge-color-item').forEach(el => {
        const title = el.querySelector('.app-title')?.value.trim();
        const desc = el.querySelector('.app-desc')?.value.trim();
        const url = el.querySelector('.app-url')?.value.trim();
        if (title || url) {
            appItems.push({ title, desc, image_url: url });
        }
    });

    const formData = new FormData(this);
    formData.append('action', 'save');
    formData.append('logo_variations_data', JSON.stringify(varItems));
    formData.append('icons_data', JSON.stringify(icoItems));
    formData.append('colors_json', JSON.stringify(colItems));
    formData.append('fonts_data', JSON.stringify(fontItems));
    formData.append('fonts_json', JSON.stringify(fontItems));
    fontFiles.forEach(ff => {
        formData.append(ff.key, ff.file);
    });
    formData.append('incorrect_uses_json', JSON.stringify(incItems));
    formData.append('applications_data', JSON.stringify(appItems));

    fetch('modules/brand_guidelines/ajax.php?action=save', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ph-bold ph-floppy-disk"></i> <?php echo $isEdit ? 'Guardar Cambios' : 'Crear Manual'; ?>';

        if (data.success) {
            Swal.fire({
                title: '¡Éxito!',
                text: data.message,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Ver Manual Generado',
                cancelButtonText: 'Seguir Editando',
                confirmButtonColor: '#ec4899'
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php?module=brand_guidelines&action=view&slug=' + encodeURIComponent(data.slug);
                } else {
                    <?php if (!$isEdit): ?>
                    window.location.href = 'index.php?module=brand_guidelines&action=edit&id=' + data.id;
                    <?php endif; ?>
                }
            });
        } else {
            Swal.fire('Error', data.message || 'No se pudo guardar el manual', 'error');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ph-bold ph-floppy-disk"></i> <?php echo $isEdit ? 'Guardar Cambios' : 'Crear Manual'; ?>';
        Swal.fire('Error', 'Error de red o procesamiento en el servidor', 'error');
    });
});

// ---------------- GOOGLE DRIVE INTEGRATION ----------------
let currentDriveTargetInput = null;
let currentDriveTargetImg = null;
let currentDriveFolderId = 'root';
let currentDriveFolderName = 'Mi Unidad';
let folderHistory = [];
let searchTimeout = null;

function openDriveModal(inputId, imgId) {
    currentDriveTargetInput = document.getElementById(inputId);
    currentDriveTargetImg = document.getElementById(imgId);
    showDriveModal();
}

function openDriveModalForRow(btn, inputSelector, imgSelector) {
    const parentRow = btn.closest('.bge-color-item');
    if (parentRow) {
        currentDriveTargetInput = parentRow.querySelector(inputSelector);
        currentDriveTargetImg = parentRow.querySelector(imgSelector);
        showDriveModal();
    }
}

function showDriveModal() {
    document.getElementById('gdriveModal').style.display = 'flex';
    switchDriveTab('explore');
    loadDriveFolder(currentDriveFolderId);
}

function closeDriveModal() {
    document.getElementById('gdriveModal').style.display = 'none';
}

function switchDriveTab(tab) {
    const tabExplore = document.getElementById('gdriveTabExplore');
    const tabUpload = document.getElementById('gdriveTabUpload');
    const btnExplore = document.getElementById('gtabBtnExplore');
    const btnUpload = document.getElementById('gtabBtnUpload');

    if (tab === 'explore') {
        tabExplore.style.display = 'block';
        tabUpload.style.display = 'none';
        btnExplore.classList.add('active');
        btnUpload.classList.remove('active');
    } else {
        tabExplore.style.display = 'none';
        tabUpload.style.display = 'block';
        btnExplore.classList.remove('active');
        btnUpload.classList.add('active');
    }
}

function loadDriveFolder(folderId, search = '') {
    const loader = document.getElementById('gdriveLoading');
    const grid = document.getElementById('gdriveItemsGrid');
    loader.style.display = 'block';
    grid.style.display = 'none';

    currentDriveFolderId = folderId;

    const params = new URLSearchParams({ folder_id: folderId });
    if (search) params.append('search', search);

    fetch('modules/brand_guidelines/ajax.php?action=drive_list&' + params.toString())
    .then(r => r.json())
    .then(data => {
        loader.style.display = 'none';
        grid.style.display = 'grid';
        grid.innerHTML = '';

        if (!data.success) {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; color:#ef4444; padding:2rem;">${data.error || 'Error al conectar con Google Drive'}</div>`;
            return;
        }

        // Update Breadcrumbs
        updateDriveBreadcrumbs(data.currentFolder);

        // Render Folders
        if (data.folders && data.folders.length > 0) {
            data.folders.forEach(f => {
                const card = document.createElement('div');
                card.className = 'gdrive-item-card';
                card.onclick = () => {
                    folderHistory.push({ id: currentDriveFolderId, name: currentDriveFolderName });
                    loadDriveFolder(f.id);
                };
                card.innerHTML = `
                    <div class="gdrive-item-icon" style="color:#f59e0b;"><i class="ph-fill ph-folder"></i></div>
                    <div class="gdrive-item-name" title="${f.name}">${f.name}</div>
                    <span style="font-size:0.7rem; color:var(--text-muted); margin-top:auto;">Carpeta</span>
                `;
                grid.appendChild(card);
            });
        }

        // Render Files
        if (data.files && data.files.length > 0) {
            data.files.forEach(f => {
                const card = document.createElement('div');
                card.className = 'gdrive-item-card';
                const isImage = (f.mimeType && f.mimeType.startsWith('image/')) || (f.name && f.name.match(/\.(png|jpg|jpeg|svg|webp|gif)$/i));
                
                let previewHtml = '';
                if (isImage) {
                    previewHtml = `<img src="${f.proxyUrl}" class="gdrive-item-thumb" alt="${f.name}" onerror="this.onerror=null; this.src='assets/img/default-logo.png';">`;
                } else {
                    previewHtml = `<div class="gdrive-item-icon" style="color:#1a73e8;"><i class="ph-bold ph-file-text"></i></div>`;
                }

                card.innerHTML = `
                    ${previewHtml}
                    <div class="gdrive-item-name" title="${f.name}">${f.name}</div>
                    <button type="button" class="gdrive-select-btn" onclick='event.stopPropagation(); selectDriveFile(${JSON.stringify(f)})'>
                        Seleccionar
                    </button>
                `;
                grid.appendChild(card);
            });
        }

        if ((!data.folders || data.folders.length === 0) && (!data.files || data.files.length === 0)) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding:3rem;">No se encontraron archivos en esta carpeta de Google Drive.</div>';
        }
    })
    .catch(() => {
        loader.style.display = 'none';
        grid.style.display = 'block';
        grid.innerHTML = '<div style="text-align:center; color:#ef4444; padding:2rem;">Error al comunicarse con el servidor.</div>';
    });
}

function updateDriveBreadcrumbs(currFolder) {
    currentDriveFolderName = currFolder ? currFolder.name : 'Mi Unidad';
    const bc = document.getElementById('gdriveBreadcrumbs');
    bc.innerHTML = `
        <span class="gdrive-crumb-item" onclick="folderHistory=[]; loadDriveFolder('root')">
            <i class="ph-bold ph-hard-drive"></i> Mi Unidad
        </span>
    `;

    if (currentDriveFolderId !== 'root') {
        bc.innerHTML += `
            <span style="opacity:0.4;">/</span>
            <span style="color:var(--text-main); font-weight:800;">${currentDriveFolderName}</span>
        `;
    }
}

function handleDriveSearch(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const val = e.target.value.trim();
        loadDriveFolder(currentDriveFolderId, val);
    }, 400);
}

function selectDriveFile(file) {
    if (currentDriveTargetInput) {
        const proxyUrl = 'ajax/drive_proxy.php?id=' + file.id;
        currentDriveTargetInput.value = proxyUrl;

        // Check if this target is a Font input
        if (currentDriveTargetInput.classList.contains('fnt-file-url')) {
            const parentRow = currentDriveTargetInput.closest('.bge-color-item');
            if (parentRow) {
                const statusEl = parentRow.querySelector('.fnt-file-status');
                if (statusEl) {
                    statusEl.innerHTML = `<span style="color:#10b981; font-weight:700;"><i class="ph-bold ph-check-circle"></i> Google Drive: ${escapeHtml(file.name)}</span>`;
                }

                const nameCustomInput = parentRow.querySelector('.fnt-name-custom');
                const nameInput = parentRow.querySelector('.fnt-name');
                const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, ' ');

                if (nameCustomInput && (!nameCustomInput.value || nameCustomInput.value === 'Inter' || nameCustomInput.value === 'Roboto')) {
                    nameCustomInput.value = cleanName;
                }
                if (nameInput) {
                    nameInput.value = nameCustomInput ? nameCustomInput.value : cleanName;
                }

                applyCustomFontPreview(parentRow, cleanName, proxyUrl);

                closeDriveModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Fuente de Google Drive vinculada',
                    text: file.name,
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                return;
            }
        }

        if (currentDriveTargetImg) {
            currentDriveTargetImg.src = proxyUrl;
            currentDriveTargetImg.style.display = 'block';

            const container = currentDriveTargetImg.parentElement;
            if (container) {
                const icon = container.querySelector('i');
                if (icon) icon.style.display = 'none';
            }
        }

        // Check if there are dropzone_info elements
        const inputId = currentDriveTargetInput.id || '';
        if (inputId.includes('primary_dark')) {
            const el = document.getElementById('dropzone_info_dark');
            if (el) el.style.display = 'none';
        } else if (inputId.includes('primary')) {
            const el = document.getElementById('dropzone_info_primary');
            if (el) el.style.display = 'none';
            // Auto extract colors from drive logo
            extractColorsFromImageSrc(proxyUrl, function(palette) {
                applyExtractedPalette(palette);
            });
        } else if (inputId.includes('symbol')) {
            const el = document.getElementById('dropzone_info_symbol');
            if (el) el.style.display = 'none';
            // Auto extract colors from drive symbol
            extractColorsFromImageSrc(proxyUrl, function(palette) {
                applyExtractedPalette(palette);
            });
        }

        closeDriveModal();
        Swal.fire({
            icon: 'success',
            title: 'Archivo de Google Drive seleccionado',
            text: file.name,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function handleDirectDriveUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    const progressDiv = document.getElementById('gdriveUploadProgress');
    const statusText = document.getElementById('gdriveUploadStatus');
    const fill = document.getElementById('gdriveProgressFill');

    progressDiv.style.display = 'block';
    statusText.textContent = `Subiendo "${file.name}" a Google Drive...`;
    fill.style.width = '30%';

    const fd = new FormData();
    fd.append('file', file);
    fd.append('folder_id', currentDriveFolderId);

    fetch('modules/brand_guidelines/ajax.php?action=drive_upload', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        fill.style.width = '100%';
        if (data.success) {
            statusText.textContent = '¡Subida completada!';
            setTimeout(() => {
                progressDiv.style.display = 'none';
                input.value = '';
                selectDriveFile({
                    id: data.fileId,
                    name: data.name,
                    mimeType: file.type
                });
            }, 500);
        } else {
            statusText.textContent = 'Error: ' + (data.error || 'No se pudo subir');
            statusText.style.color = '#ef4444';
        }
    })
    .catch(() => {
        statusText.textContent = 'Error de conexión al subir';
        statusText.style.color = '#ef4444';
    });
}

// Initialize live font previews and color studio cards on page load
document.addEventListener('DOMContentLoaded', function() {
    // Fonts initialization
    document.querySelectorAll('#fontsContainer .bge-font-item-card').forEach(row => {
        const source = row.querySelector('.fnt-source')?.value || 'google';
        const name = (source === 'custom' ? row.querySelector('.fnt-name-custom')?.value : row.querySelector('.fnt-name')?.value)?.trim() || row.querySelector('.fnt-name')?.value?.trim();
        const fileUrl = row.querySelector('.fnt-file-url')?.value?.trim();

        if (source === 'custom' && fileUrl) {
            applyCustomFontPreview(row, name, fileUrl);
        } else if (name) {
            applyGoogleFontPreview(row, name);
        }
    });

    // Color studio initialization (tints, contrast, RGB/CMYK sync)
    document.querySelectorAll('#colorsContainer .bge-studio-color-card').forEach(card => {
        const id = card.id.replace('color_row_', '');
        const hexInput = document.getElementById('hex_val_' + id);
        if (hexInput && hexInput.value) {
            updateColorValues(id, hexInput.value);
        }
    });

    // Bottom dock initial state
    if (typeof updateBottomDock === 'function') {
        updateBottomDock();
    }
});
</script>

<!-- Google Drive Picker & Uploader Modal -->
<div class="gdrive-modal-overlay" id="gdriveModal" style="display:none;" onclick="if(event.target === this) closeDriveModal()">
    <div class="gdrive-modal-dialog">
        <!-- Header -->
        <div class="gdrive-modal-header">
            <div class="gdrive-header-badge">
                <div class="gdrive-icon-logo">
                    <i class="ph-bold ph-google-drive-logo"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--text-main);">Google Drive</h3>
                    <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Explora o sube recursos visuales para tu manual de marca</p>
                </div>
            </div>
            <button type="button" class="btn-close-modal" onclick="closeDriveModal()" style="background:transparent; border:none; font-size:1.35rem; color:var(--text-muted); cursor:pointer;">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="gdrive-tabs-nav">
            <button type="button" class="gdrive-tab-link active" id="gtabBtnExplore" onclick="switchDriveTab('explore')">
                <i class="ph-bold ph-folder-open"></i> Explorar Archivos
            </button>
            <button type="button" class="gdrive-tab-link" id="gtabBtnUpload" onclick="switchDriveTab('upload')">
                <i class="ph-bold ph-cloud-arrow-up"></i> Subir Archivo Nuevo
            </button>
        </div>

        <!-- Body -->
        <div class="gdrive-modal-body">
            <!-- TAB 1: EXPLORE -->
            <div id="gdriveTabExplore">
                <div class="gdrive-toolbar">
                    <div class="gdrive-breadcrumbs" id="gdriveBreadcrumbs">
                        <span class="gdrive-crumb-item" onclick="loadDriveFolder('root')">
                            <i class="ph-bold ph-hard-drive"></i> Mi Unidad
                        </span>
                    </div>
                    <div style="position:relative;">
                        <input type="text" id="gdriveSearchInput" class="gdrive-search-input" placeholder="Buscar por nombre..." onkeyup="handleDriveSearch(event)">
                        <i class="ph-bold ph-magnifying-glass" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                    </div>
                </div>

                <div id="gdriveLoading" style="text-align:center; padding:3rem; color:var(--text-muted);">
                    <i class="ph-bold ph-spinner-gap" style="font-size:2.5rem; animation: spin 1s infinite linear; color:#1a73e8;"></i>
                    <p style="margin-top:0.75rem; font-weight:600;">Cargando archivos de Google Drive...</p>
                </div>

                <div class="gdrive-grid" id="gdriveItemsGrid" style="display:none;"></div>
            </div>

            <!-- TAB 2: DIRECT UPLOAD TO DRIVE -->
            <div id="gdriveTabUpload" style="display:none;">
                <div style="border: 2px dashed rgba(26, 115, 232, 0.4); border-radius: 20px; padding: 3rem 2rem; text-align: center; background: rgba(26, 115, 232, 0.03); cursor: pointer;" onclick="document.getElementById('gdriveDirectFileInput').click()">
                    <div style="width:64px; height:64px; border-radius:18px; background:rgba(26,115,232,0.12); color:#1a73e8; display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 1.25rem;">
                        <i class="ph-bold ph-cloud-arrow-up"></i>
                    </div>
                    <h4 style="font-size:1.15rem; font-weight:800; margin-bottom:0.4rem;">Arrastra o selecciona una imagen aquí</h4>
                    <p style="font-size:0.85rem; color:var(--text-muted); max-width:420px; margin:0 auto 1rem;">Se subirá directamente a tu Google Drive y se vinculará automáticamente al manual.</p>
                    <span style="display:inline-block; background:#1a73e8; color:white; font-size:0.85rem; font-weight:700; padding:0.5rem 1.25rem; border-radius:10px;">Examinar Archivo</span>
                    <input type="file" id="gdriveDirectFileInput" accept="image/*,.svg,.pdf" style="display:none;" onchange="handleDirectDriveUpload(this)">
                </div>

                <div id="gdriveUploadProgress" style="display:none; margin-top:1.5rem; text-align:center;">
                    <div style="font-weight:700; margin-bottom:0.5rem; font-size:0.9rem;" id="gdriveUploadStatus">Subiendo a Google Drive...</div>
                    <div style="width:100%; height:8px; background:rgba(255,255,255,0.1); border-radius:999px; overflow:hidden;">
                        <div id="gdriveProgressFill" style="width:0%; height:100%; background:linear-gradient(90deg, #1a73e8, #ec4899); border-radius:999px; transition:width 0.3s;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

