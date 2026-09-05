<?php
// modules/calendar/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';

try {
    // Fetch all work orders for the dropdown
    $stmtWO = $db->query("SELECT id, correlativo, brand_name FROM work_orders ORDER BY id DESC");
    $workOrders = $stmtWO->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all users for team selection
    $stmtUsers = $db->query("SELECT id, name, email FROM users ORDER BY name ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all projects with their work order data
    $stmtProjects = $db->query("
        SELECT p.*, w.correlativo, w.brand_name, w.data, w.public_token, w.is_archived
        FROM projects p
        JOIN work_orders w ON p.work_order_id = w.id
        ORDER BY p.id DESC
    ");
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

    // Get brand logos based on brand_name
    $stmtBrands = $db->query("SELECT name, logo FROM client_brands");
    $brandLogos = [];
    while ($row = $stmtBrands->fetch(PDO::FETCH_ASSOC)) {
        $brandLogos[$row['name']] = $row['logo'];
    }

    $activeProjects = [];
    $archivedProjects = [];

    $currentUserId = $_SESSION['user_id'];
    
    // Check admin status using role_id from DB (role_id 1 = admin)
    $stmtUserRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtUserRole->execute([$currentUserId]);
    $currentRoleId = (int)$stmtUserRole->fetchColumn();
    $isAdmin = ($currentRoleId === 1);

    foreach ($projects as $proj) {
        $teamMembers = json_decode($proj['team_members'], true) ?: [];
        
        // Filter: If user is not admin, they must be in the team
        if (!$isAdmin) {
            if (!in_array((string)$currentUserId, $teamMembers) && !in_array((int)$currentUserId, $teamMembers)) {
                continue;
            }
        }

        $data = json_decode($proj['data'], true) ?: [];
        $proj['logo'] = $brandLogos[$proj['brand_name']] ?? '';
        $proj['servicio'] = $data['servicio'] ?? 'Servicio General';
        $proj['redes'] = $data['redes'] ?? '';
        
        if ($proj['status'] === 'active') {
            $activeProjects[] = $proj;
        } else {
            $archivedProjects[] = $proj;
        }
    }

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}
?>

<style>
/* ═══════════════════════════════════════════════
   APP-STYLE MODERN SAAS DASHBOARD (LIGHT & DARK DYNAMIC)
   ═══════════════════════════════════════════════ */
:root {
    --cal-bg-card: #ffffff;
    --cal-bg-surface: #ffffff;
    --cal-bg-subtle: #f8fafc;
    --cal-border: #e2e8f0;
    --cal-border-hover: color-mix(in srgb, var(--primary-color) 40%, transparent);
    --cal-text-title: var(--color-title, #0f172a);
    --cal-text-muted: var(--color-text, #64748b);
    --cal-btn-bg: var(--color-btn-bg, var(--primary-color));
    --cal-btn-hover: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
    --cal-btn-text: var(--color-btn-text, #ffffff);
    --cal-shadow-card: 0 2px 10px rgba(0, 0, 0, 0.04);
    --cal-shadow-hover: 0 12px 28px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] {
    --cal-bg-card: #161618;
    --cal-bg-surface: #141416;
    --cal-bg-subtle: #18181a;
    --cal-border: #27272a;
    --cal-border-hover: color-mix(in srgb, var(--primary-color) 40%, transparent);
    --cal-text-title: var(--color-title, #f8fafc);
    --cal-text-muted: var(--color-text, #94a3b8);
    --cal-shadow-card: 0 4px 20px rgba(0, 0, 0, 0.25);
    --cal-shadow-hover: 0 16px 36px rgba(0, 0, 0, 0.45);
}

.cal-app-container {
    padding: 1.5rem 2rem;
    font-family: var(--font-main, 'Inter'), sans-serif;
    font-size: 13px;
    max-width: 1680px;
    margin: 0 auto;
}

/* ── App Header Bar ── */
.cal-header-card {
    background: var(--cal-bg-surface);
    border: 1px solid var(--cal-border);
    border-radius: 20px;
    padding: 1.25rem 1.75rem;
    margin-bottom: 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    box-shadow: var(--cal-shadow-card);
}

.cal-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cal-header-icon {
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

.cal-header-titles h1 {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--cal-text-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: -0.02em;
}

.cal-header-titles p {
    color: var(--cal-text-muted);
    margin: 0.2rem 0 0 0;
    font-size: 0.85rem;
    font-weight: 500;
}

/* ── Header Actions ── */
.cal-header-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

/* Search Bar */
.cal-search-box {
    position: relative;
    display: flex;
    align-items: center;
}
.cal-search-box i {
    position: absolute;
    left: 12px;
    color: var(--cal-text-muted);
    font-size: 1.1rem;
    pointer-events: none;
}
.cal-search-input {
    background: var(--cal-bg-subtle);
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    padding: 0.55rem 0.85rem 0.55rem 2.2rem;
    color: var(--cal-text-title);
    font-size: 0.85rem;
    font-family: inherit;
    width: 240px;
    transition: all 0.2s ease;
}
.cal-search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--cal-bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
    width: 280px;
}

/* App Segmented Pill Control */
.cal-segmented-control {
    display: flex;
    background: var(--cal-bg-subtle);
    border-radius: 12px;
    padding: 4px;
    border: 1px solid var(--cal-border);
    gap: 4px;
}

.cal-segmented-btn {
    padding: 6px 16px;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--cal-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    background: transparent;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
}

.cal-segmented-btn:hover {
    color: var(--cal-text-title);
    background: rgba(125, 125, 125, 0.08);
}

.cal-segmented-btn.active {
    background: var(--cal-bg-card);
    color: var(--cal-text-title);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cal-segmented-btn .tab-count {
    background: rgba(125, 125, 125, 0.15);
    color: var(--cal-text-muted);
    font-size: 0.7rem;
    padding: 1px 6px;
    border-radius: 6px;
    font-weight: 800;
}

.cal-segmented-btn.active .tab-count {
    background: var(--primary-color);
    color: #ffffff;
}

/* CTA Button */
.cal-btn-primary {
    background: var(--cal-btn-bg);
    color: var(--cal-btn-text);
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
    box-shadow: 0 4px 14px color-mix(in srgb, var(--cal-btn-bg) 35%, transparent);
}

.cal-btn-primary:hover {
    background: var(--cal-btn-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--cal-btn-bg) 50%, transparent);
    color: var(--cal-btn-text);
}

/* ── App Grid ── */
.cal-projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

/* ── App Project Card (Modern App Style) ── */
.app-project-card {
    background: var(--cal-bg-card);
    border-radius: 20px;
    border: 1px solid var(--cal-border);
    box-shadow: var(--cal-shadow-card);
    padding: 1.4rem;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.app-project-card:hover {
    transform: translateY(-5px);
    border-color: var(--cal-border-hover);
    box-shadow: var(--cal-shadow-hover);
}

/* Card Header */
.apc-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1rem;
}

.apc-logo {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--cal-bg-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid var(--cal-border);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.apc-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 2px;
}

.apc-logo-letter {
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

.apc-info {
    flex: 1;
    min-width: 0;
}

.apc-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 3px;
}

.apc-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--cal-text-title);
    margin: 0;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
}

.apc-service {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--cal-text-muted);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.apc-badge {
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

.apc-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
[data-theme="dark"] .apc-badge.active {
    color: #34d399;
}

.apc-badge.active::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}

.apc-badge.archived {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
[data-theme="dark"] .apc-badge.archived {
    color: #fbbf24;
}

.apc-badge.archived::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #f59e0b;
}

/* Meta Row */
.apc-meta-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.apc-os-badge {
    background: var(--cal-bg-subtle);
    border: 1px solid var(--cal-border);
    border-radius: 8px;
    padding: 4px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--cal-text-title);
    font-weight: 700;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.apc-os-badge:hover {
    border-color: color-mix(in srgb, var(--primary-color) 50%, transparent);
    background: var(--cal-bg-card);
}

.apc-os-badge i {
    color: var(--primary-color);
    font-size: 0.95rem;
}

.apc-social-icons {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
}

/* Team Section */
.apc-team-section {
    background: var(--cal-bg-subtle);
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.apc-team-label {
    font-size: 0.68rem;
    color: var(--cal-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 800;
}

.apc-team-avatars {
    display: flex;
    align-items: center;
}

.apc-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 800;
    border: 2px solid var(--cal-bg-card);
    margin-left: -5px;
    position: relative;
    transition: transform 0.2s;
    cursor: default;
}

.apc-avatar:first-child { margin-left: 0; }
.apc-avatar:hover { transform: scale(1.15) translateY(-2px); z-index: 5; }

.apc-avatar-more {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--cal-border);
    color: var(--cal-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 800;
    border: 2px solid var(--cal-bg-card);
    margin-left: -5px;
}

.apc-no-team {
    font-size: 0.75rem;
    color: var(--cal-text-muted);
    font-style: italic;
}

/* Action Buttons Bar */
.apc-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--cal-bg-subtle);
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 0.85rem;
    gap: 4px;
}

.apc-action-btn {
    flex: 1;
    height: 32px;
    background: transparent;
    border: none;
    color: var(--cal-text-muted);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.apc-action-btn:hover {
    background: var(--cal-border);
    color: var(--cal-text-title);
    transform: translateY(-1px);
}

.apc-action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.apc-action-btn.archive:hover {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Enter Button (App Style) */
.apc-enter-btn {
    width: 100%;
    background: var(--cal-btn-bg);
    color: var(--cal-btn-text);
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
    text-decoration: none;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--cal-btn-bg) 30%, transparent);
    box-sizing: border-box;
}

.apc-enter-btn i {
    transition: transform 0.2s ease;
}

.apc-enter-btn:hover {
    background: var(--cal-btn-hover);
    box-shadow: 0 6px 18px color-mix(in srgb, var(--cal-btn-bg) 45%, transparent);
    transform: translateY(-2px);
    color: var(--cal-btn-text);
}

.apc-enter-btn:hover i {
    transform: translateX(4px);
}

.apc-enter-btn.disabled {
    background: var(--cal-border);
    color: var(--cal-text-muted);
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Empty State */
.cal-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: var(--cal-bg-card);
    border-radius: 20px;
    border: 2px dashed var(--cal-border);
}

.cal-empty-state i {
    font-size: 3.5rem;
    color: var(--cal-text-muted);
    margin-bottom: 1rem;
    display: block;
}

.cal-empty-state h3 {
    margin: 0 0 0.5rem;
    color: var(--cal-text-title);
    font-weight: 700;
}

.cal-empty-state p {
    color: var(--cal-text-muted);
    margin: 0;
}

/* ═══════════════════════════════════════════════
   APP MODALS (ADAPTIVE LIGHT & DARK)
   ═══════════════════════════════════════════════ */
.cal-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(12px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    box-sizing: border-box;
}

.cal-modal-overlay.active { display: flex; }

.cal-modal-content {
    background: var(--cal-bg-surface);
    border: 1px solid var(--cal-border);
    border-radius: 24px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0,0,0,0.25);
    overflow: hidden;
}
[data-theme="dark"] .cal-modal-content {
    box-shadow: 0 25px 60px rgba(0,0,0,0.6);
}

.cal-modal-header {
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid var(--cal-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--cal-bg-subtle);
}

.cal-modal-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--cal-text-title);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cal-modal-close {
    background: var(--cal-bg-surface);
    border: 1px solid var(--cal-border);
    font-size: 1.1rem;
    color: var(--cal-text-muted);
    cursor: pointer;
    border-radius: 10px;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}

.cal-modal-close:hover {
    background: var(--cal-border);
    color: var(--cal-text-title);
}

.cal-modal-body {
    padding: 1.5rem 1.75rem;
    overflow-y: auto;
    flex-grow: 1;
}

.cal-modal-footer {
    padding: 1.1rem 1.75rem;
    border-top: 1px solid var(--cal-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: var(--cal-bg-subtle);
}

.cal-form-group {
    margin-bottom: 1.25rem;
}

.cal-form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--cal-text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cal-form-group select,
.cal-form-group input[type="text"],
.cal-form-group input[type="url"] {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    font-size: 0.88rem;
    font-family: inherit;
    background: var(--cal-bg-subtle);
    color: var(--cal-text-title);
    transition: all 0.2s;
    box-sizing: border-box;
}

.cal-form-group select:focus,
.cal-form-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--cal-bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.cal-team-scroll {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 6px;
    max-height: 160px;
    overflow-y: auto;
    padding: 10px;
    background: var(--cal-bg-subtle);
    border-radius: 12px;
    border: 1px solid var(--cal-border);
}

.cal-team-scroll label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: none;
    color: var(--cal-text-title);
    margin: 0;
    cursor: pointer;
    padding: 5px 8px;
    border-radius: 8px;
    transition: background 0.15s;
}

.cal-team-scroll label:hover {
    background: var(--cal-border);
}

.cal-btn-cancel {
    background: var(--cal-bg-subtle);
    color: var(--cal-text-muted);
    border: 1px solid var(--cal-border);
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
}

.cal-btn-cancel:hover {
    background: var(--cal-border);
    color: var(--cal-text-title);
}

.cal-btn-submit {
    background: var(--cal-btn-bg);
    color: var(--cal-btn-text);
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
    box-shadow: 0 2px 10px color-mix(in srgb, var(--cal-btn-bg) 30%, transparent);
}

.cal-btn-submit:hover {
    background: var(--cal-btn-hover);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--cal-btn-bg) 45%, transparent);
    transform: translateY(-1px);
    color: var(--cal-btn-text);
}

