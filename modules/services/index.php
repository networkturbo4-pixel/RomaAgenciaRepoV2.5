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
$show_trash = isset($_GET['trash']) ? true : false;
$where_clause = $show_trash ? "s.deleted_at IS NOT NULL" : "s.deleted_at IS NULL";

$stmt_serv = $db->query("
    SELECT s.*, c.name as category_name, c.color_tag as category_color
    FROM services s
    LEFT JOIN service_categories c ON s.category_id = c.id
    WHERE $where_clause
    ORDER BY s.created_at DESC
");
$services = $stmt_serv->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
/* Modern Cards Grid */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.service-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    overflow: hidden;
}

.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px rgba(0, 0, 0, 0.05);
}

.service-cover {
    width: 100%;
    height: 140px;
    background-color: var(--bg-body);
    background-size: cover;
    background-position: center;
    border-bottom: 1px solid var(--border-color);
    position: relative;
}

.service-cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 2.5rem;
    opacity: 0.5;
}

.service-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
}

.badge-active { background: rgba(34, 197, 94, 0.9); color: white; }
.badge-paused { background: rgba(234, 179, 8, 0.9); color: white; }
.badge-out_of_stock { background: rgba(239, 68, 68, 0.9); color: white; }

.service-content {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.service-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0 0 0.75rem 0;
    line-height: 1.3;
}

.service-category {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
    border: 1px solid;
}

.service-desc {
    font-size: 0.9rem;
    color: var(--color-text);
    margin-bottom: 1.5rem;
    flex-grow: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.service-footer {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 1.25rem;
    border-top: 1px dashed var(--border-color);
}

.service-price {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--primary-color);
    display: flex;
    flex-direction: column;
}

.service-price-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
}

