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
    window.SYSTEM_SERVICES = <?php echo json_encode($all_services); ?>;
    const SYSTEM_SERVICES = window.SYSTEM_SERVICES;
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

/* --- 7. Modal Redesign - Dual Theme Contrast (Light & Dark) --- */
.saas-modal-dialog {
    max-width: 1060px !important;
    width: 95% !important;
    border-radius: var(--client-radius-xl) !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04) !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

[data-theme="dark"] .saas-modal-dialog {
    border: 1px solid #2e3444 !important;
    background: #14161f !important;
    box-shadow: 0 25px 65px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.06) !important;
}

.saas-modal-header {
    padding: 1.2rem 1.65rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

[data-theme="dark"] .saas-modal-header {
    border-bottom: 1px solid #2e3444;
    background: #191c28;
}

.saas-modal-title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.saas-modal-icon-badge {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: var(--primary-color, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

[data-theme="dark"] .saas-modal-icon-badge {
    background: rgba(99, 102, 241, 0.22);
    border: 1px solid rgba(99, 102, 241, 0.35);
    color: #818cf8;
}

.saas-modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    letter-spacing: -0.01em;
}

[data-theme="dark"] .saas-modal-title {
    color: #ffffff;
}

.saas-modal-subtitle {
    font-size: 12px;
    color: #64748b;
    margin: 2px 0 0 0;
    font-weight: 400;
}

[data-theme="dark"] .saas-modal-subtitle {
    color: #94a3b8;
}

.btn-close-circular {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-close-circular:hover {
    background: #e2e8f0;
    color: #0f172a;
}

[data-theme="dark"] .btn-close-circular {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #f1f5f9;
}

[data-theme="dark"] .btn-close-circular:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
}

.saas-modal-body {
    padding: 1.65rem 1.75rem;
    max-height: calc(85vh - 145px);
    overflow-y: auto;
    background: #ffffff;
}

[data-theme="dark"] .saas-modal-body {
    background: #14161f;
}

.saas-modal-body::-webkit-scrollbar {
    width: 6px;
}
.saas-modal-body::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 4px;
}
[data-theme="dark"] .saas-modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.14);
}

.modal-split-grid {
    display: grid;
    grid-template-columns: 1fr 1.25fr;
    gap: 1.85rem;
    align-items: start;
}

.modal-section-box {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.modal-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #e2e8f0;
}

[data-theme="dark"] .modal-section-header {
    border-bottom: 1px solid #2e3444;
}

