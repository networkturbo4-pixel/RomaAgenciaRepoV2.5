<?php
// modules/suppliers/index.php
require_once 'includes/header.php';

// Auto-migration check: Ensure tables exist
try {
    $db->query("SELECT 1 FROM suppliers LIMIT 1");
} catch (Exception $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact_name VARCHAR(255) NULL,
        category VARCHAR(100) NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        tax_id VARCHAR(50) NULL,
        address VARCHAR(255) NULL,
        bank_info TEXT NULL,
        notes TEXT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        public_token VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_suppliers_status (status),
        INDEX idx_suppliers_token (public_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS supplier_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        payment_date DATE NOT NULL,
        period_month VARCHAR(7) NOT NULL,
        concept VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency ENUM('PEN', 'USD') DEFAULT 'PEN',
        payment_method VARCHAR(100) NULL,
        reference_number VARCHAR(100) NULL,
        status ENUM('paid', 'pending', 'under_review', 'cancelled') DEFAULT 'paid',
        voucher_url VARCHAR(255) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sp_supplier_id (supplier_id),
        INDEX idx_sp_payment_date (payment_date),
        INDEX idx_sp_period_month (period_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS supplier_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        period_month VARCHAR(7) NOT NULL,
        service_title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        amount DECIMAL(10,2) DEFAULT 0.00,
        currency ENUM('PEN', 'USD') DEFAULT 'PEN',
        service_date DATE NULL,
        status ENUM('in_progress', 'delivered', 'approved', 'cancelled') DEFAULT 'delivered',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ss_supplier_id (supplier_id),
        INDEX idx_ss_period_month (period_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$categories = ['Hosting & Servidores', 'Audiovisual & Edición', 'Software & SaaS', 'Imprenta & Merchandising', 'Publicidad & Pauta', 'Diseño & Creatividad', 'Desarrollo Web', 'Logística & Transporte', 'Servicios Generales'];
?>

<style>
/* ========================================================================== */
/* Suppliers Module Modern UI Styles                                          */
/* ========================================================================== */
.sup-page-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg, 16px);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    flex-wrap: wrap;
    gap: 1.25rem;
}
.sup-header-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.sup-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
    flex-shrink: 0;
}
.sup-header-title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--color-title, #0f172a);
    letter-spacing: -0.3px;
}
.sup-header-subtitle {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Timeframe Bar */
.sup-filter-bar {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.sup-pills-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    max-width: 100%;
    padding-bottom: 2px;
}
.sup-pill-btn {
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.sup-pill-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 6%, transparent);
}
.sup-pill-btn.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color) 35%, transparent);
}

/* KPI Grid */
.sup-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.sup-kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem 1.4rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sup-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.05);
}
.sup-kpi-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    margin-bottom: 0.4rem;
}
.sup-kpi-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--color-title);
    line-height: 1.2;
}
.sup-kpi-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.sup-kpi-icon {
    position: absolute;
    right: 1.25rem;
    top: 1.25rem;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.kpi-paid .sup-kpi-icon { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.kpi-pending .sup-kpi-icon { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.kpi-top .sup-kpi-icon { background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); }
.kpi-active .sup-kpi-icon { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }

/* Tabs Bar */
.sup-tabs-bar {
    display: flex;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
    gap: 0.5rem;
    overflow-x: auto;
}
.sup-tab-btn {
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-muted);
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.sup-tab-btn:hover {
    color: var(--primary-color);
}
.sup-tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}
.sup-tab-content {
    display: none;
    animation: supFadeIn 0.25s ease;
}
.sup-tab-content.active {
    display: block;
}
@keyframes supFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ========================================================================== */
/* REDESIGNED SUPPLIER CARD (Premium SaaS UI)                                 */
/* ========================================================================== */
.sup-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.sup-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.35rem;
    box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.15rem;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.sup-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color));
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

/* Card Header */
.sup-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.sup-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    overflow: hidden;
}
.sup-avatar-badge {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 20%, transparent), color-mix(in srgb, var(--primary-color) 8%, transparent));
    color: var(--primary-color);
    font-size: 1.25rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid color-mix(in srgb, var(--primary-color) 30%, transparent);
    box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color) 15%, transparent);
}
.sup-card-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sup-tags-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 0.3rem;
    flex-wrap: wrap;
}
.sup-card-cat {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    background: var(--bg-color, #f1f5f9);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
}
.sup-badge-status {
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-transform: uppercase;
}
.badge-active {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.badge-inactive {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

/* Card Header Actions (Edit / Delete) */
.sup-header-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.sup-icon-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sup-icon-btn:hover {
    background: var(--bg-color);
    border-color: var(--border-color);
    color: var(--text-main);
}
.sup-icon-btn.danger:hover {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Card Body Details */
.sup-card-details {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    padding: 0.65rem 0;
    border-top: 1px dashed var(--border-color);
    border-bottom: 1px dashed var(--border-color);
}
.sup-detail-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sup-detail-row i {
    font-size: 1rem;
    color: var(--primary-color);
    flex-shrink: 0;
}
.sup-wa-link {
    color: var(--text-main);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: color 0.2s;
}
.sup-wa-link:hover {
    color: #22c55e;
}

/* Financial Strip */
.sup-card-finance-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color);
    padding: 0.85rem 1rem;
    border-radius: 12px;
}
.sup-fin-item-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 700;
    letter-spacing: 0.5px;
}
.sup-fin-item-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--color-title);
    margin-top: 2px;
    line-height: 1.2;
}

/* Card Footer Actions */
.sup-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 0.25rem;
}
.sup-actions-left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
}
.sup-actions-right {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Lightbox Image Viewer Overlay */
#custom-image-lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(10px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 1.5rem;
}
#custom-image-lightbox.active {
    display: flex;
    animation: supFadeIn 0.2s ease;
}
.lightbox-controls {
    position: absolute;
    top: 1.25rem;
    right: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 1000000;
}
.lightbox-btn {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.lightbox-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.08);
}
.lightbox-img-wrapper {
    max-width: 90vw;
    max-height: 82vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.lightbox-img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.7);
    transition: transform 0.2s ease;
}

/* Drag and Drop Zone */
.sup-dropzone {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    background: var(--bg-color, #f8fafc);
    cursor: pointer;
    transition: all 0.2s ease;
}
.sup-dropzone:hover, .sup-dropzone.dragover {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 4%, var(--bg-color));
}
.sup-dropzone i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}
</style>

<!-- Main Page Header -->
<div class="sup-page-header">
    <div class="sup-header-left">
        <div class="sup-icon-box">
            <i class="ph ph-buildings"></i>
        </div>
        <div>
            <h1 class="sup-header-title">Gestión de Proveedores</h1>
            <p class="sup-header-subtitle">Control de proveedores, analítica temporal de desembolsos, servicios mensuales y vouchers.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <button class="btn btn-outline" id="btn-quick-service" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.1rem; border-radius: 8px;">
            <i class="ph ph-briefcase"></i> Registrar Servicio
        </button>
        <button class="btn btn-outline" id="btn-quick-payment" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.1rem; border-radius: 8px;">
            <i class="ph ph-receipt"></i> Registrar Pago
        </button>
        <button class="btn btn-primary" id="btn-new-supplier" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.25rem; border-radius: 8px;">
            <i class="ph ph-plus-circle"></i> Nuevo Proveedor
        </button>
    </div>
