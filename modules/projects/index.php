<?php
// modules/projects/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// ── Current view (active / archived) ──
$currentView = isset($_GET['view']) && $_GET['view'] === 'archived' ? 'archived' : 'active';

// ── Fetch counts for both active and archived ──
$countActive = (int)$db->query("SELECT COUNT(*) FROM module_projects WHERE status = 'active'")->fetchColumn();
$countArchived = (int)$db->query("SELECT COUNT(*) FROM module_projects WHERE status = 'archived'")->fetchColumn();

// ── Fetch all users for team member mapping & select ──
$stmtUsers = $db->query("SELECT id, name FROM users ORDER BY name ASC");
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
$usersMap = [];
foreach ($allUsers as $u) { $usersMap[$u['id']] = $u; }

// ── Fetch clients for select ──
$stmtClients = $db->query("SELECT id, name FROM clients ORDER BY name ASC");
$allClients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch brands for select ──
$stmtBrands = $db->query("SELECT id, client_id, name, logo FROM client_brands ORDER BY name ASC");
$allBrands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch services for select ──
$stmtServices = $db->query("SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY name ASC");
$allServices = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch projects from module_projects ──
$stmtProjects = $db->prepare("
    SELECT mp.*, 
           s.name AS service_name,
           c.name AS client_name,
           cb.name AS brand_name,
           cb.logo AS brand_logo,
           (SELECT COUNT(*) FROM project_services ps WHERE ps.project_id = mp.id) AS services_count
    FROM module_projects mp
    LEFT JOIN services s ON mp.service_id = s.id
    LEFT JOIN clients c ON mp.client_id = c.id
    LEFT JOIN client_brands cb ON mp.brand_id = cb.id
    WHERE mp.status = ?
    ORDER BY mp.created_at DESC
");
$stmtProjects->execute([$currentView]);
$projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch Google Drive Folders ──
require_once 'includes/GoogleDriveHelper.php';
$driveHelper = new GoogleDriveHelper();
$driveFolders = [];
if ($driveHelper->isConfigured()) {
    // 1. Find the "EQUIPO" root folder
    $equipoId = null;
    $roots = $driveHelper->listFolders('root');
    if ($roots) {
        foreach ($roots as $r) {
            if (strtoupper(trim($r->getName())) === 'EQUIPO') {
                $equipoId = $r->getId();
                break;
            }
        }
    }
    
    // 2. Fetch children of "EQUIPO"
    if ($equipoId) {
        $folders = $driveHelper->listFolders($equipoId);
        if ($folders) {
            foreach ($folders as $f) {
                $driveFolders[] = ['id' => $f->getId(), 'name' => 'EQUIPO / ' . $f->getName()];
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════════
   PROJECTS MODULE — MODERN SAAS REDESIGN (LIGHT & DARK DYNAMIC)
   ═══════════════════════════════════════════════ */
:root {
    --pj-bg-card: #ffffff;
    --pj-bg-surface: #ffffff;
    --pj-bg-subtle: #f8fafc;
    --pj-border: #e2e8f0;
    --pj-border-hover: color-mix(in srgb, var(--primary-color) 40%, transparent);
    --pj-text-title: var(--color-title, #0f172a);
    --pj-text-muted: var(--color-text, #64748b);
    --pj-btn-bg: var(--color-btn-bg, var(--primary-color));
    --pj-btn-hover: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
    --pj-btn-text: var(--color-btn-text, #ffffff);
    --pj-shadow-card: 0 2px 10px rgba(0, 0, 0, 0.04);
    --pj-shadow-hover: 0 12px 28px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] {
    --pj-bg-card: #161618;
    --pj-bg-surface: #141416;
    --pj-bg-subtle: #18181a;
    --pj-border: #27272a;
    --pj-border-hover: color-mix(in srgb, var(--primary-color) 40%, transparent);
    --pj-text-title: var(--color-title, #f8fafc);
    --pj-text-muted: var(--color-text, #94a3b8);
    --pj-shadow-card: 0 4px 20px rgba(0, 0, 0, 0.25);
    --pj-shadow-hover: 0 16px 36px rgba(0, 0, 0, 0.45);
}

.projects-container {
    padding: 1.5rem 2rem;
    font-family: var(--font-main, 'Inter'), sans-serif;
    font-size: 13px;
    max-width: 1680px;
    margin: 0 auto;
}

/* ── Header Bar ── */
.projects-header-card {
    background: var(--pj-bg-surface);
    border: 1px solid var(--pj-border);
    border-radius: 20px;
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    box-shadow: var(--pj-shadow-card);
}

.projects-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.projects-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--primary-color) 28%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.projects-header-titles h1 {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--pj-text-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: -0.02em;
}

.projects-header-titles p {
    color: var(--pj-text-muted);
    margin: 0.2rem 0 0 0;
    font-size: 0.85rem;
    font-weight: 500;
}

/* ── Header Actions (Search + Tabs + CTA) ── */
.projects-header-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

/* Search Bar */
.pj-search-box {
    position: relative;
    display: flex;
    align-items: center;
}
.pj-search-box i {
    position: absolute;
    left: 12px;
    color: var(--pj-text-muted);
    font-size: 1.1rem;
    pointer-events: none;
}
.pj-search-input {
    background: var(--pj-bg-subtle);
    border: 1px solid var(--pj-border);
    border-radius: 12px;
    padding: 0.55rem 0.85rem 0.55rem 2.2rem;
    color: var(--pj-text-title);
    font-size: 0.85rem;
    font-family: inherit;
    width: 240px;
    transition: all 0.2s ease;
}
.pj-search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--pj-bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
    width: 280px;
}

/* Tabs */
.projects-tabs {
    display: flex;
    background: var(--pj-bg-subtle);
    border-radius: 12px;
    padding: 4px;
    border: 1px solid var(--pj-border);
    gap: 4px;
}

.projects-tab {
    padding: 6px 16px;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--pj-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    background: transparent;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.projects-tab:hover {
    color: var(--pj-text-title);
    background: rgba(125, 125, 125, 0.08);
}

.projects-tab.active {
    background: var(--pj-bg-card);
    color: var(--pj-text-title);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.projects-tab .tab-count {
    background: rgba(125, 125, 125, 0.15);
    color: var(--pj-text-muted);
    font-size: 0.7rem;
    padding: 1px 6px;
    border-radius: 6px;
    font-weight: 800;
}

.projects-tab.active .tab-count {
    background: var(--primary-color);
    color: #ffffff;
}

/* CTA Button */
.btn-new-project {
    background: var(--pj-btn-bg);
    color: var(--pj-btn-text);
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: inherit;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--pj-btn-bg) 35%, transparent);
}

.btn-new-project:hover {
    background: var(--pj-btn-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--pj-btn-bg) 50%, transparent);
    color: var(--pj-btn-text);
}

/* ── Projects Grid ── */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

/* ── Project Card (Modern SaaS Style) ── */
.project-card {
    background: var(--pj-bg-card);
    border-radius: 20px;
    border: 1px solid var(--pj-border);
    box-shadow: var(--pj-shadow-card);
    padding: 1.4rem;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.project-card:hover {
    transform: translateY(-5px);
    border-color: var(--pj-border-hover);
    box-shadow: var(--pj-shadow-hover);
}

/* Card Header */
.pc-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1rem;
}

.pc-logo {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--pj-bg-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--pj-border);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.pc-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pc-logo-letter {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.pc-info {
    flex: 1;
    min-width: 0;
}

.pc-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 3px;
}

.pc-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--pj-text-title);
    margin: 0;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
}

.pc-service {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--pj-text-muted);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pc-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}

.pc-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
[data-theme="dark"] .pc-badge.active {
    color: #34d399;
}

.pc-badge.active::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}

.pc-badge.archived {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
[data-theme="dark"] .pc-badge.archived {
    color: #fbbf24;
}

.pc-badge.archived::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #f59e0b;
}

/* OS & Meta Row */
.pc-meta-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.pc-os-badge {
    background: var(--pj-bg-subtle);
    border: 1px solid var(--pj-border);
    border-radius: 8px;
    padding: 4px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--pj-text-title);
    font-weight: 700;
    font-size: 0.78rem;
}

.pc-os-badge i {
    color: var(--primary-color);
    font-size: 0.95rem;
}

.pc-stat-badge {
    background: var(--pj-bg-subtle);
    border: 1px solid var(--pj-border);
    border-radius: 8px;
    padding: 4px 8px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--pj-text-muted);
    font-weight: 600;
    font-size: 0.74rem;
}

/* Team Section */
.pc-team-section {
    background: var(--pj-bg-subtle);
    border: 1px solid var(--pj-border);
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pc-team-label {
    font-size: 0.68rem;
    color: var(--pj-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 800;
}

.pc-team-avatars {
    display: flex;
    align-items: center;
}

.pc-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 800;
    border: 2px solid var(--pj-bg-card);
    margin-left: -5px;
    position: relative;
    transition: transform 0.2s;
    cursor: default;
}

.pc-avatar:first-child { margin-left: 0; }
.pc-avatar:hover { transform: scale(1.15) translateY(-2px); z-index: 5; }

.pc-avatar-more {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--pj-border);
    color: var(--pj-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 800;
    border: 2px solid var(--pj-bg-card);
    margin-left: -5px;
}

.pc-no-team {
    font-size: 0.75rem;
    color: var(--pj-text-muted);
    font-style: italic;
}

/* Action Buttons Row */
.pc-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--pj-bg-subtle);
    border: 1px solid var(--pj-border);
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 0.85rem;
    gap: 4px;
}

