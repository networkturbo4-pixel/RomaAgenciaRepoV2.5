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
?>

<style>
/* ==========================================================================
   BRAND GUIDELINES EDITOR DESIGN SYSTEM
   ========================================================================== */
.bge-container {
    padding: 1.75rem 2.25rem 4rem;
    max-width: 1380px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', sans-serif);
    animation: bgeFade 0.3s ease;
}

@keyframes bgeFade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.bge-header {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1.25rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .bge-header {
    background: #141721;
    border-color: rgba(255, 255, 255, 0.08);
}

.bge-header-left {
    display: flex;
    align-items: center;
    gap: 1.15rem;
}

.bge-back-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-main, #0f172a);
    font-size: 1.2rem;
    text-decoration: none;
    transition: all 0.2s;
}

[data-theme="dark"] .bge-back-btn {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.bge-back-btn:hover {
    background: #ec4899;
    color: #ffffff !important;
    border-color: #ec4899;
    transform: translateX(-2px);
}

.bge-title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bge-title {
    color: #ffffff;
}

.bge-subtitle {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
}

.bge-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.bge-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 0.92rem;
    padding: 0.7rem 1.5rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 18px -3px rgba(236, 72, 153, 0.45);
    transition: all 0.25s ease;
}

.bge-btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -4px rgba(236, 72, 153, 0.6);
}

/* Tabs Navigation */
.bge-tabs-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 0.5rem;
    margin-bottom: 1.75rem;
    overflow-x: auto;
    scrollbar-width: none;
}

[data-theme="dark"] .bge-tabs-bar {
    background: #141721;
    border-color: rgba(255, 255, 255, 0.08);
}

