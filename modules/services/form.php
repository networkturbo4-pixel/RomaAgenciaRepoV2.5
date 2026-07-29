<?php
// modules/services/form.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$clone_id = isset($_GET['clone_id']) ? (int)$_GET['clone_id'] : 0;

$service = null;
$service_features = [];
$service_faqs = [];
$service_prereqs = [];
$service_packages = [];
$service_gallery = [];
$service_relations = [];
$service_addons = [];

$fetch_id = $service_id > 0 ? $service_id : ($clone_id > 0 ? $clone_id : 0);

if ($fetch_id > 0) {
    // Fetch service
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$fetch_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($service) {
        // Fetch features
        $stmt_f = $db->prepare("SELECT * FROM service_features WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_f->execute([$fetch_id]);
        $service_features = $stmt_f->fetchAll(PDO::FETCH_ASSOC);

        // Fetch faqs
        $stmt_faq = $db->prepare("SELECT * FROM service_faqs WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_faq->execute([$fetch_id]);
        $service_faqs = $stmt_faq->fetchAll(PDO::FETCH_ASSOC);

        // Fetch prerequisites
        $stmt_pre = $db->prepare("SELECT * FROM service_prerequisites WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_pre->execute([$fetch_id]);
        $service_prereqs = $stmt_pre->fetchAll(PDO::FETCH_ASSOC);

        // Fetch packages
        $stmt_pkg = $db->prepare("SELECT * FROM service_packages WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_pkg->execute([$fetch_id]);
        $service_packages = $stmt_pkg->fetchAll(PDO::FETCH_ASSOC);

        // Fetch gallery
        $stmt_gal = $db->prepare("SELECT * FROM service_gallery WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_gal->execute([$fetch_id]);
        $service_gallery = $stmt_gal->fetchAll(PDO::FETCH_ASSOC);

        // Fetch relations
        $stmt_rel = $db->prepare("SELECT related_service_id FROM service_relations WHERE service_id = ?");
        $stmt_rel->execute([$fetch_id]);
        $service_relations = $stmt_rel->fetchAll(PDO::FETCH_COLUMN);

        // Fetch addons
        $stmt_addon = $db->prepare("SELECT * FROM service_addons WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_addon->execute([$fetch_id]);
        $service_addons = $stmt_addon->fetchAll(PDO::FETCH_ASSOC);
        
        if ($clone_id > 0) {
            // We are cloning. Reset the ID and tweak the name.
            $service['id'] = 0;
            $service['name'] = $service['name'] . ' (Copia)';
            $service['cover_image'] = ''; // Don't copy the image file
            $service['slug'] = '';
            $service_gallery = []; // Don't copy gallery
        }
    } else {
        $service_id = 0; // Not found
        $clone_id = 0;
    }
}

// Fetch categories
$stmt_cat = $db->query("SELECT * FROM service_categories ORDER BY name ASC");
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Fetch all other services for cross-selling
$stmt_all_srv = $db->prepare("SELECT id, name FROM services WHERE deleted_at IS NULL AND id != ? ORDER BY name ASC");
$stmt_all_srv->execute([$service_id]);
$all_services = $stmt_all_srv->fetchAll(PDO::FETCH_ASSOC);

// Pass data to JS
$initial_data = [
    'id' => $service_id,
    'features' => array_values(array_filter($service_features, function($f) { return $f['type'] !== 'deliverable'; })),
    'deliverables' => array_values(array_filter($service_features, function($f) { return $f['type'] === 'deliverable'; })),
    'faqs' => $service_faqs,
    'prereqs' => $service_prereqs,
    'packages' => $service_packages,
    'gallery' => $service_gallery,
    'relations' => $service_relations,
    'addons' => $service_addons,
    'site_name' => $global_settings['site_name'] ?? 'Tu Agencia'
];

require_once 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
/* Modern App Form Styles */
.svc-container { max-width: 1300px; margin: 0 auto; padding-bottom: 3rem; }
.svc-header { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.svc-header-left { display: flex; align-items: center; gap: 1.25rem; }
.svc-icon-box { width: 56px; height: 56px; background: rgba(var(--primary-rgb, 37, 99, 235), 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-size: 1.75rem; }

/* 2-column layout for editing and preview */
.svc-layout { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; align-items: start; }
@media (max-width: 900px) { .svc-layout { grid-template-columns: 1fr; } }

.svc-panel { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02); }
.svc-panel-title { font-size: 1.1rem; font-weight: 700; color: var(--color-title); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); }

.svc-form-group { margin-bottom: 1.25rem; }
.svc-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-title); margin-bottom: 0.5rem; }
.svc-label .required { color: var(--color-danger); }
.svc-input { width: 100%; padding: 0.7rem 1rem; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.95rem; color: var(--color-title); background: var(--bg-body); transition: all 0.2s ease; font-family: inherit; }
.svc-input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 37, 99, 235), 0.1); background: var(--bg-surface); }
textarea.svc-input { resize: vertical; min-height: 80px; }

/* Tabs */
.svc-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
.svc-tab { padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text-muted); cursor: pointer; transition: all 0.2s; white-space: nowrap; border: none; background: none; }
.svc-tab:hover { background: var(--bg-body); color: var(--color-title); }
.svc-tab.active { background: rgba(var(--primary-rgb, 37, 99, 235), 0.1); color: var(--primary-color); }
.svc-tab-content { display: none; }
.svc-tab-content.active { display: block; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* List items (Sortable) */
.svc-add-box { background: var(--bg-body); border: 1.5px dashed var(--border-color); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
.svc-list-item { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: flex-start; transition: border-color 0.2s ease; cursor: grab; }
.svc-list-item:active { cursor: grabbing; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.svc-item-drag { color: var(--text-muted); padding-right: 0.75rem; cursor: grab; padding-top: 2px; }
.svc-item-title { font-weight: 600; font-size: 0.95rem; color: var(--color-title); margin-bottom: 0.25rem; }
.svc-item-desc { font-size: 0.85rem; color: var(--text-muted); }

/* Image upload preview */
.cover-upload-area { position: relative; width: 100%; height: 200px; border: 2px dashed var(--border-color); border-radius: var(--radius-lg); overflow: hidden; background: var(--bg-body); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: border-color 0.2s ease; }
.cover-upload-area:hover { border-color: var(--primary-color); }
.cover-preview { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; display: none; }
.cover-placeholder { z-index: 2; text-align: center; pointer-events: none; }
.cover-remove { position: absolute; top: 0.5rem; right: 0.5rem; z-index: 3; display: none; background: rgba(0,0,0,0.5); color: white; border-radius: 50%; padding: 4px; }
.file-input-hidden { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 4; }

/* Gallery */
.gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem; margin-top: 1rem; }
.gallery-item { position: relative; width: 100%; padding-top: 100%; border-radius: var(--radius-lg); overflow: hidden; background: var(--bg-body); border: 1px solid var(--border-color); cursor: grab; }
.gallery-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
.gallery-item .remove-btn { position: absolute; top: 4px; right: 4px; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.75rem; z-index: 10; opacity: 0; transition: opacity 0.2s; }
.gallery-item:hover .remove-btn { opacity: 1; }
.gallery-upload-btn { width: 100%; padding-top: 100%; position: relative; border: 2px dashed var(--border-color); border-radius: var(--radius-lg); background: var(--bg-body); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: border-color 0.2s; }
.gallery-upload-btn:hover { border-color: var(--primary-color); }
.gallery-upload-btn .content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: var(--text-muted); width: 100%; }

/* Live Preview Card */
.preview-sticky { position: sticky; top: 2rem; }
.service-card-preview { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08); display: flex; flex-direction: column; }
.preview-cover { width: 100%; height: 160px; background-color: var(--bg-body); background-size: cover; background-position: center; position: relative; border-bottom: 1px solid var(--border-color); }
.preview-cover-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 2.5rem; opacity: 0.5; }
.preview-badge { position: absolute; top: 1rem; right: 1rem; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(34, 197, 94, 0.9); color: white; backdrop-filter: blur(4px); }
.preview-content { padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1; }
.preview-title { font-size: 1.15rem; font-weight: 700; color: var(--color-title); margin: 0 0 0.75rem 0; line-height: 1.3; }
.preview-category { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; margin-bottom: 1rem; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-muted); }
.preview-desc { font-size: 0.9rem; color: var(--color-text); margin-bottom: 1.5rem; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 40px; }
.preview-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-color); }
.preview-price { font-size: 1.2rem; font-weight: 700; color: var(--primary-color); display: flex; flex-direction: column; }
.preview-price-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
.preview-disclaimer { text-align: center; font-size: 0.8rem; color: var(--text-muted); margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

/* SEO Preview */
.seo-preview { background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #dfe1e5; font-family: arial, sans-serif; }
.seo-preview-url { font-size: 0.875rem; color: #202124; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.seo-preview-title { font-size: 1.25rem; color: #1a0dab; margin-bottom: 4px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.seo-preview-desc { font-size: 0.875rem; color: #4d5156; line-height: 1.58; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Multiselect / Tags */
.svc-relations-select { width: 100%; height: 120px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-body); color: var(--color-title); font-family: inherit; }
.svc-relations-select option { padding: 0.5rem; border-radius: 4px; margin-bottom: 2px; }
</style>

<div class="svc-container">
    <div class="svc-header">
        <div class="svc-header-left">
            <div class="svc-icon-box">
                <i class="ph <?php echo $service_id ? 'ph-pencil-simple' : ($clone_id ? 'ph-copy' : 'ph-plus'); ?>"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">
                    <?php echo $service_id ? 'Editar Servicio' : ($clone_id ? 'Duplicar Servicio' : 'Nuevo Servicio'); ?>
                </h1>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    Configura todos los detalles de este servicio.
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php?module=services" class="btn btn-outline" style="text-decoration: none;">Cancelar</a>
            <button class="btn btn-primary" id="btnSaveService" onclick="ServiceFormModule.saveService()">
                <i class="ph ph-check"></i> Guardar Servicio
            </button>
        </div>
    </div>

    <form id="serviceForm" onsubmit="return false;" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="service_id" id="service_id" value="<?php echo $service_id; ?>">
        
        <div class="svc-layout">
            <!-- Left Column: Form with Tabs -->
            <div style="min-width: 0;">
                <div class="svc-tabs">
                    <button type="button" class="svc-tab active" onclick="ServiceFormModule.switchTab('tab-general')"><i class="ph ph-info"></i> Info General</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-gallery')"><i class="ph ph-images"></i> Galería</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-features')"><i class="ph ph-list-checks"></i> Contenido & Entregables</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-prereqs')"><i class="ph ph-clipboard-text"></i> Requisitos</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-faqs')"><i class="ph ph-question"></i> FAQs</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-addons')"><i class="ph ph-plus-circle"></i> Extras</button>
                    <button type="button" class="svc-tab" onclick="ServiceFormModule.switchTab('tab-seo')"><i class="ph ph-magnifying-glass"></i> SEO & Relacionados</button>
                </div>

                <!-- TAB: General Info -->
                <div id="tab-general" class="svc-tab-content active">
                    <div class="svc-panel" style="margin-bottom: 1.5rem;">
                        <div class="svc-panel-title">
                            <i class="ph ph-image"></i> Portada del Servicio
                        </div>
                        <div class="cover-upload-area" id="coverUploadArea">
                            <?php if($service && !empty($service['cover_image'])): ?>
                                <img src="uploads/services/<?php echo htmlspecialchars($service['cover_image']); ?>" id="coverPreview" class="cover-preview" style="display: block;">
                                <button type="button" class="btn-icon cover-remove" id="btnRemoveCover" onclick="ServiceFormModule.removeCoverImage()" style="display: block;"><i class="ph ph-x"></i></button>
                                <input type="hidden" name="existing_cover" id="existing_cover" value="<?php echo htmlspecialchars($service['cover_image']); ?>">
                            <?php else: ?>
                                <img src="" id="coverPreview" class="cover-preview">
                                <button type="button" class="btn-icon cover-remove" id="btnRemoveCover" onclick="ServiceFormModule.removeCoverImage()"><i class="ph ph-x"></i></button>
                                <input type="hidden" name="existing_cover" id="existing_cover" value="">
                            <?php endif; ?>
                            
                            <div class="cover-placeholder" id="coverPlaceholder" <?php echo ($service && !empty($service['cover_image'])) ? 'style="display:none;"' : ''; ?>>
                                <i class="ph ph-upload-simple" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--color-title);">Sube una imagen de portada principal</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Recomendado: 800x400px (JPG, PNG)</div>
                            </div>
                            <input type="file" name="cover_file" id="cover_file" class="file-input-hidden" accept="image/*" onchange="ServiceFormModule.handleCoverUpload(this)">
                        </div>
                    </div>

                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-info"></i> Información General
                        </div>
                        
                        <div class="svc-form-group">
                            <label class="svc-label">Título del Servicio <span class="required">*</span></label>
                            <input type="text" class="svc-input" name="name" id="service_name" placeholder="Ej: Consultoría IT Empresarial" value="<?php echo htmlspecialchars($service['name'] ?? ''); ?>" required oninput="ServiceFormModule.updatePreview()">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="svc-form-group">
                                <label class="svc-label">Categoría <span class="required">*</span></label>
                                <select class="svc-input" name="category_id" id="service_category" required onchange="ServiceFormModule.updatePreview()">
                                    <option value="">Selecciona...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" data-color="<?php echo htmlspecialchars($cat['color_tag'] ?? '#6b7280'); ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($service && $service['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="svc-form-group">
                                <label class="svc-label">Estado</label>
                                <select class="svc-input" name="status" id="service_status" onchange="ServiceFormModule.updatePreview()">
                                    <option value="active" <?php echo ($service && ($service['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Activo</option>
                                    <option value="paused" <?php echo ($service && ($service['status'] ?? '') === 'paused') ? 'selected' : ''; ?>>Pausado</option>
                                    <option value="out_of_stock" <?php echo ($service && ($service['status'] ?? '') === 'out_of_stock') ? 'selected' : ''; ?>>Agotado</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="svc-form-group">
                                <label class="svc-label">Icono (Clase CSS Phosphor. Ej: ph-rocket)</label>
                                <input type="text" class="svc-input" name="icon" id="service_icon" placeholder="Ej: ph-paint-brush" value="<?php echo htmlspecialchars($service['icon'] ?? ''); ?>">
                            </div>
                            <div class="svc-form-group">
                                <label class="svc-label">Insignia (Badge. Ej: Más Vendido)</label>
                                <input type="text" class="svc-input" name="badge" id="service_badge" placeholder="Ej: 🔥 Top Seller" value="<?php echo htmlspecialchars($service['badge'] ?? ''); ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div class="svc-form-group">
                                <label class="svc-label">Tipo de Precio</label>
                                <select class="svc-input" name="price_type" id="price_type" onchange="ServiceFormModule.handlePriceTypeChange()">
                                    <option value="fixed" <?php echo ($service && ($service['price_type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Precio Fijo</option>
                                    <option value="from" <?php echo ($service && ($service['price_type'] ?? '') === 'from') ? 'selected' : ''; ?>>Precio "Desde" (Variable)</option>
                                    <option value="monthly" <?php echo ($service && ($service['price_type'] ?? '') === 'monthly') ? 'selected' : ''; ?>>Pago Mensual (Recurrente)</option>
                                    <option value="packages" <?php echo ($service && ($service['price_type'] ?? '') === 'packages') ? 'selected' : ''; ?>>Múltiples Paquetes (Tiers)</option>
                                </select>
                            </div>
                            <div class="svc-form-group">
                                <label class="svc-label">Moneda</label>
                                <select class="svc-input" name="currency" id="currency" onchange="ServiceFormModule.updatePreview()">
                                    <option value="USD" <?php echo ($service && ($service['currency'] ?? 'USD') === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="PEN" <?php echo ($service && ($service['currency'] ?? '') === 'PEN') ? 'selected' : ''; ?>>PEN (S/)</option>
                                    <option value="EUR" <?php echo ($service && ($service['currency'] ?? '') === 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                                </select>
                            </div>
                            <div class="svc-form-group" id="group_visibility">
                                <label class="svc-label">Visibilidad</label>
                                <select class="svc-input" name="visibility" id="visibility">
                                    <option value="public" <?php echo ($service && ($service['visibility'] ?? '') === 'public') ? 'selected' : ''; ?>>Público</option>
                                    <option value="private" <?php echo ($service && ($service['visibility'] ?? '') === 'private') ? 'selected' : ''; ?>>Privado / Oculto</option>
                                </select>
                            </div>
                        </div>

                        <!-- Single Price Fields -->
                        <div id="singlePriceFields" style="<?php echo ($service && ($service['price_type'] ?? '') === 'packages') ? 'display:none;' : 'display:block;'; ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="svc-form-group">
                                    <label class="svc-label">Precio Estimado</label>
                                    <input type="number" step="0.01" class="svc-input" name="price" id="service_price" placeholder="0.00" value="<?php echo $service ? htmlspecialchars($service['price']) : '0.00'; ?>" oninput="ServiceFormModule.updatePreview()">
                                </div>
                                <div class="svc-form-group">
                                    <label class="svc-label">Descuento Anual (%)</label>
                                    <input type="number" step="0.01" class="svc-input" name="annual_discount_percent" id="annual_discount_percent" placeholder="Ej: 15" value="<?php echo htmlspecialchars($service['annual_discount_percent'] ?? ''); ?>">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                                <div class="svc-form-group">
                                    <label class="svc-label">Tiempo de Entrega (Único)</label>
                                    <input type="text" class="svc-input" name="delivery_time" id="delivery_time" placeholder="Ej: 5 días hábiles" value="<?php echo htmlspecialchars($service['delivery_time'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Packages Price Fields -->
                        <div id="packagesPriceFields" style="<?php echo ($service && ($service['price_type'] ?? '') === 'packages') ? 'display:block;' : 'display:none;'; ?> border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
                            <label class="svc-label" style="margin-bottom: 1rem;">Paquetes Disponibles</label>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Define diferentes niveles (ej: Básico, Estándar, Premium).</p>
                            
                            <div id="packagesList">
                                <!-- Filled by JS -->
                            </div>
                            
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addPackage()" style="width: 100%; justify-content: center; margin-top: 0.5rem;">
                                <i class="ph ph-plus"></i> Añadir Paquete
                            </button>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="svc-form-group" style="margin-bottom: 0;">
                                <label class="svc-label">Inventario (Cupos al mes)</label>
                                <input type="number" class="svc-input" name="stock_limit" id="stock_limit" placeholder="Ej: 5 (Dejar vacío para ilimitado)" value="<?php echo htmlspecialchars($service['stock_limit'] ?? ''); ?>">
                            </div>
                            <div class="svc-form-group" style="margin-bottom: 0;">
                                <label class="svc-label">Contador de Urgencia (Fecha Límite)</label>
                                <input type="datetime-local" class="svc-input" name="countdown_end" id="countdown_end" value="<?php echo isset($service['countdown_end']) && $service['countdown_end'] ? date('Y-m-d\TH:i', strtotime($service['countdown_end'])) : ''; ?>">
                            </div>
                        </div>

                        <div class="svc-form-group">
                            <label class="svc-label">Video de Presentación (URL de YouTube / Vimeo)</label>
                            <div style="position: relative;">
                                <i class="ph ph-video-camera" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" class="svc-input" name="video_url" id="video_url" placeholder="Ej: https://youtube.com/watch?v=..." value="<?php echo htmlspecialchars($service['video_url'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="svc-form-group">
                            <label class="svc-label">Descripción Breve del Servicio</label>
                            <textarea class="svc-input" name="description" id="service_description" rows="4" placeholder="Aparecerá en la tarjeta del servicio..." oninput="ServiceFormModule.updatePreview()"><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="svc-panel" style="margin-top: 1.5rem; background: var(--bg-body); padding: 1.25rem;">
                            <label style="display:flex; align-items:center; gap: 0.5rem; font-weight: 600; cursor: pointer; color: var(--color-title);">
                                <input type="checkbox" name="is_combo" id="is_combo" value="1" <?php echo ($service && !empty($service['is_combo'])) ? 'checked' : ''; ?> onchange="ServiceFormModule.toggleCombo()">
                                ¿Este servicio es un Combo / Super Pack?
                            </label>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Agrupa múltiples servicios existentes en uno solo. Los entregables de este servicio serán reemplazados visualmente por los servicios seleccionados.</p>
                            
                            <div id="combo_selection" style="margin-top: 1rem; <?php echo ($service && !empty($service['is_combo'])) ? 'display:block;' : 'display:none;'; ?>">
                                <label class="svc-label">Selecciona los servicios incluidos en el combo</label>
                                <select name="combo_ids[]" id="combo_ids" class="svc-relations-select" multiple>
                                    <?php 
                                    $combo_ids = [];
                                    if ($service && !empty($service['combo_ids'])) {
                                        $combo_ids = json_decode($service['combo_ids'], true) ?: [];
                                    }
                                    foreach($all_services as $as): ?>
                                        <option value="<?php echo $as['id']; ?>" <?php echo in_array($as['id'], $combo_ids) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($as['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Mantén presionada la tecla Ctrl (Windows) o Cmd (Mac) para seleccionar varios.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Gallery -->
                <div id="tab-gallery" class="svc-tab-content">
                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-images"></i> Galería de Imágenes (Portafolio)
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Sube más imágenes para mostrar ejemplos de tu trabajo o capturas adicionales. <br>💡 <b>Tip:</b> Arrástralas para ordenar la galería.</p>
                        
                        <div class="gallery-grid" id="galleryGrid">
                            <!-- Filled by JS -->
                            
                            <!-- Upload Button always at the end -->
                            <div class="gallery-upload-btn" onclick="document.getElementById('gallery_files').click()">
                                <div class="content">
                                    <i class="ph ph-plus-circle" style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;"></i>
                                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-title);">Añadir Fotos</div>
                                </div>
                            </div>
                            <input type="file" id="gallery_files" multiple accept="image/*" style="display: none;" onchange="ServiceFormModule.handleGalleryUpload(this)">
                        </div>

                        <div class="svc-form-group" style="margin-top: 1.5rem;">
                            <label class="svc-label">Añadir Elemento a la Galería (Video, Web, PDF)</label>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <div style="display: flex; gap: 0.5rem;">
                                    <select id="gallery_media_type" class="svc-input" style="width: 200px;" onchange="ServiceFormModule.toggleGalleryMediaType()">
                                        <option value="video">Video (URL)</option>
                                        <option value="web">Enlace Web (URL)</option>
                                        <option value="pdf">Documento PDF</option>
                                    </select>
                                    
                                    <!-- URL Input for Video & Web -->
                                    <input type="text" id="gallery_media_url" class="svc-input" placeholder="https://..." style="flex: 1;">
                                    
                                    <!-- File Input for PDF -->
                                    <input type="file" id="gallery_media_file" class="svc-input" accept="application/pdf" style="flex: 1; display: none;">
                                    
                                    <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addMediaToGallery()" style="white-space: nowrap;">Añadir</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Features & Deliverables -->
                <div id="tab-features" class="svc-tab-content">
                    <div class="svc-panel" style="margin-bottom: 1.5rem;">
                        <div class="svc-panel-title">
                            <i class="ph ph-list-checks"></i> Lo que Incluye (Características)
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Detalla los beneficios o características principales. <br>💡 <b>Tip:</b> Arrastra los elementos para reordenarlos.</p>
                        
                        <div class="svc-add-box">
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <input type="text" class="svc-input" id="feature_title" placeholder="Título. Ej: Diseño de Logotipo">
                            </div>
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <textarea class="svc-input" id="feature_desc" rows="2" placeholder="Descripción breve..."></textarea>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addFeature()" style="width: 100%; justify-content: center;">
                                <i class="ph ph-plus"></i> Añadir Característica
                            </button>
                        </div>

                        <div id="featuresList">
                            <!-- Filled by JS -->
                        </div>
                    </div>

                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-package"></i> Entregables Finales
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Lo que el cliente recibirá físicamente o digitalmente.</p>
                        
                        <div class="svc-add-box">
                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                                <div class="svc-form-group" style="margin-bottom: 0;">
                                    <input type="text" class="svc-input" id="deliverable_title" placeholder="Título. Ej: Manual de Marca PDF">
                                </div>
                                <div class="svc-form-group" style="margin-bottom: 0;">
                                    <input type="text" class="svc-input" id="deliverable_stage" placeholder="Fase (Ej: Fase 1)">
                                </div>
                            </div>
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <textarea class="svc-input" id="deliverable_desc" rows="2" placeholder="Detalles sobre este entregable..."></textarea>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addDeliverable()" style="width: 100%; justify-content: center;">
                                <i class="ph ph-plus"></i> Añadir Entregable
                            </button>
                        </div>

                        <div id="deliverablesList">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                </div>

                <!-- TAB: Prerequisites -->
                <div id="tab-prereqs" class="svc-tab-content">
                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-clipboard-text"></i> Requisitos Previos
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">¿Qué necesitas que el cliente proporcione antes de empezar el servicio?</p>
                        
                        <div class="svc-add-box">
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <input type="text" class="svc-input" id="prereq_title" placeholder="Ej: Accesos al servidor web">
                            </div>
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <textarea class="svc-input" id="prereq_desc" rows="2" placeholder="Instrucciones para enviar esto..."></textarea>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addPrereq()" style="width: 100%; justify-content: center;">
                                <i class="ph ph-plus"></i> Añadir Requisito
                            </button>
                        </div>

                        <div id="prereqsList">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                </div>

                <!-- TAB: FAQs -->
                <div id="tab-faqs" class="svc-tab-content">
                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-question"></i> Preguntas Frecuentes
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Anticipa las dudas comunes de tus clientes sobre este servicio.</p>
                        
                        <div class="svc-add-box">
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <input type="text" class="svc-input" id="faq_q" placeholder="Pregunta. Ej: ¿Incluye soporte técnico?">
                            </div>
                            <div class="svc-form-group" style="margin-bottom: 0.75rem;">
                                <textarea class="svc-input" id="faq_a" rows="3" placeholder="Respuesta detallada..."></textarea>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addFAQ()" style="width: 100%; justify-content: center;">
                                <i class="ph ph-plus"></i> Añadir Pregunta
                            </button>
                        </div>

                        <div id="faqsList">
                            <!-- Filled by JS -->
                        </div>
                    </div>
                </div>

                <!-- TAB: Addons (Extras) -->
                <div id="tab-addons" class="svc-tab-content">
                    <div class="svc-panel">
                        <div class="svc-panel-title" style="justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph ph-plus-circle"></i> Complementos Opcionales (Extras)
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_addons" id="has_addons" value="1" <?php echo ($service && !empty($service['has_addons'])) ? 'checked' : ''; ?> onchange="ServiceFormModule.toggleAddons()">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div id="addonsContainer" style="<?php echo ($service && !empty($service['has_addons'])) ? '' : 'display:none;'; ?>">
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                Crea servicios adicionales que el cliente puede sumar a su carrito (Ej: Diseño de post extra, Edición de Reels).
                            </p>
                            
                            <div id="addonsList" style="margin-bottom: 1rem;">
                                <!-- Rendered via JS -->
                            </div>
                            
                            <button type="button" class="btn btn-outline" onclick="ServiceFormModule.addAddon()" style="width: 100%; border-style: dashed;">
                                <i class="ph ph-plus"></i> Añadir Complemento Extra
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB: SEO & Related -->
                <div id="tab-seo" class="svc-tab-content">
                    <div class="svc-panel" style="margin-bottom: 1.5rem;">
                        <div class="svc-panel-title">
                            <i class="ph ph-magnifying-glass"></i> Optimización SEO
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">Configura cómo aparecerá este servicio en los resultados de Google.</p>
                        
                        <div class="svc-form-group">
                            <label class="svc-label">URL Slug (Amigable)</label>
                            <input type="text" class="svc-input" name="slug" id="slug" placeholder="ej-consultoria-empresarial" value="<?php echo htmlspecialchars($service['slug'] ?? ''); ?>" oninput="ServiceFormModule.updateSEOPreview()">
                        </div>

                        <div class="svc-form-group">
                            <label class="svc-label">Meta Título (máx. 60 caracteres)</label>
                            <input type="text" class="svc-input" name="meta_title" id="meta_title" placeholder="Título SEO..." value="<?php echo htmlspecialchars($service['meta_title'] ?? ''); ?>" oninput="ServiceFormModule.updateSEOPreview()">
                        </div>

                        <div class="svc-form-group">
                            <label class="svc-label">Meta Descripción (máx. 160 caracteres)</label>
                            <textarea class="svc-input" name="meta_description" id="meta_description" rows="3" placeholder="Descripción corta para Google..." oninput="ServiceFormModule.updateSEOPreview()"><?php echo htmlspecialchars($service['meta_description'] ?? ''); ?></textarea>
                        </div>

                        <div class="svc-form-group">
                            <label class="svc-label">Imagen para Redes Sociales (OG Image) <span style="font-weight:normal;color:var(--text-muted);">(Recomendado: 1200x630px)</span></label>
                            <?php if($service && !empty($service['og_image'])): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <img src="uploads/services/<?php echo htmlspecialchars($service['og_image']); ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid var(--border-color);">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="svc-input" name="og_image_file" id="og_image_file" accept="image/*">
                            <input type="hidden" name="existing_og_image" value="<?php echo htmlspecialchars($service['og_image'] ?? ''); ?>">
                        </div>

                        <label class="svc-label" style="margin-top: 1rem;">Previsualización en Google:</label>
                        <div class="seo-preview">
                            <div class="seo-preview-url">https://<?php echo htmlspecialchars(strtolower(str_replace(' ', '', $global_settings['site_name'] ?? 'tuagencia'))); ?>.com/servicios/<span id="seoPreviewSlug">slug-del-servicio</span></div>
                            <div class="seo-preview-title" id="seoPreviewTitle">Título del Servicio | <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Tu Agencia'); ?></div>
                            <div class="seo-preview-desc" id="seoPreviewDesc">Esta es una vista previa de cómo se verá la descripción de tu servicio en los resultados de búsqueda de Google. Asegúrate de que sea atractiva.</div>
                        </div>
                    </div>

                    <div class="svc-panel">
                        <div class="svc-panel-title">
                            <i class="ph ph-link"></i> Servicios Relacionados (Cross-selling)
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Selecciona otros servicios que podrían interesar al cliente para mostrarlos al final de la página de este servicio (Ctrl+Click para seleccionar múltiples).</p>
                        
                        <div class="svc-form-group">
                            <select class="svc-relations-select" name="related_services[]" multiple>
                                <?php foreach ($all_services as $srv): ?>
                                    <option value="<?php echo $srv['id']; ?>" <?php echo in_array($srv['id'], $service_relations) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($srv['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Live Preview Sticky -->
            <div>
                <div class="preview-sticky">
                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted); margin-top: 0; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="ph ph-eye"></i> Previsualización en Vivo</h4>
                    
                    <div class="service-card-preview" id="livePreviewCard">
                        <div class="preview-cover" id="prevCover">
                            <div class="preview-cover-placeholder" id="prevCoverPlaceholder"><i class="ph ph-image"></i></div>
                            <span class="preview-badge" id="prevBadge">Activo</span>
                        </div>

                        <div class="preview-content">
                            <h3 class="preview-title" id="prevTitle">Título del Servicio</h3>
                            
                            <div>
                                <span class="preview-category" id="prevCategory">
                                    <i class="ph ph-folder"></i> <span id="prevCatName">Sin categoría</span>
                                </span>
                            </div>

                            <div class="preview-desc" id="prevDesc">
                                <span style="color: var(--text-muted); font-style: italic;">Sin descripción</span>
                            </div>

                            <div class="preview-footer">
                                <div class="preview-price">
                                    <span class="preview-price-label" id="prevPriceLabel" style="display: none;">Desde</span>
                                    <div>
                                        <span id="prevCurrency"><?php echo htmlspecialchars($global_settings['currency'] ?? 'USD'); ?></span> <span id="prevPrice">0.00</span>
                                    </div>
                                </div>
                                <div style="color: var(--primary-color);">
                                    <i class="ph ph-arrow-right" style="font-size: 1.25rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-disclaimer">
                        <i class="ph ph-info"></i> Así se verá la tarjeta en el catálogo
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const INITIAL_SERVICE_DATA = <?php echo json_encode($initial_data); ?>;
</script>
<script src="assets/js/modules/service_form.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