.pc-action-btn {
    flex: 1;
    height: 32px;
    background: transparent;
    border: none;
    color: var(--pj-text-muted);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.pc-action-btn:hover {
    background: var(--pj-border);
    color: var(--pj-text-title);
    transform: translateY(-1px);
}

.pc-action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.pc-action-btn.archive:hover {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Enter Button */
.pc-enter-btn {
    width: 100%;
    background: var(--pj-btn-bg);
    color: var(--pj-btn-text);
    border: none;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.86rem;
    font-family: inherit;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--pj-btn-bg) 30%, transparent);
}

.pc-enter-btn i {
    transition: transform 0.2s ease;
}

.pc-enter-btn:hover {
    background: var(--pj-btn-hover);
    box-shadow: 0 6px 18px color-mix(in srgb, var(--pj-btn-bg) 45%, transparent);
    transform: translateY(-2px);
    color: var(--pj-btn-text);
}

.pc-enter-btn:hover i {
    transform: translateX(4px);
}

/* ── Empty State ── */
.projects-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: var(--pj-bg-card);
    border-radius: 20px;
    border: 2px dashed var(--pj-border);
}

.projects-empty i {
    font-size: 3.5rem;
    color: var(--pj-text-muted);
    margin-bottom: 1rem;
    display: block;
}

