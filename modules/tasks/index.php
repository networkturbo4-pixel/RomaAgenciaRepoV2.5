<?php
// modules/tasks/index.php — Centro de Tareas v2
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php?module=auth&action=login"); exit(); }
require_once 'includes/header.php';
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] === 'admin');

// Check granular permission
$canViewAll = $isAdmin;
if (!$isAdmin) {
    try {
        $stmtP = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'task_center_view_all_roles'");
        $stmtP->execute();
        $roles = json_decode($stmtP->fetchColumn() ?: '[]', true);
        $stmtR = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmtR->execute([$userId]);
        $canViewAll = in_array((int)$stmtR->fetchColumn(), $roles);
    } catch(Exception $e) {}
}

$users = [];
if ($canViewAll) {
    $stmtU = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
    $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
}
?>
<link rel="stylesheet" href="modules/tasks/style.css?v=<?php echo time(); ?>">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">

<!-- Pull to refresh indicator -->
<div class="tc-ptr-indicator" id="tc-ptr"><i class="ph ph-arrow-clockwise"></i>Soltar para actualizar</div>

<!-- Page Header -->
<div style="background:var(--bg-surface);border:1px solid rgba(150,150,150,0.12);border-radius:16px;padding:1.5rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.03);flex-wrap:wrap;gap:1rem;">
    <div style="display:flex;align-items:center;gap:1.25rem;">
        <div style="width:52px;height:52px;background:linear-gradient(135deg,var(--primary-color),#8b5cf6);border-radius:14px;display:flex;align-items:center;justify-content:center;">
            <i class="ph ph-kanban" style="font-size:1.6rem;color:white;"></i>
        </div>
        <div>
            <h1 style="margin:0;font-size:1.4rem;font-weight:800;color:var(--color-title);">Centro de Tareas</h1>
            <p style="margin:0.2rem 0 0;color:var(--text-muted);font-size:0.82rem;">Todas tus tareas en un solo lugar — organiza, prioriza y ejecuta.</p>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="tc-kpis" id="tc-kpis">
    <div class="tc-kpi"><div class="tc-skeleton" style="width:100%;height:48px"></div></div>
    <div class="tc-kpi"><div class="tc-skeleton" style="width:100%;height:48px"></div></div>
    <div class="tc-kpi"><div class="tc-skeleton" style="width:100%;height:48px"></div></div>
    <div class="tc-kpi"><div class="tc-skeleton" style="width:100%;height:48px"></div></div>
</div>

<!-- Workload (solo admin) -->
<?php if ($isAdmin): ?>
<div class="tc-workload" id="tc-workload" style="display:none;"></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="tc-toolbar">
    <div class="tc-filters">
        <button class="tc-pill active" data-filter="all" onclick="TC.setFilterSource('all',this)"><i class="ph ph-squares-four"></i> Todas</button>
        <button class="tc-pill" data-filter="task" onclick="TC.setFilterSource('task',this)"><span class="pill-dot" style="background:#64748b"></span> Tareas</button>
        <button class="tc-pill" data-filter="design_task" onclick="TC.setFilterSource('design_task',this)"><span class="pill-dot" style="background:#8b5cf6"></span> Diseño</button>
        <button class="tc-pill" data-filter="project_month" onclick="TC.setFilterSource('project_month',this)"><span class="pill-dot" style="background:#0ea5e9"></span> Proyectos</button>
    </div>
    <?php if ($canViewAll && !empty($users)): ?>
    <select class="tc-user-select" onchange="TC.setFilterUser(this.value)" title="Filtrar por usuario">
        <option value="all">👥 Todo el equipo</option>
        <option value="me">👤 Solo yo</option>
        <?php foreach ($users as $u): ?>
            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div class="tc-view-toggle">
        <button class="tc-view-btn active" data-view="kanban" onclick="TC.switchView('kanban')"><i class="ph ph-kanban"></i> Tablero</button>
        <button class="tc-view-btn" data-view="list" onclick="TC.switchView('list')"><i class="ph ph-list-dashes"></i> Lista</button>
        <button class="tc-view-btn" data-view="calendar" onclick="TC.switchView('calendar')"><i class="ph ph-calendar"></i> Calendario</button>
        <button onclick="TC.openCreateOC()" style="background:var(--primary-color);color:white;border:none;border-radius:8px;padding:0.4rem 1rem;font-weight:600;font-size:0.85rem;margin-left:0.5rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;box-shadow:0 2px 4px rgba(139,92,246,0.2);"><i class="ph ph-plus-circle"></i> Nueva Tarea</button>
    </div>
</div>

<!-- Kanban -->
<div class="tc-kanban" id="tc-kanban-view">
    <?php
    $cols = [
        ['pending','#f59e0b','Pendientes'],
        ['in_progress','#3b82f6','En Progreso'],
        ['in_review','#8b5cf6','En Revisión'],
        ['completed','#10b981','Completadas']
    ];
    foreach ($cols as $c): ?>
    <div class="tc-column col-<?php echo $c[0]; ?>">
        <div class="tc-col-header">
            <h3><span class="tc-col-dot" style="background:<?php echo $c[1]; ?>"></span><?php echo $c[2]; ?></h3>
            <span class="tc-col-count" id="tc-count-<?php echo $c[0]; ?>">0</span>
        </div>
        <div class="tc-col-body" id="tc-col-<?php echo $c[0]; ?>" data-status="<?php echo $c[0]; ?>"
             ondragover="TC.dov(event)" ondragleave="TC.dlv(event)" ondrop="TC.drp(event)"></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Mobile column dots -->
