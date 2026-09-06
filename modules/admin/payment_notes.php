<?php
// modules/admin/payment_notes.php
require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   PAYMENT NOTES - MODERN SAAS APP STYLES
   ========================================================================== */
.pn-app-container {
    padding: 0.25rem 0 2.5rem;
    font-size: 13px;
}

/* App Header */
.pn-app-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
}

[data-theme="dark"] .pn-app-header {
    background: #0b0b0e;
    border-color: rgba(255, 255, 255, 0.08);
}

.pn-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pn-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.pn-header-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--color-title);
    line-height: 1.2;
}

.pn-header-desc {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.8125rem;
}

.pn-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn-pn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.2rem;
    border-radius: 10px;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    white-space: nowrap;
}

.btn-pn-methods {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    color: var(--color-title);
}

.btn-pn-methods:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.btn-pn-new {
    background: var(--primary-color);
    border: 1px solid var(--primary-color);
    color: #ffffff;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 35%, transparent);
}

.btn-pn-new:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--primary-color) 50%, transparent);
    color: #ffffff;
}

/* Summary Cards Grid */
.pn-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pn-metric-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.15rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .pn-metric-card {
    background: #0e0e12;
    border-color: rgba(255, 255, 255, 0.08);
}

.pn-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

.pn-metric-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.pn-icon-total { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.pn-icon-pending { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.pn-icon-paid { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.pn-icon-balance { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }

.pn-metric-content {
    min-width: 0;
    flex: 1;
}

.pn-metric-val {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--color-title);
    line-height: 1.1;
}

.pn-metric-lbl {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
    margin-top: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.pn-overdue-banner {
    grid-column: 1 / -1;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 14px;
    padding: 0.85rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #ef4444;
}

/* Toolbar: Search, Filters & Sort */
.pn-toolbar {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 0.85rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

[data-theme="dark"] .pn-toolbar {
    background: #0b0b0e;
    border-color: rgba(255, 255, 255, 0.08);
}

.pn-search-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.pn-search-wrap i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
    pointer-events: none;
}

.pn-search-input {
    width: 100%;
    padding: 0.55rem 1rem 0.55rem 2.4rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-color);
    color: var(--color-title);
    font-size: 0.8125rem;
    outline: none;
    transition: all 0.2s;
}

.pn-search-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 14%, transparent);
}

.pn-filter-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pn-pill {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.4rem 0.85rem;
    border-radius: 9px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    user-select: none;
}

.pn-pill:hover {
    color: var(--color-title);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}

.pn-pill.active {
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);
}

.pn-pill .pill-count {
    font-size: 0.7rem;
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.06);
}

[data-theme="dark"] .pn-pill .pill-count {
    background: rgba(255,255,255,0.08);
}

.pn-pill.active .pill-count {
    background: color-mix(in srgb, var(--primary-color) 25%, transparent);
    color: var(--primary-color);
}

.pn-sort-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pn-sort-select {
    padding: 0.5rem 0.85rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-color);
    color: var(--color-title);
    font-size: 0.8125rem;
    font-weight: 500;
    outline: none;
    cursor: pointer;
}

/* Notes Grid & Cards */
.pn-notes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}

.pn-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}

[data-theme="dark"] .pn-card {
    background: #0e0e12;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}

.pn-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.07);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}

[data-theme="dark"] .pn-card:hover {
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
    border-color: var(--primary-color);
}

/* Card Header */
.pn-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pn-card-id {
    font-family: monospace;
    font-weight: 800;
    font-size: 1rem;
    color: var(--primary-color);
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-copy-id {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.85rem;
    padding: 2px 4px;
    border-radius: 4px;
    transition: color 0.15s;
}

.btn-copy-id:hover {
    color: var(--primary-color);
}

.pn-card-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.75rem;
    border-radius: 30px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* Status variants */
.status-badge-paid {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge-pending {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge-process {
    background: rgba(59, 130, 246, 0.14);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-badge-overdue {
    background: rgba(239, 68, 68, 0.14);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-badge-inactive {
    background: rgba(100, 116, 139, 0.14);
    color: #64748b;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

/* Meta Chips */
.pn-chips-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pn-chip {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-color);
    padding: 0.22rem 0.6rem;
    border-radius: 7px;
    border: 1px solid var(--border-color);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Client block */
.pn-client-block {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 0.85rem;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}

[data-theme="dark"] .pn-client-block {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.06);
}

.pn-client-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    text-transform: uppercase;
}

.pn-client-info {
    min-width: 0;
    flex: 1;
}

.pn-client-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-title);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pn-client-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 0.1rem;
}

/* Financial summary */
.pn-finances-box {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pn-progress-bar-wrap {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: rgba(0,0,0,0.06);
    overflow: hidden;
}

[data-theme="dark"] .pn-progress-bar-wrap {
    background: rgba(255,255,255,0.08);
}

.pn-progress-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: #10b981;
    transition: width 0.3s ease;
}

.pn-financial-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.pn-fin-item-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.3px;
}

.pn-fin-item-val {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-title);
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pn-balance-val {
    font-size: 1.15rem;
    font-weight: 800;
}
.pn-balance-zero { color: #10b981; }
.pn-balance-pending { color: #ef4444; }

/* Card Actions */
.pn-card-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: auto;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}

.btn-card-view {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 0.85rem;
    border-radius: 9px;
    background: color-mix(in srgb, var(--primary-color) 10%, var(--bg-surface));
    border: 1px solid color-mix(in srgb, var(--primary-color) 25%, transparent);
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.8125rem;
    text-decoration: none;
    transition: all 0.18s ease;
}

.btn-card-view:hover {
    background: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
}

.btn-card-icon-action {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.18s ease;
    text-decoration: none;
    flex-shrink: 0;
}

[data-theme="dark"] .btn-card-icon-action {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.08);
}

.btn-card-icon-action:hover {
    color: var(--primary-color);
    border-color: var(--primary-color);
    transform: scale(1.04);
}

.btn-card-delete:hover {
    color: #ef4444 !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
    background: rgba(239, 68, 68, 0.1) !important;
}

/* Empty State Modern */
.pn-empty-box {
    background: var(--bg-surface);
    border: 1.5px dashed var(--border-color);
    border-radius: 20px;
    padding: 3.5rem 1.5rem;
    text-align: center;
    max-width: 540px;
    margin: 2rem auto;
}

[data-theme="dark"] .pn-empty-box {
    background: #0e0e12;
    border-color: rgba(255, 255, 255, 0.1);
}

.pn-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1.25rem;
}