.projects-empty h3 {
    margin: 0 0 0.5rem;
    color: var(--pj-text-title);
    font-weight: 700;
}

.projects-empty p {
    color: var(--pj-text-muted);
    margin: 0;
}

/* ═══════════════════════════════════════════════
   MODERN MODALS (ADAPTIVE LIGHT & DARK)
   ═══════════════════════════════════════════════ */
.pj-modern-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(12px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    padding: 1.5rem;
    box-sizing: border-box;
}

.pj-modern-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.pj-modern-modal {
    background: var(--pj-bg-surface);
    border: 1px solid var(--pj-border);
    border-radius: 24px;
    width: 100%;
    max-width: 720px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0,0,0,0.25);
    transform: scale(0.95) translateY(20px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}
[data-theme="dark"] .pj-modern-modal {
    box-shadow: 0 25px 60px rgba(0,0,0,0.6);
}

.pj-modern-modal-overlay.show .pj-modern-modal {
    transform: scale(1) translateY(0);
}

.pj-modern-header {
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid var(--pj-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--pj-bg-subtle);
}

.pj-modern-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--pj-text-title);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pj-modern-close {
    background: var(--pj-bg-surface);
    border: 1px solid var(--pj-border);
    font-size: 1.1rem;
    color: var(--pj-text-muted);
    cursor: pointer;
    border-radius: 10px;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}

.pj-modern-close:hover {
    background: var(--pj-border);
    color: var(--pj-text-title);
}

.pj-modern-body {
    padding: 1.5rem 1.75rem;
    overflow-y: auto;
    flex-grow: 1;
}

.pj-modern-body::-webkit-scrollbar { width: 4px; }
.pj-modern-body::-webkit-scrollbar-thumb { background: var(--pj-border); border-radius: 4px; }

.pj-modern-footer {
    padding: 1.1rem 1.75rem;
    border-top: 1px solid var(--pj-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: var(--pj-bg-subtle);
}

/* Modal Form Controls */
.pj-modern-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.pj-modern-group {
    margin-bottom: 1.25rem;
}

.pj-modern-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--pj-text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pj-modern-group input[type="text"],
.pj-modern-group select,
.pj-modern-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--pj-border);
    border-radius: 12px;
    font-size: 0.88rem;
    font-family: inherit;
    background: var(--pj-bg-subtle);
    color: var(--pj-text-title);
    transition: all 0.2s;
    box-sizing: border-box;
}