</div>

<!-- Timeframe Filter & Search Bar -->
<div class="sup-filter-bar">
    <div class="sup-pills-group" id="timeframe-pills">
        <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-right: 0.3rem;">PERIODO:</span>
        <button class="sup-pill-btn active" data-period="month"><i class="ph ph-calendar-check"></i> Mes Actual</button>
        <button class="sup-pill-btn" data-period="3m"><i class="ph ph-chart-bar"></i> 3 Meses</button>
        <button class="sup-pill-btn" data-period="6m"><i class="ph ph-chart-line-up"></i> 6 Meses</button>
        <button class="sup-pill-btn" data-period="12m"><i class="ph ph-calendar"></i> 12 Meses (Año)</button>
        <button class="sup-pill-btn" data-period="all"><i class="ph ph-infinity"></i> Todo el Historial</button>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <label for="filter-month-picker" style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin: 0;">Mes:</label>
            <input type="month" id="filter-month-picker" class="form-control" value="<?php echo date('Y-m'); ?>" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; border-radius: 8px; width: 140px;">
        </div>
        <div style="position: relative; min-width: 200px;">
            <input type="text" id="sup-global-search" class="form-control" placeholder="Buscar proveedor, RUC, servicio..." style="padding-left: 2rem; font-size: 0.82rem; border-radius: 8px;">
            <i class="ph ph-magnifying-glass" style="position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
        </div>
    </div>
</div>

<!-- Dynamic KPI Grid -->
<div class="sup-kpi-grid">
    <div class="sup-kpi-card kpi-paid">
        <div class="sup-kpi-label">Total Pagado en Periodo</div>
        <div class="sup-kpi-value" id="kpi-total-paid-pen">S/ 0.00</div>
        <div class="sup-kpi-sub" id="kpi-total-paid-usd"><i class="ph ph-currency-dollar"></i> $ 0.00 USD</div>
        <div class="sup-kpi-icon"><i class="ph ph-check-circle"></i></div>
    </div>

    <div class="sup-kpi-card kpi-pending">
        <div class="sup-kpi-label">Pendiente por Pagar</div>
        <div class="sup-kpi-value" id="kpi-total-pending-pen" style="color: #f59e0b;">S/ 0.00</div>
        <div class="sup-kpi-sub" id="kpi-total-pending-usd"><i class="ph ph-clock"></i> $ 0.00 USD</div>
        <div class="sup-kpi-icon"><i class="ph ph-hourglass"></i></div>
    </div>

    <div class="sup-kpi-card kpi-top">
        <div class="sup-kpi-label">Proveedor Principal</div>
        <div class="sup-kpi-value" id="kpi-top-supplier-name" style="font-size: 1.15rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">-</div>
        <div class="sup-kpi-sub" id="kpi-top-supplier-amount"><i class="ph ph-chart-line"></i> Sin desembolsos</div>
        <div class="sup-kpi-icon"><i class="ph ph-trophy"></i></div>
    </div>

    <div class="sup-kpi-card kpi-active">
        <div class="sup-kpi-label">Proveedores Activos</div>
        <div class="sup-kpi-value" id="kpi-active-count">0</div>
        <div class="sup-kpi-sub" id="kpi-services-count"><i class="ph ph-briefcase"></i> 0 servicios en periodo</div>
        <div class="sup-kpi-icon"><i class="ph ph-users-three"></i></div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="sup-tabs-bar">
    <button class="sup-tab-btn active" data-tab="tab-directory">
        <i class="ph ph-address-book"></i> Directorio de Proveedores (<span id="count-suppliers-tab">0</span>)
    </button>
    <button class="sup-tab-btn" data-tab="tab-analytics">
        <i class="ph ph-chart-donut"></i> Analítica de Desembolsos
    </button>
    <button class="sup-tab-btn" data-tab="tab-payments">
        <i class="ph ph-receipt"></i> Historial de Pagos & Vouchers (<span id="count-payments-tab">0</span>)
    </button>
</div>

<!-- Tab 1: Directory -->
<div class="sup-tab-content active" id="tab-directory">
    <div id="suppliers-cards-container" class="sup-cards-grid">
        <!-- Rendered via JS -->
    </div>
    <div id="sup-empty-state" style="display: none; text-align: center; padding: 3.5rem 1rem; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: 16px;">
        <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--text-muted); font-size: 2.2rem;">
            <i class="ph ph-buildings"></i>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.5rem;">No se encontraron proveedores</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; max-width: 450px; margin: 0 auto 1.25rem;">Comienza registrando tu primer proveedor para gestionar pagos, comprobantes y contratos de servicios.</p>
        <button class="btn btn-primary" onclick="openNewSupplierModal()">
            <i class="ph ph-plus"></i> Registrar Primer Proveedor
        </button>
    </div>
</div>

<!-- Tab 2: Analytics Breakdown -->
<div class="sup-tab-content" id="tab-analytics">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--color-title);">Desembolsos por Proveedor</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.8rem;" id="analytics-period-title">Periodo: Mes Actual</p>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);" id="analytics-summary-badge">
                Mostrando datos ordenados por mayor desembolso.
            </div>
        </div>

        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px;">
                        <th style="padding: 0.75rem 1rem;">Proveedor</th>
                        <th style="padding: 0.75rem 1rem;">Categoría</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Total Pagado (S/)</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Total Pagado ($)</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">N° Pagos</th>
                        <th style="padding: 0.75rem 1rem;">Último Pago</th>
                        <th style="padding: 0.75rem 1rem; min-width: 140px;">% del Desembolso</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody id="analytics-table-body">
                    <!-- Rendered via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab 3: Payments & Vouchers History -->
<div class="sup-tab-content" id="tab-payments">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--color-title);">Historial de Pagos & Vouchers</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.8rem;">Registro detallado de transacciones con visualización inmediata de comprobantes.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <select id="filter-payment-status" class="form-control" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; border-radius: 8px;">
                    <option value="">Todos los Estados</option>
                    <option value="paid">Pagados</option>
                    <option value="pending">Pendientes</option>
                    <option value="under_review">En Revisión</option>
                </select>
                <button class="btn btn-primary btn-sm" id="btn-table-new-payment" style="border-radius: 8px;">
                    <i class="ph ph-plus"></i> Registrar Pago
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px;">
                        <th style="padding: 0.75rem 1rem;">Fecha</th>
                        <th style="padding: 0.75rem 1rem;">Proveedor</th>
                        <th style="padding: 0.75rem 1rem;">Concepto / Detalle</th>
                        <th style="padding: 0.75rem 1rem;">Periodo</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Monto</th>
                        <th style="padding: 0.75rem 1rem;">Método</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Estado</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Voucher</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="payments-table-body">
                    <!-- Rendered via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================== -->
<!-- MODALS                                                                     -->
<!-- ========================================================================== -->

