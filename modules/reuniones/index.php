<?php
// modules/reuniones/index.php
require_once 'includes/header.php';

global $db;

// Pagination and Filtering
$search = $_GET['search'] ?? '';
$brand_id = $_GET['brand_id'] ?? '';
$status = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(r.motivo LIKE ? OR r.resumen LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($brand_id) {
    $where[] = "r.brand_id = ?";
    $params[] = $brand_id;
}

if ($status) {
    $where[] = "r.estado = ?";
    $params[] = $status;
} else {
    // Default: hide eliminated (trash)
    $where[] = "r.estado != 'Eliminada'";
}

$whereClause = implode(" AND ", $where);

// Fetch reuniones
$sql = "SELECT r.*, b.name as brand_name, b.logo as brand_logo, b.whatsapp_group, c.whatsapp as client_whatsapp 
        FROM reuniones r 
        LEFT JOIN client_brands b ON r.brand_id = b.id 
        LEFT JOIN clients c ON b.client_id = c.id
        WHERE $whereClause 
        ORDER BY r.fecha_hora DESC 
        LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for filter
$stmtBrands = $db->query("SELECT id, name FROM client_brands ORDER BY name ASC");
$marcas = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

$activeFilters = ($search || $brand_id || $status) ? true : false;
$filterCount = ($search ? 1 : 0) + ($brand_id ? 1 : 0) + ($status ? 1 : 0);

// Fetch Quick Stats for Bento Header
$stat_total = count($reuniones);
$stat_prog = 0;
$stat_comp = 0;
$stat_rooms = 0;
try {
    $stat_prog = (int)$db->query("SELECT COUNT(*) FROM reuniones WHERE estado = 'Programada'")->fetchColumn();
    $stat_comp = (int)$db->query("SELECT COUNT(*) FROM reuniones WHERE estado = 'Completada'")->fetchColumn();
    $stat_rooms = (int)$db->query("SELECT COUNT(*) FROM meeting_rooms WHERE is_active = 1")->fetchColumn();
} catch(Exception $e) {}
?>

<style>
/* ==========================================================================
   REUNIONES MODERN APP-STYLE DESIGN SYSTEM (APPLE BENTO / MACOS)
   ========================================================================== */

.reuniones-page-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem 1rem;
    animation: appFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes appFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulseDot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.35); opacity: 0.6; }
}