.bge-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.15rem;
    border-radius: 12px;
    border: none;
    background: transparent;
    color: var(--text-muted, #64748b);
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.bge-tab-btn:hover {
    color: var(--text-main, #0f172a);
    background: var(--bg-body, #f8fafc);
}

[data-theme="dark"] .bge-tab-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
}

.bge-tab-btn.active {
    background: color-mix(in srgb, #ec4899 15%, transparent);
    color: #ec4899;
    font-weight: 700;
}

[data-theme="dark"] .bge-tab-btn.active {
    background: rgba(236, 72, 153, 0.2);
    color: #f472b6;
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
    border-color: #ec4899;
    box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.15);
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
    border-color: #ec4899;
    background: color-mix(in srgb, #ec4899 4%, transparent);
}

.bge-dropzone-icon {
    font-size: 2.2rem;
    color: #ec4899;
}

.bge-preview-box {
    max-height: 120px;
    max-width: 100%;
    object-fit: contain;
    margin-bottom: 0.5rem;
}

/* Color Palette Builder */
.bge-color-item {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.85rem;
    transition: all 0.2s;
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
    border-color: #ec4899;
    color: #ec4899;
    background: color-mix(in srgb, #ec4899 8%, transparent);
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
    background: #ec4899;
}

input[type="checkbox"]:checked + .bge-switch-toggle::after {
    transform: translateX(22px);
}
</style>

<div class="bge-container">
    <form id="brandGuidelineForm" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <!-- Header -->
        <div class="bge-header">
            <div class="bge-header-left">
                <a href="index.php?module=brand_guidelines&action=index" class="bge-back-btn" title="Volver al listado">
                    <i class="ph-bold ph-arrow-left"></i>
                </a>
                <div>
                    <h1 class="bge-title"><?php echo $isEdit ? 'Editar: ' . htmlspecialchars($guideline['brand_name']) : 'Nuevo Manual de Marca'; ?></h1>
                    <p class="bge-subtitle">Configura los elementos de identidad, logos, variaciones, paleta cromática y reglas de uso.</p>
                </div>
            </div>

            <div class="bge-header-actions">
                <?php if ($isEdit): ?>
                <a href="index.php?module=brand_guidelines&action=view&slug=<?php echo urlencode($guideline['slug']); ?>" 
                   target="_blank" 
                   class="btn btn-secondary" 
                   style="border-radius: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="ph-bold ph-arrow-square-out"></i> Previsualizar
                </a>
                <?php endif; ?>
                <button type="submit" class="bge-btn-save" id="saveSubmitBtn">
                    <i class="ph-bold ph-floppy-disk"></i>
                    <span><?php echo $isEdit ? 'Guardar Cambios' : 'Crear Manual'; ?></span>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bge-tabs-bar">
            <button type="button" class="bge-tab-btn active" onclick="switchBgeTab('general', this)">
                <i class="ph-bold ph-identification-card"></i> 1. Datos Generales
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('logos', this)">
                <i class="ph-bold ph-paint-brush-broad"></i> 2. Logos & Variaciones
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('icons', this)">
                <i class="ph-bold ph-app-window"></i> 3. Iconografía
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('colors', this)">
                <i class="ph-bold ph-palette"></i> 4. Paleta de Colores
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('typography', this)">
                <i class="ph-bold ph-text-t"></i> 5. Tipografías
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('rules', this)">
                <i class="ph-bold ph-prohibit"></i> 6. Normas & Seguridad
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('mockups', this)">
                <i class="ph-bold ph-image-square"></i> 7. Aplicaciones
            </button>
            <button type="button" class="bge-tab-btn" onclick="switchBgeTab('privacy', this)">
                <i class="ph-bold ph-share-network"></i> 8. Enlace & Privacidad
            </button>
        </div>

        <!-- ================= TAB 1: DATOS GENERALES ================= -->
        <div class="bge-section-panel active" id="tab-general">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-identification-card" style="color: #ec4899;"></i> Identidad de la Marca</h2>
                <p class="bge-panel-desc">Define el nombre oficial de la marca, cliente vinculado, slogan y esencia filosófica.</p>

                <div class="bge-form-grid">
                    <div class="bge-field-group">
                        <label class="bge-label">Nombre de la Marca *</label>
                        <input type="text" name="brand_name" id="bgeBrandName" class="bge-input" required 
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
                        <label class="bge-label">Logo Principal (Fondo Claro) *</label>
                        <div class="bge-upload-dropzone" onclick="document.getElementById('file_logo_primary').click()">
                            <?php if (!empty($guideline['logo_primary']) && file_exists(__DIR__ . '/../../' . $guideline['logo_primary'])): ?>
                                <img src="<?php echo htmlspecialchars($guideline['logo_primary']); ?>" id="preview_logo_primary" class="bge-preview-box">
                                <span style="font-size: 0.8rem; font-weight: 700; color: #10b981;">Archivo actual cargado</span>
                            <?php else: ?>
                                <img src="" id="preview_logo_primary" class="bge-preview-box" style="display:none;">
                                <i class="ph-bold ph-cloud-arrow-up bge-dropzone-icon"></i>
                                <span style="font-size: 0.88rem; font-weight: 700;">Haz clic o arrastra tu logo</span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Formatos recomendados: SVG, PNG transparente</span>
                            <?php endif; ?>
                            <input type="file" id="file_logo_primary" name="logo_primary" accept=".png,.svg,.webp,.jpg,.jpeg" style="display:none;" onchange="previewUpload(this, 'preview_logo_primary')">
                        </div>
                    </div>

                    <!-- Logo para Fondo Oscuro -->
                    <div class="bge-field-group">
                        <label class="bge-label">Logo para Fondo Oscuro / Negativo (Opcional)</label>
                        <div class="bge-upload-dropzone" style="background: #0f172a;" onclick="document.getElementById('file_logo_primary_dark').click()">
                            <?php if (!empty($guideline['logo_primary_dark']) && file_exists(__DIR__ . '/../../' . $guideline['logo_primary_dark'])): ?>
                                <img src="<?php echo htmlspecialchars($guideline['logo_primary_dark']); ?>" id="preview_logo_primary_dark" class="bge-preview-box">
                                <span style="font-size: 0.8rem; font-weight: 700; color: #10b981;">Versión oscura cargada</span>
                            <?php else: ?>
                                <img src="" id="preview_logo_primary_dark" class="bge-preview-box" style="display:none;">
                                <i class="ph-bold ph-moon bge-dropzone-icon" style="color: #cbd5e1;"></i>
                                <span style="font-size: 0.88rem; font-weight: 700; color: white;">Versión en blanco / fondo oscuro</span>
                                <span style="font-size: 0.75rem; color: #94a3b8;">PNG blanco o SVG con colores invertidos</span>
                            <?php endif; ?>
                            <input type="file" id="file_logo_primary_dark" name="logo_primary_dark" accept=".png,.svg,.webp,.jpg,.jpeg" style="display:none;" onchange="previewUpload(this, 'preview_logo_primary_dark')">
                        </div>
                    </div>

                    <!-- Isotipo o Símbolo Solo -->
                    <div class="bge-field-group">
                        <label class="bge-label">Isotipo o Símbolo Solo (Opcional)</label>
                        <div class="bge-upload-dropzone" onclick="document.getElementById('file_logo_symbol').click()">
                            <?php if (!empty($guideline['logo_symbol']) && file_exists(__DIR__ . '/../../' . $guideline['logo_symbol'])): ?>
                                <img src="<?php echo htmlspecialchars($guideline['logo_symbol']); ?>" id="preview_logo_symbol" class="bge-preview-box">
                                <span style="font-size: 0.8rem; font-weight: 700; color: #10b981;">Isotipo cargado</span>
                            <?php else: ?>
                                <img src="" id="preview_logo_symbol" class="bge-preview-box" style="display:none;">
                                <i class="ph-bold ph-shapes bge-dropzone-icon"></i>
                                <span style="font-size: 0.88rem; font-weight: 700;">Sube el isotipo / monograma</span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Elemento gráfico sin el texto</span>
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
                            <div style="width: 60px; height: 60px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0;">
                                <?php if (!empty($v['url'])): ?>
                                    <img src="<?php echo htmlspecialchars($v['url']); ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                <?php else: ?>
                                    <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.5rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <input type="text" class="bge-input var-name" placeholder="Nombre (ej. Versión Vertical)" value="<?php echo htmlspecialchars($v['name'] ?? ''); ?>">
                                <input type="text" class="bge-input var-desc" placeholder="Uso recomendado" value="<?php echo htmlspecialchars($v['desc'] ?? ''); ?>">
                                <input type="hidden" class="var-url" value="<?php echo htmlspecialchars($v['url'] ?? ''); ?>">
                            </div>
                            <input type="file" name="variation_file_<?php echo $idx; ?>" accept="image/*,.svg" style="max-width: 140px; font-size: 0.78rem;">
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
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <input type="text" class="bge-input ico-name" placeholder="Nombre (ej. Favicon Web)" value="Favicon Oficial">
                                <input type="text" class="bge-input ico-desc" placeholder="Especificación (ej. 32x32px / 64x64px)" value="Icono para pestañas del navegador">
                                <input type="hidden" class="ico-url" value="">
                            </div>
                            <input type="file" name="icon_file_0" accept="image/*,.svg,.ico" style="max-width: 140px; font-size: 0.78rem;">
                            <button type="button" class="btn-icon danger" onclick="removeIcon(0)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($icons as $idx => $ico): ?>
                            <div class="bge-color-item" id="icon_row_<?php echo $idx; ?>">
                                <div style="width: 50px; height: 50px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0;">
                                    <?php if (!empty($ico['url'])): ?>
                                        <img src="<?php echo htmlspecialchars($ico['url']); ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <i class="ph-bold ph-app-window" style="color: var(--text-muted); font-size: 1.35rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <input type="text" class="bge-input ico-name" placeholder="Nombre del Icono" value="<?php echo htmlspecialchars($ico['name'] ?? ''); ?>">
                                    <input type="text" class="bge-input ico-desc" placeholder="Descripción de uso" value="<?php echo htmlspecialchars($ico['desc'] ?? ''); ?>">
                                    <input type="hidden" class="ico-url" value="<?php echo htmlspecialchars($ico['url'] ?? ''); ?>">
                                </div>
                                <input type="file" name="icon_file_<?php echo $idx; ?>" accept="image/*,.svg,.ico" style="max-width: 140px; font-size: 0.78rem;">
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
                        <p class="bge-panel-desc" style="margin-bottom:0;">Selecciona el color visualmente y el sistema calculará automáticamente los códigos RGB y CMYK sugeridos.</p>
                    </div>
                </div>

                <div id="colorsContainer">
                    <?php foreach ($colors as $idx => $col): 
                        $hex = strtoupper($col['hex'] ?? '#000000');
                    ?>
                        <div class="bge-color-item" id="color_row_<?php echo $idx; ?>">
                            <div class="bge-color-swatch-picker" style="background: <?php echo $hex; ?>;" id="swatch_box_<?php echo $idx; ?>">
                                <input type="color" value="<?php echo $hex; ?>" onchange="updateColorValues(<?php echo $idx; ?>, this.value)">
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1.4fr 1.1fr 1fr 1.1fr 1.1fr 1fr; gap: 0.6rem; align-items:center;">
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">Nombre Color</label>
                                    <input type="text" class="bge-input col-name" value="<?php echo htmlspecialchars($col['name'] ?? ''); ?>" placeholder="Nombre del Color">
                                </div>
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">Rol / Uso</label>
                                    <select class="bge-select col-role" style="padding: 0.55rem 0.65rem; font-size: 0.85rem;">
                                        <option value="Primario" <?php echo ($col['role'] ?? '') === 'Primario' ? 'selected' : ''; ?>>Primario</option>
                                        <option value="Secundario" <?php echo ($col['role'] ?? '') === 'Secundario' ? 'selected' : ''; ?>>Secundario</option>
                                        <option value="Acento" <?php echo ($col['role'] ?? '') === 'Acento' ? 'selected' : ''; ?>>Acento</option>
                                        <option value="Fondo" <?php echo ($col['role'] ?? '') === 'Fondo' ? 'selected' : ''; ?>>Fondo</option>
                                        <option value="Texto" <?php echo ($col['role'] ?? '') === 'Texto' ? 'selected' : ''; ?>>Texto</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">HEX</label>
                                    <input type="text" class="bge-input col-hex" id="hex_val_<?php echo $idx; ?>" value="<?php echo $hex; ?>" onchange="updateColorFromHex(<?php echo $idx; ?>, this.value)" style="font-family: monospace; font-weight:700;">
                                </div>
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">RGB</label>
                                    <input type="text" class="bge-input col-rgb" id="rgb_val_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($col['rgb'] ?? ''); ?>" placeholder="79, 70, 229" style="font-family: monospace; font-size:0.8rem;">
                                </div>
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">CMYK</label>
                                    <input type="text" class="bge-input col-cmyk" id="cmyk_val_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($col['cmyk'] ?? ''); ?>" placeholder="C:66 M:69 Y:0 K:10" style="font-family: monospace; font-size:0.8rem;">
                                </div>
                                <div>
                                    <label class="bge-label" style="font-size:0.7rem;">Pantone</label>
                                    <input type="text" class="bge-input col-pantone" value="<?php echo htmlspecialchars($col['pantone'] ?? ''); ?>" placeholder="PMS 286 C" style="font-size:0.8rem;">
                                </div>
                            </div>
                            <button type="button" class="btn-icon danger" onclick="removeColor(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bge-btn-add-item" onclick="addColorRow()">
                    <i class="ph-bold ph-plus-circle"></i> Agregar Color a la Paleta
                </button>
            </div>
        </div>

        <!-- ================= TAB 5: TIPOGRAFÍAS ================= -->
        <div class="bge-section-panel" id="tab-typography">
            <div class="bge-card-panel">
                <h2 class="bge-panel-title"><i class="ph-bold ph-text-t" style="color: #ec4899;"></i> Tipografías Corporativas</h2>
                <p class="bge-panel-desc">Define las fuentes principales y secundarias de la marca. Se integran automáticamente con Google Fonts.</p>

                <div id="fontsContainer">
                    <?php foreach ($fonts as $idx => $f): ?>
                        <div class="bge-color-item" id="font_row_<?php echo $idx; ?>" style="flex-direction: column; align-items: stretch; gap: 0.85rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight: 700; font-size: 0.95rem; color: #ec4899;">Tipografía #<?php echo $idx + 1; ?></span>
                                <button type="button" class="btn-icon danger" onclick="removeFont(<?php echo $idx; ?>)" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.15rem;">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem;">
                                <div>
                                    <label class="bge-label">Familia Tipográfica</label>
                                    <input type="text" class="bge-input fnt-name" value="<?php echo htmlspecialchars($f['name'] ?? 'Inter'); ?>" placeholder="Ej: Inter, Montserrat, Poppins, Outfit">
                                </div>
                                <div>
                                    <label class="bge-label">Jerarquía / Rol</label>
                                    <input type="text" class="bge-input fnt-role" value="<?php echo htmlspecialchars($f['role'] ?? 'Titulares'); ?>" placeholder="Ej: Titulares, Subtítulos, Párrafos">
                                </div>
                                <div>
                                    <label class="bge-label">Pesos Utilizados</label>
                                    <input type="text" class="bge-input fnt-weights" value="<?php echo htmlspecialchars($f['weights'] ?? '400, 600, 700'); ?>" placeholder="Ej: Regular 400, Bold 700">
                                </div>
                            </div>

                            <div>
                                <label class="bge-label">Recomendaciones de Uso</label>
                                <input type="text" class="bge-input fnt-usage" value="<?php echo htmlspecialchars($f['usage'] ?? ''); ?>" placeholder="Ej: Utilizar exclusivamente en mayúsculas para titulares de impacto...">
                            </div>

                            <!-- Live specimen box -->
                            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; font-family: '<?php echo htmlspecialchars($f['name'] ?? 'Inter'); ?>', sans-serif;">
                                <div style="font-size: 1.4rem; font-weight: 700; margin-bottom: 0.35rem;">Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj 1234567890</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">The quick brown fox jumps over the lazy dog. 0123456789 (Á, É, Í, Ó, Ú, Ñ)</div>
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
                                    <img src="<?php echo htmlspecialchars($app['image_url']); ?>" style="max-width:100%; max-height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.8rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; display:grid; grid-template-columns: 1fr 1.5fr; gap: 0.75rem;">
                                <input type="text" class="bge-input app-title" value="<?php echo htmlspecialchars($app['title'] ?? ''); ?>" placeholder="Título (ej. Tarjetas de Presentación)">
                                <input type="text" class="bge-input app-desc" value="<?php echo htmlspecialchars($app['desc'] ?? ''); ?>" placeholder="Descripción de la aplicación">
                                <input type="hidden" class="app-url" value="<?php echo htmlspecialchars($app['image_url'] ?? ''); ?>">
                            </div>
                            <input type="file" name="application_file_<?php echo $idx; ?>" accept="image/*" style="max-width: 140px; font-size: 0.78rem;">
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
                            <input type="text" name="slug" id="bgeSlug" class="bge-input" required 
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
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Switch tabs smoothly
function switchBgeTab(tabId, btn) {
    document.querySelectorAll('.bge-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.bge-section-panel').forEach(p => p.classList.remove('active'));

    btn.classList.add('active');
    const panel = document.getElementById('tab-' + tabId);
    if (panel) {
        panel.classList.add('active');
    }
}

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
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ---------------- COLOR BUILDER LOGIC ----------------
let colorCounter = <?php echo count($colors) + 10; ?>;

function addColorRow() {
    colorCounter++;
    const container = document.getElementById('colorsContainer');
    const newIdx = colorCounter;
    const defaultHex = '#2563EB';

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'color_row_' + newIdx;
    row.innerHTML = `
        <div class="bge-color-swatch-picker" style="background: ${defaultHex};" id="swatch_box_${newIdx}">
            <input type="color" value="${defaultHex}" onchange="updateColorValues(${newIdx}, this.value)">
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1.4fr 1.1fr 1fr 1.1fr 1.1fr 1fr; gap: 0.6rem; align-items:center;">
            <div>
                <label class="bge-label" style="font-size:0.7rem;">Nombre Color</label>
                <input type="text" class="bge-input col-name" value="Nuevo Color" placeholder="Nombre">
            </div>
            <div>
                <label class="bge-label" style="font-size:0.7rem;">Rol / Uso</label>
                <select class="bge-select col-role" style="padding: 0.55rem 0.65rem; font-size: 0.85rem;">
                    <option value="Primario">Primario</option>
                    <option value="Secundario">Secundario</option>
                    <option value="Acento" selected>Acento</option>
                    <option value="Fondo">Fondo</option>
                    <option value="Texto">Texto</option>
                </select>
            </div>
            <div>
                <label class="bge-label" style="font-size:0.7rem;">HEX</label>
                <input type="text" class="bge-input col-hex" id="hex_val_${newIdx}" value="${defaultHex}" onchange="updateColorFromHex(${newIdx}, this.value)" style="font-family: monospace; font-weight:700;">
            </div>
            <div>
                <label class="bge-label" style="font-size:0.7rem;">RGB</label>
                <input type="text" class="bge-input col-rgb" id="rgb_val_${newIdx}" value="37, 99, 235" style="font-family: monospace; font-size:0.8rem;">
            </div>
            <div>
                <label class="bge-label" style="font-size:0.7rem;">CMYK</label>
                <input type="text" class="bge-input col-cmyk" id="cmyk_val_${newIdx}" value="C:84 M:58 Y:0 K:8" style="font-family: monospace; font-size:0.8rem;">
            </div>
            <div>
                <label class="bge-label" style="font-size:0.7rem;">Pantone</label>
                <input type="text" class="bge-input col-pantone" placeholder="PMS 2174 C" style="font-size:0.8rem;">
            </div>
        </div>
        <button type="button" class="btn-icon danger" onclick="removeColor(${newIdx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.25rem;">
            <i class="ph-bold ph-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function removeColor(idx) {
    const row = document.getElementById('color_row_' + idx);
    if (row) row.remove();
}

function updateColorValues(idx, hex) {
    hex = hex.toUpperCase();
    document.getElementById('hex_val_' + idx).value = hex;
    document.getElementById('swatch_box_' + idx).style.background = hex;

    // Calculate RGB
    const r = parseInt(hex.slice(1, 3), 16) || 0;
    const g = parseInt(hex.slice(3, 5), 16) || 0;
    const b = parseInt(hex.slice(5, 7), 16) || 0;
    document.getElementById('rgb_val_' + idx).value = `${r}, ${g}, ${b}`;

    // Approximate CMYK
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
    document.getElementById('cmyk_val_' + idx).value = `C:${c} M:${m} Y:${y} K:${kPercent}`;
}

function updateColorFromHex(idx, hex) {
    if (!hex.startsWith('#')) hex = '#' + hex;
    if (hex.length === 7) {
        updateColorValues(idx, hex);
    }
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
        <div style="width: 60px; height: 60px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0;">
            <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <input type="text" class="bge-input var-name" placeholder="Nombre (ej. Versión Monocromática)">
            <input type="text" class="bge-input var-desc" placeholder="Uso recomendado">
            <input type="hidden" class="var-url" value="">
        </div>
        <input type="file" name="variation_file_${idx}" accept="image/*,.svg" style="max-width: 140px; font-size: 0.78rem;">
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
        <div style="width: 50px; height: 50px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0;">
            <i class="ph-bold ph-app-window" style="color: var(--text-muted); font-size: 1.35rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <input type="text" class="bge-input ico-name" placeholder="Nombre (ej. App Icon iOS)">
            <input type="text" class="bge-input ico-desc" placeholder="Descripción de uso">
            <input type="hidden" class="ico-url" value="">
        </div>
        <input type="file" name="icon_file_${idx}" accept="image/*,.svg,.ico" style="max-width: 140px; font-size: 0.78rem;">
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

// ---------------- FONTS BUILDER ----------------
let fontCounter = <?php echo count($fonts) + 10; ?>;

function addFontRow() {
    fontCounter++;
    const container = document.getElementById('fontsContainer');
    const idx = fontCounter;

    const row = document.createElement('div');
    row.className = 'bge-color-item';
    row.id = 'font_row_' + idx;
    row.style = 'flex-direction: column; align-items: stretch; gap: 0.85rem;';
    row.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight: 700; font-size: 0.95rem; color: #ec4899;">Tipografía Adicional</span>
            <button type="button" class="btn-icon danger" onclick="removeFont(${idx})" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:1.15rem;">
                <i class="ph-bold ph-trash"></i>
            </button>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem;">
            <div>
                <label class="bge-label">Familia Tipográfica</label>
                <input type="text" class="bge-input fnt-name" value="Inter" placeholder="Familia">
            </div>
            <div>
                <label class="bge-label">Jerarquía / Rol</label>
                <input type="text" class="bge-input fnt-role" value="De Soporte" placeholder="Rol">
            </div>
            <div>
                <label class="bge-label">Pesos Utilizados</label>
                <input type="text" class="bge-input fnt-weights" value="400, 600" placeholder="Pesos">
            </div>
        </div>
        <div>
            <label class="bge-label">Recomendaciones de Uso</label>
            <input type="text" class="bge-input fnt-usage" placeholder="Aplicación recomendada...">
        </div>
    `;
    container.appendChild(row);
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
        <div style="width: 70px; height: 70px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; display:flex; align-items:center; justify-content:center; padding: 4px; flex-shrink: 0;">
            <i class="ph-bold ph-image" style="color: var(--text-muted); font-size: 1.8rem;"></i>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 1fr 1.5fr; gap: 0.75rem;">
            <input type="text" class="bge-input app-title" placeholder="Título (ej. Packaging)">
            <input type="text" class="bge-input app-desc" placeholder="Descripción de la aplicación">
            <input type="hidden" class="app-url" value="">
        </div>
        <input type="file" name="application_file_${idx}" accept="image/*" style="max-width: 140px; font-size: 0.78rem;">
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
    document.querySelectorAll('#fontsContainer .bge-color-item').forEach(el => {
        const name = el.querySelector('.fnt-name')?.value.trim();
        const role = el.querySelector('.fnt-role')?.value.trim();
        const weights = el.querySelector('.fnt-weights')?.value.trim();
        const usage = el.querySelector('.fnt-usage')?.value.trim();
        if (name) {
            fontItems.push({ name, role, weights, usage });
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
    formData.append('fonts_json', JSON.stringify(fontItems));
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
</script>

<?php require_once 'includes/footer.php'; ?>