<!-- Modal: Create / Edit Supplier -->
<div class="modal-overlay" id="modal-supplier">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-supplier-title"><i class="ph ph-buildings"></i> Nuevo Proveedor</h2>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <form id="form-supplier">
            <input type="hidden" name="id" id="sup-id" value="0">
            <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Razón Social / Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="sup-name" class="form-control" required placeholder="Ej: Amazon Web Services / Juan Pérez">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Categoría</label>
                        <select name="category" id="sup-category" class="form-control">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Persona de Contacto</label>
                        <input type="text" name="contact_name" id="sup-contact" class="form-control" placeholder="Ej: Carlos Gómez">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">RUC / DNI / Tax ID</label>
                        <input type="text" name="tax_id" id="sup-tax-id" class="form-control" placeholder="Ej: 20601234567">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Teléfono / WhatsApp</label>
                        <input type="text" name="phone" id="sup-phone" class="form-control" placeholder="Ej: +51 987654321">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Correo Electrónico</label>
                        <input type="email" name="email" id="sup-email" class="form-control" placeholder="contacto@proveedor.com">
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Datos Bancarios / Formas de Pago</label>
                    <textarea name="bank_info" id="sup-bank-info" class="form-control" rows="2" placeholder="Ej: BCP Soles Cta: 191-12345678-0-12 | CCI: 002191... | Yape: 987654321"></textarea>
                    <small style="font-size: 0.72rem; color: var(--text-muted);">Estos datos aparecerán en la vista pública para verificación y depósitos.</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Dirección / Ubicación</label>
                        <input type="text" name="address" id="sup-address" class="form-control" placeholder="Av. Principal 123, Lima">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Estado</label>
                        <select name="status" id="sup-status" class="form-control">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Notas Internas</label>
                    <textarea name="notes" id="sup-notes" class="form-control" rows="2" placeholder="Observaciones o acuerdos comerciales"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-light btn-close-modal btn-pill">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-pill" id="btn-save-supplier">Guardar Proveedor</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Record Payment -->
<div class="modal-overlay" id="modal-payment">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-payment-title"><i class="ph ph-receipt"></i> Registrar Pago a Proveedor</h2>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <form id="form-payment" enctype="multipart/form-data">
            <input type="hidden" name="id" id="pay-id" value="0">
            <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="pay-supplier-id" class="form-control" required>
                        <option value="">Selecciona un proveedor...</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Monto <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="pay-amount" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Moneda</label>
                        <select name="currency" id="pay-currency" class="form-control">
                            <option value="PEN">Soles (S/)</option>
                            <option value="USD">Dólares ($)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Concepto / Motivo <span class="text-danger">*</span></label>
                    <input type="text" name="concept" id="pay-concept" class="form-control" required placeholder="Ej: Pago mensual de hosting, Edición de 10 videos...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Fecha de Pago <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" id="pay-date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Mes del Servicio</label>
                        <input type="month" name="period_month" id="pay-period-month" class="form-control" value="<?php echo date('Y-m'); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Método de Pago</label>
                        <select name="payment_method" id="pay-method" class="form-control">
                            <option value="Transferencia BCP">Transferencia BCP</option>
                            <option value="Transferencia BBVA">Transferencia BBVA</option>
                            <option value="Transferencia Interbank">Transferencia Interbank</option>
                            <option value="Yape / Plin">Yape / Plin</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta de Crédito / Débito">Tarjeta de Crédito / Débito</option>
                            <option value="PayPal / Stripe">PayPal / Stripe</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Estado del Pago</label>
                        <select name="status" id="pay-status" class="form-control">
                            <option value="paid">Pagado</option>
                            <option value="pending">Pendiente</option>
                            <option value="under_review">En Revisión</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">N° Operación / Referencia</label>
                    <input type="text" name="reference_number" id="pay-reference" class="form-control" placeholder="Ej: Op. #84920492">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Comprobante / Voucher (JPG, PNG, WEBP, PDF)</label>
                    <div class="sup-dropzone" onclick="document.getElementById('pay-voucher-file').click();">
                        <i class="ph ph-file-arrow-up"></i>
                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--color-title);">Haz clic o arrastra tu voucher aquí</div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Formatos admitidos: JPG, PNG, WEBP, PDF (Máx 15MB)</div>
                        <div id="pay-file-selected-name" style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: 700; color: var(--primary-color);"></div>
                    </div>
                    <input type="file" name="voucher" id="pay-voucher-file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" style="display: none;" onchange="handlePaymentFileSelect(this)">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Notas Adicionales</label>
                    <textarea name="notes" id="pay-notes" class="form-control" rows="2" placeholder="Detalles o notas sobre el pago..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-light btn-close-modal btn-pill">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-pill" id="btn-save-payment">Guardar Pago</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Record Service -->
<div class="modal-overlay" id="modal-service">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-service-title"><i class="ph ph-briefcase"></i> Registrar Servicio Ofrecido</h2>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <form id="form-service">
            <input type="hidden" name="id" id="svc-id" value="0">
            <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="svc-supplier-id" class="form-control" required>
                        <option value="">Selecciona un proveedor...</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Mes / Periodo <span class="text-danger">*</span></label>
                        <input type="month" name="period_month" id="svc-period-month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Fecha de Realización</label>
                        <input type="date" name="service_date" id="svc-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Título del Servicio / Entregable <span class="text-danger">*</span></label>
                    <input type="text" name="service_title" id="svc-title" class="form-control" required placeholder="Ej: Edición de 10 Reels TikTok, Mantenimiento Servidor">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Costo Acordado</label>
                        <input type="number" step="0.01" min="0" name="amount" id="svc-amount" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 0.82rem; font-weight: 600;">Moneda</label>
                        <select name="currency" id="svc-currency" class="form-control">
                            <option value="PEN">Soles (S/)</option>
                            <option value="USD">Dólares ($)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Estado del Servicio</label>
                    <select name="status" id="svc-status" class="form-control">
                        <option value="delivered">Entregado / Realizado</option>
                        <option value="approved">Aprobado</option>
                        <option value="in_progress">En Progreso</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600;">Descripción Detallada</label>
                    <textarea name="description" id="svc-desc" class="form-control" rows="3" placeholder="Detalles de entregables, enlaces o especificaciones del servicio..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-light btn-close-modal btn-pill">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-pill" id="btn-save-service">Guardar Servicio</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Supplier 360° Profile & Details -->
