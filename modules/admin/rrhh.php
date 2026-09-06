<?php
// modules/admin/rrhh.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Initial counts for SSR
$totalCount = (int)$db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'Activo'")->fetchColumn();
$inactiveCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'Inactivo'")->fetchColumn();
$payrollTotal = (float)$db->query("SELECT SUM(salary) FROM employees WHERE status = 'Activo'")->fetchColumn();

require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   MODERN SAAS RECURSOS HUMANOS - DESIGN SYSTEM
   ========================================================================== */

:root {
    --rrhh-radius-sm: 8px;
    --rrhh-radius-md: 14px;
    --rrhh-radius-lg: 18px;
    --rrhh-radius-xl: 22px;
    --rrhh-transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.rrhh-page-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-size: 13px;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
    padding-bottom: 2.5rem;
}

/* --- 1. Page Header --- */
.rrhh-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.rrhh-title-area {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.rrhh-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.rrhh-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rrhh-page-title i {
    color: var(--primary-color);
    font-size: 1.6rem;
}

.rrhh-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    padding: 0.2rem 0.65rem;
    border-radius: var(--radius-full);
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 25%, transparent);
}

.rrhh-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

.rrhh-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.rrhh-btn-attendance {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1rem;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--rrhh-radius-md);
    background: var(--bg-surface);
    color: var(--text-main);
    border: 1px solid var(--border-color);
    text-decoration: none;
    cursor: pointer;
    transition: var(--rrhh-transition);
}

.rrhh-btn-attendance:hover {
    background: var(--bg-body);
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-1px);
}

.rrhh-btn-create {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--rrhh-radius-md);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    transition: var(--rrhh-transition);
}

.rrhh-btn-create:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
    color: white;
}

/* --- 2. KPI Summary Bar --- */
.rrhh-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
}

.rrhh-kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--rrhh-radius-md);
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: var(--rrhh-transition);
}

.rrhh-kpi-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    transform: translateY(-1px);
}

