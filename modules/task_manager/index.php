<?php
// modules/task_manager/index.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php?module=auth&action=login"); exit(); }
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
// Checking admin by role_id or permissions
$isAdmin = false;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    $isAdmin = true;
} elseif (in_array('admin', $_SESSION['user_permissions'] ?? [])) {
    $isAdmin = true;
} elseif (strtolower($_SESSION['user_role'] ?? '') === 'administrador' || strtolower($_SESSION['user_role'] ?? '') === 'admin') {
    $isAdmin = true;
}

// Fetch roles for the select dropdown
$roles = [];
try {
    $stmtRoles = $db->query("SELECT id, role_name FROM roles ORDER BY role_name ASC");
    if ($stmtRoles) {
        $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Throwable $e) {}

// Fetch users for the select dropdown
$users = [];
try {
    $stmtUsers = $db->query("SELECT id, name FROM users ORDER BY name ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}
?>
<link rel="stylesheet" href="modules/task_manager/style.css?v=<?php echo time(); ?>">

<div class="tm-header">
    <div class="tm-header-title">
        <div class="tm-icon-box"><i class="ph ph-kanban"></i></div>
        <div>
            <h1>Gestor de Tareas</h1>
            <p>Organiza, prioriza y ejecuta con eficiencia.</p>
        </div>
    </div>
    <div class="tm-header-actions">
        <button class="tm-btn-primary" onclick="TM.openCreateModal()">
            <i class="ph ph-plus-circle"></i> Nueva Tarea
        </button>
    </div>
</div>

<div class="tm-dashboard" id="tm-dashboard">
    <div class="tm-kpi tm-kpi-new">
        <div class="tm-kpi-icon"><i class="ph ph-sparkle"></i></div>
        <div class="tm-kpi-info">
            <span class="tm-kpi-val" id="kpi-new">0</span>
            <span class="tm-kpi-label">Nuevas</span>
        </div>
    </div>
    <div class="tm-kpi tm-kpi-pending">
        <div class="tm-kpi-icon"><i class="ph ph-clock"></i></div>
        <div class="tm-kpi-info">
            <span class="tm-kpi-val" id="kpi-pending">0</span>
            <span class="tm-kpi-label">Pendientes</span>
        </div>
    </div>
    <div class="tm-kpi tm-kpi-overdue">
        <div class="tm-kpi-icon"><i class="ph ph-warning-circle"></i></div>
        <div class="tm-kpi-info">
            <span class="tm-kpi-val" id="kpi-overdue">0</span>
            <span class="tm-kpi-label">Retrasadas</span>
        </div>
    </div>
    <div class="tm-kpi tm-kpi-completed">
        <div class="tm-kpi-icon"><i class="ph ph-check-circle"></i></div>
        <div class="tm-kpi-info">
            <span class="tm-kpi-val" id="kpi-completed">0</span>
            <span class="tm-kpi-label">Para Aprobar</span>
        </div>
    </div>
</div>

<div class="tm-kanban-board">
    <!-- Columna: Nuevo -->
    <div class="tm-column col-new">
        <div class="tm-col-header">
            <h3><span class="tm-dot" style="background:#3b82f6;"></span>Nuevo</h3>
            <span class="tm-col-count" id="count-new">0</span>
        </div>
        <div class="tm-col-body" id="col-new" data-status="new" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
    </div>
    <!-- Columna: Pendiente -->
    <div class="tm-column col-pending">
        <div class="tm-col-header">
            <h3><span class="tm-dot" style="background:#f59e0b;"></span>Pendiente</h3>
            <span class="tm-col-count" id="count-pending">0</span>
        </div>
        <div class="tm-col-body" id="col-pending" data-status="pending" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
    </div>
    <!-- Columna: Terminado (Requiere aprobación) -->
    <div class="tm-column col-completed">
        <div class="tm-col-header">
            <h3><span class="tm-dot" style="background:#10b981;"></span>Terminado</h3>
            <span class="tm-col-count" id="count-completed">0</span>
        </div>
        <div class="tm-col-body" id="col-completed" data-status="completed" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
    </div>
    <!-- Columna: Aprobado -->
    <div class="tm-column col-approved">
        <div class="tm-col-header">
            <h3><span class="tm-dot" style="background:#059669;"></span>Aprobado</h3>
            <span class="tm-col-count" id="count-approved">0</span>
        </div>
        <div class="tm-col-body" id="col-approved" data-status="approved" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
    </div>
</div>

<!-- Modal Creación (Startup UI) -->
<div class="tm-modal-overlay" id="tm-modal-create" style="display:none;">
    <div class="lumio-modal">
        <div class="lumio-accent-bar"></div>
        <form id="form-create-task" onsubmit="TM.submitTask(event)" class="lumio-form">
            <input type="hidden" id="tm-start-date">
            <select id="tm-assigned-roles" multiple style="display:none;"></select>

            <!-- Header -->
            <div class="lumio-header">
                <div class="lumio-header-left">
                    <button type="button" class="lumio-icon-btn lumio-close-btn" onclick="TM.closeModal('tm-modal-create')"><i class="ph ph-x"></i></button>
                    <span class="lumio-breadcrumb"><i class="ph ph-kanban"></i> Tareas <i class="ph ph-caret-right"></i> <b>Nueva Tarea</b></span>
                </div>
            </div>

            <!-- Body -->
            <div class="lumio-body">
                <input type="text" id="tm-title" class="lumio-title" placeholder="¿Qué necesitas hacer?" required>
                <input type="hidden" id="tm-desc">

                <div class="lumio-meta-grid">
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-calendar-blank"></i> Fecha Límite</div>
                        <div class="lumio-meta-value">
                            <div class="lumio-date-trigger" onclick="document.getElementById('tm-due-date').focus()">
                                <input type="text" id="tm-due-date" placeholder="Seleccionar fecha..." readonly>
                            </div>
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-flag"></i> Prioridad</div>
                        <div class="lumio-meta-value">
                            <select id="tm-priority" class="lumio-pill-select priority-pill">
                                <option value="low">Baja</option>
                                <option value="medium" selected>Media</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-tag"></i> Etiquetas</div>
                        <div class="lumio-meta-value">
                            <input type="text" id="tm-tags" class="lumio-tags-input" placeholder="Diseño, Marketing, Urgente...">
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-users-three"></i> Asignados</div>
                        <div class="lumio-meta-value">
                            <input type="text" id="tm-assigned-users" class="lumio-users-select" placeholder="Buscar y asignar personas...">
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-circle-dashed"></i> Estado</div>
                        <div class="lumio-meta-value">
                            <select id="tm-status" class="lumio-pill-select status-pill">
                                <option value="new" selected>Nuevo</option>
                                <option value="pending">Pendiente</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="lumio-tabs-nav" data-modal="create">
                    <button type="button" class="lumio-tab active" data-tab="0" onclick="TM.switchTab(this)"><i class="ph ph-text-align-left"></i> Detalles</button>
                    <button type="button" class="lumio-tab" data-tab="1" onclick="TM.switchTab(this)"><i class="ph ph-list-checks"></i> Subtareas</button>
                    <button type="button" class="lumio-tab" data-tab="2" onclick="TM.switchTab(this)"><i class="ph ph-paperclip"></i> Adjuntos</button>
                </div>
                
                <!-- Panel 0: Detalles -->
                <div class="lumio-tab-panel active" data-panel="0">
                    <div class="lumio-details-area">
                        <label class="lumio-section-label"><i class="ph ph-article"></i> Descripción</label>
                        <div id="tm-desc-editor" class="lumio-quill-editor"></div>
                    </div>
                </div>
                <!-- Panel 1: Subtareas -->
                <div class="lumio-tab-panel" data-panel="1" style="display:none;">
                    <div class="lumio-details-area">
                        <label class="lumio-section-label"><i class="ph ph-list-checks"></i> Subtareas</label>
                        <div id="tm-subtasks-list" class="lumio-dynamic-subtasks"></div>
                        <button type="button" class="lumio-add-subtask-btn" onclick="TM.addSubtaskInput('create')">
                            <i class="ph ph-plus-circle"></i> Añadir subtarea
                        </button>
                    </div>
                </div>
                <!-- Panel 2: Adjuntos -->
                <div class="lumio-tab-panel" data-panel="2" style="display:none;">
                    <div class="lumio-details-area" style="color: hsl(220,10%,55%); text-align:center; padding: 2rem 0;">
                        <i class="ph ph-cloud-arrow-up" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>
                        Próximamente: Adjuntar archivos a esta tarea.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="lumio-footer">
                <button type="button" class="lumio-cancel-btn" onclick="TM.closeModal('tm-modal-create')">Cancelar</button>
                <button type="submit" class="lumio-submit"><i class="ph ph-check-circle"></i> Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- AirDatepicker CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.css">
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.js"></script>

<!-- Modal Edición (Startup UI) -->
<div class="tm-modal-overlay" id="tm-modal-edit" style="display:none;">
    <div class="lumio-modal">
        <div class="lumio-accent-bar"></div>
        <form id="form-edit-task" onsubmit="TM.submitEditTask(event)" class="lumio-form">
            <input type="hidden" id="tm-edit-id">
            <input type="hidden" id="tm-edit-start-date">
            <select id="tm-edit-assigned-roles" multiple style="display:none;"></select>

            <!-- Header -->
            <div class="lumio-header">
                <div class="lumio-header-left">
                    <button type="button" class="lumio-icon-btn lumio-close-btn" onclick="TM.closeModal('tm-modal-edit')"><i class="ph ph-x"></i></button>
                    <span class="lumio-breadcrumb"><i class="ph ph-kanban"></i> Tareas <i class="ph ph-caret-right"></i> <b>Editar Tarea</b></span>
                </div>
                <div class="lumio-header-right">
                    <span class="lumio-task-id-badge" id="tm-edit-id-badge"></span>
                    <button type="button" class="lumio-icon-btn lumio-action-btn" onclick="TM.archiveTask()" title="Archivar Tarea"><i class="ph ph-archive"></i></button>
                    <button type="button" class="lumio-icon-btn lumio-action-btn lumio-danger-btn" onclick="TM.deleteTask()" title="Eliminar Tarea"><i class="ph ph-trash"></i></button>
                </div>
            </div>

            <!-- Body -->
            <div class="lumio-body">
                <input type="text" id="tm-edit-title" class="lumio-title" placeholder="Título de la tarea" required>
                <input type="hidden" id="tm-edit-desc">

                <div class="lumio-meta-grid">
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-calendar-blank"></i> Fecha Límite</div>
                        <div class="lumio-meta-value">
                            <div class="lumio-date-trigger" onclick="document.getElementById('tm-edit-due-date').focus()">
                                <input type="text" id="tm-edit-due-date" placeholder="Seleccionar fecha..." readonly>
                            </div>
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-flag"></i> Prioridad</div>
                        <div class="lumio-meta-value">
                            <select id="tm-edit-priority" class="lumio-pill-select priority-pill">
                                <option value="low">Baja</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-tag"></i> Etiquetas</div>
                        <div class="lumio-meta-value">
                            <input type="text" id="tm-edit-tags" class="lumio-tags-input" placeholder="Diseño, Marketing, Urgente...">
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-users-three"></i> Asignados</div>
                        <div class="lumio-meta-value">
                            <input type="text" id="tm-edit-assigned-users" class="lumio-users-select" placeholder="Buscar y asignar personas...">
                        </div>
                    </div>
                    <div class="lumio-meta-row">
                        <div class="lumio-meta-label"><i class="ph ph-circle-dashed"></i> Estado</div>
                        <div class="lumio-meta-value">
                            <select id="tm-edit-status" class="lumio-pill-select status-pill">
                                <option value="new">Nuevo</option>
                                <option value="pending">Pendiente</option>
                                <option value="overdue">Retrasado</option>
                                <option value="completed">Terminado</option>
                                <option value="approved">Aprobado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="lumio-tabs-nav" data-modal="edit">
                    <button type="button" class="lumio-tab active" data-tab="0" onclick="TM.switchTab(this)"><i class="ph ph-text-align-left"></i> Detalles</button>
                    <button type="button" class="lumio-tab" data-tab="1" onclick="TM.switchTab(this)"><i class="ph ph-list-checks"></i> Subtareas</button>
                    <button type="button" class="lumio-tab" data-tab="2" onclick="TM.switchTab(this)"><i class="ph ph-paperclip"></i> Adjuntos</button>
                </div>
                
                <!-- Panel 0: Detalles -->
                <div class="lumio-tab-panel active" data-panel="0">
                    <div class="lumio-details-area">
                        <label class="lumio-section-label"><i class="ph ph-article"></i> Descripción</label>
                        <div id="tm-edit-desc-editor" class="lumio-quill-editor"></div>
                    </div>
                </div>
                <!-- Panel 1: Subtareas -->
                <div class="lumio-tab-panel" data-panel="1" style="display:none;">
                    <div class="lumio-details-area">
                        <label class="lumio-section-label"><i class="ph ph-list-checks"></i> Subtareas</label>
                        <div id="tm-edit-subtasks-list" class="lumio-dynamic-subtasks"></div>
                        <button type="button" class="lumio-add-subtask-btn" onclick="TM.addSubtaskInput('edit')">
                            <i class="ph ph-plus-circle"></i> Añadir subtarea
                        </button>
                    </div>
                </div>
                <!-- Panel 2: Adjuntos -->
                <div class="lumio-tab-panel" data-panel="2" style="display:none;">
                    <div class="lumio-details-area" style="color: hsl(220,10%,55%); text-align:center; padding: 2rem 0;">
                        <i class="ph ph-cloud-arrow-up" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>
                        Próximamente: Adjuntar archivos a esta tarea.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="lumio-footer">
                <button type="button" class="lumio-cancel-btn" onclick="TM.closeModal('tm-modal-edit')">Cancelar</button>
                <button type="submit" class="lumio-submit"><i class="ph ph-floppy-disk"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
window.TM_IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
window.TM_USERS = [
<?php foreach($users as $u): ?>
    { "value": <?php echo json_encode($u['name']); ?>, "id": <?php echo $u['id']; ?> },
<?php endforeach; ?>
];
</script>
<script src="modules/task_manager/app.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