<div class="modal-overlay" id="modal-supplier-detail">
    <div class="modal-content" style="max-width: 850px;">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="sup-avatar-badge" id="det-avatar">P</div>
                <div>
                    <h2 class="modal-title" id="det-name" style="font-size: 1.25rem;">Proveedor</h2>
                    <span id="det-cat" class="sup-card-cat" style="margin-top: 0;">General</span>
                </div>
            </div>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <!-- Supplier Summary Header Bar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; background: var(--bg-color, #f8fafc); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Desembolsado</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-title); margin-top: 2px;" id="det-total-paid">S/ 0.00</div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Pagos Registrados</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-title); margin-top: 2px;" id="det-payments-count">0</div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Servicios Ofrecidos</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-title); margin-top: 2px;" id="det-services-count">0</div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Enlace Público</div>
                    <div style="margin-top: 4px; display: flex; gap: 0.35rem;">
                        <button class="btn btn-outline btn-sm" id="btn-det-copy-link" style="padding: 0.2rem 0.5rem; font-size: 0.72rem; border-radius: 6px;"><i class="ph ph-copy"></i> Copiar</button>
                        <a href="#" target="_blank" id="btn-det-open-link" class="btn btn-primary btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.72rem; border-radius: 6px;"><i class="ph ph-arrow-square-out"></i> Abrir</a>
                    </div>
                </div>
            </div>

            <!-- Details Tabs -->
            <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.25rem;">
                <button class="sup-det-tab-btn active" data-target="det-tab-services" style="background:none; border:none; padding: 0.5rem 0.25rem; font-weight: 700; font-size: 0.85rem; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); cursor:pointer;">Servicios por Mes</button>
                <button class="sup-det-tab-btn" data-target="det-tab-payments" style="background:none; border:none; padding: 0.5rem 0.25rem; font-weight: 700; font-size: 0.85rem; color: var(--text-muted); cursor:pointer;">Historial de Pagos & Vouchers</button>
                <button class="sup-det-tab-btn" data-target="det-tab-info" style="background:none; border:none; padding: 0.5rem 0.25rem; font-weight: 700; font-size: 0.85rem; color: var(--text-muted); cursor:pointer;">Datos de Contacto & Banco</button>
            </div>

            <!-- Det Tab: Services -->
            <div id="det-tab-services" class="det-tab-pane">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--color-title);">Servicios y Entregables</span>
                    <button class="btn btn-primary btn-sm" id="btn-det-add-service" style="border-radius: 6px;"><i class="ph ph-plus"></i> Agregar Servicio</button>
                </div>
                <div id="det-services-list" style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 350px; overflow-y: auto;">
                    <!-- Rendered via JS -->
                </div>
            </div>

            <!-- Det Tab: Payments -->
            <div id="det-tab-payments" class="det-tab-pane" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--color-title);">Historial de Pagos y Comprobantes</span>
                    <button class="btn btn-primary btn-sm" id="btn-det-add-payment" style="border-radius: 6px;"><i class="ph ph-plus"></i> Registrar Pago</button>
                </div>
                <div id="det-payments-list" style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 350px; overflow-y: auto;">
                    <!-- Rendered via JS -->
                </div>
            </div>

            <!-- Det Tab: Contact & Bank Info -->
            <div id="det-tab-info" class="det-tab-pane" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.82rem;">
                    <div>
                        <div style="font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.25rem;">Contacto</div>
                        <div id="det-info-contact" style="color: var(--color-title); font-weight: 600;">-</div>
                        <div id="det-info-email" style="color: var(--text-muted); margin-top: 2px;">-</div>
                        <div id="det-info-phone" style="color: var(--text-muted); margin-top: 2px;">-</div>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.25rem;">Fiscal & Ubicación</div>
                        <div id="det-info-tax" style="color: var(--color-title); font-weight: 600;">RUC: -</div>
                        <div id="det-info-address" style="color: var(--text-muted); margin-top: 2px;">-</div>
                    </div>
                </div>

                <div style="margin-top: 1.25rem; background: var(--bg-color, #f8fafc); border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem;">
                    <div style="font-weight: 700; color: var(--primary-color); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.4rem;"><i class="ph ph-credit-card"></i> Cuentas Bancarias para Depósito</div>
                    <div id="det-info-bank" style="font-size: 0.82rem; white-space: pre-line; color: var(--color-title); font-family: monospace;">No registrado</div>
                </div>

                <div style="margin-top: 1rem;">
                    <div style="font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.25rem;">Notas Adicionales</div>
                    <div id="det-info-notes" style="font-size: 0.82rem; color: var(--color-title); white-space: pre-line;">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-light btn-close-modal btn-pill">Cerrar</button>
        </div>
    </div>
</div>

<!-- Custom Lightbox Image Viewer Overlay -->
<div id="custom-image-lightbox">
    <div class="lightbox-controls">
        <button type="button" class="lightbox-btn" onclick="zoomLightbox(0.2)" title="Acercar"><i class="ph ph-magnifying-glass-plus"></i></button>
        <button type="button" class="lightbox-btn" onclick="zoomLightbox(-0.2)" title="Alejar"><i class="ph ph-magnifying-glass-minus"></i></button>
        <button type="button" class="lightbox-btn" onclick="rotateLightbox()" title="Rotar 90°"><i class="ph ph-arrow-clockwise"></i></button>
        <a id="lightbox-download-btn" href="#" download class="lightbox-btn" title="Descargar Voucher"><i class="ph ph-download-simple"></i></a>
        <button type="button" class="lightbox-btn" onclick="closeImageLightbox()" title="Cerrar (Esc)"><i class="ph ph-x"></i></button>
    </div>
    <div class="lightbox-img-wrapper" id="lightbox-wrapper" onclick="if(event.target === this) closeImageLightbox();">
        <img id="lightbox-current-img" class="lightbox-img" src="" alt="Voucher de Pago">
    </div>
</div>

<!-- ========================================================================== -->
<!-- JS LOGIC                                                                   -->
<!-- ========================================================================== -->
<script>
let currentPeriod = 'month';
let selectedMonth = '<?php echo date('Y-m'); ?>';
let currentSearch = '';
let globalSuppliers = [];
let globalPayments = [];
let activeDetailSupplierId = 0;

let currentZoom = 1;
let currentRotation = 0;

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initTimeframePills();
    initSearch();
    loadAnalyticsData();
    initForms();
});

// Tab switching
function initTabs() {
    document.querySelectorAll('.sup-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sup-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.sup-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const targetId = btn.getAttribute('data-tab');
            const targetContent = document.getElementById(targetId);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // Detail Modal internal tabs
    document.querySelectorAll('.sup-det-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sup-det-tab-btn').forEach(b => {
                b.classList.remove('active');
                b.style.color = 'var(--text-muted)';
                b.style.borderBottom = 'none';
            });
            document.querySelectorAll('.det-tab-pane').forEach(p => p.style.display = 'none');
            
            btn.classList.add('active');
            btn.style.color = 'var(--primary-color)';
            btn.style.borderBottom = '2px solid var(--primary-color)';
            
            const target = document.getElementById(btn.getAttribute('data-target'));
            if (target) target.style.display = 'block';
        });
    });
}

// Timeframe Pills
function initTimeframePills() {
    document.querySelectorAll('.sup-pill-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sup-pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentPeriod = btn.getAttribute('data-period');
            loadAnalyticsData();
        });
    });

    const monthPicker = document.getElementById('filter-month-picker');
    if (monthPicker) {
        monthPicker.addEventListener('change', () => {
            selectedMonth = monthPicker.value;
            document.querySelectorAll('.sup-pill-btn').forEach(b => b.classList.remove('active'));
            const monthPill = document.querySelector('.sup-pill-btn[data-period="month"]');
            if (monthPill) monthPill.classList.add('active');
            currentPeriod = 'month';
            loadAnalyticsData();
        });
    }

    const payStatusFilter = document.getElementById('filter-payment-status');
    if (payStatusFilter) {
        payStatusFilter.addEventListener('change', () => {
            renderPaymentsTable();
        });
    }
}

