<?php
// modules/task_manager/index.php — Centro Integral de Tareas y Objetivos Diarios
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php?module=auth&action=login"); exit(); }
require_once 'includes/header.php';

$userId = (int)$_SESSION['user_id'];
$isAdmin = false;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    $isAdmin = true;
} elseif (in_array('admin', $_SESSION['user_permissions'] ?? [])) {
    $isAdmin = true;
} elseif (strtolower($_SESSION['user_role'] ?? '') === 'administrador' || strtolower($_SESSION['user_role'] ?? '') === 'admin') {
    $isAdmin = true;
}

// Fetch users for dropdown
$users = [];
try {
    $stmtUsers = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}
?>
<link rel="stylesheet" href="modules/task_manager/style.css?v=<?php echo time(); ?>">
<style>
@media (max-width: 768px) {
    .content-wrapper {
        padding: 0.5rem 0.35rem !important;
        padding-top: calc(52px + 0.5rem) !important;
    }
}
</style>

<!-- AirDatepicker CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.css">
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@3.3.5/air-datepicker.min.js"></script>

<!-- Main Container -->
<div class="tm-container">

    <!-- Top Header -->
    <div class="tm-header">
        <div class="tm-header-title">
            <div class="tm-icon-box"><i class="ph ph-check-square-offset"></i></div>
            <div>
                <h1>Centro de Tareas & Objetivos</h1>
                <p>Planifica tareas diarias, semanales, evalúa tus metas y sincroniza con proyectos y áreas clave.</p>
            </div>
        </div>
        <div class="tm-header-actions">
            <button class="tm-btn-eval" onclick="TM.openDailyEvaluationModal()" title="Evaluar cumplimiento del día">
                <i class="ph ph-target"></i> <span>Evaluar Objetivos Diarios</span>
            </button>
            <button class="tm-btn-primary" onclick="TM.openCreateModal()">
                <i class="ph ph-plus-circle"></i> <span>Nueva Tarea</span>
            </button>
        </div>
    </div>

    <!-- KPIs Bar -->
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
                <span class="tm-kpi-label">En Curso / Pendientes</span>
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
                <span class="tm-kpi-label">Terminadas</span>
            </div>
        </div>
        <div class="tm-kpi tm-kpi-objectives" onclick="TM.switchView('daily')">
            <div class="tm-kpi-icon"><i class="ph ph-target"></i></div>
            <div class="tm-kpi-info">
                <span class="tm-kpi-val" id="kpi-daily-objectives">0 / 0</span>
                <span class="tm-kpi-label">Objetivos Diarios</span>
            </div>
        </div>
    </div>

    <!-- Toolbar: View Switcher & Granular Filters -->
    <div class="tm-toolbar">
        <!-- View Toggle Buttons -->
        <div class="tm-view-toggle">
            <button class="tm-view-btn active" data-view="kanban" onclick="TM.switchView('kanban')">
                <i class="ph ph-kanban"></i> <span>Tablero</span>
            </button>
            <button class="tm-view-btn" data-view="daily" onclick="TM.switchView('daily')">
                <i class="ph ph-target"></i> <span>Objetivos Diarios</span>
            </button>
            <button class="tm-view-btn" data-view="weekly" onclick="TM.switchView('weekly')">
                <i class="ph ph-calendar"></i> <span>Plan Semanal</span>
            </button>
            <button class="tm-view-btn" data-view="gantt" onclick="TM.switchView('gantt')">
                <i class="ph ph-chart-horizontal"></i> <span>Gantt</span>
            </button>
        </div>

        <!-- Quick Filters -->
        <div class="tm-filter-controls">
            <!-- User filter -->
            <div class="tm-select-wrapper">
                <i class="ph ph-user"></i>
                <select id="tm-filter-user" onchange="TM.setFilterUser(this.value)">
                    <option value="me">Solo yo</option>
                    <option value="all">Todo el equipo</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Project filter -->
            <div class="tm-select-wrapper">
                <i class="ph ph-folder"></i>
                <select id="tm-filter-project" onchange="TM.setFilterProject(this.value)">
                    <option value="all">Todos los Proyectos</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Category / Area Pills Bar -->
    <div class="tm-pills-bar">
        <button class="tm-pill-btn active" data-area="all" onclick="TM.setFilterArea('all', this)">
            <i class="ph ph-squares-four"></i> Todas <span class="tm-pill-count" id="count-pill-all">0</span>
        </button>
        <button class="tm-pill-btn area-brand" data-area="desarrollo_marca" onclick="TM.setFilterArea('desarrollo_marca', this)">
            <span class="tm-area-dot dot-brand"></span> Desarrollo de Marca <span class="tm-pill-count" id="count-pill-brand">0</span>
        </button>
        <button class="tm-pill-btn area-web" data-area="desarrollo_web" onclick="TM.setFilterArea('desarrollo_web', this)">
            <span class="tm-area-dot dot-web"></span> Desarrollo Web <span class="tm-pill-count" id="count-pill-web">0</span>
        </button>
        <button class="tm-pill-btn area-audio" data-area="audiovisual" onclick="TM.setFilterArea('audiovisual', this)">
            <span class="tm-area-dot dot-audio"></span> Audiovisual <span class="tm-pill-count" id="count-pill-audio">0</span>
        </button>
        <button class="tm-pill-btn area-pizarra" data-area="pizarras" onclick="TM.setFilterArea('pizarras', this)">
            <span class="tm-area-dot dot-pizarra"></span> Pizarras <span class="tm-pill-count" id="count-pill-pizarra">0</span>
        </button>
        <div class="tm-pill-separator"></div>
        <button class="tm-pill-btn freq-btn btn-pinned" data-freq="pinned" onclick="TM.setFilterFrequency('pinned', this)">
            <i class="ph-fill ph-push-pin"></i> Fijadas <span class="tm-pill-count" id="count-pill-pinned">0</span>
        </button>
        <button class="tm-pill-btn freq-btn" data-freq="daily" onclick="TM.setFilterFrequency('daily', this)">
            <i class="ph ph-lightning"></i> Tareas Diarias
        </button>
        <button class="tm-pill-btn freq-btn" data-freq="weekly" onclick="TM.setFilterFrequency('weekly', this)">
            <i class="ph ph-calendar-check"></i> Semanales
        </button>
        <button class="tm-pill-btn freq-btn" data-freq="objectives" onclick="TM.setFilterFrequency('objectives', this)">
            <i class="ph ph-flag"></i> Solo Objetivos
        </button>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- VIEW 1: TABLERO KANBAN                          -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="tm-kanban-board" id="tm-view-kanban">
        <!-- Columna: Nuevo -->
        <div class="tm-column col-new">
            <div class="tm-col-header">
                <h3><span class="tm-dot" style="background:#3b82f6;"></span>Nuevo</h3>
                <span class="tm-col-count" id="count-new">0</span>
            </div>
            <div class="tm-col-body" id="col-new" data-status="new" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
        </div>
        <!-- Columna: Pendiente / En Progreso -->
        <div class="tm-column col-pending">
            <div class="tm-col-header">
                <h3><span class="tm-dot" style="background:#f59e0b;"></span>Pendiente / En Curso</h3>
                <span class="tm-col-count" id="count-pending">0</span>
            </div>
            <div class="tm-col-body" id="col-pending" data-status="pending" ondragover="TM.dragOver(event)" ondragleave="TM.dragLeave(event)" ondrop="TM.drop(event)"></div>
        </div>
        <!-- Columna: Terminado -->
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

    <!-- ═══════════════════════════════════════════════ -->
    <!-- VIEW 2: OBJETIVOS DIARIOS & EVALUACIÓN          -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="tm-daily-view" id="tm-view-daily" style="display:none;">
        <div class="tm-daily-card-header">
            <div class="tm-daily-date-control">
                <button class="tm-date-nav-btn" onclick="TM.changeDailyDate(-1)"><i class="ph ph-caret-left"></i></button>
                <div class="tm-current-date-badge" onclick="document.getElementById('tm-daily-datepicker').focus()">
                    <i class="ph ph-calendar-check"></i>
                    <span id="tm-daily-date-text">Hoy</span>
                    <input type="text" id="tm-daily-datepicker" style="opacity:0; position:absolute; pointer-events:none; width:1px; height:1px;">
                </div>
                <button class="tm-date-nav-btn" onclick="TM.changeDailyDate(1)"><i class="ph ph-caret-right"></i></button>
                <button class="tm-today-quick-btn" onclick="TM.setDailyDateToday()">Ir a Hoy</button>
            </div>
            <div class="tm-daily-score-box" id="tm-daily-score-widget">
                <div class="tm-circular-progress-wrap">
                    <svg class="tm-progress-ring" width="56" height="56">
                        <circle class="tm-progress-ring-bg" stroke="rgba(150,150,150,0.2)" stroke-width="5" fill="transparent" r="23" cx="28" cy="28"/>
                        <circle class="tm-progress-ring-circle" id="tm-daily-circle-bar" stroke="var(--primary-color)" stroke-width="5" stroke-dasharray="144.5" stroke-dashoffset="144.5" stroke-linecap="round" fill="transparent" r="23" cx="28" cy="28"/>
                    </svg>
                    <span class="tm-progress-ring-text" id="tm-daily-circle-pct">0%</span>
                </div>
                <div class="tm-daily-score-details">
                    <h4 id="tm-daily-metrics-ratio">0 de 0 objetivos</h4>
                    <p id="tm-daily-metrics-status">Sin evaluar aún</p>
                </div>
                <button class="tm-btn-eval-sm" onclick="TM.openDailyEvaluationModal()">
                    <i class="ph ph-star"></i> Evaluar Día
                </button>
            </div>
        </div>

        <div class="tm-daily-content-grid">
            <!-- Daily Objectives Checklist -->
            <div class="tm-daily-list-card">
                <div class="tm-card-title-bar">
                    <h3><i class="ph ph-list-checks"></i> Objetivos del Día</h3>
                    <button class="tm-btn-text" onclick="TM.openCreateModal('daily')">
                        <i class="ph ph-plus"></i> Añadir Objetivo
                    </button>
                </div>
                <div id="tm-daily-objectives-list" class="tm-daily-objectives-items">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- Daily Evaluation & Feedback Summary -->
            <div class="tm-daily-eval-card">
                <div class="tm-card-title-bar">
                    <h3><i class="ph ph-medal"></i> Evaluación del Día</h3>
                    <span id="tm-daily-eval-badge" class="tm-status-pill">Pendiente</span>
                </div>
                <div class="tm-daily-eval-body" id="tm-daily-eval-body">
                    <p class="tm-empty-text">Aún no se ha registrado la evaluación para este día.</p>
                </div>
                <div class="tm-daily-eval-footer">
                    <button class="tm-btn-primary w-100" onclick="TM.openDailyEvaluationModal()">
                        <i class="ph ph-pencil-simple"></i> Registrar / Editar Evaluación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- VIEW 3: PLAN SEMANAL                            -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="tm-weekly-view" id="tm-view-weekly" style="display:none;">
        <div class="tm-weekly-grid" id="tm-weekly-grid">
            <!-- 7 Days populated dynamically by JS -->
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- VIEW 4: DIAGRAMA DE GANTT (DIARIO Y SEMANAL)    -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="tm-gantt-view" id="tm-view-gantt" style="display:none;">
        <!-- Gantt Toolbar: Scale, Group By, Navigation -->
        <div class="tm-gantt-toolbar">
            <div class="tm-gantt-toolbar-left">
                <!-- Scale switch: Daily / Weekly -->
                <div class="tm-gantt-scale-group">
                    <button type="button" class="tm-gantt-scale-btn active" data-scale="day" onclick="TM.setGanttScale('day')">
                        <i class="ph ph-calendar"></i> <span>Diario (Días)</span>
                    </button>
                    <button type="button" class="tm-gantt-scale-btn" data-scale="week" onclick="TM.setGanttScale('week')">
                        <i class="ph ph-calendar-blank"></i> <span>Semanal (Semanas)</span>
                    </button>
                </div>

                <div class="tm-gantt-divider"></div>

                <!-- Group By -->
                <div class="tm-gantt-group-wrapper">
                    <label><i class="ph ph-squares-four"></i> Agrupar por:</label>
                    <select id="tm-gantt-group-by" onchange="TM.setGanttGroupBy(this.value)">
                        <option value="project" selected>Proyecto / Marca</option>
                        <option value="area">Área de Trabajo</option>
                        <option value="user">Responsable</option>
                        <option value="status">Estado</option>
                        <option value="none">Sin agrupar (Plano)</option>
                    </select>
                </div>
            </div>

            <div class="tm-gantt-toolbar-right">
                <!-- Mobile Mode Toggle: Split / Timeline / List -->
                <div class="tm-gantt-mobile-toggle">
                    <button type="button" class="tm-gantt-mode-btn active" data-mode="split" onclick="TM.setGanttMobileMode('split')" title="Vista dividida (Nombres + Barras)">
                        <i class="ph ph-columns"></i> <span>Dividido</span>
                    </button>
                    <button type="button" class="tm-gantt-mode-btn" data-mode="timeline" onclick="TM.setGanttMobileMode('timeline')" title="Solo Barras de Cronograma">
                        <i class="ph ph-chart-horizontal"></i> <span>Barras</span>
                    </button>
                    <button type="button" class="tm-gantt-mode-btn" data-mode="list" onclick="TM.setGanttMobileMode('list')" title="Solo Lista de Tareas">
                        <i class="ph ph-list-dashes"></i> <span>Lista</span>
                    </button>
                </div>

                <!-- Date Range Navigator -->
                <div class="tm-gantt-nav-group">
                    <button type="button" class="tm-gantt-nav-btn" onclick="TM.navGanttRange(-1)" title="Periodo Anterior">
                        <i class="ph ph-caret-left"></i>
                    </button>
                    <button type="button" class="tm-gantt-today-btn" onclick="TM.setGanttToday()" title="Ir a la fecha actual">
                        <i class="ph ph-crosshair"></i> Hoy
                    </button>
                    <button type="button" class="tm-gantt-nav-btn" onclick="TM.navGanttRange(1)" title="Periodo Siguiente">
                        <i class="ph ph-caret-right"></i>
                    </button>
                    <span class="tm-gantt-range-label" id="tm-gantt-range-label">Septiembre 2026</span>
                </div>

                <!-- Fullscreen Toggle Button -->
                <button type="button" class="tm-gantt-fullscreen-btn" onclick="TM.toggleGanttFullscreen()" title="Pantalla Completa">
                    <i class="ph ph-arrows-out-simple"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Landscape Tip -->
        <div class="tm-gantt-mobile-tip">
            <i class="ph-bold ph-device-rotate"></i>
            <span>Tip: Gira tu teléfono en horizontal para ver el cronograma panorámico completo</span>
        </div>

        <!-- Gantt Main Container (Two Synchronized Columns) -->
        <div class="tm-gantt-container mode-split" id="tm-gantt-container">
            <!-- Left Side: Task Tree / Sidebar -->
            <div class="tm-gantt-sidebar" id="tm-gantt-sidebar">
                <div class="tm-gantt-sidebar-header">
                    <span class="tm-gantt-col-title">Tarea / Entregable</span>
                    <span class="tm-gantt-col-dates">Duración / Fechas</span>
                </div>
                <div class="tm-gantt-sidebar-body" id="tm-gantt-sidebar-body">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>

            <!-- Right Side: Scrollable Timeline Grid -->
            <div class="tm-gantt-timeline-wrapper" id="tm-gantt-timeline-wrapper">
                <div class="tm-gantt-timeline" id="tm-gantt-timeline">
                    <!-- Timeline Header (Months / Weeks / Days) -->
                    <div class="tm-gantt-timeline-header" id="tm-gantt-timeline-header"></div>
                    <!-- Timeline Body (Grid & Task Bars) -->
                    <div class="tm-gantt-timeline-body" id="tm-gantt-timeline-body">
                        <!-- Populated dynamically by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- MODAL: CREAR / EDITAR TAREA                         -->