.kpi-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: var(--rrhh-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.kpi-icon-wrap.blue { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; }
.kpi-icon-wrap.teal { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; }
.kpi-icon-wrap.red { background: color-mix(in srgb, #ef4444 15%, transparent); color: #ef4444; }
.kpi-icon-wrap.purple { background: color-mix(in srgb, #8b5cf6 15%, transparent); color: #8b5cf6; }

.kpi-content {
    display: flex;
    flex-direction: column;
}

.kpi-val {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.2;
}

.kpi-label {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
}

/* --- 3. Smart Toolbar: Search, Filters & Stats --- */
.rrhh-action-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    padding: 0.65rem 0.85rem;
    border-radius: var(--rrhh-radius-md);
    border: 1px solid var(--border-color);
}

.toolbar-left-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 280px;
    flex-wrap: wrap;
}

.rrhh-search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 440px;
    display: flex;
    align-items: center;
}

.search-icon-left {
    position: absolute;
    left: 0.75rem;
    color: var(--text-muted);
    font-size: 1rem;
    pointer-events: none;
}

.rrhh-search-box input {
    width: 100%;
    height: 38px;
    padding: 0 4rem 0 2.25rem;
    background: var(--bg-body, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--rrhh-radius-sm);
    font-size: 13px;
    color: var(--text-main);
    transition: var(--rrhh-transition);
}

.rrhh-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.rrhh-search-box input::placeholder {
    color: var(--text-muted);
}

.search-actions-right {
    position: absolute;
    right: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.search-spinner-icon {
    color: var(--primary-color);
    font-size: 1rem;
    animation: spin 0.8s linear infinite;
}

.search-clear-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: var(--radius-full);
}

.search-clear-btn:hover {
    background: var(--border-color);
    color: var(--text-main);
}

/* Filter Pills */
.rrhh-filter-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.rrhh-filter-group::-webkit-scrollbar { display: none; }

.rrhh-filter-pill {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: var(--rrhh-transition);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.rrhh-filter-pill:hover {
    color: var(--text-main);
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.rrhh-filter-pill.active {
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 25%, transparent);
    font-weight: 600;
}

/* Toolbar Right Controls */
.toolbar-right-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.search-result-pill {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
}

/* --- 4. Table / List View --- */
.rrhh-list-container {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--rrhh-radius-lg);
    overflow: hidden;
    transition: var(--rrhh-transition);
}

.rrhh-table {
    width: 100%;
    border-collapse: collapse;
}

.rrhh-table th {
    background: color-mix(in srgb, var(--bg-body) 30%, var(--bg-surface));
    padding: 1rem 1.15rem;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border-color);
}

.rrhh-table td {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    color: var(--text-main);
}

.rrhh-table tr:last-child td { border-bottom: none; }
.rrhh-table tbody tr { transition: var(--rrhh-transition); }
.rrhh-table tbody tr:hover { background: color-mix(in srgb, var(--text-muted) 3%, transparent); }

/* User cell */
.emp-user-cell {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.emp-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

.emp-info-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.emp-name-text {
    font-weight: 700;
    font-size: 13.5px;
    color: var(--text-main);
}

.emp-email-text {
    font-size: 11.5px;
    color: var(--text-muted);
}

/* Role & Dept */
.emp-role-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.emp-role-title {
    font-weight: 600;
    color: var(--text-main);
    font-size: 13px;
}

.emp-dept-title {
    font-size: 11.5px;
    color: var(--text-muted);
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
}
.status-activo { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; border: 1px solid color-mix(in srgb, #10b981 25%, transparent); }
.status-inactivo { background: color-mix(in srgb, #ef4444 15%, transparent); color: #ef4444; border: 1px solid color-mix(in srgb, #ef4444 25%, transparent); }
.status-pendiente { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #f59e0b; border: 1px solid color-mix(in srgb, #f59e0b 25%, transparent); }

/* Salary Cell */
.emp-salary-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.emp-salary-amount {
    font-weight: 700;
    color: var(--text-main);
    font-size: 13.5px;
}

.emp-hire-date {
    font-size: 11.5px;
    color: var(--text-muted);
}

/* Action Buttons */
.action-buttons-group {
    display: flex;
    gap: 0.35rem;
    justify-content: flex-end;
}

.action-btn-saas {
    width: 34px;
    height: 34px;
    border-radius: var(--rrhh-radius-sm);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.05rem;
    text-decoration: none;
    transition: var(--rrhh-transition);
}

.action-btn-saas:hover {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--bg-surface));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
    transform: translateY(-1px);
}

.action-btn-saas.delete-btn:hover {
    background: color-mix(in srgb, var(--danger-color) 15%, var(--bg-surface));
    color: var(--danger-color);
    border-color: color-mix(in srgb, var(--danger-color) 35%, var(--border-color));
}

/* Empty State */
.rrhh-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1.5rem;
    text-align: center;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: var(--rrhh-radius-lg);
    gap: 0.75rem;
}

.empty-icon-circle {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-full);
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

/* ==========================================================================
   MODAL REDESIGN: NUEVO / EDITAR EMPLEADO
   ========================================================================== */

.modal-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin: 1.25rem 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.modal-section-title:first-of-type {
    margin-top: 0.25rem;
}

.modal-section-title i {
    color: var(--primary-color);
    font-size: 0.95rem;
}

.form-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
}

.modal-input-field {
    width: 100%;
    padding: 0.65rem 0.9rem;
    background: var(--bg-body);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-main);
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: var(--rrhh-transition);
}

.modal-input-field:focus {
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.modal-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-main);
    margin-bottom: 0.35rem;
}

/* ==========================================================================
   RESPONSIVE DESIGN (Mobile & Tablet)
   ========================================================================== */

@media (max-width: 768px) {
    /* 2 Columns KPI */
    .rrhh-kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.65rem;
    }

    .rrhh-kpi-card {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
        border-radius: 12px;
    }

    .kpi-icon-wrap {
        width: 34px;
        height: 34px;
        font-size: 1rem;
    }

    .kpi-val {
        font-size: 1.1rem;
    }

    .kpi-label {
        font-size: 11px;
    }

    .toolbar-left-group, .rrhh-search-box {
        width: 100%;
        min-width: 100%;
    }

    .rrhh-filter-group {
        width: 100%;
        padding-bottom: 0.35rem;
    }

    /* Convert Table to Modern SaaS Cards */
    .rrhh-list-container {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        overflow: visible !important;
    }

    .rrhh-table thead {
        display: none !important;
    }

    .rrhh-table, 
    .rrhh-table tbody {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.85rem !important;
    }

    .rrhh-table tbody tr.emp-row-card {
        display: grid !important;
        grid-template-columns: 1fr auto !important;
        grid-template-areas:
            "user status"
            "role role"
            "salary actions" !important;
        gap: 0.65rem 0.75rem !important;
        background: var(--bg-surface) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 16px !important;
        padding: 1.15rem !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04) !important;
        transition: var(--rrhh-transition) !important;
    }

    .rrhh-table td {
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }

    .rrhh-table td.col-user {
        grid-area: user;
        display: flex;
        align-items: center;
    }

    .rrhh-table td.col-status {
        grid-area: status;
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    .rrhh-table td.col-role {
        grid-area: role;
        padding: 0.25rem 0 !important;
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .rrhh-table td.col-salary {
        grid-area: salary;
        padding-top: 0.75rem !important;
        border-top: 1px dashed var(--border-color) !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .rrhh-table td.col-actions {
        grid-area: actions;
        padding-top: 0.75rem !important;
        border-top: 1px dashed var(--border-color) !important;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .form-grid-2col {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* ==========================================================================
   DARK MODE OLED (Black Background & Zinc Borders)
   ========================================================================== */

[data-theme="dark"] body,
[data-theme="dark"] .rrhh-page-wrapper {
    background-color: #000000 !important;
}

[data-theme="dark"] .rrhh-action-toolbar,
[data-theme="dark"] .rrhh-kpi-card,
[data-theme="dark"] .rrhh-list-container,
[data-theme="dark"] .rrhh-empty-state,
[data-theme="dark"] .modal-content,
[data-theme="dark"] .calculator-panel {
    background: #0a0a0a !important;
    border-color: #262626 !important;
}

[data-theme="dark"] .rrhh-table th {
    background: #000000 !important;
    border-bottom-color: #262626 !important;
}

[data-theme="dark"] .rrhh-table td {
    border-bottom-color: #262626 !important;
}

[data-theme="dark"] .rrhh-search-box input,
[data-theme="dark"] .modal-input-field,
[data-theme="dark"] .calc-base-row,
[data-theme="dark"] .extra-field,
[data-theme="dark"] .calc-input-group {
    background: #000000 !important;
    border-color: #262626 !important;
    color: #f4f4f5 !important;
}

[data-theme="dark"] .rrhh-search-box input:focus,
[data-theme="dark"] .modal-input-field:focus {
    background: #0a0a0a !important;
}

[data-theme="dark"] .action-btn-saas {
    background: #141414 !important;
    border-color: #262626 !important;
}

[data-theme="dark"] .action-btn-saas:hover {
    background: #222222 !important;
    border-color: var(--primary-color) !important;
}

[data-theme="dark"] .rrhh-table tbody tr.emp-row-card {
    background: #0a0a0a !important;
    border-color: #262626 !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important;
}
</style>

<div class="rrhh-page-wrapper">
    <!-- Header -->
    <div class="rrhh-top-bar">
        <div class="rrhh-title-area">
            <div class="rrhh-title-row">
                <h1 class="rrhh-page-title">
                    <i class="ph ph-users-three"></i>
                    Recursos Humanos
                </h1>
                <span class="rrhh-count-badge" id="totalEmpBadge"><?php echo $totalCount; ?> empleados</span>
            </div>
            <p class="rrhh-subtitle">Gestiona a tu equipo de trabajo, roles, salarios, horarios y pagos.</p>
        </div>
        <div class="rrhh-header-actions">
            <a href="index.php?module=admin&action=asistencia" class="rrhh-btn-attendance">
                <i class="ph ph-clock"></i> Ver Asistencias
            </a>
            <button class="rrhh-btn-create" onclick="RrhhModule.openModal(0)">
                <i class="ph ph-user-plus"></i> Nuevo Empleado
            </button>
        </div>
    </div>

    <!-- KPI Summary: 2 columns on mobile, 4 columns on desktop -->
    <div class="rrhh-kpi-grid">
        <div class="rrhh-kpi-card">
            <div class="kpi-icon-wrap blue"><i class="ph ph-users"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiTotal"><?php echo $totalCount; ?></span>
                <span class="kpi-label">Total Empleados</span>
            </div>
        </div>
        <div class="rrhh-kpi-card">
            <div class="kpi-icon-wrap teal"><i class="ph ph-check-circle"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiActive"><?php echo $activeCount; ?></span>
                <span class="kpi-label">Empleados Activos</span>
            </div>
        </div>
        <div class="rrhh-kpi-card">
            <div class="kpi-icon-wrap red"><i class="ph ph-x-circle"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiInactive"><?php echo $inactiveCount; ?></span>
                <span class="kpi-label">Inactivos</span>
            </div>
        </div>
        <div class="rrhh-kpi-card">
            <div class="kpi-icon-wrap purple"><i class="ph ph-currency-dollar"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiPayroll">S/ <?php echo number_format($payrollTotal, 2); ?></span>
                <span class="kpi-label">Planilla Activa</span>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="rrhh-action-toolbar">
        <div class="toolbar-left-group">
            <div class="rrhh-search-box">
                <i class="ph ph-magnifying-glass search-icon-left"></i>
                <input type="text" id="rrhhSearchInput" placeholder="Buscar por nombre, DNI, email, rol...">
                <div class="search-actions-right">
                    <i class="ph ph-spinner search-spinner-icon" id="rrhhSearchSpinner" style="display:none;"></i>
                    <button class="search-clear-btn" id="rrhhSearchClearBtn" style="display:none;" title="Limpiar"><i class="ph ph-x"></i></button>
                </div>
            </div>
            
            <div class="rrhh-filter-group" id="rrhhStatusFilters">
                <button class="rrhh-filter-pill active" data-status="Todos"><i class="ph ph-funnel"></i> Todos</button>
                <button class="rrhh-filter-pill" data-status="Activo"><i class="ph ph-check-circle"></i> Activos</button>
                <button class="rrhh-filter-pill" data-status="Inactivo"><i class="ph ph-x-circle"></i> Inactivos</button>
                <button class="rrhh-filter-pill" data-status="Pendiente"><i class="ph ph-dots-three-circle"></i> Pendientes</button>
            </div>
        </div>
        <div class="toolbar-right-group">
            <span class="search-result-pill" id="rrhhSearchResultPill">Mostrando <?php echo $totalCount; ?> empleados</span>
        </div>
    </div>

    <!-- Table / Card List -->
    <div class="rrhh-list-container">
        <table class="rrhh-table" id="employees-table">
            <thead>
                <tr>
                    <th>USUARIO</th>
                    <th>ROL / DEPARTAMENTO</th>
                    <th>ESTADO</th>
                    <th>SALARIO / CONTRATACIÓN</th>
                    <th style="text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="employeesTableBody">
                <!-- Dynamically populated via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div class="rrhh-empty-state" id="rrhhEmptyState" style="display:none;">
        <div class="empty-icon-circle"><i class="ph ph-users-slash"></i></div>
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-main);">No se encontraron empleados</h3>
            <p style="margin:0; font-size:13px; color:var(--text-muted);">Intenta ajustar tus términos de búsqueda o filtros.</p>
        </div>
        <button class="rrhh-btn-create" onclick="document.getElementById('rrhhSearchInput').value=''; document.querySelector('[data-status=\'Todos\']').click();">
            Restablecer Filtros
        </button>
    </div>
</div>

<!-- Modal: Formulario Empleado -->
<div class="modal-overlay" id="modal-employee-form">
    <div class="modal-content" style="max-width: 580px; border-radius: var(--rrhh-radius-xl); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: color-mix(in srgb, var(--primary-color) 15%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="ph ph-user-gear"></i>
                </div>
                <h3 class="modal-title" id="modal-title-emp" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">Nuevo Empleado</h3>
            </div>
            <button type="button" class="btn-close-circular" onclick="RrhhModule.closeModal()"><i class="ph ph-x"></i></button>
        </div>
        
        <div class="modal-body" style="padding: 1.5rem;">
            <form id="emp_form" onsubmit="event.preventDefault(); RrhhModule.saveEmployee();">
                <input type="hidden" id="emp_id" name="id" value="0">
                
                <!-- Sección 1: Datos Personales -->
                <div class="modal-section-title"><i class="ph ph-identification-card"></i> Datos Personales</div>
                <div class="form-grid-2col" style="margin-bottom: 0.85rem;">
                    <div>
                        <label class="modal-label">Nombre Completo *</label>
                        <input type="text" class="modal-input-field" id="emp_name" name="name" placeholder="Ej: Carlos Mendoza" required>
                    </div>
                    <div>
                        <label class="modal-label">DNI / Documento *</label>
                        <input type="text" class="modal-input-field" id="emp_dni" name="dni" placeholder="Ej: 71234567" required>
                    </div>
                </div>
                
                <div class="form-grid-2col" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="modal-label">Correo Electrónico *</label>
                        <input type="email" class="modal-input-field" id="emp_email" name="email" placeholder="carlos@empresa.com" required>
                    </div>
                    <div>
                        <label class="modal-label">Celular / WhatsApp</label>
                        <input type="text" class="modal-input-field" id="emp_phone" name="phone" placeholder="+51 987 654 321">
                    </div>
                </div>

                <!-- Sección 2: Cargo y Remuneración -->
                <div class="modal-section-title"><i class="ph ph-briefcase"></i> Cargo y Remuneración</div>
                <div class="form-grid-2col" style="margin-bottom: 0.85rem;">
                    <div>
                        <label class="modal-label">Rol / Cargo *</label>
                        <input type="text" class="modal-input-field" id="emp_role" name="role" placeholder="Ej: Diseñador UI/UX" required>
                    </div>
                    <div>
                        <label class="modal-label">Departamento *</label>
                        <input type="text" class="modal-input-field" id="emp_department" name="department" placeholder="Ej: Diseño & Creatividad" required>
                    </div>
                </div>

                <div class="form-grid-2col" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="modal-label">Salario Mensual (S/) *</label>
                        <input type="number" step="0.01" class="modal-input-field" id="emp_salary" name="salary" placeholder="1500.00" required>
                    </div>
                    <div>
                        <label class="modal-label">Fecha de Contratación *</label>
                        <input type="date" class="modal-input-field" id="emp_hire_date" name="hire_date" required>
                    </div>
                </div>

                <!-- Sección 3: Horario y Estado -->
                <div class="modal-section-title"><i class="ph ph-clock"></i> Horario Laboral y Estado</div>
                <div class="form-grid-2col" style="margin-bottom: 0.85rem;">
                    <div>
                        <label class="modal-label">Entrada Programada</label>
                        <input type="time" class="modal-input-field" id="emp_work_start" name="work_start">
                    </div>
                    <div>
                        <label class="modal-label">Salida Programada</label>
                        <input type="time" class="modal-input-field" id="emp_work_end" name="work_end">
                    </div>
                </div>

                <div>
                    <label class="modal-label">Estado del Empleado</label>
                    <select class="modal-input-field" id="emp_status" name="status">
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                        <option value="Pendiente">Pendiente</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.65rem;">
            <button type="button" class="rrhh-btn-attendance" onclick="RrhhModule.closeModal()">Cancelar</button>
            <button type="submit" form="emp_form" id="btnSaveEmp" class="rrhh-btn-create">
                <i class="ph ph-floppy-disk"></i> Guardar Empleado
            </button>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Empleado -->
<div class="modal-overlay" id="modal-delete-employee">
    <div class="modal-content" style="max-width: 420px; border-radius: var(--rrhh-radius-lg); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="modal-title" style="color: var(--danger-color); font-size: 1.25rem;"><i class="ph ph-warning-circle"></i> Eliminar Empleado</h3>
            <button type="button" class="btn-close-circular" onclick="RrhhModule.closeDeleteModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-top: 0.75rem; color: var(--text-main); font-size: 14px;">¿Estás seguro de que deseas eliminar este empleado?</p>
            <p style="color: var(--text-muted); font-size: 13px;">Esta acción <strong>no se puede deshacer</strong> y se desvincularán sus registros.</p>
        </div>
        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light" style="background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-main);" onclick="RrhhModule.closeDeleteModal()">Cancelar</button>
            <button type="button" id="btnConfirmDeleteEmp" class="btn btn-pill" style="background: var(--danger-color); color: white;" onclick="RrhhModule.deleteEmployee()">Sí, Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Pagos -->
<div class="modal-overlay" id="modal-payments">
    <div class="modal-content" style="max-width: 1150px; border-radius: var(--rrhh-radius-xl); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding: 1.25rem 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: color-mix(in srgb, var(--primary-color) 15%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="ph ph-wallet"></i>
                </div>
                <h3 class="modal-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">Gestión de Pagos - <span id="payments-emp-name" style="color: var(--primary-color);"></span></h3>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="rrhh-btn-attendance" id="btn-share-history" style="font-size: 12px; padding: 0.35rem 0.75rem;">
                    <i class="ph ph-share-network"></i> Compartir Historial
                </button>
                <button type="button" class="btn-close-circular" onclick="RrhhModule.closePaymentsModal()"><i class="ph ph-x"></i></button>
            </div>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div class="payments-container" style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
                <div class="payments-left" style="flex: 1; min-width: 300px;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <i class="ph ph-plus-circle" style="color: var(--primary-color);"></i> Registrar Nuevo Pago
                    </h4>
                    <form id="payment_form" onsubmit="event.preventDefault(); RrhhModule.savePayment();">
                        <input type="hidden" name="employee_id" id="pay_employee_id">
                        <input type="hidden" name="pay_id" id="pay_id" value="0">
                        
                        <div class="form-group mb-3">
                            <label class="modal-label">Concepto *</label>
                            <input type="text" class="modal-input-field" id="pay_concept" name="concept" placeholder="Ej: Nómina Quincenal" required>
                        </div>
                        
                        <div class="calculator-panel">
                            <div class="calc-header">
                                <i class="ph ph-calculator"></i>
                                <span>Calculadora Salarial</span>
                            </div>
                            
                            <div class="calc-base-row">
                                <span class="calc-label">Salario Base (Fijo)</span>
                                <div class="calc-input-group">
                                    <span class="currency">S/</span>
                                    <input type="number" id="calc_base_salary" step="0.01">
                                </div>
                            </div>

                            <div class="calc-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Por día</span>
                                    <span class="stat-value" id="lbl_daily_rate">S/ 0.00</span>
                                </div>
                                <div class="divider"></div>
                                <div class="stat-item">
                                    <span class="stat-label">Por hora</span>
                                    <span class="stat-value" id="lbl_hourly_rate">S/ 0.00</span>
                                </div>
                            </div>

                            <div class="calc-extras" style="margin-bottom: 0.75rem; display: flex; gap: 0.75rem;">
                                <div class="extra-field" style="flex: 1;">
                                    <label class="modal-label">Días Extras / Faltas</label>
                                    <input type="number" id="calc_days" value="0" step="0.5" class="modal-input-field">
                                </div>
                                <div class="extra-field" style="flex: 1;">
                                    <label class="modal-label">Horas Extras</label>
                                    <input type="number" id="calc_extra_hours" value="0" step="1" class="modal-input-field">
                                </div>
                            </div>

                            <div class="calc-extras" style="display: flex; gap: 0.75rem;">
                                <div class="extra-field" style="flex: 1;">
                                    <label class="modal-label">Bonificaciones (S/)</label>
                                    <input type="number" id="calc_bonuses" name="bonuses" value="0" step="0.01" class="modal-input-field">
                                </div>
                                <div class="extra-field" style="flex: 1;">
                                    <label class="modal-label">Descuentos (S/)</label>
                                    <input type="number" id="calc_discounts" name="discounts" value="0" step="0.01" class="modal-input-field">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="pay_extra_amount" name="extra_amount" value="0">

                        <div class="form-grid-2col" style="margin-bottom: 0.85rem;">
                            <div>
                                <label class="modal-label">Fecha de Pago *</label>
                                <input type="date" class="modal-input-field" id="pay_date" name="payment_date" required>
                            </div>
                            <div>
                                <label class="modal-label">Monto Final (S/) *</label>
                                <input type="number" step="0.01" class="modal-input-field" id="pay_amount" name="amount" required readonly style="font-weight: 700; color: var(--primary-color);">
                            </div>
                        </div>

                        <div style="margin-bottom: 0.85rem;">
                            <label class="modal-label">Estado del Pago</label>
                            <select class="modal-input-field" name="status" id="pay_status">
                                <option value="Pagado">Pagado</option>
                                <option value="Pendiente">Pendiente</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1.25rem;">
                            <label class="modal-label">Comprobante (Opcional)</label>
                            <input type="file" class="modal-input-field" id="pay_voucher" name="voucher" accept=".pdf,.jpg,.jpeg,.png">
                            <small style="color: var(--text-muted); font-size: 11px;">PDF, JPG o PNG del comprobante.</small>
                        </div>

                        <button type="submit" form="payment_form" class="rrhh-btn-create w-100" style="width: 100%; justify-content: center;">
                            <i class="ph ph-check"></i> Registrar Pago
                        </button>
                        <button type="button" class="rrhh-btn-attendance" id="btn-cancel-edit" style="width: 100%; margin-top: 0.5rem; justify-content: center; display: none;" onclick="RrhhModule.cancelPaymentEdit()">
                            <i class="ph ph-x"></i> Cancelar Edición
                        </button>
                    </form>
                </div>
                
                <div class="payments-right" style="flex: 2; min-width: 350px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--rrhh-radius-lg); padding: 1.25rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <i class="ph ph-clock-counter-clockwise" style="color: var(--primary-color);"></i> Historial de Pagos
                    </h4>
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="rrhh-table" style="margin: 0; min-width: 100%;" id="payments-history-table">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>CONCEPTO</th>
                                    <th>MONTO</th>
                                    <th>ESTADO</th>
                                    <th style="text-align: right;">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody id="payments-history-tbody">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; display: flex; justify-content: flex-end;">
            <button type="button" class="rrhh-btn-attendance" onclick="RrhhModule.closePaymentsModal()">Cerrar</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/modules/rrhh.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