// Search
function initSearch() {
    const searchInput = document.getElementById('sup-global-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            currentSearch = searchInput.value.toLowerCase().trim();
            renderSuppliersCards();
            renderAnalyticsTable();
            renderPaymentsTable();
        });
    }
}

// Load Analytics and Data
async function loadAnalyticsData() {
    try {
        const res = await fetch(`modules/suppliers/ajax_get_analytics.php?period_type=${currentPeriod}&month=${selectedMonth}`);
        const data = await res.json();

        if (data.success) {
            globalSuppliers = data.suppliers || [];
            globalPayments = data.payments || [];

            // Update KPIs
            document.getElementById('kpi-total-paid-pen').textContent = `S/ ${numberFormat(data.kpis.total_paid_pen)}`;
            document.getElementById('kpi-total-paid-usd').innerHTML = `<i class="ph ph-currency-dollar"></i> $ ${numberFormat(data.kpis.total_paid_usd)} USD`;
            
            document.getElementById('kpi-total-pending-pen').textContent = `S/ ${numberFormat(data.kpis.total_pending_pen)}`;
            document.getElementById('kpi-total-pending-usd').innerHTML = `<i class="ph ph-clock"></i> $ ${numberFormat(data.kpis.total_pending_usd)} USD`;

            if (data.kpis.top_supplier) {
                document.getElementById('kpi-top-supplier-name').textContent = data.kpis.top_supplier.name;
                const topAmount = data.kpis.top_supplier.paid_pen > 0 ? `S/ ${numberFormat(data.kpis.top_supplier.paid_pen)}` : `$ ${numberFormat(data.kpis.top_supplier.paid_usd)}`;
                document.getElementById('kpi-top-supplier-amount').innerHTML = `<i class="ph ph-trend-up"></i> ${topAmount} desembolsados`;
            } else {
                document.getElementById('kpi-top-supplier-name').textContent = 'Ninguno';
                document.getElementById('kpi-top-supplier-amount').innerHTML = `<i class="ph ph-minus"></i> Sin pagos registrados`;
            }

            document.getElementById('kpi-active-count').textContent = data.kpis.active_suppliers_count;
            document.getElementById('kpi-services-count').innerHTML = `<i class="ph ph-briefcase"></i> ${data.kpis.total_services_count} servicios en periodo`;

            document.getElementById('count-suppliers-tab').textContent = globalSuppliers.length;
            document.getElementById('count-payments-tab').textContent = globalPayments.length;
            document.getElementById('analytics-period-title').textContent = `Periodo: ${data.period_label}`;

            // Populate supplier select dropdowns in modals
            populateSupplierSelects();

            // Render Views
            renderSuppliersCards();
            renderAnalyticsTable();
            renderPaymentsTable();
        }
    } catch (e) {
        console.error("Error loading analytics:", e);
    }
}

// Populate selects
function populateSupplierSelects() {
    const paySelect = document.getElementById('pay-supplier-id');
    const svcSelect = document.getElementById('svc-supplier-id');

    let options = '<option value="">Selecciona un proveedor...</option>';
    globalSuppliers.forEach(s => {
        options += `<option value="${s.id}">${escapeHtml(s.name)} (${escapeHtml(s.category)})</option>`;
    });

    if (paySelect) paySelect.innerHTML = options;
    if (svcSelect) svcSelect.innerHTML = options;
}