<!-- ═══════════════════════════════════════════════════ -->
<div class="tm-modal-overlay tm-app-modal-overlay" id="tm-modal-task" style="display:none;" onclick="if(event.target===this)TM.closeModal('tm-modal-task')">
    <!-- Overlay visual cuando se arrastran archivos sobre la ventana -->
    <div class="tm-modal-drag-overlay" id="tm-modal-drag-overlay" style="display:none;">
        <div class="tm-modal-drag-content">
            <div class="tm-mdo-icon-wrap"><i class="ph-bold ph-file-arrow-up"></i></div>
            <h4>Suelta tus imágenes o documentos aquí</h4>
            <p>Se subirán y adjuntarán automáticamente a esta tarea</p>
        </div>
    </div>

    <div class="lumio-modal lumio-modal-wide tm-app-modal-window">
        <div class="lumio-accent-bar" id="tm-modal-accent"></div>
        <form id="form-task" onsubmit="TM.saveTask(event)" class="lumio-form tm-app-form">
            <input type="hidden" id="tm-task-id">
            <select id="tm-assigned-roles" multiple style="display:none;"></select>

            <!-- Header Superior Estilo App Moderna con Modos Especiales Fijados -->
            <div class="lumio-header tm-app-modal-header">
                <div class="lumio-header-left tm-app-header-left">
                    <button type="button" class="lumio-icon-btn lumio-close-btn tm-app-icon-btn" onclick="TM.closeModal('tm-modal-task')" title="Cerrar modal (Esc)">
                        <i class="ph ph-x"></i>
                    </button>
                    <div class="tm-app-header-titles">
                        <h3 id="tm-modal-title" class="tm-app-modal-title">Nueva Tarea</h3>
                        <span class="tm-app-badge-tag"><i class="ph-bold ph-kanban"></i> Gestor de Tareas</span>
                        <span class="lumio-task-id-badge" id="tm-task-id-badge" style="display:none;"></span>
                    </div>
                </div>

                <!-- Modos Especiales FIJADOS en la Cabecera (Meta del Día & Fijar en Tablero) - En una sola línea sin card -->
                <div class="tm-app-header-center">
                    <!-- Meta del Día -->
                    <div class="tm-header-inline-toggle tm-objective-card" id="tm-objective-card" onclick="TM.toggleDailyObjectiveFromCard(event)" title="Fijar como meta principal del día">
                        <i class="ph-bold ph-target tm-header-toggle-icon"></i>
                        <span class="tm-header-toggle-label">Meta del Día</span>
                        <span class="tm-objective-badge" id="tm-objective-badge" style="display:none;">Prioritaria</span>
                        <span class="tm-objective-subtitle" id="tm-objective-text" style="display:none;"></span>
                        <!-- Botón selector de fecha rápida de la meta -->
                        <div class="tm-hsp-date-badge" id="tm-header-date-trigger" onclick="event.stopPropagation(); TM.toggleObjectiveDateDropdown();" style="display:none;" title="Seleccionar fecha de la meta">
                            <i class="ph-bold ph-calendar-blank"></i>
                            <span id="tm-header-date-display">Hoy</span>
                        </div>
                        <label class="tm-switch tm-switch-inline tm-switch-objective" onclick="event.stopPropagation()" title="Activar / Desactivar Objetivo Diario">
                            <input type="checkbox" id="tm-is-daily-objective" onchange="TM.onDailyObjectiveToggle(this.checked)">
                            <span class="tm-slider"></span>
                        </label>
                    </div>

                    <div class="tm-header-inline-divider"></div>

                    <!-- Fijar en Tablero -->
                    <div class="tm-header-inline-toggle tm-pinned-card" id="tm-pinned-card" onclick="TM.togglePinnedFromCard(event)" title="Anclar arriba y repetir a diario">
                        <i class="ph-bold ph-push-pin tm-header-toggle-icon"></i>
                        <span class="tm-header-toggle-label">Fijar en Tablero</span>
                        <span class="tm-objective-badge tm-pinned-badge-active" id="tm-pinned-badge" style="display:none;">Fijada</span>
                        <span class="tm-objective-subtitle" id="tm-pinned-text" style="display:none;"></span>
                        <label class="tm-switch tm-switch-inline tm-switch-pinned" onclick="event.stopPropagation()" title="Fijar en el tablero y repetir a diario">
                            <input type="checkbox" id="tm-is-pinned" onchange="TM.onPinnedToggle(this.checked)">
                            <span class="tm-slider"></span>
                        </label>
                    </div>

                    <!-- Panel Flotante de Selección de Fecha de Meta -->
                    <div class="tm-objective-date-panel tm-app-header-date-popover" id="tm-objective-date-panel" style="display:none;">
                        <div class="tm-objective-shortcuts">
                            <button type="button" class="tm-obj-pill-btn active" id="btn-obj-today" onclick="TM.setObjectiveQuickDate('today')">
                                <i class="ph-bold ph-calendar-check"></i> Hoy
                            </button>
                            <button type="button" class="tm-obj-pill-btn" id="btn-obj-tomorrow" onclick="TM.setObjectiveQuickDate('tomorrow')">
                                <i class="ph-bold ph-calendar-plus"></i> Mañana
                            </button>
                            <button type="button" class="tm-obj-pill-btn" id="btn-obj-custom" onclick="TM.openObjectiveDatePicker()">
                                <i class="ph-bold ph-calendar"></i> Otra Fecha
                            </button>
                        </div>
                        <div class="tm-objective-date-input-wrap" onclick="TM.openObjectiveDatePicker()">
                            <i class="ph-bold ph-calendar-blank tm-objective-calendar-icon"></i>
                            <input type="text" id="tm-objective-date-display" class="tm-objective-date-input" placeholder="Seleccionar fecha..." readonly>
                            <input type="hidden" id="tm-objective-date">
                        </div>
                    </div>
                </div>

                <div class="lumio-header-right tm-app-header-right">
                    <div id="tm-edit-actions" class="tm-app-edit-actions" style="display:none;">
                        <button type="button" class="lumio-icon-btn lumio-action-btn tm-app-btn-archive" onclick="TM.archiveTask()" title="Archivar Tarea">
                            <i class="ph ph-archive"></i> <span>Archivar</span>
                        </button>
                        <button type="button" class="lumio-icon-btn lumio-action-btn lumio-danger-btn tm-app-btn-delete" onclick="TM.deleteTask()" title="Eliminar Tarea">
                            <i class="ph ph-trash"></i> <span>Eliminar</span>
                        </button>
                    </div>
                    <button type="button" class="lumio-icon-btn tm-app-icon-btn" id="btn-toggle-modal-size" onclick="TM.toggleModalSize()" title="Alternar tamaño completo">
                        <i class="ph ph-arrows-out-simple"></i>
                    </button>
                </div>
            </div>

            <!-- Body: Grid Split 2 Columnas Estilo App Profesional -->
            <div class="lumio-body tm-app-modal-body">
                <div class="tm-app-modal-grid">
                    
                    <!-- COLUMNA PRINCIPAL (Izquierda: Título, Panel de Sincronización, Editor Quill, Adjuntos, Subtareas) -->
                    <div class="tm-app-modal-main">
                        
                        <!-- Input de Título Principal con estilo App Documental -->
                        <div class="tm-app-title-wrapper">
                            <input type="text" id="tm-title" class="lumio-title tm-app-title-field" 
                                placeholder="¿Qué necesitas lograr? Escribe el título aquí..." required autocomplete="off">
                            <input type="hidden" id="tm-desc">
                        </div>

                        <!-- Panel de Sincronización en Vivo (Condicional) -->
                        <div id="tm-sync-panel" class="tm-sync-card tm-app-sync-card" style="display:none;">
                            <div class="tm-sync-card-header">
                                <div class="tm-sync-badge-title">
                                    <i class="ph-bold ph-arrows-clockwise"></i>
                                    <span>Sincronización en Vivo</span>
                                </div>
                                <span id="tm-sync-entity-type-badge" class="tm-sync-chip">Mes de Calendario</span>
                            </div>
                            
                            <div class="tm-sync-card-body">
                                <div class="tm-sync-timing-grid">
                                    <div class="tm-sync-timing-item">
                                        <span class="tm-sync-small-label"><i class="ph ph-calendar-check"></i> Plazo del Proyecto:</span>
                                        <span id="tm-sync-parent-deadline" class="tm-sync-deadline-val">--</span>
                                    </div>
                                    <div class="tm-sync-timing-item">
                                        <span class="tm-sync-small-label"><i class="ph ph-hourglass-high"></i> Cronómetro del Proyecto:</span>
                                        <div id="tm-sync-parent-timer" class="tm-timer-pill" data-due="" data-start="" data-status="">
                                            <i class="ph-fill ph-hourglass-high"></i>
                                            <span class="timer-text">Calculando...</span>
                                        </div>
                                    </div>
                                    <button type="button" class="tm-btn-sync-action" onclick="TM.syncWithProjectDeadline()" title="Heredar y aplicar esta fecha a la tarea">
                                        <i class="ph-bold ph-calendar-plus"></i> Sincronizar fecha
                                    </button>
                                </div>

                                <div id="tm-sync-drift-alert" class="tm-sync-drift-alert" style="display:none;">
                                    <i class="ph-bold ph-warning-circle"></i>
                                    <span><strong>Desfase detectado:</strong> La fecha de esta tarea excede el límite del proyecto vinculado.</span>
                                </div>

                                <div class="tm-sync-phase-row">
                                    <div class="tm-sync-phase-label">
                                        <i class="ph ph-git-branch"></i> <span>Fase de Proceso del Proyecto:</span>
                                    </div>
                                    <div class="tm-sync-phase-controls">
                                        <select id="tm-sync-phase-select" class="lumio-pill-select" onchange="TM.onSyncPhaseSelectChange(this.value)">
                                            <!-- Opciones dinámicas -->
                                        </select>
                                        <button type="button" class="tm-btn-phase-save" onclick="TM.saveEntityProcessPhase()" title="Actualizar la fase en el módulo vinculado">
                                            <i class="ph-bold ph-check"></i> Actualizar Fase
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestañas de Contenido (Tabs) -->
                        <div class="lumio-tabs-nav tm-app-tabs-nav" data-modal="task">
                            <button type="button" class="lumio-tab active tm-app-tab-btn" data-tab="0" onclick="TM.switchTab(this)">
                                <i class="ph-bold ph-text-align-left"></i> <span>Descripción y Criterios</span>
                            </button>
                            <button type="button" class="lumio-tab tm-app-tab-btn" data-tab="1" onclick="TM.switchTab(this)">
                                <i class="ph-bold ph-list-checks"></i> <span>Subtareas & Checklist</span>
                            </button>
                        </div>
                        
                        <!-- Panel 0: Descripción con Editor Enriquecido Quill -->
                        <div class="lumio-tab-panel active tm-app-tab-panel" data-panel="0">
                            <div class="lumio-details-area tm-app-details-area">
                                <label class="lumio-section-label tm-app-section-title">
                                    <i class="ph-bold ph-article"></i> Descripción y Criterios de Aceptación
                                </label>
                                <div id="tm-desc-editor" class="lumio-quill-editor tm-app-quill-container"></div>
                            </div>
                        </div>

                        <!-- Panel 1: Subtareas & Checklist dinámico -->
                        <div class="lumio-tab-panel tm-app-tab-panel" data-panel="1" style="display:none;">
                            <div class="lumio-details-area tm-app-details-area">
                                <div class="tm-app-subtasks-header">
                                    <label class="lumio-section-label tm-app-section-title" style="margin:0;">
                                        <i class="ph-bold ph-list-checks"></i> Subtareas & Checklist
                                    </label>
                                    <button type="button" class="lumio-add-subtask-btn tm-app-add-subtask-inline" onclick="TM.addSubtaskInput()">
                                        <i class="ph-bold ph-plus-circle"></i> Añadir subtarea
                                    </button>
                                </div>
                                <div id="tm-subtasks-list" class="lumio-dynamic-subtasks tm-app-dynamic-subtasks"></div>
                            </div>
                        </div>

                        <!-- SECCIÓN DE ARCHIVOS Y ADJUNTOS (Google Drive + Formatos Adobe + Ctrl+V) -->
                        <div class="tm-app-attachments-section">
                            <div class="tm-app-attachments-header">
                                <label class="lumio-section-label tm-app-section-title" style="margin:0;">
                                    <i class="ph-bold ph-paperclip"></i> Archivos y Adjuntos 
                                    <span class="tm-meta-counter-badge" id="tm-attachments-count">0</span>
                                </label>
                                <span class="tm-app-attachments-hint">
                                    <kbd class="tm-kbd">Ctrl + V</kbd> para pegar captura o arrastra aquí
                                </span>
                            </div>

                            <!-- Barra de Integración Google Drive (Almacenamiento Cloud sin saturar servidor) -->
                            <div class="tm-app-drive-bar" id="tm-drive-bar">
                                <div class="tm-drive-bar-left">
                                    <div class="tm-drive-logo-icon">
                                        <svg viewBox="0 0 87.3 78" width="22" height="22">
                                            <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                                            <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44c-.8 1.4-1.2 2.95-1.2 4.5h27.5z" fill="#00ac47"/>
                                            <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                                            <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                                            <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                                            <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                                        </svg>
                                    </div>
                                    <div class="tm-drive-info">
                                        <div class="tm-drive-title-row">
                                            <span class="tm-drive-title">Google Drive</span>
                                            <span class="tm-drive-chip">Nube · 0 Bytes Servidor</span>
                                        </div>
                                        <span class="tm-drive-desc" id="tm-drive-desc">Almacena archivos pesados y proyectos Adobe sin saturar el servidor</span>
                                    </div>
                                </div>
                                <div class="tm-drive-bar-right">
                                    <!-- Botón Crear Carpeta (Desconectado) -->
                                    <button type="button" class="tm-btn-drive-connect" id="tm-btn-create-drive" onclick="TM.createTaskDriveFolder()">
                                        <i class="ph-bold ph-folder-plus"></i> Crear Carpeta en Drive
                                    </button>
                                    <!-- Grupo Conectado -->
                                    <div class="tm-drive-connected-group" id="tm-drive-connected-group" style="display:none;">
                                        <span class="tm-drive-badge-synced">
                                            <i class="ph-fill ph-check-circle"></i> Carpeta Activa
                                        </span>
                                        <a href="#" target="_blank" rel="noopener noreferrer" class="tm-btn-drive-open" id="tm-btn-open-drive" title="Abrir carpeta de la tarea en Google Drive">
                                            <i class="ph-bold ph-arrow-square-out"></i> Abrir en Drive
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="tm-drive-folder-id" value="">
                            <input type="hidden" id="tm-drive-folder-url" value="">

                            <!-- Dropzone de arrastrar y soltar -->
                            <div class="tm-app-dropzone" id="tm-app-dropzone" onclick="document.getElementById('tm-file-input').click()">
                                <input type="file" id="tm-file-input" multiple accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.pdf,.psd,.psb,.ai,.eps,.ait,.indd,.idml,.indt,.prproj,.mogrt,.aep,.aepx,.xd,.sesx,.dng,.lrcat,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z,.mp4,.mov" style="display:none;" onchange="TM.handleFileInputChange(event)">
                                <div class="tm-dropzone-inner">
                                    <div class="tm-dropzone-icon-circle">
                                        <i class="ph-bold ph-cloud-arrow-up"></i>
                                    </div>
                                    <div class="tm-dropzone-texts">
                                        <span class="tm-dropzone-title"><strong>Haz clic para subir</strong> o arrastra archivos aquí</span>
                                        <span class="tm-dropzone-sub">Compatible con <strong>Formatos Adobe</strong> (PSD, AI, InDesign, Premiere, After Effects, XD, PDF) e imágenes/docs • Soporta <kbd class="tm-kbd">Ctrl+V</kbd></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Grid de Miniaturas y Archivos -->
                            <div class="tm-app-attachments-grid" id="tm-attachments-grid"></div>
                            <input type="hidden" id="tm-attachments-json" value="[]">
                        </div>

                    </div>

                    <!-- COLUMNA LATERAL (Derecha: Inspector de Metadatos y Propiedades) -->
                    <div class="tm-app-modal-sidebar">
                        
                        <!-- Bloque 1: Flujo de Trabajo (Estado & Prioridad) -->
                        <div class="tm-app-sidebar-card">
                            <div class="tm-app-sidebar-card-title">
                                <i class="ph-bold ph-sliders"></i> Estado & Prioridad
                            </div>
                            <div class="tm-app-sidebar-grid-2">
                                <div class="lumio-meta-row">
                                    <div class="lumio-meta-label"><i class="ph ph-circle-dashed"></i> Estado</div>
                                    <div class="lumio-meta-value">
                                        <select id="tm-status" class="lumio-pill-select status-pill">
                                            <option value="new" selected>Nuevo</option>
                                            <option value="pending">Pendiente / En Curso</option>
                                            <option value="completed">Terminado</option>
                                            <option value="approved">Aprobado</option>
                                        </select>
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
                            </div>
                        </div>

                        <!-- Bloque 2: Fechas & Cronómetro -->
                        <div class="tm-app-sidebar-card">
                            <div class="tm-app-sidebar-card-title">
                                <i class="ph-bold ph-calendar"></i> Fechas & Cronómetro
                            </div>
                            <div class="tm-app-sidebar-grid-2">
                                <div class="lumio-meta-row">
                                    <div class="lumio-meta-label"><i class="ph ph-calendar-plus"></i> Fecha Inicio</div>
                                    <div class="lumio-meta-value">
                                        <div class="lumio-date-trigger" onclick="document.getElementById('tm-start-date').focus()">
                                            <input type="text" id="tm-start-date" placeholder="Seleccionar..." readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="lumio-meta-row">
                                    <div class="lumio-meta-label"><i class="ph ph-calendar-blank"></i> Fecha Límite</div>
                                    <div class="lumio-meta-value" style="display: flex; align-items: center; gap: 6px;">
                                        <div class="lumio-date-trigger" style="flex: 1;" onclick="document.getElementById('tm-due-date').focus()">
                                            <input type="text" id="tm-due-date" placeholder="Seleccionar..." readonly onchange="TM.checkDeadlineDrift()">
                                        </div>
                                        <div id="tm-modal-task-timer" class="tm-timer-pill" data-due="" style="display:none;" title="Tiempo restante">
                                            <i class="ph-fill ph-hourglass-high"></i>
                                            <span class="timer-text">--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque 3: Miembros Asignados -->
                        <div class="tm-app-sidebar-card tm-assigned-meta-row">
                            <div class="tm-app-sidebar-card-title">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <i class="ph-bold ph-users-three"></i> Asignados
                                </div>
                                <span class="tm-meta-counter-badge" id="tm-assigned-count">0</span>
                            </div>
                            <div class="tm-assigned-card" id="tm-assigned-container">
                                <!-- Chips de personas asignadas -->
                                <div class="tm-assigned-chips" id="tm-assigned-chips"></div>

                                <!-- Selector desplegable de personas para asignar -->
                                <div class="tm-assigned-select-row">
                                    <div class="tm-assigned-select-wrap">
                                        <i class="ph ph-user-plus tm-assigned-select-icon"></i>
                                        <select id="tm-user-select-add" class="tm-assigned-select" onchange="TM.onUserSelectChange(this.value)">
                                            <option value="">+ Asignar a un miembro...</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Barra de Asignados al Proyecto vinculado -->
                                <div class="tm-project-members-bar" id="tm-project-members-bar" style="display:none;">
                                    <div class="tm-pm-header">
                                        <span class="tm-pm-title"><i class="ph-bold ph-buildings"></i> Del proyecto:</span>
                                        <button type="button" class="tm-btn-assign-all-pm" onclick="TM.assignAllProjectMembers()" title="Asignar todos los miembros del proyecto a la tarea">
                                            <i class="ph-bold ph-check-all"></i> Todos
                                        </button>
                                    </div>
                                    <div class="tm-pm-chips" id="tm-project-members-chips"></div>
                                </div>
                            </div>
                            <input type="hidden" id="tm-assigned-users">
                        </div>

                        <!-- Bloque 4: Clasificación & Vinculación de Proyectos -->
                        <div class="tm-app-sidebar-card">
                            <div class="tm-app-sidebar-card-title">
                                <i class="ph-bold ph-git-fork"></i> Clasificación & Contexto
                            </div>
                            <div class="tm-app-sidebar-stack">
                                <div class="tm-app-sidebar-grid-2">
                                    <div class="lumio-meta-row">
                                        <div class="lumio-meta-label"><i class="ph ph-repeat"></i> Frecuencia</div>
                                        <div class="lumio-meta-value">
                                            <select id="tm-frequency" class="lumio-pill-select" onchange="TM.onFrequencyChange(this.value)">
                                                <option value="one_time">Puntual / Por Entrega</option>
                                                <option value="daily">Diaria (Recurrente)</option>
                                                <option value="weekly">Semanal</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="lumio-meta-row">
                                        <div class="lumio-meta-label"><i class="ph ph-briefcase"></i> Área</div>
                                        <div class="lumio-meta-value">
                                            <select id="tm-area" class="lumio-pill-select" onchange="TM.onAreaChange(this.value)">
                                                <option value="general">General / Operativa</option>
                                                <option value="desarrollo_marca">Desarrollo de Marca</option>
                                                <option value="desarrollo_web">Desarrollo Web</option>
                                                <option value="audiovisual">Audiovisual</option>
                                                <option value="pizarras">Pizarras</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Proyecto Activo -->
                                <div class="lumio-meta-row" id="row-project">
                                    <div class="lumio-meta-label"><i class="ph ph-folder"></i> Proyecto Vinculado</div>
                                    <div class="lumio-meta-value">
                                        <select id="tm-project-id" class="lumio-pill-select" onchange="TM.onProjectChange(this.value)">
                                            <option value="">-- Sin Vincular / General --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mes de Calendario Activo -->
                                <div class="lumio-meta-row" id="row-calendar-month">
                                    <div class="lumio-meta-label"><i class="ph ph-calendar-blank"></i> Mes Activo</div>
                                    <div class="lumio-meta-value" style="display:flex; align-items:center; gap:8px;">
                                        <select id="tm-project-month-id" class="lumio-pill-select" style="flex:1;" onchange="TM.onProjectMonthChange(this.value)">
                                            <option value="">-- Seleccionar Mes de Calendario --</option>
                                        </select>
                                        <button type="button" id="btn-open-month" class="tm-btn-open-ext" onclick="TM.openLinkedMonth()" title="Abrir Mes en Tablero" style="display:none;">
                                            <i class="ph-bold ph-arrow-square-out"></i> Abrir
                                        </button>
                                    </div>
                                </div>

                                <!-- Proyecto de Marca (Condicional) -->
                                <div class="lumio-meta-row" id="row-brand-project" style="display:none;">
                                    <div class="lumio-meta-label"><i class="ph ph-paint-brush"></i> Proy. Marca</div>
                                    <div class="lumio-meta-value" style="display:flex; align-items:center; gap:8px;">
                                        <select id="tm-brand-project-id" class="lumio-pill-select" style="flex:1;" onchange="TM.onBrandProjectChange(this.value)">
                                            <option value="">-- Seleccionar Identidad / Marca --</option>
                                        </select>
                                        <button type="button" id="btn-open-brand" class="tm-btn-open-ext" onclick="TM.openLinkedBrand()" title="Abrir Proyecto de Marca en nueva pestaña" style="display:none;">
                                            <i class="ph-bold ph-arrow-square-out"></i> Abrir
                                        </button>
                                    </div>
                                </div>

                                <!-- Fase / Grupo de Marca (Condicional) -->
                                <div class="lumio-meta-row" id="row-brand-group" style="display:none;">
                                    <div class="lumio-meta-label"><i class="ph ph-git-branch"></i> Fase / Etapa</div>
                                    <div class="lumio-meta-value">
                                        <select id="tm-brand-group-id" class="lumio-pill-select" onchange="TM.onBrandGroupChange(this.value)">
                                            <option value="">-- Seleccionar Fase de Marca --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Servicio Web/Audiovisual (Condicional) -->
                                <div class="lumio-meta-row" id="row-project-service" style="display:none;">
                                    <div class="lumio-meta-label"><i class="ph ph-gear"></i> Servicio Web/Audio</div>
                                    <div class="lumio-meta-value" style="display:flex; align-items:center; gap:8px;">
                                        <select id="tm-project-service-id" class="lumio-pill-select" style="flex:1;" onchange="TM.onProjectServiceChange(this.value)">
                                            <option value="">-- Seleccionar Servicio / Entregable --</option>
                                        </select>
                                        <button type="button" id="btn-open-service" class="tm-btn-open-ext" onclick="TM.openLinkedService()" title="Abrir Servicio en nueva pestaña" style="display:none;">
                                            <i class="ph-bold ph-arrow-square-out"></i> Abrir
                                        </button>
                                    </div>
                                </div>

                                <!-- Pizarra Vinculada (Condicional) -->
                                <div class="lumio-meta-row" id="row-whiteboard" style="display:none;">
                                    <div class="lumio-meta-label"><i class="ph ph-chalkboard-simple"></i> Pizarra Vinculada</div>
                                    <div class="lumio-meta-value" style="display:flex; align-items:center; gap:8px;">
                                        <select id="tm-whiteboard-id" class="lumio-pill-select" style="flex:1;" onchange="TM.onWhiteboardChange(this.value)">
                                            <option value="">-- Sin Vincular / Seleccionar Pizarra --</option>
                                        </select>
                                        <button type="button" id="btn-open-whiteboard" class="tm-btn-open-ext" onclick="TM.openLinkedWhiteboard()" title="Abrir Pizarra en nueva pestaña" style="display:none;">
                                            <i class="ph-bold ph-arrow-square-out"></i> Abrir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque 5: Etiquetas -->
                        <div class="tm-app-sidebar-card tm-tags-meta-row">
                            <div class="tm-app-sidebar-card-title">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <i class="ph-bold ph-tag"></i> Etiquetas
                                </div>
                                <span class="tm-meta-counter-badge" id="tm-tags-count">0</span>
                            </div>
                            <div class="tm-tags-card" id="tm-tags-container">
                                <div class="tm-tags-chips-wrap" id="tm-tags-chips-wrap"></div>

                                <div class="tm-tags-create-row">
                                    <div class="tm-tags-input-box">
                                        <i class="ph ph-tag tm-tags-input-icon"></i>
                                        <input type="text" id="tm-tag-new-input" placeholder="Etiqueta y presiona Enter..." onkeydown="TM.onTagInputKeydown(event)">
                                    </div>
                                    <button type="button" class="tm-btn-add-tag" onclick="TM.addTagFromInput()" title="Agregar etiqueta">
                                        <i class="ph-bold ph-plus"></i>
                                    </button>
                                </div>

                                <div class="tm-tags-suggestions-row">
                                    <span class="tm-tags-sug-label"><i class="ph ph-sparkle"></i> Sugeridas:</span>
                                    <div class="tm-tags-sug-pills" id="tm-tags-sug-pills"></div>
                                </div>
                            </div>
                            <input type="hidden" id="tm-tags">
                        </div>

                    </div>

                </div>
            </div>

            <!-- Footer Inferior Estilo App Moderna -->
            <div class="lumio-footer tm-app-modal-footer">
                <div class="tm-app-footer-shortcuts">
                    <span class="tm-app-kbd-hint"><kbd class="tm-kbd">Esc</kbd> Cancelar</span>
                    <span class="tm-app-kbd-hint"><kbd class="tm-kbd">Ctrl</kbd> + <kbd class="tm-kbd">Enter</kbd> Guardar</span>
                    <span class="tm-app-kbd-hint"><kbd class="tm-kbd">Ctrl</kbd> + <kbd class="tm-kbd">V</kbd> Pegar imagen</span>
                </div>
                <div class="tm-app-footer-buttons">
                    <button type="button" class="lumio-cancel-btn tm-app-cancel-btn" onclick="TM.closeModal('tm-modal-task')">Cancelar</button>
                    <button type="submit" class="lumio-submit tm-app-submit-btn" id="tm-submit-btn">
                        <i class="ph-bold ph-check-circle"></i> <span>Guardar Tarea</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- VISOR DE IMÁGENES INTEGRADO (LIGHTBOX MODAL)         -->
