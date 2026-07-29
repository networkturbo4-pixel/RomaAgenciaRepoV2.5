<?php
// modules/projects/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// ── Current view (active / archived) ──
$currentView = isset($_GET['view']) && $_GET['view'] === 'archived' ? 'archived' : 'active';

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
           cb.logo AS brand_logo
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
   PROJECTS MODULE STYLES
   ═══════════════════════════════════════════════ */
.projects-container {
    padding: 1.5rem 2rem;
    font-family: var(--font-main, 'Inter'), sans-serif;
}

/* ── Header Bar ── */
@keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.projects-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem; 
    padding: 1rem 1.5rem; 
    border-radius: 12px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 4%, transparent), color-mix(in srgb, var(--secondary-color, #10b981) 3%, transparent));
    flex-wrap: wrap;
    gap: 1rem;
}
.projects-header h1 {
    font-size: 1.8rem; font-weight: 700; color: var(--color-title, #0f172a); margin: 0;
    display: flex; align-items: center; gap: 0.5rem; animation: fadeInUp 0.4s ease-out;
}
.projects-header p {
    color: var(--text-muted, #64748b); margin: 0.25rem 0 0 0; font-size: 0.9rem; animation: fadeInUp 0.5s ease-out;
}

.projects-tabs {
    display: flex;
    background: var(--bg-sidebar, #f1f5f9);
    border-radius: 9999px;
    padding: 4px;
    border: 1px solid var(--border-color, #e2e8f0);
}

.projects-tab {
    padding: 8px 24px;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    transition: all 0.25s ease;
    border: none;
    background: transparent;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.projects-tab:hover {
    color: var(--text-main, #0f172a);
}

.projects-tab.active {
    background: var(--bg-surface, #ffffff);
    color: var(--primary-color, #22c55e);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.projects-tab .tab-count {
    background: var(--border-color, #e2e8f0);
    color: var(--text-muted, #64748b);
    font-size: 0.7rem;
    padding: 2px 7px;
    border-radius: 9999px;
    font-weight: 700;
}

.projects-tab.active .tab-count {
    background: var(--primary-color, #22c55e);
    color: #fff;
}

.btn-new-project {
    background: var(--primary-color, #22c55e);
    color: #ffffff;
    border: none;
    padding: 10px 22px;
    border-radius: var(--radius-md, 8px);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-family: inherit;
    box-shadow: 0 2px 8px rgba(34,197,94,0.25);
}

.btn-new-project:hover {
    background: var(--primary-hover, #16a34a);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34,197,94,0.35);
}

/* ── Projects Grid ── */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

/* ── Project Card ── */
.project-card {
    background: var(--bg-surface, #ffffff);
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
}

.project-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}

/* Card Header */
.pc-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.pc-logo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-sidebar, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--border-color, #e2e8f0);
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
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.pc-info { flex: 1; min-width: 0; }

.pc-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    margin: 0 0 4px 0;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pc-service {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
    margin: 0 0 8px 0;
}

.pc-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pc-badge.active {
    background: #22c55e;
    color: #ffffff;
}

.pc-badge.archived {
    background: #f59e0b;
    color: #ffffff;
}

/* OS Row */
.pc-os {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 12px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1.25rem;
    color: #059669;
    font-weight: 600;
    font-size: 0.85rem;
}

.pc-os i {
    color: #059669;
    font-size: 1.1rem;
}

/* Team Section */
.pc-team-section { margin-bottom: 1.25rem; }

.pc-team-label {
    font-size: 0.7rem;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 800;
    margin-bottom: 8px;
}

.pc-team-avatars { display: flex; }

.pc-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    border: 2px solid var(--bg-surface, #fff);
    margin-left: -6px;
    position: relative;
}

.pc-avatar:first-child { margin-left: 0; }

.pc-no-team {
    font-size: 0.8rem;
    color: var(--text-muted, #94a3b8);
    font-style: italic;
}

/* Action Buttons Row */
.pc-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 1.25rem;
    margin-top: auto;
    justify-content: center;
}

.pc-action-btn {
    flex: none;
    width: 38px;
    height: 38px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.pc-action-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
    transform: scale(1.1);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.pc-action-btn.danger:hover {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #dc2626;
}

.pc-action-btn.archive:hover {
    border-color: #fcd34d;
    background: #fffbeb;
    color: #d97706;
}

/* Enter Button */
.pc-enter-btn {
    width: 100%;
    background: var(--primary-color, #22c55e);
    color: #ffffff;
    border: none;
    padding: 12px;
    border-radius: 9999px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    font-size: 0.9rem;
    font-family: inherit;
}

.pc-enter-btn:hover {
    background: var(--primary-hover, #16a34a);
    box-shadow: 0 6px 16px rgba(34,197,94,0.3);
    transform: translateY(-2px);
}

/* ── Empty State ── */
.projects-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-surface, #fff);
    border-radius: var(--radius-lg, 16px);
    border: 2px dashed var(--border-color, #cbd5e1);
}

.projects-empty i {
    font-size: 3.5rem;
    color: var(--text-muted, #94a3b8);
    margin-bottom: 1rem;
    display: block;
}

.projects-empty h3 {
    margin: 0 0 0.5rem;
    color: var(--text-main, #334155);
    font-weight: 700;
}

.projects-empty p {
    color: var(--text-muted, #64748b);
    margin: 0;
}

/* ── Modal Styles ── */
.pj-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
    animation: pjFadeIn 0.2s ease;
}

.pj-modal-overlay.show { display: flex; }

@keyframes pjFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pjSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.pj-modal {
    background: var(--bg-surface, #ffffff);
    border-radius: 20px;
    width: 95%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: pjSlideUp 0.3s ease;
    overflow: hidden;
}

.pj-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.pj-modal-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
}

.pj-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted, #64748b);
    padding: 4px;
    border-radius: 8px;
    transition: background 0.2s;
    display: flex;
    align-items: center;
}

.pj-modal-close:hover {
    background: var(--bg-sidebar, #f1f5f9);
}

.pj-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.pj-form-group {
    margin-bottom: 1.25rem;
}

.pj-form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-main, #0f172a);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.pj-form-group input,
.pj-form-group select,
.pj-form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: var(--radius-md, 8px);
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text-main, #0f172a);
    background: var(--bg-surface, #fff);
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.pj-form-group input:focus,
.pj-form-group select:focus,
.pj-form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color, #22c55e);
    box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
}

.pj-form-group textarea { resize: vertical; min-height: 80px; }

.pj-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.pj-team-checkboxes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    max-height: 160px;
    overflow-y: auto;
    padding: 8px;
    background: var(--bg-sidebar, #f8fafc);
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--border-color, #e2e8f0);
}

.pj-team-checkboxes label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 6px;
    transition: background 0.15s;
}

.pj-team-checkboxes label:hover {
    background: var(--border-color, #e2e8f0);
}

.pj-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
}

.pj-btn-cancel {
    background: var(--bg-sidebar, #f1f5f9);
    color: var(--text-main, #334155);
    border: none;
    padding: 10px 20px;
    border-radius: 9999px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.85rem;
    transition: background 0.2s;
}

.pj-btn-cancel:hover { background: var(--border-color, #e2e8f0); }

.pj-btn-save {
    background: var(--primary-color, #22c55e);
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 9999px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.pj-btn-save:hover {
    background: var(--primary-hover, #16a34a);
}

.pj-btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Logo preview */
.pj-logo-preview {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 8px;
}

.pj-logo-preview img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color, #e2e8f0);
}

.pj-logo-preview .pj-logo-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--bg-sidebar, #f1f5f9);
    border: 2px dashed var(--border-color, #cbd5e1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted, #94a3b8);
    font-size: 1.2rem;
}

/* Confirm delete modal */
.pj-confirm-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.pj-confirm-overlay.show { display: flex; }

.pj-confirm-box {
    background: var(--bg-surface, #fff);
    border-radius: 16px;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    text-align: center;
    animation: pjSlideUp 0.3s ease;
}

.pj-confirm-box i {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
}

.pj-confirm-box h3 {
    margin: 0 0 0.5rem;
    color: var(--text-main, #0f172a);
}

.pj-confirm-box p {
    color: var(--text-muted, #64748b);
    margin: 0 0 1.5rem;
    font-size: 0.9rem;
}

.pj-confirm-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.pj-btn-confirm-delete {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 9999px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}

.pj-btn-confirm-delete:hover { background: #dc2626; }

/* Responsive */
@media (max-width: 768px) {
    .projects-container { padding: 1rem; }
    .projects-grid { grid-template-columns: 1fr; }
    .pj-form-row { grid-template-columns: 1fr; }
    .pj-modal { width: 100%; margin: 0; border-radius: 20px 20px 0 0; max-height: 95vh; align-self: flex-end; }
}
/* ==== MODERN MODAL STYLES ==== */
.pj-modern-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.pj-modern-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}
.pj-modern-modal {
    background: #ffffff;
    border-radius: 24px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    transform: scale(0.95) translateY(20px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}
.pj-modern-modal-overlay.show .pj-modern-modal {
    transform: scale(1) translateY(0);
}
.pj-modern-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}
.pj-modern-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
}
.pj-modern-close {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    border-radius: 50%;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s, color 0.2s;
}
.pj-modern-close:hover {
    background: #e2e8f0;
    color: var(--text-main, #0f172a);
}
.pj-modern-body {
    padding: 2rem;
    overflow-y: auto;
    flex-grow: 1;
}
.pj-modern-body::-webkit-scrollbar { width: 6px; }
.pj-modern-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
.pj-modern-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: #ffffff;
}

/* Form Styles Override for Modal */
.pj-modern-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
.pj-modern-group {
    margin-bottom: 1.5rem;
}
.pj-modern-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-main, #334155);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pj-modern-group input[type="text"],
.pj-modern-group select,
.pj-modern-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    font-size: 0.95rem;
    font-family: inherit;
    background: #f8fafc;
    transition: all 0.2s;
    box-sizing: border-box;
}
.pj-modern-group input:focus,
.pj-modern-group select:focus,
.pj-modern-group textarea:focus {
    outline: none;
    border-color: var(--primary-color, #22c55e);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
}
.pj-modern-group textarea {
    resize: vertical;
    min-height: 100px;
}
.pj-modern-team-box {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
    max-height: 160px;
    overflow-y: auto;
    padding: 12px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid var(--border-color, #cbd5e1);
}
.pj-modern-team-box label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: none;
    margin: 0;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: background 0.15s;
}
.pj-modern-team-box label:hover {
    background: #e2e8f0;
}
.pj-modern-file-wrapper {
    position: relative;
    border: 2px dashed var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    background: #f8fafc;
    transition: border-color 0.2s, background 0.2s;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}
.pj-modern-file-wrapper:hover {
    border-color: var(--primary-color, #22c55e);
    background: rgba(34,197,94,0.05);
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
    font-size: 2rem;
    color: var(--text-muted, #94a3b8);
}
.pj-modern-file-content span {
    font-size: 0.9rem;
    color: var(--text-main, #475569);
    font-weight: 500;
}
.pj-modern-preview {
    width: 64px; height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
    display: none;
    z-index: 2;
}
.pj-btn-modern-cancel {
    background: #f1f5f9;
    color: #475569;
    border: none;
    padding: 10px 20px;
    border-radius: 999px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    font-family: inherit;
}
.pj-btn-modern-cancel:hover { background: #e2e8f0; }
.pj-btn-modern-save {
    background: var(--primary-color, #22c55e);
    color: #ffffff;
    border: none;
    padding: 10px 24px;
    border-radius: 999px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    font-family: inherit;
}
.pj-btn-modern-save:hover {
    background: var(--primary-hover, #16a34a);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34,197,94,0.3);
}
.pj-btn-modern-save:disabled {
    opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none;
}
@media (max-width: 768px) {
    .pj-modern-form-row { grid-template-columns: 1fr; }
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
    <div class="projects-header">
        <div>
            <h1><i class="ph ph-folder" style="color: var(--primary-color);"></i> Proyectos</h1>
            <p>Gestiona todos los proyectos activos y archivados del equipo.</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div class="projects-tabs">
                <a href="index.php?module=projects&action=index&view=active" 
                   class="projects-tab <?php echo $currentView === 'active' ? 'active' : ''; ?>">
                    Activos
                    <span class="tab-count" id="countActive"><?php echo $currentView === 'active' ? count($projects) : ''; ?></span>
                </a>
                <a href="index.php?module=projects&action=index&view=archived" 
                   class="projects-tab <?php echo $currentView === 'archived' ? 'active' : ''; ?>">
                    Archivados
                    <span class="tab-count" id="countArchived"><?php echo $currentView === 'archived' ? count($projects) : ''; ?></span>
                </a>
            </div>
            <button class="btn-new-project" onclick="openModernModal()" style="padding: 8px 16px;">
                <i class="ph ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- ── Grid ── -->
    <div class="projects-grid" id="projectsGrid">
        <?php if (empty($projects)): ?>
            <div class="projects-empty">
                <i class="ph ph-folder-open"></i>
                <h3>No hay proyectos <?php echo $currentView === 'archived' ? 'archivados' : 'activos'; ?></h3>
                <p><?php echo $currentView === 'archived' ? 'Los proyectos que archives aparecerán aquí.' : 'Haz clic en "Nuevo Proyecto" para empezar.'; ?></p>
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
            <div class="project-card" data-id="<?php echo $p['id']; ?>">
                
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
                        <h3 class="pc-title" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></h3>
                        <p class="pc-service"><?php echo htmlspecialchars($p['service_name'] ?? 'Sin servicio'); ?></p>
                        <span class="pc-badge <?php echo $p['status']; ?>">
                            <?php echo $p['status'] === 'active' ? 'ACTIVO' : 'ARCHIVADO'; ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($p['os_correlativo'])): ?>
                <div class="pc-os">
                    <i class="ph ph-clipboard-text"></i>
                    <span>OS: <strong><?php echo htmlspecialchars($p['os_correlativo']); ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="pc-team-section">
                    <div class="pc-team-label">Equipo Asignado</div>
                    <?php if (!empty($teamAvatars)): ?>
                        <div class="pc-team-avatars">
                            <?php foreach($teamAvatars as $av): ?>
                                <div class="pc-avatar" style="background:<?php echo $av['color']; ?>" title="<?php echo htmlspecialchars($av['name']); ?>">
                                    <?php echo $av['initial']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="pc-no-team">Sin equipo</span>
                    <?php endif; ?>
                </div>

                <div class="pc-actions">
                    <button class="pc-action-btn" title="Ver OS" onclick="viewOS(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['os_correlativo'] ?? ''); ?>')">
                        <i class="ph ph-file-text"></i>
                    </button>
                    <button class="pc-action-btn" title="Editar" onclick='editModernProject(<?php echo json_encode($p); ?>)'>
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button class="pc-action-btn archive" title="<?php echo $p['status'] === 'active' ? 'Archivar' : 'Restaurar'; ?>" onclick="archiveProject(<?php echo $p['id']; ?>)">
                        <i class="ph ph-archive"></i>
                    </button>
                    <button class="pc-action-btn danger" title="Eliminar" onclick="confirmDeleteProject(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>')">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>

                <button class="pc-enter-btn" onclick="window.location.href='index.php?module=projects&action=view&id=<?php echo $p['id']; ?>'">
                    Entrar al Proyecto
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
            <h2 id="modernModalTitle">Nuevo Proyecto</h2>
            <button class="pj-modern-close" onclick="closeModernModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="pj-modern-body">
            <form id="modernProjectForm" enctype="multipart/form-data">
                <input type="hidden" name="project_id" id="modPjId">

                <div class="pj-modern-group">
                    <label>Nombre del Proyecto *</label>
                    <input type="text" name="name" id="modPjName" required placeholder="Ej: Campaña Navidad 2026">
                </div>

                <div class="pj-modern-group">
                    <label>Logo / Imagen</label>
                    <div class="pj-modern-file-wrapper">
                        <input type="file" name="logo" id="modPjLogo" accept="image/*">
                        <img id="modLogoPreview" class="pj-modern-preview" src="" alt="Preview">
                        <div class="pj-modern-file-content" id="modLogoContent">
                            <i class="ph ph-upload-simple"></i>
                            <span>Haz clic o arrastra una imagen</span>
                            <small style="color:var(--text-muted);font-weight:400;">PNG, JPG (Máx. 2MB)</small>
                        </div>
                    </div>
                </div>

                <div class="pj-modern-form-row">
                    <div class="pj-modern-group">
                        <label>Cliente</label>
                        <select name="client_id" id="modPjClient" onchange="filterModernBrands()">
                            <option value="">— Seleccionar —</option>
                            <?php foreach($allClients as $cl): ?>
                                <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pj-modern-group">
                        <label>Marca</label>
                        <select name="brand_id" id="modPjBrand">
                            <option value="">— Seleccionar —</option>
                            <?php foreach($allBrands as $br): ?>
                                <option value="<?php echo $br['id']; ?>" data-client="<?php echo $br['client_id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Hidden fields for removed UI elements -->
                <input type="hidden" name="service_id" id="modPjService" value="">
                <input type="hidden" name="os_correlativo" id="modPjOS" value="">

                <?php if (!empty($driveFolders)): ?>
                <div class="pj-modern-group" style="background: #f1f5f9; padding: 15px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; text-transform:none; font-size:0.95rem;">
                        <input type="checkbox" name="create_drive_folder" id="modPjCreateDrive" value="1" onchange="document.getElementById('modPjDriveFolderRow').style.display = this.checked ? 'block' : 'none';" style="width:auto; margin:0;">
                        <span><img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Google_Drive_logo.png" width="16" style="vertical-align:middle;margin-right:4px;"> Crear carpeta en Google Drive</span>
                    </label>
                    <div id="modPjDriveFolderRow" style="display:none; margin-top: 10px;">
                        <label style="font-size:0.8rem;">Seleccionar ubicación (Carpeta Padre)</label>
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
                    <label>Descripción</label>
                    <textarea name="description" id="modPjDescription" placeholder="Breve descripción, objetivos o notas..."></textarea>
                </div>
            </form>
        </div>
        <div class="pj-modern-footer">
            <button class="pj-btn-modern-cancel" onclick="closeModernModal()">Cancelar</button>
            <button class="pj-btn-modern-save" id="btnModernSave" onclick="saveModernProject()">
                <i class="ph ph-check"></i> Guardar Proyecto
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Confirmar Eliminación -->
<!-- ═══════════════════════════════════════════════ -->
<div class="pj-confirm-overlay" id="confirmDeleteModal">
    <div class="pj-confirm-box">
        <i class="ph ph-warning-circle"></i>
        <h3>¿Eliminar proyecto?</h3>
        <p id="confirmDeleteText">Esta acción no se puede deshacer.</p>
        <div class="pj-confirm-actions">
            <button class="pj-btn-cancel" onclick="closeConfirmDelete()">Cancelar</button>
            <button class="pj-btn-confirm-delete" id="btnConfirmDelete">Eliminar</button>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════
// PROJECTS MODULE JS
// ═══════════════════════════════════════════════

function openModernModal() {
    document.getElementById('modernProjectForm').reset();
    document.getElementById('modPjId').value = '';
    document.getElementById('modernModalTitle').textContent = 'Nuevo Proyecto';
    
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
    document.getElementById('modernModalTitle').textContent = 'Editar Proyecto';
    
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
            btn.innerHTML = '<i class="ph ph-check"></i> Guardar Proyecto';
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-check"></i> Guardar Proyecto';
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