@media (max-width: 992px) {
    .cal-app-container { padding: 1rem; }
    .cal-header-card { flex-direction: column; align-items: stretch; gap: 1rem; padding: 1rem; }
    .cal-header-actions { justify-content: space-between; }
    .cal-search-box { width: 100%; }
    .cal-search-input { width: 100% !important; }
    .cal-projects-grid { grid-template-columns: 1fr; }
}

    /* ===== MOBILE APP OPTIMIZATIONS ===== */
    @media (max-width: 576px) {
        .cal-app-container {
            padding: 0.5rem !important;
        }
        .cal-projects-grid {
            grid-template-columns: 1fr !important;
            gap: 0.85rem !important;
        }
        .cal-header-card {
            padding: 1.25rem 1rem !important;
            border-radius: 16px !important;
        }
        .app-project-card {
            padding: 1.25rem !important;
            border-radius: 16px !important;
        }
        .apc-metrics {
            gap: 0.75rem !important;
        }
        .apc-team {
            gap: 0.5rem !important;
        }
        .tab-count {
            margin-left: 4px !important;
        }
    }
</style>

<?php if (isset($error)): ?>
    <div style="margin: 1.5rem 2rem; padding: 1rem; border-radius: 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5;">
        <i class="ph-bold ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="cal-app-container">

    <!-- ── Header Bar ── -->
    <div class="cal-header-card">
        <div class="cal-header-left">
            <div class="cal-header-icon">
                <i class="ph-bold ph-kanban"></i>
            </div>
            <div class="cal-header-titles">
                <h1>Tableros de Trabajo</h1>
                <p>Gestión integral, monitoreo y acceso directo a tableros por marca.</p>
            </div>
        </div>

        <div class="cal-header-actions">
            <!-- Search Input -->
            <div class="cal-search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="calendarSearchInput" class="cal-search-input" placeholder="Buscar tablero, marca u OT..." onkeyup="filterCalendarProjects()">
            </div>

            <!-- Segmented Pill Switcher -->
            <div class="cal-segmented-control">
                <button class="cal-segmented-btn active" id="btn-active-projects" onclick="switchView('active')">
                    Activos
                    <span class="tab-count" id="countActive"><?php echo count($activeProjects); ?></span>
                </button>
                <button class="cal-segmented-btn" id="btn-archived-projects" onclick="switchView('archived')">
                    Archivados
                    <span class="tab-count" id="countArchived"><?php echo count($archivedProjects); ?></span>
                </button>
            </div>

            <!-- CTA Button -->
            <button class="cal-btn-primary" id="btn-new-project" onclick="openNewProjectModal()">
                <i class="ph-bold ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- ── Active Projects Grid ── -->
    <div class="cal-projects-grid" id="active-projects-container">
        <?php if (empty($activeProjects)): ?>
            <div class="cal-empty-state">
                <i class="ph ph-folder-open"></i>
                <h3>No hay proyectos activos</h3>
                <p>Haz clic en "+ Nuevo Proyecto" para asignar una Orden de Servicio al equipo.</p>
            </div>
        <?php else: ?>
            <?php foreach ($activeProjects as $project): ?>
                <?php renderProjectCard($project); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── Archived Projects Grid ── -->
    <div class="cal-projects-grid" id="archived-projects-container" style="display: none;">
        <?php if (empty($archivedProjects)): ?>
            <div class="cal-empty-state">
                <i class="ph ph-archive"></i>
                <h3>No hay proyectos archivados</h3>
                <p>Los proyectos que archives aparecerán aquí para consulta histórica.</p>
            </div>
        <?php else: ?>
            <?php foreach ($archivedProjects as $project): ?>
                <?php renderProjectCard($project); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Nuevo Proyecto -->