/* Modals Modern */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1060;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-app-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    width: 100%;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    animation: modalScaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .modal-app-card {
    background: #0e0e12;
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
}

@keyframes modalScaleIn {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

.btn-close-circular {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.15s;
}

.btn-close-circular:hover {
    color: var(--color-title);
    background: var(--bg-surface);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .pn-metrics-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.65rem !important;
        margin-bottom: 1.25rem !important;
    }
    .pn-metric-card {
        padding: 0.75rem 0.8rem !important;
        gap: 0.65rem !important;
        border-radius: 12px !important;
        min-width: 0 !important;
    }
    .pn-metric-icon {
        width: 38px !important;
        height: 38px !important;
        font-size: 1.15rem !important;
        border-radius: 10px !important;
    }
    .pn-metric-val {
        font-size: 1.25rem !important;
    }
    .pn-metric-lbl {
        font-size: 0.68rem !important;
    }
    .pn-app-header {
        flex-direction: column;
        align-items: stretch;
        padding: 1.1rem;
        gap: 1rem;
    }
    .pn-header-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .btn-pn-action {
        justify-content: center;
        width: 100%;
    }
    .pn-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.85rem;
    }
    .pn-search-wrap {
        width: 100%;
        min-width: 0;
    }
    .pn-filter-pills {
        overflow-x: auto;
        padding-bottom: 0.25rem;
        flex-wrap: nowrap;
        scrollbar-width: none;
    }
    .pn-filter-pills::-webkit-scrollbar {
        display: none;
    }
    .pn-notes-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 380px) {
    .pn-metrics-grid {
        gap: 0.5rem !important;
    }
    .pn-metric-card {
        padding: 0.65rem 0.6rem !important;
        gap: 0.5rem !important;
    }
    .pn-metric-icon {
        width: 32px !important;
        height: 32px !important;
        font-size: 1.05rem !important;
    }
    .pn-metric-val {
        font-size: 1.1rem !important;
    }
    .pn-metric-lbl {
        font-size: 0.64rem !important;
    }
}
</style>

<div class="pn-app-container">
    <!-- Top Header -->
    <div class="pn-app-header">
        <div class="pn-header-left">
            <div class="pn-header-icon">
                <i class="ph-fill ph-receipt"></i>
            </div>
            <div>
                <h1 class="pn-header-title">Notas de Pago</h1>
                <p class="pn-header-desc">Gestiona, visualiza y comparte notas de cobro y membresías de clientes</p>
            </div>
        </div>
        <div class="pn-header-actions">
            <button type="button" class="btn-pn-action btn-pn-methods" id="btn-open-payment-methods">
                <i class="ph-bold ph-credit-card"></i> Formas de Pago
            </button>
            <button type="button" class="btn-pn-action btn-pn-new" onclick="window.location.href='index.php?module=admin&action=payment_note_webview&id=NEW'">
                <i class="ph-bold ph-plus"></i> Nueva Nota
            </button>
        </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div id="summary-cards-container" class="pn-metrics-grid">
        <!-- Dynamically injected via renderNotes() -->
    </div>

    <!-- Toolbar: Search, Filters, Sort -->
    <div class="pn-toolbar">
        <div class="pn-search-wrap">
            <i class="ph-bold ph-magnifying-glass"></i>
            <input type="text" id="pn-search-input" class="pn-search-input" placeholder="Buscar por cliente, empresa, N° de nota...">
        </div>
        <div class="pn-filter-pills">
            <div class="pn-pill active" data-filter="all">Todos <span class="pill-count" id="count-all">0</span></div>
            <div class="pn-pill" data-filter="pendiente">Pendientes <span class="pill-count" id="count-pendientes">0</span></div>
            <div class="pn-pill" data-filter="en_proceso">En proceso <span class="pill-count" id="count-proceso">0</span></div>
            <div class="pn-pill" data-filter="pagado">Pagados <span class="pill-count" id="count-pagados">0</span></div>
            <div class="pn-pill" data-filter="retrasado">Retrasados <span class="pill-count" id="count-retrasados">0</span></div>
        </div>
        <div class="pn-sort-wrap">
            <select id="pn-sort-select" class="pn-sort-select" title="Ordenar notas">
                <option value="recent">Más recientes</option>
                <option value="oldest">Más antiguos</option>
                <option value="amount_desc">Mayor monto</option>
                <option value="amount_asc">Menor monto</option>
                <option value="client">Cliente (A - Z)</option>
            </select>
        </div>
    </div>

    <!-- Cards Grid -->
    <div id="notes-grid" class="pn-notes-grid">
        <!-- Cards will be injected here via JS -->
    </div>

    <!-- Empty State -->
    <div id="empty-state" class="pn-empty-box" style="display: none;">
        <div class="pn-empty-icon">
            <i class="ph-fill ph-receipt"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-title); margin: 0 0 0.5rem;" id="empty-state-title">No hay notas de pago</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1.5rem;" id="empty-state-desc">Crea tu primera nota de pago para empezar a cobrar y gestionar cuotas con tus clientes.</p>
        <button type="button" class="btn-pn-action btn-pn-new" id="empty-state-btn" onclick="window.location.href='index.php?module=admin&action=payment_note_webview&id=NEW'">
            <i class="ph-bold ph-plus"></i> Crear Primera Nota
        </button>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal-overlay" id="modal-delete-note">
    <div class="modal-app-card" style="max-width: 420px; text-align: center; padding: 2rem 1.5rem;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1.25rem;">
            <i class="ph-fill ph-warning-circle"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-title); margin: 0 0 0.5rem;">Eliminar Nota de Pago</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1.5rem;">¿Estás seguro de que deseas eliminar la nota <strong id="delete-note-id" style="color: var(--primary-color);"></strong>? Esta acción no se puede deshacer y se perderán todos los datos asociados.</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <button type="button" class="btn-close-modal" style="padding: 0.65rem; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); font-weight: 600; cursor: pointer; transition: all 0.15s;">Cancelar</button>
            <button type="button" id="btn-confirm-delete" style="padding: 0.65rem; border-radius: 10px; background: #ef4444; border: none; color: #fff; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">Sí, Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Share Note -->
