<?php
// modules/services/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all service categories
$stmt_cat = $db->query("SELECT * FROM service_categories ORDER BY name ASC");
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services
$stmt_serv = $db->query("
    SELECT s.*, c.name as category_name
    FROM services s
    LEFT JOIN service_categories c ON s.category_id = c.id
    ORDER BY s.created_at DESC
");
$services = $stmt_serv->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-briefcase" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Servicios</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona los servicios y sus categorías.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <button class="btn btn-outline" onclick="ServiceModule.openCategoryModal()" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-folder-plus"></i> Nueva Categoría
        </button>
        <button class="btn btn-primary" onclick="ServiceModule.openServiceModal()" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-plus"></i> Nuevo Servicio
        </button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>SERVICIO</th>
                    <th>CATEGORÍA</th>
                    <th>PRECIO</th>
                    <th style="text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 2rem; color: var(--color-text);">No hay servicios registrados.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                    <tr id="service-row-<?php echo $service['id']; ?>">
                        <td data-label="SERVICIO">
                            <div style="font-weight: 500; color: var(--color-title);" id="service-name-<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></div>
                            <div style="font-size: 0.875rem; color: var(--color-text); margin-top: 0.25rem;" id="service-desc-<?php echo $service['id']; ?>">
                                <?php echo $service['description'] ? htmlspecialchars($service['description']) : ''; ?>
                            </div>
                        </td>
                        <td data-label="CATEGORÍA">
                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--bg-body); border-radius: 4px; font-size: 0.75rem; border: 1px solid var(--border-color);">
                                <i class="ph ph-folder" style="color: var(--color-text);"></i>
                                <span id="service-cat-<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['category_name'] ?? 'Sin categoría'); ?></span>
                            </span>
                        </td>
                        <td data-label="PRECIO">
                            <div style="font-weight: 600; color: var(--primary-color);" id="service-price-<?php echo $service['id']; ?>">
                                <?php 
                                    $currency = $global_settings['currency'] ?? 'USD';
                                    echo htmlspecialchars($currency) . ' ' . number_format($service['price'], 2); 
                                ?>
                            </div>
                        </td>
                        <td data-label="ACCIONES" style="text-align: right;">
                            <div class="action-buttons" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                <button class="btn-icon" onclick="ServiceModule.editService(<?php echo $service['id']; ?>)" title="Editar">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button class="btn-icon" onclick="ServiceModule.deleteService(<?php echo $service['id']; ?>)" title="Eliminar" style="color: var(--color-danger);">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Servicio -->
<style>
    /* Service Modal Two-Column Layout */
    #serviceModal .modal-content {
        max-width: 1200px;
        width: 92vw;
    }
    #serviceModal .svc-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 700px) {
        #serviceModal .svc-modal-grid {
            grid-template-columns: 1fr;
        }
    }
    /* Soft gray bordered inputs for the service modal */
    #serviceModal .svc-input {
        width: 100%;
        padding: 0.6rem 0.85rem;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9rem;
        color: var(--color-title);
        background: var(--bg-surface);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        font-family: inherit;
    }
    #serviceModal .svc-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 37, 99, 235), 0.1);
    }
    #serviceModal .svc-input::placeholder {
        color: #9ca3af;
    }
    #serviceModal select.svc-input {
        cursor: pointer;
        appearance: auto;
    }
    #serviceModal textarea.svc-input {
        resize: vertical;
        min-height: 50px;
    }
    #serviceModal .svc-field-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--color-title);
        margin-bottom: 0.4rem;
        letter-spacing: 0.2px;
    }
    #serviceModal .svc-field-label .required {
        color: #ef4444;
        margin-left: 2px;
    }
    #serviceModal .svc-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    #serviceModal .svc-column {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    /* Feature/Deliverable add section */
    #serviceModal .svc-add-section {
        padding: 0.85rem;
        border: 1.5px dashed #d1d5db;
        border-radius: 10px;
        background: var(--bg-body, #f9fafb);
        margin-top: 0.5rem;
    }
    #serviceModal .svc-add-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.9rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1.5px solid var(--primary-color);
        background: var(--primary-color);
        color: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    #serviceModal .svc-add-btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    /* Feature items list */
    #serviceModal .svc-feature-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 0.65rem 0.85rem;
        background: var(--bg-body, #f9fafb);
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        transition: border-color 0.2s ease;
    }
    #serviceModal .svc-feature-item:hover {
        border-color: #c7c9ce;
    }
    /* Dark mode overrides */
    [data-theme="dark"] #serviceModal .svc-input {
        background: var(--bg-body);
        border-color: var(--border-color);
        color: #e2e8f0;
    }
    [data-theme="dark"] #serviceModal .svc-input:focus {
        border-color: var(--primary-color);
    }
    [data-theme="dark"] #serviceModal .svc-input::placeholder {
        color: #64748b;
    }
    [data-theme="dark"] #serviceModal .svc-section-title {
        color: #94a3b8;
    }
    [data-theme="dark"] #serviceModal .svc-add-section {
        background: var(--bg-surface);
        border-color: var(--border-color);
    }
    [data-theme="dark"] #serviceModal .svc-feature-item {
        background: var(--bg-surface);
        border-color: var(--border-color);
    }
