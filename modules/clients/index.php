<?php
// modules/clients/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all clients with brands and memberships
$stmt = $db->query("
    SELECT c.*, 
           GROUP_CONCAT(b.name SEPARATOR '||') as brands, 
           GROUP_CONCAT(b.logo SEPARATOR '||') as logos,
           GROUP_CONCAT(COALESCE(b.has_membership, 0) SEPARATOR '||') as memberships
    FROM clients c
    LEFT JOIN client_brands b ON c.id = b.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services for multi-select in brands
$stmtServices = $db->query("SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY name ASC");
$all_services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// Calculate KPI summary stats
$totalClients = count($clients);
$clientsWithMembership = 0;
$clientsWithDrive = 0;
$clientsWithPortal = 0;
foreach ($clients as $c) {
    if (!empty($c['memberships']) && strpos($c['memberships'], '1') !== false) {
        $clientsWithMembership++;
    }
    if (!empty($c['drive_folder_id'])) {
        $clientsWithDrive++;
    }
    if (!empty($c['portal_enabled'])) {
        $clientsWithPortal++;
    }
}
$totalBrands = (int)$db->query("SELECT COUNT(*) FROM client_brands")->fetchColumn();

require_once 'includes/header.php';
?>

<script>
    const SYSTEM_SERVICES = <?php echo json_encode($all_services); ?>;
</script>

<style>
/* ==========================================================================
   MODERN SAAS CLIENTS MODULE - DESIGN SYSTEM (ui-guidelines compliant)
   ========================================================================== */

:root {
    --client-radius-sm: 8px;
    --client-radius-md: 12px;
    --client-radius-lg: 16px;
    --client-radius-xl: 20px;
    --client-avatar-size: 40px;
    --client-transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.clients-page-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-size: 13px;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
    padding-bottom: 2.5rem;
}

/* --- 1. Page Header --- */
.clients-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.clients-title-area {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.clients-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.clients-page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.clients-page-title i {
    color: var(--primary-color);
    font-size: 1.6rem;
}

.clients-count-badge {
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

.clients-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

.client-btn-create {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.1rem;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--client-radius-md);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    transition: var(--client-transition);
}

.client-btn-create:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
}

/* --- 2. KPI Summary Bar --- */
.clients-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
}

.client-kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-md);
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: var(--client-transition);
}

.client-kpi-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    transform: translateY(-1px);
}

