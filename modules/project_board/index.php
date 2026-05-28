<?php
// modules/project_board/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    echo "<div class='alert alert-danger'>ID de proyecto no válido.</div>";
    require_once 'includes/footer.php';
    exit();
}

try {
    // Fetch project details
    $stmtProject = $db->prepare("
        SELECT p.*, w.correlativo, w.brand_name, w.data, w.public_token 
        FROM projects p
        JOIN work_orders w ON p.work_order_id = w.id
        WHERE p.id = ?
    ");
    $stmtProject->execute([$projectId]);
    $project = $stmtProject->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception("Proyecto no encontrado.");
    }

    $woData = json_decode($project['data'], true) ?: [];
    $servicio = $woData['servicio'] ?? 'Servicio General';
    
    // Get brand logo
    $stmtBrand = $db->prepare("SELECT logo FROM client_brands WHERE name = ?");
    $stmtBrand->execute([$project['brand_name']]);
    $brand = $stmtBrand->fetch(PDO::FETCH_ASSOC);
    $logo = $brand ? $brand['logo'] : 'assets/img/default-logo.png';

    // Fetch project months
    $filterMonth = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
    $filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';

    $query = "SELECT * FROM project_months WHERE project_id = ?";
    $params = [$projectId];

    if ($filterMonth !== '') {
        $query .= " AND month = ?";
        $params[] = (int)$filterMonth;
    }
    if ($filterYear !== '') {
        $query .= " AND year = ?";
        $params[] = (int)$filterYear;
    }
    $query .= " ORDER BY year DESC, month DESC";

    $stmtMonths = $db->prepare($query);
    $stmtMonths->execute($params);
    $months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

    // Month Names Array for display
    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

} catch (Exception $e) {
    $error = $e->getMessage();
}

?>