.modal-section-badge {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #eef2ff;
    color: var(--primary-color, #4338ca);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

[data-theme="dark"] .modal-section-badge {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

.modal-section-badge.brand-badge {
    background: #eff6ff;
    color: #2563eb;
}

[data-theme="dark"] .modal-section-badge.brand-badge {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.modal-section-title {
    font-size: 12.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #0f172a;
    margin: 0;
}

[data-theme="dark"] .modal-section-title {
    color: #f8fafc;
}

.modal-section-desc {
    font-size: 11px;
    color: #64748b;
    display: block;
    margin-top: 1px;
}

[data-theme="dark"] .modal-section-desc {
    color: #94a3b8;
}

.modal-section-box .form-label,
.modal-section-box .field-micro-label {
    color: #1e293b;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 0.35rem;
    display: block;
}

[data-theme="dark"] .modal-section-box .form-label,
[data-theme="dark"] .modal-section-box .field-micro-label {
    color: #f1f5f9;
}

.field-icon-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.field-icon-wrap i {
    position: absolute;
    left: 0.85rem;
    color: #64748b;
    font-size: 1.05rem;
    pointer-events: none;
    transition: color 0.2s ease;
}

[data-theme="dark"] .field-icon-wrap i {
    color: #94a3b8;
}

.field-icon-wrap .form-control {
    padding-left: 2.5rem;
    height: 42px;
    font-size: 13px;
    border-radius: var(--client-radius-sm);
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #0f172a !important;
    transition: all 0.2s ease;
    width: 100%;
}

.field-icon-wrap .form-control::placeholder {
    color: #94a3b8 !important;
    opacity: 1;
}

.field-icon-wrap .form-control:focus {
    outline: none;
    background: #ffffff;
    border-color: var(--primary-color, #4f46e5);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

[data-theme="dark"] .field-icon-wrap .form-control {
    background: #191c26;
    border: 1px solid #2e3444;
    color: #ffffff !important;
}

[data-theme="dark"] .field-icon-wrap .form-control::placeholder {
    color: #64748b !important;
    opacity: 1;
}

[data-theme="dark"] .field-icon-wrap .form-control:focus {
    background: #1f2330;
    border-color: #818cf8;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.field-help-tip {
    font-size: 11px;
    color: #64748b;
    margin-top: 0.25rem;
}

[data-theme="dark"] .field-help-tip {
    color: #94a3b8;
}

/* Portal Switcher Card */
.portal-toggle-box {
    margin-top: 0.35rem;
    padding: 0.95rem 1.15rem;
    border-radius: var(--client-radius-md);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    transition: all 0.2s ease;
}

.portal-toggle-box.active {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

[data-theme="dark"] .portal-toggle-box {
    background: #191c26;
    border: 1px solid #2e3444;
}

[data-theme="dark"] .portal-toggle-box.active {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.35);
}

.portal-toggle-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.portal-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #eef2ff;
    color: var(--primary-color, #4338ca);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.portal-toggle-box.active .portal-icon-wrap {
    background: #d1fae5;
    color: #059669;
}

[data-theme="dark"] .portal-icon-wrap {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

[data-theme="dark"] .portal-toggle-box.active .portal-icon-wrap {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.portal-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.portal-title {
    font-size: 12.5px;
    font-weight: 600;
    color: #0f172a;
}

[data-theme="dark"] .portal-title {
    color: #ffffff;
}

.portal-status-pill {
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 1px 7px;
    border-radius: 6px;
    background: #e2e8f0;
    color: #475569;
}

.portal-status-pill.active {
    background: #d1fae5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

[data-theme="dark"] .portal-status-pill {
    background: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}

[data-theme="dark"] .portal-status-pill.active {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.35);
}

.portal-desc {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

[data-theme="dark"] .portal-desc {
    color: #94a3b8;
}

/* Brand Creator Box inside modal */
.brand-create-box {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: var(--client-radius-md);
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    position: relative;
}

[data-theme="dark"] .brand-create-box {
    background: #191c26;
    border: 1px solid #2e3444;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.brand-create-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand-create-title {
    font-size: 12px;
    font-weight: 700;
    color: #2563eb;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

[data-theme="dark"] .brand-create-title {
    color: #60a5fa;
}

.brand-logo-control-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.brand-logo-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.field-micro-hint {
    font-size: 11px;
    color: #64748b;
}

[data-theme="dark"] .field-micro-hint {
    color: #94a3b8;
}

/* Logo Drop & Paste Zone */
.logo-drop-paste-zone {
    border: 1.5px dashed #93c5fd;
    border-radius: 12px;
    padding: 0.95rem 1.1rem;
    background: #ffffff;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.9rem;
    transition: all 0.2s ease;
    user-select: none;
}

.logo-drop-paste-zone:hover,
.logo-drop-paste-zone.dragover {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 16px rgba(37, 99, 235, 0.12);
}

[data-theme="dark"] .logo-drop-paste-zone {
    border: 1.5px dashed rgba(96, 165, 250, 0.4);
    background: rgba(59, 130, 246, 0.05);
}

[data-theme="dark"] .logo-drop-paste-zone:hover,
[data-theme="dark"] .logo-drop-paste-zone.dragover {
    border-color: #60a5fa;
    background: rgba(59, 130, 246, 0.12);
    box-shadow: 0 0 16px rgba(59, 130, 246, 0.2);
}

.drop-zone-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.logo-drop-paste-zone:hover .drop-zone-icon {
    transform: translateY(-2px);
    background: #dbeafe;
}

[data-theme="dark"] .drop-zone-icon {
    background: rgba(59, 130, 246, 0.18);
    color: #60a5fa;
}

[data-theme="dark"] .logo-drop-paste-zone:hover .drop-zone-icon {
    background: rgba(59, 130, 246, 0.25);
}

.drop-zone-content {
    flex: 1;
    min-width: 0;
}

.drop-zone-title {
    font-size: 12.5px;
    color: #0f172a;
    line-height: 1.35;
}

.drop-zone-title strong {
    color: #2563eb;
}

[data-theme="dark"] .drop-zone-title {
    color: #f1f5f9;
}

[data-theme="dark"] .drop-zone-title strong {
    color: #60a5fa;
}

.drop-zone-hint {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

[data-theme="dark"] .drop-zone-hint {
    color: #94a3b8;
}

.drop-zone-actions {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    flex-shrink: 0;
}

.zone-action-btn {
    height: 30px;
    padding: 0 0.75rem;
    border-radius: 7px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #1e293b;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.zone-action-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
    border-color: #94a3b8;
}

[data-theme="dark"] .zone-action-btn {
    background: #232733;
    border: 1px solid #3b4255;
    color: #f1f5f9;
}

[data-theme="dark"] .zone-action-btn:hover {
    background: #2e3547;
    border-color: #60a5fa;
    color: #ffffff;
}

/* URL Input Bar */
.logo-url-input-bar {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: #ffffff;
    padding: 0.45rem 0.55rem;
    border-radius: 9px;
    border: 1px solid #93c5fd;
    animation: fadeInSlide 0.2s ease;
}

[data-theme="dark"] .logo-url-input-bar {
    background: #14161f;
    border: 1px solid rgba(59, 130, 246, 0.4);
}

.url-input-wrap {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
}

.url-input-wrap i {
    position: absolute;
    left: 0.7rem;
    color: #64748b;
    font-size: 1rem;
    pointer-events: none;
}

[data-theme="dark"] .url-input-wrap i {
    color: #94a3b8;
}

.url-input-wrap input {
    width: 100%;
    height: 36px;
    padding: 0 0.5rem 0 2.2rem;
    font-size: 12px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #0f172a;
}

.url-input-wrap input:focus {
    outline: none;
    border-color: #2563eb;
    background: #ffffff;
}

[data-theme="dark"] .url-input-wrap input {
    background: #1f2330;
    border: 1px solid #3b4255;
    color: #f8fafc;
}

[data-theme="dark"] .url-input-wrap input:focus {
    border-color: #60a5fa;
    background: #252b3b;
}

.btn-apply-url {
    height: 36px;
    padding: 0 0.9rem;
    border-radius: 6px;
    background: #2563eb;
    border: none;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-apply-url:hover {
    background: #1d4ed8;
}

.btn-cancel-url {
    height: 36px;
    width: 36px;
    border-radius: 6px;
    background: transparent;
    border: 1px solid #cbd5e1;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-cancel-url:hover {
    background: #f1f5f9;
    color: #0f172a;
}

[data-theme="dark"] .btn-cancel-url {
    border: 1px solid #3b4255;
    color: #94a3b8;
}

[data-theme="dark"] .btn-cancel-url:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

/* Loaded Logo Preview Card */
.logo-loaded-preview-card {
    border: 1px solid #86efac;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    background: #f0fdf4;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    animation: fadeInSlide 0.2s ease;
}

[data-theme="dark"] .logo-loaded-preview-card {
    border: 1px solid rgba(16, 185, 129, 0.35);
    background: rgba(16, 185, 129, 0.08);
}

.preview-thumb-box {
    width: 46px;
    height: 46px;
    border-radius: 9px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    padding: 3px;
}

[data-theme="dark"] .preview-thumb-box {
    background: #12141a;
    border: 1px solid #2e3444;
}

.preview-thumb-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.preview-info-box {
    flex: 1;
    min-width: 0;
}

.preview-source-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 2px 7px;
    border-radius: 5px;
    background: #dcfce7;
    color: #15803d;
}

.preview-source-badge.clipboard {
    background: #ede9fe;
    color: #6d28d9;
}

.preview-source-badge.url {
    background: #dbeafe;
    color: #1d4ed8;
}

[data-theme="dark"] .preview-source-badge {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

[data-theme="dark"] .preview-source-badge.clipboard {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
}

[data-theme="dark"] .preview-source-badge.url {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.preview-file-name {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .preview-file-name {
    color: #f8fafc;
}

.preview-actions-box {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.preview-action-btn {
    height: 30px;
    padding: 0 0.7rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
}

.preview-action-btn.change {
    background: #ffffff;
    color: #1e293b;
}

.preview-action-btn.change:hover {
    background: #f8fafc;
    border-color: #2563eb;
    color: #2563eb;
}

[data-theme="dark"] .preview-action-btn.change {
    background: #232733;
    border-color: #3b4255;
    color: #e2e8f0;
}

[data-theme="dark"] .preview-action-btn.change:hover {
    background: #2e3547;
    border-color: #60a5fa;
    color: #60a5fa;
}

.preview-action-btn.remove {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #b91c1c;
    width: 30px;
    padding: 0;
    justify-content: center;
}

.preview-action-btn.remove:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

[data-theme="dark"] .preview-action-btn.remove {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #f87171;
}

[data-theme="dark"] .preview-action-btn.remove:hover {
    background: #ef4444;
    color: #ffffff;
}

/* Brand Add Submit Button */
.brand-add-submit-btn {
    width: 100%;
    height: 40px;
    border-radius: 9px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    border: none;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.25);
    transition: all 0.2s ease;
}

.brand-add-submit-btn:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.35);
    transform: translateY(-1px);
}

/* Dynamic Brand Editor Cards */
.brands-list-dynamic {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 0.25rem;
}

.brand-editor-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--client-radius-md);
    padding: 0.9rem 1.05rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: var(--client-transition);
}

.brand-editor-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .brand-editor-card {
    background: #191c26;
    border: 1px solid #2e3444;
    box-shadow: none;
}

[data-theme="dark"] .brand-editor-card:hover {
    border-color: rgba(99, 102, 241, 0.35);
}

.brand-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand-identity {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.brand-avatar-clickable-wrap {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    flex-shrink: 0;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .brand-avatar-clickable-wrap {
    border: 1px solid #2e3444;
    background: #12141a;
}

.brand-avatar-clickable-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.brand-preview-avatar.default {
    background: #f1f5f9;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
}

[data-theme="dark"] .brand-preview-avatar.default {
    background: #232733;
    color: #94a3b8;
}

.avatar-hover-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.brand-avatar-clickable-wrap:hover .avatar-hover-overlay {
    opacity: 1;
}

.brand-name-wrap {
    display: flex;
    flex-direction: column;
}

.brand-card-title {
    font-weight: 600;
    font-size: 13px;
    color: #0f172a;
}

[data-theme="dark"] .brand-card-title {
    color: #ffffff;
}

.brand-card-subtitle {
    font-size: 11px;
    color: #64748b;
}

[data-theme="dark"] .brand-card-subtitle {
    color: #94a3b8;
}

.brand-delete-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: #64748b;
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
    background: #fee2e2;
    color: #b91c1c;
}

[data-theme="dark"] .brand-delete-btn {
    color: #94a3b8;
}

[data-theme="dark"] .brand-delete-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.brand-card-controls {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1rem;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
}

[data-theme="dark"] .brand-card-controls {
    border-top: 1px dashed #2e3444;
}

/* Secondary modal (Change logo for existing brand) */
.change-logo-modal-dialog {
    max-width: 440px !important;
    background: #ffffff !important;
    border-radius: 16px !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15) !important;
    padding: 1.25rem !important;
    color: #0f172a !important;
}

[data-theme="dark"] .change-logo-modal-dialog {
    background: #161821 !important;
    border: 1px solid #2e3444 !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.65) !important;
    color: #f8fafc !important;
}

.change-logo-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #e2e8f0;
}

[data-theme="dark"] .change-logo-header {
    border-bottom: 1px solid #2e3444;
}

.saas-modal-footer {
    padding: 1.1rem 1.65rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

[data-theme="dark"] .saas-modal-footer {
    border-top: 1px solid #2e3444;
    background: #191c28;
}

.saas-modal-footer .btn-outline {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 13px;
    height: 40px;
    padding: 0 1.2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.saas-modal-footer .btn-outline:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}

[data-theme="dark"] .saas-modal-footer .btn-outline {
    background: transparent;
    border: 1px solid #3f3f46;
    color: #e4e4e7;
}

[data-theme="dark"] .saas-modal-footer .btn-outline:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.saas-modal-footer .btn-primary {
    height: 40px;
    padding: 0 1.35rem;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

@keyframes fadeInSlide {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
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
    background-color: #cbd5e1;
    transition: 0.25s;
    border-radius: 20px;
}

[data-theme="dark"] .toggle-slider {
    background-color: #3f3f46;
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
    color: #1e293b;
    cursor: pointer;
    user-select: none;
}

[data-theme="dark"] .toggle-label {
    color: #e2e8f0;
}

.field-micro-label {
    font-size: 10.5px;
    font-weight: 600;
    color: #64748b;
    display: block;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

[data-theme="dark"] .field-micro-label {
    color: #94a3b8;
}

/* Multiselect Styling - High Contrast & Dual Theme */
.custom-multiselect {
    position: relative;
    width: 100%;
}

.custom-multiselect-header {
    border: 1px solid #cbd5e1;
    border-radius: var(--client-radius-sm);
    padding: 6px 10px;
    background: #ffffff;
    color: #0f172a;
    cursor: pointer;
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    transition: var(--client-transition);
}

.custom-multiselect-header:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}

[data-theme="dark"] .custom-multiselect-header {
    border: 1px solid #2e3444;
    background: #14161f;
    color: #f8fafc;
}

[data-theme="dark"] .custom-multiselect-header:hover {
    border-color: #4f46e5;
    background: #191c26;
}

.badges-wrapper {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
}

.service-selected-pill {
    background: #e0e7ff;
    color: #3730a3;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #c7d2fe;
    line-height: 1.2;
}

[data-theme="dark"] .service-selected-pill {
    background: rgba(99, 102, 241, 0.22);
    color: #c7d2fe;
    border: 1px solid rgba(99, 102, 241, 0.4);
}

.pill-remove-btn {
    border: none;
    background: transparent;
    color: inherit;
    font-size: 13px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    opacity: 0.7;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.15s, transform 0.15s;
}

.pill-remove-btn:hover {
    opacity: 1;
    transform: scale(1.15);
}

.service-none-text {
    color: #64748b;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .service-none-text {
    color: #94a3b8;
}

.caret-icon {
    color: #64748b;
    font-size: 0.9rem;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

[data-theme="dark"] .caret-icon {
    color: #94a3b8;
}

.custom-multiselect.open .caret-icon {
    transform: rotate(180deg);
}

.custom-multiselect-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: var(--client-radius-sm);
    max-height: 240px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    z-index: 100;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12), 0 4px 10px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .custom-multiselect-dropdown {
    background: #191c26;
    border: 1px solid #333a4d;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.55);
}

.multiselect-search-box {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

[data-theme="dark"] .multiselect-search-box {
    border-bottom: 1px solid #2e3444;
    background: #191c26;
}

.multiselect-search-box i {
    color: #64748b;
    font-size: 13px;
}

[data-theme="dark"] .multiselect-search-box i {
    color: #94a3b8;
}

.multiselect-search-box input {
    width: 100%;
    padding: 4px 7px;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #f8fafc;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s;
}

[data-theme="dark"] .multiselect-search-box input {
    background: #12141c;
    border: 1px solid #2e3444;
    color: #f8fafc;
}

.multiselect-search-box input:focus {
    border-color: #6366f1;
}

.multiselect-options-list {
    overflow-y: auto;
    max-height: 165px;
    flex: 1;
}

.custom-multiselect-option {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    margin: 0;
    font-size: 12px;
    color: #0f172a;
    transition: background 0.15s;
    user-select: none;
}

[data-theme="dark"] .custom-multiselect-option {
    color: #f1f5f9;
    border-bottom: 1px solid #232733;
}

.custom-multiselect-option:hover {
    background: #f1f5f9;
}

[data-theme="dark"] .custom-multiselect-option:hover {
    background: rgba(99, 102, 241, 0.15);
}

.custom-multiselect-option.selected {
    background: #eef2ff;
    font-weight: 500;
}

[data-theme="dark"] .custom-multiselect-option.selected {
    background: rgba(99, 102, 241, 0.2);
}

.custom-multiselect-option input[type="checkbox"] {
    accent-color: #4f46e5;
    cursor: pointer;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
}

.multiselect-dropdown-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
}

[data-theme="dark"] .multiselect-dropdown-footer {
    background: #12141c;
    border-top: 1px solid #2e3444;
}

.services-count-text {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

[data-theme="dark"] .services-count-text {
    color: #94a3b8;
}

.btn-dropdown-done {
    background: #4f46e5;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    padding: 3px 12px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-dropdown-done:hover {
    background: #4338ca;
}

.input-with-icon-mini {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-icon-mini i {
    position: absolute;
    left: 0.65rem;
    color: #64748b;
    font-size: 0.9rem;
}

[data-theme="dark"] .input-with-icon-mini i {
    color: #94a3b8;
}

.form-control-sm {
    width: 100%;
    height: 34px;
    padding: 0 0.5rem 0 1.9rem;
    font-size: 11.5px;
    border: 1px solid #cbd5e1;
    border-radius: var(--client-radius-sm);
    background: #f8fafc;
    color: #0f172a;
}

.form-control-sm:focus {
    outline: none;
    border-color: #2563eb;
    background: #ffffff;
}

[data-theme="dark"] .form-control-sm {
    border: 1px solid #2e3444;
    background: #14161f;
    color: #f8fafc;
}

[data-theme="dark"] .form-control-sm:focus {
    border-color: #60a5fa;
    background: #1e2230;
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
            <div class="saas-modal-title-group">
                <div class="saas-modal-icon-badge" id="clientModalIconBadge">
                    <i class="ph ph-user-plus"></i>
                </div>
                <div>
                    <h2 class="saas-modal-title" id="clientModalTitle">Nuevo Cliente</h2>
                    <p class="saas-modal-subtitle" id="clientModalSubtitle">Registra los datos comerciales y gestiona sus marcas</p>
                </div>
            </div>
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
                        <div class="modal-section-header">
                            <span class="modal-section-badge"><i class="ph ph-user"></i></span>
                            <div>
                                <h3 class="modal-section-title">Datos del Cliente</h3>
                                <span class="modal-section-desc">Información comercial y de contacto</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_name">Nombre Completo o Empresa *</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-buildings"></i>
                                <input type="text" class="form-control" name="name" id="client_name" placeholder="Ej: Corporación Andina S.A." required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="client_dni">DNI / RUC</label>
                            <div class="field-icon-wrap">
                                <i class="ph ph-identification-card"></i>
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

                        <!-- Portal Switcher Card -->
                        <div class="portal-toggle-box" id="portalToggleCard">
                            <div class="portal-toggle-left">
                                <div class="portal-icon-wrap">
                                    <i class="ph ph-app-window"></i>
                                </div>
                                <div class="portal-info-wrap">
                                    <div class="portal-title-row">
                                        <span class="portal-title">Portal de Cliente</span>
                                        <span class="portal-status-pill" id="portalStatusPill">Desactivado</span>
                                    </div>
                                    <div class="portal-desc">Acceso con DNI/RUC para entregables y servicios</div>
                                </div>
                            </div>
                            <label class="modern-toggle-switch" style="margin: 0; flex-shrink: 0;">
                                <input type="checkbox" name="portal_enabled" id="client_portal_enabled" value="1" onchange="ClientModule.onPortalToggleChange(this)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Column 2: Brands, Memberships & Services -->
                    <div class="modal-section-box">
                        <div class="modal-section-header">
                            <span class="modal-section-badge brand-badge"><i class="ph ph-briefcase"></i></span>
                            <div>
                                <h3 class="modal-section-title">Marcas y Servicios</h3>
                                <span class="modal-section-desc">Vincula marcas comerciales y membresías</span>
                            </div>
                        </div>

                        <!-- Brand Add Box -->
                        <div class="brand-create-box">
                            <div class="brand-create-header">
                                <span class="brand-create-title"><i class="ph ph-sparkle"></i> Vincular Nueva Marca</span>
                            </div>

                            <div class="form-group" style="margin: 0;">
                                <label class="field-micro-label" for="new_brand_name">Nombre de la Marca *</label>
                                <div class="field-icon-wrap">
                                    <i class="ph ph-tag"></i>
                                    <input type="text" class="form-control" id="new_brand_name" placeholder="Ej: Mi Marca Pro, Nike Perú, etc.">
                                </div>
                            </div>

                            <!-- Logo Multi-channel Uploader -->
                            <div class="brand-logo-control-group">
                                <div class="brand-logo-header-row">
                                    <label class="field-micro-label" style="margin: 0;">Logotipo</label>
                                    <span class="field-micro-hint">Opcional</span>
                                </div>

                                <!-- Hidden native file input -->
                                <input type="file" id="new_brand_logo_file" accept="image/*" style="display:none;" onchange="ClientModule.handleLogoFileInput(this)">

                                <!-- Empty / Drop / Paste zone when no logo is set -->
                                <div id="brandLogoEmptyZone" class="logo-drop-paste-zone" onclick="document.getElementById('new_brand_logo_file').click()">
                                    <div class="drop-zone-icon">
                                        <i class="ph ph-cloud-arrow-up"></i>
                                    </div>
                                    <div class="drop-zone-content">
                                        <div class="drop-zone-title">
                                            <strong>Haz clic para subir</strong> o arrastra una imagen aquí
                                        </div>
                                        <div class="drop-zone-hint">
                                            PNG, JPG, WEBP, SVG &bull; También puedes <strong>presionar Ctrl+V</strong>
                                        </div>
                                    </div>
                                    <div class="drop-zone-actions" onclick="event.stopPropagation();">
                                        <button type="button" class="zone-action-btn" onclick="ClientModule.pasteLogoFromClipboard()" title="Pegar imagen desde el portapapeles">
                                            <i class="ph ph-clipboard-text"></i> Pegar
                                        </button>
                                        <button type="button" class="zone-action-btn" onclick="ClientModule.toggleLogoUrlInput()" title="Ingresar enlace de imagen">
                                            <i class="ph ph-link"></i> URL
                                        </button>
                                    </div>
                                </div>

                                <!-- URL Input Bar -->
                                <div id="brandLogoUrlBar" class="logo-url-input-bar" style="display: none;">
                                    <div class="url-input-wrap">
                                        <i class="ph ph-link-simple"></i>
                                        <input type="url" id="brand_logo_url_input" placeholder="Pega el enlace de la imagen (https://...)" onkeydown="if(event.key==='Enter'){event.preventDefault();ClientModule.applyLogoUrl();}">
                                    </div>
                                    <button type="button" class="btn-apply-url" onclick="ClientModule.applyLogoUrl()">
                                        Cargar
                                    </button>
                                    <button type="button" class="btn-cancel-url" onclick="ClientModule.toggleLogoUrlInput(false)" title="Cerrar">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </div>

                                <!-- Loaded Preview Card -->
                                <div id="brandLogoPreviewCard" class="logo-loaded-preview-card" style="display: none;">
                                    <div class="preview-thumb-box">
                                        <img id="brandLogoPreviewImg" src="" alt="Vista previa del logotipo">
                                    </div>
                                    <div class="preview-info-box">
                                        <div class="preview-source-badge" id="brandLogoSourceBadge">
                                            <i class="ph ph-check-circle"></i> Logotipo cargado
                                        </div>
                                        <div class="preview-file-name" id="brandLogoFileName">logo.png</div>
                                    </div>
                                    <div class="preview-actions-box">
                                        <button type="button" class="preview-action-btn change" onclick="document.getElementById('new_brand_logo_file').click()" title="Cambiar imagen">
                                            <i class="ph ph-arrows-clockwise"></i> Cambiar
                                        </button>
                                        <button type="button" class="preview-action-btn remove" onclick="ClientModule.clearDraftLogo()" title="Quitar logotipo">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="brand-add-submit-btn" onclick="ClientModule.addBrand()">
                                <i class="ph ph-plus-circle"></i> Vincular Marca al Cliente
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
        <div class="modal-footer saas-modal-footer">
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

<!-- Modal Secundario: Cambiar Logotipo de Marca Existente -->
<div class="modal-overlay" id="changeLogoModal" style="z-index: 1050;">
    <div class="modal-content change-logo-modal-dialog">
        <input type="file" id="change_brand_logo_file" accept="image/*" style="display:none;" onchange="ClientModule.handleChangeBrandLogoFile(this)">
        
        <div class="change-logo-header">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div class="modal-section-badge brand-badge" style="width: 34px; height: 34px; border-radius: 9px; font-size: 1.15rem;">
                    <i class="ph ph-image"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 13px; font-weight: 700;" id="changeLogoModalBrandTitle">Cambiar Logotipo</h4>
                    <span style="font-size: 11px; opacity: 0.8;">Elige cómo deseas actualizar la imagen</span>
                </div>
            </div>
            <button type="button" class="btn-close-circular" onclick="ClientModule.closeChangeLogoModal()" style="width: 28px; height: 28px;">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <!-- Drop zone / click to upload -->
            <div class="logo-drop-paste-zone" style="padding: 0.85rem;" onclick="document.getElementById('change_brand_logo_file').click()">
                <div class="drop-zone-icon" style="width: 36px; height: 36px; font-size: 1.15rem;">
                    <i class="ph ph-cloud-arrow-up"></i>
                </div>
                <div class="drop-zone-content">
                    <div style="font-size: 12px; font-weight: 600;">Subir archivo desde el equipo</div>
                    <div class="drop-zone-hint">PNG, JPG, WEBP o SVG</div>
                </div>
            </div>

            <!-- Paste from clipboard button -->
            <button type="button" class="zone-action-btn" style="height: 38px; justify-content: center; width: 100%; font-size: 12px;" onclick="ClientModule.pasteLogoFromClipboardForExisting()">
                <i class="ph ph-clipboard-text" style="font-size: 1.15rem;"></i> Pegar desde Portapapeles (Ctrl+V)
            </button>

            <!-- URL input -->
            <div style="margin-top: 0.25rem;">
                <label class="field-micro-label">O ingresa una URL de imagen:</label>
                <div style="display: flex; gap: 0.4rem;">
                    <input type="url" id="change_brand_logo_url_input" class="form-control-sm" placeholder="https://ejemplo.com/logo.png" style="flex: 1; height: 36px;" onkeydown="if(event.key==='Enter'){event.preventDefault();ClientModule.applyChangeLogoUrl();}">
                    <button type="button" class="btn-apply-url" style="height: 36px;" onclick="ClientModule.applyChangeLogoUrl()">Aplicar</button>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-outline" style="font-size: 11.5px; height: 32px; padding: 0 0.75rem; color: var(--danger-color); border-color: rgba(239,68,68,0.3);" onclick="ClientModule.removeExistingBrandLogo()">
                    <i class="ph ph-trash"></i> Quitar Logo
                </button>
                <button type="button" class="btn btn-outline" style="font-size: 11.5px; height: 32px; padding: 0 0.85rem;" onclick="ClientModule.closeChangeLogoModal()">
                    Cancelar
                </button>
            </div>
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