@keyframes shimmerLoading {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* ============ SEGMENTED FLOATING NAV TABS ============ */
.reuniones-segmented-nav {
    display: inline-flex;
    align-items: center;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 5px;
    border-radius: 9999px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px -2px rgba(0, 0, 0, 0.04);
    gap: 4px;
}

[data-theme="dark"] .reuniones-segmented-nav {
    background: #141720;
    border-color: rgba(255, 255, 255, 0.08);
}

.reuniones-seg-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.15rem;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    text-decoration: none;
    transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

.reuniones-seg-tab:hover {
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .reuniones-seg-tab:hover {
    color: #ffffff;
}

.reuniones-seg-tab.active {
    background: var(--primary-color, #4f46e5);
    color: #ffffff !important;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color, #4f46e5) 35%, transparent);
}

.reuniones-seg-badge {
    background: rgba(255, 255, 255, 0.25);
    color: inherit;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.12rem 0.45rem;
    border-radius: 9999px;
}

.reuniones-seg-tab:not(.active) .reuniones-seg-badge {
    background: color-mix(in srgb, var(--border-color, #e2e8f0) 80%, transparent);
    color: var(--text-muted, #64748b);
}

/* ============ HERO APP BAR ============ */
.reuniones-hero-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .reuniones-hero-card {
    background: #141720;
    border-color: rgba(255, 255, 255, 0.08);
}

.hero-ambient-accent {
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(234, 67, 53, 0.12) 0%, rgba(244, 63, 94, 0.06) 45%, transparent 70%);
    top: -120px;
    right: -40px;
    pointer-events: none;
    filter: blur(40px);
}

.hero-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}

.hero-identity {
    display: flex;
    align-items: center;
    gap: 1.15rem;
}

.hero-app-squircle {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    background: linear-gradient(135deg, #ea4335 0%, #ff5252 50%, #e11d48 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    color: #ffffff;
    box-shadow: 0 10px 25px -4px rgba(234, 67, 53, 0.45);
    flex-shrink: 0;
}

.hero-app-text h1 {
    margin: 0;
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -0.6px;
    color: var(--text-main, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

[data-theme="dark"] .hero-app-text h1 {
    color: #ffffff;
}

.hero-badge-meet {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    background: rgba(234, 67, 53, 0.12);
    color: #ea4335;
    border: 1px solid rgba(234, 67, 53, 0.25);
    padding: 0.22rem 0.65rem;
    border-radius: 9999px;
}

.hero-app-text p {
    margin: 0.35rem 0 0 0;
    color: var(--text-muted, #64748b);
    font-size: 0.9rem;
    font-weight: 500;
}

.hero-app-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.btn-hero-sync {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: color-mix(in srgb, #10b981 10%, transparent);
    color: #10b981;
    border: 1.5px solid color-mix(in srgb, #10b981 30%, transparent);
    padding: 0.65rem 1.15rem;
    border-radius: 14px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.22s ease;
    text-decoration: none;
}

.btn-hero-sync:hover {
    background: color-mix(in srgb, #10b981 18%, transparent);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
}

.btn-hero-create {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #ea4335 0%, #dc2626 100%);
    color: #ffffff !important;
    border: none;
    padding: 0.65rem 1.25rem;
    border-radius: 14px;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 8px 20px -3px rgba(234, 67, 53, 0.4);
    transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-decoration: none;
}

.btn-hero-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -4px rgba(234, 67, 53, 0.5);
}

/* ============ BENTO MINI STATS TILES ============ */
.hero-bento-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
    position: relative;
    z-index: 2;
}

[data-theme="dark"] .hero-bento-stats {
    border-top-color: rgba(255, 255, 255, 0.06);
}

.bento-stat-pill {
    background: color-mix(in srgb, var(--bg-body, #f8fafc) 70%, transparent);
    border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
    padding: 0.75rem 1rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: all 0.2s ease;
}

[data-theme="dark"] .bento-stat-pill {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.06);
}

.bento-stat-pill:hover {
    transform: translateY(-2px);
    border-color: var(--primary-color, #4f46e5);
}

.bento-stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.bento-stat-icon.icon-blue {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
}

.bento-stat-icon.icon-amber {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}

.bento-stat-icon.icon-green {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.bento-stat-icon.icon-purple {
    background: rgba(139, 92, 246, 0.12);
    color: #8b5cf6;
}

.bento-stat-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.bento-stat-val {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--text-main, #0f172a);
    font-variant-numeric: tabular-nums;
}

[data-theme="dark"] .bento-stat-val {
    color: #ffffff;
}

.bento-stat-lbl {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

/* ============ SEARCH & FILTER BAR ============ */
.reuniones-filter-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .reuniones-filter-card {
    background: #141720;
    border-color: rgba(255, 255, 255, 0.08);
}

.filters-row {
    display: grid;
    grid-template-columns: 2fr 1.2fr 1fr auto;
    gap: 0.85rem;
    align-items: center;
}

.filter-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.filter-input-wrap i {
    position: absolute;
    left: 1rem;
    color: var(--text-muted, #94a3b8);
    font-size: 1rem;
    pointer-events: none;
}

.filter-input {
    width: 100%;
    padding: 0.65rem 1rem 0.65rem 2.5rem;
    border-radius: 12px;
    background: var(--bg-body, #f8fafc);
    border: 1.5px solid var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a);
    font-size: 0.85rem;
    font-weight: 500;
    outline: none;
    transition: all 0.2s ease;
}

[data-theme="dark"] .filter-input {
    background: #0d1017;
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.filter-input:focus {
    border-color: var(--primary-color, #4f46e5);
    background: var(--bg-surface, #ffffff);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color, #4f46e5) 15%, transparent);
}

[data-theme="dark"] .filter-input:focus {
    background: #141720;
}

.filter-select {
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: 12px;
    background: var(--bg-body, #f8fafc);
    border: 1.5px solid var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a);
    font-size: 0.85rem;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

[data-theme="dark"] .filter-select {
    background: #0d1017;
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.filter-select:focus {
    border-color: var(--primary-color, #4f46e5);
}

.btn-filter-reset {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.65rem 1rem;
    border-radius: 12px;
    background: transparent;
    border: 1.5px solid var(--border-color, #e2e8f0);
    color: var(--text-muted, #64748b);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.btn-filter-reset:hover {
    background: color-mix(in srgb, var(--text-muted, #64748b) 12%, transparent);
    color: var(--text-main, #0f172a);
}

/* ============ MEETINGS LIST: DESKTOP TABLE ============ */
.reuniones-table-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 22px;
    box-shadow: 0 6px 25px -4px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

[data-theme="dark"] .reuniones-table-card {
    background: #141720;
    border-color: rgba(255, 255, 255, 0.08);
}

.reuniones-app-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.88rem;
}

.reuniones-app-table thead th {
    padding: 0.95rem 1.25rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #94a3b8);
    background: color-mix(in srgb, var(--bg-body, #f8fafc) 80%, transparent);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

[data-theme="dark"] .reuniones-app-table thead th {
    background: rgba(255, 255, 255, 0.02);
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.reuniones-app-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--border-color, #f1f5f9);
}

[data-theme="dark"] .reuniones-app-table tbody tr {
    border-bottom-color: rgba(255, 255, 255, 0.04);
}

.reuniones-app-table tbody tr:hover {
    background: color-mix(in srgb, var(--primary-color, #4f46e5) 4%, transparent);
    transform: translateY(-1px);
}

[data-theme="dark"] .reuniones-app-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.reuniones-app-table tbody td {
    padding: 1.05rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color, #f1f5f9);
}

[data-theme="dark"] .reuniones-app-table tbody td {
    border-bottom-color: rgba(255, 255, 255, 0.04);
}

/* Brand & Meeting info */
.meet-main-cell {
    display: flex;
    align-items: center;
    gap: 0.95rem;
}

.brand-squircle-avatar {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.brand-squircle-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.meet-info-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.meet-title-link {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-main, #0f172a);
    text-decoration: none;
    line-height: 1.25;
    transition: color 0.2s;
}

.meet-title-link:hover {
    color: var(--primary-color, #4f46e5);
}

[data-theme="dark"] .meet-title-link {
    color: #f1f5f9;
}

[data-theme="dark"] .meet-title-link:hover {
    color: #818cf8;
}

.meet-meta-chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.brand-chip-label {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
}

.tag-micro-pill {
    background: color-mix(in srgb, var(--primary-color, #4f46e5) 10%, transparent);
    color: var(--primary-color, #4f46e5);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

/* Date & Time pill */
.date-time-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.date-primary {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .date-primary {
    color: #e2e8f0;
}

.time-secondary {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 3px;
}

/* Status Badges */
.status-pill-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.32rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.2px;
    white-space: nowrap;
}

.status-pill-modern .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-pill-programada {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.25);
}
.status-pill-programada .status-dot {
    background: #3b82f6;
    box-shadow: 0 0 6px #3b82f6;
    animation: pulseDot 2s infinite;
}

.status-pill-completada {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.status-pill-completada .status-dot {
    background: #10b981;
}

.status-pill-cancelada {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.25);
}
.status-pill-cancelada .status-dot {
    background: #ef4444;
}

/* Link Actions Squircles */
.meet-actions-group {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}

.squircle-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    text-decoration: none;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    color: var(--text-muted, #64748b);
    transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
}

[data-theme="dark"] .squircle-action-btn {
    background: #181b24;
    border-color: rgba(255, 255, 255, 0.08);
}

.squircle-action-btn:hover {
    transform: scale(1.08) translateY(-1px);
}

.squircle-action-btn.btn-meet-video {
    color: #ea4335;
    background: rgba(234, 67, 53, 0.08);
    border-color: rgba(234, 67, 53, 0.25);
}
.squircle-action-btn.btn-meet-video:hover {
    background: #ea4335;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(234, 67, 53, 0.35);
}

.squircle-action-btn.btn-meet-rec {
    color: #10b981;
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
}
.squircle-action-btn.btn-meet-rec:hover {
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
}

.squircle-action-btn.btn-meet-gemini {
    color: #8b5cf6;
    background: rgba(139, 92, 246, 0.08);
    border-color: rgba(139, 92, 246, 0.25);
}
.squircle-action-btn.btn-meet-gemini:hover {
    background: #8b5cf6;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
}

.squircle-action-btn.btn-meet-wa {
    color: #25D366;
    background: rgba(37, 211, 102, 0.08);
    border-color: rgba(37, 211, 102, 0.25);
}
.squircle-action-btn.btn-meet-wa:hover {
    background: #25D366;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35);
}

.btn-detail-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.95rem;
    border-radius: 11px;
    background: var(--primary-color, #4f46e5);
    color: #ffffff !important;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 4px 12px -2px color-mix(in srgb, var(--primary-color, #4f46e5) 40%, transparent);
}

.btn-detail-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px color-mix(in srgb, var(--primary-color, #4f46e5) 50%, transparent);
}

/* ============ MOBILE CARDS (RESPONSIVE) ============ */
.reuniones-mobile-grid {
    display: none;
    padding: 0.85rem;
    gap: 0.85rem;
}

.meet-mobile-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 18px;
    padding: 1.15rem 1rem;
    box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

[data-theme="dark"] .meet-mobile-card {
    background: #141720;
    border-color: rgba(255, 255, 255, 0.08);
}

.meet-mobile-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.meet-mobile-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
}

[data-theme="dark"] .meet-mobile-footer {
    border-top-color: rgba(255, 255, 255, 0.06);
}

/* Empty State */
.reuniones-empty-state {
    padding: 4.5rem 2rem;
    text-align: center;
}

.empty-squircle-icon {
    width: 72px;
    height: 72px;
    border-radius: 22px;
    background: color-mix(in srgb, var(--primary-color, #4f46e5) 10%, transparent);
    color: var(--primary-color, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin: 0 auto 1.25rem auto;
}

/* Skeleton Loading */
.sk-box {
    background: linear-gradient(90deg, var(--border-color) 25%, color-mix(in srgb, var(--border-color), white 25%) 50%, var(--border-color) 75%);
    background-size: 200% 100%;
    animation: shimmerLoading 1.5s ease-in-out infinite;
    border-radius: 8px;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .filters-row {
        grid-template-columns: 1fr 1fr;
    }
    .hero-bento-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .reuniones-page-wrapper {
        padding: 0.75rem 0.45rem 4rem 0.45rem;
    }
    .reuniones-hero-card {
        padding: 1.25rem 1rem;
        border-radius: 20px;
    }
    .hero-top-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .hero-app-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .btn-hero-sync, .btn-hero-create {
        justify-content: center;
        padding: 0.65rem 0.5rem;
        font-size: 0.82rem;
    }
    .hero-bento-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.6rem;
    }
    .bento-stat-pill {
        padding: 0.65rem 0.75rem;
    }
    .filters-row {
        grid-template-columns: 1fr;
    }
    .reuniones-table-card {
        display: none;
    }
    .reuniones-mobile-grid {
        display: flex;
        flex-direction: column;
        padding: 0;
    }
}
</style>

<div class="reuniones-page-wrapper">

    <!-- Segmented Navigation Tabs -->
    <div class="reuniones-segmented-nav">
        <a href="index.php?module=reuniones&action=index" class="reuniones-seg-tab active">
            <i class="ph-fill ph-list-bullets"></i>
            <span>Historial</span>
            <span class="reuniones-seg-badge"><?php echo $stat_total; ?></span>
        </a>
        <a href="index.php?module=reuniones&action=rooms" class="reuniones-seg-tab">
            <i class="ph-fill ph-buildings"></i>
            <span>Salas</span>
            <span class="reuniones-seg-badge"><?php echo $stat_rooms; ?></span>
        </a>
    </div>

    <!-- App Hero Header -->
    <div class="reuniones-hero-card">
        <div class="hero-ambient-accent"></div>

        <div class="hero-top-row">
            <div class="hero-identity">
                <div class="hero-app-squircle">
                    <i class="ph-fill ph-video-camera"></i>
                </div>
                <div class="hero-app-text">
                    <h1>
                        Historial de Reuniones
                        <span class="hero-badge-meet">Meet & IA</span>
                    </h1>
                    <p>Gestiona tus videollamadas en vivo, grabaciones y minutas automáticas con Gemini IA.</p>
                </div>
            </div>

            <div class="hero-app-actions">
                <button type="button" onclick="syncGeminiNotes()" class="btn-hero-sync" id="btn-sync-notes" title="Sincronizar con correos de Gemini">
                    <i class="ph-bold ph-arrows-clockwise"></i>
                    <span>Sincronizar IA</span>
                </button>
                <button type="button" onclick="if(window.openMeetModal) window.openMeetModal();" class="btn-hero-create" title="Programar nueva reunión de Meet">
                    <i class="ph-bold ph-calendar-plus"></i>
                    <span>Programar</span>
                </button>
            </div>
        </div>

        <!-- Bento Mini Stats Row -->
        <div class="hero-bento-stats">
            <div class="bento-stat-pill">
                <div class="bento-stat-icon icon-blue"><i class="ph-bold ph-calendar"></i></div>
                <div class="bento-stat-text">
                    <span class="bento-stat-val"><?php echo $stat_total; ?></span>
                    <span class="bento-stat-lbl">Reuniones</span>
                </div>
            </div>
            <div class="bento-stat-pill">
                <div class="bento-stat-icon icon-amber"><i class="ph-bold ph-clock-countdown"></i></div>
                <div class="bento-stat-text">
                    <span class="bento-stat-val"><?php echo $stat_prog; ?></span>
                    <span class="bento-stat-lbl">Programadas</span>
                </div>
            </div>
            <div class="bento-stat-pill">
                <div class="bento-stat-icon icon-green"><i class="ph-bold ph-check-circle"></i></div>
                <div class="bento-stat-text">
                    <span class="bento-stat-val"><?php echo $stat_comp; ?></span>
                    <span class="bento-stat-lbl">Completadas</span>
                </div>
            </div>
            <div class="bento-stat-pill">
                <div class="bento-stat-icon icon-purple"><i class="ph-bold ph-buildings"></i></div>
                <div class="bento-stat-text">
                    <span class="bento-stat-val"><?php echo $stat_rooms; ?></span>
                    <span class="bento-stat-lbl">Salas Activas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters Form -->
    <div class="reuniones-filter-card">
        <form id="reuniones-filter-form" method="GET" action="index.php">
            <input type="hidden" name="module" value="reuniones">
            <div class="filters-row">
                <div class="filter-input-wrap">
                    <i class="ph-bold ph-magnifying-glass"></i>
                    <input type="text" name="search" id="filter-search" class="filter-input" placeholder="Buscar por motivo, tema o resumen..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div>
                    <select name="brand_id" id="filter-brand" class="filter-select">
                        <option value="">Todas las marcas</option>
                        <?php foreach($marcas as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $brand_id == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <select name="status" id="filter-status" class="filter-select">
                        <option value="">Todos los estados</option>
                        <option value="Programada" <?php echo $status == 'Programada' ? 'selected' : ''; ?>>Programada</option>
                        <option value="Completada" <?php echo $status == 'Completada' ? 'selected' : ''; ?>>Completada</option>
                        <option value="Cancelada" <?php echo $status == 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="Eliminada" <?php echo $status == 'Eliminada' ? 'selected' : ''; ?>>Papelera</option>
                    </select>
                </div>

                <div>
                    <a href="index.php?module=reuniones" class="btn-filter-reset" title="Limpiar filtros">
                        <i class="ph-bold ph-arrow-counter-clockwise"></i>
                        <span>Limpiar</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Meetings Container (AJAX Target) -->
    <div id="reuniones-list-container">
        <?php if(empty($reuniones)): ?>
            <div class="reuniones-table-card">
                <div class="reuniones-empty-state">
                    <div class="empty-squircle-icon">
                        <i class="ph ph-video-camera-slash"></i>
                    </div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">No se encontraron reuniones</h3>
                    <p style="color: var(--text-muted); font-size: 0.88rem; max-width: 380px; margin: 0 auto;">No hay registros que coincidan con los filtros seleccionados. Intenta cambiar los criterios de búsqueda.</p>
                </div>
            </div>
        <?php else: ?>

            <!-- Desktop Modern Table -->
            <div class="reuniones-table-card">
                <table class="reuniones-app-table">
                    <thead>
                        <tr>
                            <th style="width: 38%;">Reunión & Marca</th>
                            <th style="width: 22%;">Fecha y Hora</th>
                            <th style="width: 15%; text-align: center;">Estado</th>
                            <th style="width: 12%; text-align: center;">Enlaces</th>
                            <th style="width: 13%; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reuniones as $r): ?>
                            <tr>
                                <td>
                                    <div class="meet-main-cell">
                                        <div class="brand-squircle-avatar" style="background: linear-gradient(135deg, rgba(79,70,229,0.1) 0%, rgba(147,51,234,0.1) 100%);">
                                            <?php if(!empty($r['brand_logo']) && file_exists($r['brand_logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($r['brand_logo']); ?>" alt="<?php echo htmlspecialchars($r['brand_name']); ?>">
                                            <?php else: ?>
                                                <span style="color: var(--primary-color, #4f46e5);"><?php echo strtoupper(substr($r['brand_name'] ?: 'R', 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meet-info-text">
                                            <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="meet-title-link">
                                                <?php echo htmlspecialchars($r['motivo']); ?>
                                            </a>
                                            <div class="meet-meta-chips">
                                                <span class="brand-chip-label"><?php echo htmlspecialchars($r['brand_name'] ?: 'Reunión General'); ?></span>
                                                <?php if(!empty($r['tags'])): ?>
                                                    <?php $tags = explode(',', $r['tags']); foreach($tags as $t): $t = trim($t); if(!$t) continue; ?>
                                                        <span class="tag-micro-pill"><i class="ph-bold ph-tag"></i><?php echo htmlspecialchars($t); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="date-time-cell">
                                        <span class="date-primary"><?php echo date('d M, Y', strtotime($r['fecha_hora'])); ?></span>
                                        <span class="time-secondary"><i class="ph-bold ph-clock"></i> <?php echo date('h:i A', strtotime($r['fecha_hora'])); ?></span>
                                    </div>
                                </td>

                                <td style="text-align: center;">
                                    <?php
                                        $pillClass = 'status-pill-programada';
                                        if($r['estado'] === 'Completada') $pillClass = 'status-pill-completada';
                                        elseif($r['estado'] === 'Cancelada' || $r['estado'] === 'Eliminada') $pillClass = 'status-pill-cancelada';
                                    ?>
                                    <span class="status-pill-modern <?php echo $pillClass; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo htmlspecialchars($r['estado']); ?>
                                    </span>
                                </td>

                                <td style="text-align: center;">
                                    <div class="meet-actions-group">
                                        <?php if(!empty($r['meet_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($r['meet_link']); ?>" target="_blank" class="squircle-action-btn btn-meet-video" title="Unirse a Google Meet">
                                                <i class="ph-bold ph-video-camera"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if(!empty($r['recording_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($r['recording_link']); ?>" target="_blank" class="squircle-action-btn btn-meet-rec" title="Ver Grabación">
                                                <i class="ph-bold ph-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if(!empty($r['resumen'])): ?>
                                            <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="squircle-action-btn btn-meet-gemini" title="Minuta Gemini disponible">
                                                <i class="ph-bold ph-sparkle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td style="text-align: right;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.45rem;">
                                        <?php if(!empty($r['meet_link'])): ?>
                                            <button type="button" class="squircle-action-btn" onclick="copyInvitation('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>')" title="Copiar invitación">
                                                <i class="ph-bold ph-copy"></i>
                                            </button>
                                            <button type="button" class="squircle-action-btn btn-meet-wa" onclick="sendWhatsApp('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>', '<?php echo htmlspecialchars($r['client_whatsapp'] ?? ''); ?>', '<?php echo htmlspecialchars($r['whatsapp_group'] ?? ''); ?>')" title="Enviar por WhatsApp">
                                                <i class="ph-bold ph-whatsapp-logo"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="btn-detail-link">
                                            <span>Detalle</span>
                                            <i class="ph-bold ph-arrow-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Modern Cards Grid -->
            <div class="reuniones-mobile-grid">
                <?php foreach($reuniones as $r): ?>
                    <?php
                        $pillClass = 'status-pill-programada';
                        if($r['estado'] === 'Completada') $pillClass = 'status-pill-completada';
                        elseif($r['estado'] === 'Cancelada' || $r['estado'] === 'Eliminada') $pillClass = 'status-pill-cancelada';
                    ?>
                    <div class="meet-mobile-card">
                        <div class="meet-mobile-top">
                            <div style="display: flex; gap: 0.75rem; align-items: center; min-width: 0;">
                                <div class="brand-squircle-avatar" style="width: 40px; height: 40px; border-radius: 11px;">
                                    <?php if(!empty($r['brand_logo']) && file_exists($r['brand_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($r['brand_logo']); ?>" alt="<?php echo htmlspecialchars($r['brand_name']); ?>">
                                    <?php else: ?>
                                        <span style="color: var(--primary-color, #4f46e5); font-size: 1rem;"><?php echo strtoupper(substr($r['brand_name'] ?: 'R', 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0;">
                                    <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="meet-title-link" style="font-size: 0.92rem;">
                                        <?php echo htmlspecialchars($r['motivo']); ?>
                                    </a>
                                    <div class="brand-chip-label" style="margin-top: 2px;">
                                        <?php echo htmlspecialchars($r['brand_name'] ?: 'Reunión General'); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="status-pill-modern <?php echo $pillClass; ?>" style="font-size: 0.68rem; padding: 0.2rem 0.55rem;">
                                <span class="status-dot"></span>
                                <?php echo htmlspecialchars($r['estado']); ?>
                            </span>
                        </div>

                        <?php if(!empty($r['tags'])): ?>
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                <?php $tags = explode(',', $r['tags']); foreach($tags as $t): $t = trim($t); if(!$t) continue; ?>
                                    <span class="tag-micro-pill"><i class="ph-bold ph-tag"></i><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="meet-mobile-footer">
                            <div class="time-secondary">
                                <i class="ph-bold ph-calendar-blank"></i>
                                <span><?php echo date('d M', strtotime($r['fecha_hora'])); ?> · <?php echo date('h:i A', strtotime($r['fecha_hora'])); ?></span>
                            </div>

                            <div style="display: flex; gap: 0.4rem; align-items: center;">
                                <?php if(!empty($r['meet_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($r['meet_link']); ?>" target="_blank" class="squircle-action-btn btn-meet-video" style="width: 32px; height: 32px; font-size: 0.95rem;" title="Meet">
                                        <i class="ph-bold ph-video-camera"></i>
                                    </a>
                                    <button type="button" class="squircle-action-btn btn-meet-wa" style="width: 32px; height: 32px; font-size: 0.95rem;" onclick="sendWhatsApp('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>', '<?php echo htmlspecialchars($r['client_whatsapp'] ?? ''); ?>', '<?php echo htmlspecialchars($r['whatsapp_group'] ?? ''); ?>')" title="WhatsApp">
                                        <i class="ph-bold ph-whatsapp-logo"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="btn-detail-link" style="padding: 0.38rem 0.75rem; font-size: 0.76rem;">
                                    <span>Ver</span>
                                    <i class="ph-bold ph-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

</div>

<script>
function copyInvitation(title, datetime, link) {
    const textToCopy = `${title}\n${datetime}\n${link}\nTe esperamos en la reunión.`;
    navigator.clipboard.writeText(textToCopy).then(() => {
        Swal.fire({
            title: '¡Copiado!',
            text: 'Enlace e invitación copiada al portapapeles.',
            icon: 'success',
            timer: 1600,
            showConfirmButton: false
        });
    }).catch(err => {
        Swal.fire('Error', 'No se pudo copiar la invitación.', 'error');
    });
}

function sendWhatsApp(title, datetime, link, clientPhone, groupPhone) {
    const textToCopy = `Hola, te comparto el enlace para nuestra reunión:\n\n*${title}*\n📅 ${datetime}\n🔗 ${link}\n\nTe esperamos.`;
    const escapedMsg = textToCopy.replace(/`/g, '\\`');

    const optionsHtml = `
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:15px;">
            <button class="swal2-confirm swal2-styled" style="background-color: #25D366; width:100%; margin:0; display:flex; align-items:center; justify-content:center; gap:8px; border-radius: 12px;" onclick="executeSendWA('${groupPhone}', \`${escapedMsg}\`)">
                <i class="ph ph-users"></i> Enviar al Grupo del Proyecto
            </button>
            <button class="swal2-confirm swal2-styled" style="background-color: #128C7E; width:100%; margin:0; display:flex; align-items:center; justify-content:center; gap:8px; border-radius: 12px;" onclick="executeSendWA('${clientPhone}', \`${escapedMsg}\`)">
                <i class="ph ph-user"></i> Enviar al Cliente Directo
            </button>
        </div>
    `;
    
    Swal.fire({
        title: 'Enviar Invitación',
        html: optionsHtml,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancelar'
    });
}

function executeSendWA(phone, msg) {
    if (!phone || phone === 'undefined' || phone === 'null') {
        Swal.fire('Atención', 'No hay un número o ID de grupo registrado para esta opción.', 'warning');
        return;
    }
    Swal.fire({
        title: 'Enviando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch('ajax/send_meet_whatsapp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: phone, message: msg })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Enviado', 'La invitación se envió por WhatsApp correctamente.', 'success');
        } else {
            Swal.fire('Error', res.error || 'No se pudo enviar el mensaje', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reuniones-filter-form');
    const listContainer = document.getElementById('reuniones-list-container');
    let timeout = null;

    const fetchResults = () => {
        const url = new URL(form.action, window.location.origin);
        const params = new URLSearchParams(new FormData(form));
        url.search = params.toString();

        const skeletonHtml = `
            <div class="reuniones-table-card" style="padding: 1.5rem;">
                <div style="display:flex; flex-direction:column; gap:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; gap:1rem; width:45%;">
                            <div class="sk-box" style="width:44px; height:44px; border-radius:13px;"></div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem; width:80%;">
                                <div class="sk-box" style="width:80%; height:16px;"></div>
                                <div class="sk-box" style="width:40%; height:12px;"></div>
                            </div>
                        </div>
                        <div class="sk-box" style="width:20%; height:24px;"></div>
                        <div class="sk-box" style="width:15%; height:32px;"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; gap:1rem; width:45%;">
                            <div class="sk-box" style="width:44px; height:44px; border-radius:13px;"></div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem; width:80%;">
                                <div class="sk-box" style="width:70%; height:16px;"></div>
                                <div class="sk-box" style="width:50%; height:12px;"></div>
                            </div>
                        </div>
                        <div class="sk-box" style="width:20%; height:24px;"></div>
                        <div class="sk-box" style="width:15%; height:32px;"></div>
                    </div>
                </div>
            </div>
        `;
        listContainer.innerHTML = skeletonHtml;

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('reuniones-list-container');
            if (newTable) {
                listContainer.innerHTML = newTable.innerHTML;
                window.history.replaceState({}, '', url.toString());
            }
        });
    };

    document.getElementById('filter-brand').addEventListener('change', fetchResults);
    document.getElementById('filter-status').addEventListener('change', fetchResults);

    document.getElementById('filter-search').addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(fetchResults, 350);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchResults();
    });
});

async function syncGeminiNotes() {
    const btn = document.getElementById('btn-sync-notes');
    if (!btn) return;
    
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Sincronizando...';
    btn.disabled = true;

    try {
        const res = await fetch('cron/fetch_gemini_notes.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        let data;
        try {
            data = await res.json();
        } catch (e) {
            const text = await res.text();
            data = { success: false, log: [text] };
        }
        
        let logHtml = '';
        if (data.log && data.log.length > 0) {
            logHtml = '<ul style="text-align: left; background: var(--bg-color, #f8fafc); padding: 1rem 1rem 1rem 2rem; border-radius: 8px; font-size: 0.88rem; max-height: 280px; overflow-y: auto; margin: 0; color: var(--text-main, #334155); border: 1px solid var(--border-color, #e2e8f0);">';
            data.log.forEach(item => {
                logHtml += `<li style="margin-bottom: 0.4rem; line-height: 1.4;">${item}</li>`;
            });
            logHtml += '</ul>';
        } else {
            logHtml = '<p style="color: var(--text-muted); margin: 0; padding: 1rem; text-align: center;">No se encontraron nuevas actualizaciones de correos.</p>';
        }

        Swal.fire({
            title: data.success ? 'Sincronización Completada' : 'Aviso',
            html: logHtml,
            icon: data.success ? 'success' : 'warning',
            confirmButtonText: 'Continuar',
            allowOutsideClick: false
        }).then(() => {
            location.reload();
        });

    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar al sincronizador', 'error');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