<div class="tc-col-dots">
    <div class="tc-col-dot-indicator active" onclick="TC.scrollToCol(0)"></div>
    <div class="tc-col-dot-indicator" onclick="TC.scrollToCol(1)"></div>
    <div class="tc-col-dot-indicator" onclick="TC.scrollToCol(2)"></div>
    <div class="tc-col-dot-indicator" onclick="TC.scrollToCol(3)"></div>
</div>

<!-- List View -->
<div class="tc-list" id="tc-list-view">
    <table class="tc-list-table">
        <thead><tr><th>Título</th><th>Estado</th><th>Prioridad</th><th>Fecha Límite</th><th>Asignados</th><th>Módulo</th></tr></thead>
        <tbody id="tc-list-body"></tbody>
    </table>
</div>

<!-- Calendar View -->
<div class="tc-calendar" id="tc-calendar-view">
    <div id="tc-cal"></div>
</div>

<!-- Offcanvas Detalle -->
<div class="tc-oc-overlay" id="tc-oc-overlay"></div>
<div class="tc-oc-panel" id="tc-oc-panel">
    <div class="tc-oc-header">
        <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:var(--color-title);">Detalle de Tarea</h3>
        <button class="btn-icon" onclick="TC.closeDetail()" style="border:none;background:transparent;cursor:pointer;color:var(--text-muted);font-size:1.3rem"><i class="ph ph-x"></i></button>
    </div>
    <div class="tc-oc-body" id="tc-oc-body"><div class="tc-empty"><i class="ph ph-cursor-click"></i>Selecciona una tarea</div></div>
</div>

<!-- Offcanvas Creación -->
<div class="tc-oc-panel" id="tc-create-panel" style="width: 420px;">
    <div class="tc-oc-header">
        <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:var(--color-title);">Nueva Tarea</h3>
        <button class="btn-icon" onclick="TC.closeCreateOC()" style="border:none;background:transparent;cursor:pointer;color:var(--text-muted);font-size:1.3rem"><i class="ph ph-x"></i></button>
    </div>
    <div class="tc-oc-body" id="tc-create-body" style="background:#f8fafc; padding: 1.5rem;">
        
        <div id="tc-create-step1">
            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-weight:700;margin-bottom:0.5rem;font-size:0.9rem;color:var(--color-title)">Título de la tarea</label>
                <input type="text" id="tc-create-title" placeholder="Ej: Programar contenido de..." style="width:100%;padding:0.75rem 1rem;border:1px solid #cbd5e1;border-radius:10px;font-size:0.95rem;box-shadow:0 1px 2px rgba(0,0,0,0.02);outline:none;transition:all 0.2s;">
            </div>
            
            <div style="margin-bottom:0.5rem;">
                <label style="display:block;font-weight:700;margin-bottom:0.5rem;font-size:0.9rem;color:var(--color-title)">Seleccionar base</label>
                <div style="position:relative;margin-bottom:0.8rem;">
                    <i class="ph ph-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
                    <input type="text" id="tc-create-search" placeholder="Buscar proyecto o diseño..." onkeyup="TC.filterTemplates(this.value)" style="width:100%;padding:0.6rem 0.8rem 0.6rem 2rem;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;outline:none;">
                </div>
            </div>
            <div id="tc-create-templates" style="display:flex;flex-direction:column;gap:0.6rem;max-height:450px;overflow-y:auto;padding-right:0.4rem;padding-bottom:1rem;">
                <!-- JS fill -->
            </div>
            <button onclick="TC.confirmCreateEmpty()" style="width:100%;padding:0.75rem;background:white;border:1px dashed #cbd5e1;border-radius:10px;color:var(--text-muted);font-weight:600;cursor:pointer;margin-top:1rem;transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.color='var(--primary-color)'" onmouseout="this.style.borderColor='#cbd5e1';this.style.color='var(--text-muted)'"><i class="ph ph-plus"></i> Crear sin base</button>
        </div>

        <div id="tc-create-step2" style="display:none;">
            <div class="tc-create-preview-box">
                <div class="tc-cp-header">
                    <h3 id="tc-cp-title" class="tc-cp-title-text">Titulo de la tarea</h3>
                    <div class="tc-cp-actions">
                        <button class="tc-cp-btn confirm" onclick="TC.confirmCreateTemplate()"><i class="ph ph-check"></i></button>
                    </div>
                </div>
                <div id="tc-cp-card-wrapper" class="tc-cp-card-wrapper">
                    <!-- Cloned card -->
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Toast container -->
<div class="tc-toast-container" id="tc-toasts"></div>

<script>window.TC_IS_ADMIN=<?php echo $canViewAll?'true':'false'; ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
<script src="modules/tasks/app.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