</style>
<div class="modal-overlay" id="serviceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="serviceModalTitle">Nuevo Servicio</h2>
            <button class="btn-icon close-modal" onclick="ServiceModule.closeServiceModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="serviceForm" onsubmit="return false;" autocomplete="off">
                <input type="hidden" name="service_id" id="service_id">
                
                <div class="svc-modal-grid">
                    <!-- LEFT COLUMN: Name, Category, Description, Price -->
                    <div class="svc-column">
                        <div class="svc-section-title">
                            <i class="ph ph-info"></i> Información del Servicio
                        </div>

                        <div>
                            <label class="svc-field-label">Nombre del Servicio <span class="required">*</span></label>
                            <input type="text" class="svc-input" name="name" id="service_name" placeholder="Ej: Consultoría IT" required>
                        </div>
                        
                        <div>
                            <label class="svc-field-label">Categoría <span class="required">*</span></label>
                            <div style="display: flex; gap: 0.5rem;">
                                <select class="svc-input" name="category_id" id="service_category" required style="flex: 1;">
                                    <option value="">Selecciona una categoría</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline" onclick="ServiceModule.openCategoryModal()" title="Nueva Categoría" style="padding: 0.5rem 0.75rem; border-radius: 8px; flex-shrink: 0;">
                                    <i class="ph ph-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="svc-field-label">Descripción</label>
                            <textarea class="svc-input" name="description" id="service_description" rows="4" placeholder="Detalles del servicio..."></textarea>
                        </div>

                        <div>
                            <label class="svc-field-label">Precio Estimado (<?php echo htmlspecialchars($global_settings['currency'] ?? 'USD'); ?>)</label>
                            <input type="number" step="0.01" class="svc-input" name="price" id="service_price" placeholder="0.00" value="0.00">
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Characteristics & Deliverables -->
                    <div class="svc-column">
                        <!-- Características -->
                        <div>
                            <div class="svc-section-title">
                                <i class="ph ph-list-checks"></i> Características
                            </div>
                            <div class="svc-add-section">
                                <input type="text" class="svc-input" id="feature_title" placeholder="Ej: Diseño de Logo" style="margin-bottom: 0.5rem;">
                                <textarea class="svc-input" id="feature_desc" rows="2" placeholder="Descripción de la característica..." style="margin-bottom: 0.5rem;"></textarea>
                                <button type="button" class="svc-add-btn" onclick="ServiceModule.addFeature()">
                                    <i class="ph ph-plus-circle"></i> Añadir
                                </button>
                            </div>
                            <div id="featuresList" style="display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.65rem;">
                                <!-- Features will be added here -->
                            </div>
                        </div>

                        <!-- Entregables -->
                        <div style="margin-top: 0.5rem;">
                            <div class="svc-section-title">
                                <i class="ph ph-package"></i> Entregables
                            </div>
                            <div class="svc-add-section">
                                <input type="text" class="svc-input" id="deliverable_title" placeholder="Ej: Manual de marca en PDF" style="margin-bottom: 0.5rem;">
                                <textarea class="svc-input" id="deliverable_desc" rows="2" placeholder="Descripción del entregable..." style="margin-bottom: 0.5rem;"></textarea>
                                <button type="button" class="svc-add-btn" onclick="ServiceModule.addDeliverable()">
                                    <i class="ph ph-plus-circle"></i> Añadir
                                </button>
                            </div>
                            <div id="deliverablesList" style="display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.65rem;">
                                <!-- Deliverables will be added here -->
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <button class="btn btn-outline" onclick="ServiceModule.closeServiceModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnSaveService" onclick="ServiceModule.saveService()">
                <span class="btn-text">Guardar Servicio</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Categoría -->
<div class="modal-overlay" id="categoryModal" style="z-index: 1060;"> <!-- Higher z-index to overlay service modal if both open -->
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title">Gestión de Categorías</h2>
            <button class="btn-icon close-modal" onclick="ServiceModule.closeCategoryModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="categoryForm" onsubmit="return false;" style="margin-bottom: 1.5rem;">
                <input type="hidden" name="category_id" id="category_id">
                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-size: 0.875rem;">Nombre de Categoría *</label>
                    <input type="text" class="form-control" name="category_name" id="category_name" placeholder="Ej: Mantenimiento" required>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" class="btn btn-outline" id="btnCancelEditCategory" onclick="ServiceModule.cancelEditCategory()" style="display: none;">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnSaveCategory" onclick="ServiceModule.saveCategory()">
                        <span class="btn-text">Guardar</span>
                    </button>
                </div>
            </form>

            <div style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden;">
                <div style="max-height: 250px; overflow-y: auto;">
                    <table class="table" style="margin-bottom: 0;">
                        <thead style="background: var(--bg-surface);">
                            <tr>
                                <th>CATEGORÍA</th>
                                <th style="text-align: right;">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody">
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="2" style="text-align: center; color: var(--color-text); padding: 1rem;">No hay categorías.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <tr id="cat-row-<?php echo $cat['id']; ?>">
                                    <td id="cat-name-<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td style="text-align: right;">
                                        <button class="btn-icon" onclick="ServiceModule.editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>')" title="Editar">
                                            <i class="ph ph-pencil-simple"></i>
                                        </button>
                                        <button class="btn-icon" onclick="ServiceModule.deleteCategory(<?php echo $cat['id']; ?>)" title="Eliminar" style="color: var(--color-danger);">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="deleteConfirmModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i class="ph ph-warning"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Estás seguro?</h3>
            <p style="margin-bottom: 0;">Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="ServiceModule.closeDeleteModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmDelete" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<script src="assets/js/modules/services.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
