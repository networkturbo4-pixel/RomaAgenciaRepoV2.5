<?php
// modules/quotes/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$quotes = [];
$totalQuotes = 0;
$totalBorrador = 0;
$totalAceptada = 0;
$totalEnviada = 0;

try {
    $stmt = $db->query("
        SELECT q.*, c.name as client_name 
        FROM quotes q 
        LEFT JOIN clients c ON q.client_id = c.id 
        ORDER BY q.created_at DESC
    ");
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalQuotes = count($quotes);
    foreach ($quotes as $q) {
        $st = strtolower($q['status']);
        if ($st === 'borrador') $totalBorrador++;
        if ($st === 'aceptada') $totalAceptada++;
        if ($st === 'enviada') $totalEnviada++;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   MODERN SAAS QUOTES MODULE - DESIGN SYSTEM
   ========================================================================== */

:root {
    --quote-radius-sm: 8px;
    --quote-radius-md: 14px;
    --quote-radius-lg: 18px;
    --quote-transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.quotes-page-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-size: 13px;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
    padding-bottom: 2.5rem;
}

/* --- 1. Page Header --- */
.quotes-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.quotes-title-area {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.quotes-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.quotes-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quotes-page-title i {
    color: var(--primary-color);
    font-size: 1.6rem;
}

.quotes-count-badge {
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

.quotes-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

.quote-btn-create {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.1rem;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--quote-radius-md);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    transition: var(--quote-transition);
}

.quote-btn-create:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
    color: white;
}

/* --- 2. KPI Summary Bar --- */
.quotes-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
}

.quote-kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--quote-radius-md);
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: var(--quote-transition);
}

.quote-kpi-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    transform: translateY(-1px);
}