.pj-modern-group input:focus,
.pj-modern-group select:focus,
.pj-modern-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--pj-bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.pj-modern-group textarea {
    resize: vertical;
    min-height: 80px;
}

.pj-modern-team-box {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 6px;
    max-height: 160px;
    overflow-y: auto;
    padding: 10px;
    background: var(--pj-bg-subtle);
    border-radius: 12px;
    border: 1px solid var(--pj-border);
}

.pj-modern-team-box label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: none;
    color: var(--pj-text-title);
    margin: 0;
    cursor: pointer;
    padding: 5px 8px;
    border-radius: 8px;
    transition: background 0.15s;
}

.pj-modern-team-box label:hover {
    background: var(--pj-border);
}

.pj-modern-file-wrapper {
    position: relative;
    border: 2px dashed var(--pj-border);
    border-radius: 14px;
    padding: 1.25rem;
    text-align: center;
    background: var(--pj-bg-subtle);
    transition: border-color 0.2s, background 0.2s;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.pj-modern-file-wrapper:hover {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, transparent);
}

.pj-modern-file-wrapper input[type="file"] {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    opacity: 0; cursor: pointer;
}

.pj-modern-file-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    pointer-events: none;
}

.pj-modern-file-content i {
    font-size: 1.8rem;
    color: var(--pj-text-muted);
}

.pj-modern-file-content span {
    font-size: 0.85rem;
    color: var(--pj-text-title);
    font-weight: 600;
}

.pj-modern-preview {
    width: 54px; height: 54px;
    border-radius: 12px;
    object-fit: cover;
    border: 1.5px solid var(--pj-border);
    display: none;
    z-index: 2;
}

.pj-btn-modern-cancel {
    background: var(--pj-bg-subtle);
    color: var(--pj-text-muted);
    border: 1px solid var(--pj-border);
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
}

.pj-btn-modern-cancel:hover {
    background: var(--pj-border);
    color: var(--pj-text-title);
}

.pj-btn-modern-save {
    background: var(--pj-btn-bg);
    color: var(--pj-btn-text);
    border: none;
    padding: 8px 20px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
    box-shadow: 0 2px 10px color-mix(in srgb, var(--pj-btn-bg) 30%, transparent);
}

.pj-btn-modern-save:hover {
    background: var(--pj-btn-hover);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--pj-btn-bg) 45%, transparent);
    transform: translateY(-1px);
    color: var(--pj-btn-text);
}

.pj-btn-modern-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Confirm delete modal */
.pj-confirm-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: 10001;
    align-items: center;
    justify-content: center;
}

.pj-confirm-overlay.show { display: flex; }