<!-- ═══════════════════════════════════════════════════ -->
<div class="tm-image-viewer-overlay" id="tm-image-viewer" style="display:none;" onclick="if(event.target===this)TM.closeImageViewer()">
    <div class="tm-image-viewer-bar">
        <div class="tm-iv-info">
            <i class="ph-bold ph-image"></i>
            <span id="tm-iv-title" class="tm-iv-title-text">Vista previa</span>
            <span id="tm-iv-size" class="tm-iv-size-badge"></span>
        </div>
        <div class="tm-iv-actions">
            <button type="button" class="tm-iv-btn" onclick="TM.zoomImageViewer(-0.2)" title="Alejar (-)"><i class="ph-bold ph-magnifying-glass-minus"></i></button>
            <span id="tm-iv-zoom-level" class="tm-iv-zoom-text">100%</span>
            <button type="button" class="tm-iv-btn" onclick="TM.zoomImageViewer(0.2)" title="Acercar (+)"><i class="ph-bold ph-magnifying-glass-plus"></i></button>
            <button type="button" class="tm-iv-btn" onclick="TM.resetImageViewerZoom()" title="Restablecer zoom"><i class="ph-bold ph-arrows-counter-clockwise"></i></button>
            <a id="tm-iv-download" href="#" download class="tm-iv-btn" title="Descargar imagen"><i class="ph-bold ph-download-simple"></i></a>
            <button type="button" class="tm-iv-btn tm-iv-close" onclick="TM.closeImageViewer()" title="Cerrar visor (Esc)"><i class="ph-bold ph-x"></i></button>
        </div>
    </div>
    <div class="tm-image-viewer-stage" onclick="if(event.target===this)TM.closeImageViewer()">
        <img id="tm-iv-img" src="" alt="Vista previa de imagen" class="tm-iv-preview-img">
    </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- MODAL: EVALUACIÓN DE OBJETIVOS DIARIOS              -->
