<?php
// modules/work_orders/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Initial counts for SSR
$totalCount = (int)$db->query("SELECT COUNT(*) FROM work_orders")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM work_orders WHERE is_archived = 0")->fetchColumn();
$archivedCount = (int)$db->query("SELECT COUNT(*) FROM work_orders WHERE is_archived = 1")->fetchColumn();

require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   MODERN SAAS WORK ORDERS MODULE - DESIGN SYSTEM
   ========================================================================== */

:root {
    --wo-radius-sm: 8px;
    --wo-radius-md: 14px;
    --wo-radius-lg: 18px;
    --wo-radius-xl: 20px;
    --wo-transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.wo-page-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-size: 13px;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
    padding-bottom: 2.5rem;
}

/* --- 1. Page Header --- */
.wo-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.wo-title-area {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.wo-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.wo-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wo-page-title i {
    color: var(--primary-color);
    font-size: 1.6rem;
}

.wo-count-badge {
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

.wo-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

.wo-btn-create {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--wo-radius-md);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    transition: var(--wo-transition);
}

.wo-btn-create:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
    color: white;
}

/* --- 2. KPI Summary Bar --- */
.wo-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.85rem;
}

.wo-kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wo-radius-md);
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: var(--wo-transition);
}

.wo-kpi-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    transform: translateY(-1px);
}