<div class="modal-overlay" id="modal-share-note">
    <div class="modal-app-card" style="max-width: 500px; padding: 1.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    <i class="ph-fill ph-share-network"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--color-title);">Compartir Nota</h3>
                    <p style="margin: 0.15rem 0 0; font-size: 0.75rem; color: var(--text-muted);">Envía el enlace público a tu cliente</p>
                </div>
            </div>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph-bold ph-x"></i></button>
        </div>

        <div style="border-bottom: 1px solid var(--border-color); margin-bottom: 1.25rem;">
            <div style="display: flex; gap: 0.5rem;">
                <button class="tab-btn active" data-target="tab-link" style="background: none; border: none; padding: 0.5rem 0.75rem 0.75rem; color: var(--primary-color); font-weight: 600; border-bottom: 2px solid var(--primary-color); cursor: pointer; transition: all 0.2s; font-size: 0.8125rem;"><i class="ph-bold ph-link"></i> Enlace</button>
                <button class="tab-btn" data-target="tab-whatsapp" style="background: none; border: none; padding: 0.5rem 0.75rem 0.75rem; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8125rem;"><i class="ph-bold ph-whatsapp-logo"></i> WhatsApp</button>
                <button class="tab-btn" data-target="tab-email" style="background: none; border: none; padding: 0.5rem 0.75rem 0.75rem; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8125rem;"><i class="ph-bold ph-envelope-simple"></i> Correo</button>
            </div>
        </div>

        <div>
            <!-- Tab: Link -->
            <div id="tab-link" class="share-tab-content">
                <p style="margin: 0 0 1rem; color: var(--text-muted); font-size: 0.85rem;">Copia el enlace corto para compartirlo por cualquier canal con tu cliente.</p>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="share-link-input" class="form-control" readonly style="flex: 1; font-size: 0.8125rem; border-radius: 10px; font-family: monospace; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.6rem 0.85rem;">
                    <button type="button" id="btn-copy-link" class="btn-pn-action btn-pn-new" style="white-space: nowrap; border-radius: 10px; padding: 0.6rem 1rem;">
                        <i class="ph-bold ph-copy"></i> Copiar
                    </button>
                </div>
            </div>

            <!-- Tab: WhatsApp -->
            <div id="tab-whatsapp" class="share-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--color-title); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Número de WhatsApp (con código de país)</label>
                    <div style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--bg-color);">
                        <div style="padding: 0.55rem 0.85rem; background: rgba(0,0,0,0.03); color: var(--text-muted); font-weight: 700; border-right: 1px solid var(--border-color);">+</div>
                        <input type="text" id="share-wa-phone" class="form-control" placeholder="51999999999" style="border: none; border-radius: 0; outline: none; background: transparent; padding: 0.55rem 0.85rem; font-size: 0.8125rem; color: var(--color-title);">
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Ejemplo: 51902595959 (sin el símbolo +)</small>
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--color-title); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Mensaje</label>
                    <textarea id="share-wa-msg" class="form-control" rows="4" style="resize: vertical; font-size: 0.8125rem; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.65rem 0.85rem; width: 100%; box-sizing: border-box;"></textarea>
                </div>
                <button type="button" id="btn-send-wa" class="btn-pn-action" style="width: 100%; justify-content: center; background: #25D366; border: none; color: white; box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);">
                    <i class="ph-bold ph-paper-plane-right"></i> Enviar por WhatsApp
                </button>
            </div>

            <!-- Tab: Email -->
            <div id="tab-email" class="share-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--color-title); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Correo del Cliente</label>
                    <input type="email" id="share-email-to" class="form-control" placeholder="cliente@correo.com" style="font-size: 0.8125rem; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.55rem 0.85rem; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--color-title); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Asunto</label>
                    <input type="text" id="share-email-subject" class="form-control" style="font-size: 0.8125rem; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.55rem 0.85rem; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--color-title); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Mensaje</label>
                    <textarea id="share-email-msg" class="form-control" rows="3" style="resize: vertical; font-size: 0.8125rem; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.65rem 0.85rem; width: 100%; box-sizing: border-box;"></textarea>
                </div>
                <button type="button" id="btn-send-email" class="btn-pn-action btn-pn-new" style="width: 100%; justify-content: center;">
                    <i class="ph-bold ph-envelope-simple-open"></i> Enviar Correo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Payment Methods -->