.kpi-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: var(--quote-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.kpi-icon-wrap.blue { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; }
.kpi-icon-wrap.gold { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #f59e0b; }
.kpi-icon-wrap.teal { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; }
.kpi-icon-wrap.gray { background: color-mix(in srgb, #64748b 15%, transparent); color: #64748b; }

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

/* --- 3. Smart Toolbar: Search, Filters & Views --- */
.quotes-action-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    padding: 0.65rem 0.85rem;
    border-radius: var(--quote-radius-md);
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

.quotes-search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 460px;
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

.quotes-search-box input {
    width: 100%;
    height: 38px;
    padding: 0 4rem 0 2.25rem;
    background: var(--bg-body, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--quote-radius-sm);
    font-size: 13px;
    color: var(--text-main);
    transition: var(--quote-transition);
}

.quotes-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.quotes-search-box input::placeholder {
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
.quotes-filter-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.quotes-filter-group::-webkit-scrollbar { display: none; }

.quote-filter-pill {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: var(--quote-transition);
    display: inline-flex;
    align-items: center;
}

.quote-filter-pill:hover {
    color: var(--text-main);
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.quote-filter-pill.active {
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

/* --- 4. Table & Modern Row Styles --- */
.quotes-list-container {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--quote-radius-lg);
    overflow: hidden;
    transition: var(--quote-transition);
}

.quotes-table {
    width: 100%;
    border-collapse: collapse;
}

.quotes-table th {
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

.quotes-table td {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    color: var(--text-main);
}

.quotes-table tr:last-child td { border-bottom: none; }
.quotes-table tbody tr { transition: var(--quote-transition); }
.quotes-table tbody tr:hover { background: color-mix(in srgb, var(--text-muted) 3%, transparent); }

.quote-id-badge {
    font-weight: 700;
    font-size: 13px;
    color: var(--text-main);
}

.quote-client-name {
    font-weight: 600;
    font-size: 13.5px;
    color: var(--text-main);
}

.date-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--text-muted);
    font-size: 12px;
}

.date-chip i {
    font-size: 0.95rem;
    color: var(--text-muted);
}

.total-label-sub {
    display: none;
}

.total-val {
    font-weight: 700;
    font-size: 14px;
    color: var(--text-main);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.65rem;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.status-borrador { background: color-mix(in srgb, #64748b 15%, transparent); color: #64748b; border: 1px solid color-mix(in srgb, #64748b 30%, transparent); }
.status-enviada { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; border: 1px solid color-mix(in srgb, #3b82f6 30%, transparent); }
.status-aceptada { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; border: 1px solid color-mix(in srgb, #10b981 30%, transparent); }
.status-rechazada { background: color-mix(in srgb, #ef4444 15%, transparent); color: #ef4444; border: 1px solid color-mix(in srgb, #ef4444 30%, transparent); }
.status-expirada { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #f59e0b; border: 1px solid color-mix(in srgb, #f59e0b 30%, transparent); }

/* Action Buttons */
.actions-wrapper {
    display: flex;
    gap: 0.35rem;
    justify-content: flex-end;
}

.action-btn-saas {
    width: 34px;
    height: 34px;
    border-radius: var(--quote-radius-sm);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.05rem;
    text-decoration: none;
    transition: var(--quote-transition);
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
.quotes-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1.5rem;
    text-align: center;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: var(--quote-radius-lg);
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
   RESPONSIVE DESIGN: 2 COLUMNS KPI & MODERN ROW CARDS ON MOBILE
   ========================================================================== */

@media (max-width: 768px) {
    /* Always 2 columns for KPIs */
    .quotes-kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem;
    }

    .quote-kpi-card {
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

    .toolbar-left-group, .quotes-search-box {
        width: 100%;
        min-width: 100%;
    }

    .quotes-filter-group {
        width: 100%;
        padding-bottom: 0.35rem;
    }

    /* Convert Table into Modern SaaS Cards on Mobile */
    .quotes-list-container {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        overflow: visible !important;
    }

    .quotes-table thead {
        display: none !important;
    }

    .quotes-table, 
    .quotes-table tbody {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.85rem !important;
    }

    .quotes-table tbody tr.quote-row-card {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        grid-template-areas:
            "id status"
            "client client"
            "issue due"
            "total actions" !important;
        gap: 0.65rem 0.75rem !important;
        background: var(--bg-surface) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 16px !important;
        padding: 1.15rem !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04) !important;
        transition: var(--quote-transition) !important;
    }

    .quotes-table td {
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }

    .quotes-table td.quote-id-col {
        grid-area: id;
        display: flex;
        align-items: center;
    }

    .quote-id-badge {
        font-size: 12px;
        font-weight: 700;
        color: var(--primary-color);
        background: color-mix(in srgb, var(--primary-color) 12%, transparent);
        padding: 0.2rem 0.6rem;
        border-radius: 6px;
        border: 1px solid color-mix(in srgb, var(--primary-color) 25%, transparent);
    }

    .quotes-table td.quote-status-col {
        grid-area: status;
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    .quotes-table td.quote-client-col {
        grid-area: client;
        padding: 0.15rem 0 !important;
    }

    .quotes-table td.quote-client-col .quote-client-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
        display: block;
    }

    .quotes-table td.quote-issue-date {
        grid-area: issue;
        display: flex;
        align-items: center;
    }

    .quotes-table td.quote-due-date {
        grid-area: due;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .date-chip {
        font-size: 11px;
        background: color-mix(in srgb, var(--text-muted) 10%, transparent);
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
    }

    .quotes-table td.quote-total-col {
        grid-area: total;
        padding-top: 0.75rem !important;
        border-top: 1px dashed var(--border-color) !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .total-label-sub {
        display: block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
    }

    .total-val {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--primary-color);
    }

    .quotes-table td.quote-actions-col {
        grid-area: actions;
        padding-top: 0.75rem !important;
        border-top: 1px dashed var(--border-color) !important;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .actions-wrapper {
        display: flex;
        gap: 0.4rem;
    }

    .action-btn-saas {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 1.15rem;
    }
}

/* DARK MODE OLED */
[data-theme="dark"] body,
[data-theme="dark"] .quotes-page-wrapper {
    background-color: #000000 !important;
}
[data-theme="dark"] .quotes-action-toolbar,
[data-theme="dark"] .quote-kpi-card,
[data-theme="dark"] .quotes-list-container,
[data-theme="dark"] .quotes-empty-state {
    background: #0a0a0a !important;
    border-color: #262626 !important;
}
[data-theme="dark"] .quotes-table th {
    background: #000000 !important;
    border-bottom-color: #262626 !important;
}
[data-theme="dark"] .quotes-table td {
    border-bottom-color: #262626 !important;
}
[data-theme="dark"] .quotes-search-box input {
    background: #000000 !important;
    border-color: #262626 !important;
}
[data-theme="dark"] .quotes-search-box input:focus {
    background: #0a0a0a !important;
}
[data-theme="dark"] .quotes-table tbody tr.quote-row-card {
    background: #0a0a0a !important;
    border-color: #262626 !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important;
}
[data-theme="dark"] .action-btn-saas {
    background: #141414 !important;
    border-color: #262626 !important;
}
[data-theme="dark"] .action-btn-saas:hover {
    background: #222222 !important;
    border-color: var(--primary-color) !important;
}
</style>

<div class="quotes-page-wrapper">
    <!-- Header -->
    <div class="quotes-top-bar">
        <div class="quotes-title-area">
            <div class="quotes-title-row">
                <h1 class="quotes-page-title">
                    <i class="ph ph-file-text"></i>
                    Cotizaciones
                </h1>
                <span class="quotes-count-badge" id="totalQuotesBadge"><?php echo $totalQuotes; ?> total</span>
            </div>
            <p class="quotes-subtitle">Gestiona y crea cotizaciones profesionales para tus clientes.</p>
        </div>
        <div class="quotes-top-actions">
            <a href="index.php?module=quotes&action=form" class="quote-btn-create">
                <i class="ph ph-plus-circle"></i>
                Nueva Cotización
            </a>
        </div>
    </div>

    <!-- KPI Summary: 2 columns on mobile, 4 columns on desktop -->
    <div class="quotes-kpi-grid">
        <div class="quote-kpi-card">
            <div class="kpi-icon-wrap blue"><i class="ph ph-files"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiTotal"><?php echo $totalQuotes; ?></span>
                <span class="kpi-label">Total Cotizaciones</span>
            </div>
        </div>
        <div class="quote-kpi-card">
            <div class="kpi-icon-wrap gray"><i class="ph ph-file-dashed"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiBorrador"><?php echo $totalBorrador; ?></span>
                <span class="kpi-label">Borradores</span>
            </div>
        </div>
        <div class="quote-kpi-card">
            <div class="kpi-icon-wrap gold"><i class="ph ph-paper-plane-tilt"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiEnviada"><?php echo $totalEnviada; ?></span>
                <span class="kpi-label">Enviadas</span>
            </div>
        </div>
        <div class="quote-kpi-card">
            <div class="kpi-icon-wrap teal"><i class="ph ph-check-circle"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiAceptada"><?php echo $totalAceptada; ?></span>
                <span class="kpi-label">Aceptadas</span>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="quotes-action-toolbar">
        <div class="toolbar-left-group">
            <div class="quotes-search-box">
                <i class="ph ph-magnifying-glass search-icon-left"></i>
                <input type="text" id="quotesSearchInput" placeholder="Buscar cotización, cliente...">
                <div class="search-actions-right">
                    <i class="ph ph-spinner search-spinner-icon" id="searchSpinner" style="display:none;"></i>
                    <button class="search-clear-btn" id="searchClearBtn" style="display:none;" title="Limpiar"><i class="ph ph-x"></i></button>
                </div>
            </div>
            
            <div class="quotes-filter-group" id="statusFilters">
                <button class="quote-filter-pill active" data-status="todos">Todos</button>
                <button class="quote-filter-pill" data-status="Borrador">Borrador</button>
                <button class="quote-filter-pill" data-status="Enviada">Enviada</button>
                <button class="quote-filter-pill" data-status="Aceptada">Aceptada</button>
                <button class="quote-filter-pill" data-status="Rechazada">Rechazada</button>
            </div>
        </div>
        <div class="toolbar-right-group">
            <span class="search-result-pill" id="searchResultPill">Mostrando <?php echo $totalQuotes; ?> cotizaciones</span>
        </div>
    </div>

    <!-- List View / Table -->
    <div class="quotes-list-container">
        <table class="quotes-table" id="quotesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>F. Emisión</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="quotesTableBody">
                <!-- Loaded via AJAX -->
            </tbody>
        </table>
    </div>
    
    <div class="quotes-empty-state" id="quotesEmptyState" style="display:none;">
        <div class="empty-icon-circle"><i class="ph ph-file-x"></i></div>
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-main);">No se encontraron cotizaciones</h3>
            <p style="margin:0; font-size:13px; color:var(--text-muted);">Intenta ajustar tus filtros de búsqueda o crea una nueva.</p>
        </div>
        <button class="quote-btn-create" onclick="document.getElementById('quotesSearchInput').value=''; document.querySelector('[data-status=\'todos\']').click();">
            Limpiar Filtros
        </button>
    </div>
</div>

<!-- Modal: Eliminar -->
<div id="modal-delete-quote" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; border-radius: var(--quote-radius-lg); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color); font-size: 1.25rem;"><i class="ph ph-warning-circle"></i> Eliminar Cotización</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('modal-delete-quote').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <input type="hidden" id="delete_quote_id" value="">
        <div class="modal-body">
            <p style="margin-top: 1rem; color: var(--text-main); font-size: 14px;">¿Estás seguro de que deseas eliminar esta cotización?</p>
            <p style="color: var(--text-muted); font-size: 13px;">Esta acción <strong>no se puede deshacer</strong> y se perderán todos los datos.</p>
        </div>
        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal" style="background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-main);" onclick="document.getElementById('modal-delete-quote').classList.remove('active')">Cancelar</button>
            <button type="button" id="btnConfirmDeleteQuote" class="btn btn-pill" style="background: var(--danger-color); color: white;" onclick="executeDeleteQuote()">Sí, Eliminar</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let searchTimeout;
let currentStatus = 'todos';

const baseUrl = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/$/, '');
const publicUrlBase = baseUrl + '/c/';

function loadQuotes() {
    const query = document.getElementById('quotesSearchInput').value;
    const spinner = document.getElementById('searchSpinner');
    const clearBtn = document.getElementById('searchClearBtn');
    
    spinner.style.display = 'block';
    if(query.trim() !== '') {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }

    fetch(`modules/quotes/ajax_search_quotes.php?q=${encodeURIComponent(query)}&status=${encodeURIComponent(currentStatus)}`)
        .then(response => response.json())
        .then(res => {
            spinner.style.display = 'none';
            if(res.success) {
                renderQuotes(res.data);
            } else {
                console.error(res.error);
            }
        })
        .catch(err => {
            spinner.style.display = 'none';
            console.error(err);
        });
}

function renderQuotes(quotes) {
    const tbody = document.getElementById('quotesTableBody');
    const tableContainer = document.querySelector('.quotes-list-container');
    const emptyState = document.getElementById('quotesEmptyState');
    const pill = document.getElementById('searchResultPill');
    
    pill.textContent = `Mostrando ${quotes.length} cotizaciones`;
    
    if (quotes.length === 0) {
        tableContainer.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }
    
    tableContainer.style.display = 'block';
    emptyState.style.display = 'none';
    
    let html = '';
    quotes.forEach(q => {
        const idStr = String(q.id).padStart(4, '0');
        const st = q.status.toLowerCase();
        
        let issueDate = new Date(q.issue_date + 'T00:00:00').toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'});
        let dueDate = new Date(q.due_date + 'T00:00:00').toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'});
        
        const publicLink = publicUrlBase + q.public_token;

        html += `
        <tr class="quote-row-card">
            <td class="quote-id-col" data-label="ID">
                <span class="quote-id-badge">#${idStr}</span>
            </td>
            <td class="quote-client-col" data-label="Cliente">
                <span class="quote-client-name">${q.client_name || 'Sin Cliente'}</span>
            </td>
            <td class="quote-issue-date" data-label="F. Emisión">
                <span class="date-chip"><i class="ph ph-calendar-plus"></i> ${issueDate}</span>
            </td>
            <td class="quote-due-date" data-label="Vencimiento">
                <span class="date-chip"><i class="ph ph-clock"></i> ${dueDate}</span>
            </td>
            <td class="quote-status-col" data-label="Estado">
                <span class="status-badge status-${st}">${q.status}</span>
            </td>
            <td class="quote-total-col" data-label="Total">
                <span class="total-label-sub">Total</span>
                <span class="total-val">${q.currency} ${parseFloat(q.total).toFixed(2)}</span>
            </td>
            <td class="quote-actions-col" data-label="Acciones">
                <div class="actions-wrapper">
                    <a href="index.php?module=quotes&action=form&id=${q.id}" class="action-btn-saas" title="Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </a>
                    <button class="action-btn-saas" onclick="copyToClipboard('${publicLink}')" title="Copiar Enlace Público">
                        <i class="ph ph-link"></i>
                    </button>
                    <a href="${publicLink}" target="_blank" class="action-btn-saas" title="Vista Pública">
                        <i class="ph ph-eye"></i>
                    </a>
                    <button class="action-btn-saas delete-btn" onclick="deleteQuote(${q.id})" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Enlace copiado al portapapeles',
            showConfirmButton: false,
            timer: 2000,
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        });
    }, function(err) {
        console.error('No se pudo copiar: ', err);
    });
}

document.getElementById('quotesSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadQuotes, 300);
});

document.getElementById('searchClearBtn').addEventListener('click', function() {
    document.getElementById('quotesSearchInput').value = '';
    loadQuotes();
});

document.querySelectorAll('.quote-filter-pill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.quote-filter-pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentStatus = this.dataset.status;
        loadQuotes();
    });
});

function deleteQuote(id) {
    document.getElementById('delete_quote_id').value = id;
    document.getElementById('modal-delete-quote').classList.add('active');
}

function executeDeleteQuote() {
    const id = document.getElementById('delete_quote_id').value;
    const btn = document.getElementById('btnConfirmDeleteQuote');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Eliminando...';
    btn.disabled = true;
    
    $.post('modules/quotes/ajax_delete_quote.php', { id: id }, function(response) {
        document.getElementById('modal-delete-quote').classList.remove('active');
        if (response.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Cotización eliminada',
                showConfirmButton: false,
                timer: 2500,
                background: 'var(--bg-surface)',
                color: 'var(--text-main)'
            });
            loadQuotes();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message || 'No se pudo eliminar.',
                background: 'var(--bg-surface)',
                color: 'var(--text-main)'
            });
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 'json').fail(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
        document.getElementById('modal-delete-quote').classList.remove('active');
    });
}

// Initial load
document.addEventListener('DOMContentLoaded', loadQuotes);
</script>

<?php require_once 'includes/footer.php'; ?>