.kpi-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: var(--wo-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.kpi-icon-wrap.blue { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; }
.kpi-icon-wrap.teal { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; }
.kpi-icon-wrap.amber { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #f59e0b; }

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
.wo-action-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    padding: 0.65rem 0.85rem;
    border-radius: var(--wo-radius-md);
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

.wo-search-box {
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

.wo-search-box input {
    width: 100%;
    height: 38px;
    padding: 0 4rem 0 2.25rem;
    background: var(--bg-body, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--wo-radius-sm);
    font-size: 13px;
    color: var(--text-main);
    transition: var(--wo-transition);
}

.wo-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.wo-search-box input::placeholder {
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
.wo-filter-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.wo-filter-group::-webkit-scrollbar { display: none; }

.wo-filter-pill {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: var(--wo-transition);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.wo-filter-pill:hover {
    color: var(--text-main);
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.wo-filter-pill.active {
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

.wo-view-toggle {
    display: flex;
    background: var(--bg-body, #1e1e1e);
    border: 1px solid var(--border-color);
    border-radius: var(--wo-radius-sm);
    padding: 2px;
    gap: 2px;
}

.wo-view-toggle button {
    border: none;
    background: transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: var(--wo-transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.wo-view-toggle button:hover { color: var(--text-main); }
.wo-view-toggle button.active {
    background: var(--bg-surface);
    color: var(--primary-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

/* --- 4. Cards Grid View --- */
.wo-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 1.15rem;
}

.wo-card-item {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wo-radius-lg);
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: var(--wo-transition);
    overflow: hidden;
    position: relative;
}

.wo-card-item:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.08);
}

.wo-card-item.archived {
    opacity: 0.8;
    border-style: dashed;
}

.wo-card-header-block {
    padding: 1.25rem 1.25rem 0 1.25rem;
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
}

.wo-avatar-container {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid var(--border-color);
    background: var(--bg-body);
}

.wo-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.wo-avatar-fallback {
    width: 100%;
    height: 100%;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    font-weight: 800;
}

.wo-header-text-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.wo-client-title {
    margin: 0;
    font-size: 1.05rem;
    color: var(--text-main);
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wo-brand-correlativo {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.wo-correlativo-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    font-family: monospace, sans-serif;
}

/* Channels */
.wo-channels-block {
    padding: 0.85rem 1.25rem;
    flex: 1;
}

.wo-channels-label {
    font-size: 0.65rem;
    color: var(--text-muted);
    font-weight: 700;
    letter-spacing: 0.08em;
    margin-bottom: 0.45rem;
    text-transform: uppercase;
}

.wo-channels-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

.wo-social-chip {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    color: #ffffff !important;
    font-size: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    transition: transform 0.2s ease;
}

.wo-social-chip:hover {
    transform: scale(1.15);
}

.wo-social-more {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--bg-body);
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid var(--border-color);
}

.wo-no-channels {
    font-size: 11.5px;
    color: var(--text-muted);
    font-style: italic;
    opacity: 0.6;
}

/* Action Icon Buttons */
.wo-actions-strip {
    padding: 0 1.25rem 0.85rem 1.25rem;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.wo-icon-btn {
    height: 40px;
    border-radius: var(--wo-radius-sm);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.15rem;
    text-decoration: none;
    transition: var(--wo-transition);
}

.wo-icon-btn:hover {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--bg-surface));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
    transform: translateY(-1px);
}

.wo-icon-btn.delete-btn:hover {
    background: color-mix(in srgb, var(--danger-color) 15%, var(--bg-surface));
    color: var(--danger-color);
    border-color: color-mix(in srgb, var(--danger-color) 35%, var(--border-color));
}

/* Primary Details Button */
.wo-footer-btn-block {
    padding: 0 1.25rem 1.25rem 1.25rem;
}

.wo-btn-detail {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 42px;
    border-radius: var(--wo-radius-md);
    background: #0f766e;
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: var(--wo-transition);
    box-shadow: 0 3px 10px rgba(15, 118, 110, 0.25);
    border: none;
    cursor: pointer;
}

.wo-btn-detail:hover {
    background: #0d9488;
    box-shadow: 0 5px 14px rgba(15, 118, 110, 0.35);
    color: #ffffff;
    transform: translateY(-1px);
}

/* --- 5. Empty State --- */
.wo-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1.5rem;
    text-align: center;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: var(--wo-radius-lg);
    gap: 0.75rem;
    grid-column: 1 / -1;
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
   RESPONSIVE DESIGN (Mobile & Tablet)
   ========================================================================== */

@media (max-width: 768px) {
    .wo-kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.65rem;
    }

    .wo-kpi-card {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
        border-radius: 12px;
    }

    .toolbar-left-group, .wo-search-box {
        width: 100%;
        min-width: 100%;
    }

    .wo-filter-group {
        width: 100%;
        padding-bottom: 0.35rem;
    }

    .wo-cards-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .wo-kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .wo-cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ==========================================================================
   DARK MODE OLED (Black Background & Zinc Borders)
   ========================================================================== */

[data-theme="dark"] body,
[data-theme="dark"] .wo-page-wrapper {
    background-color: #000000 !important;
}

[data-theme="dark"] .wo-action-toolbar,
[data-theme="dark"] .wo-kpi-card,
[data-theme="dark"] .wo-card-item,
[data-theme="dark"] .wo-empty-state {
    background: #0a0a0a !important;
    border-color: #262626 !important;
}

[data-theme="dark"] .wo-card-item:hover {
    border-color: var(--primary-color) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,0.7) !important;
}

[data-theme="dark"] .wo-search-box input,
[data-theme="dark"] .wo-view-toggle,
[data-theme="dark"] .wo-avatar-container {
    background: #000000 !important;
    border-color: #262626 !important;
}

[data-theme="dark"] .wo-search-box input:focus {
    background: #0a0a0a !important;
}

[data-theme="dark"] .wo-icon-btn {
    background: #121212 !important;
    border-color: #262626 !important;
}

[data-theme="dark"] .wo-icon-btn:hover {
    background: #1f1f1f !important;
}
</style>

<div class="wo-page-wrapper">
    <!-- Header -->
    <div class="wo-top-bar">
        <div class="wo-title-area">
            <div class="wo-title-row">
                <h1 class="wo-page-title">
                    <i class="ph ph-clipboard-text"></i>
                    Órdenes de Servicio
                </h1>
                <span class="wo-count-badge" id="totalWoBadge"><?php echo $totalCount; ?> total</span>
            </div>
            <p class="wo-subtitle">Gestiona y comparte las órdenes de servicio con tus clientes y equipo.</p>
        </div>
        <div class="wo-top-actions">
            <a href="index.php?module=work_orders&action=edit" class="wo-btn-create">
                <i class="ph ph-plus-circle"></i>
                Nueva Orden
            </a>
        </div>
    </div>

    <!-- KPI Summary: 2 columns on mobile, 3 columns on desktop -->
    <div class="wo-kpi-grid">
        <div class="wo-kpi-card">
            <div class="kpi-icon-wrap blue"><i class="ph ph-files"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiTotal"><?php echo $totalCount; ?></span>
                <span class="kpi-label">Total Órdenes</span>
            </div>
        </div>
        <div class="wo-kpi-card">
            <div class="kpi-icon-wrap teal"><i class="ph ph-check-circle"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiActive"><?php echo $activeCount; ?></span>
                <span class="kpi-label">Órdenes Activas</span>
            </div>
        </div>
        <div class="wo-kpi-card">
            <div class="kpi-icon-wrap amber"><i class="ph ph-archive-box"></i></div>
            <div class="kpi-content">
                <span class="kpi-val" id="kpiArchived"><?php echo $archivedCount; ?></span>
                <span class="kpi-label">Archivadas</span>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="wo-action-toolbar">
        <div class="toolbar-left-group">
            <div class="wo-search-box">
                <i class="ph ph-magnifying-glass search-icon-left"></i>
                <input type="text" id="woSearchInput" placeholder="Buscar orden, cliente, marca...">
                <div class="search-actions-right">
                    <i class="ph ph-spinner search-spinner-icon" id="woSearchSpinner" style="display:none;"></i>
                    <button class="search-clear-btn" id="woSearchClearBtn" style="display:none;" title="Limpiar"><i class="ph ph-x"></i></button>
                </div>
            </div>
            
            <div class="wo-filter-group" id="woStatusFilters">
                <button class="wo-filter-pill active" data-status="active"><i class="ph ph-check"></i> Activas</button>
                <button class="wo-filter-pill" data-status="archived"><i class="ph ph-archive-box"></i> Archivadas</button>
                <button class="wo-filter-pill" data-status="all"><i class="ph ph-squares-four"></i> Todas</button>
            </div>
        </div>
        <div class="toolbar-right-group">
            <span class="search-result-pill" id="woSearchResultPill">Mostrando <?php echo $activeCount; ?> órdenes</span>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="wo-cards-grid" id="woCardsContainer">
        <!-- Loaded via AJAX -->
    </div>

    <!-- Empty State -->
    <div class="wo-empty-state" id="woEmptyState" style="display:none;">
        <div class="empty-icon-circle"><i class="ph ph-clipboard-text"></i></div>
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-main);">No se encontraron órdenes de servicio</h3>
            <p style="margin:0; font-size:13px; color:var(--text-muted);">Intenta ajustar tus términos de búsqueda o filtros.</p>
        </div>
        <button class="wo-btn-create" onclick="document.getElementById('woSearchInput').value=''; document.querySelector('[data-status=\'active\']').click();">
            Restablecer Filtros
        </button>
    </div>
</div>

<!-- Modal: Compartir -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 440px; border-radius: var(--wo-radius-lg); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--primary-color); font-size: 1.25rem;"><i class="ph ph-share-network"></i> Compartir Orden</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('shareModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-top: 0.75rem; color: var(--text-main); font-size: 13.5px;">Copia este enlace directo para que tu cliente visualice su orden de servicio pública.</p>
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <input type="text" id="shareLinkInput" class="form-control" readonly style="flex: 1; font-size: 0.85rem; border-radius: 10px; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color); padding: 0.6rem 0.8rem;">
                <button class="wo-btn-create" style="padding: 0.6rem 1rem; border-radius: 10px;" onclick="WorkOrderModule.copyShareLink()">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Orden -->
<div id="modal-delete-order" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; border-radius: var(--wo-radius-lg); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color); font-size: 1.25rem;"><i class="ph ph-warning-circle"></i> Eliminar Orden</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('modal-delete-order').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <input type="hidden" id="delete_wo_id" value="">
        <div class="modal-body">
            <p style="margin-top: 0.75rem; color: var(--text-main); font-size: 14px;">¿Estás seguro de que deseas eliminar esta orden de servicio?</p>
            <p style="color: var(--text-muted); font-size: 13px;">Esta acción <strong>no se puede deshacer</strong> y se perderán todos los datos asociados.</p>
        </div>
        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal" style="background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-main);" onclick="document.getElementById('modal-delete-order').classList.remove('active')">Cancelar</button>
            <button type="button" id="btnConfirmDeleteWo" class="btn btn-pill" style="background: var(--danger-color); color: white;" onclick="WorkOrderModule.executeDelete()">Sí, Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Archivar / Restaurar Orden -->
<div id="modal-archive-order" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px; border-radius: var(--wo-radius-lg); background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--primary-color); font-size: 1.25rem;"><i class="ph ph-archive-box"></i> Cambiar Estado</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('modal-archive-order').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <input type="hidden" id="archive_wo_id" value="">
        <div class="modal-body">
            <p style="margin-top: 0.75rem; color: var(--text-main); font-size: 14px;">¿Deseas modificar el estado de archivo de esta orden de servicio?</p>
            <p style="color: var(--text-muted); font-size: 13px;">Podrás restaurarla o volver a archivarla cuando lo necesites.</p>
        </div>
        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal" style="background:var(--bg-body); border:1px solid var(--border-color); color:var(--text-main);" onclick="document.getElementById('modal-archive-order').classList.remove('active')">Cancelar</button>
            <button type="button" id="btnConfirmArchiveWo" class="btn btn-pill" style="background: var(--primary-color); color: white;" onclick="WorkOrderModule.executeArchive()">Confirmar</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/modules/work_orders.js?v=<?php echo time(); ?>"></script>

<script>
let woSearchTimeout;
let currentWoStatus = 'active';

const REDES_MAP = {
    'Facebook': { icon: 'ph-facebook-logo', color: '#1877F2' },
    'Instagram': { icon: 'ph-instagram-logo', color: '#E4405F' },
    'TikTok': { icon: 'ph-tiktok-logo', color: '#000000' },
    'VK': { icon: 'ph-users-three', color: '#4680C2' },
    'Google': { icon: 'ph-google-logo', color: '#DB4437' },
    'YouTube': { icon: 'ph-youtube-logo', color: '#FF0000' },
    'LinkedIn': { icon: 'ph-linkedin-logo', color: '#0A66C2' },
    'Web': { icon: 'ph-globe', color: '#0f766e' }
};

const AVATAR_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#0ea5e9'];

function loadWorkOrders() {
    const query = document.getElementById('woSearchInput').value;
    const spinner = document.getElementById('woSearchSpinner');
    const clearBtn = document.getElementById('woSearchClearBtn');

    spinner.style.display = 'block';
    clearBtn.style.display = query.trim() !== '' ? 'flex' : 'none';

    fetch(`modules/work_orders/ajax_search_orders.php?q=${encodeURIComponent(query)}&status=${encodeURIComponent(currentWoStatus)}`)
        .then(res => res.json())
        .then(res => {
            spinner.style.display = 'none';
            if (res.success) {
                // Update KPI counts
                if (res.counts) {
                    document.getElementById('totalWoBadge').textContent = `${res.counts.total} total`;
                    document.getElementById('kpiTotal').textContent = res.counts.total;
                    document.getElementById('kpiActive').textContent = res.counts.active;
                    document.getElementById('kpiArchived').textContent = res.counts.archived;
                }
                renderWorkOrders(res.data);
            } else {
                console.error(res.error);
            }
        })
        .catch(err => {
            spinner.style.display = 'none';
            console.error(err);
        });
}

function renderWorkOrders(orders) {
    const container = document.getElementById('woCardsContainer');
    const emptyState = document.getElementById('woEmptyState');
    const pill = document.getElementById('woSearchResultPill');

    pill.textContent = `Mostrando ${orders.length} órdenes`;

    if (orders.length === 0) {
        container.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }

    container.style.display = 'grid';
    emptyState.style.display = 'none';

    let html = '';
    orders.forEach(wo => {
        const initial = (wo.cliente || 'S').charAt(0).toUpperCase();
        const color = AVATAR_COLORS[wo.cliente.length % AVATAR_COLORS.length];
        const isArchived = wo.is_archived === 1;

        // Avatar
        let avatarHtml = '';
        if (wo.brand_logo) {
            avatarHtml = `<img src="${wo.brand_logo}" class="wo-avatar-img" alt="logo">`;
        } else {
            avatarHtml = `<div class="wo-avatar-fallback" style="background:${color};">${initial}</div>`;
        }

        // Channels
        let channelsHtml = '';
        if (wo.redes && wo.redes.length > 0) {
            const maxShow = 4;
            let chips = '';
            wo.redes.forEach((r, idx) => {
                const netId = r.id || '';
                if (!netId) return;

                if (idx < maxShow) {
                    const conf = REDES_MAP[netId] || { icon: 'ph-share-network', color: '#577a9e' };
                    const url = r.url || '';
                    if (url) {
                        chips += `<a href="${url}" target="_blank" class="wo-social-chip" style="background:${conf.color};" title="${netId}"><i class="ph ${conf.icon}"></i></a>`;
                    } else {
                        chips += `<span class="wo-social-chip" style="background:${conf.color};" title="${netId}"><i class="ph ${conf.icon}"></i></span>`;
                    }
                } else if (idx === maxShow) {
                    chips += `<span class="wo-social-more" title="Más canales">+${wo.redes.length - maxShow}</span>`;
                }
            });

            channelsHtml = `
                <div class="wo-channels-block">
                    <div class="wo-channels-label">Canales</div>
                    <div class="wo-channels-list">${chips}</div>
                </div>
            `;
        } else {
            channelsHtml = `
                <div class="wo-channels-block">
                    <div class="wo-channels-label">Canales</div>
                    <div class="wo-no-channels">Sin canales configurados</div>
                </div>
            `;
        }

        const archiveIcon = isArchived ? 'ph-arrow-counter-clockwise' : 'ph-archive-box';
        const archiveTitle = isArchived ? 'Restaurar Orden' : 'Archivar Orden';

        html += `
            <div class="wo-card-item ${isArchived ? 'archived' : ''}">
                <!-- Header -->
                <div class="wo-card-header-block">
                    <div class="wo-avatar-container">
                        ${avatarHtml}
                    </div>
                    <div class="wo-header-text-group">
                        <h3 class="wo-client-title" title="${wo.cliente}">
                            ${wo.cliente}
                        </h3>
                        <div class="wo-brand-correlativo">
                            <span>${wo.brand_name}</span>
                            <span style="opacity:0.4;">•</span>
                            <span class="wo-correlativo-badge">${wo.correlativo}</span>
                        </div>
                    </div>
                </div>

                <!-- Channels -->
                ${channelsHtml}

                <!-- Actions Strip -->
                <div class="wo-actions-strip">
                    <button type="button" class="wo-icon-btn" onclick="WorkOrderModule.shareOrder('${wo.public_token}');" title="Compartir">
                        <i class="ph ph-share-network"></i>
                    </button>
                    <a href="index.php?module=work_orders&action=edit&id=${wo.id}" class="wo-icon-btn" title="Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </a>
                    <button type="button" class="wo-icon-btn" onclick="WorkOrderModule.archiveOrder(${wo.id});" title="${archiveTitle}">
                        <i class="ph ${archiveIcon}"></i>
                    </button>
                    <button type="button" class="wo-icon-btn delete-btn" onclick="WorkOrderModule.confirmDelete(${wo.id});" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>

                <!-- Detail / Edit Button -->
                <div class="wo-footer-btn-block">
                    <a href="index.php?module=work_orders&action=edit&id=${wo.id}" class="wo-btn-detail">
                        Ver Detalle
                    </a>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Search input debounce
document.getElementById('woSearchInput').addEventListener('input', function() {
    clearTimeout(woSearchTimeout);
    woSearchTimeout = setTimeout(loadWorkOrders, 300);
});

document.getElementById('woSearchClearBtn').addEventListener('click', function() {
    document.getElementById('woSearchInput').value = '';
    loadWorkOrders();
});

// Filter pill clicks
document.querySelectorAll('.wo-filter-pill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.wo-filter-pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentWoStatus = this.dataset.status;
        loadWorkOrders();
    });
});

// Initial load
document.addEventListener('DOMContentLoaded', loadWorkOrders);
</script>

<?php require_once 'includes/footer.php'; ?>