<!-- ═══════════════════════════════════════════════ -->
<div class="cal-modal-overlay" id="new-project-modal">
    <div class="cal-modal-content">
        <div class="cal-modal-header">
            <h2><i class="ph-bold ph-folder-plus" style="color: var(--primary-color);"></i> Nuevo Proyecto</h2>
            <button class="cal-modal-close" onclick="closeModal('new-project-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="cal-modal-body">
            <form id="new-project-form">
                <div class="cal-form-group">
                    <label>Orden de Servicio *</label>
                    <select name="work_order_id" id="work_order_select" required onchange="fetchWorkOrderData()">
                        <option value="">— Seleccionar Orden de Servicio —</option>
                        <?php foreach ($workOrders as $wo): ?>
                            <option value="<?php echo $wo['id']; ?>"><?php echo htmlspecialchars($wo['correlativo'] . ' - ' . $wo['brand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="wo-details-preview" style="display: none; background: var(--cal-bg-subtle); padding: 1.15rem; border-radius: 14px; border: 1px solid var(--cal-border); margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
                        <img id="preview-logo" src="" alt="Logo" style="width: 44px; height: 44px; object-fit: contain; border-radius: 10px; background: var(--cal-bg-surface); border: 1px solid var(--cal-border); padding: 2px;">
                        <div>
                            <div style="font-weight: 800; color: var(--cal-text-title); font-size: 0.95rem;" id="preview-brand"></div>
                            <div style="font-size: 0.8rem; color: var(--cal-text-muted);" id="preview-networks"></div>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--cal-text-muted);">
                        Fecha de Inicio: <span id="preview-date" style="font-weight: 700; color: var(--primary-color);"></span>
                    </div>
                </div>

                <div class="cal-form-group">
                    <label>Equipo Asignado</label>
                    <div class="cal-team-scroll">
                        <?php foreach ($users as $user): ?>
                            <label>
                                <input type="checkbox" name="team_members[]" value="<?php echo $user['id']; ?>">
                                <span><?php echo htmlspecialchars($user['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cal-form-group" style="background: var(--cal-bg-subtle); padding: 14px; border-radius: 12px; border: 1px dashed var(--cal-border);">
                    <label style="display: flex; align-items: center; gap: 6px; color: var(--primary-color); text-transform: none; font-size: 0.85rem; margin-bottom: 4px;">
                        <i class="ph-bold ph-google-drive-logo" style="font-size: 1.1rem;"></i> Carpeta Global en Google Drive (Opcional)
                    </label>
                    <p style="font-size: 0.75rem; color: var(--cal-text-muted); margin: 0 0 10px 0;">
                        Si se deja vacía, el sistema creará una carpeta dedicada automáticamente.
                    </p>
                    <div style="display: flex; gap: 8px;">
                        <input type="url" name="global_folder_link" id="inp-global-folder" placeholder="Enlace de la carpeta global...">
                        <input type="hidden" name="global_folder_id" id="inp-global-folder-id">
                        <button type="button" class="cal-btn-cancel" onclick="promptGlobalFolder()" style="white-space: nowrap; color: var(--primary-color); border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);">Elegir</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="cal-modal-footer">
            <button class="cal-btn-cancel" onclick="closeModal('new-project-modal')">Cancelar</button>
            <button class="cal-btn-submit" onclick="saveProject()">
                <i class="ph-bold ph-check"></i> Guardar Proyecto
            </button>
        </div>
    </div>
</div>

<?php require_once 'includes/custom_drive_picker.php'; ?>
<script>
function promptGlobalFolder() {
    cdOpenPicker(null, function(folder) {
        if (!folder.url) {
            folder.url = "https://drive.google.com/drive/folders/" + folder.id;
        }
        document.getElementById('inp-global-folder').value = folder.url;
        document.getElementById('inp-global-folder-id').value = folder.id;
    });
}
</script>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Editar Proyecto (Equipo) -->
<!-- ═══════════════════════════════════════════════ -->
<div class="cal-modal-overlay" id="edit-project-modal">
    <div class="cal-modal-content">
        <div class="cal-modal-header">
            <h2><i class="ph-bold ph-pencil-simple" style="color: var(--primary-color);"></i> Asignar Equipo</h2>
            <button class="cal-modal-close" onclick="closeModal('edit-project-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="cal-modal-body">
            <form id="edit-project-form">
                <input type="hidden" name="id" id="edit-project-id">
                <div class="cal-form-group">
                    <label>Miembros del Equipo</label>
                    <div class="cal-team-scroll" id="edit-team-members-container">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </form>
        </div>
        <div class="cal-modal-footer">
            <button class="cal-btn-cancel" onclick="closeModal('edit-project-modal')">Cancelar</button>
            <button class="cal-btn-submit" onclick="updateProject()">
                <i class="ph-bold ph-check"></i> Actualizar Equipo
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Vista Pública - Orden de Servicio -->
<!-- ═══════════════════════════════════════════════ -->
<div class="cal-modal-overlay" id="public-wo-modal">
    <div class="cal-modal-content" style="max-width: 1000px; height: 85vh;">
        <div class="cal-modal-header">
            <h2><i class="ph-bold ph-file-text" style="color: var(--primary-color);"></i> Orden de Servicio</h2>
            <button class="cal-modal-close" onclick="closeModal('public-wo-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="cal-modal-body" style="flex: 1; padding: 0; background: #0e0e10;">
            <iframe id="public-wo-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- MODAL: Confirmar Eliminación -->
<!-- ═══════════════════════════════════════════════ -->
<div class="cal-modal-overlay" id="deleteConfirmModal" style="z-index: 10070;">
    <div class="cal-modal-content" style="max-width: 380px; text-align: center;">
        <div class="cal-modal-body" style="padding: 2rem 1.5rem 1rem;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1rem;">
                <i class="ph-bold ph-trash"></i>
            </div>
            <h3 style="margin: 0 0 0.5rem; color: var(--cal-text-title); font-size: 1.15rem; font-weight: 800;">¿Eliminar proyecto?</h3>
            <p style="color: var(--cal-text-muted); margin: 0; font-size: 0.85rem; line-height: 1.4;">Esta acción quitará el tablero del proyecto y no se puede deshacer.</p>
        </div>
        <div class="cal-modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem;">
            <button type="button" class="cal-btn-cancel" onclick="closeModal('deleteConfirmModal')">Cancelar</button>
            <button type="button" class="cal-btn-submit" id="btnConfirmDelete" style="background: #ef4444; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.3); color: #fff;">
                Sí, Eliminar
            </button>
        </div>
    </div>
</div>

<script>
// Available users for JS to render in edit form
const systemUsers = <?php echo json_encode($users); ?>;
let projectToDeleteId = null;

// Live Search Filter for Cards
function filterCalendarProjects() {
    const query = (document.getElementById('calendarSearchInput').value || '').trim().toLowerCase();
    const cards = document.querySelectorAll('.cal-projects-grid .app-project-card');

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search') || '';
        if (!query || searchText.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function switchView(view) {
    const activeContainer = document.getElementById('active-projects-container');
    const archivedContainer = document.getElementById('archived-projects-container');
    const btnActive = document.getElementById('btn-active-projects');
    const btnArchived = document.getElementById('btn-archived-projects');

    if (view === 'active') {
        activeContainer.style.display = 'grid';
        archivedContainer.style.display = 'none';
        btnActive.classList.add('active');
        btnArchived.classList.remove('active');
    } else {
        activeContainer.style.display = 'none';
        archivedContainer.style.display = 'grid';
        btnArchived.classList.add('active');
        btnActive.classList.remove('active');
    }
    filterCalendarProjects();
}

function openNewProjectModal() {
    document.getElementById('new-project-form').reset();
    document.getElementById('wo-details-preview').style.display = 'none';
    document.getElementById('new-project-modal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openPublicWoModal(token) {
    document.getElementById('public-wo-iframe').src = 'index.php?module=work_orders&action=public&token=' + token;
    document.getElementById('public-wo-modal').classList.add('active');
}

async function openEditProjectModal(projectId) {
    try {
        const response = await fetch(`modules/calendar/ajax_get_project.php?id=${projectId}`);
        const data = await response.json();

        if (data.success) {
            document.getElementById('edit-project-id').value = projectId;
            
            const container = document.getElementById('edit-team-members-container');
            container.innerHTML = '';
            
            systemUsers.forEach(user => {
                const isChecked = data.team_members.includes(user.id.toString()) || data.team_members.includes(user.id) ? 'checked' : '';
                container.innerHTML += `
                    <label>
                        <input type="checkbox" name="team_members[]" value="${user.id}" ${isChecked}>
                        <span>${user.name}</span>
                    </label>
                `;
            });

            document.getElementById('edit-project-modal').classList.add('active');
        } else {
            alert('Error al cargar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function updateProject() {
    const form = document.getElementById('edit-project-form');
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/calendar/ajax_update_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function fetchWorkOrderData() {
    const woId = document.getElementById('work_order_select').value;
    const previewContainer = document.getElementById('wo-details-preview');
    if (!woId) {
        previewContainer.style.display = 'none';
        return;
    }

    try {
        const response = await fetch(`modules/calendar/ajax_get_work_order.php?id=${woId}`);
        const data = await response.json();

        if (data.success) {
            document.getElementById('preview-logo').src = data.logo || 'assets/img/default-logo.png';
            document.getElementById('preview-brand').innerText = data.brand_name;
            
            // Render networks icons if any
            let networksHtml = '';
            if (data.networks) {
                const nets = data.networks.split(',').map(n => n.trim().toLowerCase());
                nets.forEach(n => {
                    if (n.includes('facebook')) networksHtml += '<i class="ph ph-facebook-logo" style="font-size:1.1rem; color: #1877F2; margin-right:4px;"></i>';
                    else if (n.includes('instagram')) networksHtml += '<i class="ph ph-instagram-logo" style="font-size:1.1rem; color: #E4405F; margin-right:4px;"></i>';
                    else if (n.includes('tiktok')) networksHtml += '<i class="ph ph-tiktok-logo" style="font-size:1.1rem; color: #ffffff; margin-right:4px;"></i>';
                    else networksHtml += n + ' ';
                });
            }
            document.getElementById('preview-networks').innerHTML = networksHtml || 'Sin redes especificadas';
            document.getElementById('preview-date').innerText = data.start_date || 'No definida';

            previewContainer.style.display = 'block';
        } else {
            alert('Error al cargar datos de la orden de servicio.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red al cargar la orden de servicio.');
    }
}

async function saveProject() {
    const form = document.getElementById('new-project-form');
    const formData = new FormData(form);

    if (!formData.get('work_order_id')) {
        alert('Seleccione una orden de servicio.');
        return;
    }

    try {
        const response = await fetch('modules/calendar/ajax_save_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al guardar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function toggleArchive(projectId, currentStatus) {
    try {
        const newStatus = currentStatus === 'active' ? 'archived' : 'active';
        const formData = new FormData();
        formData.append('id', projectId);
        formData.append('status', newStatus);

        const response = await fetch('modules/calendar/ajax_archive_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar el estado.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

function deleteProject(projectId) {
    projectToDeleteId = projectId;
    
    // Assign event listener to confirm button
    const confirmBtn = document.getElementById('btnConfirmDelete');
    confirmBtn.onclick = async function() {
        if (!projectToDeleteId) return;
        
        try {
            const formData = new FormData();
            formData.append('id', projectToDeleteId);

            const response = await fetch('modules/calendar/ajax_delete_project.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert(result.error || 'Error al eliminar el proyecto.');
                closeModal('deleteConfirmModal');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red.');
            closeModal('deleteConfirmModal');
        }
    };
    
    document.getElementById('deleteConfirmModal').classList.add('active');
}

// Close modals on overlay click or Escape key
document.querySelectorAll('.cal-modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.cal-modal-overlay.active').forEach(m => closeModal(m.id));
    }
});
</script>

<?php
function renderProjectCard($project) {
    $statusText = $project['status'] === 'active' ? 'ACTIVO' : 'ARCHIVADO';
    $statusClass = $project['status'] === 'active' ? 'active' : 'archived';

    // Team members as overlapping avatars
    $teamMembers = json_decode($project['team_members'], true) ?: [];
    global $users;
    
    $teamAvatars = [];
    $avatarColors = ['#10b981', '#0ea5e9', '#ef4444', '#8b5cf6', '#f59e0b', '#ec4899', '#14b8a6', '#6366f1'];
    if (!empty($teamMembers)) {
        foreach ($teamMembers as $idx => $userId) {
            $userName = 'Colaborador';
            if (is_array($users)) {
                foreach ($users as $u) {
                    if ($u['id'] == $userId) {
                        $userName = trim($u['name']);
                        break;
                    }
                }
            }
            $initial = strtoupper(substr($userName, 0, 1));
            $color = $avatarColors[$userId % count($avatarColors)];
            $teamAvatars[] = ['initial' => $initial, 'color' => $color, 'name' => $userName];
        }
    }
    
    $logoUrl = $project['logo'] ? htmlspecialchars($project['logo']) : '';
    $hasImage = !empty($logoUrl) && file_exists($logoUrl);
    $firstLetter = strtoupper(substr(trim($project['brand_name']), 0, 1));
    $letterBg = $avatarColors[ord($firstLetter) % count($avatarColors)];
    
    $otCorrelativo = isset($project['correlativo']) ? htmlspecialchars($project['correlativo']) : 'No asignada';
    $publicToken = isset($project['public_token']) ? htmlspecialchars($project['public_token']) : '';
    $isArchived = isset($project['is_archived']) && $project['is_archived'] == 1;

    $searchMeta = htmlspecialchars(strtolower($project['brand_name'] . ' ' . $project['servicio'] . ' ' . $otCorrelativo));

    // Social icons
    $socialsHtml = '';
    if (!empty($project['redes'])) {
        $nets = explode(',', strtolower($project['redes']));
        foreach ($nets as $n) {
            $n = trim($n);
            if (strpos($n, 'facebook') !== false) $socialsHtml .= '<i class="ph ph-facebook-logo" style="color:#1877f2;font-size:1.05rem;" title="Facebook"></i>';
            elseif (strpos($n, 'instagram') !== false) $socialsHtml .= '<i class="ph ph-instagram-logo" style="color:#e4405f;font-size:1.05rem;" title="Instagram"></i>';
            elseif (strpos($n, 'tiktok') !== false) $socialsHtml .= '<i class="ph ph-tiktok-logo" style="color:#ffffff;font-size:1.05rem;" title="TikTok"></i>';
        }
    }
    ?>
    <div class="app-project-card" data-id="<?php echo $project['id']; ?>" data-search="<?php echo $searchMeta; ?>">
        
        <!-- Card Header -->
        <div class="apc-header">
            <div class="apc-logo">
                <?php if ($hasImage): ?>
                    <img src="<?php echo $logoUrl; ?>" alt="Logo">
                <?php else: ?>
                    <div class="apc-logo-letter" style="background:<?php echo $letterBg; ?>">
                        <?php echo $firstLetter; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="apc-info">
                <div class="apc-title-row">
                    <h3 class="apc-title" title="<?php echo htmlspecialchars($project['brand_name']); ?>">
                        <?php echo htmlspecialchars($project['brand_name']); ?>
                    </h3>
                    <span class="apc-badge <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                </div>
                <p class="apc-service"><?php echo htmlspecialchars($project['servicio']); ?></p>
            </div>
        </div>

        <!-- Meta Row (OS & Socials) -->
        <div class="apc-meta-row">
            <div class="apc-os-badge" title="Ver Orden de Servicio" onclick="openPublicWoModal('<?php echo $publicToken; ?>')">
                <i class="ph-bold ph-clipboard-text"></i>
                <span>OS: <strong><?php echo $otCorrelativo; ?></strong></span>
            </div>
            <?php if (!empty($socialsHtml)): ?>
                <div class="apc-social-icons">
                    <?php echo $socialsHtml; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Team Section -->
        <div class="apc-team-section">
            <div class="apc-team-label">Equipo Asignado</div>
            <?php if (!empty($teamAvatars)): ?>
                <div class="apc-team-avatars">
                    <?php 
                    $visibleAvatars = array_slice($teamAvatars, 0, 4);
                    $extraCount = count($teamAvatars) - 4;
                    foreach($visibleAvatars as $av): 
                    ?>
                        <div class="apc-avatar" style="background:<?php echo $av['color']; ?>" title="<?php echo htmlspecialchars($av['name']); ?>">
                            <?php echo $av['initial']; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($extraCount > 0): ?>
                        <div class="apc-avatar-more" title="+<?php echo $extraCount; ?> más">+<?php echo $extraCount; ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="apc-no-team">Sin equipo asignado</span>
            <?php endif; ?>
        </div>

        <!-- Micro Actions Capsule Bar -->
        <div class="apc-actions-bar">
            <button class="apc-action-btn" title="Ver Orden de Servicio" onclick="openPublicWoModal('<?php echo $publicToken; ?>')">
                <i class="ph ph-file-text"></i>
            </button>
            <button class="apc-action-btn" title="Asignar / Editar Equipo" onclick="openEditProjectModal(<?php echo $project['id']; ?>)">
                <i class="ph ph-pencil-simple"></i>
            </button>
            <button class="apc-action-btn archive" title="<?php echo $project['status'] === 'active' ? 'Archivar Proyecto' : 'Restaurar Proyecto'; ?>" onclick="toggleArchive(<?php echo $project['id']; ?>, '<?php echo $project['status']; ?>')">
                <i class="ph ph-archive"></i>
            </button>
            <button class="apc-action-btn danger" title="Eliminar Proyecto" onclick="deleteProject(<?php echo $project['id']; ?>)">
                <i class="ph ph-trash"></i>
            </button>
        </div>

        <!-- Enter / Action Button -->
        <?php if ($isArchived): ?>
            <button class="apc-enter-btn disabled" disabled title="La orden de servicio está archivada">
                <span>Orden Archivada</span>
                <i class="ph-bold ph-lock"></i>
            </button>
        <?php else: ?>
            <a href="index.php?module=project_board&id=<?php echo $project['id']; ?>" class="apc-enter-btn">
                <span>Entrar al Tablero</span>
                <i class="ph-bold ph-arrow-right"></i>
            </a>
        <?php endif; ?>

    </div>
    <?php
}

require_once 'includes/footer.php';
?>