<!-- ═══════════════════════════════════════════════════ -->
<div class="tm-modal-overlay" id="tm-modal-daily-eval" style="display:none;">
    <div class="lumio-modal tm-modal-eval-dialog">
        <div class="lumio-accent-bar" style="background: linear-gradient(90deg, #f59e0b, #10b981);"></div>
        <div class="lumio-header">
            <div class="lumio-header-left">
                <button type="button" class="lumio-icon-btn lumio-close-btn" onclick="TM.closeModal('tm-modal-daily-eval')"><i class="ph ph-x"></i></button>
                <h3 style="margin:0; font-size:1.2rem; font-weight:800; display:flex; align-items:center; gap:0.5rem;">
                    <i class="ph ph-target" style="color:#f59e0b;"></i> Evaluación de Objetivos Diarios
                </h3>
            </div>
            <div class="lumio-header-right">
                <button type="button" class="tm-btn-subtle" onclick="TM.toggleEvalHistory()">
                    <i class="ph ph-clock-counter-clockwise"></i> Ver Historial
                </button>
            </div>
        </div>

        <div class="lumio-body">
            <!-- Top Controls: User and Date -->
            <div class="tm-eval-control-strip">
                <div class="tm-eval-field">
                    <label><i class="ph ph-calendar"></i> Fecha Evaluada</label>
                    <input type="date" id="tm-eval-date" onchange="TM.loadDailyObjectivesForEval(this.value)">
                </div>
                <?php if ($isAdmin): ?>
                <div class="tm-eval-field">
                    <label><i class="ph ph-user"></i> Empleado / Usuario</label>
                    <select id="tm-eval-user" onchange="TM.loadDailyObjectivesForEval(null, this.value)">
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $userId ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="tm-eval-user" value="<?php echo $userId; ?>">
                <?php endif; ?>
            </div>

            <!-- Live Compliance Score Card -->
            <div class="tm-eval-score-banner">
                <div class="tm-eval-metric">
                    <span class="tm-eval-num" id="tm-eval-completed-count">0 / 0</span>
                    <span class="tm-eval-lbl">Objetivos Cumplidos</span>
                </div>
                <div class="tm-eval-metric">
                    <span class="tm-eval-pct" id="tm-eval-compliance-pct">0%</span>
                    <span class="tm-eval-lbl">Cumplimiento del Día</span>
                </div>
                <div class="tm-eval-metric">
                    <span class="tm-eval-level-badge" id="tm-eval-level-badge">Pendiente</span>
                    <span class="tm-eval-lbl">Nivel de Rendimiento</span>
                </div>
            </div>

            <!-- Objectives Checklist for Today -->
            <div class="tm-eval-checklist-box">
                <label class="lumio-section-label">
                    <i class="ph ph-check-square"></i> Marca los objetivos cumplidos hoy:
                </label>
                <div id="tm-eval-checklist-items" class="tm-eval-items-container">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Star Rating (1 to 5) -->
            <div class="tm-eval-stars-group">
                <label class="lumio-section-label"><i class="ph ph-star"></i> Calificación Global del Desempeño</label>
                <div class="tm-stars-container" id="tm-stars-container">
                    <span class="tm-star" data-rating="1" onclick="TM.setEvalScore(1)"><i class="ph-fill ph-star"></i></span>
                    <span class="tm-star" data-rating="2" onclick="TM.setEvalScore(2)"><i class="ph-fill ph-star"></i></span>
                    <span class="tm-star" data-rating="3" onclick="TM.setEvalScore(3)"><i class="ph-fill ph-star"></i></span>
                    <span class="tm-star" data-rating="4" onclick="TM.setEvalScore(4)"><i class="ph-fill ph-star"></i></span>
                    <span class="tm-star" data-rating="5" onclick="TM.setEvalScore(5)"><i class="ph-fill ph-star"></i></span>
                </div>
                <input type="hidden" id="tm-eval-score-val" value="3">
                <div class="tm-stars-caption" id="tm-stars-caption">Calificación: 3 de 5 estrellas</div>
            </div>

            <!-- Notes & Feedback -->
            <div class="tm-eval-notes-group">
                <label class="lumio-section-label"><i class="ph ph-chat-teardrop-text"></i> Notas de Retroalimentación & Bloqueos</label>
                <textarea id="tm-eval-notes" class="tm-eval-textarea" rows="3" placeholder="Comentarios sobre el rendimiento, bloqueos encontrados, logros clave o pendientes para el día siguiente..."></textarea>
            </div>

            <!-- Evaluation History Drawer / Section (Hidden by default) -->
            <div id="tm-eval-history-section" style="display:none;" class="tm-history-drawer">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 style="margin:0; font-size:0.95rem; font-weight:700;"><i class="ph ph-clock-counter-clockwise"></i> Historial de Evaluaciones</h4>
                    <button type="button" class="btn-close-sm" onclick="TM.toggleEvalHistory()"><i class="ph ph-x"></i></button>
                </div>
                <div class="table-responsive">
                    <table class="tm-history-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Objetivos</th>
                                <th>% Cumplido</th>
                                <th>Calificación</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody id="tm-history-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="lumio-footer">
            <button type="button" class="lumio-cancel-btn" onclick="TM.closeModal('tm-modal-daily-eval')">Cerrar</button>
            <button type="button" class="lumio-submit" onclick="TM.submitDailyEvaluation()">
                <i class="ph ph-check-circle"></i> Guardar Evaluación del Día
            </button>
        </div>
    </div>
</div>

<script>
window.TM_USER_ID = <?php echo $userId; ?>;
window.TM_IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
window.TM_USERS = [
<?php foreach($users as $u): 
    $initial = mb_strtoupper(mb_substr(trim($u['name'] ?? 'U'), 0, 1));
    $av = $u['avatar'] ?? '';
    if ($av && !file_exists(__DIR__ . '/../../' . ltrim($av, '/\\'))) {
        $av = '';
    }
?>
    { "id": <?php echo (int)$u['id']; ?>, "name": <?php echo json_encode($u['name']); ?>, "value": <?php echo json_encode($u['name']); ?>, "avatar": <?php echo json_encode($av); ?>, "initial": <?php echo json_encode($initial); ?> },
<?php endforeach; ?>
];
</script>
<script src="modules/task_manager/app.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>

