<?php
// modules/admin/finances.php
require_once 'includes/header.php';
?>

<style>
/* ============================== */
/* Finance Module Styles          */
/* ============================== */

.finance-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}
.finance-tabs .tabs-left {
    display: flex;
    gap: 0;
    align-items: center;
    flex: 1 1 100%;
    min-width: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.finance-tabs .tabs-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    padding: 0.4rem 0;
    flex-shrink: 0;
}
@media (min-width: 769px) {
    .finance-tabs .tabs-left {
        flex: 1;
    }
}
.finance-tabs .tab-btn {
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-muted);
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    transition: all 0.25s ease;
    font-family: var(--font-family);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.finance-tabs .tab-btn:hover {
    color: var(--primary-color);
    background: var(--primary-bg);
}
.finance-tabs .tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}
.tab-content { display: none; animation: fadeTabIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeTabIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    padding-right: 4.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
.kpi-card .kpi-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 700;
    letter-spacing: 0.5px;
}
.kpi-card .kpi-value {
    font-size: clamp(1.1rem, 5vw, 1.75rem);
    font-weight: 800;
    color: var(--color-title);
    line-height: 1.2;
    white-space: nowrap;
}
.kpi-card .kpi-icon {
    position: absolute;
    right: 1rem;
    top: 1rem;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.kpi-card.income .kpi-value { color: var(--secondary-color); }
.kpi-card.income .kpi-icon { background: rgba(16,185,129,0.12); color: var(--secondary-color); }
.kpi-card.expense .kpi-value { color: #ef4444; }
.kpi-card.expense .kpi-icon { background: rgba(239,68,68,0.12); color: #ef4444; }
.kpi-card.profit .kpi-icon { background: var(--primary-bg); color: var(--primary-color); }
.kpi-card.repartido .kpi-icon { background: rgba(245,158,11,0.12); color: var(--warning-color); }

/* Closing Bar */
.closing-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.closing-bar.closed {
    border-left: 4px solid var(--secondary-color);
    background: color-mix(in srgb, var(--secondary-color) 5%, var(--bg-surface));
}
.closing-bar.open {
    border-left: 4px solid var(--warning-color);
}

/* Charts */
.chart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}
.chart-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.chart-card canvas { max-height: 300px; }

/* Finance Table */
.finance-table-container {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}
.finance-table-header {
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    border-bottom: 1px solid var(--border-color);
}
.finance-table-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.finance-table-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.finance-search {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.85rem;
    background: var(--bg-color);
    color: var(--text-main);
    min-width: 180px;
    font-family: var(--font-family);
}
.finance-search:focus { outline: none; border-color: var(--primary-color); }

.table-responsive { overflow-x: auto; }
.table-responsive table { width: 100%; border-collapse: collapse; }
.table-responsive th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.table-responsive td {
    padding: 0.85rem 1rem;
    font-size: 0.85rem;
    color: var(--text-main);
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}
.table-responsive tbody tr { transition: background 0.15s; }
.table-responsive tbody tr:hover { background: var(--primary-bg); }
.table-responsive tbody tr:last-child td { border-bottom: none; }

.badge-estado {
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}
.badge-pagado { background: rgba(16,185,129,0.15); color: #059669; }
.badge-pendiente { background: rgba(245,158,11,0.15); color: #d97706; }

.voucher-thumb {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: transform 0.2s;
}
.voucher-thumb:hover { transform: scale(1.15); }

.btn-icon-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1rem;
}
.btn-icon-sm.edit { background: var(--primary-bg); color: var(--primary-color); }
.btn-icon-sm.edit:hover { background: var(--primary-color); color: white; }
.btn-icon-sm.delete { background: rgba(239,68,68,0.1); color: #ef4444; }
.btn-icon-sm.delete:hover { background: #ef4444; color: white; }

/* Column filters */
.filter-row th {
    padding: 0.25rem 0.35rem !important;
    background: var(--bg-color) !important;
}
.col-filter {
    width: 100%;
    padding: 0.3rem 0.5rem;
    font-size: 0.7rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface);
    color: var(--text-color);
    outline: none;
    transition: border-color 0.2s;
}
.col-filter:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(16,185,129,0.15);
}
select.col-filter {
    cursor: pointer;
    appearance: auto;
}

/* Pagination */
.smart-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    border-top: 1px solid var(--border-color);
}
.smart-pagination .pg-info { font-weight: 600; }
.smart-pagination .pg-buttons {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.smart-pagination .pg-btn {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s;
}
.smart-pagination .pg-btn:hover:not(:disabled) {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}
.smart-pagination .pg-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}
.smart-pagination .pg-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.smart-pagination .pg-size {
    margin-left: 0.75rem;
    padding: 0.25rem 0.4rem;
    font-size: 0.72rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface);
    color: var(--text-color);
}

/* Recurring templates list */
.recurring-list { list-style: none; padding: 0; margin: 0; }
.recurring-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    gap: 0.75rem;
}
.recurring-list li:last-child { border-bottom: none; }