.pj-confirm-box {
    background: var(--pj-bg-surface);
    border: 1px solid var(--pj-border);
    border-radius: 20px;
    padding: 2rem;
    max-width: 380px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
[data-theme="dark"] .pj-confirm-box {
    box-shadow: 0 20px 50px rgba(0,0,0,0.6);
}

.pj-confirm-box i {
    font-size: 2.75rem;
    color: #ef4444;
    margin-bottom: 0.75rem;
}

.pj-confirm-box h3 {
    margin: 0 0 0.5rem;
    color: var(--pj-text-title);
    font-size: 1.15rem;
    font-weight: 800;
}

.pj-confirm-box p {
    color: var(--pj-text-muted);
    margin: 0 0 1.5rem;
    font-size: 0.85rem;
    line-height: 1.4;
}

.pj-confirm-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.pj-btn-confirm-delete {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 8px 20px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
    transition: background 0.2s;
}

.pj-btn-confirm-delete:hover { background: #dc2626; }

/* Responsive adjustments */
@media (max-width: 992px) {
    .projects-container { padding: 1rem; }
    .projects-header-card { flex-direction: column; align-items: stretch; gap: 1rem; padding: 1rem; }
    .projects-header-actions { justify-content: space-between; }
    .pj-search-box { width: 100%; }
    .pj-search-input { width: 100% !important; }
    .pj-modern-form-row { grid-template-columns: 1fr; }
    .projects-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ═══════════════════════════════════════════════ -->
<!-- PHP DATA FOR JS -->
<!-- ═══════════════════════════════════════════════ -->
<script>
    const BRANDS_DATA = <?php echo json_encode($allBrands); ?>;
</script>

<div class="projects-container">

    <!-- ── Header ── -->
    <div class="projects-header-card">
        <div class="projects-header-left">
            <div class="projects-header-icon">
                <i class="ph-bold ph-folder-notch-open"></i>
            </div>
            <div class="projects-header-titles">
                <h1>Proyectos</h1>
                <p>Gestión integral de tableros, marcas y entregas del equipo.</p>
            </div>
        </div>

        <div class="projects-header-actions">
            <!-- Live Search Filter -->
            <div class="pj-search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="projectSearchInput" class="pj-search-input" placeholder="Buscar proyecto, marca u OT..." onkeyup="filterProjectsGrid()">
            </div>

            <!-- Tabs: Activos / Archivados -->
            <div class="projects-tabs">
                <a href="index.php?module=projects&action=index&view=active" 
                   class="projects-tab <?php echo $currentView === 'active' ? 'active' : ''; ?>">
                    Activos
                    <span class="tab-count" id="countActive"><?php echo $countActive; ?></span>
                </a>
                <a href="index.php?module=projects&action=index&view=archived" 
                   class="projects-tab <?php echo $currentView === 'archived' ? 'active' : ''; ?>">
                    Archivados
                    <span class="tab-count" id="countArchived"><?php echo $countArchived; ?></span>
                </a>
            </div>

            <!-- New Project Button -->
            <button class="btn-new-project" onclick="openModernModal()">
                <i class="ph-bold ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- ── Grid ── -->
    <div class="projects-grid" id="projectsGrid">
        <?php if (empty($projects)): ?>
            <div class="projects-empty">
                <i class="ph ph-folder-open"></i>
                <h3>No hay proyectos <?php echo $currentView === 'archived' ? 'archivados' : 'activos'; ?></h3>
                <p><?php echo $currentView === 'archived' ? 'Los proyectos que archives aparecerán aquí.' : 'Haz clic en "+ Nuevo Proyecto" para empezar a organizar tu equipo.'; ?></p>
            </div>
        <?php else: ?>
            <?php foreach($projects as $p): ?>
            <?php
                // Build team data
                $teamIds = json_decode($p['team_members'], true) ?: [];
                $teamAvatars = [];
                $avatarColors = ['#10b981','#0ea5e9','#ef4444','#8b5cf6','#f59e0b','#ec4899','#14b8a6','#6366f1'];
                foreach ($teamIds as $idx => $uid) {
                    if (isset($usersMap[$uid])) {
                        $name = trim($usersMap[$uid]['name']);
                        $parts = explode(' ', $name);
                        $initial = strtoupper(substr($parts[0], 0, 1));
                        $color = $avatarColors[$idx % count($avatarColors)];
                        $teamAvatars[] = ['initial' => $initial, 'color' => $color, 'name' => $name];
                    }
                }

                // Logo resolution: custom logo > brand logo > letter fallback
                $logoPath = $p['logo'];
                if (empty($logoPath) && !empty($p['brand_logo'])) {
                    $logoPath = $p['brand_logo'];
                }
                $hasImage = !empty($logoPath) && file_exists($logoPath);
                $firstLetter = strtoupper(substr(trim($p['name']), 0, 1));
                $letterBg = $avatarColors[ord($firstLetter) % count($avatarColors)];
            ?>
            <div class="project-card" data-id="<?php echo $p['id']; ?>" data-search="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . ($p['brand_name'] ?? '') . ' ' . ($p['client_name'] ?? '') . ' ' . ($p['service_name'] ?? '') . ' ' . ($p['os_correlativo'] ?? ''))); ?>">
                
                <!-- Card Header -->
                <div class="pc-header">
                    <div class="pc-logo">
                        <?php if ($hasImage): ?>
                            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
                        <?php else: ?>
                            <div class="pc-logo-letter" style="background:<?php echo $letterBg; ?>">
                                <?php echo $firstLetter; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pc-info">
                        <div class="pc-title-row">
                            <h3 class="pc-title" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></h3>
                            <span class="pc-badge <?php echo $p['status']; ?>">
                                <?php echo $p['status'] === 'active' ? 'ACTIVO' : 'ARCHIVADO'; ?>
                            </span>
                        </div>
                        <p class="pc-service"><?php echo htmlspecialchars($p['service_name'] ?? ($p['brand_name'] ?? 'Servicio General')); ?></p>
                    </div>
                </div>

                <!-- Meta Row (OS / Correlativo / Drive) -->
                <div class="pc-meta-row">
                    <?php if (!empty($p['os_correlativo'])): ?>
                        <div class="pc-os-badge" title="Orden de Servicio">
                            <i class="ph-bold ph-clipboard-text"></i>
                            <span>OS: <strong><?php echo htmlspecialchars($p['os_correlativo']); ?></strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($p['services_count']) && $p['services_count'] > 0): ?>
                        <div class="pc-stat-badge" title="Servicios asignados">
                            <i class="ph ph-briefcase" style="color: #60a5fa;"></i>
                            <span><?php echo $p['services_count']; ?> serv.</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($p['drive_folder_link'])): ?>
                        <div class="pc-stat-badge" title="Google Drive conectado">
                            <i class="ph ph-google-drive-logo" style="color: #34d399;"></i>
                            <span>Drive</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Team Section -->
                <div class="pc-team-section">
                    <div class="pc-team-label">Equipo Asignado</div>
                    <?php if (!empty($teamAvatars)): ?>
                        <div class="pc-team-avatars">
                            <?php 
                            $visibleAvatars = array_slice($teamAvatars, 0, 4);
                            $extraCount = count($teamAvatars) - 4;
                            foreach($visibleAvatars as $av): 
                            ?>
                                <div class="pc-avatar" style="background:<?php echo $av['color']; ?>" title="<?php echo htmlspecialchars($av['name']); ?>">
                                    <?php echo $av['initial']; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($extraCount > 0): ?>
                                <div class="pc-avatar-more" title="+<?php echo $extraCount; ?> más">+<?php echo $extraCount; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="pc-no-team">Sin equipo asignado</span>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons Capsule Bar -->
                <div class="pc-actions-bar">
                    <button class="pc-action-btn" title="Ver Orden de Servicio" onclick="viewOS(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['os_correlativo'] ?? ''); ?>')">
                        <i class="ph ph-file-text"></i>
                    </button>
                    <button class="pc-action-btn" title="Editar Proyecto" onclick='editModernProject(<?php echo json_encode($p); ?>)'>
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button class="pc-action-btn archive" title="<?php echo $p['status'] === 'active' ? 'Archivar Proyecto' : 'Restaurar Proyecto'; ?>" onclick="archiveProject(<?php echo $p['id']; ?>)">
                        <i class="ph ph-archive"></i>
                    </button>
                    <button class="pc-action-btn danger" title="Eliminar Proyecto" onclick="confirmDeleteProject(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>')">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>

                <!-- Enter / Open Project Button -->
                <button class="pc-enter-btn" onclick="window.location.href='index.php?module=projects&action=view&id=<?php echo $p['id']; ?>'">
                    <span>Entrar al Tablero</span>
                    <i class="ph-bold ph-arrow-right"></i>
                </button>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODERN MODAL: Crear / Editar Proyecto -->
<!-- ═══════════════════════════════════════════════ -->
<div class="pj-modern-modal-overlay" id="modernProjectModal">
    <div class="pj-modern-modal">
        <div class="pj-modern-header">
            <h2 id="modernModalTitle"><i class="ph-bold ph-folder-plus" style="color: var(--primary-color);"></i> Nuevo Proyecto</h2>
            <button class="pj-modern-close" onclick="closeModernModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="pj-modern-body">
            <form id="modernProjectForm" enctype="multipart/form-data">
                <input type="hidden" name="project_id" id="modPjId">

                <div class="pj-modern-group">
                    <label>Nombre del Proyecto *</label>
                    <input type="text" name="name" id="modPjName" required placeholder="Ej: Campaña Publicitaria 2026">
                </div>

                <div class="pj-modern-group">
                    <label>Logo / Imagen Representativa</label>
                    <div class="pj-modern-file-wrapper">
                        <input type="file" name="logo" id="modPjLogo" accept="image/*">
                        <img id="modLogoPreview" class="pj-modern-preview" src="" alt="Preview">
                        <div class="pj-modern-file-content" id="modLogoContent">
                            <i class="ph ph-upload-simple"></i>
                            <span>Haz clic o arrastra una imagen aquí</span>
                            <small style="color:var(--pj-text-muted);font-weight:500;">PNG, JPG, SVG (Máx. 2MB)</small>
                        </div>
                    </div>
                </div>

                <div class="pj-modern-form-row">
                    <div class="pj-modern-group">
                        <label>Cliente</label>
                        <select name="client_id" id="modPjClient" onchange="filterModernBrands()">
                            <option value="">— Seleccionar Cliente —</option>
                            <?php foreach($allClients as $cl): ?>
                                <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pj-modern-group">
                        <label>Marca</label>
                        <select name="brand_id" id="modPjBrand">
                            <option value="">— Seleccionar Marca —</option>
                            <?php foreach($allBrands as $br): ?>
                                <option value="<?php echo $br['id']; ?>" data-client="<?php echo $br['client_id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Hidden fields for compatibility -->
                <input type="hidden" name="service_id" id="modPjService" value="">
                <input type="hidden" name="os_correlativo" id="modPjOS" value="">

                <?php if (!empty($driveFolders)): ?>
                <div class="pj-modern-group" style="background: #18181a; padding: 14px; border-radius: 12px; border: 1px dashed var(--pj-border);">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; text-transform:none; font-size:0.9rem; color: #f8fafc;">
                        <input type="checkbox" name="create_drive_folder" id="modPjCreateDrive" value="1" onchange="document.getElementById('modPjDriveFolderRow').style.display = this.checked ? 'block' : 'none';" style="width:auto; margin:0;">
                        <span><img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Google_Drive_logo.png" width="16" style="vertical-align:middle;margin-right:4px;"> Crear carpeta dedicada en Google Drive</span>
                    </label>
                    <div id="modPjDriveFolderRow" style="display:none; margin-top: 10px;">
                        <label style="font-size:0.75rem;">Seleccionar Carpeta Padre</label>
                        <select name="drive_parent_id" id="modPjDriveParent">
                            <?php foreach($driveFolders as $df): ?>
                                <option value="<?php echo $df['id']; ?>"><?php echo htmlspecialchars($df['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pj-modern-group">
                    <label>Equipo Asignado</label>
                    <div class="pj-modern-team-box">
                        <?php foreach($allUsers as $u): ?>
                            <label>
                                <input type="checkbox" name="team_members[]" value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pj-modern-group">
                    <label>Descripción / Objetivos</label>
                    <textarea name="description" id="modPjDescription" placeholder="Breve descripción, objetivos o notas del proyecto..."></textarea>
                </div>
            </form>
        </div>
        <div class="pj-modern-footer">
            <button class="pj-btn-modern-cancel" onclick="closeModernModal()">Cancelar</button>
            <button class="pj-btn-modern-save" id="btnModernSave" onclick="saveModernProject()">
                <i class="ph-bold ph-check"></i> Guardar Proyecto
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Confirmar Eliminación -->
<!-- ═══════════════════════════════════════════════ -->
<div class="pj-confirm-overlay" id="confirmDeleteModal">
    <div class="pj-confirm-box">
        <i class="ph-bold ph-warning-circle"></i>
        <h3>¿Eliminar proyecto?</h3>
        <p id="confirmDeleteText">Esta acción no se puede deshacer.</p>
        <div class="pj-confirm-actions">
            <button class="pj-btn-modern-cancel" onclick="closeConfirmDelete()">Cancelar</button>
            <button class="pj-btn-confirm-delete" id="btnConfirmDelete">Sí, Eliminar</button>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════
// PROJECTS MODULE JS
// ═══════════════════════════════════════════════

// Real-time live search filter
function filterProjectsGrid() {
    const query = (document.getElementById('projectSearchInput').value || '').trim().toLowerCase();
    const cards = document.querySelectorAll('#projectsGrid .project-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search') || '';
        if (!query || searchText.includes(query)) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
}

function openModernModal() {
    document.getElementById('modernProjectForm').reset();
    document.getElementById('modPjId').value = '';
    document.getElementById('modernModalTitle').innerHTML = '<i class="ph-bold ph-folder-plus" style="color: var(--primary-color);"></i> Nuevo Proyecto';
    
    // Reset file preview
    document.getElementById('modLogoPreview').style.display = 'none';
    document.getElementById('modLogoContent').style.display = 'flex';
    document.getElementById('modLogoPreview').src = '';
    
    // Reset team checkboxes
    document.querySelectorAll('.pj-modern-team-box input[type=checkbox]').forEach(cb => cb.checked = false);
    
    // Reset brands
    document.querySelectorAll('#modPjBrand option[data-client]').forEach(opt => opt.style.display = '');

    document.getElementById('modernProjectModal').classList.add('show');
}

function closeModernModal() {
    document.getElementById('modernProjectModal').classList.remove('show');
}

function editModernProject(data) {
    openModernModal(); // resets everything
    document.getElementById('modernModalTitle').innerHTML = '<i class="ph-bold ph-pencil-simple" style="color: var(--primary-color);"></i> Editar Proyecto';
    
    document.getElementById('modPjId').value = data.id;
    document.getElementById('modPjName').value = data.name || '';
    document.getElementById('modPjClient').value = data.client_id || '';
    filterModernBrands();
    document.getElementById('modPjBrand').value = data.brand_id || '';
    document.getElementById('modPjService').value = data.service_id || '';
    document.getElementById('modPjOS').value = data.os_correlativo || '';
    document.getElementById('modPjDescription').value = data.description || '';

    // Check team members
    let teamIds = [];
    try { teamIds = JSON.parse(data.team_members) || []; } catch(e) {}
    teamIds.forEach(id => {
        const cb = document.querySelector(`.pj-modern-team-box input[value="${id}"]`);
        if (cb) cb.checked = true;
    });

    // Handle logo preview
    const logo = data.logo || data.brand_logo;
    if (logo) {
        document.getElementById('modLogoPreview').src = logo;
        document.getElementById('modLogoPreview').style.display = 'block';
        document.getElementById('modLogoContent').style.display = 'none';
    }
}

function filterModernBrands() {
    const clientId = document.getElementById('modPjClient').value;
    const brandSelect = document.getElementById('modPjBrand');
    
    brandSelect.querySelectorAll('option[data-client]').forEach(opt => {
        if (!clientId || opt.dataset.client === clientId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    const currentBrand = brandSelect.options[brandSelect.selectedIndex];
    if (currentBrand && currentBrand.dataset.client && currentBrand.dataset.client !== clientId) {
        brandSelect.value = '';
    }
}

// Logo file preview update
document.getElementById('modPjLogo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('modLogoPreview').src = ev.target.result;
            document.getElementById('modLogoPreview').style.display = 'block';
            document.getElementById('modLogoContent').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});

async function saveModernProject() {
    const btn = document.getElementById('btnModernSave');
    const form = document.getElementById('modernProjectForm');
    
    const name = document.getElementById('modPjName').value.trim();
    if (!name) {
        alert('El nombre del proyecto es obligatorio.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

    const formData = new FormData(form);
    
    // JSON encode team members
    const teamChecked = [...document.querySelectorAll('.pj-modern-team-box input:checked')].map(cb => cb.value);
    formData.delete('team_members[]');
    formData.append('team_members', JSON.stringify(teamChecked));

    try {
        const resp = await fetch('index.php?module=projects&action=ajax_save_project', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        
        if (data.success) {
            closeModernModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error al guardar el proyecto.');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Guardar Proyecto';
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-bold ph-check"></i> Guardar Proyecto';
    }
}

function confirmDeleteProject(id, name) {
    document.getElementById('confirmDeleteText').textContent = `¿Estás seguro de eliminar "${name}"? Esta acción no se puede deshacer.`;
    document.getElementById('confirmDeleteModal').classList.add('show');
    
    document.getElementById('btnConfirmDelete').onclick = function() {
        deleteProject(id);
    };
}

function closeConfirmDelete() {
    document.getElementById('confirmDeleteModal').classList.remove('show');
}

async function deleteProject(id) {
    try {
        const resp = await fetch('index.php?module=projects&action=ajax_delete_project', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        });
        const data = await resp.json();
        
        if (data.success) {
            closeConfirmDelete();
            // Animate card removal
            const card = document.querySelector(`.project-card[data-id="${id}"]`);
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => window.location.reload(), 350);
            } else {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Error al eliminar.');
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
    }
}

async function archiveProject(id) {
    try {
        const resp = await fetch('index.php?module=projects&action=ajax_archive_project', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        });
        const data = await resp.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al cambiar estado.');
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
    }
}

function viewOS(id, correlativo) {
    if (correlativo) {
        alert('Orden de Servicio: ' + correlativo);
    } else {
        alert('Este proyecto no tiene OS asignada.');
    }
}

document.getElementById('confirmDeleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmDelete();
});

document.getElementById('modernProjectModal').addEventListener('click', function(e) {
    if (e.target === this) closeModernModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModernModal();
        closeConfirmDelete();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>