.kpi-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: var(--client-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.kpi-icon-wrap.blue {
    background: color-mix(in srgb, #3b82f6 15%, transparent);
    color: #3b82f6;
}

.kpi-icon-wrap.gold {
    background: color-mix(in srgb, #f59e0b 15%, transparent);
    color: #f59e0b;
}

.kpi-icon-wrap.purple {
    background: color-mix(in srgb, #8b5cf6 15%, transparent);
    color: #8b5cf6;
}

.kpi-icon-wrap.teal {
    background: color-mix(in srgb, #10b981 15%, transparent);
    color: #10b981;
}

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
.clients-action-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    padding: 0.65rem 0.85rem;
    border-radius: var(--client-radius-md);
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

/* Modern Search Box */
.clients-search-box {
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

.clients-search-box input {
    width: 100%;
    height: 38px;
    padding: 0 4rem 0 2.25rem;
    background: var(--bg-body, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-sm);
    font-size: 13px;
    color: var(--text-main);
    transition: var(--client-transition);
}

.clients-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.clients-search-box input::placeholder {
    color: var(--text-muted);
    font-size: 12px;
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
    transition: var(--client-transition);
}

.search-clear-btn:hover {
    background: var(--border-color);
    color: var(--text-main);
}

.search-kbd-chip {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 2px 5px;
    pointer-events: none;
    user-select: none;
}

/* Filter Pills */
.clients-filter-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.clients-filter-group::-webkit-scrollbar {
    display: none;
}

.client-filter-pill {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: var(--client-transition);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.client-filter-pill:hover {
    color: var(--text-main);
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.client-filter-pill.active {
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

.clients-view-toggle {
    display: flex;
    background: var(--bg-body, #1e1e1e);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-sm);
    padding: 2px;
    gap: 2px;
}

.clients-view-toggle button {
    border: none;
    background: transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--client-transition);
}

.clients-view-toggle button:hover {
    color: var(--text-main);
}

.clients-view-toggle button.active {
    background: var(--bg-surface);
    color: var(--primary-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

/* --- 4. List View (Modern Rows) --- */
.clients-list-container {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.client-row-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-md);
    padding: 0.85rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    cursor: pointer;
    transition: var(--client-transition);
}

.client-row-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 3%, var(--bg-surface));
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.client-saas-avatar {
    width: var(--client-avatar-size);
    height: var(--client-avatar-size);
    border-radius: var(--client-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    color: #ffffff;
    flex-shrink: 0;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.client-row-name-col {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 180px;
    flex: 1.2;
}

.client-main-name {
    font-weight: 600;
    font-size: 13.5px;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.membership-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 10px;
    font-weight: 600;
    padding: 1px 7px;
    border-radius: var(--radius-full);
    background: color-mix(in srgb, #f59e0b 15%, transparent);
    color: #f59e0b;
    border: 1px solid color-mix(in srgb, #f59e0b 30%, transparent);
}

.client-meta-pills {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.client-dni-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 11px;
    color: var(--text-muted);
}

.client-drive-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 11px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    padding: 1px 6px;
    border-radius: 4px;
    background: color-mix(in srgb, #3b82f6 10%, transparent);
    transition: var(--client-transition);
}

.client-drive-link:hover {
    background: color-mix(in srgb, #3b82f6 20%, transparent);
}

/* Contact Column */
.client-row-contact-col {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex: 1.2;
    flex-wrap: wrap;
}

.contact-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 11.5px;
    padding: 0.35rem 0.65rem;
    border-radius: var(--radius-full);
    text-decoration: none;
    transition: var(--client-transition);
    white-space: nowrap;
}

.contact-pill.whatsapp-pill {
    background: color-mix(in srgb, #25d366 12%, transparent);
    color: #25d366;
    border: 1px solid color-mix(in srgb, #25d366 25%, transparent);
    font-weight: 500;
}

.contact-pill.whatsapp-pill:hover {
    background: color-mix(in srgb, #25d366 22%, transparent);
    transform: scale(1.02);
}

.contact-pill.email-pill {
    background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.contact-pill.email-pill:hover {
    background: color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.contact-empty-tag {
    font-size: 11px;
    color: var(--text-muted);
    opacity: 0.6;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Brands Column */
.client-row-brands-col {
    flex: 1.4;
    display: flex;
    align-items: center;
}

.client-brands-container {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.client-brand-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 3px 8px;
    background: var(--bg-body, rgba(255,255,255,0.05));
    border: 1px solid var(--border-color);
    border-radius: var(--radius-full);
    font-size: 11px;
    color: var(--text-main);
    font-weight: 500;
    max-width: 150px;
}

.client-brand-chip.with-membership {
    border-color: color-mix(in srgb, #f59e0b 40%, var(--border-color));
}

.brand-chip-img {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    object-fit: cover;
}

.brand-chip-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.brand-star-badge {
    color: #f59e0b;
    font-size: 10px;
}

.client-brand-more {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    background: var(--bg-body, rgba(255,255,255,0.05));
    border-radius: var(--radius-full);
    color: var(--text-muted);
}

.no-brands-label {
    font-size: 11px;
    color: var(--text-muted);
    opacity: 0.6;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Action Buttons */
.client-row-actions-col {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.action-btn-saas {
    width: 32px;
    height: 32px;
    border-radius: var(--client-radius-sm);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.95rem;
    text-decoration: none;
    transition: var(--client-transition);
}

.action-btn-saas:hover {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--bg-surface));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
}

.action-btn-saas.delete-btn:hover {
    background: color-mix(in srgb, var(--danger-color) 15%, var(--bg-surface));
    color: var(--danger-color);
    border-color: color-mix(in srgb, var(--danger-color) 35%, var(--border-color));
}

/* --- 5. Grid View (Modern Cards) --- */
.clients-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 1rem;
}

.client-grid-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-lg);
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    cursor: pointer;
    transition: var(--client-transition);
}

.client-grid-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.client-grid-card.has-membership {
    border-top: 3px solid #f59e0b;
}

.grid-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.grid-card-title-group {
    flex: 1;
    min-width: 0;
}

.grid-card-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-main);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.grid-card-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.2rem;
    flex-wrap: wrap;
}

.grid-card-contacts {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding-top: 0.6rem;
    border-top: 1px solid var(--border-color);
}

.grid-card-brands-section {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    flex: 1;
}

.grid-brands-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
}

.grid-brands-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.grid-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    margin-top: auto;
}

.grid-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* --- 6. Empty State --- */
.clients-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3.5rem 1.5rem;
    text-align: center;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: var(--client-radius-lg);
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
    font-size: 1.75rem;
}

.clients-empty-state h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0;
    color: var(--text-main);
}

.clients-empty-state p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin: 0;
    max-width: 380px;
}

.empty-actions {
    margin-top: 0.5rem;
}

/* --- 7. Modal Redesign (SaaS Glassmorphism & Two-Column layout) --- */
.saas-modal-dialog {
    max-width: 960px !important;
    width: 92% !important;
    border-radius: var(--client-radius-xl) !important;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    box-shadow: 0 20px 45px rgba(0,0,0,0.25) !important;
    overflow: hidden;
}

.saas-modal-header {
    padding: 1.15rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
}

.saas-modal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.saas-modal-title i {
    color: var(--primary-color);
}

.saas-modal-body {
    padding: 1.5rem;
    max-height: 75vh;
    overflow-y: auto;
}

.modal-split-grid {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 1.75rem;
}

.modal-section-box {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.modal-section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.field-icon-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.field-icon-wrap i {
    position: absolute;
    left: 0.85rem;
    color: var(--text-muted);
    font-size: 1rem;
    pointer-events: none;
}

.field-icon-wrap .form-control {
    padding-left: 2.35rem;
    height: 38px;
    font-size: 13px;
    border-radius: var(--client-radius-sm);
}

.field-help-tip {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

/* Brand Creator Card inside modal */
.brand-create-box {
    background: var(--bg-body, rgba(255,255,255,0.03));
    border: 1px dashed var(--border-color);
    border-radius: var(--client-radius-md);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.brand-create-inputs {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    flex-wrap: wrap;
}

.brand-name-input-wrap {
    flex: 1.2;
    min-width: 140px;
}

.brand-logo-input-wrap {
    flex: 1;
    min-width: 140px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.logo-preview-box {
    width: 38px;
    height: 38px;
    border-radius: var(--client-radius-sm);
    border: 1px solid var(--border-color);
    display: none;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.brand-add-btn {
    align-self: flex-end;
    height: 38px;
    padding: 0 1rem;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: var(--client-radius-sm);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: var(--client-transition);
}

.brand-add-btn:hover {
    background: var(--primary-hover);
}

/* Dynamic Brand Editor Cards */
.brands-list-dynamic {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.brand-editor-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-md);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    transition: var(--client-transition);
}

.brand-editor-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
}

.brand-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand-identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.brand-preview-avatar {
    width: 34px;
    height: 34px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

.brand-preview-avatar.default {
    background: var(--bg-body, #2a2a2a);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.brand-name-wrap {
    display: flex;
    flex-direction: column;
}

.brand-card-title {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-main);
}

.brand-card-subtitle {
    font-size: 11px;
    color: var(--text-muted);
}

.brand-delete-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1rem;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--client-transition);
}

.brand-delete-btn:hover {
    background: color-mix(in srgb, var(--danger-color) 15%, transparent);
    color: var(--danger-color);
}

.brand-card-controls {
    display: grid;
    grid-template-columns: auto 1fr 1fr;
    gap: 0.75rem;
    align-items: flex-start;
    padding-top: 0.65rem;
    border-top: 1px dashed var(--border-color);
}

/* Modern iOS Style Toggle Switch */
.brand-membership-switch-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding-top: 0.4rem;
}

.modern-toggle-switch {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
}

.modern-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--border-color);
    transition: 0.25s;
    border-radius: 20px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.25s;
    border-radius: 50%;
}

.modern-toggle-switch input:checked + .toggle-slider {
    background-color: #10b981;
}

.modern-toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(16px);
}

.toggle-label {
    font-size: 11.5px;
    font-weight: 500;
    color: var(--text-main);
    cursor: pointer;
    user-select: none;
}

.field-micro-label {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--text-muted);
    display: block;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Multiselect Styling */
.custom-multiselect {
    position: relative;
}

.custom-multiselect-header {
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-sm);
    padding: 4px 8px;
    background: var(--bg-body, var(--bg-surface));
    cursor: pointer;
    min-height: 34px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
}

.badges-wrapper {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 3px;
    flex: 1;
}

.service-selected-pill {
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10.5px;
    font-weight: 600;
}

.service-none-text {
    color: var(--text-muted);
    font-size: 11.5px;
}

.caret-icon {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.custom-multiselect-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-sm);
    max-height: 160px;
    overflow-y: auto;
    z-index: 50;
    box-shadow: var(--shadow-lg);
}

.custom-multiselect-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    margin: 0;
    font-size: 12px;
    color: var(--text-main);
    transition: background 0.15s;
}

.custom-multiselect-option:hover {
    background: color-mix(in srgb, var(--primary-color) 8%, var(--bg-surface));
}

.input-with-icon-mini {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-icon-mini i {
    position: absolute;
    left: 0.65rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.form-control-sm {
    width: 100%;
    height: 34px;
    padding: 0 0.5rem 0 1.9rem;
    font-size: 11.5px;
    border: 1px solid var(--border-color);
    border-radius: var(--client-radius-sm);
    background: var(--bg-body, var(--bg-surface));
    color: var(--text-main);
}

.form-control-sm:focus {
    outline: none;
    border-color: var(--primary-color);
}

.brands-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.5rem 0;
    color: var(--text-muted);
    font-size: 12px;
}

.brands-empty-state i {
    font-size: 1.25rem;
}

.shake-error {
    animation: shake 0.3s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
    border-color: var(--danger-color) !important;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
    40%, 60% { transform: translate3d(3px, 0, 0); }
}

/* --- 8. Responsive Rules (Mobile & Tablet) --- */
@media (max-width: 1024px) {
    .clients-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .modal-split-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}

@media (max-width: 768px) {
    .clients-top-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .client-btn-create {
        justify-content: center;
        width: 100%;
        padding: 0.65rem 1rem;
    }

    .clients-kpi-grid {
        display: flex;
        overflow-x: auto;
        padding-bottom: 0.35rem;
        scrollbar-width: none;
    }

    .clients-kpi-grid::-webkit-scrollbar {
        display: none;
    }

    .client-kpi-card {
        min-width: 170px;
        flex: 1 0 auto;
    }

    .clients-action-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .toolbar-left-group {
        width: 100%;
        min-width: 100%;
    }

    .clients-search-box {
        max-width: 100%;
        width: 100%;
    }

    .toolbar-right-group {
        justify-content: space-between;
        width: 100%;
    }

    /* List Rows mobile transformation */
    .client-row-card {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 1rem;
    }

    .client-row-avatar-col {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .client-row-card {
        display: grid;
        grid-template-columns: auto 1fr;
        grid-template-areas: 
            "avatar name"
            "contact contact"
            "brands brands"
            "actions actions";
        align-items: center;
    }

    .client-row-avatar-col { grid-area: avatar; }
    .client-row-name-col { grid-area: name; }
    .client-row-contact-col { grid-area: contact; padding-top: 0.25rem; }
    .client-row-brands-col { grid-area: brands; padding-top: 0.25rem; }
    .client-row-actions-col { 
        grid-area: actions; 
        justify-content: flex-end; 
        border-top: 1px solid var(--border-color); 
        padding-top: 0.5rem; 
        margin-top: 0.25rem;
    }

    /* Modal as Bottom Sheet in Mobile */
    .saas-modal-dialog {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 20px 20px 0 0 !important;
        max-height: 90vh;
        margin: auto 0 0 0 !important;
    }

    .brand-card-controls {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }
}
</style>

<div class="clients-page-wrapper">
    <!-- 1. Header Area -->
    <div class="clients-top-bar">
        <div class="clients-title-area">
            <div class="clients-title-row">
                <h1 class="clients-page-title">
                    <i class="ph-duotone ph-buildings"></i> Clientes
                </h1>
                <span class="clients-count-badge" id="totalClientsBadge"><?php echo $totalClients; ?> total</span>
            </div>
            <p class="clients-subtitle">Gestión centralizada de clientes, marcas comerciales, membresías y servicios.</p>
        </div>

        <button type="button" class="client-btn-create" onclick="ClientModule.openModal()">
            <i class="ph ph-plus-circle"></i>
            <span>Nuevo Cliente</span>
        </button>
    </div>

    <!-- 2. KPI Summary Bar -->
    <div class="clients-kpi-grid">
        <div class="client-kpi-card">
            <div class="kpi-icon-wrap blue">
                <i class="ph ph-users-three"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-val"><?php echo $totalClients; ?></span>
                <span class="kpi-label">Total Clientes</span>
            </div>
        </div>

        <div class="client-kpi-card">
            <div class="kpi-icon-wrap gold">
                <i class="ph-fill ph-star"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-val"><?php echo $clientsWithMembership; ?></span>
                <span class="kpi-label">Con Membresía</span>
            </div>
        </div>

        <div class="client-kpi-card">
            <div class="kpi-icon-wrap purple">
                <i class="ph ph-briefcase"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-val"><?php echo $totalBrands; ?></span>
                <span class="kpi-label">Marcas Vinculadas</span>
            </div>
        </div>

        <div class="client-kpi-card">
            <div class="kpi-icon-wrap teal">
                <i class="ph ph-app-window"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-val"><?php echo $clientsWithPortal; ?></span>
                <span class="kpi-label">Portal Activo</span>
            </div>
        </div>
    </div>

    <!-- 3. Smart Toolbar: AJAX Search, Filter Pills & View Toggle -->
    <div class="clients-action-toolbar">
        <div class="toolbar-left-group">
            <!-- Search Box with AJAX & Debounce -->
            <div class="clients-search-box">
                <i class="ph ph-magnifying-glass search-icon-left"></i>
                <input type="text" id="clientSearch" placeholder="Buscar por cliente, DNI, WhatsApp, correo o marca..." oninput="ClientModule.onSearchInput(this.value)" autocomplete="off">
                <div class="search-actions-right">
                    <i class="ph ph-spinner search-spinner-icon" id="searchSpinner" style="display: none;"></i>
                    <button type="button" class="search-clear-btn" id="searchClearBtn" onclick="ClientModule.clearSearch()" style="display: none;" title="Limpiar búsqueda">
                        <i class="ph ph-x"></i>
                    </button>
                    <span class="search-kbd-chip"><kbd>⌘K</kbd></span>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="clients-filter-group">
                <button type="button" class="client-filter-pill active" data-filter="all" onclick="ClientModule.setFilter('all', this)">
                    <i class="ph ph-squares-four"></i> Todos
                </button>
                <button type="button" class="client-filter-pill" data-filter="membership" onclick="ClientModule.setFilter('membership', this)">
                    <i class="ph-fill ph-star" style="color: #f59e0b;"></i> Membresía
                </button>
                <button type="button" class="client-filter-pill" data-filter="portal" onclick="ClientModule.setFilter('portal', this)">
                    <i class="ph ph-app-window" style="color: #10b981;"></i> Portal Activo
                </button>
                <button type="button" class="client-filter-pill" data-filter="has_brands" onclick="ClientModule.setFilter('has_brands', this)">
                    <i class="ph ph-briefcase"></i> Con Marcas
                </button>
                <button type="button" class="client-filter-pill" data-filter="no_brands" onclick="ClientModule.setFilter('no_brands', this)">
                    <i class="ph ph-empty"></i> Sin Marcas
                </button>
            </div>
        </div>

        <div class="toolbar-right-group">
            <span class="search-result-pill" id="searchResultCount"><?php echo $totalClients; ?> clientes</span>
            
            <div class="clients-view-toggle">
                <button type="button" data-view="list" class="active" onclick="ClientModule.setView('list', this)" title="Vista Lista Compacta">
                    <i class="ph ph-list-dashes"></i>
                </button>
                <button type="button" data-view="grid" onclick="ClientModule.setView('grid', this)" title="Vista Cuadrícula">
                    <i class="ph ph-squares-four"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. List View (Rendered by default / PHP + dynamic AJAX) -->
    <div id="clientsListView" class="clients-list-container">
        <?php if (empty($clients)): ?>
        <div class="clients-empty-state">
            <div class="empty-icon-circle">
                <i class="ph ph-users-three"></i>
            </div>
            <h3>No hay clientes registrados</h3>
            <p>Comienza creando el primer cliente para organizar marcas, servicios y accesos.</p>
            <div class="empty-actions">
                <button type="button" class="btn btn-primary" onclick="ClientModule.openModal()">
                    <i class="ph ph-plus"></i> Registrar Primer Cliente
                </button>
            </div>
        </div>
        <?php else: ?>
            <?php
            $avatarColors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#2563eb','#0d9488','#ea580c'];
            foreach ($clients as $i => $client):
                $initials = mb_strtoupper(mb_substr($client['name'], 0, 2));
                $avatarColor = $avatarColors[$i % count($avatarColors)];
                $brandNames = $client['brands'] ? explode('||', $client['brands']) : [];
                $brandLogos = $client['logos'] ? explode('||', $client['logos']) : [];
                $brandMemberships = $client['memberships'] ? explode('||', $client['memberships']) : [];
                $hasAnyMembership = in_array('1', $brandMemberships);
                $cleanWa = preg_replace('/[^0-9]/', '', $client['whatsapp'] ?? '');
            ?>
            <div class="client-row-card" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)">
                <!-- Avatar Column -->
                <div class="client-row-avatar-col">
                    <div class="client-saas-avatar" style="background: <?php echo $avatarColor; ?>;">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                </div>

                <!-- Name & Identification -->
                <div class="client-row-name-col">
                    <div class="client-main-name">
                        <span><?php echo htmlspecialchars($client['name']); ?></span>
                        <?php if ($hasAnyMembership): ?>
                        <span class="membership-pill-badge"><i class="ph-fill ph-star"></i> Membresía</span>
                        <?php endif; ?>
                    </div>
                    <div class="client-meta-pills">
                        <?php if (!empty($client['dni'])): ?>
                        <span class="client-dni-badge"><i class="ph ph-identification-card"></i> <?php echo htmlspecialchars($client['dni']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($client['portal_enabled'])): ?>
                        <span class="client-portal-badge" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 10.5px; font-weight: 600; color: #10b981; background: color-mix(in srgb, #10b981 12%, transparent); padding: 1px 6px; border-radius: 4px; border: 1px solid color-mix(in srgb, #10b981 25%, transparent);" title="Portal de cliente activo">
                            <i class="ph-fill ph-check-circle"></i> Portal Activo
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($client['drive_folder_id'])): ?>
                        <a href="https://drive.google.com/drive/folders/<?php echo htmlspecialchars($client['drive_folder_id']); ?>" target="_blank" onclick="event.stopPropagation();" class="client-drive-link" title="Abrir Google Drive">
                            <i class="ph ph-google-drive-logo"></i> Portal Drive
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Direct Contact Actions -->
                <div class="client-row-contact-col">
                    <?php if (!empty($client['whatsapp'])): ?>
                    <a href="https://wa.me/<?php echo $cleanWa; ?>" target="_blank" onclick="event.stopPropagation();" class="contact-pill whatsapp-pill" title="Conversar en WhatsApp">
                        <i class="ph-fill ph-whatsapp-logo"></i>
                        <span><?php echo htmlspecialchars($client['whatsapp']); ?></span>
                    </a>
                    <?php else: ?>
                    <span class="contact-empty-tag"><i class="ph ph-whatsapp-logo"></i> Sin WhatsApp</span>
                    <?php endif; ?>

                    <?php if (!empty($client['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" onclick="event.stopPropagation();" class="contact-pill email-pill" title="<?php echo htmlspecialchars($client['email']); ?>">
                        <i class="ph ph-envelope-simple"></i>
                        <span><?php echo htmlspecialchars($client['email']); ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Associated Brands -->
                <div class="client-row-brands-col">
                    <div class="client-brands-container">
                        <?php if (!empty($brandNames)): ?>
                            <?php 
                            $maxShow = 3;
                            foreach (array_slice($brandNames, 0, $maxShow) as $idx => $bName):
                                $bLogo = $brandLogos[$idx] ?? '';
                                $hasM = ($brandMemberships[$idx] ?? '') === '1';
                            ?>
                            <span class="client-brand-chip <?php echo $hasM ? 'with-membership' : ''; ?>" title="<?php echo htmlspecialchars($bName); ?>">
                                <?php if ($bLogo): ?>
                                <img src="<?php echo htmlspecialchars($bLogo); ?>" alt="" class="brand-chip-img">
                                <?php else: ?>
                                <i class="ph ph-briefcase"></i>
                                <?php endif; ?>
                                <span class="brand-chip-name"><?php echo htmlspecialchars($bName); ?></span>
                                <?php if ($hasM): ?>
                                <i class="ph-fill ph-star brand-star-badge" title="Membresía Activa"></i>
                                <?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($brandNames) > $maxShow): ?>
                            <span class="client-brand-more">+<?php echo count($brandNames) - $maxShow; ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="no-brands-label"><i class="ph ph-minus"></i> Sin marcas</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Toolbar -->
                <div class="client-row-actions-col" onclick="event.stopPropagation();">
                    <a href="index.php?module=clients&action=social_auth&client_id=<?php echo $client['id']; ?>" class="action-btn-saas" title="Conexiones y Redes Sociales">
                        <i class="ph ph-share-network"></i>
                    </a>
                    <button type="button" class="action-btn-saas" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)" title="Editar Cliente">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button type="button" class="action-btn-saas delete-btn" onclick="ClientModule.deleteClient(<?php echo $client['id']; ?>)" title="Eliminar Cliente">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 5. Grid View (Initially hidden, dynamic toggle) -->
    <div id="clientsGridView" class="clients-grid-container" style="display: none;">
        <?php if (!empty($clients)):
            foreach ($clients as $i => $client):
                $initials = mb_strtoupper(mb_substr($client['name'], 0, 2));
                $avatarColor = $avatarColors[$i % count($avatarColors)];
                $brandNames = $client['brands'] ? explode('||', $client['brands']) : [];
                $brandLogos = $client['logos'] ? explode('||', $client['logos']) : [];
                $brandMemberships = $client['memberships'] ? explode('||', $client['memberships']) : [];
                $hasAnyMembership = in_array('1', $brandMemberships);
                $cleanWa = preg_replace('/[^0-9]/', '', $client['whatsapp'] ?? '');
        ?>
        <div class="client-grid-card <?php echo $hasAnyMembership ? 'has-membership' : ''; ?>" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)">
            <div class="grid-card-header">
                <div class="client-saas-avatar" style="background: <?php echo $avatarColor; ?>;">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <div class="grid-card-title-group">
                    <div class="grid-card-name"><?php echo htmlspecialchars($client['name']); ?></div>
                    <div class="grid-card-badges">
                        <?php if (!empty($client['dni'])): ?>
                        <span class="client-dni-badge"><i class="ph ph-identification-card"></i> <?php echo htmlspecialchars($client['dni']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($client['portal_enabled'])): ?>
                        <span class="client-portal-badge" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 10px; font-weight: 600; color: #10b981; background: color-mix(in srgb, #10b981 12%, transparent); padding: 1px 5px; border-radius: 4px; border: 1px solid color-mix(in srgb, #10b981 25%, transparent);" title="Portal de cliente activo">
                            <i class="ph-fill ph-check-circle"></i> Portal
                        </span>
                        <?php endif; ?>
                        <?php if ($hasAnyMembership): ?>
                        <span class="membership-pill-badge"><i class="ph-fill ph-star"></i> Membresía</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid-card-contacts">
                <?php if (!empty($client['whatsapp'])): ?>
                <a href="https://wa.me/<?php echo $cleanWa; ?>" target="_blank" onclick="event.stopPropagation();" class="contact-pill whatsapp-pill">
                    <i class="ph-fill ph-whatsapp-logo"></i>
                    <span><?php echo htmlspecialchars($client['whatsapp']); ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($client['email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" onclick="event.stopPropagation();" class="contact-pill email-pill">
                    <i class="ph ph-envelope-simple"></i>
                    <span><?php echo htmlspecialchars($client['email']); ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($client['drive_folder_id'])): ?>
                <a href="https://drive.google.com/drive/folders/<?php echo htmlspecialchars($client['drive_folder_id']); ?>" target="_blank" onclick="event.stopPropagation();" class="client-drive-link">
                    <i class="ph ph-google-drive-logo"></i> Google Drive
                </a>
                <?php endif; ?>
            </div>

            <div class="grid-card-brands-section">
                <div class="grid-brands-title">Marcas Asociadas (<?php echo count($brandNames); ?>)</div>
                <div class="grid-brands-list">
                    <?php if (!empty($brandNames)): ?>
                        <?php foreach ($brandNames as $idx => $bName):
                            $bLogo = $brandLogos[$idx] ?? '';
                            $hasM = ($brandMemberships[$idx] ?? '') === '1';
                        ?>
                        <span class="client-brand-chip <?php echo $hasM ? 'with-membership' : ''; ?>">
                            <?php if ($bLogo): ?>
                            <img src="<?php echo htmlspecialchars($bLogo); ?>" alt="" class="brand-chip-img">
                            <?php else: ?>
                            <i class="ph ph-briefcase"></i>
                            <?php endif; ?>
                            <span class="brand-chip-name"><?php echo htmlspecialchars($bName); ?></span>
                            <?php if ($hasM): ?>
                            <i class="ph-fill ph-star brand-star-badge" title="Membresía"></i>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="no-brands-label"><i class="ph ph-minus"></i> Sin marcas asociadas</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-card-footer" onclick="event.stopPropagation();">
                <a href="index.php?module=clients&action=social_auth&client_id=<?php echo $client['id']; ?>" class="action-btn-saas" title="Redes Sociales">
                    <i class="ph ph-share-network"></i>
                </a>
                <div class="grid-footer-actions">
                    <button type="button" class="action-btn-saas" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)" title="Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button type="button" class="action-btn-saas delete-btn" onclick="ClientModule.deleteClient(<?php echo $client['id']; ?>)" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- 6. Modal Rediseñado (Nuevo / Editar Cliente) -->
<div class="modal-overlay" id="clientModal">
    <div class="modal-content saas-modal-dialog">
        <!-- Modal Header -->
        <div class="saas-modal-header">
            <h2 class="saas-modal-title" id="clientModalTitle">
                <i class="ph ph-user-plus"></i> <span>Nuevo Cliente</span>
            </h2>
            <button type="button" class="btn-close-circular" onclick="ClientModule.closeModal()" title="Cerrar modal">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="saas-modal-body">
            <form id="clientForm" onsubmit="return false;">
                <input type="hidden" name="client_id" id="client_id">

                <div class="modal-split-grid">
                    <!-- Column 1: Client Personal Details -->
                    <div class="modal-section-box">
                        <h3 class="modal-section-title">
                            <i class="ph ph-identification-card"></i> Datos del Cliente
                        </h3>

                        <div class="form-group">
                            <label class="form-label" for="client_name">Nombre Completo o Empresa *</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-user"></i>
                                <input type="text" class="form-control" name="name" id="client_name" placeholder="Ej: Corporación Andina S.A." required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_dni">DNI / RUC</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-fingerprint"></i>
                                <input type="text" class="form-control" name="dni" id="client_dni" placeholder="Ej: 71234567 o 20123456789">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_whatsapp">WhatsApp / Teléfono</label>
                            <div class="field-icon-wrap">
                                <i class="ph-fill ph-whatsapp-logo" style="color: #25d366;"></i>
                                <input type="text" class="form-control" name="whatsapp" id="client_whatsapp" placeholder="Ej: +51 987 654 321">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_email">Correo Electrónico</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-envelope-simple"></i>
                                <input type="email" class="form-control" name="email" id="client_email" placeholder="contacto@empresa.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_drive_folder_id">ID Carpeta Google Drive (Portal)</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-google-drive-logo" style="color: #3b82f6;"></i>
                                <input type="text" class="form-control" name="drive_folder_id" id="client_drive_folder_id" placeholder="ID (ej: 1A2b3C4d5E...)">
                            </div>
                            <div class="field-help-tip">Habilita acceso automático al portal de entregables para este cliente.</div>
                        </div>

                        <!-- Portal Switcher Card -->
                        <div class="portal-toggle-box" style="margin-top: 1.1rem; padding: 0.85rem 1rem; border-radius: var(--client-radius-md); background: color-mix(in srgb, var(--primary-color) 6%, var(--bg-body, var(--bg-surface))); border: 1px solid color-mix(in srgb, var(--primary-color) 20%, var(--border-color));">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 34px; height: 34px; border-radius: 8px; background: color-mix(in srgb, var(--primary-color) 15%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                                        <i class="ph ph-app-window"></i>
                                    </div>
                                    <div>
                                        <div style="font-size: 12.5px; font-weight: 600; color: var(--text-main);">Activar Portal de Cliente</div>
                                        <div style="font-size: 11px; color: var(--text-muted);">Permite al cliente ingresar con su DNI/RUC en el portal</div>
                                    </div>
                                </div>
                                <label class="modern-toggle-switch" style="margin: 0; flex-shrink: 0;">
                                    <input type="checkbox" name="portal_enabled" id="client_portal_enabled" value="1">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Brands, Memberships & Services -->
                    <div class="modal-section-box">
                        <h3 class="modal-section-title">
                            <i class="ph ph-briefcase"></i> Marcas y Servicios Vinculados
                        </h3>

                        <!-- Brand Add Box -->
                        <div class="brand-create-box">
                            <div class="brand-create-inputs">
                                <div class="brand-name-input-wrap">
                                    <label class="field-micro-label">Nombre de la Marca</label>
                                    <input type="text" class="form-control" id="new_brand_name" placeholder="Ej: Mi Marca Pro" style="height: 38px; font-size: 13px;">
                                </div>
                                <div class="brand-logo-input-wrap">
                                    <div style="flex:1;">
                                        <label class="field-micro-label">Logotipo (Opcional)</label>
                                        <input type="file" class="form-control" id="new_brand_logo" accept="image/*" onchange="ClientModule.previewNewBrandLogo(this)" style="height: 38px;">
                                    </div>
                                    <div class="logo-preview-box" id="newBrandLogoPreview"></div>
                                </div>
                            </div>
                            <button type="button" class="brand-add-btn" onclick="ClientModule.addBrand()">
                                <i class="ph ph-plus"></i> Vincular Marca
                            </button>
                        </div>

                        <!-- Dynamic Brands List -->
                        <div id="brandsList" class="brands-list-dynamic">
                            <!-- Injected dynamically via ClientModule.renderBrands() -->
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="ClientModule.closeModal()">
                Cancelar
            </button>
            <button type="button" class="btn btn-primary" id="btnSaveClient" onclick="ClientModule.saveClient()">
                <i class="ph ph-check-circle"></i>
                <span class="btn-text">Guardar Cliente</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#new') {
        setTimeout(() => {
            if (typeof ClientModule !== 'undefined' && ClientModule.openModal) {
                ClientModule.openModal();
            }
        }, 300);
        history.replaceState('', document.title, window.location.pathname + window.location.search);
    }
});
</script>

<script src="assets/js/modules/clients.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