/* Responsive */
@media (max-width: 768px) {
    .chart-grid { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .finance-table-header { flex-direction: column; align-items: stretch; }
    .finance-table-actions { justify-content: stretch; }
    .finance-search { width: 100%; min-width: auto; }
    
    /* Card-style table for mobile */
    .table-responsive table,
    .table-responsive thead,
    .table-responsive tbody,
    .table-responsive th,
    .table-responsive td,
    .table-responsive tr { display: block; }
    .table-responsive thead { display: none; }
    .table-responsive tr {
        margin-bottom: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.5rem;
        background: var(--bg-surface);
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .table-responsive td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid color-mix(in srgb, var(--border-color) 50%, transparent);
        font-size: 0.8rem;
    }
    .table-responsive td::before {
        content: attr(data-label);
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-right: 1rem;
        flex-shrink: 0;
    }
    .table-responsive td:last-child { border-bottom: none; }
}
</style>

<!-- Tabs -->
<div class="finance-tabs" id="finance-tabs">
    <div class="tabs-left">
        <button class="tab-btn active" data-tab="tab-dashboard"><i class="ph ph-chart-bar"></i> Dashboard</button>
        <button class="tab-btn" data-tab="tab-incomes"><i class="ph ph-arrow-circle-down"></i> Ingresos</button>
        <button class="tab-btn" data-tab="tab-expenses"><i class="ph ph-arrow-circle-up"></i> Gastos</button>
        <button class="tab-btn" data-tab="tab-history"><i class="ph ph-clock-counter-clockwise"></i> Historial de Cierres</button>
    </div>
    <div class="tabs-right">
        <select id="filter-month" class="form-control" style="min-width: 150px; font-weight: 600; font-size: 0.8rem; padding: 0.4rem 0.6rem;"></select>
        <div id="closing-actions" style="display: flex; align-items: center; gap: 0.4rem;"></div>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid" id="kpi-grid">
    <div class="kpi-card income">
        <div class="kpi-icon"><i class="ph ph-arrow-circle-down"></i></div>
        <span class="kpi-label">Total Ingresos</span>
        <span class="kpi-value" id="kpi-income">S/ 0.00</span>
    </div>
    <div class="kpi-card expense">
        <div class="kpi-icon"><i class="ph ph-arrow-circle-up"></i></div>
        <span class="kpi-label">Total Egresos</span>
        <span class="kpi-value" id="kpi-expense">S/ 0.00</span>
    </div>
    <div class="kpi-card profit">
        <div class="kpi-icon"><i class="ph ph-chart-line-up"></i></div>
        <span class="kpi-label">Utilidad Neta</span>
        <span class="kpi-value" id="kpi-profit">S/ 0.00</span>
    </div>
    <div class="kpi-card repartido">
        <div class="kpi-icon"><i class="ph ph-hand-coins"></i></div>
        <span class="kpi-label">Monto Repartido</span>
        <span class="kpi-value" id="kpi-repartido">S/ 0.00</span>
    </div>
</div>



<!-- TAB: Dashboard -->
<div class="tab-content active" id="tab-dashboard">
    <div class="chart-grid">
        <div class="chart-card">
            <h3><i class="ph ph-chart-line-up" style="color: var(--primary-color);"></i> Crecimiento Mensual</h3>
            <canvas id="chart-growth"></canvas>
        </div>
        <div class="chart-card">
            <h3><i class="ph ph-chart-pie-slice" style="color: var(--warning-color);"></i> Gastos por Categoría</h3>
            <canvas id="chart-categories"></canvas>
        </div>
    </div>
</div>

<!-- TAB: Incomes -->
<div class="tab-content" id="tab-incomes">
    <div class="finance-table-container">
        <div class="finance-table-header">
            <h3><i class="ph ph-arrow-circle-down" style="color: var(--secondary-color);"></i> Ingresos del Mes</h3>
            <div class="finance-table-actions">
                <input type="text" class="finance-search" id="search-incomes" placeholder="Buscar ingreso...">
                <button class="btn btn-primary btn-add-income" id="btn-add-income" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; white-space: nowrap;">
                    <i class="ph ph-plus"></i> Nuevo Ingreso
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Servicio</th>
                        <th>Monto</th>
                        <th>Fecha de Pago</th>
                        <th>Estado</th>
                        <th>N° Operación</th>
                        <th>Banco</th>
                        <th>Voucher</th>
                        <th></th>
                    </tr>
                    <tr class="filter-row">
                        <th><select class="col-filter" id="filter-inc-empresa" data-col="0"><option value="">Todas</option></select></th>
                        <th><select class="col-filter" id="filter-inc-servicio" data-col="1"><option value="">Todos</option></select></th>
                        <th>
                            <select class="col-filter" id="filter-inc-monto-sort" data-col="sort-monto">
                                <option value="">— Ordenar —</option>
                                <option value="asc">Menor a Mayor</option>
                                <option value="desc">Mayor a Menor</option>
                            </select>
                        </th>
                        <th><input type="date" class="col-filter" id="filter-inc-fecha" data-col="3"></th>
                        <th>
                            <select class="col-filter" data-col="4">
                                <option value="">Todos</option>
                                <option value="pagado">Pagado</option>
                                <option value="pendiente">Pendiente</option>
                            </select>
                        </th>
                        <th><input type="text" class="col-filter" data-col="5" placeholder="Filtrar..."></th>
                        <th><select class="col-filter" id="filter-inc-banco" data-col="6"><option value="">Todos</option></select></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="incomes-tbody"></tbody>
            </table>
        </div>
        <div class="smart-pagination" id="incomes-pagination"></div>
        <div id="incomes-empty" style="display: none; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <i class="ph ph-receipt" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
            <p>No hay ingresos registrados para este mes.</p>
        </div>
    </div>
</div>

<!-- TAB: Expenses -->
<div class="tab-content" id="tab-expenses">
    <div class="finance-table-container">
        <div class="finance-table-header">
            <h3><i class="ph ph-arrow-circle-up" style="color: #ef4444;"></i> Gastos del Mes</h3>
            <div class="finance-table-actions">
                <input type="text" class="finance-search" id="search-expenses" placeholder="Buscar gasto...">
                <button class="btn btn-outline" id="btn-generate-recurring" title="Genera gastos recurrentes y sueldos de RRHH automáticamente" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; white-space: nowrap;">
                    <i class="ph ph-lightning"></i> Generar Recurrentes + Sueldos
                </button>
                <button class="btn btn-outline" id="btn-manage-recurring" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; white-space: nowrap;">
                    <i class="ph ph-gear"></i> Plantillas
                </button>
                <button class="btn btn-primary btn-add-expense" id="btn-add-expense" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; white-space: nowrap;">
                    <i class="ph ph-plus"></i> Nuevo Gasto
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre del Gasto</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Voucher</th>
                        <th></th>
                    </tr>
                    <tr class="filter-row">
                        <th><input type="date" class="col-filter" id="filter-exp-fecha" data-col="0"></th>
                        <th><input type="text" class="col-filter" data-col="1" placeholder="Filtrar..."></th>
                        <th>
                            <select class="col-filter" data-col="2">
                                <option value="">Todas</option>
                                <option value="Oficina">Oficina</option>
                                <option value="Servicios">Servicios</option>
                                <option value="Herramientas">Herramientas</option>
                                <option value="Publicidad">Publicidad</option>
                                <option value="Personal">Personal</option>
                                <option value="Impuestos">Impuestos</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </th>
                        <th>
                            <select class="col-filter" data-col="3">
                                <option value="">Todos</option>
                                <option value="Pago Fijo">Pago Fijo</option>
                                <option value="Variable">Variable</option>
                            </select>
                        </th>
                        <th>
                            <select class="col-filter" id="filter-exp-monto-sort" data-col="sort-monto">
                                <option value="">— Ordenar —</option>
                                <option value="asc">Menor a Mayor</option>
                                <option value="desc">Mayor a Menor</option>
                            </select>
                        </th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="expenses-tbody"></tbody>
            </table>
        </div>
        <div class="smart-pagination" id="expenses-pagination"></div>
        <div id="expenses-summary" style="display: none; padding: 0.75rem 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 1.5rem; flex-wrap: wrap;"></div>
        <div id="expenses-empty" style="display: none; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <i class="ph ph-receipt" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
            <p>No hay gastos registrados para este mes.</p>
        </div>
    </div>
</div>

<!-- TAB: History -->
<div class="tab-content" id="tab-history">
    <div class="finance-table-container">
        <div class="finance-table-header">
            <h3><i class="ph ph-clock-counter-clockwise" style="color: var(--primary-color);"></i> Historial de Cierres de Mes</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Total Ingresos</th>
                        <th>Total Egresos</th>
                        <th>Utilidad Neta</th>
                        <th>Monto Repartido</th>
                        <th>Estado</th>
                        <th>Cerrado Por</th>
                        <th>Fecha de Cierre</th>
                    </tr>
                </thead>
                <tbody id="history-tbody"></tbody>
            </table>
        </div>
        <div id="history-empty" style="display: none; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <i class="ph ph-clock-counter-clockwise" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
            <p>No hay cierres de mes registrados.</p>
        </div>
    </div>
</div>

<!-- =============================== -->
<!-- MODALS                          -->
<!-- =============================== -->

<!-- Modal: Income Form -->
<div class="modal-overlay" id="modal-income">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-income-title"><i class="ph ph-arrow-circle-down"></i> Nuevo Ingreso</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="income-id">
            <!-- Payment Note Reference -->
            <div class="form-group" id="income-note-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 0.4rem;">
                    <i class="ph ph-receipt" style="color: var(--primary-color);"></i> Vincular Nota de Pago
                </label>
                <select id="income-nota-pago" class="form-control">
                    <option value="">— Sin vincular (ingreso manual) —</option>
                </select>
            </div>
            <!-- Note info card (shown when a note is selected) -->
            <div id="income-note-info" style="display: none; background: var(--bg-color); border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1rem; border-left: 4px solid var(--primary-color); animation: fadeTabIn 0.2s ease;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">TOTAL NOTA DE PAGO</span>
                    <span id="income-note-code" style="font-size: 0.7rem; color: var(--primary-color); font-weight: 700;"></span>
                </div>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary-color);" id="income-note-total">S/ 0.00</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;" id="income-note-services"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Empresa</label>
                <input type="text" id="income-empresa" class="form-control" placeholder="Nombre de la empresa">
            </div>
            <div class="form-group">
                <label class="form-label">Servicio</label>
                <input type="text" id="income-servicio" class="form-control" placeholder="Descripción del servicio">
            </div>
            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Monto (S/)</label>
                    <input type="number" id="income-monto" class="form-control" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Fecha de Pago</label>
                    <input type="date" id="income-fecha" class="form-control">
                </div>
            </div>
            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Estado</label>
                    <select id="income-estado" class="form-control">
                        <option value="pendiente">Pendiente</option>
                        <option value="pagado">Pagado</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Banco</label>
                    <input type="text" id="income-banco" class="form-control" placeholder="BCP, Interbank, etc.">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">N° Operación</label>
                <input type="text" id="income-operacion" class="form-control" placeholder="Número de operación bancaria">
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Voucher <small style="font-weight: normal; color: var(--text-muted);">(o pega con Ctrl+V)</small></span>
                    <span id="income-ai-status" style="font-size: 0.75rem; font-weight: 600; color: var(--primary-color); display: none;"><i class="ph ph-spinner ph-spin"></i> Leyendo...</span>
                </label>
                <input type="file" id="income-voucher" class="form-control" accept="image/*,application/pdf">
                <button type="button" onclick="openCamera('income')" style="width: 100%; margin-top: 0.5rem; padding: 0.6rem; border-radius: 10px; border: 1.5px dashed var(--primary-color); background: var(--primary-bg); color: var(--primary-color); font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='var(--primary-color)';this.style.color='white'" onmouseout="this.style.background='var(--primary-bg)';this.style.color='var(--primary-color)'">
                    <i class="ph ph-camera" style="font-size: 1.1rem;"></i> Tomar Foto con Cámara
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-save-income" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Expense Form -->
<div class="modal-overlay" id="modal-expense">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-expense-title"><i class="ph ph-arrow-circle-up"></i> Nuevo Gasto</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="expense-id">
            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Fecha</label>
                    <input type="date" id="expense-fecha" class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Categoría</label>
                    <select id="expense-categoria" class="form-control">
                        <option value="Oficina">Oficina</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Herramientas">Herramientas</option>
                        <option value="Publicidad">Publicidad</option>
                        <option value="Personal">Personal</option>
                        <option value="Impuestos">Impuestos</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre del Gasto</label>
                <input type="text" id="expense-nombre" class="form-control" placeholder="Descripción del gasto">
            </div>
            <div class="form-group">
                <label class="form-label">Monto (S/)</label>
                <input type="number" id="expense-monto" class="form-control" placeholder="0.00" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Subir Foto / Voucher <small style="font-weight: normal; color: var(--text-muted);">(o pega con Ctrl+V)</small></span>
                    <span id="expense-ai-status" style="font-size: 0.75rem; font-weight: 600; color: var(--primary-color); display: none;"><i class="ph ph-spinner ph-spin"></i> Leyendo...</span>
                </label>
                <input type="file" id="expense-voucher" class="form-control" accept="image/*,application/pdf">
                <button type="button" onclick="openCamera('expense')" style="width: 100%; margin-top: 0.5rem; padding: 0.6rem; border-radius: 10px; border: 1.5px dashed var(--primary-color); background: var(--primary-bg); color: var(--primary-color); font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='var(--primary-color)';this.style.color='white'" onmouseout="this.style.background='var(--primary-bg)';this.style.color='var(--primary-color)'">
                    <i class="ph ph-camera" style="font-size: 1.1rem;"></i> Tomar Foto con Cámara
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-save-expense" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Close Month -->
<div class="modal-overlay" id="modal-close-month">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-lock-simple"></i> Cerrar Mes</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-muted); margin-bottom: 1.25rem;">Al cerrar el mes, se bloquearán todos los ingresos y gastos registrados y no podrán ser editados.</p>
            <div style="background: var(--bg-color); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; border-left: 4px solid var(--primary-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Total Ingresos</span>
                    <span id="close-total-income" style="font-weight: 700; color: var(--secondary-color);">S/ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Total Egresos</span>
                    <span id="close-total-expense" style="font-weight: 700; color: #ef4444;">S/ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--color-title);">Utilidad Neta</span>
                    <span id="close-utilidad" style="font-weight: 800; color: var(--primary-color);">S/ 0.00</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Monto Repartido (S/)</label>
                <input type="number" id="close-monto-repartido" class="form-control" placeholder="0.00" step="0.01" min="0">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-confirm-close" class="btn btn-primary" style="background: var(--secondary-color); border-color: var(--secondary-color);">
                <i class="ph ph-lock-simple"></i> Cerrar Mes
            </button>
        </div>
    </div>