<style>
    .project-header {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        flex-wrap: wrap;
    }
    .btn-back-compact {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .btn-back-compact:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .project-header-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    .project-header-info img {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-md);
        object-fit: contain;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        padding: 0.25rem;
    }
    .project-header-info h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-title);
    }
    .project-header-info p {
        margin: 0.25rem 0 0 0;
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    
    /* Toolbar / Filters */
    .board-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .filters-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    /* Cards Grid */
    .months-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .mc-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        gap: 1rem;
        transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    }
    .mc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.08);
    }
    .mc-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
    }
    .mc-stats {
        display: flex;
        gap: 0.75rem;
    }
    .mc-stat-box {
        flex: 1;
        background: var(--bg-color);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        border: 1px solid var(--border-color);
    }
    [data-theme="dark"] .mc-stat-box {
        background: var(--bg-color);
    }
    .mc-stat-num {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-title);
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    .mc-stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .mc-progress-wrapper {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .mc-progress-header {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
    }
    .mc-progress-bar {
        height: 6px;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        overflow: hidden;
    }
    [data-theme="dark"] .mc-progress-bar {
        background: var(--border-color);
    }
    .mc-progress-fill {
        height: 100%;
        background: var(--primary-color);
        border-radius: 4px;
    }
    .mc-date {
        font-size: 0.8rem;
        color: var(--text-muted);
        text-align: right;
    }
    .mc-divider {
        border: 0;
        border-top: 1px solid var(--border-color);
        margin: 0;
    }
    .mc-footer-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .mc-status {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.3rem 0.6rem;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-pendiente { background: rgba(245, 158, 11, 0.15); color: #d97706; }
    .status-en_progreso { background: rgba(79, 70, 229, 0.15); color: var(--primary-color); }
    .status-finalizado { background: rgba(16, 185, 129, 0.15); color: var(--secondary-color); }

    .mc-actions {
        display: flex;
        gap: 1rem;
    }
    .mc-btn-text {
        background: none;
        border: none;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0;
        text-transform: uppercase;
    }
    .mc-btn-text.text-blue { color: var(--primary-color); }
    .mc-btn-text.text-red { color: var(--danger-color); }
    
    .mc-footer-bottom {
        display: flex;
        gap: 0.5rem;
    }
    .mc-btn-enter {
        flex: 1;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.6rem;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }
    .mc-btn-enter:hover {
        background: var(--primary-hover);
        color: white;
    }
    .mc-select {
        flex: 2;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--color-title);
        border-radius: 8px;
        padding: 0.6rem;
        font-weight: 600;
        font-size: 0.9rem;
        outline: none;
    }
    [data-theme="dark"] .mc-select {
        background: var(--bg-color);
        border-color: var(--border-color);
        color: var(--color-title);
    }
    
    @media (max-width: 768px) {
        .board-toolbar {
            flex-direction: column;
            align-items: stretch;
        }
        .filters-group {
            width: 100%;
        }
        .filters-group form {
            width: 100%;
            display: flex;
            gap: 0.5rem;
        }
        .filters-group select {
            flex: 1;
        }
        .project-header-info h1 {
            font-size: 1.25rem;
        }
        .mb-4 {
            margin-bottom: 2rem !important;
        }
        .project-header {
            flex-direction: column;
            text-align: center;
        }
    }

    /* Estilos para el modal de carpetas */
    .folder-box {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
        background: var(--bg-color);
    }
    .folder-box-title {
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-transform: uppercase;
    }
    .folder-box-input-group {
        display: flex;
        gap: 0.5rem;
    }
    .folder-box-input-group input {
        flex: 1;
        background: var(--bg-surface);
    }
</style>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="padding: 1rem; border-radius: var(--radius-md); background: #fee2e2; color: #991b1b; margin-bottom: 2rem;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

    <div class="project-header">
        <div style="display: flex; align-items: center; gap: 1.5rem; flex: 1; flex-wrap: wrap;">
            <a href="index.php?module=calendar" class="btn-back-compact" title="Volver a Proyectos">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="project-header-info">
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
                <div>
                    <h1><?php echo htmlspecialchars($project['brand_name']); ?> - <?php echo htmlspecialchars($servicio); ?></h1>
                    <p>Orden de Servicio: <strong><?php echo htmlspecialchars($project['correlativo']); ?></strong> | Estado: <span style="text-transform: uppercase; font-weight: bold; color: <?php echo $project['status'] === 'active' ? '#059669' : '#4b5563'; ?>;"><?php echo htmlspecialchars($project['status']); ?></span></p>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <form method="GET" action="index.php" style="display: flex; gap: 0.5rem; background: var(--bg-color); padding: 0.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <input type="hidden" name="module" value="project_board">
                <input type="hidden" name="id" value="<?php echo $projectId; ?>">
                
                <select name="filter_month" class="form-control" style="background: transparent; border: none; padding: 0.25rem 0.5rem; font-weight: 600; cursor: pointer; width: auto;" onchange="this.form.submit()">
                    <option value="">Todos los Meses</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $filterMonth === (string)$num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_year" class="form-control" style="background: transparent; border: none; padding: 0.25rem 0.5rem; font-weight: 600; cursor: pointer; width: auto; border-left: 1px solid var(--border-color); border-radius: 0;" onchange="this.form.submit()">
                    <option value="">Todos los Años</option>
                    <?php 
                    $currentYear = date('Y');
                    for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filterYear === (string)$y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>

            <button class="btn btn-outline" onclick="openProjectInfoOffcanvas()" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;">
                <i class="ph ph-info"></i> Información del proyecto
            </button>
            <button class="btn btn-primary" onclick="openNewMonthModal()" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;">
                <i class="ph ph-plus"></i> Añadir Nuevo Mes
            </button>
        </div>
    </div>

    <!-- Months Grid -->
    <div class="months-grid">
        <?php if (empty($months)): ?>
            <div style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); color: var(--text-muted);">
                <i class="ph ph-calendar-blank" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
                <h3>No hay meses creados</h3>
                <p>Comienza añadiendo el primer mes de trabajo para este proyecto.</p>
            </div>
        <?php else: ?>
            <?php foreach ($months as $m): ?>
                <?php 
                    $statusClass = 'status-' . str_replace(' ', '_', strtolower($m['status']));
                    
                    // Format creation date or use dummy
                    $dateObj = new DateTime($m['created_at']);
                    // For Spanish date like '24 abr 2026, 04:52 p.m.'
                    $fmtDate = strftime('%d %b %Y, %I:%M %p', $dateObj->getTimestamp());
                    // Or standard fallback if strftime is deprecated (PHP 8.1+)
                    $monthsEs = ['Jan'=>'ene','Feb'=>'feb','Mar'=>'mar','Apr'=>'abr','May'=>'may','Jun'=>'jun','Jul'=>'jul','Aug'=>'ago','Sep'=>'sep','Oct'=>'oct','Nov'=>'nov','Dec'=>'dic'];
                    $fmtDate = $dateObj->format('d ') . $monthsEs[$dateObj->format('M')] . $dateObj->format(' Y, h:i a');
                ?>
                <?php
                    // Obtener conteos dinámicos
                    $stmtP = $db->prepare("SELECT COUNT(*) FROM month_posts WHERE month_id = ?");
                    $stmtP->execute([$m['id']]);
                    $postsCount = $stmtP->fetchColumn();
                    
                    $stmtC = $db->prepare("
                        SELECT COUNT(*) 
                        FROM post_comments pc 
                        JOIN month_posts mp ON pc.post_id = mp.id 
                        WHERE mp.month_id = ?
                    ");
                    $stmtC->execute([$m['id']]);
                    $commentsCount = $stmtC->fetchColumn();
                ?>
                <div class="mc-card">
                    <h2 class="mc-title"><?php echo $monthNames[$m['month']] . ' ' . $m['year']; ?></h2>
                    
                    <div class="mc-stats">
                        <div class="mc-stat-box">
                            <div class="mc-stat-num"><?php echo $postsCount; ?></div>
                            <div class="mc-stat-label">POSTS</div>
                        </div>
                        <div class="mc-stat-box">
                            <div class="mc-stat-num"><?php echo $commentsCount; ?></div>
                            <div class="mc-stat-label">COMENTARIOS</div>
                        </div>
                    </div>
                    
                    <div class="mc-progress-wrapper">
                        <div class="mc-progress-header">
                            <span>PROGRESO</span>
                            <span>0%</span>
                        </div>
                        <div class="mc-progress-bar"><div class="mc-progress-fill" style="width: 0%"></div></div>
                    </div>
                    
                    <div class="mc-date"><?php echo htmlspecialchars($fmtDate); ?></div>
                    
                    <hr class="mc-divider">
                    
                    <div class="mc-footer-top">
                        <span class="mc-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($m['status']); ?></span>
                        <div class="mc-actions">
                            <button class="mc-btn-text text-blue" onclick="editMonth(<?php echo $m['id']; ?>)">
                                <i class="ph ph-pencil-simple"></i> EDITAR
                            </button>
                            <button class="mc-btn-text text-red" onclick="deleteMonth(<?php echo $m['id']; ?>)">
                                <i class="ph ph-trash"></i> ELIMINAR
                            </button>
                        </div>
                    </div>
                    
                    <div class="mc-footer-bottom">
                        <a href="index.php?module=month_board&id=<?php echo $m['id']; ?>" class="mc-btn-enter">
                            <i class="ph ph-pencil-simple"></i> Entrar
                        </a>
                        <select class="mc-select" onchange="changeContentPhase(<?php echo $m['id']; ?>, this)">
                            <option value="En Borrador" <?php echo ($m['content_phase'] ?? 'En Borrador') === 'En Borrador' ? 'selected' : ''; ?>>En Borrador</option>
                            <option value="Grilla de contenidos" <?php echo ($m['content_phase'] ?? '') === 'Grilla de contenidos' ? 'selected' : ''; ?>>Grilla de contenidos</option>
                            <option value="Parrilla de contenidos" <?php echo ($m['content_phase'] ?? '') === 'Parrilla de contenidos' ? 'selected' : ''; ?>>Parrilla de contenidos</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<!-- Modal Añadir Nuevo Mes -->
<div class="modal-overlay" id="new-month-modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h2>Añadir Nuevo Mes</h2>
            <button class="btn-icon" onclick="document.getElementById('new-month-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <form id="new-month-form">
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Mes</label>
                        <select name="month" class="form-control" required>
                            <option value="">Selecciona un mes...</option>
                            <?php foreach ($monthNames as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo $num == date('n') ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Año</label>
                        <select name="year" class="form-control" required>
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-01'); ?>">
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
                
                <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-title);">
                    <i class="ph ph-google-drive-logo" style="color: #3b82f6; font-size: 1.5rem;"></i> Carpetas del Proyecto
                </h3>

                <input type="hidden" name="drive_folders_json" id="new_drive_folders_json">

                <div id="new-folders-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <!-- Cards de carpetas -->
                </div>

                <button type="button" id="btn-generate-new-folders" class="btn btn-outline" style="width: 100%; justify-content: center; border-style: dashed; padding: 1.5rem; color: #3b82f6; border-color: #3b82f6; background: rgba(59, 130, 246, 0.05);" onclick="generateDriveFolders('new')">
                    <i class="ph ph-magic-wand" style="font-size: 1.5rem;"></i>
                    <span style="font-weight: 600; font-size: 1rem;">Generar Estructura de Carpetas</span>
                </button>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="document.getElementById('new-month-modal').classList.remove('active')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveNewMonth()">Guardar Mes</button>
        </div>
    </div>
</div>

<!-- Modal Editar Mes -->
<div class="modal-overlay" id="edit-month-modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h2>Editar Mes</h2>
            <button class="btn-icon" onclick="document.getElementById('edit-month-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <form id="edit-month-form">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="form-group">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="start_date" id="edit-start_date" class="form-control" required>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
                
                <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-title);">
                    <i class="ph ph-google-drive-logo" style="color: #3b82f6; font-size: 1.5rem;"></i> Carpetas del Proyecto
                </h3>

                <input type="hidden" name="drive_folders_json" id="edit_drive_folders_json">

                <div id="edit-folders-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <!-- Cards de carpetas -->
                </div>

                <button type="button" id="btn-generate-edit-folders" class="btn btn-outline" style="width: 100%; justify-content: center; border-style: dashed; padding: 1.5rem; color: #3b82f6; border-color: #3b82f6; background: rgba(59, 130, 246, 0.05);" onclick="generateDriveFolders('edit')">
                    <i class="ph ph-magic-wand" style="font-size: 1.5rem;"></i>
                    <span style="font-weight: 600; font-size: 1rem;">Generar Estructura de Carpetas</span>
                </button>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="document.getElementById('edit-month-modal').classList.remove('active')">Cancelar</button>
            <button class="btn btn-primary" onclick="updateMonth()">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- Off-canvas Información del proyecto -->
<style>
    .pi-offcanvas {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: 600px;
        max-width: 100vw;
        background: var(--bg-surface);
        box-shadow: -5px 0 25px rgba(0,0,0,0.1);
        z-index: 1050;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }
    .pi-offcanvas.active {
        transform: translateX(0);
    }
    .pi-offcanvas-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 1040;
        display: none;
        backdrop-filter: blur(2px);
    }
    .pi-offcanvas-overlay.active { display: block; }
    
    .pi-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-color);
    }
    
    .pi-body {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
    }

    .pi-nav {
        display: flex;
        background: var(--bg-color);
        padding: 4px;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }
    .pi-nav-item {
        flex: 1;
        text-align: center;
        padding: 0.6rem 1rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    .pi-nav-item.active {
        color: var(--color-title);
        background: var(--bg-surface);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .pi-tab-pane { display: none; }
    .pi-tab-pane.active { display: block; }

    .pi-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        transition: box-shadow 0.2s;
    }
    .pi-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .pi-card-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    .pi-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .pi-card-icon.form { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .pi-card-icon.pdf { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .pi-card-title {
        font-weight: 700;
        font-size: 1rem;
        color: var(--color-title);
        margin: 0;
    }
    .pi-card-meta {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0;
    }
    .pi-actions {
        display: flex;
        gap: 0.5rem;
    }

    .pi-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: var(--radius-lg);
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: var(--bg-color);
    }
    .pi-upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(var(--primary-color-rgb), 0.05);
    }
</style>

<div class="pi-offcanvas-overlay" id="pi-overlay" onclick="closeProjectInfoOffcanvas()"></div>
<div class="pi-offcanvas" id="pi-offcanvas">
    <div class="pi-header">
        <h2 style="margin:0; font-size: 1.5rem; font-weight: 700; display:flex; align-items:center; gap: 0.5rem;">
            <i class="ph ph-info"></i> Información del proyecto
        </h2>
        <button type="button" class="btn-icon" onclick="closeProjectInfoOffcanvas()" style="width: 40px; height: 40px;">
            <i class="ph ph-x" style="font-size: 1.5rem;"></i>
        </button>
    </div>
    <div class="pi-body">
        <div class="pi-nav">
            <div class="pi-nav-item active" onclick="switchPiTab('forms', this)">Formularios</div>
            <div class="pi-nav-item" onclick="switchPiTab('pdfs', this)">Documentos PDF</div>
        </div>

        <!-- Formularios Tab -->
        <div id="pi-tab-forms" class="pi-tab-pane active">
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700;">Vincular Nuevo Formulario</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="pi-form-select" class="form-control" style="flex: 1;">
                        <option value="">Cargando formularios...</option>
                    </select>
                    <button class="btn btn-primary" onclick="linkForm()" id="btn-link-form">
                        <i class="ph ph-link"></i> Vincular
                    </button>
                </div>
            </div>
            
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Formularios Vinculados</h3>
            <div id="pi-forms-list">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Cargando...</div>
            </div>
        </div>

        <!-- PDFs Tab -->
        <div id="pi-tab-pdfs" class="pi-tab-pane">
            <div class="pi-upload-area" onclick="document.getElementById('pi-pdf-upload').click()" id="pi-upload-zone">
                <i class="ph ph-upload-simple" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3 style="margin: 0 0 0.5rem 0;">Subir Documento PDF</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">Haz clic aquí para seleccionar un archivo. (Se guardará en la carpeta Form en Drive)</p>
                <input type="file" id="pi-pdf-upload" accept="application/pdf" style="display: none;" onchange="uploadPdf(this)">
            </div>
            
            <div id="pi-upload-progress" style="display: none; margin-top: 1rem; background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                    <span>Subiendo archivo a Google Drive...</span>
                    <i class="ph ph-spinner ph-spin" style="color: var(--primary-color);"></i>
                </div>
            </div>

            <h3 style="font-size: 1.1rem; margin: 2rem 0 1rem 0;">Documentos Subidos</h3>
            <div id="pi-pdfs-list">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Cargando...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Formulario Público -->
<div class="modal-overlay" id="pi-form-modal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; padding: 0;">
        <div class="modal-header" style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color);">
            <h2 id="pi-form-modal-title" style="margin: 0; font-size: 1.25rem;">Vista Pública del Formulario</h2>
            <button class="btn-icon" onclick="document.getElementById('pi-form-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden; background: #f3f4f6;">
            <iframe id="pi-form-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal PDF Viewer -->
