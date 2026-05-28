<?php
// modules/design_tasks/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    $db = (new Database())->getConnection();
    $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    if ($stmtRole->fetchColumn() == 1) {
        $isAdmin = true;
    }
}
$currentUserId = $_SESSION['user_id'];

require_once 'includes/header.php';
require_once 'includes/custom_drive_picker.php';

// Fetch active forms (submissions) for the dropdown
$stmtForms = $db->query("SELECT s.id, s.correlativo, s.respondent_name, t.title as form_title FROM form_submissions s JOIN form_templates t ON s.template_id = t.id ORDER BY s.created_at DESC");
$activeForms = $stmtForms->fetchAll(PDO::FETCH_ASSOC);

// Fetch clients for the dropdown
$stmtClients = $db->query("SELECT id, name FROM clients ORDER BY name ASC");
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- TomSelect CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">

<style>
          /* Mobile Responsiveness for New Cards */
      @media (max-width: 768px) {
          .dt-new-card-wrap { border-radius: 12px !important; }
          .dt-new-card-wrap > div:last-child { padding: 0.5rem !important; }
          .dt-new-card-wrap > div:last-child > div:first-child { padding: 0.75rem !important; }
          .dt-new-card-wrap .ph { font-size: 1rem !important; }
      }
      @media (max-width: 768px) {
        .dt-table-responsive, .dt-table-responsive thead, .dt-table-responsive tbody, .dt-table-responsive th, .dt-table-responsive td, .dt-table-responsive tr {
            display: block;
        }
        .dt-table-responsive thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        .dt-table-responsive tr {
            border: 1px solid rgba(150,150,150,0.1);
            margin-bottom: 1rem;
            border-radius: 12px;
            background: var(--bg-surface);
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .dt-table-responsive td {
            border: none !important;
            position: relative;
            padding-left: 40% !important;
            text-align: right;
            margin-bottom: 0.75rem;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            min-height: 24px;
        }
        .dt-table-responsive td:last-child {
            margin-bottom: 0;
        }
        .dt-table-responsive td:before {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            width: 35%;
            white-space: nowrap;
            text-align: left;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-muted);
            content: attr(data-label);
        }
        /* Extra fix for desktop background override */
        .dt-table-responsive {
            background: transparent !important;
        }
    }
    :root {
        --task-bg: var(--bg-surface);
        --radius-xl: 1.25rem;
    }
    
    [data-theme="dark"] .ts-control {
        background-color: rgba(255,255,255,0.05) !important;
        border-color: rgba(255,255,255,0.2) !important;
        color: var(--text-main) !important;
    }
    [data-theme="dark"] .ts-dropdown {
        background-color: var(--bg-surface) !important;
        border-color: rgba(255,255,255,0.1) !important;
        color: var(--text-main) !important;
    }
    [data-theme="dark"] .ts-dropdown .active {
        background-color: var(--primary-color) !important;
        color: white !important;
    }
    .ts-control {
        border-radius: var(--radius-md) !important;
        padding: 0.5rem 0.75rem !important;
    }

    /* Subtle form borders */
    .form-control {
        border: 1px solid rgba(150, 150, 150, 0.2) !important;
        background: var(--bg-color) !important;
        color: var(--text-main) !important;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1) !important;
    }
    
    [data-theme="dark"] .form-control {
        background: rgba(255, 255, 255, 0.06) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
    }
    [data-theme="dark"] .form-control::placeholder {
        color: rgba(255, 255, 255, 0.4) !important;
    }

    .dt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .dt-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-surface);
        padding: 0.5rem;
        border-radius: var(--radius-lg);
        border: 1px solid rgba(150, 150, 150, 0.15);
        overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    max-width: 100%;
}

    .dt-tab {
        padding: 0.5rem 1.5rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s;
        flex: 1 0 auto;
}

    .dt-tab.active {
        background: var(--primary-color);
        color: white;
    }

    /* Kanban Styles */
    .dt-kanban {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding-bottom: 1rem;
        min-height: 60vh;
    }

    .dt-column {
        flex: 1;
        min-width: 300px;
        background: var(--bg-surface);
        border-radius: var(--radius-xl);
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(150, 150, 150, 0.15);
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }

    .dt-column-header {
        padding: 1.25rem;
        font-weight: 700;
        font-size: 1.1rem;
        border-bottom: 1px solid rgba(150, 150, 150, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dt-column-body {
        padding: 1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        overflow-y: auto;
        min-height: 150px;
    }

    .dt-task {
        background: var(--task-bg);
        border: 1px solid rgba(150, 150, 150, 0.15);
        border-radius: var(--radius-lg);
        padding: 0; /* padding moved to inner wrapper for covers */
        overflow: hidden;
        cursor: grab;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .dt-task:active {
        cursor: grabbing;
    }

    .dt-task:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 12px rgba(0,0,0,0.08);
    }

    .dt-task-cover {
        width: 100%;
        height: 120px;
        background-size: cover;
        background-position: center;
        border-bottom: 1px solid rgba(150, 150, 150, 0.1);
    }

    .task-urgent {
        border-color: var(--danger-color) !important;
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.2) !important;
    }
    @keyframes pulse-urgent {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
        70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .task-urgent:hover {
        animation: pulse-urgent 1.5s infinite;
    }

    .dt-task-title {
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--color-title);
    }

    .dt-task-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 1rem;
    }

    .badge-priority {
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
    }
    .badge-priority.baja { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .badge-priority.media { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .badge-priority.alta { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

    /* Calendar Styles */
    #dt-calendar-view {
        display: none;
        background: var(--bg-surface);
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        border: 1px solid rgba(150, 150, 150, 0.15);
    }

    .fc-theme-standard td, .fc-theme-standard th {
        border-color: rgba(150, 150, 150, 0.15);
    }
    .fc-event {
        border: none !important;
        background: transparent !important;
        margin-bottom: 4px;
        cursor: pointer;
    }
    .fc-h-event .fc-event-main {
        color: inherit;
    }
    .fc-daygrid-event-harness {
        margin: 2px 4px !important;
    }
    .custom-fc-event {
        padding: 6px 8px;
        border-radius: 8px;
        color: #1e293b;
        display: flex;
        flex-direction: column;
        gap: 6px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        min-height: 50px;
        width: 100%;
        box-sizing: border-box;
    }
    .custom-fc-event.p-baja { background: #ecfdf5; } 
    .custom-fc-event.p-media { background: #eff6ff; } 
    .custom-fc-event.p-alta { background: #fef2f2; } 
    .custom-fc-event.p-terminado { background: #f1f5f9; color: #64748b; }

    .custom-fc-title {
        font-weight: 600;
        font-size: 0.85rem;
        line-height: 1.3;
        white-space: normal;
    }
    .custom-fc-time {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 4px;
    }
    .custom-fc-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
    }
    .custom-fc-avatars { display: flex; }
    .custom-fc-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        border: 2px solid white;
    }
    .custom-fc-tag {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 500;
    }

    /* Offcanvas Modal */
    .dt-offcanvas {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: 750px;
        max-width: 100vw;
        background: var(--bg-surface);
        box-shadow: -5px 0 25px rgba(0,0,0,0.1);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }
    .dt-offcanvas.active {
        transform: translateX(0);
    }
    .dt-offcanvas-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 999;
        display: none;
        backdrop-filter: blur(2px);
    }
    .dt-offcanvas-overlay.active { display: block; }

          /* Premium Offcanvas Overrides */
      .oc-header {
          padding: 1.5rem 2rem;
          border-bottom: 1px solid rgba(150, 150, 150, 0.1);
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: var(--bg-surface);
      }
      .oc-header h2 { font-size: 1.6rem !important; letter-spacing: -0.02em; color: var(--color-title); }
      .oc-header .btn-icon { transition: transform 0.2s, background 0.2s; border: none; }
      .oc-header .btn-icon:hover { transform: scale(1.05); }
      #btn-clone-task { background: #e0f2fe !important; color: #0284c7 !important; }
      #btn-delete-task { background: #fee2e2 !important; color: #ef4444 !important; }
      [data-theme="dark"] #btn-clone-task { background: rgba(2, 132, 199, 0.2) !important; color: #38bdf8 !important; }
      [data-theme="dark"] #btn-delete-task { background: rgba(239, 68, 68, 0.2) !important; color: #f87171 !important; }

      .oc-nav {
          background: rgba(150, 150, 150, 0.05) !important;
          padding: 6px !important;
          border-radius: 14px !important;
          box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
      }
      .oc-nav-item { border-radius: 10px !important; font-size: 0.95rem; }
      .oc-nav-item.active { box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important; font-weight: 700; color: var(--color-title) !important; }
      
      .oc-body .form-control {
          background: color-mix(in srgb, var(--bg-surface) 50%, var(--bg-color));
          border: 1px solid rgba(150,150,150,0.2);
          border-radius: 10px;
          padding: 0.75rem 1rem;
          font-size: 1rem;
          transition: border-color 0.2s, box-shadow 0.2s;
      }
      .oc-body .form-control:focus {
          border-color: var(--primary-color);
          box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
          outline: none;
      }
      .pill-label { border-radius: 10px !important; font-size: 0.95rem; }
      .pill-radio input:checked + .pill-label { box-shadow: 0 4px 10px color-mix(in srgb, var(--primary-color) 30%, transparent); }

    .oc-body {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
    }

    .oc-nav {
        display: flex;
        background: rgba(150, 150, 150, 0.1);
        padding: 4px;
        border-radius: 12px;
        margin-bottom: 2rem;
        gap: 0;
        overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    max-width: 100%;
}
    .oc-nav-item {
        flex: 1;
        text-align: center;
        padding: 0.6rem 1rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 10px;
        transition: all 0.3s ease;
        flex: 1 0 auto;
}
    .oc-nav-item.active {
        color: var(--color-title);
        background: var(--bg-surface);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .oc-tab-pane { display: none; }
    .oc-tab-pane.active { display: block; }

    .oc-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid rgba(150, 150, 150, 0.15);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        background: var(--bg-color);
    }

    /* Pills */
    .pill-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .pill-radio input { display: none; }
    .pill-label {
        padding: 0.4rem 1rem;
        background: var(--bg-color);
        border: 1px solid rgba(150, 150, 150, 0.15);
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s;
    }
    .pill-radio input:checked + .pill-label {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    /* Subtask Cards */
    .st-card {
        background: var(--bg-color);
        border: 1px solid rgba(150, 150, 150, 0.15);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* Image Thumbnails */
    .thumb-grid {
        display: flex;
        overflow-x: auto;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-bottom: 0.5rem;
    }
    .thumb-grid::-webkit-scrollbar {
        height: 6px;
    }
    .thumb-grid::-webkit-scrollbar-thumb {
        background: rgba(150, 150, 150, 0.3);
        border-radius: 3px;
    }
    .thumb-item {
        position: relative;
        flex: 0 0 auto;
        width: 100px;
        height: 100px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(150, 150, 150, 0.2);
    }
    .thumb-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .thumb-btn-del {
        position: absolute;
        top: 2px; right: 2px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px; height: 20px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px;
    }

    /* Document/File Card */
    .file-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border: 1px solid rgba(150, 150, 150, 0.15);
        border-radius: 12px;
        margin-bottom: 0.75rem;
        background: var(--bg-surface);
    }
    .file-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .file-icon {
        font-size: 2rem;
        color: #ef4444; 
    }

    /* FullCalendar Buttons Modernization */
    .fc .fc-button {
        background-color: var(--bg-surface) !important;
        border: 1px solid rgba(150, 150, 150, 0.2) !important;
        color: var(--text-main) !important;
        border-radius: 8px !important;
        text-transform: capitalize !important;
        box-shadow: none !important;
        font-weight: 500 !important;
        padding: 0.4rem 0.8rem !important;
        transition: all 0.2s ease !important;
    }
    .fc .fc-button:hover {
        background-color: var(--bg-body) !important;
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active, 
    .fc .fc-button-primary:not(:disabled):active {
        background-color: var(--primary-color) !important;
        color: white !important;
        border-color: var(--primary-color) !important;
    }
    .fc .fc-button-group {
        background: var(--bg-surface);
        border-radius: 8px;
        padding: 2px;
        border: 1px solid rgba(150, 150, 150, 0.15);
    }
    .fc .fc-button-group > .fc-button {
        border: none !important;
        border-radius: 6px !important;
        margin: 0 !important;
    }
    /* Floating Action Button (Mobile) */
    .fab-mobile {
        display: none;
        position: fixed;
        bottom: 2rem;
        right: 1.5rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 900;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .fab-mobile:active { transform: scale(0.95); }

    /* Responsive Adjustments */
          /* Mobile Responsiveness for New Cards */
      @media (max-width: 768px) {
          .dt-new-card-wrap { border-radius: 12px !important; }
          .dt-new-card-wrap > div:last-child { padding: 0.5rem !important; }
          .dt-new-card-wrap > div:last-child > div:first-child { padding: 0.75rem !important; }
          .dt-new-card-wrap .ph { font-size: 1rem !important; }
      }
      @media (max-width: 768px) {
        .btn-nueva-tarea-desktop { display: none !important; }
        .fab-mobile { display: flex; }
        
        /* Snap-scroll Kanban */
        .dt-kanban {
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            gap: 1rem;
            padding-right: 1rem;
        }
        .dt-column {
            min-width: 85vw;
            flex: 0 0 auto;
            scroll-snap-align: center;
        }

        /* Full-screen Offcanvas on Mobile */
        .dt-offcanvas { width: 100vw; }
        
        /* Page Header Fixes */
        .dt-header-wrap {
            flex-direction: column;
            align-items: center !important;
            text-align: center;
        }
        .dt-header-wrap > div:first-child {
            flex-direction: column;
            gap: 0.75rem !important;
            margin-bottom: 0.5rem;
        }
        .dt-header-wrap > div:last-child {
            width: 100%;
            justify-content: center;
        }

        /* Calendar Header Fixes */
        .fc .fc-header-toolbar {
            flex-direction: column;
            gap: 1rem;
        }
        .fc .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .fc .fc-toolbar-title {
            font-size: 1.25rem !important;
            text-align: center;
        }
    }
    
    /* Global Upload Toast */
    #global-upload-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border-radius: 8px;
        padding: 1rem;
        width: 320px;
        z-index: 10000;
        display: none;
        flex-direction: column;
        gap: 0.5rem;
    }
    #global-upload-toast .toast-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--color-title);
    }
    #global-upload-toast .progress-bar-container {
        width: 100%;
        height: 6px;
        background: var(--bg-color);
        border-radius: 3px;
        overflow: hidden;
    }
    #global-upload-toast .progress-bar-fill {
        height: 100%;
        background: var(--primary-color);
        width: 0%;
        transition: width 0.2s ease;
    }
    #global-upload-toast .toast-status {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
          /* Mobile Responsiveness for New Cards */
      @media (max-width: 768px) {
          .dt-new-card-wrap { border-radius: 12px !important; }
          .dt-new-card-wrap > div:last-child { padding: 0.5rem !important; }
          .dt-new-card-wrap > div:last-child > div:first-child { padding: 0.75rem !important; }
          .dt-new-card-wrap .ph { font-size: 1rem !important; }
      }
      @media (max-width: 768px) {
        .fc-dayGridMonth-button, .fc-timeGridWeek-button {
            display: none !important;
        }
    }
          /* Mobile Responsiveness for New Cards */
      @media (max-width: 768px) {
          .dt-new-card-wrap { border-radius: 12px !important; }
          .dt-new-card-wrap > div:last-child { padding: 0.5rem !important; }
          .dt-new-card-wrap > div:last-child > div:first-child { padding: 0.75rem !important; }
          .dt-new-card-wrap .ph { font-size: 1rem !important; }
      }
      @media (max-width: 768px) {
        .fc { width: 100%; overflow-x: hidden; }
        .fc-view-harness { overflow-x: auto; }
        #calendar { padding: 0.25rem !important; }
        #dt-calendar-view { margin-top: 0.5rem !important; border-radius: 12px !important; box-sizing: border-box; }
        /* Obsolete mobile overrides removed */
    }
    /* Dark mode classes for tags and avatars */
    .dt-task-tag { background: rgba(255, 255, 255, 0.5); color: var(--tag-color); }
    .dt-avatar-wrap { background: rgba(255, 255, 255, 0.5); width: fit-content; }
    [data-theme="dark"] .dt-task-tag { background: var(--tag-color); color: var(--tag-contrast); }
    [data-theme="dark"] .dt-avatar-wrap { background: rgba(0, 0, 0, 0.2); }
</style>

<div class="dt-header-wrap" style="background: var(--bg-surface); border: 1px solid rgba(150, 150, 150, 0.15); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(150, 150, 150, 0.15);">
            <i class="ph ph-paint-brush" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Diseño Gráfico</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona las tareas de diseño y creatividad.</p>
        </div>
    </div>
    
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div class="dt-tabs">
            <div class="dt-tab active" onclick="switchView('kanban')" id="tab-kanban">
                <i class="ph ph-kanban"></i> Tablero
            </div>
            <div class="dt-tab" onclick="switchView('list')" id="tab-list">
                <i class="ph ph-list-dashes"></i> Lista
            </div>
            <div class="dt-tab" onclick="switchView('calendar')" id="tab-calendar">
                <i class="ph ph-calendar"></i> Calendario
            </div>
            <div class="dt-tab" onclick="switchView('trash')" id="tab-trash" style="margin-left: auto; color: var(--danger-color);">
                <i class="ph ph-trash"></i> Papelera
            </div>
        </div>
        <button class="btn btn-primary btn-nueva-tarea-desktop" onclick="openTaskModal()">
            <i class="ph ph-plus"></i> Nueva Tarea
        </button>
    </div>
</div>

<!-- Modal Formulario Público (Design Tasks) -->
<div class="modal-overlay" id="dt-form-modal" style="z-index: 1060;">
    <div class="modal-content" style="max-width: 90vw; height: 90vh; display: flex; flex-direction: column; padding: 0;">
        <div class="modal-header" style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color);">
            <h2 id="dt-form-modal-title" style="margin: 0; font-size: 1.25rem;">Vista Pública del Formulario</h2>
            <button class="btn-icon" onclick="document.getElementById('dt-form-modal').classList.remove('active')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0; overflow: hidden; background: #f3f4f6;">
            <iframe id="dt-form-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Mobile FAB -->
<button class="fab-mobile" onclick="openTaskModal()">
    <i class="ph ph-plus"></i>
</button>

<!-- KANBAN VIEW -->
<div id="dt-kanban-view" class="dt-kanban">
    <div class="dt-column">
        <div class="dt-column-header">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <span style="width:10px; height:10px; border-radius:50%; background:#f43f5e;"></span> Pendiente
            </span>
            <span class="badge" id="count-Pendiente">0</span>
        </div>
        <div class="dt-column-body" id="col-Pendiente" data-status="Pendiente"></div>
    </div>
    <div class="dt-column">
        <div class="dt-column-header">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <span style="width:10px; height:10px; border-radius:50%; background:#3b82f6;"></span> En progreso
            </span>
            <span class="badge" id="count-En progreso">0</span>
        </div>
        <div class="dt-column-body" id="col-En progreso" data-status="En progreso"></div>
    </div>
    <div class="dt-column">
        <div class="dt-column-header">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <span style="width:10px; height:10px; border-radius:50%; background:#f59e0b;"></span> En revisión
            </span>
            <span class="badge" id="count-En revisión">0</span>
        </div>
        <div class="dt-column-body" id="col-En revisión" data-status="En revisión"></div>
    </div>
    <div class="dt-column">
        <div class="dt-column-header">
            <span style="display:flex; align-items:center; gap:0.5rem;">
                <span style="width:10px; height:10px; border-radius:50%; background:#10b981;"></span> Terminado
            </span>
            <span class="badge" id="count-Terminado">0</span>
        </div>
        <div class="dt-column-body" id="col-Terminado" data-status="Terminado"></div>
    </div>
</div>

<!-- CALENDAR VIEW -->
<div id="dt-calendar-view" style="display:none;">
    <div id="calendar"></div>
</div>

<!-- LIST VIEW -->
<div id="dt-list-view" style="display:none; padding: 1rem;">
    <div style="background:var(--bg-surface); border-radius:var(--radius-lg); overflow:hidden; border:1px solid rgba(150,150,150,0.15);">
        <table class="dt-table-responsive" style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            <thead>
                <tr style="background:rgba(150,150,150,0.05); text-align:left; border-bottom:1px solid rgba(150,150,150,0.15);">
                    <th style="padding:1rem;">Título</th>
                    <th style="padding:1rem;">Estado</th>
                    <th style="padding:1rem;">Prioridad</th>
                    <th style="padding:1rem;">Fecha Límite</th>
                    <th style="padding:1rem;">Asignados</th>
                    <th style="padding:1rem;">Tiempo</th>
                </tr>
            </thead>
            <tbody id="dt-list-body">
                <!-- List rows here -->
            </tbody>
        </table>
    </div>
</div>

<!-- TRASH VIEW -->
<div id="dt-trash-view" style="display:none; padding: 1rem;">
    <div style="background:var(--bg-surface); border-radius:var(--radius-lg); overflow:hidden; border:1px solid rgba(150,150,150,0.15);">
        <table class="dt-table-responsive" style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            <thead>
                <tr style="background:rgba(239,68,68,0.05); text-align:left; border-bottom:1px solid rgba(150,150,150,0.15);">
                    <th style="padding:1rem;">Título</th>
                    <th style="padding:1rem;">Estado Original</th>
                    <th style="padding:1rem;">Fecha de Eliminación</th>
                    <th style="padding:1rem; text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="dt-trash-body">
                <!-- Trash rows here -->
            </tbody>
        </table>
    </div>
</div>

<!-- OFFCANVAS MODAL -->
<div class="dt-offcanvas-overlay" id="oc-overlay" onclick="closeTaskModal()"></div>
<div class="dt-offcanvas" id="task-offcanvas">
    <div class="oc-header">
        <h2 id="modal-title" style="margin:0;">Nueva Tarea</h2>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button type="button" class="btn-icon text-blue" id="btn-clone-task" style="display:none; width: 40px; height: 40px; background: rgba(59, 130, 246, 0.1); color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;" onclick="cloneTask()" title="Duplicar Tarea">
                <i class="ph ph-copy" style="font-size: 1.25rem;"></i>
            </button>
            <button type="button" class="btn-icon text-red" id="btn-delete-task" style="display:none; width: 40px; height: 40px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;" onclick="deleteTask()" title="Eliminar Tarea">
                <i class="ph ph-trash" style="font-size: 1.25rem;"></i>
            </button>
            <button type="button" class="btn-icon" onclick="closeTaskModal()" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="ph ph-x" style="font-size: 1.5rem;"></i>
            </button>
        </div>
    </div>
    
    <div class="oc-body">
        <!-- Must have enctype for files -->
        <form id="task-form" onsubmit="event.preventDefault(); saveTask();" enctype="multipart/form-data">
            <input type="hidden" id="task-id" name="id">
            <input type="hidden" name="drive_folder_id" id="task-drive-folder">
            
            <div class="oc-nav">
                <div class="oc-nav-item active" onclick="switchOcTab('details', this)">Detalles</div>
                <div class="oc-nav-item" onclick="switchOcTab('subtasks', this)">Subtareas</div>
                <div class="oc-nav-item" onclick="switchOcTab('files', this)">Archivos</div>
                <div class="oc-nav-item" onclick="switchOcTab('avances', this)">Avances</div>
            </div>

            <!-- Pestaña Detalles -->
            <div id="tab-details" class="oc-tab-pane active">
                <div class="form-group">
                    <label class="form-label" style="font-weight:700;">Título</label>
                    <input type="text" class="form-control" name="title" id="task-title" required placeholder="Ej: Diseño de banner web" style="font-size:1.1rem;">
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Prioridad</label>
                    <div class="pill-group">
                        <label class="pill-radio"><input type="radio" name="priority" value="baja" id="prio-baja"><span class="pill-label">Baja</span></label>
                        <label class="pill-radio"><input type="radio" name="priority" value="media" id="prio-media" checked><span class="pill-label">Media</span></label>
                        <label class="pill-radio"><input type="radio" name="priority" value="alta" id="prio-alta"><span class="pill-label">Alta</span></label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Fecha y Hora Límite</label>
                    <input type="datetime-local" class="form-control" name="due_date" id="task-due-date">
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Descripción</label>
                    <div id="task-desc-editor" style="height: 150px; background: var(--bg-surface);"></div>
                    <input type="hidden" name="description" id="task-desc">
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Vincular Formulario</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <select class="form-control" name="linked_form_id" id="task-linked-form" style="flex: 1; color: var(--text-color); background: var(--input-bg);" onchange="toggleViewFormBtn(this)">
                            <option value="" style="color: #111827; background: #fff;">Ninguno</option>
                            <?php foreach ($activeForms as $f): ?>
                                <option value="<?php echo $f['id']; ?>" style="color: #111827; background: #fff;">
                                    <?php echo htmlspecialchars($f['form_title'] . ' - ' . ($f['respondent_name'] ?: 'Sin nombre') . ' (' . $f['correlativo'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline" id="btn-view-form" style="display: none; white-space: nowrap;" onclick="openLinkedForm()">
                            <i class="ph ph-eye"></i> Ver Formulario
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Vincular al Cliente (Opcional)</label>
                    <select class="form-control" name="client_id" id="task-client-id" style="font-size: 1rem;">
                        <option value="">-- Sin cliente asignado --</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <label class="form-label" style="font-weight:700; margin:0;">Etiquetas (Tags)</label>
                        <button type="button" class="btn btn-outline btn-sm" style="padding:0.1rem 0.5rem; font-size:0.75rem;" onclick="openTagsManager()">
                            <i class="ph ph-gear"></i> Gestionar
                        </button>
                    </div>
                    <select class="form-control" name="tags[]" id="task-tags" multiple placeholder="Añadir etiquetas..."></select>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;"><i class="ph ph-link"></i> Enlaces Externos</label>
                    <textarea class="form-control" name="external_links" id="task-external-links" rows="2" placeholder="Links de Figma, Canva, Adobe XD (uno por línea)..."></textarea>
                </div>

                <!-- Subida de referencias generales -->
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Referencias (Imágenes/Moodboard)</label>
                    <input type="file" class="form-control" name="main_references[]" multiple accept="*/*" disabled id="inp-main-ref">
                    <small class="text-muted" id="warn-main-ref" style="display:block; margin-top:0.5rem;"><i class="ph ph-warning-circle"></i> Conecta Drive en "Archivos" para subir referencias.</small>
                    <div id="ref-images-container" style="margin-top:1rem;"></div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Asignar A</label>
                    <select class="form-control" name="assigned_to[]" id="task-assign" multiple placeholder="Seleccionar usuarios...">
                        <!-- Options loaded via JS -->
                    </select>
                </div>

                <div class="form-group" id="status-group" style="display: none; margin-top: 1.5rem;">
                    <label class="form-label" style="font-weight:700;">Estado</label>
                    <div class="pill-group">
                        <label class="pill-radio"><input type="radio" name="status" value="Pendiente" id="st-pen"><span class="pill-label">Pendiente</span></label>
                        <label class="pill-radio"><input type="radio" name="status" value="En progreso" id="st-prog"><span class="pill-label">En progreso</span></label>
                        <label class="pill-radio"><input type="radio" name="status" value="En revisión" id="st-rev"><span class="pill-label">En revisión</span></label>
                        <label class="pill-radio"><input type="radio" name="status" value="Terminado" id="st-ter"><span class="pill-label">Terminado</span></label>
                    </div>
                </div>
            </div>

            <!-- Pestaña Subtareas -->
            <div id="tab-subtasks" class="oc-tab-pane">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h3 style="margin:0;">Desglose de Tareas</h3>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addSubtaskCard()">
                        <i class="ph ph-plus"></i> Añadir Tarjeta
                    </button>
                </div>
                <div id="subtasks-container"></div>
                <div id="subtasks-empty" style="text-align:center; padding: 2rem; color:var(--text-muted);">
                    <i class="ph ph-cards" style="font-size:3rem; opacity:0.5;"></i>
                    <p>No hay subtareas registradas.</p>
                </div>
            </div>

            <!-- Pestaña Archivos (Drive) -->
            <div id="tab-files" class="oc-tab-pane">
                <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                        <div>
                            <h3 style="margin:0; font-size:1.1rem; color: var(--primary-color);">Conexión a Drive</h3>
                            <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">Elige la carpeta raíz del proyecto para alojar los archivos.</p>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="cdOpenPicker(null, setDriveFolder)">
                            <i class="ph ph-google-logo"></i> Seleccionar Carpeta
                        </button>
                    </div>
                    <div id="selected-folder-info" style="font-size:0.9rem; font-weight:600; color:var(--text-main);">
                        <i class="ph ph-warning-circle text-warning"></i> Ninguna carpeta seleccionada
                    </div>
                </div>
                
                <!-- Boton Generar Carpetas -->
                <div id="btn-generate-folders" style="display:none; text-align:center; margin-bottom: 2rem; border: 1px dashed var(--primary-color); padding: 2rem; border-radius: 12px;">
                    <button type="button" class="btn btn-outline" style="color:var(--primary-color); border-color:var(--primary-color);" onclick="generateFolderStructure()" id="generate-btn-element">
                        <i class="ph ph-magic-wand"></i> Generar Estructura de Carpetas
                    </button>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem; margin-bottom:0;">Creará automáticamente "Diseño y empaquetado" y "referencias".</p>
                </div>

                <div id="upload-areas" style="display: none;">
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label" style="font-weight:700;"><i class="ph ph-paint-brush"></i> Subir Diseños y Empaquetado</label>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:-0.5rem; margin-bottom:0.5rem;">Sube múltiples carpetas o archivos que irán a "Diseño y empaquetado".</p>
                        <!-- webkitdirectory allows folder upload -->
                        <input type="file" class="form-control" name="design_files[]" multiple webkitdirectory directory>
                    </div>
                    
                    <div id="existing-design-files"></div>
                </div>
                
                <div id="no-drive-warning" style="text-align:center; padding: 3rem 1rem; color:var(--text-muted);">
                    <i class="ph ph-folder-lock" style="font-size:3rem; opacity:0.5; margin-bottom:1rem;"></i>
                    <p>Selecciona una carpeta de Drive para habilitar la subida de archivos.</p>
                </div>

            </div>

            <!-- Pestaña Avances -->
            <div id="tab-avances" class="oc-tab-pane">
                <div style="background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.3); padding: 2rem; border-radius: 12px; text-align: center; margin-bottom: 2rem;" id="avances-paste-area">
                    <i class="ph ph-clipboard" style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;"></i>
                    <h3 style="margin:0 0 0.5rem 0;">Presiona Ctrl + V para pegar avance</h3>
                    <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">Haz captura de pantalla y pega aquí. Se subirá automáticamente a Drive.</p>
                    <div id="avance-upload-progress" style="margin-top: 1rem; font-size: 0.85rem; color: var(--primary-color); display: none;">Subiendo imagen...</div>
                </div>

                <div id="avances-gallery" style="display:flex; flex-direction:column; gap:1rem;">
                    <!-- Avances subidos aparecerán aquí -->
                </div>
                <div id="avances-empty" style="text-align:center; padding: 2rem; color:var(--text-muted);">
                    <p>No hay avances subidos todavía.</p>
                </div>
            </div>
        </form>
    </div>
    
    <div class="oc-footer">
        <button class="btn btn-outline" onclick="closeTaskModal()">Cerrar</button>
        <button class="btn btn-primary" onclick="saveTask()">Guardar Tarea</button>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />

<script>
    function getContrastColor(hexColor) {
        if(!hexColor) return '#000000';
        if(hexColor.startsWith('#')) hexColor = hexColor.substring(1);
        if(hexColor.length === 3) hexColor = hexColor.split('').map(c => c+c).join('');
        let r = parseInt(hexColor.substr(0, 2), 16);
        let g = parseInt(hexColor.substr(2, 2), 16);
        let b = parseInt(hexColor.substr(4, 2), 16);
        let yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? '#000000' : '#ffffff';
    }
    let allTasks = [];
    let systemUsers = [];
    let calendar;
    let tomSelectAssign;
    let tomSelectTags;
    let subtaskIndex = 0;
    let foldersGenerated = false;
    let quillEditor;

    let localMainReferences = [];
    let localSubtaskReferences = {};

    const currentUserId = <?= $currentUserId ?>;
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    document.addEventListener('DOMContentLoaded', () => {
        Fancybox.bind("[data-fancybox]", {});

        // Initialize TomSelect
        tomSelectAssign = new TomSelect('#task-assign', {
            plugins: ['remove_button'],
            placeholder: 'Seleccionar...',
        });

        tomSelectTags = new TomSelect('#task-tags', {
            plugins: ['remove_button'],
            create: true,
            persist: false,
            placeholder: 'Etiquetas (ej: Logo, Web)...'
        });

        // Global Drag & Drop over offcanvas
        const ocPanel = document.getElementById('task-offcanvas');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            ocPanel.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            ocPanel.addEventListener(eventName, () => {
                if (foldersGenerated) ocPanel.style.boxShadow = 'inset 0 0 0 3px var(--primary-color)';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            ocPanel.addEventListener(eventName, () => {
                ocPanel.style.boxShadow = 'none';
            }, false);
        });
        
        ocPanel.addEventListener('drop', (e) => {
            if (!foldersGenerated) return;
            let dt = e.dataTransfer;
            let files = dt.files;
            if (files.length > 0) {
                const inp = document.getElementById('inp-main-ref');
                const dTrans = new DataTransfer();
                for(let i=0; i<inp.files.length; i++) dTrans.items.add(inp.files[i]);
                for(let i=0; i<files.length; i++) dTrans.items.add(files[i]);
                inp.files = dTrans.files;
                handleLocalFiles(inp.files, 'main');
                switchOcTab('details', document.querySelector('.oc-nav-item')); 
            }
        }, false);

        // Initialize Quill Editor
        quillEditor = new Quill('#task-desc-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            },
            placeholder: 'Instrucciones generales, detalles y especificaciones...'
        });

        // Title input listener to enable/disable generate folders button
        document.getElementById('task-title').addEventListener('input', function(e) {
            const btn = document.getElementById('generate-btn-element');
            if (e.target.value.trim() !== '') {
                btn.disabled = false;
                btn.title = '';
            } else {
                btn.disabled = true;
                btn.title = 'Debes ingresar un título para generar las carpetas';
            }
        });

        fetchUsers().then(() => {
            fetchTasks();
        });
        initSortable();
        initCalendar();

        document.addEventListener('paste', handlePaste);
        document.getElementById('inp-main-ref').addEventListener('change', function(e) {
            handleLocalFiles(e.target.files, 'main');
        });
    });

    function toggleViewFormBtn(selectEl) {
        const btn = document.getElementById('btn-view-form');
        if (selectEl.value) {
            btn.style.display = 'inline-flex';
        } else {
            btn.style.display = 'none';
        }
    }

    function openLinkedForm() {
        const selectEl = document.getElementById('task-linked-form');
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const submissionId = selectedOption.value;
        const title = selectedOption.text;

        if (submissionId) {
            const iframe = document.getElementById('dt-form-iframe');
            iframe.src = `modules/forms/view_submission.php?id=${submissionId}&mode=iframe`;
            document.getElementById('dt-form-modal-title').textContent = title;
            document.getElementById('dt-form-modal').classList.add('active');
        }
    }

    function handlePaste(e) {
        if (!document.getElementById('task-offcanvas').classList.contains('active')) return;
        if (!foldersGenerated) return; // Prevent paste if folders aren't generated

        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        let files = [];
        for (let index in items) {
            const item = items[index];
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                const blob = item.getAsFile();
                const file = new File([blob], "Pasted_Image_" + Date.now() + ".png", { type: item.type });
                files.push(file);
            }
        }
        
        if (files.length > 0) {
            e.preventDefault();
            const activeEl = document.activeElement;
            let targetSubtaskId = null;
            if (activeEl && activeEl.closest('.st-card')) {
                const stCard = activeEl.closest('.st-card');
                const fileInp = stCard.querySelector('.st-file-inp');
                if (fileInp) {
                    const match = fileInp.name.match(/st_files_(\d+)/);
                    if (match) targetSubtaskId = parseInt(match[1]);
                }
            }

            if (document.getElementById('tab-avances').classList.contains('active')) {
                uploadAvance(files[0]);
            } else if (targetSubtaskId !== null) {
                handleLocalFiles(files, 'subtask', targetSubtaskId);
            } else {
                handleLocalFiles(files, 'main');
                switchOcTab('details', document.querySelectorAll('.oc-nav-item')[0]);
            }
        }
    }

    async function uploadAvance(file) {
        const driveFolderId = document.getElementById('task-drive-folder').value;
        const taskId = document.getElementById('task-id').value;
        if (!driveFolderId) {
            alert('Debes generar la estructura de carpetas en la pestaña "Archivos" antes de subir avances.');
            return;
        }

        const progressEl = document.getElementById('avance-upload-progress');
        progressEl.style.display = 'block';

        try {
            const fd = new FormData();
            fd.append('action', 'upload_avance');
            fd.append('task_id', taskId);
            fd.append('drive_folder_id', driveFolderId);
            fd.append('avance_image', file);

            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                if (!taskId) {
                    // Pending attachment to be saved when task is saved
                    const hiddenInp = document.createElement('input');
                    hiddenInp.type = 'hidden';
                    hiddenInp.name = 'pending_avances[]';
                    hiddenInp.value = JSON.stringify(data.attachment);
                    document.getElementById('task-form').appendChild(hiddenInp);
                }
                renderAvance(data.attachment);
            } else {
                alert(data.error || 'Error al subir avance');
            }
        } catch(e) {
            console.error(e);
            alert('Error de red');
        } finally {
            progressEl.style.display = 'none';
        }
    }

    function renderAvance(att) {
        const gallery = document.getElementById('avances-gallery');
        document.getElementById('avances-empty').style.display = 'none';
        gallery.innerHTML += renderAvanceCard(att);
    }

    function handleLocalFiles(files, type, subtaskId = null) {
        const dataTransfer = new DataTransfer();
        
        if (type === 'main') {
            for(let f of files) localMainReferences.push(f);
            localMainReferences.forEach(f => dataTransfer.items.add(f));
            document.getElementById('inp-main-ref').files = dataTransfer.files;
            renderLocalPreviews('main');
        } else {
            if (!localSubtaskReferences[subtaskId]) localSubtaskReferences[subtaskId] = [];
            for(let f of files) localSubtaskReferences[subtaskId].push(f);
            localSubtaskReferences[subtaskId].forEach(f => dataTransfer.items.add(f));
            const inp = document.querySelector(`input[name="st_files_${subtaskId}[]"]`);
            if (inp) inp.files = dataTransfer.files;
            renderLocalPreviews('subtask', subtaskId);
        }
    }

    function removeLocalFile(index, type, subtaskId = null) {
        const dataTransfer = new DataTransfer();
        if (type === 'main') {
            localMainReferences.splice(index, 1);
            localMainReferences.forEach(f => dataTransfer.items.add(f));
            document.getElementById('inp-main-ref').files = dataTransfer.files;
            renderLocalPreviews('main');
        } else {
            localSubtaskReferences[subtaskId].splice(index, 1);
            localSubtaskReferences[subtaskId].forEach(f => dataTransfer.items.add(f));
            const inp = document.querySelector(`input[name="st_files_${subtaskId}[]"]`);
            if (inp) inp.files = dataTransfer.files;
            renderLocalPreviews('subtask', subtaskId);
        }
    }

    function renderLocalPreviews(type, subtaskId = null) {
        let container, files;
        if (type === 'main') {
            container = document.getElementById('ref-images-container');
            files = localMainReferences;
        } else {
            container = document.getElementById(`st-local-previews-${subtaskId}`);
            files = localSubtaskReferences[subtaskId] || [];
        }

        let localContainer = container.querySelector('.local-previews-grid');
        if (!localContainer) {
            localContainer = document.createElement('div');
            localContainer.className = 'thumb-grid local-previews-grid';
            localContainer.style.marginTop = '0.5rem';
            container.appendChild(localContainer);
        }
        
        localContainer.innerHTML = '';
        files.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            localContainer.innerHTML += `
                <div class="thumb-item">
                    <a href="${url}" data-fancybox="gallery">
                        <img src="${url}" alt="Preview">
                    </a>
                    <button type="button" class="thumb-btn-del" onclick="removeLocalFile(${index}, '${type}', ${subtaskId})"><i class="ph ph-x"></i></button>
                </div>
            `;
        });
    }

    async function fetchUsers() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_users');
            const data = await res.json();
            if(data.success) {
                systemUsers = data.data;
                systemUsers.forEach(u => {
                    tomSelectAssign.addOption({value: u.id, text: u.name});
                });
            }
        } catch(e) { console.error(e); }
    }

    async function fetchTasks() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_tasks');
            const data = await res.json();
            if(data.success) {
                allTasks = data.data;
                renderKanban();
                renderCalendar();
            }
        } catch(e) { console.error(e); }
    }

    function switchView(view) {
        document.getElementById('tab-kanban').classList.remove('active');
        document.getElementById('tab-calendar').classList.remove('active');
        document.getElementById('tab-list').classList.remove('active');
        document.getElementById('tab-trash').classList.remove('active');
        
        document.getElementById('dt-kanban-view').style.display = 'none';
        document.getElementById('dt-calendar-view').style.display = 'none';
        document.getElementById('dt-list-view').style.display = 'none';
        document.getElementById('dt-trash-view').style.display = 'none';

        if (view === 'kanban') {
            document.getElementById('tab-kanban').classList.add('active');
            document.getElementById('dt-kanban-view').style.display = 'flex';
        } else if (view === 'list') {
            document.getElementById('tab-list').classList.add('active');
            document.getElementById('dt-list-view').style.display = 'block';
        } else if (view === 'trash') {
            document.getElementById('tab-trash').classList.add('active');
            document.getElementById('dt-trash-view').style.display = 'block';
            fetchTrash();
        } else {
            document.getElementById('tab-calendar').classList.add('active');
            document.getElementById('dt-calendar-view').style.display = 'block';
            calendar.render(); 
        }
    }

    function renderKanban() {
        const cols = ['Pendiente', 'En progreso', 'En revisión', 'Terminado'];
        cols.forEach(status => {
            document.getElementById('col-' + status).innerHTML = '';
            document.getElementById('count-' + status).innerText = '0';
        });

        const counts = {'Pendiente':0, 'En progreso':0, 'En revisión':0, 'Terminado':0};

        allTasks.forEach(task => {
    if (counts[task.status] !== undefined) {
        let dateFormatted = '';
        if(task.due_date) {
            const d = new Date(task.due_date);
            dateFormatted = d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } else {
            dateFormatted = '-';
        }

        let priorityColor = '#3b82f6';
        let priorityText = 'SIN ETIQUETA';
        let contrastColor = '#ffffff';

        if (task.tags && task.tags.length > 0) {
            priorityColor = task.tags[0].color;
            priorityText = task.tags[0].name;
            if (typeof getContrastColor === 'function') {
                contrastColor = getContrastColor(priorityColor);
            }
        } else {
            if (task.priority === 'alta') { priorityColor = '#ef4444'; priorityText = 'ALTA PRIORIDAD'; }
            else if (task.priority === 'media') { priorityColor = '#f97316'; priorityText = 'PRIORIDAD MEDIA'; }
            else if (task.priority === 'baja') { priorityColor = '#22c55e'; priorityText = 'BAJA PRIORIDAD'; }
        }

        let avatarsHtml = '';
        if(task.assigned_to && task.assigned_to.length > 0) {
            task.assigned_to.slice(0, 3).forEach((uid, idx) => {
                const u = systemUsers.find(x => x.id == uid);
                if(u) {
                    const initial = u.name.charAt(0).toUpperCase();
                    const ml = idx > 0 ? '-8px' : '0';
                    if (u.avatar && u.avatar.trim() !== '') {
                        avatarsHtml += `<img src="${u.avatar}" style="width:26px; height:26px; border-radius:50%; object-fit:cover; border:2px solid var(--bg-surface); margin-left:${ml};" title="${u.name}">`;
                    } else {
                        avatarsHtml += `<div style="width:26px; height:26px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:10px; border:2px solid var(--bg-surface); margin-left:${ml}; font-weight:bold;" title="${u.name}">${initial}</div>`;
                    }
                }
            });
        }

        let attachCount = task.attachments ? task.attachments.length : 0;
        let subtaskCount = task.subtasks ? task.subtasks.length : 0;
        let desc = task.description ? task.description.replace(/<[^>]*>?/gm, '') : '';

        const el = document.createElement('div');
        el.className = 'dt-task';
        el.dataset.id = task.id;
        el.style.padding = '0';
        el.style.background = 'transparent';
        el.style.border = 'none';
        el.style.boxShadow = 'none';

        let innerBoxBg = document.body.getAttribute('data-theme') === 'dark' ? '#1e1e1e' : '#f9f9f9';

        el.innerHTML = `
            <div class="dt-new-card-wrap" style="border-radius: 16px; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1rem; position: relative;">
                
                <!-- Top Banner -->
                <div style="background: ${priorityColor}; color: ${contrastColor}; text-align: center; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; padding: 0.4rem 1rem; text-transform: uppercase;">
                    ${priorityText}
                </div>

                <div style="padding: 0.75rem;">
                    <!-- Inner Dashed Box -->
                    <div style="border: 1px dashed var(--border-color); border-radius: 12px; padding: 1rem; position: relative; background: var(--bg-color);">
                        
                        <!-- Title -->
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.5rem; line-height: 1.2;">
                            ${task.title}
                        </div>
                        
                        <!-- Description -->
                        ${desc.trim() !== '' ? `
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            ${desc}
                        </div>
                        ` : '<div style="margin-bottom: 1.25rem;"></div>'}

                        <!-- Avatars and Status -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                            <div style="display: flex;">
                                ${avatarsHtml}
                            </div>
                            
                            <div style="background: var(--bg-color); color: var(--text-main); font-size: 0.7rem; font-weight: 600; padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid rgba(150,150,150,0.1);">
                                ${task.status}
                            </div>
                        </div>

                    </div>

                    <!-- Bottom Stats Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding: 0 0.5rem;">
                        <div style="display: flex; gap: 0.75rem; color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">
                            <div style="display:flex; align-items:center; gap:0.25rem;"><i class="ph ph-chat-circle"></i> 0</div>
                            <div style="display:flex; align-items:center; gap:0.25rem;"><i class="ph ph-link"></i> ${attachCount}</div>
                            <div style="display:flex; align-items:center; gap:0.25rem;"><i class="ph ph-folder"></i> ${subtaskCount}</div>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 500;">
                            ${dateFormatted}
                        </div>
                    </div>
                </div>
                ${task.timer_running == 1 ? '<div style="position:absolute; top: 6px; right: 8px; width:10px; height:10px; border-radius:50%; background:#10b981; animation:pulse-urgent 1.5s infinite; border:2px solid white;"></div>' : ''}
            </div>
        `;

        el.onclick = () => openEditModalById(task.id);
        document.getElementById('col-' + task.status).appendChild(el);
        counts[task.status]++;
    }
});

        cols.forEach(status => {
            document.getElementById('count-' + status).innerText = counts[status];
        });

        renderListView();
    }

    function renderListView() {
        const tbody = document.getElementById('dt-list-body');
        tbody.innerHTML = '';
        if (allTasks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No hay tareas</td></tr>';
            return;
        }

        allTasks.forEach(task => {
            let dateFormatted = task.due_date ? new Date(task.due_date).toLocaleDateString() : '-';
            let timerBtn = `
                <button type="button" class="btn-icon" style="padding: 0.2rem; width: 24px; height: 24px; background: ${task.timer_running == 1 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'}; color: ${task.timer_running == 1 ? '#ef4444' : '#10b981'}; border-radius: 4px;" onclick="toggleTimer(${task.id}, ${task.timer_running})" title="${task.timer_running == 1 ? 'Detener Tiempo' : 'Iniciar Tiempo'}">
                    <i class="ph ${task.timer_running == 1 ? 'ph-stop' : 'ph-play'}"></i>
                </button>
            `;

            let row = document.createElement('tr');
            row.style.borderBottom = '1px solid rgba(150,150,150,0.1)';
            row.innerHTML = `
                <td data-label="T�tulo" style="padding:1rem; cursor:pointer;" onclick="openEditModalById(${task.id})"><div style="font-weight:600; text-align:right;">${task.title}</div></td>
                <td data-label="Estado" style="padding:1rem;"><span style="font-size:0.8rem; background:rgba(150,150,150,0.1); padding:0.2rem 0.5rem; border-radius:4px;">${task.status}</span></td>
                <td data-label="Prioridad" style="padding:1rem;"><span class="badge-priority ${task.priority}">${task.priority}</span></td>
                <td data-label="Fecha" style="padding:1rem;">${dateFormatted}</td>
                <td data-label="Asignados" style="padding:1rem;"><div style="display:flex; justify-content:flex-end;">
                    ${task.assigned_to.map(uid => {
                        const u = systemUsers.find(x => x.id == uid);
                        if(!u) return '';
                        if (u.avatar) return `<img src="${u.avatar}" style="width:24px; height:24px; border-radius:50%; object-fit:cover; margin-right:2px;" title="${u.name}">`;
                        return `<div style="width:24px; height:24px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:10px; margin-right:2px;" title="${u.name}">${u.name.charAt(0)}</div>`;
                    }).join('')}
                </div></td>
                <td data-label="Tiempo" style="padding:1rem;">
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.5rem;">
                        ${timerBtn}
                        <span class="${task.timer_running == 1 ? 'live-timer' : ''}" data-started="${task.timer_started_at || ''}" data-spent="${task.time_spent || 0}" style="font-size:0.85rem; font-weight:600;">${formatTimeSpent(task.time_spent, task.timer_running, task.timer_started_at)}</span>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function openEditModalById(id) {
        const task = allTasks.find(t => t.id == id);
        if (task) openEditModal(task);
    }

    function initSortable() {
        const cols = ['Pendiente', 'En progreso', 'En revisión', 'Terminado'];
        cols.forEach(status => {
            const el = document.getElementById('col-' + status);
            new Sortable(el, {
                group: 'kanban',
                animation: 150,
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const newStatus = evt.to.dataset.status;
                    const taskId = itemEl.dataset.id;
                    
                    const task = allTasks.find(t => t.id == taskId);
                    if(task && task.status !== newStatus) {
                        task.status = newStatus;
                        updateTaskStatus(taskId, newStatus);
                        renderKanban(); 
                    }
                },
            });
        });
    }

    async function updateTaskStatus(id, status) {
        try {
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('status', status);
            await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
        } catch(e) { console.error(e); }
    }

    function initCalendar() {
        const calendarEl = document.getElementById('calendar');
        const isMobile = window.innerWidth < 768;
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: isMobile ? 'listMonth' : 'dayGridMonth',
            locale: 'es',
            editable: true,
            droppable: true,
            eventDisplay: 'block',
            dayMaxEvents: 3, // Add limit to prevent infinite cell stretching
            contentHeight: 'auto', // Prevent rows from stretching to fit an aspect ratio
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: isMobile ? '' : 'dayGridMonth,timeGridWeek'
            },
            events: [],
            eventContent: function(arg) {
    const task = arg.event.extendedProps.task;
    if(!task) return;

    let dateFormatted = '';
    if(task.due_date) {
        const d = new Date(task.due_date);
        dateFormatted = d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } else {
        dateFormatted = '-';
    }

    let priorityColor = '#3b82f6';
    let priorityText = 'SIN ETIQUETA';
    let contrastColor = '#ffffff';

    if (task.tags && task.tags.length > 0) {
        priorityColor = task.tags[0].color;
        priorityText = task.tags[0].name;
        if (typeof getContrastColor === 'function') {
            contrastColor = getContrastColor(priorityColor);
        }
    } else {
        if (task.priority === 'alta') { priorityColor = '#ef4444'; priorityText = 'ALTA PRIORIDAD'; }
        else if (task.priority === 'media') { priorityColor = '#f97316'; priorityText = 'PRIORIDAD MEDIA'; }
        else if (task.priority === 'baja') { priorityColor = '#22c55e'; priorityText = 'BAJA PRIORIDAD'; }
    }

    let avatarsHtml = '';
    if(task.assigned_to && task.assigned_to.length > 0) {
        task.assigned_to.slice(0, 3).forEach((uid, idx) => {
            const u = systemUsers.find(x => x.id == uid);
            if(u) {
                const initial = u.name.charAt(0).toUpperCase();
                const ml = idx > 0 ? '-8px' : '0';
                if (u.avatar && u.avatar.trim() !== '') {
                    avatarsHtml += `<img src="${u.avatar}" style="width:22px; height:22px; border-radius:50%; object-fit:cover; border:2px solid var(--bg-surface); margin-left:${ml};" title="${u.name}">`;
                } else {
                    avatarsHtml += `<div style="width:22px; height:22px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:9px; border:2px solid var(--bg-surface); margin-left:${ml}; font-weight:bold;" title="${u.name}">${initial}</div>`;
                }
            }
        });
    }

    let attachCount = task.attachments ? task.attachments.length : 0;
    let subtaskCount = task.subtasks ? task.subtasks.length : 0;
    let desc = task.description ? task.description.replace(/<[^>]*>?/gm, '') : '';

    let html = `
        <div class="dt-new-card-wrap" style="border-radius: 12px; overflow: hidden; background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 0.5rem; position: relative; white-space: normal;">
            
            <div style="background: ${priorityColor}; color: ${contrastColor}; text-align: center; font-size: 0.6rem; font-weight: 800; letter-spacing: 0.05em; padding: 0.3rem 0.5rem; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ${priorityText}
            </div>

            <div style="padding: 0.5rem;">
                <div style="border: 1px dashed var(--border-color); border-radius: 8px; padding: 0.6rem; position: relative; background: var(--bg-color);">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.4rem; line-height: 1.2;">
                        ${task.title}
                    </div>
                    
                    ${desc.trim() !== '' ? `
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.75rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        ${desc}
                    </div>
                    ` : '<div style="margin-bottom: 0.75rem;"></div>'}

                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <div style="display: flex;">
                            ${avatarsHtml}
                        </div>
                        <div style="background: var(--bg-surface); color: var(--text-main); font-size: 0.65rem; font-weight: 600; padding: 0.2rem 0.4rem; border-radius: 4px; border: 1px solid rgba(150,150,150,0.1);">
                            ${task.status}
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; padding: 0 0.25rem;">
                    <div style="display: flex; gap: 0.5rem; color: var(--text-muted); font-size: 0.7rem; font-weight: 600;">
                        <div style="display:flex; align-items:center; gap:0.2rem;"><i class="ph ph-chat-circle"></i> 0</div>
                        <div style="display:flex; align-items:center; gap:0.2rem;"><i class="ph ph-link"></i> ${attachCount}</div>
                        <div style="display:flex; align-items:center; gap:0.2rem;"><i class="ph ph-folder"></i> ${subtaskCount}</div>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.7rem; font-weight: 500;">
                        ${dateFormatted}
                    </div>
                </div>
            </div>
            ${task.timer_running == 1 ? '<div style="position:absolute; top: 4px; right: 4px; width:8px; height:8px; border-radius:50%; background:#10b981; animation:pulse-urgent 1.5s infinite; border:1px solid white;"></div>' : ''}
        </div>
    `;
    return { html: html };
},
            eventDrop: async function(info) {
                const taskId = info.event.id;
                const newDateStr = info.event.start.toISOString().slice(0, 19).replace('T', ' ');
                
                const task = allTasks.find(t => t.id == taskId);
                if(task) task.due_date = newDateStr;
                
                try {
                    const fd = new FormData();
                    fd.append('action', 'update_date');
                    fd.append('id', taskId);
                    fd.append('due_date', newDateStr);
                    await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
                    renderKanban();
                } catch(e) {
                    console.error(e);
                    info.revert();
                }
            },
            eventClick: function(info) {
                const task = allTasks.find(t => t.id == info.event.id);
                if(task) openEditModal(task);
            }
        });
        calendar.render(); 
        checkCalendarView();
    }

    function checkCalendarView() {
        if (!calendar) return;
        if (window.innerWidth < 768) {
            if (calendar.view.type !== 'listMonth') {
                calendar.changeView('listMonth');
            }
        } else {
            if (calendar.view.type !== 'dayGridMonth') {
                calendar.changeView('dayGridMonth');
            }
        }
    }
    
    window.addEventListener('resize', checkCalendarView);

    function renderCalendar() {
        calendar.removeAllEvents();
        const events = allTasks.filter(t => t.due_date).map(t => {
            return {
                id: t.id,
                title: t.title,
                start: t.due_date,
                extendedProps: { task: t }
            };
        });
        calendar.addEventSource(events);
    }

    function switchOcTab(tabName, el) {
        document.querySelectorAll('.oc-nav-item').forEach(n => n.classList.remove('active'));
        el.classList.add('active');
        document.querySelectorAll('.oc-tab-pane').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
    }

    function setDriveFolder(folder) {
        document.getElementById('task-drive-folder').value = folder.id;
        document.getElementById('selected-folder-info').innerHTML = `<i class="ph ph-check-circle text-success"></i> Conectado a: <strong>${folder.name}</strong>`;
        
        if (!foldersGenerated) {
            document.getElementById('btn-generate-folders').style.display = 'block';
            document.getElementById('upload-areas').style.display = 'none';
        } else {
            document.getElementById('btn-generate-folders').style.display = 'none';
            document.getElementById('upload-areas').style.display = 'block';
        }
        document.getElementById('no-drive-warning').style.display = 'none';
    }

    async function generateFolderStructure() {
        const driveFolderId = document.getElementById('task-drive-folder').value;
        if(!driveFolderId) return;

        const btn = document.getElementById('generate-btn-element');
        const oldText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'generate_folder_structure');
            fd.append('drive_folder_id', driveFolderId);
            fd.append('task_title', document.getElementById('task-title').value);
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (data.new_folder_id) {
                    document.getElementById('task-drive-folder').value = data.new_folder_id;
                    document.getElementById('selected-folder-info').innerHTML = '<i class="ph ph-folder text-primary"></i> Carpeta de tarea generada y vinculada.';
                }
                foldersGenerated = true;
                document.getElementById('btn-generate-folders').style.display = 'none';
                document.getElementById('upload-areas').style.display = 'block';
                document.getElementById('inp-main-ref').disabled = false;
                document.getElementById('warn-main-ref').style.display = 'none';
                document.querySelectorAll('.st-file-inp').forEach(inp => inp.disabled = false);
            } else {
                alert(data.error || 'Error al generar carpetas');
            }
        } catch(e) {
            console.error(e);
            alert('Error de red');
        }
        btn.innerHTML = oldText;
        btn.disabled = false;
    }

    function initSubtasksSortable() {
        const container = document.getElementById('subtasks-container');
        new Sortable(container, {
            animation: 150,
            handle: '.st-card'
        });
    }

    function openTaskModal(taskId = null) {
        document.getElementById('task-form').reset();
        document.getElementById('task-id').value = '';
        if (quillEditor) quillEditor.root.innerHTML = '';
        document.getElementById('task-drive-folder').value = '';
        document.getElementById('task-client-id').value = '';
        document.getElementById('task-linked-form').value = '';
        toggleViewFormBtn(document.getElementById('task-linked-form'));
        document.getElementById('btn-delete-task').style.display = 'none';
        document.getElementById('btn-clone-task').style.display = 'none';
        tomSelectAssign.clear();
        document.getElementById('subtasks-container').innerHTML = '';
        document.getElementById('subtasks-empty').style.display = 'block';
        subtaskIndex = 0;
        
        initSubtasksSortable();
        document.getElementById('modal-title').innerText = taskId ? 'Editar Tarea' : 'Nueva Tarea';
        document.getElementById('status-group').style.display = 'none';
        
        document.getElementById('prio-media').checked = true;
        
        localMainReferences = [];
        localSubtaskReferences = {};
        
        document.getElementById('ref-images-container').innerHTML = '';
        document.getElementById('existing-design-files').innerHTML = '';
        document.getElementById('avances-gallery').innerHTML = '';
        document.getElementById('avances-empty').style.display = 'block';
        
        foldersGenerated = false;
        document.getElementById('task-drive-folder').value = '';
        document.getElementById('selected-folder-info').innerHTML = '<i class="ph ph-warning-circle text-warning"></i> Ninguna carpeta seleccionada';
        document.getElementById('upload-areas').style.display = 'none';
        document.getElementById('btn-generate-folders').style.display = 'none';
        document.getElementById('no-drive-warning').style.display = 'block';
        
        document.getElementById('inp-main-ref').disabled = true;
        document.getElementById('warn-main-ref').style.display = 'block';
        
        document.getElementById('oc-overlay').classList.add('active');
        document.getElementById('task-offcanvas').classList.add('active');
        switchOcTab('details', document.querySelector('.oc-nav-item')); 
    }

    function getDriveFileId(url) {
        if(!url) return null;
        let match = url.match(/\/d\/(.+?)\//);
        if (match) return match[1];
        match = url.match(/id=([^&]+)/);
        if (match) return match[1];
        return null;
    }

    window.handleImageError = function(img, id, url, name) {
        const parent = img.closest('.thumb-item');
        if (parent) {
            parent.innerHTML = `
                <div class="file-card" style="margin:0; width:100%; height:100%; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; padding:0.5rem;">
                    <i class="ph ph-image-broken" style="font-size:2rem; color:var(--text-muted); margin-bottom:0.5rem;"></i>
                    <a href="${url}" target="_blank" style="font-size:0.7rem; color:var(--primary-color); word-break:break-all;">Ver Original</a>
                </div>
                <button type="button" class="thumb-btn-del" onclick="deleteAttachment(${id})"><i class="ph ph-x"></i></button>
            `;
        }
    };

    function renderReferenceCard(att) {
        const fileId = getDriveFileId(att.file_path);
        let name = att.file_name || 'Archivo';
        let ext = name.split('.').pop().toLowerCase();
        let isImage = ['jpg','jpeg','png','gif','webp', 'heic'].includes(ext);

        let dateStr = '';
        if (att.created_at) {
            const d = new Date(att.created_at);
            // Si la fecha es válida, formatearla
            if (!isNaN(d.getTime())) {
                const day = d.getDate().toString().padStart(2, '0');
                const month = (d.getMonth() + 1).toString().padStart(2, '0');
                const hours = d.getHours() % 12 || 12;
                const minutes = d.getMinutes().toString().padStart(2, '0');
                const ampm = d.getHours() >= 12 ? 'PM' : 'AM';
                dateStr = `<div style="position:absolute; bottom:5px; left:5px; background:rgba(0,0,0,0.65); color:#fff; font-size:0.65rem; padding:0.2rem 0.4rem; border-radius:4px; pointer-events:none; z-index:5;"><i class="ph ph-clock"></i> ${day}/${month} - ${hours}:${minutes} ${ampm}</div>`;
            }
        }

        if (isImage && fileId) {
            // sz=w300 instead of w800 for much faster loading
            const thumbUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w300`;
            const fullUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w1920`;
            return `
                <div class="thumb-item" id="att-${att.id}" onmouseenter="this.querySelector('.pin-btn').style.opacity='1'" onmouseleave="this.querySelector('.pin-btn').style.opacity='0'">
                    <a href="${fullUrl}" data-fancybox="gallery">
                        <img src="${thumbUrl}" alt="Referencia" onerror="handleImageError(this, ${att.id}, '${att.file_path}', '${name}')">
                    </a>
                    <button type="button" class="btn btn-primary pin-btn" style="position:absolute; top:5px; left:5px; padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:4px; opacity:0; transition:opacity 0.2s; z-index:5;" onclick="openPinModal(${att.id}, '${fullUrl}')">
                        <i class="ph ph-chat-circle"></i> Pines
                    </button>
                    ${dateStr}
                    <button type="button" class="thumb-btn-del" onclick="deleteAttachment(${att.id})"><i class="ph ph-x"></i></button>
                </div>
            `;
        }

        return renderFileCard(att);
    }

    function renderAvanceCard(att) {
        const fileId = getDriveFileId(att.file_path);
        let name = att.file_name || 'Avance';
        
        let dateStr = 'Recién subido';
        let timeStr = '';
        if (att.created_at) {
            const d = new Date(att.created_at);
            if (!isNaN(d.getTime())) {
                const day = d.getDate().toString().padStart(2, '0');
                const month = (d.getMonth() + 1).toString().padStart(2, '0');
                const year = d.getFullYear();
                const hours = d.getHours() % 12 || 12;
                const minutes = d.getMinutes().toString().padStart(2, '0');
                const ampm = d.getHours() >= 12 ? 'PM' : 'AM';
                dateStr = `${day}/${month}/${year}`;
                timeStr = `${hours}:${minutes} ${ampm}`;
            }
        }

        const thumbUrl = fileId ? `https://drive.google.com/thumbnail?id=${fileId}&sz=w300` : '';
        const fullUrl = fileId ? `https://drive.google.com/thumbnail?id=${fileId}&sz=w1920` : att.file_path;

        return `
            <div id="att-${att.id}" style="display:flex; align-items:center; background:var(--card-bg); border:1px solid var(--border-color); border-radius:12px; padding:1rem; gap:1.5rem; position:relative; overflow:hidden; transition:border-color 0.2s;">
                <!-- Miniatura a la izquierda -->
                <a href="${fullUrl}" data-fancybox="gallery" style="flex-shrink:0; width:120px; height:80px; border-radius:8px; overflow:hidden; background:var(--bg-color); display:flex; align-items:center; justify-content:center;">
                    ${thumbUrl ? `<img src="${thumbUrl}" alt="Avance" style="width:100%; height:100%; object-fit:cover;" onerror="this.outerHTML='<i class=\\'ph ph-image\\' style=\\'font-size:2rem; color:var(--text-muted)\\'></i>'">` : `<i class="ph ph-image" style="font-size:2rem; color:var(--text-muted)"></i>`}
                </a>
                
                <!-- Info a la derecha -->
                <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                    <div style="font-size:1rem; font-weight:600; color:var(--text-color); margin-bottom:0.25rem;">${name}</div>
                    <div style="display:flex; align-items:center; gap:1rem; color:var(--text-muted); font-size:0.85rem;">
                        <span style="display:flex; align-items:center; gap:0.3rem;"><i class="ph ph-calendar"></i> ${dateStr}</span>
                        ${timeStr ? `<span style="display:flex; align-items:center; gap:0.3rem;"><i class="ph ph-clock"></i> ${timeStr}</span>` : ''}
                    </div>
                </div>

                <button type="button" class="btn-icon" style="color:var(--danger-color); position:absolute; right:1rem; top:50%; transform:translateY(-50%);" onclick="deleteAttachment(${att.id})">
                    <i class="ph ph-trash" style="font-size:1.2rem;"></i>
                </button>
            </div>
        `;
    }

    function renderFileCard(att) {
        let name = att.file_name || 'Archivo';
        let ext = name.split('.').pop().toLowerCase();
        let icon = 'ph-file';
        if(['jpg','jpeg','png','gif','webp'].includes(ext)) icon = 'ph-image';
        if(['pdf'].includes(ext)) icon = 'ph-file-pdf';
        if(['zip','rar','7z'].includes(ext)) icon = 'ph-file-zip';

        return `
            <div class="file-card" id="att-${att.id}">
                <div class="file-info">
                    <i class="ph ${icon} file-icon" style="color:#3b82f6;"></i>
                    <div>
                        <div style="font-weight:600; color:var(--color-title); font-size:0.9rem;">${name}</div>
                        <a href="${att.file_path}" target="_blank" style="font-size:0.75rem; color:var(--primary-color);">Abrir archivo <i class="ph ph-arrow-square-out"></i></a>
                    </div>
                </div>
                <button type="button" class="btn btn-outline" style="color:var(--danger-color); border-color:var(--danger-color); padding: 0.2rem 0.5rem;" onclick="deleteAttachment(${att.id})">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
    }

    function openEditModal(task) {
        openTaskModal(task.id); 
        
        const d = document.getElementById('btn-delete-task');
        const c = document.getElementById('btn-clone-task');
        if(task) {
            document.getElementById('modal-title').innerText = 'Editar Tarea';
            document.getElementById('task-id').value = task.id;
            document.getElementById('task-title').value = task.title;
            if (quillEditor) quillEditor.clipboard.dangerouslyPasteHTML(task.description || '');
            document.getElementById('task-external-links').value = task.external_links || '';
            document.getElementById('task-client-id').value = task.client_id || '';
            if(task.priority) document.querySelector(`input[name="priority"][value="${task.priority}"]`).checked = true;
            if(task.status) {
                const statusRadio = document.querySelector(`input[name="status"][value="${task.status}"]`);
                if(statusRadio) statusRadio.checked = true;
            }
            if(task.linked_submission_id) {
                document.getElementById('task-linked-form').value = task.linked_submission_id;
                toggleViewFormBtn(document.getElementById('task-linked-form'));
            }
            document.getElementById('status-group').style.display = 'block';
            if (task.due_date) {
                document.getElementById('task-due-date').value = task.due_date.replace(' ', 'T').substring(0, 16);
            }
            if (isAdmin || task.created_by == currentUserId) d.style.display = 'flex';
            c.style.display = 'flex';
        }

        if (task.assigned_to) {
            tomSelectAssign.setValue(task.assigned_to);
        }

        if (task.tags) {
            tomSelectTags.clear();
            task.tags.forEach(t => {
                tomSelectTags.addOption({value: t.name, text: t.name});
            });
            tomSelectTags.setValue(task.tags.map(t => t.name));
        }

        if (task.drive_folder_id) {
            foldersGenerated = true; // Assume generated if folder exists on edit
            document.getElementById('task-drive-folder').value = task.drive_folder_id;
            document.getElementById('selected-folder-info').innerHTML = `<i class="ph ph-check-circle text-success"></i> Conectado a carpeta (ID: ${task.drive_folder_id.substring(0,8)}...)`;
            document.getElementById('upload-areas').style.display = 'block';
            document.getElementById('btn-generate-folders').style.display = 'none';
            document.getElementById('no-drive-warning').style.display = 'none';
            
            document.getElementById('inp-main-ref').disabled = false;
            document.getElementById('warn-main-ref').style.display = 'none';
        }

        // Subtasks
        const stContainer = document.getElementById('subtasks-container');
        stContainer.innerHTML = '';
        task.subtasks.forEach(st => {
            const stAttachments = task.attachments.filter(a => a.subtask_id == st.id);
            addSubtaskCard(st.id, st.title, st.description, st.is_completed, stAttachments, st.due_date);
        });

        // Main References
        const refContainer = document.getElementById('ref-images-container');
        refContainer.innerHTML = '<div class="thumb-grid server-previews-grid"></div>';
        const serverGrid = refContainer.querySelector('.server-previews-grid');
        
        const mainRefs = task.attachments.filter(a => a.attachment_type === 'reference');
        mainRefs.forEach(att => {
            serverGrid.innerHTML += renderReferenceCard(att);
        });

        // Design Files
        const desContainer = document.getElementById('existing-design-files');
        desContainer.innerHTML = '';
        const designFiles = task.attachments.filter(a => a.attachment_type === 'design');
        designFiles.forEach(att => {
            desContainer.innerHTML += renderFileCard(att);
        });

        // Avances
        const avancesContainer = document.getElementById('avances-gallery');
        const avancesEmpty = document.getElementById('avances-empty');
        avancesContainer.innerHTML = '';
        const avancesFiles = task.attachments.filter(a => a.attachment_type === 'avance');
        if (avancesFiles.length > 0) {
            avancesEmpty.style.display = 'none';
            avancesFiles.forEach(att => {
                avancesContainer.innerHTML += renderAvanceCard(att);
            });
        } else {
            avancesEmpty.style.display = 'block';
        }
    }

    function checkSubtasksEmpty() {
        const container = document.getElementById('subtasks-container');
        const emptyState = document.getElementById('subtasks-empty');
        if (container.children.length === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }

    async function cloneTask() {
        const taskId = document.getElementById('task-id').value;
        if (!taskId) return;
        
        if (confirm('¿Estás seguro de duplicar esta tarea y todas sus subtareas?')) {
            try {
                const fd = new FormData();
                fd.append('action', 'clone_task');
                fd.append('task_id', taskId);
                const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    closeTaskModal();
                    fetchTasks(); // Reload tasks
                } else {
                    alert(data.error || 'Error al clonar la tarea');
                }
            } catch(e) { console.error(e); }
        }
    }

    function addSubtaskCard(id = '', title = '', desc = '', isCompleted = 0, attachments = [], dueDate = null) {
        const container = document.getElementById('subtasks-container');
        const div = document.createElement('div');
        div.className = 'st-card';
        const checked = isCompleted == 1 ? 'checked' : '';
        const idx = subtaskIndex++;
        const dueDateFormatted = dueDate ? dueDate.replace(' ', 'T').substring(0, 16) : '';
        
        let attHtml = '';
        if (attachments.length > 0) {
            attHtml = '<div class="thumb-grid server-previews-grid" style="margin-top:0.5rem;">';
            attachments.forEach(att => {
                attHtml += renderReferenceCard(att);
            });
            attHtml += '</div>';
        }
        
        const disabledState = foldersGenerated ? '' : 'disabled';

        div.innerHTML = `
            <input type="hidden" name="st_ids[]" value="${id}">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="display:flex; align-items:center; gap:0.5rem; flex:1;">
                    <input type="hidden" name="st_comps[]" value="${isCompleted}">
                    <input type="checkbox" ${checked} onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" style="width: 20px; height: 20px; cursor: pointer;">
                    <input type="text" class="form-control" name="st_titles[]" value="${title}" placeholder="Título de la subtarea" required style="font-weight:600; font-size:1.05rem;">
                </div>
                <div style="display:flex; gap:0.5rem; margin-left:1rem;">
                    ${id ? `<button type="button" class="btn-icon" style="color:var(--primary-color);" onclick="convertSubtaskToTask(${id})" title="Convertir a Tarea"><i class="ph ph-share-network"></i></button>` : ''}
                    <button type="button" class="btn-icon text-red" onclick="this.closest('.st-card').remove(); checkSubtasksEmpty();" title="Eliminar Subtarea"><i class="ph ph-trash"></i></button>
                </div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <textarea class="form-control" name="st_descs[]" rows="2" placeholder="Descripción de la subtarea..." style="flex:1;">${desc}</textarea>
                <div style="width: 200px;">
                    <input type="datetime-local" class="form-control" name="st_due_dates[]" value="${dueDateFormatted}" style="font-size:0.8rem; padding:0.5rem;">
                </div>
            </div>
            <div style="margin-top:0.5rem;">
                <label class="form-label" style="font-size:0.8rem; font-weight:600;"><i class="ph ph-paperclip"></i> Subir Referencias (Subtarea)</label>
                <input type="file" class="form-control st-file-inp" name="st_files_${idx}[]" multiple accept="*/*" ${disabledState} style="font-size:0.8rem; padding:0.25rem;" onchange="handleLocalFiles(this.files, 'subtask', ${idx})">
                ${attHtml}
                <div id="st-local-previews-${idx}"></div>
            </div>
        `;
        container.appendChild(div);
        checkSubtasksEmpty();
    }

    async function saveTask() {
        // Sync Quill editor to hidden input
        document.getElementById('task-desc').value = quillEditor.root.innerHTML;

        const form = document.getElementById('task-form');
        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const hasFiles = document.querySelector('input[type="file"][name="main_references[]"]').files.length > 0 || 
                         document.querySelector('input[type="file"][name="design_files[]"]').files.length > 0;
        const hasDrive = document.getElementById('task-drive-folder').value !== '';
        
        if (hasFiles && (!hasDrive || !foldersGenerated)) {
            alert('Has seleccionado archivos pero no has generado las carpetas en Drive. Por favor selecciona la carpeta destino en la pestaña "Archivos" y haz clic en "Generar Estructura".');
            switchOcTab('files', document.querySelectorAll('.oc-nav-item')[2]);
            return;
        }

        const fd = new FormData(form);
        fd.append('action', 'save_task');

        let hasStFiles = false;
        document.querySelectorAll('.st-file-inp').forEach(inp => {
            if (inp.files.length > 0) hasStFiles = true;
        });
        if (hasStFiles && (!hasDrive || !foldersGenerated)) {
            alert('Has seleccionado referencias en las subtareas pero no has generado las carpetas en Drive. Ve a la pestaña "Archivos".');
            switchOcTab('files', document.querySelectorAll('.oc-nav-item')[2]);
            return;
        }

        const btn = document.querySelector('.oc-footer .btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check"></i> Iniciando...';
        btn.disabled = true;

        // Close modal immediately so user can continue working
        setTimeout(() => {
            closeTaskModal();
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 500);

        // Show global toast
        const toast = document.getElementById('global-upload-toast');
        const fill = toast.querySelector('.progress-bar-fill');
        const status = toast.querySelector('.toast-status');
        
        toast.style.display = 'flex';
        fill.style.width = '0%';
        status.innerText = 'Subiendo archivos... 0%';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'modules/design_tasks/ajax.php', true);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                fill.style.width = percentComplete + '%';
                status.innerText = `Subiendo archivos... ${percentComplete}%`;
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let text = xhr.responseText.trim();
                    if (text.indexOf('{') !== -1) {
                        text = text.substring(text.indexOf('{'), text.lastIndexOf('}') + 1);
                    }
                    const data = JSON.parse(text);
                    if (data.success) {
                        status.innerText = '¡Guardado correctamente!';
                        fill.style.background = '#10b981';
                        setTimeout(() => {
                            toast.style.display = 'none';
                            fill.style.background = 'var(--primary-color)';
                            fetchTasks();
                        }, 2000);
                    } else {
                        status.innerText = 'Error al guardar';
                        fill.style.background = '#ef4444';
                        alert(data.error || 'Error al guardar');
                        setTimeout(() => toast.style.display = 'none', 3000);
                    }
                } catch (e) {
                    console.error("Parse error. Raw response:", xhr.responseText);
                    status.innerText = 'Error de formato';
                    fill.style.background = '#ef4444';
                    setTimeout(() => toast.style.display = 'none', 3000);
                }
            } else {
                status.innerText = 'Error de conexión';
                fill.style.background = '#ef4444';
                setTimeout(() => toast.style.display = 'none', 3000);
            }
        };

        xhr.onerror = function() {
            status.innerText = 'Error de red';
            fill.style.background = '#ef4444';
            setTimeout(() => toast.style.display = 'none', 3000);
        };

        xhr.send(fd);
    }

    function deleteTask() {
        document.getElementById('delete-confirm-modal').classList.add('active');
    }
    function closeDeleteConfirm() {
        document.getElementById('delete-confirm-modal').classList.remove('active');
    }
    async function confirmDeleteTask() {
        closeDeleteConfirm();
        const id = document.getElementById('task-id').value;
        const fd = new FormData();
        fd.append('action', 'delete_task');
        fd.append('id', id);

        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                closeTaskModal();
                fetchTasks();
            }
        } catch(e) { console.error(e); }
    }

    async function deleteAttachment(id) {
        if(!confirm('¿Eliminar archivo? (Se quitará del sistema, pero seguirá existiendo en Drive si no lo borras manualmente)')) return;
        const fd = new FormData();
        fd.append('action', 'delete_attachment');
        fd.append('id', id);

        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                const el = document.getElementById('att-'+id);
                if(el) el.remove();
                allTasks.forEach(t => {
                    t.attachments = t.attachments.filter(a => a.id != id);
                });
            }
        } catch(e) { console.error(e); }
    }

    function closeTaskModal() {
        document.getElementById('oc-overlay').classList.remove('active');
        document.getElementById('task-offcanvas').classList.remove('active');
        document.getElementById('task-form').reset();
        document.getElementById('task-id').value = '';
        if (tomSelectAssign) tomSelectAssign.clear();
        if (tomSelectTags) {
            tomSelectTags.clear();
        }
    }

    async function convertSubtaskToTask(subtaskId) {
        if (!confirm('¿Convertir esta subtarea en una tarea principal? Se moverán sus archivos.')) return;
        try {
            const fd = new FormData();
            fd.append('action', 'convert_subtask_to_task');
            fd.append('subtask_id', subtaskId);
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                closeTaskModal();
                fetchTasks();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    async function toggleTimer(taskId, currentRunningState) {
        event.stopPropagation(); // prevent opening the modal
        try {
            const fd = new FormData();
            fd.append('action', 'update_timer');
            fd.append('task_id', taskId);
            fd.append('type', currentRunningState == 1 ? 'stop' : 'start');
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTasks();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    function formatTimeSpent(seconds, isRunning, startedAt) {
        let total = parseInt(seconds) || 0;
        if (isRunning && startedAt) {
            const start = new Date(startedAt.replace(' ', 'T')).getTime();
            const now = new Date().getTime();
            total += Math.floor((now - start) / 1000);
        }
        if (!total) return '0s';
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        
        if (h > 0) return `${h}h ${m}m`;
        if (m > 0) return `${m}m ${s}s`;
        return `${s}s`;
    }

    async function fetchTrash() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_trash');
            const data = await res.json();
            const tbody = document.getElementById('dt-trash-body');
            tbody.innerHTML = '';
            
            if(data.success && data.data.length > 0) {
                data.data.forEach(task => {
                    const dateFormatted = task.deleted_at ? new Date(task.deleted_at).toLocaleString() : '-';
                    tbody.innerHTML += `
                        <tr style="border-bottom:1px solid rgba(150,150,150,0.1);">
                            <td style="padding:1rem; font-weight:600; text-decoration:line-through; color:var(--text-muted);">${task.title}</td>
                            <td style="padding:1rem;">${task.status}</td>
                            <td style="padding:1rem;">${dateFormatted}</td>
                            <td style="padding:1rem; text-align:right;">
                                <button type="button" class="btn btn-primary" style="padding:0.25rem 0.5rem; font-size:0.8rem; margin-right:0.5rem;" onclick="restoreTask(${task.id})"><i class="ph ph-arrow-counter-clockwise"></i> Restaurar</button>
                                <button type="button" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.8rem;" onclick="forceDeleteTask(${task.id})"><i class="ph ph-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">La papelera está vacía</td></tr>';
            }
        } catch(e) { console.error(e); }
    }

    async function restoreTask(id) {
        if(!confirm('¿Restaurar esta tarea? Volverá al tablero.')) return;
        const fd = new FormData();
        fd.append('action', 'restore_task');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTrash();
                fetchTasks();
            }
        } catch(e) { console.error(e); }
    }

    async function forceDeleteTask(id) {
        if(!confirm('¿Eliminar PERMANENTEMENTE esta tarea? Esta acción NO se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('action', 'force_delete_task');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTrash();
            }
        } catch(e) { console.error(e); }
    }

    setInterval(() => {
        document.querySelectorAll('.live-timer').forEach(el => {
            const startedAt = el.dataset.started;
            const spent = parseInt(el.dataset.spent) || 0;
            if (startedAt) {
                const start = new Date(startedAt.replace(' ', 'T')).getTime();
                const now = new Date().getTime();
                const total = spent + Math.floor((now - start) / 1000);
                
                const h = Math.floor(total / 3600);
                const m = Math.floor((total % 3600) / 60);
                const s = total % 60;
                
                let text = `${s}s`;
                if (h > 0) text = `${h}h ${m}m ${s}s`;
                else if (m > 0) text = `${m}m ${s}s`;
                
                el.innerText = text;
            }
        });
    }, 1000);
    // ==========================================
    // IMAGE PIN MODAL LOGIC
    // ==========================================
    let currentPinAttachmentId = null;

    async function openPinModal(attachmentId, imgSrc) {
        currentPinAttachmentId = attachmentId;
        document.getElementById('pin-modal').style.display = 'flex';
        document.getElementById('pin-img').src = imgSrc;
        await loadPins(attachmentId);
    }

    function closePinModal() {
        document.getElementById('pin-modal').style.display = 'none';
        currentPinAttachmentId = null;
    }

    async function loadPins(attachmentId) {
        document.querySelectorAll('.img-pin').forEach(p => p.remove());
        try {
            const res = await fetch(`modules/design_tasks/ajax.php?action=fetch_pins&attachment_id=${attachmentId}`);
            const data = await res.json();
            if(data.success && data.data) {
                data.data.forEach(pin => {
                    renderPin(pin.id, pin.x_pos, pin.y_pos, pin.comment, pin.user_name, pin.user_avatar);
                });
            }
        } catch(e) { console.error(e); }
    }

    function renderPin(id, x, y, comment, userName, userAvatar) {
        const wrapper = document.getElementById('pin-img-wrapper');
        const pinHtml = document.createElement('div');
        pinHtml.className = 'img-pin';
        pinHtml.style.position = 'absolute';
        pinHtml.style.left = `${x}%`;
        pinHtml.style.top = `${y}%`;
        pinHtml.style.transform = 'translate(-50%, -50%)';
        pinHtml.style.cursor = 'pointer';
        pinHtml.style.zIndex = '10';
        
        let avatarStr = `<div style="width:28px;height:28px;border-radius:50%;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.5);">${userName.charAt(0)}</div>`;
        if (userAvatar) {
            avatarStr = `<img src="${userAvatar}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.5);">`;
        }
        
        pinHtml.innerHTML = `
            ${avatarStr}
            <div class="pin-tooltip" style="display:none; position:absolute; top:35px; left:50%; transform:translateX(-50%); background:white; color:var(--text-main); padding:0.75rem; border-radius:8px; font-size:0.85rem; width:200px; text-align:left; box-shadow:0 10px 25px rgba(0,0,0,0.3); z-index:20;">
                <strong style="display:block; margin-bottom:0.25rem;">${userName}</strong>${comment}
            </div>
        `;
        
        pinHtml.onmouseenter = () => pinHtml.querySelector('.pin-tooltip').style.display = 'block';
        pinHtml.onmouseleave = () => pinHtml.querySelector('.pin-tooltip').style.display = 'none';
        
        wrapper.appendChild(pinHtml);
    }

    async function handleImageClick(e) {
        if (!currentPinAttachmentId) return;
        
        const rect = e.target.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        
        const comment = prompt("Añade un comentario en esta zona de la imagen:");
        if (comment && comment.trim() !== '') {
            const fd = new FormData();
            fd.append('action', 'save_pin');
            fd.append('attachment_id', currentPinAttachmentId);
            fd.append('x', x);
            fd.append('y', y);
            fd.append('comment', comment);
            
            try {
                const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
                const data = await res.json();
                if(data.success) {
                    const curUser = systemUsers.find(u => u.id == currentUserId);
                    renderPin(data.id, x, y, comment, curUser ? curUser.name : 'Yo', curUser ? curUser.avatar : '');
                }
            } catch(e) { console.error(e); }
        }
    }
    
    // ==========================================
    // TAGS MANAGER LOGIC
    // ==========================================
    let masterTags = [];
    
    async function loadMasterTags() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_master_tags');
            const data = await res.json();
            if(data.success) {
                masterTags = data.data;
                // Update TomSelect options
                if (tomSelectTags) {
                    tomSelectTags.clearOptions();
                    masterTags.forEach(t => {
                        tomSelectTags.addOption({value: t.name, text: t.name});
                    });
                }
                renderTagsList();
            }
        } catch(e) { console.error(e); }
    }

    function openTagsManager() {
        document.getElementById('tags-manager-modal').style.display = 'flex';
        loadMasterTags();
    }

    function closeTagsManager() {
        document.getElementById('tags-manager-modal').style.display = 'none';
        document.getElementById('tag-form').reset();
        document.getElementById('tag-id').value = '';
    }

    function renderTagsList() {
        const container = document.getElementById('tags-list');
        container.innerHTML = '';
        if (masterTags.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:var(--text-muted); font-size:0.85rem;">No hay etiquetas creadas.</div>';
            return;
        }
        
        masterTags.forEach(tag => {
            const el = document.createElement('div');
            el.style.display = 'flex';
            el.style.justifyContent = 'space-between';
            el.style.alignItems = 'center';
            el.style.padding = '0.5rem';
            el.style.background = 'var(--bg-color)';
            el.style.borderRadius = '6px';
            el.style.border = '1px solid rgba(150,150,150,0.1)';
            
            el.innerHTML = `
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <div style="width:16px; height:16px; border-radius:4px; background:${tag.color};"></div>
                    <span style="font-weight:600; font-size:0.9rem;">${tag.name}</span>
                </div>
                <div style="display:flex; gap:0.25rem;">
                    <button type="button" class="btn-icon" style="width:24px; height:24px; font-size:0.8rem;" onclick="editMasterTag(${tag.id}, '${tag.name.replace(/'/g, "\\'")}', '${tag.color}')"><i class="ph ph-pencil"></i></button>
                    <button type="button" class="btn-icon text-red" style="width:24px; height:24px; font-size:0.8rem;" onclick="deleteMasterTag(${tag.id})"><i class="ph ph-trash"></i></button>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function editMasterTag(id, name, color) {
        document.getElementById('tag-id').value = id;
        document.getElementById('tag-name').value = name;
        document.getElementById('tag-color').value = color;
    }

    async function saveMasterTag() {
        const fd = new FormData();
        fd.append('action', 'save_master_tag');
        fd.append('id', document.getElementById('tag-id').value);
        fd.append('name', document.getElementById('tag-name').value);
        fd.append('color', document.getElementById('tag-color').value);
        
        try {
            const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
            const data = await res.json();
            if(data.success) {
                document.getElementById('tag-form').reset();
                document.getElementById('tag-id').value = '';
                loadMasterTags();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    async function deleteMasterTag(id) {
        if(!confirm('¿Eliminar esta etiqueta maestra?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_master_tag');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
            const data = await res.json();
            if(data.success) {
                loadMasterTags();
            }
        } catch(e) { console.error(e); }
    }

    // Call loadMasterTags on init
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { loadMasterTags(); }, 500);
    });
</script>

<!-- TAGS MANAGER MODAL -->
<div id="tags-manager-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(3px);">
    <div style="background:var(--bg-surface); width:100%; max-width:400px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.3); display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:1.25rem; border-bottom:1px solid rgba(150,150,150,0.15);">
            <h3 style="margin:0; font-size:1.1rem;"><i class="ph ph-tag"></i> Gestor de Etiquetas</h3>
            <button type="button" class="btn-icon" onclick="closeTagsManager()"><i class="ph ph-x"></i></button>
        </div>
        <div style="padding:1.25rem; flex:1; overflow-y:auto; max-height:50vh;">
            <div id="tags-list" style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
                <!-- Tags rendered here -->
            </div>
            
            <form id="tag-form" onsubmit="event.preventDefault(); saveMasterTag();" style="background:var(--bg-color); padding:1rem; border-radius:8px; border:1px solid rgba(150,150,150,0.15);">
                <h4 style="margin:0 0 0.5rem 0; font-size:0.9rem;">Crear/Editar Etiqueta</h4>
                <input type="hidden" id="tag-id">
                <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                    <input type="text" id="tag-name" class="form-control" placeholder="Nombre (ej: Logo)" required style="flex:1;">
                    <input type="color" id="tag-color" class="form-control" value="#3b82f6" style="width:50px; padding:0 2px;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('tag-form').reset(); document.getElementById('tag-id').value='';">Limpiar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PIN MODAL -->
<div id="pin-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.9); z-index:9999; flex-direction:column;">
    <div style="display:flex; justify-content:space-between; padding:1rem; color:white; background:rgba(0,0,0,0.5);">
        <h3 style="margin:0;"><i class="ph ph-chat-circle"></i> Comentarios Visuales</h3>
        <button type="button" class="btn-icon" style="color:white;" onclick="closePinModal()"><i class="ph ph-x"></i></button>
    </div>
    <div style="flex:1; overflow:auto; display:flex; justify-content:center; align-items:center; padding:2rem; position:relative;" id="pin-container">
        <div style="position:relative; display:inline-block;" id="pin-img-wrapper">
            <img id="pin-img" src="" style="max-width:100%; max-height:80vh; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5); cursor:crosshair;" onclick="handleImageClick(event)">
            <!-- Pins will be injected here -->
        </div>
    </div>
</div>

<!-- GLOBAL UPLOAD TOAST -->
<div id="global-upload-toast">
    <div class="toast-header">
        <span><i class="ph ph-cloud-arrow-up"></i> Guardando Tarea</span>
        <i class="ph ph-spinner ph-spin" style="color: var(--primary-color);"></i>
    </div>
    <div class="progress-bar-container">
        <div class="progress-bar-fill"></div>
    </div>
    <div class="toast-status">Subiendo archivos... 0%</div>
</div>

<?php require_once 'includes/footer.php'; ?>