</div>

<!-- Modal: Recurring Templates Manager -->
<div class="modal-overlay" id="modal-recurring">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-lightning"></i> Plantillas de Gastos Recurrentes</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <!-- Add form -->
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--bg-color);">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end;">
                    <div style="flex: 2; min-width: 140px;">
                        <label class="form-label" style="font-size: 0.7rem;">Nombre</label>
                        <input type="text" id="rec-nombre" class="form-control" placeholder="Ej: Alquiler" style="font-size: 0.85rem;">
                    </div>
                    <div style="flex: 1; min-width: 80px;">
                        <label class="form-label" style="font-size: 0.7rem;">Monto</label>
                        <input type="number" id="rec-monto" class="form-control" placeholder="0.00" step="0.01" style="font-size: 0.85rem;">
                    </div>
                    <div style="flex: 1; min-width: 100px;">
                        <label class="form-label" style="font-size: 0.7rem;">Categoría</label>
                        <select id="rec-categoria" class="form-control" style="font-size: 0.85rem;">
                            <option value="Oficina">Oficina</option>
                            <option value="Servicios">Servicios</option>
                            <option value="Herramientas">Herramientas</option>
                            <option value="Publicidad">Publicidad</option>
                            <option value="Personal">Personal</option>
                            <option value="Impuestos">Impuestos</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>
                    <div style="width: 60px;">
                        <label class="form-label" style="font-size: 0.7rem;">Día</label>
                        <input type="number" id="rec-dia" class="form-control" value="1" min="1" max="31" style="font-size: 0.85rem;">
                    </div>
                    <button class="btn btn-primary" id="btn-save-recurring" style="padding: 0.5rem 0.75rem; border-radius: 8px; height: 38px;">
                        <i class="ph ph-plus"></i>
                    </button>
                </div>
            </div>
            <!-- List -->
            <ul class="recurring-list" id="recurring-list">
                <!-- Populated via JS -->
            </ul>
            <div id="recurring-empty" style="display: none; text-align: center; padding: 2rem; color: var(--text-muted);">
                <p>No hay plantillas recurrentes configuradas.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal-overlay" id="modal-delete-confirm">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title" style="color: #ef4444;"><i class="ph ph-warning-circle"></i> Confirmar Eliminación</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p id="delete-confirm-text">¿Estás seguro de que deseas eliminar este registro?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-confirm-delete" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Viewer -->
<div class="modal-overlay" id="modal-viewer">
    <div class="modal-content" style="max-width: 800px; padding: 1rem;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="modal-title"><i class="ph ph-image"></i> Visor de Voucher</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="text-align: center; overflow: auto; max-height: 70vh;" id="viewer-container">
            <!-- Content injected here -->
        </div>
    </div>
</div>