<div class="modal-overlay" id="pi-pdf-modal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; padding: 0;">
        <div class="modal-header" style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color);">
            <h2 id="pi-pdf-modal-title" style="margin: 0; font-size: 1.25rem;">Visor de PDF</h2>
            <button class="btn-icon" onclick="document.getElementById('pi-pdf-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden;">
            <iframe id="pi-pdf-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
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
            <input type="hidden" id="delete-month-id">
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteConfirmModal').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmDeleteMonth()" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<?php require_once 'includes/custom_drive_picker.php'; ?>
<script>
    // --- CUSTOM DRIVE PICKER API (FOLDER SELECTION) ---
    const PROJECT_FOLDER_ID = '<?php echo htmlspecialchars($project['drive_folder_id'] ?? ""); ?>';
    let currentFolderInputId = '';

    function promptFolder(inputId) {
        currentFolderInputId = inputId;
        const restrictedId = PROJECT_FOLDER_ID ? PROJECT_FOLDER_ID : null;
        
        cdOpenPicker(restrictedId, function(folder) {
            if (!folder.url) {
                folder.url = "https://drive.google.com/drive/folders/" + folder.id;
            }
            if (currentFolderInputId) {
                document.getElementById(currentFolderInputId).value = folder.url;
            }
        });
    }
    // --- FIN CUSTOM DRIVE PICKER ---

    function renderDriveFolderCards(foldersJsonStr, containerId, btnId) {
        const container = document.getElementById(containerId);
        const btn = document.getElementById(btnId);
        
        let foldersData = null;
        try { foldersData = JSON.parse(foldersJsonStr); } catch(e){}
        
        if (!foldersData || !foldersData.subfolders || foldersData.subfolders.length === 0) {
            container.innerHTML = '';
            btn.style.display = 'flex';
            return;
        }
        
        btn.style.display = 'none'; // hide generate button
        
        let html = '';
        foldersData.subfolders.forEach(f => {
            html += `
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: var(--shadow-sm);">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-title); font-weight: 700; font-size: 0.85rem;">
                    <i class="ph-fill ph-folder" style="font-size: 1.5rem; color: #facc15;"></i>
                    ${f.name}
                </div>
                <a href="${f.url}" target="_blank" style="text-decoration: none; color: #3b82f6; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.25rem; margin-top: auto;">
                    Abrir en Drive <i class="ph ph-arrow-square-out"></i>
                </a>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    async function generateDriveFolders(mode) {
        const btn = document.getElementById(`btn-generate-${mode}-folders`);
        const origHtml = btn.innerHTML;
        
        const formData = new FormData();
        
        if (mode === 'new') {
            const form = document.getElementById('new-month-form');
            const m = form.querySelector('[name="month"]').value;
            const y = form.querySelector('[name="year"]').value;
            const pId = form.querySelector('[name="project_id"]').value;
            if (!m || !y) {
                alert('Por favor selecciona Mes y Año primero.');
                return;
            }
            formData.append('project_id', pId);
            formData.append('month', m);
            formData.append('year', y);
        } else {
            const form = document.getElementById('edit-month-form');
            const monthId = form.querySelector('[name="id"]').value;
            formData.append('month_id', monthId);
        }
        
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';
        btn.disabled = true;
        
        try {
            const response = await fetch('modules/project_board/ajax_generate_month_folders.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            btn.innerHTML = origHtml;
            btn.disabled = false;
            
            if (result.success) {
                const jsonStr = JSON.stringify(result.data);
                document.getElementById(`${mode}_drive_folders_json`).value = jsonStr;
                renderDriveFolderCards(jsonStr, `${mode}-folders-container`, `btn-generate-${mode}-folders`);
            } else {
                alert(result.error || 'Error al crear carpetas.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red.');
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    }

    function openNewMonthModal() {
        document.getElementById('new-month-form').reset();
        document.getElementById('new_drive_folders_json').value = '';
        document.getElementById('new-folders-container').innerHTML = '';
        document.getElementById('btn-generate-new-folders').style.display = 'flex';
        document.getElementById('new-month-modal').classList.add('active');
    }

async function saveNewMonth() {
    const form = document.getElementById('new-month-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/project_board/ajax_save_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al guardar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

// --- PROJECT INFO OFFCANVAS LOGIC ---
function openProjectInfoOffcanvas() {
    document.getElementById('pi-overlay').classList.add('active');
    document.getElementById('pi-offcanvas').classList.add('active');
    loadProjectInfo();
}

function closeProjectInfoOffcanvas() {
    document.getElementById('pi-overlay').classList.remove('active');
    document.getElementById('pi-offcanvas').classList.remove('active');
}

function switchPiTab(tabId, el) {
    document.querySelectorAll('.pi-nav-item').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.pi-tab-pane').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('pi-tab-' + tabId).classList.add('active');
}

async function loadProjectInfo() {
    try {
        const response = await fetch(`modules/project_board/ajax_get_project_info.php?project_id=<?php echo $projectId; ?>`);
        const result = await response.json();
        
        if (result.success) {
            // Render Select
            const select = document.getElementById('pi-form-select');
            select.innerHTML = '<option value="" style="color:#111827; background:#fff;">Selecciona un formulario...</option>';
            result.available_forms.forEach(f => {
                let text = `${f.form_title} - ${f.respondent_name || 'Sin nombre'} (${f.correlativo})`;
                select.innerHTML += `<option value="${f.id}" style="color:#111827; background:#fff;">${text}</option>`;
            });

            // Render Forms List
            const formsList = document.getElementById('pi-forms-list');
            if (result.forms.length === 0) {
                formsList.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:8px;">No hay formularios vinculados.</div>';
            } else {
                let html = '';
                result.forms.forEach(f => {
                    const date = new Date(f.created_at).toLocaleDateString('es-ES', {day: 'numeric', month: 'short', year: 'numeric'});
                    let titleText = `${f.form_title} - ${f.respondent_name || 'Sin nombre'} (${f.correlativo})`;
                    html += `
                    <div class="pi-card">
                        <div class="pi-card-info" style="cursor: pointer;" onclick="openFormModal(${f.submission_id}, '${f.form_title}')">
                            <div class="pi-card-icon form"><i class="ph ph-article"></i></div>
                            <div>
                                <h4 class="pi-card-title">${titleText}</h4>
                                <p class="pi-card-meta">Vinculado el ${date}</p>
                            </div>
                        </div>
                        <div class="pi-actions">
                            <button class="btn-icon text-red" onclick="deleteAttachment(${f.id}, 'form')" title="Quitar vinculación">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>`;
                });
                formsList.innerHTML = html;
            }

            // Render PDFs List
            const pdfsList = document.getElementById('pi-pdfs-list');
            if (result.pdfs.length === 0) {
                pdfsList.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:8px;">No hay documentos PDF.</div>';
            } else {
                let html = '';
                result.pdfs.forEach(p => {
                    const date = new Date(p.created_at).toLocaleDateString('es-ES', {day: 'numeric', month: 'short', year: 'numeric'});
                    html += `
                    <div class="pi-card">
                        <div class="pi-card-info" style="cursor: pointer;" onclick="openPdfModal('${p.url}', '${p.file_name}')">
                            <div class="pi-card-icon pdf"><i class="ph ph-file-pdf"></i></div>
                            <div style="overflow: hidden;">
                                <h4 class="pi-card-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${p.file_name}">${p.file_name}</h4>
                                <p class="pi-card-meta">Subido el ${date}</p>
                            </div>
                        </div>
                        <div class="pi-actions">
                            <a href="${p.url}" target="_blank" class="btn-icon text-blue" title="Abrir en Drive">
                                <i class="ph ph-arrow-square-out"></i>
                            </a>
                            <button class="btn-icon text-red" onclick="deleteAttachment(${p.id}, 'pdf')" title="Eliminar archivo">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>`;
                });
                pdfsList.innerHTML = html;
            }
        }
    } catch (e) {
        console.error(e);
        document.getElementById('pi-forms-list').innerHTML = '<div style="color:red;">Error cargando datos.</div>';
        document.getElementById('pi-pdfs-list').innerHTML = '<div style="color:red;">Error cargando datos.</div>';
    }
}

async function linkForm() {
    const submissionId = document.getElementById('pi-form-select').value;
    if (!submissionId) return;

    const btn = document.getElementById('btn-link-form');
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('submission_id', submissionId);

    try {
        const response = await fetch('modules/project_board/ajax_link_form.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al vincular formulario');
        }
    } catch (e) {
        console.error(e);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="ph ph-link"></i> Vincular';
}

async function uploadPdf(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    if (file.type !== 'application/pdf') {
        alert('Solo se permiten archivos PDF.');
        input.value = '';
        return;
    }

    const zone = document.getElementById('pi-upload-zone');
    const progress = document.getElementById('pi-upload-progress');
    
    zone.style.display = 'none';
    progress.style.display = 'block';

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('pdf_file', file);

    try {
        const response = await fetch('modules/project_board/ajax_upload_pdf.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al subir PDF');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red al subir PDF');
    }

    input.value = '';
    zone.style.display = 'block';
    progress.style.display = 'none';
}

async function deleteAttachment(id, type) {
    if (!confirm('¿Estás seguro de eliminar este elemento?')) return;

    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const response = await fetch('modules/project_board/ajax_delete_attachment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al eliminar');
        }
    } catch (e) {
        console.error(e);
    }
}

function openFormModal(id, title) {
    const iframe = document.getElementById('pi-form-iframe');
    iframe.src = `modules/forms/view_submission.php?id=${id}&mode=iframe`;
    document.getElementById('pi-form-modal-title').textContent = title;
    document.getElementById('pi-form-modal').classList.add('active');
}

function openPdfModal(url, title) {
    const iframe = document.getElementById('pi-pdf-iframe');
    // Usamos el webViewLink de Google Drive que soporta iframes si está público
    iframe.src = url;
    document.getElementById('pi-pdf-modal-title').textContent = title;
    document.getElementById('pi-pdf-modal').classList.add('active');
}


async function editMonth(id) {
    try {
        const response = await fetch(`modules/project_board/ajax_get_month.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            document.getElementById('edit-id').value = data.id;
            document.getElementById('edit-start_date').value = data.start_date || '';
            document.getElementById('edit_drive_folders_json').value = data.drive_folders_json || '';
            
            renderDriveFolderCards(data.drive_folders_json, 'edit-folders-container', 'btn-generate-edit-folders');
            
            document.getElementById('edit-month-modal').classList.add('active');
        } else {
            alert('Error al obtener datos: ' + result.error);
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function updateMonth() {
    const form = document.getElementById('edit-month-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/project_board/ajax_update_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

function deleteMonth(id) {
    document.getElementById('delete-month-id').value = id;
    document.getElementById('deleteConfirmModal').classList.add('active');
}

async function confirmDeleteMonth() {
    const id = document.getElementById('delete-month-id').value;
    if (!id) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    try {
        const response = await fetch('modules/project_board/ajax_delete_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al eliminar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function changeContentPhase(id, selectElement) {
    const phase = selectElement.value;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('content_phase', phase);
    
    try {
        const response = await fetch('modules/project_board/ajax_update_phase.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (!result.success) {
            alert(result.error || 'Error al actualizar el estado.');
            window.location.reload();
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
        window.location.reload();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
