<?php
// modules/project_board/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    echo "<div class='alert alert-danger'>ID de proyecto no válido.</div>";
    require_once 'includes/footer.php';
    exit();
}

try {
    // Fetch project details
    $stmtProject = $db->prepare("
        SELECT p.*, w.correlativo, w.brand_name, w.data, w.public_token 
        FROM projects p
        JOIN work_orders w ON p.work_order_id = w.id
        WHERE p.id = ?
    ");
    $stmtProject->execute([$projectId]);
    $project = $stmtProject->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception("Proyecto no encontrado.");
    }

    $woData = json_decode($project['data'], true) ?: [];
    $servicio = $woData['servicio'] ?? 'Servicio General';
    
    // Get brand logo
    $stmtBrand = $db->prepare("SELECT logo FROM client_brands WHERE name = ?");
    $stmtBrand->execute([$project['brand_name']]);
    $brand = $stmtBrand->fetch(PDO::FETCH_ASSOC);
    $logo = $brand ? $brand['logo'] : 'assets/img/default-logo.png';

    // Fetch project months
    $filterMonth = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
    $filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';

    $query = "SELECT * FROM project_months WHERE project_id = ?";
    $params = [$projectId];

    if ($filterMonth !== '') {
        $query .= " AND month = ?";
        $params[] = (int)$filterMonth;
    }
    if ($filterYear !== '') {
        $query .= " AND year = ?";
        $params[] = (int)$filterYear;
    }
    $query .= " ORDER BY year DESC, month DESC";

    $stmtMonths = $db->prepare($query);
    $stmtMonths->execute($params);
    $months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

    // Month Names Array for display
    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

} catch (Exception $e) {
    $error = $e->getMessage();
}

?>