.service-actions {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: space-between;
    background: var(--bg-color, #f9fafb);
    padding: 0.4rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.service-actions .btn-icon {
    flex: 1;
    display: flex;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.service-actions .btn-icon:hover {
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Filters bar */
.filters-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    background: var(--bg-surface);
    padding: 1rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.filters-bar input, .filters-bar select {
    padding: 0.6rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-body);
    color: var(--color-title);
    outline: none;
    font-family: inherit;
}
.filters-bar input:focus, .filters-bar select:focus {
    border-color: var(--primary-color);
}

[data-theme="dark"] .service-card { box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
[data-theme="dark"] .service-card:hover { box-shadow: 0 12px 20px rgba(0,0,0,0.4); }
</style>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
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
        <a href="index.php?module=public&action=catalog" target="_blank" class="btn btn-outline" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; color: var(--primary-color); border-color: var(--primary-color);">
            <i class="ph ph-storefront"></i> Ver Catálogo
        </a>
        
        <?php if ($show_trash): ?>
            <a href="index.php?module=services" class="btn btn-outline" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none;">
                <i class="ph ph-arrow-left"></i> Volver
            </a>
        <?php else: ?>
            <a href="index.php?module=services&trash=1" class="btn btn-outline" title="Ver Papelera" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; color: var(--text-muted);">
                <i class="ph ph-trash"></i> Papelera
            </a>
        <?php endif; ?>
        
        <button class="btn btn-outline" onclick="ServiceModule.openCategoryModal()" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-folder-plus"></i> Categorías
        </button>
        <a href="index.php?module=services&action=form" class="btn btn-primary" title="Atajo: Ctrl + N" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none;">
            <i class="ph ph-plus"></i> Nuevo Servicio
        </a>
    </div>
</div>

<div class="filters-bar">
    <div style="flex: 1; min-width: 250px; position: relative;">
        <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
        <input type="text" id="searchInput" placeholder="Buscar servicios..." style="width: 100%; padding-left: 2.5rem;" onkeyup="ServiceModule.filterServices()">
    </div>
    <select id="categoryFilter" style="min-width: 200px;" onchange="ServiceModule.filterServices()">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="services-grid" id="servicesGrid">
    <?php if (empty($services)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
            <div style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"><i class="ph ph-briefcase"></i></div>
            <h3 style="color: var(--color-title); font-size: 1.2rem; margin-bottom: 0.5rem;">No hay servicios registrados</h3>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Comienza creando tu primer servicio para ofrecer a tus clientes.</p>
            <a href="index.php?module=services&action=form" class="btn btn-primary"><i class="ph ph-plus"></i> Crear Servicio</a>
        </div>
    <?php else: ?>
        <?php foreach ($services as $service): 
            $catColor = !empty($service['category_color']) ? $service['category_color'] : '#6b7280';
            // Convert hex to rgb for background opacity
            list($r, $g, $b) = sscanf($catColor, "#%02x%02x%02x");
            $rgbaColor = "rgba($r, $g, $b, 0.1)";
        ?>
        <div class="service-card" id="service-card-<?php echo $service['id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>" data-category="<?php echo $service['category_id']; ?>">
            
            <?php 
                // Build public link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $public_link = $protocol . '://' . $host . $base_dir . "/servicio/" . $service['id'];
                if (!empty($service['slug'])) {
                    $public_link .= "/" . urlencode($service['slug']);
                }
            ?>
            <div class="service-cover">
                <?php if(!empty($service['cover_image'])): ?>
                    <img src="uploads/services/<?php echo htmlspecialchars($service['cover_image']); ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                <?php else: ?>
                    <div class="service-cover-placeholder"><i class="ph ph-image"></i></div>
                <?php endif; ?>
                
                <?php
                $status = $service['status'] ?? 'active';
                $badgeClass = 'badge-active';
                $badgeText = 'Activo';
                if ($status === 'paused') { $badgeClass = 'badge-paused'; $badgeText = 'Pausado'; }
                if ($status === 'out_of_stock') { $badgeClass = 'badge-out_of_stock'; $badgeText = 'Agotado'; }
                ?>
                <span class="service-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
            </div>

            <div class="service-content">
                <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                
                <div>
                    <span class="service-category" style="color: <?php echo $catColor; ?>; background-color: <?php echo $rgbaColor; ?>; border-color: <?php echo $catColor; ?>;">
                        <i class="ph ph-folder"></i>
                        <?php echo htmlspecialchars($service['category_name'] ?? 'Sin categoría'); ?>
                    </span>
                </div>

                <div class="service-desc">
                    <?php echo $service['description'] ? nl2br(htmlspecialchars($service['description'])) : '<span style="color: var(--text-muted); font-style: italic;">Sin descripción</span>'; ?>
                </div>

                <div class="service-footer">
                    <div class="service-price">
                        <?php if(isset($service['price_type']) && $service['price_type'] === 'packages'): ?>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Desde (Paquetes)</div>
                        <?php elseif(isset($service['price_type']) && $service['price_type'] === 'monthly'): ?>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Mensual / Recurrente</div>
                        <?php elseif(isset($service['price_type']) && $service['price_type'] === 'from'): ?>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Desde</div>
                        <?php endif; ?>
                        <div>
                            <?php 
                                $srv_currency_code = !empty($service['currency']) ? trim($service['currency']) : trim(htmlspecialchars($global_settings['currency'] ?? 'USD'));
                                $currency_symbols = ['PEN' => 'S/', 'USD' => '$', 'EUR' => '€', 'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'COP' => '$'];
                                $srv_currency = $currency_symbols[$srv_currency_code] ?? $srv_currency_code;
                                echo $srv_currency . ' ' . number_format($service['price'], 2); 
                            ?>
                        </div>
                    </div>
                    <div class="service-actions">
                        <?php if ($show_trash): ?>
                            <button class="btn-icon" onclick="ServiceModule.restoreService(<?php echo $service['id']; ?>)" title="Restaurar" style="color: var(--primary-color);">
                                <i class="ph ph-arrow-counter-clockwise"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn-icon" onclick="ServiceModule.copyLink(this, '<?php echo $public_link; ?>')" title="Copiar enlace" style="color: var(--primary-color);">
                                <i class="ph ph-link"></i>
                            </button>
                            <button onclick="window.location.href='index.php?module=services&action=form&clone_id=<?php echo $service['id']; ?>'" class="btn-icon" title="Duplicar">
                                <i class="ph ph-copy"></i>
                            </button>
                            <a href="index.php?module=services&action=form&id=<?php echo $service['id']; ?>" class="btn-icon" title="Editar" style="text-decoration: none;">
                                <i class="ph ph-pencil-simple"></i>
                            </a>
                            <button class="btn-icon" onclick="ServiceModule.deleteService(<?php echo $service['id']; ?>)" title="Eliminar" style="color: var(--color-danger);">
                                <i class="ph ph-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Categoría -->
<div class="modal-overlay" id="categoryModal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header">
            <h2 class="modal-title">Gestión de Categorías</h2>
            <button class="btn-icon close-modal" onclick="ServiceModule.closeCategoryModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">
            <!-- Columna Izquierda: Formulario -->
            <div style="background: var(--bg-body); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                <h3 id="categoryFormTitle" style="margin-top: 0; font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-title);">
                    <i class="ph ph-plus-circle" id="categoryFormIcon"></i> 
                    <span id="categoryFormText">Añadir Nueva Categoría</span>
                </h3>
                <form id="categoryForm" onsubmit="return false;">
                    <input type="hidden" name="category_id" id="category_id">
                    <div style="margin-bottom: 1.25rem;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Nombre de la Categoría *</label>
                        <input type="text" class="form-control" name="category_name" id="category_name" placeholder="Ej: Mantenimiento Preventivo" required style="background: var(--bg-surface);">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Etiqueta de Color *</label>
                        <div style="display: flex; gap: 0.75rem; align-items: center; background: var(--bg-surface); padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-color);">
                            <input type="color" name="color_tag" id="color_tag" value="#4b5563" style="width: 36px; height: 36px; padding: 0; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">
                            <span style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.3;">Selecciona un color para identificar visualmente esta categoría.</span>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <button type="button" class="btn btn-primary" id="btnSaveCategory" onclick="ServiceModule.saveCategory()" style="width: 100%; justify-content: center;">
                            <span class="btn-text">Guardar Categoría</span>
                        </button>
                        <button type="button" class="btn btn-outline" id="btnCancelEditCategory" onclick="ServiceModule.cancelEditCategory()" style="display: none; width: 100%; justify-content: center;">
                            Cancelar Edición
                        </button>
                    </div>
                </form>
            </div>

            <!-- Columna Derecha: Lista -->
            <div>
                <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted); margin-top: 0; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Categorías Existentes</h4>
                <div style="max-height: 380px; overflow-y: auto; padding-right: 0.5rem;" id="categoriesListContainer">
                    <?php if (empty($categories)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 8px;">
                            <i class="ph ph-folder-open" style="font-size: 2.5rem; margin-bottom: 0.75rem;"></i>
                            <p style="margin: 0; font-size: 0.95rem;">No hay categorías creadas aún.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($categories as $cat): ?>
                            <div id="cat-row-<?php echo $cat['id']; ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; transition: border-color 0.2s;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; font-weight: 500; color: var(--color-title);">
                                    <div style="width: 14px; height: 14px; border-radius: 50%; background-color: <?php echo htmlspecialchars($cat['color_tag'] ?? '#4b5563'); ?>; box-shadow: 0 0 0 2px rgba(255,255,255,0.8), 0 0 0 3px <?php echo htmlspecialchars($cat['color_tag'] ?? '#4b5563'); ?>;"></div>
                                    <span id="cat-name-<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></span>
                                </div>
                                <div style="display: flex; gap: 0.25rem;">
                                    <button class="btn-icon" onclick="ServiceModule.editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', '<?php echo htmlspecialchars($cat['color_tag'] ?? '#4b5563'); ?>')" title="Editar" style="width: 32px; height: 32px; background: rgba(37, 99, 235, 0.1); color: var(--primary-color);">
                                        <i class="ph ph-pencil-simple"></i>
                                    </button>
                                    <button class="btn-icon" onclick="ServiceModule.deleteCategory(<?php echo $cat['id']; ?>)" title="Eliminar" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.1); color: var(--danger-color);">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