// ==========================================================================
// RENDER SUPPLIER CARDS (REDESIGNED)
// ==========================================================================
function renderSuppliersCards() {
    const container = document.getElementById('suppliers-cards-container');
    const emptyState = document.getElementById('sup-empty-state');
    if (!container) return;

    let filtered = globalSuppliers.filter(s => {
        if (!currentSearch) return true;
        const text = (s.name + ' ' + (s.contact_name||'') + ' ' + (s.tax_id||'') + ' ' + (s.category||'')).toLowerCase();
        return text.includes(currentSearch);
    });

    if (filtered.length === 0) {
        container.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    const baseUrl = window.location.origin + window.location.pathname;

    let html = '';
    filtered.forEach(s => {
        const publicUrl = `${baseUrl}?module=suppliers&action=public&token=${s.public_token}`;
        const initial = s.name.charAt(0).toUpperCase();
        const isInactive = s.status === 'inactive';
        
        // Clean phone for whatsapp
        const cleanPhone = (s.phone || '').replace(/[^0-9]/g, '');

        html += `
        <div class="sup-card" style="${isInactive ? 'opacity: 0.75;' : ''}">
            <!-- Header Section -->
            <div class="sup-card-header">
                <div class="sup-card-header-left">
                    <div class="sup-avatar-badge">${initial}</div>
                    <div style="overflow: hidden;">
                        <h3 class="sup-card-name" title="${escapeHtml(s.name)}">${escapeHtml(s.name)}</h3>
                        <div class="sup-tags-row">
                            <span class="sup-card-cat">${escapeHtml(s.category)}</span>
                            <span class="sup-badge-status ${isInactive ? 'badge-inactive' : 'badge-active'}">
                                ${isInactive ? '● Inactivo' : '● Activo'}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="sup-header-actions">
                    <button class="sup-icon-btn" onclick="editSupplier(${s.id})" title="Editar Proveedor">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button class="sup-icon-btn danger" onclick="deleteSupplier(${s.id}, '${escapeHtml(s.name)}')" title="Eliminar Proveedor">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Body Details -->
            <div class="sup-card-details">
                ${s.contact_name ? `
                    <div class="sup-detail-row">
                        <i class="ph ph-user"></i> <span>Contacto: <strong>${escapeHtml(s.contact_name)}</strong></span>
                    </div>` : ''}

                ${s.tax_id ? `
                    <div class="sup-detail-row">
                        <i class="ph ph-identification-card"></i> <span>RUC / DNI: <strong>${escapeHtml(s.tax_id)}</strong></span>
                    </div>` : ''}

                ${s.phone ? `
                    <div class="sup-detail-row">
                        <i class="ph ph-whatsapp-logo" style="color: #22c55e;"></i> 
                        ${cleanPhone.length >= 7 ? `
                            <a href="https://wa.me/${cleanPhone}" target="_blank" class="sup-wa-link" title="Abrir chat en WhatsApp">
                                ${escapeHtml(s.phone)} <i class="ph ph-arrow-up-right" style="font-size:0.75rem; color:#22c55e;"></i>
                            </a>
                        ` : `<span>${escapeHtml(s.phone)}</span>`}
                    </div>` : ''}

                ${s.bank_info ? `
                    <div class="sup-detail-row" title="${escapeHtml(s.bank_info)}">
                        <i class="ph ph-bank"></i> 
                        <span style="font-family: monospace; font-size: 0.72rem; color: var(--text-muted); background: var(--bg-color); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color);">
                            ${escapeHtml(s.bank_info.substring(0, 32))}${s.bank_info.length > 32 ? '...' : ''}
                        </span>
                    </div>` : ''}
            </div>

            <!-- Financial Inset Strip -->
            <div class="sup-card-finance-box">
                <div>
                    <div class="sup-fin-item-label">Pagado en Periodo</div>
                    <div class="sup-fin-item-val" style="color: #10b981;">S/ ${numberFormat(s.paid_pen)}</div>
                    ${s.paid_usd > 0 ? `<div style="font-size: 0.72rem; font-weight: 700; color: #3b82f6; margin-top: 2px;">$ ${numberFormat(s.paid_usd)} USD</div>` : ''}
                </div>
                <div>
                    <div class="sup-fin-item-label">Actividad</div>
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--color-title); margin-top: 2px;">
                        ${s.payments_count} pagos • ${s.services_count} serv.
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                        ${s.last_payment_date ? 'Últ: ' + s.last_payment_date : 'Sin pagos en periodo'}
                    </div>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="sup-card-actions">
                <div class="sup-actions-left">
                    <button class="btn btn-outline btn-sm" onclick="openSupplierDetail(${s.id})" title="Ver ficha completa e historial" style="flex: 1; justify-content: center; border-radius: 8px;">
                        <i class="ph ph-eye"></i> Detalle
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="openPaymentForSupplier(${s.id})" title="Registrar pago a este proveedor" style="flex: 1; justify-content: center; border-radius: 8px;">
                        <i class="ph ph-receipt"></i> Pagar
                    </button>
                </div>

                <div class="sup-actions-right">
                    <button class="btn btn-outline btn-sm" onclick="openServiceForSupplier(${s.id})" title="Registrar nuevo servicio" style="padding: 0.4rem 0.55rem; border-radius: 8px;">
                        <i class="ph ph-briefcase"></i>
                    </button>
                    <a href="${publicUrl}" target="_blank" class="btn btn-outline btn-sm" title="Abrir portal público" style="padding: 0.4rem 0.55rem; border-radius: 8px;">
                        <i class="ph ph-arrow-square-out"></i>
                    </a>
                    <button class="btn btn-outline btn-sm" onclick="copyPublicLink('${publicUrl}')" title="Copiar enlace del portal público" style="padding: 0.4rem 0.55rem; border-radius: 8px;">
                        <i class="ph ph-link"></i>
                    </button>
                </div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

// Render Analytics Table (Tab 2)
function renderAnalyticsTable() {
    const tbody = document.getElementById('analytics-table-body');
    if (!tbody) return;

    let filtered = globalSuppliers.filter(s => {
        if (!currentSearch) return true;
        const text = (s.name + ' ' + (s.tax_id||'') + ' ' + (s.category||'')).toLowerCase();
        return text.includes(currentSearch);
    });

    let totalDisbursed = 0;
    filtered.forEach(s => {
        totalDisbursed += s.paid_pen + (s.paid_usd * 3.75);
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay registros en este periodo.</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach((s, idx) => {
        const equiv = s.paid_pen + (s.paid_usd * 3.75);
        const percent = totalDisbursed > 0 ? ((equiv / totalDisbursed) * 100).toFixed(1) : '0.0';

        html += `
        <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 0.85rem 1rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); width: 20px;">#${idx + 1}</span>
                    <div>
                        <strong style="color: var(--color-title);">${escapeHtml(s.name)}</strong>
                        ${s.tax_id ? `<div style="font-size: 0.72rem; color: var(--text-muted);">RUC: ${escapeHtml(s.tax_id)}</div>` : ''}
                    </div>
                </div>
            </td>
            <td style="padding: 0.85rem 1rem;"><span class="sup-card-cat">${escapeHtml(s.category)}</span></td>
            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: ${s.paid_pen > 0 ? '#10b981' : 'var(--text-muted)'};">
                S/ ${numberFormat(s.paid_pen)}
            </td>
            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: ${s.paid_usd > 0 ? '#3b82f6' : 'var(--text-muted)'};">
                $ ${numberFormat(s.paid_usd)}
            </td>
            <td style="padding: 0.85rem 1rem; text-align: center; font-weight: 600;">${s.payments_count}</td>
            <td style="padding: 0.85rem 1rem; font-size: 0.78rem; color: var(--text-muted);">
                ${s.last_payment_date ? `${s.last_payment_date} (${s.last_payment_currency === 'USD' ? '$' : 'S/'} ${numberFormat(s.last_payment_amount)})` : '-'}
            </td>
            <td style="padding: 0.85rem 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="flex: 1; height: 7px; background: var(--border-color); border-radius: 4px; overflow: hidden;">
                        <div style="width: ${percent}%; height: 100%; background: var(--primary-color); border-radius: 4px;"></div>
                    </div>
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--color-title); min-width: 35px;">${percent}%</span>
                </div>
            </td>
            <td style="padding: 0.85rem 1rem; text-align: center;">
                <button class="btn btn-outline btn-sm" onclick="openSupplierDetail(${s.id})" style="padding: 0.25rem 0.55rem; font-size: 0.75rem; border-radius: 6px;">
                    <i class="ph ph-eye"></i>
                </button>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

// Render Payments Table (Tab 3)
function renderPaymentsTable() {
    const tbody = document.getElementById('payments-table-body');
    const statusFilter = document.getElementById('filter-payment-status')?.value || '';
    if (!tbody) return;

    let filtered = globalPayments.filter(p => {
        if (statusFilter && p.status !== statusFilter) return false;
        if (!currentSearch) return true;
        const text = (p.supplier_name + ' ' + p.concept + ' ' + (p.reference_number||'') + ' ' + (p.payment_method||'')).toLowerCase();
        return text.includes(currentSearch);
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">No se encontraron pagos registrados con los filtros seleccionados.</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach(p => {
        let statusBadge = '<span class="sup-badge-status badge-active">Pagado</span>';
        if (p.status === 'pending') statusBadge = '<span class="sup-badge-status" style="background: rgba(245,158,11,0.12); color: #f59e0b;">Pendiente</span>';
        if (p.status === 'under_review') statusBadge = '<span class="sup-badge-status" style="background: rgba(59,130,246,0.12); color: #3b82f6;">En Revisión</span>';
        if (p.status === 'cancelled') statusBadge = '<span class="sup-badge-status badge-inactive">Cancelado</span>';

        const currSym = p.currency === 'USD' ? '$' : 'S/';
        const hasVoucher = p.voucher_url && p.voucher_url.trim() !== '';

        html += `
        <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600;">${p.payment_date}</td>
            <td style="padding: 0.85rem 1rem;">
                <strong style="color: var(--color-title);">${escapeHtml(p.supplier_name)}</strong>
                <div style="font-size: 0.72rem; color: var(--text-muted);">${escapeHtml(p.supplier_category || 'General')}</div>
            </td>
            <td style="padding: 0.85rem 1rem; max-width: 250px;">
                <div style="font-weight: 600; color: var(--color-title); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(p.concept)}</div>
                ${p.reference_number ? `<div style="font-size: 0.72rem; color: var(--text-muted);">Ref: ${escapeHtml(p.reference_number)}</div>` : ''}
            </td>
            <td style="padding: 0.85rem 1rem; font-size: 0.78rem; color: var(--text-muted);">${p.period_month}</td>
            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; font-size: 0.95rem; color: var(--color-title);">
                ${currSym} ${numberFormat(p.amount)}
            </td>
            <td style="padding: 0.85rem 1rem; font-size: 0.78rem; color: var(--text-muted);">${escapeHtml(p.payment_method || '-')}</td>
            <td style="padding: 0.85rem 1rem; text-align: center;">${statusBadge}</td>
            <td style="padding: 0.85rem 1rem; text-align: center;">
                ${hasVoucher ? `
                    <button class="btn btn-outline btn-sm" onclick="openVoucherLightbox('${escapeHtml(p.voucher_url)}')" style="padding: 0.3rem 0.6rem; border-radius: 6px; color: var(--primary-color);" title="Abrir Comprobante">
                        <i class="ph ph-image"></i> Ver
                    </button>
                ` : `<span style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-prohibit"></i> Sin voucher</span>`}
            </td>
            <td style="padding: 0.85rem 1rem; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.3rem;">
                    <button class="btn btn-outline btn-sm" onclick="editPayment(${p.id})" style="padding: 0.25rem 0.45rem; border-radius: 6px;" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deletePayment(${p.id})" style="padding: 0.25rem 0.45rem; border-radius: 6px; color: #ef4444;" title="Eliminar"><i class="ph ph-trash"></i></button>
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

// ==========================================================================
// FORMS & MODAL HANDLERS
// ==========================================================================
function initForms() {
    document.getElementById('btn-new-supplier')?.addEventListener('click', openNewSupplierModal);

    document.getElementById('btn-quick-payment')?.addEventListener('click', () => {
        openPaymentModal();
    });
    document.getElementById('btn-table-new-payment')?.addEventListener('click', () => {
        openPaymentModal();
    });
    document.getElementById('btn-quick-service')?.addEventListener('click', () => {
        openServiceModal();
    });

    document.getElementById('form-supplier')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.getElementById('btn-save-supplier');
        submitBtn.disabled = true;

        try {
            const res = await fetch('modules/suppliers/ajax_save_supplier.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-supplier');
                loadAnalyticsData();
            } else {
                alert(data.message || 'Error al guardar proveedor');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión');
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.getElementById('form-payment')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.getElementById('btn-save-payment');
        submitBtn.disabled = true;

        try {
            const res = await fetch('modules/suppliers/ajax_save_payment.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-payment');
                loadAnalyticsData();
                if (activeDetailSupplierId > 0) {
                    openSupplierDetail(activeDetailSupplierId);
                }
            } else {
                alert(data.message || 'Error al guardar pago');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión');
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.getElementById('form-service')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.getElementById('btn-save-service');
        submitBtn.disabled = true;

        try {
            const res = await fetch('modules/suppliers/ajax_save_service.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                closeModal('modal-service');
                loadAnalyticsData();
                if (activeDetailSupplierId > 0) {
                    openSupplierDetail(activeDetailSupplierId);
                }
            } else {
                alert(data.message || 'Error al guardar servicio');
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión');
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const overlay = btn.closest('.modal-overlay');
            if (overlay) overlay.classList.remove('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('active');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeImageLightbox();
            document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        }
    });
}

function openNewSupplierModal() {
    document.getElementById('form-supplier').reset();
    document.getElementById('sup-id').value = '0';
    document.getElementById('modal-supplier-title').innerHTML = '<i class="ph ph-buildings"></i> Nuevo Proveedor';
    openModal('modal-supplier');
}

function editSupplier(id) {
    const s = globalSuppliers.find(item => item.id == id);
    if (!s) return;

    document.getElementById('sup-id').value = s.id;
    document.getElementById('sup-name').value = s.name;
    document.getElementById('sup-category').value = s.category || 'Hosting & Servidores';
    document.getElementById('sup-contact').value = s.contact_name || '';
    document.getElementById('sup-tax-id').value = s.tax_id || '';
    document.getElementById('sup-phone').value = s.phone || '';
    document.getElementById('sup-email').value = s.email || '';
    document.getElementById('sup-bank-info').value = s.bank_info || '';
    document.getElementById('sup-address').value = s.address || '';
    document.getElementById('sup-status').value = s.status || 'active';
    document.getElementById('sup-notes').value = s.notes || '';

    document.getElementById('modal-supplier-title').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Proveedor';
    openModal('modal-supplier');
}

async function deleteSupplier(id, name) {
    if (!confirm(`¿Estás seguro de eliminar el proveedor "${name}"? Se eliminará todo su historial de pagos y servicios.`)) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const res = await fetch('modules/suppliers/ajax_delete_supplier.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadAnalyticsData();
        } else {
            alert(data.message || 'Error al eliminar proveedor');
        }
    } catch (e) {
        console.error(e);
        alert('Error de conexión');
    }
}

function openPaymentModal(supplierId = 0) {
    document.getElementById('form-payment').reset();
    document.getElementById('pay-id').value = '0';
    document.getElementById('pay-file-selected-name').textContent = '';
    document.getElementById('modal-payment-title').innerHTML = '<i class="ph ph-receipt"></i> Registrar Pago';
    
    if (supplierId > 0) {
        document.getElementById('pay-supplier-id').value = supplierId;
    }
    openModal('modal-payment');
}

function openPaymentForSupplier(supplierId) {
    openPaymentModal(supplierId);
}

function openServiceModal(supplierId = 0) {
    document.getElementById('form-service').reset();
    document.getElementById('svc-id').value = '0';
    document.getElementById('modal-service-title').innerHTML = '<i class="ph ph-briefcase"></i> Registrar Servicio';
    
    if (supplierId > 0) {
        document.getElementById('svc-supplier-id').value = supplierId;
    }
    openModal('modal-service');
}

function openServiceForSupplier(supplierId) {
    openServiceModal(supplierId);
}

function editPayment(paymentId) {
    const p = globalPayments.find(item => item.id == paymentId);
    if (!p) return;

    document.getElementById('pay-id').value = p.id;
    document.getElementById('pay-supplier-id').value = p.supplier_id;
    document.getElementById('pay-amount').value = p.amount;
    document.getElementById('pay-currency').value = p.currency || 'PEN';
    document.getElementById('pay-concept').value = p.concept;
    document.getElementById('pay-date').value = p.payment_date;
    document.getElementById('pay-period-month').value = p.period_month;
    document.getElementById('pay-method').value = p.payment_method || 'Transferencia BCP';
    document.getElementById('pay-status').value = p.status || 'paid';
    document.getElementById('pay-reference').value = p.reference_number || '';
    document.getElementById('pay-notes').value = p.notes || '';
    document.getElementById('pay-file-selected-name').textContent = p.voucher_url ? `Archivo actual: ${p.voucher_url}` : '';

    document.getElementById('modal-payment-title').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Pago';
    openModal('modal-payment');
}

async function deletePayment(paymentId) {
    if (!confirm('¿Estás seguro de eliminar este pago?')) return;

    const formData = new FormData();
    formData.append('id', paymentId);

    try {
        const res = await fetch('modules/suppliers/ajax_delete_payment.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadAnalyticsData();
            if (activeDetailSupplierId > 0) openSupplierDetail(activeDetailSupplierId);
        } else {
            alert(data.message || 'Error al eliminar pago');
        }
    } catch (e) {
        console.error(e);
    }
}

// Supplier Detail 360° Modal
async function openSupplierDetail(supplierId) {
    activeDetailSupplierId = supplierId;
    try {
        const res = await fetch(`modules/suppliers/ajax_get_supplier.php?id=${supplierId}`);
        const data = await res.json();

        if (!data.success) {
            alert('No se pudo cargar el proveedor');
            return;
        }

        const s = data.supplier;
        const payments = data.payments || [];
        const services = data.services || [];
        const totals = data.totals || {};

        document.getElementById('det-name').textContent = s.name;
        document.getElementById('det-cat').textContent = s.category || 'General';
        document.getElementById('det-avatar').textContent = s.name.charAt(0).toUpperCase();
        
        document.getElementById('det-total-paid').innerHTML = `S/ ${numberFormat(totals.total_paid_pen)} ${totals.total_paid_usd > 0 ? `<small style="font-size:0.75rem; color:var(--text-muted);">$ ${numberFormat(totals.total_paid_usd)}</small>` : ''}`;
        document.getElementById('det-payments-count').textContent = totals.payments_count;
        document.getElementById('det-services-count').textContent = totals.services_count;

        const baseUrl = window.location.origin + window.location.pathname;
        const publicUrl = `${baseUrl}?module=suppliers&action=public&token=${s.public_token}`;
        document.getElementById('btn-det-open-link').href = publicUrl;
        document.getElementById('btn-det-copy-link').onclick = () => copyPublicLink(publicUrl);

        // Populate Contact & Bank tab
        document.getElementById('det-info-contact').textContent = s.contact_name || 'No especificado';
        document.getElementById('det-info-email').textContent = s.email || 'Sin correo';
        document.getElementById('det-info-phone').textContent = s.phone || 'Sin teléfono';
        document.getElementById('det-info-tax').textContent = `RUC/DNI: ${s.tax_id || 'No registrado'}`;
        document.getElementById('det-info-address').textContent = s.address || 'Sin dirección';
        document.getElementById('det-info-bank').textContent = s.bank_info || 'No registrado';
        document.getElementById('det-info-notes').textContent = s.notes || 'Sin notas';

        // Add action triggers
        document.getElementById('btn-det-add-service').onclick = () => openServiceForSupplier(s.id);
        document.getElementById('btn-det-add-payment').onclick = () => openPaymentForSupplier(s.id);

        // Populate Services list
        const svcContainer = document.getElementById('det-services-list');
        if (services.length === 0) {
            svcContainer.innerHTML = `<div style="text-align:center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">No hay servicios registrados para este proveedor.</div>`;
        } else {
            let svcHtml = '';
            services.forEach(svc => {
                svcHtml += `
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 700; color: var(--color-title); font-size: 0.88rem;">${escapeHtml(svc.service_title)}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                            Periodo: <strong>${svc.period_month}</strong> ${svc.service_date ? `| Fecha: ${svc.service_date}` : ''}
                        </div>
                        ${svc.description ? `<div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">${escapeHtml(svc.description)}</div>` : ''}
                    </div>
                    <div style="text-align: right; flex-shrink: 0;">
                        <div style="font-weight: 800; color: var(--color-title);">${svc.currency === 'USD' ? '$' : 'S/'} ${numberFormat(svc.amount)}</div>
                        <button class="btn btn-outline btn-sm" onclick="deleteService(${svc.id})" style="padding: 0.2rem 0.4rem; font-size: 0.7rem; color: #ef4444; border-color: #fee2e2; margin-top: 4px;">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>`;
            });
            svcContainer.innerHTML = svcHtml;
        }

        // Populate Payments list
        const payContainer = document.getElementById('det-payments-list');
        if (payments.length === 0) {
            payContainer.innerHTML = `<div style="text-align:center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">No hay pagos registrados para este proveedor.</div>`;
        } else {
            let payHtml = '';
            payments.forEach(p => {
                const hasV = p.voucher_url && p.voucher_url.trim() !== '';
                payHtml += `
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 700; color: var(--color-title); font-size: 0.88rem;">${escapeHtml(p.concept)}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                            ${p.payment_date} | Periodo: <strong>${p.period_month}</strong> | Método: ${escapeHtml(p.payment_method || '-')}
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="font-weight: 800; font-size: 0.95rem; color: #10b981;">${p.currency === 'USD' ? '$' : 'S/'} ${numberFormat(p.amount)}</div>
                        ${hasV ? `
                            <button class="btn btn-outline btn-sm" onclick="openVoucherLightbox('${escapeHtml(p.voucher_url)}')" style="padding: 0.3rem 0.5rem; font-size: 0.75rem; color: var(--primary-color);">
                                <i class="ph ph-image"></i> Voucher
                            </button>
                        ` : ''}
                        <button class="btn btn-outline btn-sm" onclick="deletePayment(${p.id})" style="padding: 0.25rem 0.4rem; font-size: 0.7rem; color: #ef4444; border-color: #fee2e2;">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>`;
            });
            payContainer.innerHTML = payHtml;
        }

        openModal('modal-supplier-detail');
    } catch (e) {
        console.error(e);
        alert('Error al obtener datos');
    }
}

async function deleteService(serviceId) {
    if (!confirm('¿Eliminar este servicio?')) return;
    const formData = new FormData();
    formData.append('id', serviceId);

    try {
        const res = await fetch('modules/suppliers/ajax_delete_service.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadAnalyticsData();
            if (activeDetailSupplierId > 0) openSupplierDetail(activeDetailSupplierId);
        } else {
            alert(data.message || 'Error al eliminar');
        }
    } catch (e) {
        console.error(e);
    }
}

// ==========================================================================
// IMAGE LIGHTBOX VIEWER
// ==========================================================================
function openVoucherLightbox(url) {
    if (!url) return;
    const ext = url.split('.').pop().toLowerCase();
    
    if (ext === 'pdf') {
        window.open(url, '_blank');
        return;
    }

    currentZoom = 1;
    currentRotation = 0;
    const lightbox = document.getElementById('custom-image-lightbox');
    const img = document.getElementById('lightbox-current-img');
    const dlBtn = document.getElementById('lightbox-download-btn');

    if (img && lightbox) {
        img.src = url;
        img.style.transform = `scale(1) rotate(0deg)`;
        if (dlBtn) dlBtn.href = url;
        lightbox.classList.add('active');
    }
}

function closeImageLightbox() {
    const lightbox = document.getElementById('custom-image-lightbox');
    if (lightbox) lightbox.classList.remove('active');
}

function zoomLightbox(delta) {
    currentZoom = Math.max(0.4, Math.min(3.5, currentZoom + delta));
    updateLightboxTransform();
}

function rotateLightbox() {
    currentRotation = (currentRotation + 90) % 360;
    updateLightboxTransform();
}

function updateLightboxTransform() {
    const img = document.getElementById('lightbox-current-img');
    if (img) {
        img.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
    }
}

function handlePaymentFileSelect(input) {
    const label = document.getElementById('pay-file-selected-name');
    if (input.files && input.files[0]) {
        label.textContent = `Archivo seleccionado: ${input.files[0].name} (${(input.files[0].size / 1024 / 1024).toFixed(2)} MB)`;
    } else {
        label.textContent = '';
    }
}

// ==========================================================================
// UTILITIES
// ==========================================================================
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

function numberFormat(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function copyPublicLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Enlace del portal público copiado al portapapeles.');
    }).catch(() => {
        prompt('Copia el enlace manualmente:', url);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