<style>
    /* ===== REDESIGNED PROJECT BOARD ===== */
    .project-header {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 20px;
        padding: 1.1rem 1.6rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        flex-wrap: wrap;
        position: relative;
    }
    [data-theme="dark"] .project-header {
        background: #121212;
        border: 1px solid #27272a;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7);
    }

    .project-header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }
    .btn-back-compact {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .btn-back-compact:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateX(-3px);
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    }
    [data-theme="dark"] .btn-back-compact {
        background: #181818;
        border-color: #27272a;
        color: #e4e4e7;
    }

    .project-header-info {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    .project-header-info img {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        object-fit: contain;
        background: #ffffff;
        padding: 0.35rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
    }
    [data-theme="dark"] .project-header-info img {
        background: #181818;
        border-color: #27272a;
    }
    .project-header-info h1 {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 800;
        color: var(--color-title);
        letter-spacing: -0.5px;
        line-height: 1.2;
    }
    .project-meta-row {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.25rem;
        flex-wrap: wrap;
    }
    .board-brand-name {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .board-ot-badge {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.12rem 0.45rem;
        border-radius: 6px;
    }
    .board-status-badge {
        font-size: 0.68rem;
        font-weight: 800;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-activo, .status-active, .status-finalizado, .status-terminado { background: rgba(16, 185, 129, 0.15); color: #059669; }
    .status-inactivo, .status-inactive { background: rgba(100, 116, 139, 0.15); color: #64748b; }
    .status-pendiente { background: rgba(245, 158, 11, 0.15); color: #d97706; }
    [data-theme="dark"] .status-activo, [data-theme="dark"] .status-active, [data-theme="dark"] .status-finalizado, [data-theme="dark"] .status-terminado { color: #34d399; background: rgba(16, 185, 129, 0.2); }
    [data-theme="dark"] .status-pendiente { color: #fbbf24; background: rgba(245, 158, 11, 0.2); }

    /* Actions & Filters Group */
    .project-header-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .filter-pill-container {
        display: flex;
        align-items: center;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 2px 6px;
        gap: 4px;
    }
    [data-theme="dark"] .filter-pill-container {
        background: #181818;
        border-color: #27272a;
    }
    .filter-select {
        background: transparent;
        border: none;
        color: var(--text-color);
        padding: 0.4rem 0.5rem;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        outline: none;
    }
    [data-theme="dark"] .filter-select {
        color: #f1f5f9;
    }
    [data-theme="dark"] .filter-select option {
        background: #121212;
        color: #f1f5f9;
    }

    .btn-project-info {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        padding: 0.6rem 1rem;
        border-radius: 12px;
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-project-info:hover {
        background: rgba(0,0,0,0.04);
        color: var(--color-title);
        border-color: rgba(0,0,0,0.15);
    }
    [data-theme="dark"] .btn-project-info {
        background: #181818;
        border-color: #27272a;
        color: #cbd5e1;
    }
    [data-theme="dark"] .btn-project-info:hover {
        background: #27272a;
        color: #ffffff;
    }

    .btn-create-month {
        background: var(--color-btn-bg, var(--primary-color));
        color: var(--color-btn-text, #ffffff);
        border: none;
        font-weight: 700;
        padding: 0.65rem 1.25rem;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 4px 16px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 30%, transparent);
        white-space: nowrap;
        font-size: 0.88rem;
        cursor: pointer;
    }
    .btn-create-month:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 45%, transparent);
        background: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
        color: var(--color-btn-text, #ffffff);
    }

    /* ===== MONTH CARDS GRID ===== */
    .months-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.5rem;
    }
    .mc-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 20px;
        padding: 1.4rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        position: relative;
    }
    .mc-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.08);
    }
    [data-theme="dark"] .mc-card {
        background: #141416;
        border: 1px solid #27272a;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    }
    [data-theme="dark"] .mc-card:hover {
        background: #161618;
        border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.8);
    }

    .mc-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    .mc-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--color-title);
        margin: 0;
        letter-spacing: -0.4px;
    }

    /* Cronómetro Moderno en Card de Mes */
    .modern-month-timer {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        transition: all 0.3s ease;
        font-variant-numeric: tabular-nums;
    }
    .modern-month-timer.active {
        background: rgba(56, 189, 248, 0.15);
        color: #0284c7;
        border: 1px solid rgba(56, 189, 248, 0.3);
    }
    [data-theme="dark"] .modern-month-timer.active {
        background: #181818;
        color: #38bdf8;
        border: 1px solid #27272a;
    }
    .modern-month-timer.warning {
        background: rgba(245, 158, 11, 0.9);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.3);
        animation: pulse-timer-warning 2s infinite ease-in-out;
    }
    .modern-month-timer.expired {
        background: rgba(239, 68, 68, 0.9);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .modern-month-timer.completed {
        background: rgba(16, 185, 129, 0.9);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .modern-month-timer.upcoming {
        background: rgba(99, 102, 241, 0.9);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    /* Stats Grid Tiles */
    .mc-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    .mc-stat-tile {
        background: rgba(15, 23, 42, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.2s ease;
    }
    [data-theme="dark"] .mc-stat-tile {
        background: #181818;
        border-color: #27272a;
    }
    .mc-stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .mc-stat-icon.posts { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
    .mc-stat-icon.comments { background: rgba(168, 85, 247, 0.12); color: #a855f7; }

    .mc-stat-info {
        display: flex;
        flex-direction: column;
    }
    .mc-stat-num {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--color-title);
        line-height: 1.1;
    }
    .mc-stat-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Progress Section */
    .mc-progress-section {
        background: rgba(15, 23, 42, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 0.85rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }
    [data-theme="dark"] .mc-progress-section {
        background: #181818;
        border-color: #27272a;
    }
    .mc-progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.68rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .mc-progress-pct {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
        padding: 0.1rem 0.45rem;
        border-radius: 6px;
        font-weight: 800;
    }
    [data-theme="dark"] .mc-progress-pct {
        color: #34d399;
    }
    .mc-progress-bar {
        height: 6px;
        background: rgba(0, 0, 0, 0.06);
        border-radius: 6px;
        overflow: hidden;
    }
    [data-theme="dark"] .mc-progress-bar {
        background: #27272a;
    }
    .mc-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6 0%, #10b981 100%);
        border-radius: 6px;
        transition: width 0.5s ease;
    }

    .mc-pipeline-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.4rem;
        margin-top: 0.25rem;
    }
    .mc-pipeline-pill {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0.35rem 0.2rem;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        text-align: center;
    }
    [data-theme="dark"] .mc-pipeline-pill {
        background: #121212;
        border-color: #27272a;
    }
    .mc-pipeline-num {
        font-size: 0.88rem;
        font-weight: 800;
        line-height: 1.1;
    }
    .mc-pipeline-lbl {
        font-size: 0.6rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-top: 2px;
    }

    /* Dates Row */
    .mc-dates-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.74rem;
        color: var(--text-muted);
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    .mc-date-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-weight: 600;
        color: var(--text-color);
    }
    .mc-created-date {
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    /* Footer */
    .mc-card-footer {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        border-top: 1px solid var(--border-color);
        padding-top: 0.85rem;
    }
    .mc-footer-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .mc-status-pill {
        font-size: 0.68rem;
        font-weight: 800;
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .mc-actions-btns {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .mc-action-icon-btn {
        background: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.88rem;
    }
    .mc-action-icon-btn.edit:hover {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border-color: #3b82f6;
    }
    .mc-action-icon-btn.delete:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border-color: #ef4444;
    }
    [data-theme="dark"] .mc-action-icon-btn {
        background: #181818;
        border-color: #27272a;
        color: #9ca3af;
    }
    [data-theme="dark"] .mc-action-icon-btn:hover {
        background: #27272a;
    }

    .mc-btn-enter {
        width: 100%;
        background: var(--color-btn-bg, var(--primary-color));
        color: var(--color-btn-text, #ffffff);
        border: none;
        border-radius: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.65rem 1rem;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        font-size: 0.88rem;
        box-shadow: 0 4px 14px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 25%, transparent);
    }
    .mc-btn-enter:hover {
        background: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
        color: var(--color-btn-text, #ffffff);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 40%, transparent);
    }

    @media (max-width: 992px) {
        .project-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 1.25rem;
            padding: 1.25rem;
        }
        .project-header-left {
            width: 100%;
        }
        .project-header-actions {
            width: 100%;
            justify-content: space-between;
        }
    }
    @media (max-width: 576px) {
        .project-header-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-pill-container {
            width: 100%;
            justify-content: space-between;
        }
        .filter-select {
            flex: 1;
        }
        .btn-project-info, .btn-create-month {
            width: 100%;
            justify-content: center;
        }
    }

    /* ===== MODERN APP MODAL (ADD & EDIT MONTH) ===== */
    .app-modal-card {
        background: var(--bg-surface) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 24px !important;
        max-width: 580px !important;
        width: 92% !important;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05) !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        animation: appModalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes appModalPop {
        from { opacity: 0; transform: scale(0.96) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .app-modal-header {
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-surface);
        flex-shrink: 0;
    }
    .app-modal-header-left {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    .app-modal-icon-badge {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }
    [data-theme="dark"] .app-modal-icon-badge {
        background: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.3);
    }
    .app-modal-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--color-title);
        margin: 0;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .app-modal-subtitle {
        font-size: 0.76rem;
        color: var(--text-muted);
        margin: 3px 0 0 0;
        font-weight: 500;
    }
    .btn-app-modal-close {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid var(--border-color);
        background: var(--bg-main);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.2s ease;
    }
    .btn-app-modal-close:hover {
        background: var(--border-color);
        color: var(--color-title);
        transform: rotate(90deg);
    }

    .app-modal-body {
        padding: 1.35rem 1.5rem;
        overflow-y: auto;
        max-height: 72vh;
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
    }
    .app-modal-body::-webkit-scrollbar { width: 5px; }
    .app-modal-body::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

    .app-modal-section {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.1rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    .app-modal-section-title {
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--color-title);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .app-modal-section-title i {
        font-size: 1rem;
        color: var(--primary-color, #10b981);
    }
    .app-modal-section-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin: -0.35rem 0 0.25rem 0;
        line-height: 1.45;
    }

    .app-modal-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    @media (max-width: 480px) {
        .app-modal-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    .app-field-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .app-field-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .app-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }
    .app-input-icon {
        position: absolute;
        left: 0.85rem;
        font-size: 1.05rem;
        color: var(--text-muted);
        pointer-events: none;
        z-index: 1;
    }
    .app-form-control {
        width: 100%;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.65rem 0.85rem 0.65rem 2.4rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-main);
        outline: none;
        transition: all 0.2s ease;
        font-family: inherit;
    }
    .app-form-control:focus {
        border-color: var(--primary-color, #10b981);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }
    select.app-form-control {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 256 256'%3E%3Cpath d='M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.85rem center;
        background-size: 14px;
        padding-right: 2.2rem;
        cursor: pointer;
    }

    .app-badge-drive {
        font-size: 0.65rem;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 6px;
        background: rgba(59, 130, 246, 0.12);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.25);
        margin-left: auto;
    }

    .app-btn-generate-folders {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.9rem 1.1rem;
        border-radius: 14px;
        border: 2px dashed rgba(59, 130, 246, 0.35);
        background: rgba(59, 130, 246, 0.04);
        color: #3b82f6;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
    }
    .app-btn-generate-folders:hover {
        background: rgba(59, 130, 246, 0.09);
        border-color: #3b82f6;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.15);
    }
    .app-btn-magic-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(59, 130, 246, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .app-btn-generate-text strong {
        display: block;
        font-size: 0.84rem;
        font-weight: 700;
        color: #3b82f6;
    }
    .app-btn-generate-text span {
        display: block;
        font-size: 0.72rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .app-drive-folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.65rem;
    }
    .app-folder-mini-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        transition: all 0.2s ease;
    }
    .app-folder-mini-card:hover {
        border-color: #3b82f6;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }
    .app-folder-title {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--color-title);
        font-weight: 700;
        font-size: 0.8rem;
    }
    .app-folder-link {
        text-decoration: none;
        color: #3b82f6;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-top: auto;
    }
    .app-folder-link:hover { text-decoration: underline; }

    .app-modal-footer {
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        border-top: 1px solid var(--border-color);
        background: var(--bg-surface);
        flex-shrink: 0;
    }
    .app-btn-modal-cancel {
        padding: 0.65rem 1.25rem;
        border-radius: 12px;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .app-btn-modal-cancel:hover {
        background: var(--border-color);
    }
    .app-btn-modal-save {
        padding: 0.65rem 1.4rem;
        border-radius: 12px;
        background: var(--color-btn-bg, var(--primary-color, #10b981));
        color: #ffffff;
        border: none;
        font-size: 0.82rem;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }
    .app-btn-modal-save:hover {
        transform: translateY(-1px);
        filter: brightness(1.08);
        box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
    }

    /* ===== MOBILE APP OPTIMIZATIONS ===== */
    @media (max-width: 576px) {
        .months-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        .project-header {
            padding: 1.1rem !important;
            border-radius: 16px !important;
            gap: 1rem !important;
        }
        .mc-card {
            border-radius: 16px !important;
        }
        .mc-stats-grid {
            gap: 0.5rem !important;
        }
        .mc-pipeline-grid {
            gap: 0.35rem !important;
        }
        .mc-pipeline-lbl {
            font-size: 0.62rem !important;
        }
    }
</style>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="padding: 1rem; border-radius: var(--radius-md); background: #fee2e2; color: #991b1b; margin-bottom: 2rem;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

    <!-- Header Principal Rediseñado -->
    <div class="project-header">
        <div class="project-header-left">
            <a href="index.php?module=calendar" class="btn-back-compact" title="Volver a Proyectos">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="project-header-info">
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
                <div>
                    <h1><?php echo htmlspecialchars($project['brand_name']); ?></h1>
                    <?php 
                        $statusEs = strtolower($project['status']) === 'active' ? 'ACTIVO' : (strtolower($project['status']) === 'inactive' ? 'INACTIVO' : strtoupper($project['status']));
                        $correlativoDisplay = (strpos($project['correlativo'] ?? '', 'OT-') === 0) ? $project['correlativo'] : 'OT-' . ($project['correlativo'] ?? '');
                    ?>
                    <div class="project-meta-row">
                        <span class="board-brand-name"><i class="ph-bold ph-storefront"></i> <?php echo htmlspecialchars($project['brand_name']); ?></span>
                        <span class="board-ot-badge"><?php echo htmlspecialchars($correlativoDisplay); ?></span>
                        <span class="board-status-badge status-<?php echo strtolower($project['status']); ?>"><?php echo $statusEs; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="project-header-actions">
            <form method="GET" action="index.php" class="filter-pill-container">
                <input type="hidden" name="module" value="project_board">
                <input type="hidden" name="id" value="<?php echo $projectId; ?>">
                
                <i class="ph ph-calendar-blank" style="color: var(--text-muted); margin-left: 4px;"></i>
                <select name="filter_month" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todos los Meses</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $filterMonth === (string)$num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_year" class="filter-select" style="border-left: 1px solid var(--border-color);" onchange="this.form.submit()">
                    <option value="">Todos los Años</option>
                    <?php 
                    $currentYear = date('Y');
                    for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filterYear === (string)$y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>

            <button class="btn-project-info" onclick="openProjectInfoOffcanvas()">
                <i class="ph ph-info"></i> Información del proyecto
            </button>
            <button class="btn-create-month" onclick="openNewMonthModal()">
                <i class="ph-bold ph-plus"></i> Añadir Nuevo Mes
            </button>
        </div>
    </div>

    <!-- Months Grid Rediseñado -->
    <div class="months-grid">
        <?php if (empty($months)): ?>
            <div style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: 20px; color: var(--text-muted);">
                <i class="ph ph-calendar-blank" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: var(--color-title); font-size: 1.25rem;">No hay meses creados</h3>
                <p>Comienza añadiendo el primer mes de trabajo para este proyecto.</p>
            </div>
        <?php else: ?>
            <?php foreach ($months as $m): ?>
                <?php 
                    $statusClass = 'status-' . str_replace(' ', '_', strtolower($m['status']));
                    
                    // Format creation date
                    $dateObj = new DateTime($m['created_at']);
                    $monthsEs = ['Jan'=>'ene','Feb'=>'feb','Mar'=>'mar','Apr'=>'abr','May'=>'may','Jun'=>'jun','Jul'=>'jul','Aug'=>'ago','Sep'=>'sep','Oct'=>'oct','Nov'=>'nov','Dec'=>'dic'];
                    $fmtDate = $dateObj->format('d ') . ($monthsEs[$dateObj->format('M')] ?? $dateObj->format('M')) . $dateObj->format(', h:i a');
                    
                    // Format Month Range
                    $rangeText = '';
                    if (!empty($m['start_date']) && !empty($m['due_date'])) {
                        $sObj = new DateTime($m['start_date']);
                        $dObj = new DateTime($m['due_date']);
                        $rangeText = $sObj->format('d ') . ($monthsEs[$sObj->format('M')] ?? $sObj->format('M')) . ' - ' . $dObj->format('d ') . ($monthsEs[$dObj->format('M')] ?? $dObj->format('M')) . ' ' . $dObj->format('Y');
                    }
                ?>
                <?php
                    // Obtener conteos dinámicos
                    $stmtP = $db->prepare("SELECT COUNT(*) FROM month_posts WHERE month_id = ?");
                    $stmtP->execute([$m['id']]);
                    $postsCount = (int)$stmtP->fetchColumn();
                    
                    $stmtC = $db->prepare("
                        SELECT COUNT(*) 
                        FROM post_comments pc 
                        JOIN month_posts mp ON pc.post_id = mp.id 
                        WHERE mp.month_id = ?
                    ");
                    $stmtC->execute([$m['id']]);
                    $commentsCount = (int)$stmtC->fetchColumn();
                    
                    // Obtener conteo de los estados del pipeline
                    $stmtS = $db->prepare("
                        SELECT 
                            SUM(CASE WHEN status = 'Borrador' THEN 1 ELSE 0 END) as c_borrador,
                            SUM(CASE WHEN status = 'En Revisión' THEN 1 ELSE 0 END) as c_revision,
                            SUM(CASE WHEN status = 'Aprobado' THEN 1 ELSE 0 END) as c_aprobado,
                            SUM(CASE WHEN status = 'Publicado' THEN 1 ELSE 0 END) as c_publicado
                        FROM month_posts 
                        WHERE month_id = ?
                    ");
                    $stmtS->execute([$m['id']]);
                    $statusCounts = $stmtS->fetch(PDO::FETCH_ASSOC);
                    
                    $c_borrador = (int)($statusCounts['c_borrador'] ?? 0);
                    $c_revision = (int)($statusCounts['c_revision'] ?? 0);
                    $c_aprobado = (int)($statusCounts['c_aprobado'] ?? 0);
                    $c_publicado = (int)($statusCounts['c_publicado'] ?? 0);
                    
                    $progressPct = $postsCount > 0 ? round(($c_publicado / $postsCount) * 100) : 0;
                    $isMonthCompleted = ($postsCount > 0 && $progressPct >= 100) || strtolower($m['status']) === 'finalizado';
                    $statusBadgeClass = $isMonthCompleted ? 'status-terminado' : 'status-' . strtolower($m['status']);
                    $statusBadgeText = $isMonthCompleted ? 'TERMINADO' : strtoupper($m['status']);
                ?>
                <div class="mc-card">
                    <!-- Header -->
                    <div class="mc-header">
                        <h2 class="mc-title"><?php echo $monthNames[$m['month']] . ' ' . $m['year']; ?></h2>
                        <div class="modern-month-timer <?php echo $isMonthCompleted ? 'completed' : 'active'; ?>" data-start="<?php echo htmlspecialchars($m['start_date'] ?? ''); ?>" data-due="<?php echo htmlspecialchars($m['due_date'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($m['status'] ?? ''); ?>" data-progress="<?php echo $progressPct; ?>" data-posts="<?php echo $postsCount; ?>" title="Cronómetro del mes">
                            <i class="<?php echo $isMonthCompleted ? 'ph-fill ph-check-circle' : 'ph-fill ph-hourglass-high'; ?>"></i>
                            <span class="timer-text"><?php echo $isMonthCompleted ? 'Terminado' : 'Calculando...'; ?></span>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="mc-stats-grid">
                        <div class="mc-stat-tile">
                            <div class="mc-stat-icon posts">
                                <i class="ph-bold ph-newspaper"></i>
                            </div>
                            <div class="mc-stat-info">
                                <div class="mc-stat-num"><?php echo $postsCount; ?></div>
                                <div class="mc-stat-label">Publicaciones</div>
                            </div>
                        </div>
                        <div class="mc-stat-tile">
                            <div class="mc-stat-icon comments">
                                <i class="ph-bold ph-chat-circle-dots"></i>
                            </div>
                            <div class="mc-stat-info">
                                <div class="mc-stat-num"><?php echo $commentsCount; ?></div>
                                <div class="mc-stat-label">Comentarios</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress & Pipeline -->
                    <div class="mc-progress-section">
                        <div class="mc-progress-header">
                            <span>Progreso General</span>
                            <span class="mc-progress-pct"><?php echo $progressPct; ?>%</span>
                        </div>
                        <div class="mc-progress-bar">
                            <div class="mc-progress-fill" style="width: <?php echo $progressPct; ?>%"></div>
                        </div>
                        <div class="mc-pipeline-grid">
                            <div class="mc-pipeline-pill">
                                <span class="mc-pipeline-num" style="color: #64748b;"><?php echo $c_borrador; ?></span>
                                <span class="mc-pipeline-lbl">Borrador</span>
                            </div>
                            <div class="mc-pipeline-pill">
                                <span class="mc-pipeline-num" style="color: #eab308;"><?php echo $c_revision; ?></span>
                                <span class="mc-pipeline-lbl">Revisión</span>
                            </div>
                            <div class="mc-pipeline-pill">
                                <span class="mc-pipeline-num" style="color: #3b82f6;"><?php echo $c_aprobado; ?></span>
                                <span class="mc-pipeline-lbl">Aprobado</span>
                            </div>
                            <div class="mc-pipeline-pill">
                                <span class="mc-pipeline-num" style="color: #10b981;"><?php echo $c_publicado; ?></span>
                                <span class="mc-pipeline-lbl">Publicado</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dates Row (Cleanly separated) -->
                    <div class="mc-dates-row">
                        <?php if (!empty($rangeText)): ?>
                            <div class="mc-date-pill">
                                <i class="ph ph-calendar-blank" style="color: var(--primary-color);"></i> <?php echo $rangeText; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mc-created-date" title="Fecha de creación">
                            <i class="ph ph-clock"></i> <?php echo htmlspecialchars($fmtDate); ?>
                        </div>
                    </div>
                    
                    <!-- Footer & Actions -->
                    <div class="mc-card-footer">
                        <div class="mc-footer-meta">
                            <span class="board-status-badge <?php echo $statusBadgeClass; ?>"><?php echo htmlspecialchars($statusBadgeText); ?></span>
                            <?php if (isset($role_id) && $role_id == 1): ?>
                            <div class="mc-actions-btns">
                                <button class="mc-action-icon-btn edit" onclick="editMonth(<?php echo $m['id']; ?>)" title="Editar Mes">
                                    <i class="ph-bold ph-pencil-simple"></i>
                                </button>
                                <button class="mc-action-icon-btn delete" onclick="deleteMonth(<?php echo $m['id']; ?>)" title="Eliminar Mes">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="index.php?module=month_board&id=<?php echo $m['id']; ?>" class="mc-btn-enter">
                            <span>Entrar al Mes</span>
                            <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<!-- Modal Añadir Nuevo Mes -->
<div class="modal-overlay" id="new-month-modal">
    <div class="modal-content app-modal-card">
        <div class="app-modal-header">
            <div class="app-modal-header-left">
                <div class="app-modal-icon-badge">
                    <i class="ph-bold ph-calendar-plus"></i>
                </div>
                <div>
                    <h2 class="app-modal-title">Añadir Nuevo Mes</h2>
                    <p class="app-modal-subtitle">Configura el período de trabajo y recursos para este mes</p>
                </div>
            </div>
            <button type="button" class="btn-app-modal-close" onclick="document.getElementById('new-month-modal').classList.remove('active')">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        
        <form id="new-month-form">
            <div class="app-modal-body">
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                
                <!-- Sección 1: Período & Fechas -->
                <div class="app-modal-section">
                    <div class="app-modal-section-title">
                        <i class="ph-bold ph-calendar-blank"></i> Período del Tablero
                    </div>
                    <div class="app-modal-grid-2">
                        <div class="app-field-group">
                            <label class="app-field-label">Mes</label>
                            <div class="app-input-wrap">
                                <i class="ph ph-calendar app-input-icon"></i>
                                <select name="month" id="new-month-select" class="app-form-control" required onchange="onMonthYearChange('new')">
                                    <option value="">Selecciona mes...</option>
                                    <?php foreach ($monthNames as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $num == date('n') ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="app-field-group">
                            <label class="app-field-label">Año</label>
                            <div class="app-input-wrap">
                                <i class="ph ph-calendar-star app-input-icon"></i>
                                <select name="year" id="new-year-select" class="app-form-control" required onchange="onMonthYearChange('new')">
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="app-modal-grid-2" style="margin-top: 0.4rem;">
                        <div class="app-field-group">
                            <label class="app-field-label">Fecha de Inicio</label>
                            <div class="app-input-wrap">
                                <i class="ph ph-play-circle app-input-icon"></i>
                                <input type="date" name="start_date" id="new-start_date" class="app-form-control" required value="<?php echo date('Y-m-01'); ?>">
                            </div>
                        </div>

                        <div class="app-field-group">
                            <label class="app-field-label" style="display: flex; align-items: center; gap: 0.35rem;">
                                <i class="ph-fill ph-hourglass-high" style="color: #f59e0b;"></i> Fecha Límite / Fin (Cronómetro)
                            </label>
                            <div class="app-input-wrap">
                                <i class="ph ph-flag-checkered app-input-icon" style="color: #f59e0b;"></i>
                                <input type="date" name="due_date" id="new-due_date" class="app-form-control" required value="<?php echo date('Y-m-t'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Carpetas del Proyecto -->
                <div class="app-modal-section">
                    <div class="app-modal-section-title">
                        <i class="ph-bold ph-google-drive-logo" style="color: #3b82f6;"></i> Carpetas del Proyecto
                        <span class="app-badge-drive">Google Drive</span>
                    </div>
                    <p class="app-modal-section-hint">Genera la estructura de carpetas en la nube para referencias, contenido y entregables.</p>

                    <input type="hidden" name="drive_folders_json" id="new_drive_folders_json">

                    <div id="new-folders-container" class="app-drive-folders-grid">
                        <!-- Cards de carpetas generadas -->
                    </div>

                    <button type="button" id="btn-generate-new-folders" class="app-btn-generate-folders" onclick="generateDriveFolders('new')">
                        <div class="app-btn-magic-icon"><i class="ph-bold ph-magic-wand"></i></div>
                        <div class="app-btn-generate-text">
                            <strong>Generar Estructura de Carpetas</strong>
                            <span>Crea automáticamente las carpetas para este mes</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="app-modal-footer">
                <button type="button" class="app-btn-modal-cancel" onclick="document.getElementById('new-month-modal').classList.remove('active')">Cancelar</button>
                <button type="button" class="app-btn-modal-save" onclick="saveNewMonth()">
                    <i class="ph-bold ph-check"></i> Guardar Mes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Mes -->
<div class="modal-overlay" id="edit-month-modal">
    <div class="modal-content app-modal-card">
        <div class="app-modal-header">
            <div class="app-modal-header-left">
                <div class="app-modal-icon-badge" style="background: rgba(59, 130, 246, 0.12); border-color: rgba(59, 130, 246, 0.25); color: #3b82f6;">
                    <i class="ph-bold ph-pencil-simple"></i>
                </div>
                <div>
                    <h2 class="app-modal-title">Editar Mes</h2>
                    <p class="app-modal-subtitle">Modifica los plazos y recursos asociados a este período</p>
                </div>
            </div>
            <button type="button" class="btn-app-modal-close" onclick="document.getElementById('edit-month-modal').classList.remove('active')">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        
        <form id="edit-month-form">
            <div class="app-modal-body">
                <input type="hidden" name="id" id="edit-id">
                
                <!-- Sección: Cronograma & Plazos -->
                <div class="app-modal-section">
                    <div class="app-modal-section-title">
                        <i class="ph-bold ph-calendar-blank"></i> Cronograma del Mes
                    </div>
                    <div class="app-modal-grid-2">
                        <div class="app-field-group">
                            <label class="app-field-label">Fecha de Inicio</label>
                            <div class="app-input-wrap">
                                <i class="ph ph-play-circle app-input-icon"></i>
                                <input type="date" name="start_date" id="edit-start_date" class="app-form-control" required>
                            </div>
                        </div>

                        <div class="app-field-group">
                            <label class="app-field-label" style="display: flex; align-items: center; gap: 0.35rem;">
                                <i class="ph-fill ph-hourglass-high" style="color: #f59e0b;"></i> Fecha Límite / Fin (Cronómetro)
                            </label>
                            <div class="app-input-wrap">
                                <i class="ph ph-flag-checkered app-input-icon" style="color: #f59e0b;"></i>
                                <input type="date" name="due_date" id="edit-due_date" class="app-form-control" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección: Carpetas de Drive -->
                <div class="app-modal-section">
                    <div class="app-modal-section-title">
                        <i class="ph-bold ph-google-drive-logo" style="color: #3b82f6;"></i> Carpetas del Proyecto
                        <span class="app-badge-drive">Google Drive</span>
                    </div>
                    <p class="app-modal-section-hint">Estructura organizada en Google Drive para esta entrega.</p>

                    <input type="hidden" name="drive_folders_json" id="edit_drive_folders_json">

                    <div id="edit-folders-container" class="app-drive-folders-grid">
                        <!-- Cards de carpetas generadas -->
                    </div>

                    <button type="button" id="btn-generate-edit-folders" class="app-btn-generate-folders" onclick="generateDriveFolders('edit')">
                        <div class="app-btn-magic-icon"><i class="ph-bold ph-magic-wand"></i></div>
                        <div class="app-btn-generate-text">
                            <strong>Generar Estructura de Carpetas</strong>
                            <span>Crea automáticamente las carpetas para este mes</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="app-modal-footer">
                <button type="button" class="app-btn-modal-cancel" onclick="document.getElementById('edit-month-modal').classList.remove('active')">Cancelar</button>
                <button type="button" class="app-btn-modal-save" onclick="updateMonth()">
                    <i class="ph-bold ph-check"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Off-canvas Información del proyecto -->
<style>
    .pi-offcanvas {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: 600px;
        max-width: 100vw;
        background: var(--bg-surface);
        box-shadow: -5px 0 25px rgba(0,0,0,0.1);
        z-index: 1050;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }
    .pi-offcanvas.active {
        transform: translateX(0);
    }
    .pi-offcanvas-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 1040;
        display: none;
        backdrop-filter: blur(2px);
    }
    .pi-offcanvas-overlay.active { display: block; }
    
    .pi-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-color);
    }
    
    .pi-body {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
    }

    .pi-nav {
        display: flex;
        background: var(--bg-color);
        padding: 4px;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
    }
    .pi-nav-item {
        flex: 1;
        text-align: center;
        padding: 0.6rem 1rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    .pi-nav-item.active {
        color: var(--color-title);
        background: var(--bg-surface);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .pi-tab-pane { display: none; }
    .pi-tab-pane.active { display: block; }

    .pi-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        transition: box-shadow 0.2s;
    }
    .pi-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .pi-card-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    .pi-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .pi-card-icon.form { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .pi-card-icon.pdf { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .pi-card-title {
        font-weight: 700;
        font-size: 1rem;
        color: var(--color-title);
        margin: 0;
    }
    .pi-card-meta {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0;
    }
    .pi-actions {
        display: flex;
        gap: 0.5rem;
    }

    .pi-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: var(--radius-lg);
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: var(--bg-color);
    }
    .pi-upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(var(--primary-color-rgb), 0.05);
    }
</style>

<div class="pi-offcanvas-overlay" id="pi-overlay" onclick="closeProjectInfoOffcanvas()"></div>
<div class="pi-offcanvas" id="pi-offcanvas">
    <div class="pi-header">
        <h2 style="margin:0; font-size: 1.5rem; font-weight: 700; display:flex; align-items:center; gap: 0.5rem;">
            <i class="ph ph-info"></i> Información del proyecto
        </h2>
        <button type="button" class="btn-icon" onclick="closeProjectInfoOffcanvas()" style="width: 40px; height: 40px;">
            <i class="ph ph-x" style="font-size: 1.5rem;"></i>
        </button>
    </div>
    <div class="pi-body">
        <div class="pi-nav">
            <div class="pi-nav-item active" onclick="switchPiTab('forms', this)">Formularios</div>
            <div class="pi-nav-item" onclick="switchPiTab('pdfs', this)">Documentos PDF</div>
        </div>

        <!-- Formularios Tab -->
        <div id="pi-tab-forms" class="pi-tab-pane active">
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700;">Vincular Nuevo Formulario</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="pi-form-select" class="form-control" style="flex: 1;">
                        <option value="">Cargando formularios...</option>
                    </select>
                    <button class="btn btn-primary" onclick="linkForm()" id="btn-link-form">
                        <i class="ph ph-link"></i> Vincular
                    </button>
                </div>
            </div>
            
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Formularios Vinculados</h3>
            <div id="pi-forms-list">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Cargando...</div>
            </div>
        </div>

        <!-- PDFs Tab -->
        <div id="pi-tab-pdfs" class="pi-tab-pane">
            <div class="pi-upload-area" onclick="document.getElementById('pi-pdf-upload').click()" id="pi-upload-zone">
                <i class="ph ph-upload-simple" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3 style="margin: 0 0 0.5rem 0;">Subir Documento PDF</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">Haz clic aquí para seleccionar un archivo. (Se guardará en la carpeta Form en Drive)</p>
                <input type="file" id="pi-pdf-upload" accept="application/pdf" style="display: none;" onchange="uploadPdf(this)">
            </div>
            
            <div id="pi-upload-progress" style="display: none; margin-top: 1rem; background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 600;">
                    <span>Subiendo archivo a Google Drive...</span>
                    <i class="ph ph-spinner ph-spin" style="color: var(--primary-color);"></i>
                </div>
            </div>

            <h3 style="font-size: 1.1rem; margin: 2rem 0 1rem 0;">Documentos Subidos</h3>
            <div id="pi-pdfs-list">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Cargando...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Formulario Público -->
<div class="modal-overlay" id="pi-form-modal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; padding: 0;">
        <div class="modal-header" style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color);">
            <h2 id="pi-form-modal-title" style="margin: 0; font-size: 1.25rem;">Vista Pública del Formulario</h2>
            <button class="btn-icon" onclick="document.getElementById('pi-form-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden; background: #f3f4f6;">
            <iframe id="pi-form-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal PDF Viewer -->
<div class="modal-overlay" id="pi-pdf-modal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; padding: 0;">
        <div class="modal-header" style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color);">
            <h2 id="pi-pdf-modal-title" style="margin: 0; font-size: 1.25rem;">Visor de PDF</h2>
            <button class="btn-icon" onclick="document.getElementById('pi-pdf-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden;">
            <iframe id="pi-pdf-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="deleteConfirmModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i class="ph ph-warning"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Estás seguro?</h3>
            <p style="margin-bottom: 0;">Esta acción no se puede deshacer.</p>
            <input type="hidden" id="delete-month-id">
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteConfirmModal').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmDeleteMonth()" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<?php require_once 'includes/custom_drive_picker.php'; ?>
<script>
    // --- CUSTOM DRIVE PICKER API (FOLDER SELECTION) ---
    const PROJECT_FOLDER_ID = '<?php echo htmlspecialchars($project['drive_folder_id'] ?? ""); ?>';
    let currentFolderInputId = '';

    function promptFolder(inputId) {
        currentFolderInputId = inputId;
        const restrictedId = PROJECT_FOLDER_ID ? PROJECT_FOLDER_ID : null;
        
        cdOpenPicker(restrictedId, function(folder) {
            if (!folder.url) {
                folder.url = "https://drive.google.com/drive/folders/" + folder.id;
            }
            if (currentFolderInputId) {
                document.getElementById(currentFolderInputId).value = folder.url;
            }
        });
    }
    // --- FIN CUSTOM DRIVE PICKER ---

    function renderDriveFolderCards(foldersJsonStr, containerId, btnId) {
        const container = document.getElementById(containerId);
        const btn = document.getElementById(btnId);
        
        let foldersData = null;
        try { foldersData = JSON.parse(foldersJsonStr); } catch(e){}
        
        if (!foldersData || !foldersData.subfolders || foldersData.subfolders.length === 0) {
            container.innerHTML = '';
            btn.style.display = 'flex';
            return;
        }
        
        btn.style.display = 'none'; // hide generate button
        
        let html = '';
        foldersData.subfolders.forEach(f => {
            html += `
            <div class="app-folder-mini-card">
                <div class="app-folder-title">
                    <i class="ph-fill ph-folder" style="font-size: 1.35rem; color: #facc15;"></i>
                    <span>${f.name}</span>
                </div>
                <a href="${f.url}" target="_blank" class="app-folder-link">
                    <span>Abrir en Drive</span> <i class="ph-bold ph-arrow-square-out"></i>
                </a>
            </div>
            `;
        });
        container.innerHTML = html;
    }

    async function generateDriveFolders(mode) {
        const btn = document.getElementById(`btn-generate-${mode}-folders`);
        const origHtml = btn.innerHTML;
        
        const formData = new FormData();
        
        if (mode === 'new') {
            const form = document.getElementById('new-month-form');
            const m = form.querySelector('[name="month"]').value;
            const y = form.querySelector('[name="year"]').value;
            const pId = form.querySelector('[name="project_id"]').value;
            if (!m || !y) {
                alert('Por favor selecciona Mes y Año primero.');
                return;
            }
            formData.append('project_id', pId);
            formData.append('month', m);
            formData.append('year', y);
        } else {
            const form = document.getElementById('edit-month-form');
            const monthId = form.querySelector('[name="id"]').value;
            formData.append('month_id', monthId);
        }
        
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';
        btn.disabled = true;
        
        try {
            const response = await fetch('modules/project_board/ajax_generate_month_folders.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            btn.innerHTML = origHtml;
            btn.disabled = false;
            
            if (result.success) {
                const jsonStr = JSON.stringify(result.data);
                document.getElementById(`${mode}_drive_folders_json`).value = jsonStr;
                renderDriveFolderCards(jsonStr, `${mode}-folders-container`, `btn-generate-${mode}-folders`);
            } else {
                alert(result.error || 'Error al crear carpetas.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red.');
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    }

    function onMonthYearChange(mode) {
        if (mode === 'new') {
            const m = parseInt(document.getElementById('new-month-select').value);
            const y = parseInt(document.getElementById('new-year-select').value);
            if (m && y) {
                const padM = String(m).padStart(2, '0');
                const lastDay = new Date(y, m, 0).getDate();
                const padLastDay = String(lastDay).padStart(2, '0');
                document.getElementById('new-start_date').value = `${y}-${padM}-01`;
                document.getElementById('new-due_date').value = `${y}-${padM}-${padLastDay}`;
            }
        }
    }

    function openNewMonthModal() {
        document.getElementById('new-month-form').reset();
        document.getElementById('new_drive_folders_json').value = '';
        document.getElementById('new-folders-container').innerHTML = '';
        document.getElementById('btn-generate-new-folders').style.display = 'flex';
        onMonthYearChange('new');
        document.getElementById('new-month-modal').classList.add('active');
    }

async function saveNewMonth() {
    const form = document.getElementById('new-month-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/project_board/ajax_save_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al guardar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

// --- PROJECT INFO OFFCANVAS LOGIC ---
function openProjectInfoOffcanvas() {
    document.getElementById('pi-overlay').classList.add('active');
    document.getElementById('pi-offcanvas').classList.add('active');
    loadProjectInfo();
}

function closeProjectInfoOffcanvas() {
    document.getElementById('pi-overlay').classList.remove('active');
    document.getElementById('pi-offcanvas').classList.remove('active');
}

function switchPiTab(tabId, el) {
    document.querySelectorAll('.pi-nav-item').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.pi-tab-pane').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('pi-tab-' + tabId).classList.add('active');
}

async function loadProjectInfo() {
    try {
        const response = await fetch(`modules/project_board/ajax_get_project_info.php?project_id=<?php echo $projectId; ?>`);
        const result = await response.json();
        
        if (result.success) {
            // Render Select
            const select = document.getElementById('pi-form-select');
            select.innerHTML = '<option value="" style="color:#111827; background:#fff;">Selecciona un formulario...</option>';
            result.available_forms.forEach(f => {
                let text = `${f.form_title} - ${f.respondent_name || 'Sin nombre'} (${f.correlativo})`;
                select.innerHTML += `<option value="${f.id}" style="color:#111827; background:#fff;">${text}</option>`;
            });

            // Render Forms List
            const formsList = document.getElementById('pi-forms-list');
            if (result.forms.length === 0) {
                formsList.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:8px;">No hay formularios vinculados.</div>';
            } else {
                let html = '';
                result.forms.forEach(f => {
                    const date = new Date(f.created_at).toLocaleDateString('es-ES', {day: 'numeric', month: 'short', year: 'numeric'});
                    let titleText = `${f.form_title} - ${f.respondent_name || 'Sin nombre'} (${f.correlativo})`;
                    html += `
                    <div class="pi-card">
                        <div class="pi-card-info" style="cursor: pointer;" onclick="openFormModal(${f.submission_id}, '${f.form_title}')">
                            <div class="pi-card-icon form"><i class="ph ph-article"></i></div>
                            <div>
                                <h4 class="pi-card-title">${titleText}</h4>
                                <p class="pi-card-meta">Vinculado el ${date}</p>
                            </div>
                        </div>
                        <div class="pi-actions">
                            <button class="btn-icon text-red" onclick="deleteAttachment(${f.id}, 'form')" title="Quitar vinculación">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>`;
                });
                formsList.innerHTML = html;
            }

            // Render PDFs List
            const pdfsList = document.getElementById('pi-pdfs-list');
            if (result.pdfs.length === 0) {
                pdfsList.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:8px;">No hay documentos PDF.</div>';
            } else {
                let html = '';
                result.pdfs.forEach(p => {
                    const date = new Date(p.created_at).toLocaleDateString('es-ES', {day: 'numeric', month: 'short', year: 'numeric'});
                    html += `
                    <div class="pi-card">
                        <div class="pi-card-info" style="cursor: pointer;" onclick="openPdfModal('${p.url}', '${p.file_name}')">
                            <div class="pi-card-icon pdf"><i class="ph ph-file-pdf"></i></div>
                            <div style="overflow: hidden;">
                                <h4 class="pi-card-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${p.file_name}">${p.file_name}</h4>
                                <p class="pi-card-meta">Subido el ${date}</p>
                            </div>
                        </div>
                        <div class="pi-actions">
                            <a href="${p.url}" target="_blank" class="btn-icon text-blue" title="Abrir en Drive">
                                <i class="ph ph-arrow-square-out"></i>
                            </a>
                            <button class="btn-icon text-red" onclick="deleteAttachment(${p.id}, 'pdf')" title="Eliminar archivo">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>`;
                });
                pdfsList.innerHTML = html;
            }
        }
    } catch (e) {
        console.error(e);
        document.getElementById('pi-forms-list').innerHTML = '<div style="color:red;">Error cargando datos.</div>';
        document.getElementById('pi-pdfs-list').innerHTML = '<div style="color:red;">Error cargando datos.</div>';
    }
}

async function linkForm() {
    const submissionId = document.getElementById('pi-form-select').value;
    if (!submissionId) return;

    const btn = document.getElementById('btn-link-form');
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('submission_id', submissionId);

    try {
        const response = await fetch('modules/project_board/ajax_link_form.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al vincular formulario');
        }
    } catch (e) {
        console.error(e);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="ph ph-link"></i> Vincular';
}

async function uploadPdf(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    if (file.type !== 'application/pdf') {
        alert('Solo se permiten archivos PDF.');
        input.value = '';
        return;
    }

    const zone = document.getElementById('pi-upload-zone');
    const progress = document.getElementById('pi-upload-progress');
    
    zone.style.display = 'none';
    progress.style.display = 'block';

    const formData = new FormData();
    formData.append('project_id', <?php echo $projectId; ?>);
    formData.append('pdf_file', file);

    try {
        const response = await fetch('modules/project_board/ajax_upload_pdf.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al subir PDF');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red al subir PDF');
    }

    input.value = '';
    zone.style.display = 'block';
    progress.style.display = 'none';
}

async function deleteAttachment(id, type) {
    if (!confirm('¿Estás seguro de eliminar este elemento?')) return;

    try {
        const formData = new FormData();
        formData.append('id', id);
        
        const response = await fetch('modules/project_board/ajax_delete_attachment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            loadProjectInfo();
        } else {
            alert(result.error || 'Error al eliminar');
        }
    } catch (e) {
        console.error(e);
    }
}

function openFormModal(id, title) {
    const iframe = document.getElementById('pi-form-iframe');
    iframe.src = `modules/forms/view_submission.php?id=${id}&mode=iframe`;
    document.getElementById('pi-form-modal-title').textContent = title;
    document.getElementById('pi-form-modal').classList.add('active');
}

function openPdfModal(url, title) {
    const iframe = document.getElementById('pi-pdf-iframe');
    // Usamos el webViewLink de Google Drive que soporta iframes si está público
    iframe.src = url;
    document.getElementById('pi-pdf-modal-title').textContent = title;
    document.getElementById('pi-pdf-modal').classList.add('active');
}


async function editMonth(id) {
    try {
        const response = await fetch(`modules/project_board/ajax_get_month.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            document.getElementById('edit-id').value = data.id;
            document.getElementById('edit-start_date').value = data.start_date || '';
            document.getElementById('edit-due_date').value = data.due_date || '';
            document.getElementById('edit_drive_folders_json').value = data.drive_folders_json || '';
            
            renderDriveFolderCards(data.drive_folders_json, 'edit-folders-container', 'btn-generate-edit-folders');
            
            document.getElementById('edit-month-modal').classList.add('active');
        } else {
            alert('Error al obtener datos: ' + result.error);
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function updateMonth() {
    const form = document.getElementById('edit-month-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/project_board/ajax_update_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

function deleteMonth(id) {
    document.getElementById('delete-month-id').value = id;
    document.getElementById('deleteConfirmModal').classList.add('active');
}

async function confirmDeleteMonth() {
    const id = document.getElementById('delete-month-id').value;
    if (!id) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    try {
        const response = await fetch('modules/project_board/ajax_delete_month.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al eliminar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function changeContentPhase(id, selectElement) {
    const phase = selectElement.value;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('content_phase', phase);
    
    try {
        const response = await fetch('modules/project_board/ajax_update_phase.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (!result.success) {
            alert(result.error || 'Error al actualizar el estado.');
            window.location.reload();
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
        window.location.reload();
    }
}

// --- CRONÓMETROS EN VIVO DE LOS MESES ---
function updateMonthTimers() {
    const now = new Date();
    document.querySelectorAll('.modern-month-timer').forEach(el => {
        const dueStr = el.getAttribute('data-due');
        const startStr = el.getAttribute('data-start');
        const status = (el.getAttribute('data-status') || '').toLowerCase();
        const progress = parseInt(el.getAttribute('data-progress') || '0', 10);
        const posts = parseInt(el.getAttribute('data-posts') || '0', 10);
        const textEl = el.querySelector('.timer-text');
        const iconEl = el.querySelector('i');
        
        if (status === 'finalizado' || (posts > 0 && progress >= 100)) {
            el.className = 'modern-month-timer completed';
            if (iconEl) iconEl.className = 'ph-fill ph-check-circle';
            if (textEl) textEl.textContent = 'Terminado';
            return;
        }

        if (!dueStr) {
            if (textEl) textEl.textContent = 'Sin fecha';
            return;
        }

        const due = new Date(dueStr + 'T23:59:59');
        const start = startStr ? new Date(startStr + 'T00:00:00') : new Date();

        if (now < start) {
            el.className = 'modern-month-timer upcoming';
            if (iconEl) iconEl.className = 'ph-bold ph-clock-countdown';
            const diffUpcoming = start - now;
            const upDays = Math.floor(diffUpcoming / (1000 * 60 * 60 * 24));
            const upHours = String(Math.floor((diffUpcoming % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
            const upMins = String(Math.floor((diffUpcoming % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
            const upSecs = String(Math.floor((diffUpcoming % (1000 * 60)) / 1000)).padStart(2, '0');
            if (upDays > 0) {
                textEl.textContent = `Inicia en ${upDays}d ${upHours}:${upMins}:${upSecs}`;
            } else {
                textEl.textContent = `Inicia en ${upHours}:${upMins}:${upSecs}`;
            }
            return;
        }

        if (now > due) {
            el.className = 'modern-month-timer expired';
            if (iconEl) iconEl.className = 'ph-fill ph-warning-circle';
            if (textEl) textEl.textContent = 'Tiempo agotado';
            return;
        }

        const diff = due - now;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        const mins = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
        const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

        if (days < 2) {
            el.className = 'modern-month-timer warning';
            if (iconEl) iconEl.className = 'ph-fill ph-hourglass-medium';
        } else {
            el.className = 'modern-month-timer active';
            if (iconEl) iconEl.className = 'ph-fill ph-hourglass-high';
        }

        if (days > 0) {
            textEl.textContent = `${days}d ${hours}:${mins}:${secs}`;
        } else {
            textEl.textContent = `${hours}:${mins}:${secs}`;
        }
    });
}
document.addEventListener('DOMContentLoaded', updateMonthTimers);
updateMonthTimers();
setInterval(updateMonthTimers, 1000);
</script>

<?php require_once 'includes/footer.php'; ?>