<!-- Modal: Camera -->
<div class="modal-overlay" id="modal-camera" style="z-index: 10001;">
    <div class="modal-content" style="max-width: 560px; padding: 0; overflow: hidden;">
        <div class="modal-header" style="padding: 0.75rem 1rem;">
            <h3 class="modal-title"><i class="ph ph-camera"></i> Tomar Foto</h3>
            <button type="button" class="btn-close-circular" onclick="closeCamera()"><i class="ph ph-x"></i></button>
        </div>
        <div style="position: relative; background: #000;">
            <video id="camera-video" autoplay playsinline style="width: 100%; display: block;"></video>
            <canvas id="camera-canvas" style="display: none;"></canvas>
        </div>
        <div style="display: flex; justify-content: center; padding: 1rem; gap: 1rem; background: var(--bg-surface);">
            <button type="button" class="btn btn-primary" onclick="capturePhoto()" style="border-radius: 50%; width: 56px; height: 56px; padding: 0; font-size: 1.4rem;">
                <i class="ph ph-aperture"></i>
            </button>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ===================== STATE =====================
    let currentMonth = '';
    let financeData = null;
    let monthIsClosed = false;
    let growthChart = null;
    let categoriesChart = null;
    let deleteCallback = null;

    const MONTH_NAMES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    // ===================== INIT =====================
    function initMonthFilter() {
        const sel = document.getElementById('filter-month');
        const now = new Date();
        for (let i = 0; i < 12; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const val = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            const label = MONTH_NAMES[d.getMonth()] + ' ' + d.getFullYear();
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = label;
            sel.appendChild(opt);
        }
        currentMonth = sel.value;
        sel.addEventListener('change', () => {
            currentMonth = sel.value;
            loadFinances();
        });
    }

    // ===================== TAB SWITCHING =====================
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
        });
    });

    // ===================== LOAD DATA =====================
    async function loadFinances() {
        try {
            const res = await fetch(`modules/admin/ajax_get_finances.php?month=${currentMonth}`);
            const data = await res.json();
            if (data.success) {
                financeData = data;
                monthIsClosed = data.closing && data.closing.status === 'cerrado';
                renderAll();
            } else {
                console.error('Error loading finances:', data.error);
            }
        } catch (e) {
            console.error('Fetch error:', e);
        }
    }

    function renderAll() {
        renderKPIs();
        renderClosingBar();
        renderIncomes();
        renderExpenses();
        renderHistory();
        renderCharts();
        updateUIForClosedState();
    }

    // ===================== KPIs =====================
    function renderKPIs() {
        const d = financeData;
        const totalInc = d.incomes.reduce((s, i) => s + parseFloat(i.monto), 0);
        const totalExp = d.expenses.reduce((s, e) => s + parseFloat(e.monto), 0);
        const utilidad = totalInc - totalExp;
        const repartido = d.closing ? parseFloat(d.closing.monto_repartido) : 0;

        document.getElementById('kpi-income').textContent = 'S/ ' + totalInc.toLocaleString('es-PE', {minimumFractionDigits: 2});
        document.getElementById('kpi-expense').textContent = 'S/ ' + totalExp.toLocaleString('es-PE', {minimumFractionDigits: 2});
        document.getElementById('kpi-profit').textContent = 'S/ ' + utilidad.toLocaleString('es-PE', {minimumFractionDigits: 2});
        document.getElementById('kpi-repartido').textContent = 'S/ ' + repartido.toLocaleString('es-PE', {minimumFractionDigits: 2});

        // Color utilidad
        const profitEl = document.getElementById('kpi-profit');
        profitEl.style.color = utilidad >= 0 ? 'var(--secondary-color)' : '#ef4444';
    }

    // ===================== CLOSING BAR =====================
    function renderClosingBar() {
        const actions = document.getElementById('closing-actions');

        if (monthIsClosed) {
            actions.innerHTML = `<button class="btn btn-outline" id="btn-reopen-month" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; white-space: nowrap;">
                <i class="ph ph-lock-simple-open"></i> Reabrir
            </button>`;
            document.getElementById('btn-reopen-month').addEventListener('click', reopenMonth);
        } else {
            actions.innerHTML = `<button class="btn btn-primary" id="btn-open-close-modal" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; background: var(--secondary-color); border-color: var(--secondary-color); white-space: nowrap;">
                <i class="ph ph-lock-simple"></i> Cerrar Mes
            </button>`;
            document.getElementById('btn-open-close-modal').addEventListener('click', openCloseMonthModal);
        }
    }

    function updateUIForClosedState() {
        const addIncBtn = document.getElementById('btn-add-income');
        const addExpBtn = document.getElementById('btn-add-expense');
        const genRecBtn = document.getElementById('btn-generate-recurring');

        if (monthIsClosed) {
            addIncBtn.disabled = true;
            addIncBtn.style.opacity = '0.5';
            addIncBtn.style.pointerEvents = 'none';
            addExpBtn.disabled = true;
            addExpBtn.style.opacity = '0.5';
            addExpBtn.style.pointerEvents = 'none';
            genRecBtn.disabled = true;
            genRecBtn.style.opacity = '0.5';
            genRecBtn.style.pointerEvents = 'none';
        } else {
            addIncBtn.disabled = false;
            addIncBtn.style.opacity = '1';
            addIncBtn.style.pointerEvents = 'auto';
            addExpBtn.disabled = false;
            addExpBtn.style.opacity = '1';
            addExpBtn.style.pointerEvents = 'auto';
            genRecBtn.disabled = false;
            genRecBtn.style.opacity = '1';
            genRecBtn.style.pointerEvents = 'auto';
        }

        // Disable action buttons in tables
        document.querySelectorAll('.btn-icon-sm.edit, .btn-icon-sm.delete').forEach(btn => {
            if (monthIsClosed) {
                btn.disabled = true;
                btn.style.opacity = '0.3';
                btn.style.pointerEvents = 'none';
            }
        });
    }

    // ===================== SMART TABLE ENGINE =====================
    const ROWS_PER_PAGE = { incomes: 10, expenses: 10 };
    const currentPage  = { incomes: 1,  expenses: 1  };
    const sortState     = { incomes: null, expenses: null }; // 'asc' | 'desc' | null

    function getColumnFilters(tableId) {
        const container = tableId === 'incomes' ? '#tab-incomes' : '#tab-expenses';
        const filters = {};
        document.querySelectorAll(`${container} .col-filter`).forEach(el => {
            const colAttr = el.dataset.col;
            if (colAttr === 'sort-monto') return; // handled separately
            const col = parseInt(colAttr);
            if (isNaN(col)) return;
            const val = (el.value || '').trim();
            if (val) filters[col] = val;
        });
        return filters;
    }

    function filterRows(allRows, tableId) {
        const globalQ = document.getElementById(`search-${tableId}`).value.toLowerCase().trim();
        const colFilters = getColumnFilters(tableId);

        let result = allRows.filter(row => {
            if (globalQ && !row.searchText.toLowerCase().includes(globalQ)) return false;
            for (const col in colFilters) {
                const filterVal = colFilters[col];
                const cellVal = (row.cells[col] || '');
                // Date columns: exact date match
                if (row.colTypes && row.colTypes[col] === 'date') {
                    if (cellVal !== filterVal) return false;
                } else {
                    if (!cellVal.toLowerCase().includes(filterVal.toLowerCase())) return false;
                }
            }
            return true;
        });

        // Sorting
        const sortDir = sortState[tableId];
        if (sortDir) {
            result.sort((a, b) => {
                const aVal = a.montoNum || 0;
                const bVal = b.montoNum || 0;
                return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
            });
        }

        return result;
    }

    function renderPagination(tableId, totalRows) {
        const container = document.getElementById(`${tableId}-pagination`);
        const perPage = ROWS_PER_PAGE[tableId];
        const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
        if (currentPage[tableId] > totalPages) currentPage[tableId] = totalPages;
        const page = currentPage[tableId];
        const from = totalRows === 0 ? 0 : (page - 1) * perPage + 1;
        const to = Math.min(page * perPage, totalRows);

        let btns = '';
        btns += `<button class="pg-btn" ${page <= 1 ? 'disabled' : ''} onclick="smartPage('${tableId}',${page - 1})"><i class="ph ph-caret-left"></i></button>`;

        const maxBtns = 5;
        let start = Math.max(1, page - Math.floor(maxBtns / 2));
        let end = Math.min(totalPages, start + maxBtns - 1);
        if (end - start < maxBtns - 1) start = Math.max(1, end - maxBtns + 1);

        if (start > 1) btns += `<button class="pg-btn" onclick="smartPage('${tableId}',1)">1</button>`;
        if (start > 2) btns += `<span style="padding: 0 0.2rem;">…</span>`;
        for (let i = start; i <= end; i++) {
            btns += `<button class="pg-btn ${i === page ? 'active' : ''}" onclick="smartPage('${tableId}',${i})">${i}</button>`;
        }
        if (end < totalPages - 1) btns += `<span style="padding: 0 0.2rem;">…</span>`;
        if (end < totalPages) btns += `<button class="pg-btn" onclick="smartPage('${tableId}',${totalPages})">${totalPages}</button>`;

        btns += `<button class="pg-btn" ${page >= totalPages ? 'disabled' : ''} onclick="smartPage('${tableId}',${page + 1})"><i class="ph ph-caret-right"></i></button>`;
        btns += `<select class="pg-size" onchange="smartPageSize('${tableId}', this.value)">
            <option value="5" ${perPage === 5 ? 'selected' : ''}>5</option>
            <option value="10" ${perPage === 10 ? 'selected' : ''}>10</option>
            <option value="25" ${perPage === 25 ? 'selected' : ''}>25</option>
            <option value="50" ${perPage === 50 ? 'selected' : ''}>50</option>
        </select>`;

        container.innerHTML = `
            <span class="pg-info">Mostrando ${from}–${to} de ${totalRows}</span>
            <div class="pg-buttons">${btns}</div>
        `;
    }

    window.smartPage = function(tableId, page) {
        currentPage[tableId] = page;
        if (tableId === 'incomes') renderIncomes();
        else renderExpenses();
    };
    window.smartPageSize = function(tableId, size) {
        ROWS_PER_PAGE[tableId] = parseInt(size);
        currentPage[tableId] = 1;
        if (tableId === 'incomes') renderIncomes();
        else renderExpenses();
    };

    // Populate dynamic dropdowns from data
    function populateIncomeDropdowns() {
        const incomes = financeData.incomes || [];
        const empresas = [...new Set(incomes.map(i => i.empresa).filter(Boolean))].sort();
        const servicios = [...new Set(incomes.map(i => i.servicio).filter(Boolean))].sort();
        const bancos = [...new Set(incomes.map(i => i.banco).filter(Boolean))].sort();

        const elEmp = document.getElementById('filter-inc-empresa');
        const elServ = document.getElementById('filter-inc-servicio');
        const elBanco = document.getElementById('filter-inc-banco');

        const savedEmp = elEmp.value, savedServ = elServ.value, savedBanco = elBanco.value;

        elEmp.innerHTML = '<option value="">Todas</option>' + empresas.map(e => `<option value="${esc(e)}">${esc(e)}</option>`).join('');
        elServ.innerHTML = '<option value="">Todos</option>' + servicios.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        elBanco.innerHTML = '<option value="">Todos</option>' + bancos.map(b => `<option value="${esc(b)}">${esc(b)}</option>`).join('');

        elEmp.value = savedEmp; elServ.value = savedServ; elBanco.value = savedBanco;
    }

    // ===================== INCOMES TABLE =====================
    let incomesAllRows = [];

    function buildIncomeRows() {
        incomesAllRows = (financeData.incomes || []).map(inc => {
            const badgeClass = inc.estado === 'pagado' ? 'badge-pagado' : 'badge-pendiente';
            const voucherHtml = inc.voucher
                ? `<img src="${inc.voucher}" class="voucher-thumb" onclick="openVoucherViewer('${inc.voucher}')" alt="Voucher">`
                : '<span style="color: var(--text-muted); font-size: 0.75rem;">—</span>';
            const montoNum = parseFloat(inc.monto) || 0;
            const montoStr = `S/ ${montoNum.toFixed(2)}`;

            return {
                montoNum,
                colTypes: { 3: 'date' },
                cells: {
                    0: inc.empresa || '',
                    1: inc.servicio || '',
                    2: montoStr,
                    3: inc.fecha_pago || '',
                    4: inc.estado || '',
                    5: inc.n_operacion || '',
                    6: inc.banco || ''
                },
                searchText: `${inc.empresa} ${inc.servicio} ${montoStr} ${inc.fecha_pago} ${inc.estado} ${inc.n_operacion} ${inc.banco}`,
                html: `<tr>
                    <td data-label="Empresa" style="font-weight: 600;">${esc(inc.empresa)}</td>
                    <td data-label="Servicio">${esc(inc.servicio)}</td>
                    <td data-label="Monto" style="font-weight: 700;">${montoStr}</td>
                    <td data-label="Fecha">${inc.fecha_pago}</td>
                    <td data-label="Estado"><span class="badge-estado ${badgeClass}">${inc.estado}</span></td>
                    <td data-label="N° Operación">${esc(inc.n_operacion || '—')}</td>
                    <td data-label="Banco">${esc(inc.banco || '—')}</td>
                    <td data-label="Voucher">${voucherHtml}</td>
                    <td>
                        <div style="display: flex; gap: 0.35rem;">
                            <button class="btn-icon-sm edit" onclick="editIncome(${inc.id})" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                            <button class="btn-icon-sm delete" onclick="confirmDeleteIncome(${inc.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                        </div>
                    </td>
                </tr>`
            };
        });
    }

    function renderIncomes() {
        const tbody = document.getElementById('incomes-tbody');
        const empty = document.getElementById('incomes-empty');
        buildIncomeRows();
        populateIncomeDropdowns();

        // Read sort
        const sortEl = document.getElementById('filter-inc-monto-sort');
        sortState['incomes'] = sortEl ? sortEl.value || null : null;

        if (incomesAllRows.length === 0) {
            tbody.innerHTML = '';
            empty.style.display = 'block';
            tbody.parentElement.style.display = 'none';
            document.getElementById('incomes-pagination').innerHTML = '';
            return;
        }

        empty.style.display = 'none';
        tbody.parentElement.style.display = '';

        const filtered = filterRows(incomesAllRows, 'incomes');
        const perPage = ROWS_PER_PAGE['incomes'];
        const page = currentPage['incomes'];
        const sliced = filtered.slice((page - 1) * perPage, page * perPage);

        tbody.innerHTML = sliced.map(r => r.html).join('');
        renderPagination('incomes', filtered.length);
    }

    // Event listeners for incomes
    document.getElementById('search-incomes').addEventListener('input', function() {
        currentPage['incomes'] = 1;
        renderIncomes();
    });
    document.querySelectorAll('#tab-incomes .col-filter').forEach(el => {
        el.addEventListener('input', function() { currentPage['incomes'] = 1; renderIncomes(); });
        el.addEventListener('change', function() { currentPage['incomes'] = 1; renderIncomes(); });
    });

    // ===================== EXPENSES TABLE =====================
    let expensesAllRows = [];

    function buildExpenseRows() {
        expensesAllRows = (financeData.expenses || []).map(exp => {
            const catColors = {
                'Oficina': '#6366f1', 'Servicios': '#0ea5e9', 'Herramientas': '#8b5cf6',
                'Publicidad': '#f59e0b', 'Personal': '#10b981', 'Impuestos': '#ef4444', 'Otros': '#64748b'
            };
            const catColor = catColors[exp.categoria] || '#64748b';
            const isFixed = !!exp.recurring_source_id || exp.categoria === 'Personal';
            const tipoBadge = isFixed
                ? '<span style="background: rgba(249,115,22,0.12); color: #f97316; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; white-space: nowrap;"><i class="ph ph-lightning" style="margin-right:2px"></i>Pago Fijo</span>'
                : '<span style="background: rgba(100,116,139,0.1); color: #94a3b8; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700;">Variable</span>';
            const tipoText = isFixed ? 'Pago Fijo' : 'Variable';
            const montoNum = parseFloat(exp.monto) || 0;
            const montoStr = `S/ ${montoNum.toFixed(2)}`;
            const voucherHtml = exp.voucher
                ? `<img src="${exp.voucher}" class="voucher-thumb" onclick="openVoucherViewer('${exp.voucher}')" alt="Voucher">`
                : '<span style="color: var(--text-muted); font-size: 0.75rem;">—</span>';

            return {
                montoNum,
                isFixed,
                colTypes: { 0: 'date' },
                cells: {
                    0: exp.fecha || '',
                    1: exp.nombre_gasto || '',
                    2: exp.categoria || '',
                    3: tipoText,
                    4: montoStr
                },
                searchText: `${exp.fecha} ${exp.nombre_gasto} ${exp.categoria} ${tipoText} ${montoStr}`,
                html: `<tr>
                    <td data-label="Fecha">${exp.fecha}</td>
                    <td data-label="Nombre" style="font-weight: 600;">${esc(exp.nombre_gasto)}</td>
                    <td data-label="Categoría"><span style="background: ${catColor}15; color: ${catColor}; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700;">${esc(exp.categoria)}</span></td>
                    <td data-label="Tipo">${tipoBadge}</td>
                    <td data-label="Monto" style="font-weight: 700; color: #ef4444;">${montoStr}</td>
                    <td data-label="Voucher">${voucherHtml}</td>
                    <td>
                        <div style="display: flex; gap: 0.35rem;">
                            <button class="btn-icon-sm edit" onclick="editExpense(${exp.id})" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                            <button class="btn-icon-sm delete" onclick="confirmDeleteExpense(${exp.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                        </div>
                    </td>
                </tr>`
            };
        });
    }

    function renderExpenses() {
        const tbody = document.getElementById('expenses-tbody');
        const empty = document.getElementById('expenses-empty');
        buildExpenseRows();

        // Read sort
        const sortEl = document.getElementById('filter-exp-monto-sort');
        sortState['expenses'] = sortEl ? sortEl.value || null : null;

        if (expensesAllRows.length === 0) {
            tbody.innerHTML = '';
            empty.style.display = 'block';
            tbody.parentElement.style.display = 'none';
            document.getElementById('expenses-pagination').innerHTML = '';
            return;
        }

        empty.style.display = 'none';
        tbody.parentElement.style.display = '';

        const filtered = filterRows(expensesAllRows, 'expenses');
        const perPage = ROWS_PER_PAGE['expenses'];
        const page = currentPage['expenses'];
        const sliced = filtered.slice((page - 1) * perPage, page * perPage);

        tbody.innerHTML = sliced.map(r => r.html).join('');
        renderPagination('expenses', filtered.length);

        // Summary bar: fixed vs variable
        const summaryEl = document.getElementById('expenses-summary');
        const allExp = expensesAllRows;
        const totalFijo = allExp.filter(r => r.isFixed).reduce((s, r) => s + r.montoNum, 0);
        const totalVariable = allExp.filter(r => !r.isFixed).reduce((s, r) => s + r.montoNum, 0);
        const totalAll = totalFijo + totalVariable;
        if (allExp.length > 0) {
            summaryEl.style.display = 'flex';
            summaryEl.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-lightning" style="color: #f97316;"></i>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Pago Fijo:</span>
                    <span style="font-size: 0.85rem; font-weight: 700; color: #f97316;">S/ ${totalFijo.toFixed(2)}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-swap" style="color: #94a3b8;"></i>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Variable:</span>
                    <span style="font-size: 0.85rem; font-weight: 700; color: #94a3b8;">S/ ${totalVariable.toFixed(2)}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-left: auto;">
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Total:</span>
                    <span style="font-size: 0.85rem; font-weight: 800; color: #ef4444;">S/ ${totalAll.toFixed(2)}</span>
                </div>
            `;
        } else {
            summaryEl.style.display = 'none';
        }
    }

    // Event listeners for expenses
    document.getElementById('search-expenses').addEventListener('input', function() {
        currentPage['expenses'] = 1;
        renderExpenses();
    });
    document.querySelectorAll('#tab-expenses .col-filter').forEach(el => {
        el.addEventListener('input', function() { currentPage['expenses'] = 1; renderExpenses(); });
        el.addEventListener('change', function() { currentPage['expenses'] = 1; renderExpenses(); });
    });


    // ===================== HISTORY TABLE =====================
    function renderHistory() {
        const tbody = document.getElementById('history-tbody');
        const empty = document.getElementById('history-empty');
        const history = financeData.closings_history || [];

        if (history.length === 0) {
            tbody.innerHTML = '';
            empty.style.display = 'block';
            tbody.parentElement.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        tbody.parentElement.style.display = '';
        tbody.innerHTML = history.map(h => {
            const utilidad = parseFloat(h.total_incomes) - parseFloat(h.total_expenses);
            const utilColor = utilidad >= 0 ? 'var(--secondary-color)' : '#ef4444';
            const statusBadge = h.status === 'cerrado'
                ? '<span class="badge-estado badge-pagado">Cerrado</span>'
                : '<span class="badge-estado badge-pendiente">Abierto</span>';
            // Format period to month name
            const parts = h.period.split('-');
            const periodLabel = MONTH_NAMES[parseInt(parts[1]) - 1] + ' ' + parts[0];

            return `<tr>
                <td data-label="Período" style="font-weight: 700;">${periodLabel}</td>
                <td data-label="Ingresos" style="color: var(--secondary-color); font-weight: 600;">S/ ${parseFloat(h.total_incomes).toFixed(2)}</td>
                <td data-label="Egresos" style="color: #ef4444; font-weight: 600;">S/ ${parseFloat(h.total_expenses).toFixed(2)}</td>
                <td data-label="Utilidad" style="color: ${utilColor}; font-weight: 700;">S/ ${utilidad.toFixed(2)}</td>
                <td data-label="Repartido" style="font-weight: 600;">S/ ${parseFloat(h.monto_repartido).toFixed(2)}</td>
                <td data-label="Estado">${statusBadge}</td>
                <td data-label="Cerrado Por">${esc(h.closed_by_name || '—')}</td>
                <td data-label="Fecha">${h.closed_at ? new Date(h.closed_at).toLocaleDateString('es-PE') : '—'}</td>
            </tr>`;
        }).join('');
    }

    // ===================== CHARTS =====================
    function renderCharts() {
        renderGrowthChart();
        renderCategoriesChart();
    }

    function renderGrowthChart() {
        const history = financeData.history || [];
        const labels = history.map(h => {
            const parts = h.period.split('-');
            return MONTH_NAMES[parseInt(parts[1]) - 1].substring(0, 3) + ' ' + parts[0].substring(2);
        });
        const incomes = history.map(h => parseFloat(h.total_incomes));
        const expenses = history.map(h => parseFloat(h.total_expenses));
        const utilidad = history.map(h => parseFloat(h.utilidad));

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        if (growthChart) growthChart.destroy();

        const ctx = document.getElementById('chart-growth').getContext('2d');
        growthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: incomes,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Egresos',
                        data: expenses,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#ef4444',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Utilidad',
                        data: utilidad,
                        borderColor: '#6366f1',
                        backgroundColor: 'transparent',
                        borderDash: [6, 4],
                        tension: 0.4,
                        borderWidth: 2,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: { color: textColor, usePointStyle: true, padding: 16, font: { size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': S/ ' + ctx.parsed.y.toLocaleString('es-PE', {minimumFractionDigits: 2})
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 11 },
                            callback: v => 'S/ ' + v.toLocaleString('es-PE')
                        }
                    }
                }
            }
        });
    }

    function renderCategoriesChart() {
        const breakdown = financeData.categories_breakdown || [];
        if (breakdown.length === 0) {
            if (categoriesChart) categoriesChart.destroy();
            return;
        }

        const labels = breakdown.map(b => b.categoria);
        const data = breakdown.map(b => parseFloat(b.total));
        const colors = ['#6366f1', '#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#64748b', '#ec4899'];

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        if (categoriesChart) categoriesChart.destroy();

        const ctx = document.getElementById('chart-categories').getContext('2d');
        categoriesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor, usePointStyle: true, padding: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: ctx => ctx.label + ': S/ ' + ctx.parsed.toLocaleString('es-PE', {minimumFractionDigits: 2})
                        }
                    }
                }
            }
        });
    }

    // ===================== INCOME CRUD =====================

    // Payment note selector change handler
    document.getElementById('income-nota-pago').addEventListener('change', function() {
        const noteId = this.value;
        const infoCard = document.getElementById('income-note-info');

        if (!noteId || !financeData.payment_notes) {
            infoCard.style.display = 'none';
            return;
        }

        const note = financeData.payment_notes.find(n => n.id == noteId);
        if (!note) {
            infoCard.style.display = 'none';
            return;
        }

        // Show info card
        infoCard.style.display = 'block';
        document.getElementById('income-note-code').textContent = note.note_code;
        document.getElementById('income-note-total').textContent = 'S/ ' + parseFloat(note.total).toLocaleString('es-PE', {minimumFractionDigits: 2});
        document.getElementById('income-note-services').textContent = note.services_summary || 'Sin servicios detallados';

        // Auto-fill fields
        document.getElementById('income-empresa').value = note.company_name || note.client_name || '';
        document.getElementById('income-servicio').value = note.services_summary || ('Nota ' + note.note_code);
        document.getElementById('income-monto').value = note.total || '';
    });

    function populateNoteSelector() {
        const sel = document.getElementById('income-nota-pago');
        // Keep first option, remove rest
        while (sel.options.length > 1) sel.remove(1);

        const notes = financeData.payment_notes || [];
        notes.forEach(note => {
            const opt = document.createElement('option');
            opt.value = note.id;
            const statusIcon = note.status === 'PAGADO' ? '✅' : '⏳';
            opt.textContent = `${statusIcon} ${note.note_code} — ${note.company_name || note.client_name} — S/ ${parseFloat(note.total).toFixed(2)}`;
            sel.appendChild(opt);
        });
    }

    document.getElementById('btn-add-income').addEventListener('click', () => {
        if (monthIsClosed) return;
        document.getElementById('income-id').value = '';
        document.getElementById('income-empresa').value = '';
        document.getElementById('income-servicio').value = '';
        document.getElementById('income-monto').value = '';
        document.getElementById('income-fecha').value = currentMonth + '-01';
        document.getElementById('income-estado').value = 'pendiente';
        document.getElementById('income-banco').value = '';
        document.getElementById('income-operacion').value = '';
        document.getElementById('income-voucher').value = '';
        document.getElementById('modal-income-title').innerHTML = '<i class="ph ph-arrow-circle-down"></i> Nuevo Ingreso';

        // Show note selector and populate
        document.getElementById('income-note-group').style.display = '';
        document.getElementById('income-note-info').style.display = 'none';
        document.getElementById('income-nota-pago').value = '';
        populateNoteSelector();

        document.getElementById('modal-income').classList.add('active');
    });

    window.editIncome = function(id) {
        if (monthIsClosed) return;
        const inc = financeData.incomes.find(i => i.id == id);
        if (!inc) return;
        document.getElementById('income-id').value = inc.id;
        document.getElementById('income-empresa').value = inc.empresa;
        document.getElementById('income-servicio').value = inc.servicio;
        document.getElementById('income-monto').value = inc.monto;
        document.getElementById('income-fecha').value = inc.fecha_pago;
        document.getElementById('income-estado').value = inc.estado;
        document.getElementById('income-banco').value = inc.banco || '';
        document.getElementById('income-operacion').value = inc.n_operacion || '';
        document.getElementById('income-voucher').value = '';
        document.getElementById('modal-income-title').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Ingreso';

        // Hide note selector when editing
        document.getElementById('income-note-group').style.display = 'none';
        document.getElementById('income-note-info').style.display = 'none';

        document.getElementById('modal-income').classList.add('active');
    };

    document.getElementById('btn-save-income').addEventListener('click', async () => {
        const fd = new FormData();
        const id = document.getElementById('income-id').value;
        if (id) fd.append('id', id);
        fd.append('empresa', document.getElementById('income-empresa').value);
        fd.append('servicio', document.getElementById('income-servicio').value);
        fd.append('monto', document.getElementById('income-monto').value);
        fd.append('fecha_pago', document.getElementById('income-fecha').value);
        fd.append('estado', document.getElementById('income-estado').value);
        fd.append('banco', document.getElementById('income-banco').value);
        fd.append('n_operacion', document.getElementById('income-operacion').value);
        const voucherFile = document.getElementById('income-voucher').files[0];
        if (voucherFile) fd.append('voucher', voucherFile);

        try {
            const res = await fetch('modules/admin/ajax_save_income.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('modal-income').classList.remove('active');
                loadFinances();
            } else {
                alert(data.message || data.error || 'Error al guardar ingreso');
            }
        } catch(e) {
            alert('Error de conexión');
        }
    });

    window.confirmDeleteIncome = function(id) {
        if (monthIsClosed) return;
        document.getElementById('delete-confirm-text').textContent = '¿Estás seguro de que deseas eliminar este ingreso?';
        deleteCallback = async () => {
            try {
                const res = await fetch('modules/admin/ajax_delete_income.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (data.success) {
                    loadFinances();
                } else {
                    alert(data.message || data.error || 'Error al eliminar');
                }
            } catch(e) {
                alert('Error de conexión');
            }
        };
        document.getElementById('modal-delete-confirm').classList.add('active');
    };

    // ===================== EXPENSE CRUD =====================
    document.getElementById('btn-add-expense').addEventListener('click', () => {
        if (monthIsClosed) return;
        document.getElementById('expense-id').value = '';
        document.getElementById('expense-fecha').value = currentMonth + '-01';
        document.getElementById('expense-nombre').value = '';
        document.getElementById('expense-monto').value = '';
        document.getElementById('expense-categoria').value = 'Oficina';
        const ev = document.getElementById('expense-voucher'); if (ev) ev.value = '';
        document.getElementById('modal-expense-title').innerHTML = '<i class="ph ph-arrow-circle-up"></i> Nuevo Gasto';
        document.getElementById('modal-expense').classList.add('active');
    });

    window.editExpense = function(id) {
        if (monthIsClosed) return;
        const exp = financeData.expenses.find(e => e.id == id);
        if (!exp) return;
        document.getElementById('expense-id').value = exp.id;
        document.getElementById('expense-fecha').value = exp.fecha;
        document.getElementById('expense-nombre').value = exp.nombre_gasto;
        document.getElementById('expense-monto').value = exp.monto;
        document.getElementById('expense-categoria').value = exp.categoria;
        document.getElementById('modal-expense-title').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Gasto';
        document.getElementById('modal-expense').classList.add('active');
    };

    document.getElementById('btn-save-expense').addEventListener('click', async () => {
        const formData = new FormData();
        formData.append('fecha', document.getElementById('expense-fecha').value);
        formData.append('nombre_gasto', document.getElementById('expense-nombre').value);
        formData.append('monto', document.getElementById('expense-monto').value);
        formData.append('categoria', document.getElementById('expense-categoria').value);
        const id = document.getElementById('expense-id').value;
        if (id) formData.append('id', id);

        const voucherFile = document.getElementById('expense-voucher').files[0];
        if (voucherFile) formData.append('voucher', voucherFile);

        try {
            const res = await fetch('modules/admin/ajax_save_expense.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('modal-expense').classList.remove('active');
                loadFinances();
            } else {
                alert(data.message || data.error || 'Error al guardar gasto');
            }
        } catch(e) {
            alert('Error de conexión');
        }
    });

    window.confirmDeleteExpense = function(id) {
        if (monthIsClosed) return;
        document.getElementById('delete-confirm-text').textContent = '¿Estás seguro de que deseas eliminar este gasto?';
        deleteCallback = async () => {
            try {
                const res = await fetch('modules/admin/ajax_delete_expense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (data.success) {
                    loadFinances();
                } else {
                    alert(data.message || data.error || 'Error al eliminar');
                }
            } catch(e) {
                alert('Error de conexión');
            }
        };
        document.getElementById('modal-delete-confirm').classList.add('active');
    };

    // Delete confirmation handler
    document.getElementById('btn-confirm-delete').addEventListener('click', () => {
        if (deleteCallback) {
            deleteCallback();
            deleteCallback = null;
        }
        document.getElementById('modal-delete-confirm').classList.remove('active');
    });

    // ===================== RECURRING =====================
    document.getElementById('btn-generate-recurring').addEventListener('click', async () => {
        if (monthIsClosed) return;
        const btn = document.getElementById('btn-generate-recurring');
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';
        btn.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_generate_recurring.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ month: currentMonth })
            });
            const data = await res.json();
            if (data.success) {
                const count = data.generated || 0;
                const rrhh = data.generated_rrhh || 0;
                const label = rrhh > 0 ? `${count} generados (${rrhh} sueldos)` : `${count} generados`;
                btn.innerHTML = `<i class="ph ph-check"></i> ${label}`;
                btn.style.background = 'rgba(16,185,129,0.15)';
                btn.style.color = 'var(--secondary-color)';
                btn.style.borderColor = 'var(--secondary-color)';
                setTimeout(() => {
                    btn.innerHTML = origHtml;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.style.borderColor = '';
                    btn.disabled = false;
                }, 3000);
                loadFinances();
            } else {
                alert(data.message || data.error || 'Error al generar recurrentes');
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        } catch(e) {
            alert('Error de conexión');
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    });

    // Recurring templates modal
    document.getElementById('btn-manage-recurring').addEventListener('click', () => {
        renderRecurringList();
        document.getElementById('modal-recurring').classList.add('active');
    });

    function renderRecurringList() {
        const list = document.getElementById('recurring-list');
        const empty = document.getElementById('recurring-empty');
        const templates = financeData.recurring_templates || [];

        if (templates.length === 0) {
            list.innerHTML = '';
            empty.style.display = 'block';
            return;
        }

        empty.style.display = 'none';
        list.innerHTML = templates.map(t => `
            <li>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-title);">${esc(t.nombre_gasto)}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${esc(t.categoria)} · Día ${t.dia_pago}</div>
                </div>
                <div style="font-weight: 700; font-size: 0.95rem; white-space: nowrap; color: var(--color-title);">S/ ${parseFloat(t.monto).toFixed(2)}</div>
                <button class="btn-icon-sm delete" onclick="deleteRecurring(${t.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
            </li>
        `).join('');
    }

    document.getElementById('btn-save-recurring').addEventListener('click', async () => {
        const payload = {
            nombre_gasto: document.getElementById('rec-nombre').value,
            monto: document.getElementById('rec-monto').value,
            categoria: document.getElementById('rec-categoria').value,
            dia_pago: document.getElementById('rec-dia').value || 1,
        };

        if (!payload.nombre_gasto || !payload.monto) {
            alert('Completa nombre y monto.');
            return;
        }

        try {
            const res = await fetch('modules/admin/ajax_save_recurring_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('rec-nombre').value = '';
                document.getElementById('rec-monto').value = '';
                document.getElementById('rec-dia').value = '1';
                loadFinances().then(() => renderRecurringList());
            } else {
                alert(data.message || data.error || 'Error al guardar plantilla');
            }
        } catch(e) {
            alert('Error de conexión');
        }
    });

    window.deleteRecurring = async function(id) {
        if (!confirm('¿Eliminar esta plantilla recurrente?')) return;
        try {
            const res = await fetch('modules/admin/ajax_delete_recurring_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.success) {
                loadFinances().then(() => renderRecurringList());
            } else {
                alert(data.message || data.error || 'Error al eliminar');
            }
        } catch(e) {
            alert('Error de conexión');
        }
    };

    // ===================== CLOSE / REOPEN MONTH =====================
    function openCloseMonthModal() {
        const totalInc = financeData.incomes.reduce((s, i) => s + parseFloat(i.monto), 0);
        const totalExp = financeData.expenses.reduce((s, e) => s + parseFloat(e.monto), 0);
        document.getElementById('close-total-income').textContent = 'S/ ' + totalInc.toFixed(2);
        document.getElementById('close-total-expense').textContent = 'S/ ' + totalExp.toFixed(2);
        document.getElementById('close-utilidad').textContent = 'S/ ' + (totalInc - totalExp).toFixed(2);
        document.getElementById('close-monto-repartido').value = '';
        document.getElementById('modal-close-month').classList.add('active');
    }

    document.getElementById('btn-confirm-close').addEventListener('click', async () => {
        const monto = document.getElementById('close-monto-repartido').value || 0;
        const btn = document.getElementById('btn-confirm-close');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Cerrando...';

        try {
            const res = await fetch('modules/admin/ajax_close_month.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ month: currentMonth, monto_repartido: parseFloat(monto) })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('modal-close-month').classList.remove('active');
                loadFinances();
            } else {
                alert(data.message || data.error || 'Error al cerrar mes');
            }
        } catch(e) {
            alert('Error de conexión');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-lock-simple"></i> Cerrar Mes';
        }
    });

    async function reopenMonth() {
        if (!confirm('¿Estás seguro de reabrir este mes? Los registros volverán a ser editables.')) return;
        try {
            const res = await fetch('modules/admin/ajax_reopen_month.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ month: currentMonth })
            });
            const data = await res.json();
            if (data.success) {
                loadFinances();
            } else {
                alert(data.message || data.error || 'Error al reabrir mes');
            }
        } catch(e) {
            alert('Error de conexión');
        }
    }

    // ===================== HELPERS =====================
    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ===================== AI VOUCHER SCANNER =====================
    const GEMINI_API_KEY = 'AQ.Ab8RN6LHiQlYpQdxIaPvVCFxp5MxjlJVMZZBUQ2cf40bkVcZzw';

    async function scanVoucherWithAI(file, type) {
        if (!file || !file.type.match(/(image\/.*|application\/pdf)/)) return null;

        const base64Promise = new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result.split(',')[1]);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });

        try {
            const base64data = await base64Promise;
            let prompt = "";
            if (type === 'ingreso') {
                prompt = "Analiza esta imagen de un voucher o comprobante de pago. Extrae el número de operación, el monto total pagado y la fecha de pago. Devuelve ÚNICAMENTE un objeto JSON válido con las claves: 'operacion' (string), 'monto' (number) y 'fecha' (string en formato YYYY-MM-DD). El monto debe usar punto para decimales y no incluir moneda ni comas. Si no encuentras alguno, omítelo. No devuelvas ningún texto extra.";
            } else {
                prompt = "Analiza esta imagen de un comprobante, boleta o factura. Extrae el monto total pagado y la fecha de emisión/pago. Devuelve ÚNICAMENTE un objeto JSON válido con las claves 'monto' (number) y 'fecha' (string en formato YYYY-MM-DD). El monto debe usar punto para decimales y no incluir moneda ni comas. Si no encuentras alguno, omítelo. No devuelvas ningún texto extra.";
            }

            const payload = {
                contents: [{
                    parts: [
                        { text: prompt },
                        {
                            inline_data: {
                                mime_type: file.type,
                                data: base64data
                            }
                        }
                    ]
                }]
            };

            const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${GEMINI_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                const textResponse = data.candidates[0].content.parts[0].text;
                let jsonStr = textResponse.replace(/```json/gi, '').replace(/```/g, '').trim();
                return JSON.parse(jsonStr);
            }
        } catch (e) {
            console.error('Error scanning voucher:', e);
        }
        return null;
    }

    document.getElementById('income-voucher').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;
        const statusEl = document.getElementById('income-ai-status');
        statusEl.style.display = 'inline-flex';

        const result = await scanVoucherWithAI(file, 'ingreso');
        statusEl.style.display = 'none';

        if (result) {
            if (result.operacion) document.getElementById('income-operacion').value = result.operacion;
            if (result.monto) document.getElementById('income-monto').value = parseFloat(result.monto.toString().replace(/[^0-9.]/g, '')).toFixed(2);
            if (result.fecha) document.getElementById('income-fecha').value = result.fecha;
            document.getElementById('income-estado').value = 'pagado';
        }
    });

    const expenseVoucherEl = document.getElementById('expense-voucher');
    if (expenseVoucherEl) {
        expenseVoucherEl.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;
            const statusEl = document.getElementById('expense-ai-status');
            statusEl.style.display = 'inline-flex';

            const result = await scanVoucherWithAI(file, 'gasto');
            statusEl.style.display = 'none';

            if (result) {
                if (result.monto) document.getElementById('expense-monto').value = parseFloat(result.monto.toString().replace(/[^0-9.]/g, '')).toFixed(2);
                if (result.fecha) document.getElementById('expense-fecha').value = result.fecha;
            }
        });
    }

    // ===================== PASTE VOUCHER =====================
    document.addEventListener('paste', function(e) {
        const modalIncome = document.getElementById('modal-income');
        const modalExpense = document.getElementById('modal-expense');
        let targetInput = null;

        if (modalIncome && modalIncome.classList.contains('active')) {
            targetInput = document.getElementById('income-voucher');
        } else if (modalExpense && modalExpense.classList.contains('active')) {
            targetInput = document.getElementById('expense-voucher');
        }

        if (targetInput && e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
            const file = e.clipboardData.files[0];
            if (file.type.match(/(image\/.*|application\/pdf)/)) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                targetInput.files = dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                targetInput.dispatchEvent(event);
            }
        }
    });

    window.openVoucherViewer = function(url) {
        const container = document.getElementById('viewer-container');
        if (url.toLowerCase().endsWith('.pdf')) {
            container.innerHTML = `<iframe src="${url}" style="width:100%; height: 60vh; border:none; border-radius:8px;"></iframe>`;
        } else {
            container.innerHTML = `<img src="${url}" style="max-width: 100%; max-height: 60vh; object-fit: contain; border-radius:8px;">`;
        }
        document.getElementById('modal-viewer').classList.add('active');
    };

    // ===================== CAMERA =====================
    let cameraStream = null;
    let cameraTargetType = null;

    window.openCamera = async function(type) {
        cameraTargetType = type;
        const video = document.getElementById('camera-video');
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            });
            video.srcObject = cameraStream;
            document.getElementById('modal-camera').classList.add('active');
        } catch(err) {
            console.error('Camera error:', err);
            alert('No se pudo acceder a la cámara. Verifica los permisos del navegador.');
        }
    };

    window.capturePhoto = function() {
        const video = document.getElementById('camera-video');
        const canvas = document.getElementById('camera-canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function(blob) {
            const file = new File([blob], 'foto_voucher.jpg', { type: 'image/jpeg' });
            const targetInput = document.getElementById(cameraTargetType + '-voucher');
            const dt = new DataTransfer();
            dt.items.add(file);
            targetInput.files = dt.files;
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            closeCamera();
        }, 'image/jpeg', 0.9);
    };

    window.closeCamera = function() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }
        document.getElementById('camera-video').srcObject = null;
        document.getElementById('modal-camera').classList.remove('active');
    };

    // ===================== INIT =====================
    initMonthFilter();
    loadFinances();
});
</script>

<?php require_once 'includes/footer.php'; ?>