<div class="modal-overlay" id="modal-payment-methods">
    <div class="modal-app-card" style="max-width: 620px; padding: 1.75rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    <i class="ph-fill ph-credit-card"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--color-title);">Formas de Pago</h3>
                    <p style="margin: 0.15rem 0 0; font-size: 0.75rem; color: var(--text-muted);">Configura las cuentas y billeteras para tus notas</p>
                </div>
            </div>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph-bold ph-x"></i></button>
        </div>

        <!-- List of existing methods -->
        <div id="pm-list" style="max-height: 320px; overflow-y: auto; margin-bottom: 1.25rem;"></div>

        <!-- Add new method form -->
        <div style="padding: 1.15rem; border: 1px solid var(--border-color); border-radius: 14px; background: var(--bg-color);">
            <div style="font-weight: 700; font-size: 0.8125rem; margin-bottom: 0.75rem; color: var(--primary-color); display: flex; align-items: center; gap: 0.4rem;">
                <i class="ph-bold ph-plus-circle"></i> Agregar Nuevo Método
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                <input type="text" id="pm-new-label" class="form-control" placeholder="Nombre (Ej: BCP Soles, Yape)" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title);">
                <input type="text" id="pm-new-code" class="form-control" placeholder="Cuenta / CCI / Celular" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title);">
            </div>
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem;">
                <input type="text" id="pm-new-image" class="form-control" placeholder="URL del logo o QR (opcional)" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title);">
                <button type="button" id="btn-pm-add" class="btn-pn-action btn-pn-new" style="padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.8125rem;">
                    <i class="ph-bold ph-plus"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // State
    let notes = [];
    let availableClients = [];
    let noteToDeleteId = null;
    let activeNoteForShare = null;
    let activeNoteUrlForShare = '';
    let currentFilter = 'all';
    let searchQuery = '';
    let currentSort = 'recent';

    // Elements
    const notesGrid = document.getElementById('notes-grid');
    const emptyState = document.getElementById('empty-state');
    const emptyStateTitle = document.getElementById('empty-state-title');
    const emptyStateDesc = document.getElementById('empty-state-desc');
    const emptyStateBtn = document.getElementById('empty-state-btn');
    const summaryContainer = document.getElementById('summary-cards-container');
    const searchInput = document.getElementById('pn-search-input');
    const sortSelect = document.getElementById('pn-sort-select');
    const filterPills = document.querySelectorAll('.pn-pill');
    
    // Modals
    const modalDeleteNote = document.getElementById('modal-delete-note');
    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    const modalShareNote = document.getElementById('modal-share-note');
    const shareLinkInput = document.getElementById('share-link-input');
    const btnCopyLink = document.getElementById('btn-copy-link');

    // Close modals
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });

    // Avatar helpers
    const avatarGradients = [
        'linear-gradient(135deg, #3b82f6, #1d4ed8)',
        'linear-gradient(135deg, #10b981, #047857)',
        'linear-gradient(135deg, #8b5cf6, #6d28d9)',
        'linear-gradient(135deg, #f59e0b, #b45309)',
        'linear-gradient(135deg, #ec4899, #be185d)',
        'linear-gradient(135deg, #06b6d4, #0e7490)'
    ];

    function getInitials(name) {
        if (!name) return 'CL';
        const clean = name.trim();
        const parts = clean.split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function getAvatarGradient(name) {
        let hash = 0;
        const str = name || 'cliente';
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return avatarGradients[Math.abs(hash) % avatarGradients.length];
    }

    // Helper: Compute note financials and status
    function processNoteData(note) {
        let noteStatusText = 'Pagado';
        let statusClass = 'status-badge-paid';
        let statusKey = 'pagado';
        let isPagado = true;

        if (note.cronograma && note.cronograma.length > 0) {
            let hasRetrasado = false;
            let hasEnProceso = false;
            let hasNoActivo = false;
            
            note.cronograma.forEach(c => {
                if (c.estado !== 'pagado') {
                    isPagado = false;
                    if (!c.fecha) {
                        hasEnProceso = true;
                        return;
                    }
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    const parts = c.fecha.split('-');
                    const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                    const diffTime = cuotaDate.getTime() - today.getTime();
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays < -15) {
                        hasRetrasado = true;
                    } else if (diffDays <= 7 && diffDays >= -15) {
                        hasEnProceso = true;
                    } else {
                        hasNoActivo = true;
                    }
                }
            });

            if (!isPagado) {
                if (hasRetrasado) {
                    noteStatusText = 'Retrasado';
                    statusClass = 'status-badge-overdue';
                    statusKey = 'retrasado';
                } else if (hasEnProceso) {
                    noteStatusText = 'En proceso';
                    statusClass = 'status-badge-process';
                    statusKey = 'en_proceso';
                } else if (hasNoActivo) {
                    noteStatusText = 'No Activo';
                    statusClass = 'status-badge-inactive';
                    statusKey = 'en_proceso';
                } else {
                    noteStatusText = 'Pendiente';
                    statusClass = 'status-badge-pending';
                    statusKey = 'pendiente';
                }
            }
        } else {
            isPagado = (note.status === 'PAGADO');
            if (!isPagado) {
                noteStatusText = 'Pendiente';
                statusClass = 'status-badge-pending';
                statusKey = 'pendiente';
            }
        }

        let computedTotal = parseFloat(note.total || 0);
        if ((note.servicios && note.servicios.length > 0) || (note.cronograma && note.cronograma.length > 0)) {
            let noteTotalServicios = 0;
            let noteTotalPendiente = 0;
            
            if (note.servicios) {
                noteTotalServicios = note.servicios.reduce((sum, s) => sum + (parseFloat(s.cantidad || 0) * parseFloat(s.costoUnit || 0)), 0);
            }

            if (note.cronograma) {
                note.cronograma.forEach(c => {
                    if (c.estado === 'pagado') return;
                    if (!c.fecha) {
                        noteTotalPendiente += parseFloat(c.monto || 0);
                        return;
                    }
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    const parts = c.fecha.split('-');
                    const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                    const diffTime = cuotaDate.getTime() - today.getTime();
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays <= 7) { 
                        noteTotalPendiente += parseFloat(c.monto || 0);
                    }
                });
            }
            computedTotal = noteTotalServicios + noteTotalPendiente;
        }

        // Subtract abonos
        let totalAbonos = 0;
        if (note.abonos && note.abonos.length > 0) {
            totalAbonos = note.abonos.reduce((sum, a) => sum + parseFloat(a.monto || 0), 0);
        }

        const balance = isPagado ? 0 : Math.max(0, computedTotal - totalAbonos);

        // Check if note is overdue
        let isOverdue = false;
        if (!isPagado && note.startDate) {
            const dueDays = note.due_days || 30;
            const start = new Date(note.startDate);
            const dueDate = new Date(start);
            dueDate.setDate(dueDate.getDate() + dueDays);
            const today = new Date();
            today.setHours(0,0,0,0);
            if (today > dueDate) {
                isOverdue = true;
            }
        }

        return {
            noteStatusText,
            statusClass,
            statusKey,
            isPagado,
            computedTotal,
            totalAbonos,
            balance,
            isOverdue
        };
    }

    // Fetch and Migrate
    async function loadNotes() {
        const localNotes = localStorage.getItem('payment_notes');
        if (localNotes) {
            try {
                const parsed = JSON.parse(localNotes);
                if (parsed.length > 0) {
                    const res = await fetch('modules/admin/ajax_migrate_payment_notes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ notes: parsed })
                    });
                    if (res.ok) {
                        const migrationData = await res.json();
                        if (migrationData.success === true) {
                            localStorage.removeItem('payment_notes');
                        }
                    }
                } else {
                    localStorage.removeItem('payment_notes');
                }
            } catch(e) {
                console.error('Migration error', e);
            }
        }

        try {
            const res = await fetch('modules/admin/ajax_get_payment_notes.php');
            const data = await res.json();
            if (data.success) {
                notes = data.notes || [];
                if (data.clients) availableClients = data.clients;
                renderNotes();
            }
        } catch(e) {
            console.error('Fetch error', e);
        }
    }

    // Render Main Function
    function renderNotes() {
        notesGrid.innerHTML = '';

        if (notes.length === 0) {
            notesGrid.style.display = 'none';
            if (summaryContainer) summaryContainer.style.display = 'none';
            emptyState.style.display = 'block';
            emptyStateTitle.innerText = 'No hay notas de pago';
            emptyStateDesc.innerText = 'Crea tu primera nota de pago para empezar a cobrar y gestionar cuotas con tus clientes.';
            emptyStateBtn.style.display = 'inline-flex';
            emptyStateBtn.innerHTML = '<i class="ph-bold ph-plus"></i> Crear Primera Nota';
            emptyStateBtn.onclick = () => window.location.href='index.php?module=admin&action=payment_note_webview&id=NEW';
            return;
        }

        notesGrid.style.display = 'grid';
        if (summaryContainer) summaryContainer.style.display = 'grid';
        emptyState.style.display = 'none';

        // Process all notes metrics
        let totalNotas = notes.length;
        let countPendientes = 0;
        let countProceso = 0;
        let countPagados = 0;
        let countRetrasados = 0;
        let countVencidas = 0;
        let saldoPendienteTotal = 0;

        const processedList = notes.map((note, index) => {
            const processed = processNoteData(note);
            note._processed = processed;
            note._index = index;

            if (processed.noteStatusText === 'Pagado') {
                countPagados++;
            } else if (processed.noteStatusText === 'Retrasado') {
                countRetrasados++;
            } else if (processed.noteStatusText === 'En proceso') {
                countProceso++;
            } else {
                countPendientes++;
            }

            if (processed.isOverdue) {
                countVencidas++;
            }

            saldoPendienteTotal += parseFloat(processed.balance || 0);

            return note;
        });

        // Update Summary Cards
        if (summaryContainer) {
            let overdueAlert = '';
            if (countVencidas > 0) {
                overdueAlert = `
                    <div class="pn-overdue-banner">
                        <i class="ph-fill ph-warning-circle" style="font-size: 1.3rem;"></i>
                        <span style="font-size: 0.8125rem; font-weight: 600;">⚠️ Tienes ${countVencidas} nota${countVencidas !== 1 ? 's' : ''} con fecha vencida sin pago</span>
                    </div>
                `;
            }

            summaryContainer.innerHTML = overdueAlert + `
                <div class="pn-metric-card">
                    <div class="pn-metric-icon pn-icon-total">
                        <i class="ph-fill ph-receipt"></i>
                    </div>
                    <div class="pn-metric-content">
                        <div class="pn-metric-val">${totalNotas}</div>
                        <div class="pn-metric-lbl">Total Notas</div>
                    </div>
                </div>
                <div class="pn-metric-card">
                    <div class="pn-metric-icon pn-icon-pending">
                        <i class="ph-fill ph-clock-countdown"></i>
                    </div>
                    <div class="pn-metric-content">
                        <div class="pn-metric-val">${countPendientes + countProceso}</div>
                        <div class="pn-metric-lbl">Pendientes</div>
                    </div>
                </div>
                <div class="pn-metric-card">
                    <div class="pn-metric-icon pn-icon-paid">
                        <i class="ph-fill ph-check-circle"></i>
                    </div>
                    <div class="pn-metric-content">
                        <div class="pn-metric-val">${countPagados}</div>
                        <div class="pn-metric-lbl">Pagados</div>
                    </div>
                </div>
                <div class="pn-metric-card">
                    <div class="pn-metric-icon pn-icon-balance">
                        <i class="ph-fill ph-wallet"></i>
                    </div>
                    <div class="pn-metric-content">
                        <div class="pn-metric-val" style="color: ${saldoPendienteTotal > 0 ? '#ef4444' : '#10b981'};">S/ ${saldoPendienteTotal.toFixed(2)}</div>
                        <div class="pn-metric-lbl">Saldo Pendiente</div>
                    </div>
                </div>
            `;
        }

        // Update Filter Pill Counts
        const elAll = document.getElementById('count-all');
        const elPendientes = document.getElementById('count-pendientes');
        const elProceso = document.getElementById('count-proceso');
        const elPagados = document.getElementById('count-pagados');
        const elRetrasados = document.getElementById('count-retrasados');

        if (elAll) elAll.innerText = totalNotas;
        if (elPendientes) elPendientes.innerText = countPendientes;
        if (elProceso) elProceso.innerText = countProceso;
        if (elPagados) elPagados.innerText = countPagados;
        if (elRetrasados) elRetrasados.innerText = countRetrasados;

        // Filter notes
        let filtered = processedList.filter(note => {
            // Pill filter
            if (currentFilter === 'pendiente' && note._processed.statusKey !== 'pendiente') return false;
            if (currentFilter === 'en_proceso' && note._processed.statusKey !== 'en_proceso') return false;
            if (currentFilter === 'pagado' && note._processed.statusKey !== 'pagado') return false;
            if (currentFilter === 'retrasado' && note._processed.statusKey !== 'retrasado') return false;

            // Search query
            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                const idMatch = (note.id || '').toLowerCase().includes(q);
                const clientMatch = (note.client || '').toLowerCase().includes(q);
                const companyMatch = (note.company || '').toLowerCase().includes(q);
                if (!idMatch && !clientMatch && !companyMatch) return false;
            }

            return true;
        });

        // Sort notes
        filtered.sort((a, b) => {
            if (currentSort === 'oldest') {
                return (new Date(a.date || 0)) - (new Date(b.date || 0));
            } else if (currentSort === 'amount_desc') {
                return parseFloat(b.total || 0) - parseFloat(a.total || 0);
            } else if (currentSort === 'amount_asc') {
                return parseFloat(a.total || 0) - parseFloat(b.total || 0);
            } else if (currentSort === 'client') {
                return (a.client || '').localeCompare(b.client || '');
            } else {
                // Default: recent first
                return (new Date(b.date || 0)) - (new Date(a.date || 0));
            }
        });

        // If no matching filtered results
        if (filtered.length === 0) {
            notesGrid.style.display = 'none';
            emptyState.style.display = 'block';
            emptyStateTitle.innerText = 'No se encontraron notas';
            emptyStateDesc.innerText = 'No hay notas de pago que coincidan con los filtros o el término de búsqueda.';
            emptyStateBtn.style.display = 'inline-flex';
            emptyStateBtn.innerHTML = '<i class="ph-bold ph-arrow-counter-clockwise"></i> Limpiar Filtros';
            emptyStateBtn.onclick = () => {
                currentFilter = 'all';
                searchQuery = '';
                if (searchInput) searchInput.value = '';
                filterPills.forEach(p => p.classList.remove('active'));
                const firstPill = document.querySelector('.pn-pill[data-filter="all"]');
                if (firstPill) firstPill.classList.add('active');
                renderNotes();
            };
            return;
        }

        // Render each note card
        filtered.forEach(note => {
            const p = note._processed;
            const displayDate = note.date ? note.date.split(' ')[0] : '-';
            const clientName = note.client || 'Cliente sin nombre';
            const companyName = note.company || 'Sin empresa registrada';
            const initials = getInitials(clientName);
            const avatarBg = getAvatarGradient(clientName);

            // Progress percentage
            let progressPercent = 0;
            if (p.isPagado) {
                progressPercent = 100;
            } else if (p.computedTotal > 0) {
                progressPercent = Math.min(100, Math.round((p.totalAbonos / p.computedTotal) * 100));
            }

            const card = document.createElement('div');
            card.className = 'pn-card';
            card.innerHTML = `
                <!-- Card Header -->
                <div class="pn-card-header">
                    <div class="pn-card-id">
                        <span>${note.id}</span>
                        <button type="button" class="btn-copy-id" data-copy="${note.id}" title="Copiar ID">
                            <i class="ph-bold ph-copy"></i>
                        </button>
                    </div>
                    <span class="pn-card-status-badge ${p.statusClass}">
                        <i class="ph-fill ph-circle" style="font-size: 0.55rem;"></i> ${p.noteStatusText}
                    </span>
                </div>

                <!-- Chips Row -->
                <div class="pn-chips-row">
                    <span class="pn-chip" title="${note.last_viewed_at ? 'Última vez: ' + new Date(note.last_viewed_at).toLocaleString('es-PE') : 'Aún no visto'}">
                        <i class="ph-bold ph-eye"></i> ${note.view_count || 0} vista${note.view_count !== 1 ? 's' : ''}
                    </span>
                    ${note.access_pin ? '<span class="pn-chip" style="color: #6366f1; border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.08);"><i class="ph-bold ph-lock-simple"></i> PIN</span>' : ''}
                    ${note.voucher_url ? '<span class="pn-chip" style="color: #10b981; border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.08);"><i class="ph-bold ph-receipt"></i> Voucher</span>' : ''}
                    ${(note.cronograma && note.cronograma.length > 0) ? `<span class="pn-chip" style="color: #8b5cf6; border-color: rgba(139, 92, 246, 0.3); background: rgba(139, 92, 246, 0.08);"><i class="ph-bold ph-calendar-star"></i> ${note.cronograma.length} cuotas</span>` : ''}
                </div>

                <!-- Client Block -->
                <div class="pn-client-block">
                    <div class="pn-client-avatar" style="background: ${avatarBg};">
                        ${initials}
                    </div>
                    <div class="pn-client-info">
                        <div class="pn-client-name" title="${clientName}">${clientName}</div>
                        <div class="pn-client-sub" title="${companyName}">${companyName}</div>
                    </div>
                </div>

                <!-- Financial Summary & Progress -->
                <div class="pn-finances-box">
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; font-weight: 700; color: var(--text-muted);">
                        <span>PROGRESO DE PAGO</span>
                        <span style="color: ${p.isPagado ? '#10b981' : 'var(--color-title)'};">${progressPercent}%</span>
                    </div>
                    <div class="pn-progress-bar-wrap">
                        <div class="pn-progress-bar-fill" style="width: ${progressPercent}%; ${p.isPagado ? 'background: #10b981;' : (progressPercent > 0 ? 'background: var(--primary-color);' : 'background: transparent;')}"></div>
                    </div>

                    <div class="pn-financial-row" style="margin-top: 0.25rem;">
                        <div>
                            <div class="pn-fin-item-lbl">Monto Total</div>
                            <div class="pn-fin-item-val" title="S/ ${parseFloat(note.total || p.computedTotal).toFixed(2)}">S/ ${parseFloat(note.total || p.computedTotal).toFixed(2)}</div>
                        </div>
                        <div style="text-align: right;">
                            <div class="pn-fin-item-lbl">Fecha</div>
                            <div class="pn-fin-item-val" title="${displayDate}">${displayDate}</div>
                        </div>
                    </div>

                    <div style="padding-top: 0.45rem; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: baseline;">
                        <span class="pn-fin-item-lbl" style="color: ${p.isPagado ? '#10b981' : '#ef4444'};">Saldo Pendiente</span>
                        <span class="pn-balance-val ${p.isPagado ? 'pn-balance-zero' : 'pn-balance-pending'}">S/ ${parseFloat(p.balance).toFixed(2)}</span>
                    </div>
                </div>

                <!-- Card Actions -->
                <div class="pn-card-actions">
                    <a href="index.php?module=admin&action=payment_note_webview&id=${note.id}" class="btn-card-view">
                        <i class="ph-bold ph-note-pencil"></i> Ver / Editar
                    </a>
                    <button type="button" class="btn-card-icon-action btn-share" data-id="${note.id}" data-index="${note._index}" title="Compartir Nota">
                        <i class="ph-bold ph-share-network"></i>
                    </button>
                    <a href="${note.public_token ? 'np/' + note.public_token : 'index.php?module=admin&action=payment_note_webview&token=' + (note.public_token || '') + '&view=public'}" target="_blank" class="btn-card-icon-action" title="Ver Vista Pública">
                        <i class="ph-bold ph-globe"></i>
                    </a>
                    <button type="button" class="btn-card-icon-action btn-card-delete btn-delete" data-id="${note.id}" data-index="${note._index}" title="Eliminar Nota">
                        <i class="ph-bold ph-trash"></i>
                    </button>
                </div>
            `;
            notesGrid.appendChild(card);
        });

        // Event Listeners: Copy ID
        notesGrid.querySelectorAll('.btn-copy-id').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const text = btn.getAttribute('data-copy');
                navigator.clipboard.writeText(text).then(() => {
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="ph-bold ph-check" style="color: #10b981;"></i>';
                    setTimeout(() => { btn.innerHTML = orig; }, 1600);
                });
            });
        });

        // Event Listeners: Delete
        notesGrid.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                noteToDeleteId = btn.getAttribute('data-id');
                document.getElementById('delete-note-id').innerText = noteToDeleteId;
                modalDeleteNote.classList.add('active');
            });
        });

        // Event Listeners: Share
        notesGrid.querySelectorAll('.btn-share').forEach(btn => {
            btn.addEventListener('click', async () => {
                const noteId = btn.getAttribute('data-id');
                const note = notes.find(n => n.id === noteId);
                if (!note) return;

                activeNoteForShare = note;
                const baseUrl = window.location.origin + window.location.pathname;
                
                shareLinkInput.value = 'Generando enlace...';
                modalShareNote.classList.add('active');
                
                // Reset tabs to Link
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.style.color = 'var(--text-muted)';
                    b.style.borderBottom = 'none';
                    b.classList.remove('active');
                });
                const firstTab = document.querySelector('.tab-btn[data-target="tab-link"]');
                if (firstTab) {
                    firstTab.style.color = 'var(--primary-color)';
                    firstTab.style.borderBottom = '2px solid var(--primary-color)';
                    firstTab.classList.add('active');
                }
                
                document.querySelectorAll('.share-tab-content').forEach(c => c.style.display = 'none');
                const linkContent = document.getElementById('tab-link');
                if (linkContent) linkContent.style.display = 'block';

                try {
                    const basePath = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/+$/, '');
                    const publicUrl = note.public_token ? `${basePath}/np/${note.public_token}` : (baseUrl + '?module=admin&action=payment_note_webview&token=' + (note.public_token || '') + '&view=public');
                    shareLinkInput.value = publicUrl;
                    activeNoteUrlForShare = publicUrl;

                    const clientName = note.client || 'Cliente';
                    let clientPhone = '';
                    let clientEmail = '';
                    if (clientName !== 'Cliente' && availableClients) {
                        const clientObj = availableClients.find(c => c.name === clientName);
                        if (clientObj) {
                            clientPhone = clientObj.whatsapp || '';
                            clientEmail = clientObj.email || '';
                            if (clientPhone.startsWith('+')) clientPhone = clientPhone.substring(1);
                        }
                    }
                    
                    document.getElementById('share-wa-phone').value = clientPhone;
                    document.getElementById('share-wa-msg').value = `Hola ${clientName},\n\nTe compartimos el enlace para visualizar tu nota de pago y los métodos disponibles:\n\n${publicUrl}\n\nQuedamos atentos a cualquier consulta.`;

                    document.getElementById('share-email-to').value = clientEmail;
                    document.getElementById('share-email-subject').value = `Nota de Pago - ${clientName}`;
                    document.getElementById('share-email-msg').value = `Hola ${clientName},\n\nAdjuntamos el enlace para visualizar tu nota de pago. Podrás revisar el detalle y los métodos de pago disponibles:\n${publicUrl}\n\nSaludos cordiales.`;
                } catch(e) {
                    shareLinkInput.value = 'Error de red';
                }
            });
        });
    }

    // Search and Filters Event Listeners
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim();
            renderNotes();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', (e) => {
            currentSort = e.target.value;
            renderNotes();
        });
    }

    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentFilter = pill.getAttribute('data-filter') || 'all';
            renderNotes();
        });
    });

    // Handle Delete Note
    btnConfirmDelete.addEventListener('click', async () => {
        if (!noteToDeleteId) return;
        try {
            const res = await fetch('modules/admin/ajax_delete_payment_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(noteToDeleteId)}`
            });
            const data = await res.json();
            if (data.success) {
                notes = notes.filter(n => n.id !== noteToDeleteId);
                renderNotes();
                Swal.fire({ icon: 'success', title: 'Eliminado', text: 'La nota de pago ha sido eliminada.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al eliminar la nota.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)' });
            }
        } catch(e) {
            console.error(e);
        }
        noteToDeleteId = null;
        modalDeleteNote.classList.remove('active');
    });

    // Handle Copy Link
    btnCopyLink.addEventListener('click', () => {
        shareLinkInput.select();
        navigator.clipboard.writeText(shareLinkInput.value);
        
        const originalText = btnCopyLink.innerHTML;
        btnCopyLink.innerHTML = '<i class="ph-bold ph-check"></i> ¡Copiado!';
        btnCopyLink.style.backgroundColor = '#10b981';
        btnCopyLink.style.borderColor = '#10b981';
        
        setTimeout(() => {
            btnCopyLink.innerHTML = originalText;
            btnCopyLink.style.backgroundColor = '';
            btnCopyLink.style.borderColor = '';
        }, 2000);
    });

    // Share Modal Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.style.color = 'var(--text-muted)';
                b.style.borderBottom = 'none';
                b.classList.remove('active');
            });
            btn.style.color = 'var(--primary-color)';
            btn.style.borderBottom = '2px solid var(--primary-color)';
            btn.classList.add('active');

            document.querySelectorAll('.share-tab-content').forEach(c => c.style.display = 'none');
            const target = document.getElementById(btn.getAttribute('data-target'));
            if (target) target.style.display = 'block';
        });
    });

    // Send WhatsApp
    document.getElementById('btn-send-wa').addEventListener('click', async () => {
        const phone = document.getElementById('share-wa-phone').value.trim();
        const msg = document.getElementById('share-wa-msg').value.trim();
        if (!phone || !msg) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Ingresa el número y el mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            return;
        }

        const btn = document.getElementById('btn-send-wa');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Enviando...';
        btn.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_send_note_whatsapp.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ phone, message: msg, note_id: activeNoteForShare.id })
            });
            
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Enviado', text: 'Mensaje de WhatsApp enviado correctamente.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo enviar el mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            }
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red: ' + e.message, confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });

    // Send Email
    document.getElementById('btn-send-email').addEventListener('click', async () => {
        const to = document.getElementById('share-email-to').value.trim();
        const subject = document.getElementById('share-email-subject').value.trim();
        const msg = document.getElementById('share-email-msg').value.trim();
        if (!to || !subject || !msg) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Completa el correo, asunto y mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            return;
        }

        const btn = document.getElementById('btn-send-email');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Enviando...';
        btn.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_send_note_email.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ to, subject, message: msg, note_id: activeNoteForShare.id, url: activeNoteUrlForShare })
            });
            
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Enviado', text: 'Correo enviado correctamente.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo enviar el correo.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            }
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error: ' + e.message, confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });

    // Payment Methods Management
    const pmModal = document.getElementById('modal-payment-methods');
    const pmList = document.getElementById('pm-list');

    document.getElementById('btn-open-payment-methods').addEventListener('click', () => {
        pmModal.classList.add('active');
        loadPaymentMethods();
    });

    async function loadPaymentMethods() {
        pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);"><i class="ph-bold ph-spinner ph-spin"></i> Cargando formas de pago...</div>';
        try {
            const res = await fetch('modules/admin/ajax_payment_methods.php');
            const data = await res.json();
            if (data.success) {
                renderPMList(data.methods || []);
            } else {
                pmList.innerHTML = `<div style="padding: 2rem; text-align: center; color: var(--danger-color);"><i class="ph-bold ph-warning-circle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>${data.error || 'Error desconocido del servidor'}</div>`;
            }
        } catch(e) {
            pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--danger-color);"><i class="ph-bold ph-warning-circle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>Error de conexión</div>';
            console.error('Fetch error in loadPaymentMethods:', e);
        }
    }

    function renderPMList(methods) {
        if (methods.length === 0) {
            pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);"><i class="ph-bold ph-credit-card" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>No hay métodos de pago configurados</div>';
            return;
        }
        pmList.innerHTML = methods.map(m => `
            <div class="pm-item" data-id="${m.id}" style="display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); transition: background 0.15s;">
                <div style="width: 42px; height: 42px; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                    ${m.image_url 
                        ? `<img src="${m.image_url}" alt="${m.label}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">` 
                        : `<i class="ph-bold ph-bank" style="font-size: 1.25rem; color: var(--primary-color);"></i>`}
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.875rem; color: var(--color-title);">${m.label}</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace; margin-top: 0.1rem;">${m.code}</div>
                </div>
                <div style="display: flex; gap: 0.4rem; flex-shrink: 0;">
                    <button class="btn-card-icon-action btn-pm-edit" data-id="${m.id}" data-label="${m.label}" data-code="${m.code}" data-image="${m.image_url || ''}" title="Editar">
                        <i class="ph-bold ph-pencil-simple"></i>
                    </button>
                    <button class="btn-card-icon-action btn-card-delete btn-pm-delete" data-id="${m.id}" data-label="${m.label}" title="Eliminar">
                        <i class="ph-bold ph-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Edit handlers
        pmList.querySelectorAll('.btn-pm-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const item = btn.closest('.pm-item');
                item.innerHTML = `
                    <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <input type="text" class="form-control pm-edit-label" value="${btn.dataset.label}" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.4rem 0.6rem;" placeholder="Nombre">
                        <input type="text" class="form-control pm-edit-code" value="${btn.dataset.code}" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.4rem 0.6rem;" placeholder="Código/Cuenta">
                        <input type="text" class="form-control pm-edit-image" value="${btn.dataset.image}" style="font-size: 0.8125rem; border-radius: 8px; padding: 0.4rem 0.6rem; grid-column: 1/-1;" placeholder="URL de imagen (opcional)">
                    </div>
                    <div style="display: flex; gap: 0.4rem; flex-shrink: 0; align-self: center;">
                        <button class="btn-pn-action btn-pn-new pm-save-edit" data-id="${id}" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;"><i class="ph-bold ph-check"></i></button>
                        <button class="btn-pn-action btn-pn-methods pm-cancel-edit" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;"><i class="ph-bold ph-x"></i></button>
                    </div>
                `;
                item.querySelector('.pm-save-edit').addEventListener('click', async () => {
                    const label = item.querySelector('.pm-edit-label').value.trim();
                    const code = item.querySelector('.pm-edit-code').value.trim();
                    const image_url = item.querySelector('.pm-edit-image').value.trim();
                    if (!label || !code) return;
                    await fetch('modules/admin/ajax_payment_methods.php', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({action:'update', id, label, code, image_url})
                    });
                    loadPaymentMethods();
                });
                item.querySelector('.pm-cancel-edit').addEventListener('click', () => loadPaymentMethods());
            });
        });

        // Delete handlers
        pmList.querySelectorAll('.btn-pm-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: '¿Eliminar método?',
                    text: `¿Deseas eliminar "${btn.dataset.label}"?`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    background: 'var(--bg-surface)',
                    color: 'var(--color-text)'
                });
                if (!result.isConfirmed) return;
                await fetch('modules/admin/ajax_payment_methods.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({action:'delete', id: btn.dataset.id})
                });
                loadPaymentMethods();
            });
        });
    }

    // Add new payment method
    document.getElementById('btn-pm-add').addEventListener('click', async () => {
        const label = document.getElementById('pm-new-label').value.trim();
        const code = document.getElementById('pm-new-code').value.trim();
        const image_url = document.getElementById('pm-new-image').value.trim();
        
        if (!label || !code) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Nombre y código/cuenta son obligatorios.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)' });
            return;
        }

        await fetch('modules/admin/ajax_payment_methods.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'add', label, code, image_url})
        });
        
        document.getElementById('pm-new-label').value = '';
        document.getElementById('pm-new-code').value = '';
        document.getElementById('pm-new-image').value = '';
        loadPaymentMethods();
    });

    // Initialize
    loadNotes();
});
</script>

<?php require_once 'includes/footer.php'; ?>
