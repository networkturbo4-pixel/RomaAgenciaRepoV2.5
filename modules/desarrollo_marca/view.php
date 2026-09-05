<?php
// modules/desarrollo_marca/view.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<div style='padding:2rem;'>ID de proyecto inválido.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener rol del usuario directamente de la base de datos (como en AJAX)
$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$role_id = $stmtRole->fetchColumn();

// Fetch project
if ($role_id == 1) {
    $stmt = $db->prepare("SELECT * FROM brand_projects WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("
        SELECT p.* FROM brand_projects p
        JOIN brand_project_users pu ON p.id = pu.project_id
        WHERE p.id = ? AND pu.user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
}
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo "<div style='padding:2rem;'>Proyecto no encontrado o no tienes acceso.</div>";
    exit;
}

// Fetch form submission and template details if linked
$form_data = [];
$form_template_fields = [];
$submission = null;
if (!empty($project['form_submission_id'])) {
    $stmtForm = $db->prepare("
        SELECT fs.*, ft.title as template_title, ft.fields_json 
        FROM form_submissions fs 
        LEFT JOIN form_templates ft ON fs.template_id = ft.id 
        WHERE fs.id = ?
    ");
    $stmtForm->execute([$project['form_submission_id']]);
    $submission = $stmtForm->fetch(PDO::FETCH_ASSOC);
    if ($submission) {
        if (!empty($submission['data_json'])) {
            $form_data = json_decode($submission['data_json'], true) ?: [];
        }
        if (!empty($submission['fields_json'])) {
            $rawFields = json_decode($submission['fields_json'], true) ?: [];
            foreach ($rawFields as $rf) {
                if (!empty($rf['id'])) {
                    $lbl = !empty($rf['label']) ? $rf['label'] : (!empty($rf['description']) ? $rf['description'] : 'Pregunta');
                    $form_template_fields[$rf['id']] = $lbl;
                    $form_template_fields['field_' . $rf['id']] = $lbl;
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
/* Reset and Base */
.view-container {
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
}
.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    background: var(--bg-surface, #fff);
    padding: 1.25rem 1.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
}
.view-header h1 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-main);
}
.view-header h1 .btn-back {
    color: var(--text-muted);
    text-decoration: none;
    padding: 0.5rem;
    border-radius: 10px;
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.view-header h1 .btn-back:hover {
    background: var(--border-color);
    color: var(--text-main);
    transform: translateX(-2px);
}

/* Layout 70/30 */
.view-layout {
    display: grid;
    grid-template-columns: 7fr 3fr;
    gap: 1.5rem;
}
@media (max-width: 1024px) {
    .view-layout {
        grid-template-columns: 1fr;
    }
}

/* Common Card Styles */
.v-card {
    background: var(--bg-surface, #fff);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
    margin-bottom: 1.5rem;
}
.v-card-header {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-main);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding-bottom: 0.85rem;
}
.v-card-header i {
    color: var(--primary-color, #4f46e5);
}

/* Left Column: Tareas Verticales */
.task-group {
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    margin-bottom: 1.25rem;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.task-group:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}
.task-group-header {
    background: var(--bg-surface, #f1f5f9);
    padding: 0.85rem 1.15rem;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-main);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    cursor: grab;
}
.task-group-header .group-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.task-group-header .group-title i {
    color: var(--text-muted);
}
.task-list {
    padding: 1rem;
    min-height: 60px;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.task-card {
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 10px;
    padding: 1rem 1.15rem;
    margin-bottom: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    cursor: grab;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -4px rgba(0,0,0,0.08);
    border-color: color-mix(in srgb, var(--primary-color) 50%, var(--border-color));
}
[data-theme="dark"] .task-card:hover {
    box-shadow: 0 6px 16px -4px rgba(0,0,0,0.4);
}
.task-card-title {
    font-weight: 600;
    color: var(--text-main);
    font-size: 0.95rem;
    margin-bottom: 0.35rem;
    line-height: 1.4;
}
.task-card-desc {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.btn-action {
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.15s ease;
    padding: 0.35rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-action:hover {
    background: var(--bg-color);
    color: var(--primary-color);
}
.btn-action.del:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* ==========================================================================
   Modern App Sidebar Widgets: Brief & Entregables
   ========================================================================== */
.v-card-sidebar {
    border-radius: 24px;
    padding: 1.5rem;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    background: var(--bg-surface, #141417);
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.2);
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    position: relative;
}

[data-theme="light"] .v-card-sidebar {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.06);
}

.sidebar-widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    padding-bottom: 0.85rem;
}

.sidebar-widget-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
}

.app-widget-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.app-widget-icon.purple {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 18%, transparent);
    color: var(--primary-color, #818cf8);
    border: 1px solid color-mix(in srgb, var(--primary-color, #6366f1) 35%, transparent);
}
.app-widget-icon.amber {
    background: color-mix(in srgb, #f59e0b 18%, transparent);
    color: #fbbf24;
    border: 1px solid color-mix(in srgb, #f59e0b 35%, transparent);
}
.app-widget-icon.blue {
    background: color-mix(in srgb, #3b82f6 18%, transparent);
    color: #60a5fa;
    border: 1px solid color-mix(in srgb, #3b82f6 35%, transparent);
}

.app-widget-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.app-btn-icon-sm {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.2s ease;
}
.app-btn-icon-sm:hover {
    background: var(--border-color);
    color: var(--text-main, #ffffff);
    transform: scale(1.05);
}

/* Brief Meta Header Pill */
.brief-meta-badges {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}
.brief-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.6rem;
    border-radius: 7px;
    font-size: 0.72rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--primary-color, #6366f1) 12%, transparent);
    color: var(--primary-color, #818cf8);
    border: 1px solid color-mix(in srgb, var(--primary-color, #6366f1) 25%, transparent);
}
.brief-pill-badge.respondent {
    background: var(--bg-color, #09090b);
    color: var(--text-muted, #94a3b8);
    border-color: var(--border-color);
}

/* Brief Preview Cards in Sidebar */
.brief-preview-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 0.2rem;
}
.brief-card-item {
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 14px;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    transition: border-color 0.2s ease;
}
[data-theme="light"] .brief-card-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.brief-card-item:hover {
    border-color: color-mix(in srgb, var(--primary-color, #6366f1) 40%, transparent);
}
.brief-q-badge {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--primary-color, #818cf8);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.brief-q-badge i {
    font-size: 0.85rem;
}
.brief-a-content {
    font-size: 0.86rem;
    color: var(--text-main, #ffffff);
    line-height: 1.5;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Brief View All CTA Button */
.brief-view-cta-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color, #6366f1) 14%, var(--bg-color));
    border: 1px solid color-mix(in srgb, var(--primary-color, #6366f1) 35%, transparent);
    color: var(--primary-color, #818cf8);
    font-weight: 700;
    font-size: 0.84rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.brief-view-cta-btn:hover {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 22%, var(--bg-color));
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

/* Google Drive Cloud Hub Card */
.app-drive-hub-card {
    background: color-mix(in srgb, #3b82f6 10%, var(--bg-color));
    border: 1px solid color-mix(in srgb, #3b82f6 28%, transparent);
    border-radius: 16px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    transition: all 0.25s ease;
}
.app-drive-hub-card:hover {
    border-color: color-mix(in srgb, #3b82f6 55%, transparent);
    box-shadow: 0 8px 24px -6px rgba(59, 130, 246, 0.25);
}
.drive-hub-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.drive-hub-brand {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.drive-hub-logo {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: color-mix(in srgb, #3b82f6 22%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #3b82f6;
    flex-shrink: 0;
}
.drive-hub-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.drive-hub-meta span.title {
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
}
.drive-hub-meta span.subtitle {
    font-size: 0.72rem;
    color: #93c5fd;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.drive-hub-meta span.subtitle .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}

.drive-hub-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.95rem;
    background: #2563eb;
    border-radius: 12px;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.drive-hub-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.45);
}

/* Deliverables App Grid Showcase */
.deliverables-app-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

.deliverable-card-item {
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 16px;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
[data-theme="light"] .deliverable-card-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.deliverable-card-item:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--primary-color, #6366f1) 45%, transparent);
    box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.25);
}

.deliverable-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.deliverable-icon-wrap.logo { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.deliverable-icon-wrap.manual { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.deliverable-icon-wrap.fonts { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.deliverable-icon-wrap.palette { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.deliverable-icon-wrap.add { background: color-mix(in srgb, var(--border-color) 60%, transparent); color: var(--text-muted); }

.deliverable-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.deliverable-info span.name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.deliverable-info span.type {
    font-size: 0.68rem;
    color: var(--text-muted, #94a3b8);
    font-weight: 600;
    text-transform: uppercase;
}

/* Modal Visor de Brief Completo */
.brief-modal-container {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}
.brief-modal-search {
    position: relative;
}
.brief-modal-search input {
    width: 100%;
    padding: 0.65rem 1rem 0.65rem 2.4rem;
    border-radius: 12px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    font-size: 0.88rem;
    box-sizing: border-box;
}
.brief-modal-search i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
}
.brief-modal-grid {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.brief-modal-row {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    text-align: left;
}
.brief-modal-row .q-title {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--primary-color, #818cf8);
}
.brief-modal-row .a-text {
    font-size: 0.92rem;
    color: var(--text-main);
    line-height: 1.6;
}

/* Timer CSS */
.modern-timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: background 0.3s;
}
.modern-timer.expired {
    background: rgba(239, 68, 68, 0.85);
}

/* ==========================================================================
   Global App Modal Dialog CSS (Light & Dark Theme)
   ========================================================================== */
.swal2-zero-pad {
    padding: 0 !important;
    overflow: hidden !important;
    border-radius: 28px !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
    background: var(--bg-surface, #121212) !important;
    box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45) !important;
}
.app-modal-actions {
    display: none !important;
}

.app-modal-dialog {
    text-align: left;
    background: var(--bg-surface, #121212);
    color: var(--text-main, #ffffff);
    display: flex;
    flex-direction: column;
    width: 100%;
}
.app-modal-header {
    padding: 1.5rem 2rem 1.25rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    background: var(--bg-surface, #121212);
}
.app-modal-title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.app-modal-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color, #4f46e5) 15%, transparent);
    color: var(--primary-color, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.app-modal-titles {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.app-modal-titles span {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #9ca3af);
}
.app-modal-titles h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
}
.app-close-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--bg-color, #1e1e1e);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    color: var(--text-muted, #9ca3af);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}
.app-close-circle:hover {
    background: var(--border-color, #333);
    color: var(--text-main, #fff);
    transform: rotate(90deg);
}

.app-modal-body {
    padding: 1.75rem 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-height: 65vh;
    overflow-y: auto;
}

.app-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.app-form-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted, #9ca3af);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.app-input-title {
    width: 100%;
    background: var(--bg-color, #0a0a0a);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    color: var(--text-main, #ffffff);
    font-size: 1.05rem;
    font-weight: 600;
    padding: 0.85rem 1.15rem;
    border-radius: 14px;
    outline: none;
    transition: all 0.2s ease;
}
.app-input-title:focus {
    border-color: var(--primary-color, #4f46e5);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}
.app-input-title::placeholder {
    color: var(--text-muted);
    opacity: 0.5;
    font-weight: 400;
}

/* ==========================================================================
   Redesigned Modern Status Selector (Matrix Cards)
   ========================================================================== */
.app-status-grid-new {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.6rem;
    width: 100%;
}
.app-status-card {
    background: var(--bg-surface, #141414);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 14px;
    padding: 0.65rem 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}
[data-theme="light"] .app-status-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.app-status-card:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}
.app-status-card .status-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.app-status-card .status-text-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow: hidden;
    flex: 1;
}
.app-status-card .status-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted, #94a3b8);
    white-space: nowrap;
    transition: color 0.2s ease;
}
.app-status-card .status-desc {
    font-size: 0.65rem;
    color: var(--text-muted, #64748b);
    white-space: nowrap;
    opacity: 0.8;
}

/* Color palettes per status */
.app-status-card[data-val="pending"] .status-icon-wrap {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
}
.app-status-card[data-val="in_progress"] .status-icon-wrap {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.app-status-card[data-val="review"] .status-icon-wrap {
    background: rgba(168, 85, 247, 0.12);
    color: #a855f7;
}
.app-status-card[data-val="completed"] .status-icon-wrap {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

/* Active status highlights */
.app-status-card.active[data-val="pending"] {
    background: rgba(148, 163, 184, 0.08);
    border-color: #94a3b8;
    box-shadow: 0 0 0 1px #94a3b8, 0 4px 12px rgba(148, 163, 184, 0.12);
}
.app-status-card.active[data-val="pending"] .status-name {
    color: var(--text-main, #ffffff);
}
[data-theme="light"] .app-status-card.active[data-val="pending"] .status-name {
    color: #334155;
}
.app-status-card.active[data-val="pending"] .status-icon-wrap {
    background: #94a3b8;
    color: #ffffff;
}

.app-status-card.active[data-val="in_progress"] {
    background: rgba(245, 158, 11, 0.08);
    border-color: #f59e0b;
    box-shadow: 0 0 0 1px #f59e0b, 0 4px 12px rgba(245, 158, 11, 0.15);
}
.app-status-card.active[data-val="in_progress"] .status-name {
    color: #f59e0b;
}
.app-status-card.active[data-val="in_progress"] .status-icon-wrap {
    background: #f59e0b;
    color: #ffffff;
}

.app-status-card.active[data-val="review"] {
    background: rgba(168, 85, 247, 0.08);
    border-color: #a855f7;
    box-shadow: 0 0 0 1px #a855f7, 0 4px 12px rgba(168, 85, 247, 0.15);
}
.app-status-card.active[data-val="review"] .status-name {
    color: #a855f7;
}
.app-status-card.active[data-val="review"] .status-icon-wrap {
    background: #a855f7;
    color: #ffffff;
}

.app-status-card.active[data-val="completed"] {
    background: rgba(16, 185, 129, 0.08);
    border-color: #10b981;
    box-shadow: 0 0 0 1px #10b981, 0 4px 12px rgba(16, 185, 129, 0.15);
}
.app-status-card.active[data-val="completed"] .status-name {
    color: #10b981;
}
.app-status-card.active[data-val="completed"] .status-icon-wrap {
    background: #10b981;
    color: #ffffff;
}

/* ==========================================================================
   Redesigned Timeline Range Date Pickers (Stacked Layout)
   ========================================================================== */
.app-timeline-range-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}
.app-timeline-tile {
    background: var(--bg-surface, #141414);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 14px;
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s ease;
    width: 100%;
    box-sizing: border-box;
}
[data-theme="light"] .app-timeline-tile {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.app-timeline-tile:focus-within {
    border-color: var(--primary-color, #6366f1);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color, #6366f1) 20%, transparent);
}
.app-timeline-tile.due-tile:focus-within {
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}
.btn-action-add-task {
    background: #10b981 !important;
    color: #ffffff !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
    cursor: pointer !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4) !important;
    transition: all 0.2s ease !important;
}
.btn-action-add-task:hover {
    background: #059669 !important;
    transform: scale(1.08) !important;
}
.btn-action-add-task i {
    color: #ffffff !important;
    font-size: 1.15rem !important;
    font-weight: bold !important;
}
.app-timeline-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.app-timeline-icon.start-icon {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}
.app-timeline-icon.due-icon {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.app-timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    overflow: hidden;
}
.app-timeline-tag {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted, #94a3b8);
}
.app-timeline-input {
    border: none;
    background: transparent;
    color: var(--text-main, #ffffff);
    font-size: 0.85rem;
    font-weight: 700;
    outline: none;
    width: 100%;
    font-family: inherit;
    cursor: pointer;
    padding: 0;
}
[data-theme="light"] .app-timeline-input {
    color: #1e293b;
}

.app-textarea {
    width: 100%;
    min-height: 120px;
    background: var(--bg-color, #0a0a0a);
    color: var(--text-main, #ffffff);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 14px;
    padding: 0.85rem 1.15rem;
    font-size: 0.9rem;
    line-height: 1.6;
    outline: none;
    resize: vertical;
    font-family: inherit;
    transition: all 0.2s ease;
}
.app-textarea:focus {
    border-color: var(--primary-color, #4f46e5);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}
.app-textarea::placeholder {
    color: var(--text-muted);
    opacity: 0.5;
}

.app-modal-footer {
    padding: 1.25rem 2rem;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    background: var(--bg-surface, #121212);
    border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
}
.btn-app-cancel {
    background: var(--bg-color, #1e1e1e);
    color: var(--text-muted, #9ca3af);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    padding: 0.65rem 1.4rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-app-cancel:hover {
    background: var(--border-color);
    color: var(--text-main);
}
.btn-app-submit {
    background: var(--primary-color, #22c55e);
    color: #ffffff;
    border: none;
    padding: 0.65rem 1.75rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: 0 4px 15px color-mix(in srgb, var(--primary-color, #22c55e) 40%, transparent);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-app-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--primary-color, #22c55e) 55%, transparent);
    filter: brightness(1.08);
}

/* ==========================================================================
   2-Column Wide Task Modal Layout (80% App-Style)
   ========================================================================== */
.task-modal-two-col {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 1.75rem;
    align-items: start;
}
@media (max-width: 1100px) {
    .task-modal-two-col {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

.task-modal-col-left {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    background: color-mix(in srgb, var(--bg-color) 45%, var(--bg-surface));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 20px;
    padding: 1.25rem 1.35rem;
}
[data-theme="light"] .task-modal-col-left {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.task-modal-col-right {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Right Column Section Cards */
.task-section-panel {
    background: color-mix(in srgb, var(--bg-color) 60%, var(--bg-surface));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 18px;
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
[data-theme="light"] .task-section-panel {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.task-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.task-section-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.task-section-title i {
    font-size: 1.15rem;
    color: var(--primary-color, #6366f1);
}

/* File Upload & Dropzone for Google Drive */
.task-dropzone-box {
    border: 1.5px dashed var(--border-color, rgba(255, 255, 255, 0.15));
    border-radius: 14px;
    padding: 1.25rem 1rem;
    text-align: center;
    background: var(--bg-color, #09090b);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}
[data-theme="light"] .task-dropzone-box {
    background: #ffffff;
    border-color: #cbd5e1;
}
.task-dropzone-box:hover,
.task-dropzone-box.dragover {
    border-color: #3b82f6;
    background: color-mix(in srgb, #3b82f6 8%, var(--bg-color));
    transform: translateY(-2px);
}
.task-dropzone-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: color-mix(in srgb, #3b82f6 18%, transparent);
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.task-dropzone-text {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-main, #ffffff);
}
.task-dropzone-hint {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
}

/* Attachment Items List */
.task-attachments-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 180px;
    overflow-y: auto;
    padding-right: 0.2rem;
}
.task-attachment-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 12px;
    gap: 0.75rem;
    transition: all 0.15s ease;
}
[data-theme="light"] .task-attachment-item {
    background: #ffffff;
    border-color: #e2e8f0;
}
.task-attachment-item:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);
}
.task-attachment-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    overflow: hidden;
    flex: 1;
}
.task-attachment-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.task-attachment-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow: hidden;
}
.task-attachment-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.task-attachment-size {
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.task-attachment-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

/* Subtasks and Tag Manager Enhancements */
.app-subtasks-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: var(--bg-color, #0a0a0a);
    padding: 0.85rem;
    border-radius: 16px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
}
[data-theme="light"] .app-subtasks-container {
    background: #ffffff;
    border-color: #e2e8f0;
}
.app-subtask-item {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    background: var(--bg-surface, #141414);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    padding: 0.75rem 0.85rem;
    border-radius: 12px;
    position: relative;
}
[data-theme="light"] .app-subtask-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.app-subtask-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.app-subtask-check {
    width: 18px;
    height: 18px;
    accent-color: var(--secondary-color, #10b981);
    cursor: pointer;
}
.app-subtask-input-title {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-main, #fff);
}
.app-subtask-input-desc {
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.8rem;
    color: var(--text-muted, #9ca3af);
    padding-left: 1.75rem;
    width: 100%;
}
.app-subtask-del {
    background: transparent;
    border: none;
    color: var(--text-muted, #9ca3af);
    cursor: pointer;
    font-size: 1rem;
    padding: 0.2rem;
    display: flex;
    align-items: center;
    border-radius: 6px;
}
.app-subtask-del:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

/* Tag Manager Modal Item */
.app-tag-mgmt-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--bg-color, #0a0a0a);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 14px;
    gap: 0.75rem;
}
/* App Header Timer & Autosave Indicator */
.app-header-timer {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(16, 185, 129, 0.15)) !important;
    border: 1px solid color-mix(in srgb, var(--primary-color, #6366f1) 40%, transparent) !important;
    padding: 0.45rem 1.15rem 0.45rem 0.6rem !important;
    border-radius: 9999px !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(10px) !important;
}

.timer-badge-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-color, #6366f1);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 0 12px color-mix(in srgb, var(--primary-color, #6366f1) 60%, transparent);
}

.timer-details-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    text-align: left;
}

.timer-sublabel {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted, #9ca3af);
}

.app-header-timer .timer-text {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-main, #ffffff);
    letter-spacing: 0.5px;
    font-variant-numeric: tabular-nums;
}
[data-theme="light"] .app-header-timer {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
}
[data-theme="light"] .app-header-timer .timer-badge-icon {
    background: #6366f1 !important;
    color: #ffffff !important;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.3) !important;
}
[data-theme="light"] .app-header-timer .timer-text {
    color: #1e293b !important;
}
[data-theme="light"] .app-header-timer .timer-sublabel {
    color: #64748b !important;
}

.app-header-timer.expired {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(239, 68, 68, 0.15)) !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
}
.app-header-timer.expired .timer-badge-icon {
    background: #ef4444;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.6);
}

/* Autosave Pulse Animation */
@keyframes syncPulse {
    0% { transform: scale(0.95); opacity: 0.7; }
    50% { transform: scale(1.15); opacity: 1; filter: brightness(1.2); }
    100% { transform: scale(0.95); opacity: 0.7; }
}
.syncing .sync-dot {
    background: #f59e0b !important;
    box-shadow: 0 0 10px #f59e0b !important;
    animation: syncPulse 0.8s infinite ease-in-out !important;
}

/* ==========================================================================
   Mobile & Responsive Optimization (< 900px)
   ========================================================================== */
.main-phases-card {
    border-radius: 24px;
    padding: 1.75rem;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.15);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

@media (max-width: 900px) {
    .view-container {
        padding: 0.35rem !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        overflow-x: hidden !important;
    }
    .view-layout {
        display: flex !important;
        flex-direction: column !important;
        gap: 1rem !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .view-main, .view-sidebar {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
    .view-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.85rem !important;
        padding: 1rem 0.85rem !important;
        border-radius: 18px !important;
        margin-bottom: 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .view-header-left {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
    }
    .view-header-left h1 {
        font-size: 1.15rem !important;
        word-break: break-word !important;
        line-height: 1.3 !important;
    }
    .view-header-right {
        width: 100% !important;
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
    }
    .app-header-timer {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        max-width: 100% !important;
        justify-content: center !important;
    }
    #app-sync-indicator {
        flex-shrink: 0 !important;
    }

    /* Fases y Tareas Main Card Header */
    .main-phases-card {
        padding: 0.85rem 0.75rem !important;
        border-radius: 18px !important;
        margin-bottom: 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .v-card-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.75rem !important;
        margin-bottom: 1rem !important;
        padding-bottom: 0.75rem !important;
    }
    .brand-header-actions {
        width: 100% !important;
        display: flex !important;
        gap: 0.5rem !important;
    }
    .brand-header-actions button {
        flex: 1 !important;
        justify-content: center !important;
        padding: 0.6rem 0.5rem !important;
        font-size: 0.82rem !important;
        min-height: 42px !important;
    }

    /* Task Groups & Header */
    .task-group {
        border-radius: 16px !important;
        margin-bottom: 0.85rem !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .task-group-header {
        padding: 0.75rem 0.85rem !important;
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .task-group-header .group-info {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }
    .task-group-header .group-title-text {
        font-size: 0.9rem !important;
        word-break: break-word !important;
    }
    .task-group-header .group-actions {
        display: flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }
    .task-group-header .btn-action {
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        font-size: 1rem !important;
        border-radius: 10px !important;
    }

    /* Task Cards */
    .task-list {
        padding: 0.65rem 0.5rem !important;
        gap: 0.65rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .task-card {
        padding: 0.85rem !important;
        border-radius: 14px !important;
        flex-direction: row !important;
        align-items: flex-start !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .task-card-title {
        font-size: 0.92rem !important;
        word-break: break-word !important;
    }
    .task-card-desc {
        word-break: break-word !important;
    }
    .task-card .btn-action.del {
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        border-radius: 8px !important;
    }

    /* Sidebar Widgets on Mobile */
    .v-card-sidebar {
        padding: 1rem 0.85rem !important;
        border-radius: 18px !important;
        margin-bottom: 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Modals Responsive */
    .swal2-container {
        padding: 0.35rem !important;
    }
    .swal2-popup.swal2-app-modal,
    .swal2-popup.swal2-zero-pad {
        width: 96vw !important;
        max-width: 96vw !important;
        margin: 0 auto !important;
        border-radius: 20px !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }
    .app-modal-dialog {
        border-radius: 20px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .app-modal-header {
        padding: 0.85rem 1rem !important;
    }
    .app-modal-body {
        padding: 1rem 0.85rem !important;
        max-height: 74vh !important;
        box-sizing: border-box !important;
    }
    .task-modal-two-col {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
    .task-modal-col-left {
        padding: 0.85rem !important;
        border-radius: 16px !important;
    }
    .task-section-panel {
        padding: 0.85rem !important;
        border-radius: 16px !important;
    }
    .app-modal-footer {
        flex-direction: column-reverse !important;
        gap: 0.65rem !important;
        padding: 0.85rem 1rem !important;
    }
    .app-modal-footer > div {
        width: 100% !important;
        justify-content: center !important;
    }
    .app-modal-footer button {
        flex: 1 !important;
        width: 100% !important;
        justify-content: center !important;
        min-height: 44px !important;
    }
}
</style>

<div class="view-container">
    <!-- Modern App Header -->
    <div class="view-header" style="background: var(--bg-surface); border-radius: 20px; padding: 1.25rem 1.75rem; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
        <div class="view-header-left" style="display: flex; align-items: center; gap: 1rem;">
            <a href="index.php?module=desarrollo_marca&action=index" class="btn-app-cancel" style="padding: 0.5rem 0.85rem; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;" title="Volver">
                <i class="ph-bold ph-arrow-left" style="font-size: 1.1rem;"></i>
            </a>
            <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Tablero de Proyecto</span>
                <h1 style="margin: 0; font-size: 1.45rem; font-weight: 700; color: var(--text-main); letter-spacing: -0.3px;">
                    <?php echo htmlspecialchars($project['title']); ?>
                </h1>
            </div>
        </div>
        <div class="view-header-right" style="display: flex; align-items: center; gap: 0.85rem;">
            <!-- Background Autosave Badge -->
            <div id="app-sync-indicator" style="display: inline-flex; align-items: center; gap: 0.45rem; background: var(--bg-color); border: 1px solid var(--border-color); padding: 0.45rem 0.9rem; border-radius: 9999px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); transition: all 0.3s ease;">
                <span class="sync-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block; box-shadow: 0 0 8px #10b981;"></span>
                <span class="sync-label">Sincronizado</span>
            </div>

            <?php if (!empty($project['due_date'])): ?>
                <div class="modern-timer app-header-timer" data-start="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>" data-due="<?php echo htmlspecialchars($project['due_date']); ?>">
                    <div class="timer-badge-icon"><i class="ph-bold ph-timer"></i></div>
                    <div class="timer-details-wrapper">
                        <span class="timer-sublabel">Tiempo Restante</span>
                        <span class="timer-text">Calculando...</span>
                    </div>
                </div>
            <?php else: ?>
                <span style="background: color-mix(in srgb, var(--primary-color) 15%, transparent); color: var(--primary-color); padding: 0.5rem 1.25rem; border-radius: 9999px; font-weight: 700; font-size: 0.85rem; border: 1px solid color-mix(in srgb, var(--primary-color) 30%, transparent);">
                    <?php echo htmlspecialchars($project['status']); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="view-layout">
        <!-- Izquierda (70%) -->
        <div class="view-main">
            <div class="v-card main-phases-card">
                <div class="v-card-header" style="justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.65rem; font-size: 1.15rem; font-weight: 700; color: var(--text-main);">
                        <div style="width: 36px; height: 36px; border-radius: 10px; background: color-mix(in srgb, var(--secondary-color, #10b981) 15%, transparent); color: var(--secondary-color, #10b981); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="ph-bold ph-kanban"></i>
                        </div>
                        Fases y Tareas
                    </div>
                    <div class="brand-header-actions" style="display: flex; align-items: center; gap: 0.5rem;">
                        <button onclick="openTemplateManagerModal()" class="btn-app-cancel" style="padding: 0.55rem 1.15rem; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; color: #818cf8; border-color: color-mix(in srgb, var(--primary-color, #6366f1) 30%, transparent); background: color-mix(in srgb, var(--primary-color, #6366f1) 12%, transparent);">
                            <i class="ph-bold ph-magic-wand"></i> Plantillas
                        </button>
                        <button onclick="openGroupModal()" class="btn-app-submit" style="background: var(--secondary-color, #10b981); padding: 0.55rem 1.25rem; font-size: 0.85rem; cursor: pointer;">
                            <i class="ph-bold ph-plus"></i> Añadir Grupo
                        </button>
                    </div>
                </div>
                <div id="project-global-progress-bar" style="margin-bottom: 1.5rem; display: none;"></div>
                <div id="task-groups-container">
                    <div style="text-align:center; padding: 3rem 1rem; color: var(--text-muted);">
                        <i class="ph-bold ph-spinner-gap" style="font-size:2rem; animation: spin 1s linear infinite; margin-bottom: 0.5rem; display: block;"></i>
                        Cargando fases y tareas...
                    </div>
                </div>
            </div>
        </div>

        <!-- Derecha (30%) -->
        <!-- Derecha (30%) -->
        <div class="view-sidebar">
            <!-- Resumen & Brief Widget -->
            <div class="v-card-sidebar">
                <div class="sidebar-widget-header">
                    <div class="sidebar-widget-title">
                        <div class="app-widget-icon purple">
                            <i class="ph-fill ph-notebook"></i>
                        </div>
                        <span>Resumen & Brief</span>
                    </div>
                    <div class="app-widget-actions">
                        <?php if (!empty($form_data)): ?>
                            <button type="button" class="app-btn-icon-sm" onclick="copyBriefText()" title="Copiar resumen del Brief">
                                <i class="ph-bold ph-copy"></i>
                            </button>
                            <button type="button" class="app-btn-icon-sm" onclick="openBriefModal()" title="Ampliar visor de Brief">
                                <i class="ph-bold ph-arrows-out-simple"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($form_data)): ?>
                    <div style="color:var(--text-muted); font-size:0.86rem; text-align:center; padding: 2rem 1.25rem; background: var(--bg-color); border-radius: 18px; border: 1px dashed var(--border-color); display: flex; flex-direction: column; align-items: center; gap: 0.6rem;">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: color-mix(in srgb, var(--primary-color, #6366f1) 12%, transparent); color: var(--primary-color, #818cf8); display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                            <i class="ph-bold ph-notebook"></i>
                        </div>
                        <span style="font-weight: 600; color: var(--text-main);">Sin formulario vinculado</span>
                        <p style="margin: 0; font-size: 0.76rem; color: var(--text-muted);">Edita el proyecto para vincular un formulario de Brief o agregar notas.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $briefItems = [];
                    $briefPlainText = "";
                    foreach ($form_data as $field => $answer) {
                        if (empty($answer)) continue;
                        if (is_array($answer)) $answer = implode(", ", $answer);
                        
                        // Map field label from template if found, otherwise clean field key
                        $fieldKeyClean = str_replace('field_', '', $field);
                        $label = $form_template_fields[$field] ?? ($form_template_fields[$fieldKeyClean] ?? ucwords(str_replace(['_', '-'], ' ', $fieldKeyClean)));
                        
                        $cleanLabel = htmlspecialchars($label);
                        $cleanAnswer = nl2br(htmlspecialchars($answer));
                        $plainAnswer = strip_tags($answer);
                        
                        $briefItems[] = [
                            'label' => $cleanLabel,
                            'answer_html' => $cleanAnswer,
                            'answer_plain' => $plainAnswer
                        ];
                        $briefPlainText .= "• " . $cleanLabel . ":\n" . $plainAnswer . "\n\n";
                    }
                    ?>

                    <?php if ($submission): ?>
                        <div class="brief-meta-badges">
                            <?php if (!empty($submission['correlativo'])): ?>
                                <span class="brief-pill-badge" title="Correlativo de registro">
                                    <i class="ph-bold ph-hash"></i> <?php echo htmlspecialchars($submission['correlativo']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($submission['respondent_name'])): ?>
                                <span class="brief-pill-badge respondent" title="Enviado por">
                                    <i class="ph-bold ph-user-circle"></i> <?php echo htmlspecialchars($submission['respondent_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="brief-preview-list">
                        <?php foreach (array_slice($briefItems, 0, 4) as $item): ?>
                            <div class="brief-card-item">
                                <div class="brief-q-badge">
                                    <i class="ph-bold ph-question"></i> <?php echo $item['label']; ?>
                                </div>
                                <div class="brief-a-content">
                                    <?php echo $item['answer_html']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($briefItems) > 4): ?>
                            <div style="text-align: center; font-size: 0.74rem; color: var(--text-muted); font-weight: 600; padding: 0.2rem 0;">
                                + <?php echo (count($briefItems) - 4); ?> preguntas adicionales en el visor
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="brief-view-cta-btn" onclick="openBriefModal()">
                        <i class="ph-bold ph-book-open-text"></i> Abrir Visor Completo del Brief
                    </button>

                    <script>
                        const projectBriefData = <?php echo json_encode($briefItems); ?>;
                        const projectBriefPlainText = <?php echo json_encode($briefPlainText); ?>;

                        function copyBriefText() {
                            if (navigator.clipboard && projectBriefPlainText) {
                                navigator.clipboard.writeText(projectBriefPlainText).then(() => {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Copiado!',
                                        text: 'El resumen del brief se copió al portapapeles.',
                                        timer: 1800,
                                        showConfirmButton: false,
                                        customClass: { popup: 'swal2-modern-popup' }
                                    });
                                });
                            }
                        }

                        function openBriefModal() {
                            let rowsHtml = projectBriefData.map(item => `
                                <div class="brief-modal-row brief-search-item" data-search="${(item.label + ' ' + item.answer_plain).toLowerCase()}">
                                    <div class="q-title"><i class="ph-bold ph-caret-right" style="color:var(--primary-color);"></i> ${item.label}</div>
                                    <div class="a-text">${item.answer_html}</div>
                                </div>
                            `).join('');

                            let modalHtml = `
                                <div class="app-modal-dialog" style="max-width: 820px;">
                                    <div class="app-modal-header">
                                        <div class="app-modal-title-group">
                                            <div class="app-modal-icon-badge" style="background: color-mix(in srgb, #6366f1 18%, transparent); color: #818cf8;">
                                                <i class="ph-fill ph-notebook"></i>
                                            </div>
                                            <div class="app-modal-titles">
                                                <span>Visor Interactivo</span>
                                                <h3>Brief del Cliente — <?php echo htmlspecialchars($project['title']); ?></h3>
                                            </div>
                                        </div>
                                        <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
                                    </div>
                                    <div class="app-modal-body brief-modal-container" style="max-height: 60vh; overflow-y: auto;">
                                        <div class="brief-modal-search">
                                            <i class="ph-bold ph-magnifying-glass"></i>
                                            <input type="text" placeholder="Buscar preguntas o respuestas en el brief..." oninput="filterBriefModal(this.value)">
                                        </div>
                                        <div class="brief-modal-grid" id="brief-modal-grid">
                                            ${rowsHtml}
                                        </div>
                                    </div>
                                    <div class="app-modal-footer" style="justify-content: space-between;">
                                        <button type="button" class="btn-app-cancel" onclick="copyBriefText()">
                                            <i class="ph-bold ph-copy"></i> Copiar Todo
                                        </button>
                                        <button type="button" class="btn-app-submit" onclick="Swal.close()">
                                            Cerrar Visor
                                        </button>
                                    </div>
                                </div>
                            `;

                            Swal.fire({
                                html: modalHtml,
                                width: '820px',
                                showConfirmButton: false,
                                showCancelButton: false,
                                customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' }
                            });
                        }

                        function filterBriefModal(val) {
                            val = (val || '').toLowerCase().trim();
                            document.querySelectorAll('.brief-search-item').forEach(el => {
                                let search = el.getAttribute('data-search') || '';
                                if (!val || search.includes(val)) {
                                    el.style.display = 'flex';
                                } else {
                                    el.style.display = 'none';
                                }
                            });
                        }
                    </script>
                <?php endif; ?>
            </div>

            <!-- Entregables y Archivos Widget -->
            <div class="v-card-sidebar">
                <div class="sidebar-widget-header">
                    <div class="sidebar-widget-title">
                        <div class="app-widget-icon amber">
                            <i class="ph-fill ph-folder-notch-open"></i>
                        </div>
                        <span>Entregables & Archivos</span>
                    </div>
                    <div class="app-widget-actions">
                        <span class="brief-pill-badge" style="background: rgba(245, 158, 11, 0.12); color: #fbbf24; border-color: rgba(245, 158, 11, 0.25);">
                            Recursos
                        </span>
                    </div>
                </div>

                <!-- Google Drive Cloud Hub -->
                <?php if (!empty($project['drive_folder_url'])): ?>
                    <div class="app-drive-hub-card">
                        <div class="drive-hub-top">
                            <div class="drive-hub-brand">
                                <div class="drive-hub-logo">
                                    <i class="ph-fill ph-google-drive-logo"></i>
                                </div>
                                <div class="drive-hub-meta">
                                    <span class="title">Google Drive</span>
                                    <span class="subtitle"><span class="status-dot"></span> Carpeta Sincronizada</span>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($project['drive_folder_url']); ?>" target="_blank" class="drive-hub-btn">
                            <span><i class="ph-fill ph-folder" style="font-size: 1.1rem; vertical-align: middle; margin-right: 0.35rem;"></i> Abrir Carpeta Cloud</span>
                            <i class="ph-bold ph-arrow-square-out"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div style="padding: 1rem; border-radius: 16px; background: var(--bg-color); border: 1px dashed var(--border-color); text-align: center; font-size: 0.8rem; color: var(--text-muted);">
                        <i class="ph-bold ph-cloud-slash" style="font-size: 1.5rem; display: block; margin-bottom: 0.35rem; opacity: 0.6;"></i>
                        Sin carpeta de Google Drive asignada.
                    </div>
                <?php endif; ?>

                <!-- Deliverables Categorized Hub -->
                <div class="deliverables-app-grid">
                    <div class="deliverable-card-item" onclick="<?php echo !empty($project['drive_folder_url']) ? "window.open('" . htmlspecialchars($project['drive_folder_url']) . "', '_blank')" : "openDeliverableModal('Logotipo & Vectores', 'SVG, AI, PNG en alta resolución en la carpeta de Drive.')"; ?>" title="Abrir archivos de Logo">
                        <div class="deliverable-icon-wrap logo">
                            <i class="ph-bold ph-vector-three"></i>
                        </div>
                        <div class="deliverable-info">
                            <span class="name">Logotipo & Vector</span>
                            <span class="type">SVG • AI • PNG</span>
                        </div>
                    </div>

                    <div class="deliverable-card-item" onclick="<?php echo !empty($project['drive_folder_url']) ? "window.open('" . htmlspecialchars($project['drive_folder_url']) . "', '_blank')" : "openDeliverableModal('Manual de Identidad', 'Lineamientos y manual de marca en PDF en la carpeta de Drive.')"; ?>" title="Abrir Manual de Marca">
                        <div class="deliverable-icon-wrap manual">
                            <i class="ph-bold ph-file-pdf"></i>
                        </div>
                        <div class="deliverable-info">
                            <span class="name">Manual de Marca</span>
                            <span class="type">Documento PDF</span>
                        </div>
                    </div>

                    <div class="deliverable-card-item" onclick="<?php echo !empty($project['drive_folder_url']) ? "window.open('" . htmlspecialchars($project['drive_folder_url']) . "', '_blank')" : "openDeliverableModal('Tipografías & Fuentes', 'Paquete de fuentes institucionales en la carpeta de Drive.')"; ?>" title="Abrir Tipografías">
                        <div class="deliverable-icon-wrap fonts">
                            <i class="ph-bold ph-text-t"></i>
                        </div>
                        <div class="deliverable-info">
                            <span class="name">Tipografías</span>
                            <span class="type">Paquete TTF/OTF</span>
                        </div>
                    </div>

                    <div class="deliverable-card-item" onclick="<?php echo !empty($project['drive_folder_url']) ? "window.open('" . htmlspecialchars($project['drive_folder_url']) . "', '_blank')" : "openDeliverableModal('Paleta & Colores', 'Códigos HEX, CMYK y Pantone en la carpeta de Drive.')"; ?>" title="Abrir Guía de Color">
                        <div class="deliverable-icon-wrap palette">
                            <i class="ph-bold ph-palette"></i>
                        </div>
                        <div class="deliverable-info">
                            <span class="name">Guía de Color</span>
                            <span class="type">HEX • CMYK</span>
                        </div>
                    </div>
                </div>

                <script>
                    function openDeliverableModal(title, desc) {
                        Swal.fire({
                            title: title,
                            text: desc,
                            icon: 'info',
                            confirmButtonText: 'Entendido',
                            customClass: { popup: 'swal2-modern-popup' }
                        });
                    }
                </script>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.polyfills.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />
<style>
/* Tagify Modern High-Contrast Theme (Light & Dark) */
.tagify {
    --tags-border-color: var(--border-color, #e2e8f0) !important;
    --tags-hover-border-color: var(--primary-color, #6366f1) !important;
    --tags-focus-border-color: var(--primary-color, #6366f1) !important;
    background: var(--bg-color, #09090b) !important;
    color: var(--text-main, #ffffff) !important;
    border-radius: 14px !important;
    padding: 0.4rem 0.65rem !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)) !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.35rem !important;
    align-items: center !important;
    min-height: 46px !important;
    box-sizing: border-box !important;
}
[data-theme="light"] .tagify {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
}

.tagify__tag {
    margin: 2px !important;
    background: transparent !important;
}

.tagify__tag > div {
    border-radius: 8px !important;
    padding: 0.35rem 0.75rem !important;
    background: var(--tag-bg, color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent)) !important;
    color: var(--tag-color, var(--primary-color, #6366f1)) !important;
    border: 1px solid var(--tag-border, color-mix(in srgb, var(--primary-color, #6366f1) 35%, transparent)) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
    display: inline-flex !important;
    align-items: center !important;
}

.tagify__tag > div::before {
    display: none !important;
}

.tagify__tag-text {
    color: var(--tag-color, var(--primary-color, #6366f1)) !important;
    font-weight: 700 !important;
    font-size: 0.84rem !important;
    letter-spacing: 0.2px !important;
    text-shadow: none !important;
}

.tagify__tag__removeBtn {
    color: var(--tag-color, var(--primary-color, #6366f1)) !important;
    opacity: 0.75 !important;
    margin-left: 6px !important;
    font-size: 0.95rem !important;
    transition: all 0.2s ease !important;
}
.tagify__tag__removeBtn:hover {
    opacity: 1 !important;
    color: #ef4444 !important;
    background: rgba(239, 68, 68, 0.15) !important;
    border-radius: 50% !important;
}

.tagify__input {
    color: var(--text-main, #ffffff) !important;
    font-size: 0.88rem !important;
    font-weight: 500 !important;
}
.tagify__input::before {
    color: var(--text-muted, #9ca3af) !important;
    opacity: 0.7 !important;
}

/* Tagify Dropdown Styling */
.tagify__dropdown {
    background: var(--bg-surface, #1e1e1e) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12)) !important;
    border-radius: 14px !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25) !important;
    overflow: hidden !important;
    z-index: 999999 !important;
}
[data-theme="light"] .tagify__dropdown {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
}
.tagify__dropdown__wrapper {
    background: transparent !important;
    border: none !important;
    padding: 0.4rem !important;
    box-shadow: none !important;
}
.tagify__dropdown__item {
    color: var(--text-main, #ffffff) !important;
    background: transparent !important;
    padding: 0.65rem 0.95rem !important;
    font-size: 0.88rem !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    margin: 2px 0 !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}
.tagify__dropdown__item:hover,
.tagify__dropdown__item--active {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent) !important;
    color: var(--primary-color, #6366f1) !important;
}
</style>
<script>
const PROJECT_ID = <?php echo $id; ?>;
let allTags = [];

document.addEventListener('DOMContentLoaded', () => {
    loadProjectTasks();
    loadTagsForTasks();
});

function loadTagsForTasks() {
    let fd = new FormData();
    fd.append('action', 'get_tags');
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if(data.success) allTags = data.tags; });
}

function loadProjectTasks() {
    let formData = new FormData();
    formData.append('action', 'get_project_tasks');
    formData.append('project_id', PROJECT_ID);

    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            renderTaskGroups(data.groups);
        }
    });
}

function renderTaskGroups(groups) {
    window.currentProjectGroups = groups || [];
    const container = document.getElementById('task-groups-container');
    container.innerHTML = '';

    // Calculate overall progress across groups, tasks, and subtasks
    let totalTasks = 0;
    let completedTasks = 0;
    let totalSubtasks = 0;
    let completedSubtasks = 0;
    let totalScore = 0;

    groups.forEach(g => {
        if (g.tasks && g.tasks.length > 0) {
            g.tasks.forEach(t => {
                totalTasks++;
                let subtasksList = t.subtasks || [];
                let subCount = subtasksList.length;
                let subDone = subtasksList.filter(s => s.completed == 1).length;
                totalSubtasks += subCount;
                completedSubtasks += subDone;

                if (t.status === 'completed') {
                    completedTasks++;
                    totalScore += 1.0;
                } else if (subCount > 0) {
                    let taskScore = subDone / subCount;
                    totalScore += taskScore;
                    if (subDone === subCount && subCount > 0) {
                        completedTasks++;
                    }
                } else if (t.status === 'review') {
                    totalScore += 0.75;
                } else if (t.status === 'in_progress') {
                    totalScore += 0.5;
                }
            });
        }
    });

    let progressPct = totalTasks > 0 ? Math.round((totalScore / totalTasks) * 100) : 0;
    let progBarEl = document.getElementById('project-global-progress-bar');
    if (progBarEl && totalTasks > 0) {
        let badgeClass = progressPct >= 100 ? '#10b981' : (progressPct > 20 ? '#6366f1' : '#94a3b8');
        progBarEl.style.display = 'block';
        progBarEl.innerHTML = `
            <div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.15rem 1.35rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.65rem; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); display:inline-flex; align-items:center; gap:0.45rem;">
                        <i class="ph-bold ph-chart-line-up" style="color: var(--primary-color, #6366f1); font-size:1.15rem;"></i> Escala de Progreso Global
                    </span>
                    <span style="font-size: 0.85rem; font-weight: 800; color: ${badgeClass}; background: color-mix(in srgb, ${badgeClass} 15%, transparent); border: 1px solid color-mix(in srgb, ${badgeClass} 30%, transparent); padding: 0.2rem 0.75rem; border-radius: 9999px; font-variant-numeric: tabular-nums;">
                        ${progressPct}%
                    </span>
                </div>
                <div style="width:100%; height:8px; border-radius:9999px; background:var(--border-color); overflow:hidden; margin-bottom:0.65rem;">
                    <div style="width:${progressPct}%; height:100%; border-radius:9999px; background:linear-gradient(90deg, #6366f1 0%, #10b981 100%); transition:width 0.6s cubic-bezier(0.4,0,0.2,1); box-shadow:0 0 10px rgba(16,185,129,0.3);"></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.78rem; color:var(--text-muted); font-weight:600; flex-wrap: wrap; gap: 0.5rem;">
                    <span style="display:inline-flex; align-items:center; gap:0.4rem; white-space: nowrap;"><i class="ph-bold ph-check-square" style="color:#6366f1; font-size:0.95rem;"></i> <b>${completedTasks}/${totalTasks}</b> tareas completadas</span>
                    <span style="display:inline-flex; align-items:center; gap:0.4rem; white-space: nowrap;"><i class="ph-bold ph-list-checks" style="color:#10b981; font-size:0.95rem;"></i> <b>${completedSubtasks}/${totalSubtasks}</b> subtareas completadas</span>
                </div>
            </div>
        `;
    } else if (progBarEl) {
        progBarEl.style.display = 'none';
    }

    if(groups.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding: 4rem 1.5rem; background: var(--bg-color); border-radius: 20px; border: 1px dashed var(--border-color);">
                <div style="width:64px; height:64px; border-radius:20px; background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); display:inline-flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:1.25rem;">
                    <i class="ph-bold ph-kanban"></i>
                </div>
                <h4 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:700; color:var(--text-main);">No hay grupos de tareas aún</h4>
                <p style="margin:0 0 1.5rem 0; font-size:0.88rem; color:var(--text-muted); max-width:480px; margin-left:auto; margin-right:auto;">Crea tus fases manualmente o usa una plantilla predeterminada para cargar automáticamente todo el proceso de trabajo con un solo clic.</p>
                <div style="display:flex; justify-content:center; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                    <button onclick="openTemplateManagerModal()" class="btn-app-submit" style="background: var(--primary-color, #6366f1); cursor:pointer; padding:0.65rem 1.4rem;">
                        <i class="ph-bold ph-magic-wand"></i> Usar Plantilla de Procesos
                    </button>
                    <button onclick="openGroupModal()" class="btn-app-cancel" style="cursor:pointer; padding:0.65rem 1.25rem;">
                        <i class="ph-bold ph-plus"></i> Añadir Fase Manual
                    </button>
                </div>
            </div>
        `;
        return;
    }

    groups.forEach(g => {
        let gDiv = document.createElement('div');
        gDiv.className = 'task-group';
        gDiv.setAttribute('data-id', g.id);
        gDiv.style.cssText = "background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 18px; margin-bottom: 1.5rem; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);";
        
        let tasksHtml = '';
        if(g.tasks && g.tasks.length > 0) {
            g.tasks.forEach(t => {
                let tagsArr = [];
                try { tagsArr = JSON.parse(t.tags || '[]'); } catch(e) {}
                let tagsHtml = '';
                if(tagsArr.length > 0) {
                    tagsHtml = '<div style="display:flex; gap:0.4rem; flex-wrap:wrap; margin-bottom:0.6rem;">';
                    tagsArr.forEach(tag => {
                        let matchedTag = allTags.find(at => at.name === (tag.value || tag.name || tag)) || {};
                        let color = tag.color || matchedTag.color || '#6366f1';
                        let tagName = tag.value || tag.name || tag;
                        tagsHtml += `<span style="background:color-mix(in srgb, ${color} 15%, transparent); color:${color}; border:1px solid color-mix(in srgb, ${color} 30%, transparent); font-size:0.7rem; padding:0.2rem 0.6rem; border-radius:8px; font-weight:600;">${tagName}</span>`;
                    });
                    tagsHtml += '</div>';
                }

                let statusLabels = {
                    pending: { label: 'Pendiente', color: '#94a3b8', bg: 'rgba(148, 163, 184, 0.12)', icon: 'ph-hourglass-high' },
                    in_progress: { label: 'En Proceso', color: '#facc15', bg: 'rgba(250, 204, 21, 0.12)', icon: 'ph-arrows-clockwise' },
                    review: { label: 'Revisión', color: '#c084fc', bg: 'rgba(192, 132, 252, 0.12)', icon: 'ph-magnifying-glass' },
                    completed: { label: 'Completado', color: '#4ade80', bg: 'rgba(74, 222, 128, 0.12)', icon: 'ph-check-circle' }
                };
                let st = statusLabels[t.status] || statusLabels.pending;

                let metaHtml = '';
                if(t.due_date) {
                    let isLate = (new Date(t.due_date + 'T23:59:59') < new Date()) && t.status !== 'completed';
                    let dateColor = isLate ? '#ef4444' : 'var(--text-muted)';
                    metaHtml += `<div style="font-size:0.75rem; color:${dateColor}; margin-top:0.6rem; display:flex; align-items:center; gap:0.35rem; font-weight: 600;"><i class="ph-bold ph-calendar-blank"></i> ${t.due_date} ${isLate ? '<span style="background:rgba(239,68,68,0.15); color:#ef4444; padding:2px 6px; border-radius:6px; font-size:0.65rem;">Vencida</span>' : ''}</div>`;
                }

                // Subtasks preview & progress
                let subtasksList = t.subtasks || [];
                let subtasksHtml = '';
                if (subtasksList.length > 0) {
                    let doneCount = subtasksList.filter(s => s.completed == 1).length;
                    let pct = Math.round((doneCount / subtasksList.length) * 100);
                    subtasksHtml = `
                        <div style="margin-top:0.75rem; padding-top:0.65rem; border-top:1px dashed var(--border-color);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem; font-size:0.75rem; color:var(--text-muted); font-weight:600;">
                                <span><i class="ph-bold ph-check-square"></i> Subtareas (${doneCount}/${subtasksList.length})</span>
                                <span>${pct}%</span>
                            </div>
                            <div style="width:100%; height:5px; background:var(--bg-color); border-radius:9999px; overflow:hidden; margin-bottom:0.6rem;">
                                <div style="width:${pct}%; height:100%; background:var(--secondary-color, #10b981); transition:width 0.3s ease;"></div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                                ${subtasksList.map(st => `
                                    <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.82rem; padding: 0.2rem 0; color:${st.completed == 1 ? 'var(--text-muted)' : 'var(--text-main)'}; ${st.completed == 1 ? 'text-decoration: line-through;' : ''}" onclick="event.stopPropagation(); toggleSubtaskDone(${st.id}, ${st.completed == 1 ? 0 : 1})">
                                        <i class="ph-bold ${st.completed == 1 ? 'ph-check-circle' : 'ph-circle'}" style="color:${st.completed == 1 ? '#10b981' : 'var(--text-muted)'}; font-size:1.05rem; cursor:pointer; flex-shrink: 0;"></i>
                                        <span>${st.title}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }

                let safeTitle = (t.title||'').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                let safeDesc = (t.description||'').replace(/'/g, "\\'").replace(/"/g, "&quot;").replace(/\n/g, "\\n");
                let safeTags = (t.tags||'[]').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                let safeSubtasks = JSON.stringify(subtasksList).replace(/'/g, "\\'").replace(/"/g, "&quot;");
                let safeAttachments = JSON.stringify(t.attachments ? (typeof t.attachments === 'string' ? JSON.parse(t.attachments || '[]') : t.attachments) : []).replace(/'/g, "\\'").replace(/"/g, "&quot;");

                let atts = [];
                try { atts = typeof t.attachments === 'string' ? JSON.parse(t.attachments || '[]') : (t.attachments || []); } catch(e) {}
                let attsHtml = atts.length > 0 ? `<div style="font-size:0.75rem; color:#3b82f6; margin-top:0.5rem; display:inline-flex; align-items:center; gap:0.35rem; font-weight:700; background:rgba(59, 130, 246, 0.12); padding:2px 8px; border-radius:6px;"><i class="ph-bold ph-paperclip"></i> ${atts.length} ${atts.length === 1 ? 'archivo en Drive' : 'archivos en Drive'}</div>` : '';

                tasksHtml += `
                <div class="task-card" data-id="${t.id}" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.15rem 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; cursor: grab; transition: all 0.2s ease;">
                    <div style="flex:1; cursor:pointer;" onclick="openTaskModal(${t.id}, ${g.id}, '${safeTitle}', '${safeDesc}', '${t.status}', '${t.start_date||''}', '${t.due_date||''}', '${safeTags}', '${safeSubtasks}', '${safeAttachments}')">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem; flex-wrap:wrap; gap:0.35rem;">
                            ${tagsHtml || '<div></div>'}
                            <span style="background:${st.bg}; color:${st.color}; font-size:0.72rem; font-weight:700; padding:0.25rem 0.65rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.3rem; flex-shrink: 0;">
                                <i class="ph-bold ${st.icon}"></i> ${st.label}
                            </span>
                        </div>
                        <div class="task-card-title" style="font-weight:700; color:var(--text-main); font-size:1rem; margin-bottom:0.35rem; line-height:1.4;">${t.title}</div>
                        ${t.description ? `<div class="task-card-desc" style="font-size:0.84rem; color:var(--text-muted); line-height:1.5;">${t.description}</div>` : ''}
                        ${metaHtml}
                        ${attsHtml}
                        ${subtasksHtml}
                    </div>
                    <div class="task-actions" style="margin-left: 0.75rem; flex-shrink: 0;">
                        <button class="btn-action del" onclick="deleteTask(${t.id})" title="Eliminar Tarea" style="border-radius:8px; padding:0.4rem; width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;"><i class="ph-bold ph-trash"></i></button>
                    </div>
                </div>`;
            });
        } else {
            tasksHtml = `
                <div style="text-align:center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: 0.88rem; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.75rem;">
                    <div style="width:44px; height:44px; border-radius:14px; background:rgba(255,255,255,0.05); border:1px dashed var(--border-color); display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:1.3rem;">
                        <i class="ph-bold ph-clipboard-text"></i>
                    </div>
                    <span>No hay tareas en esta fase todavía</span>
                    <button onclick="openTaskModal(0, ${g.id})" class="btn-app-submit" style="background: var(--primary-color, #6366f1); padding: 0.5rem 1.25rem; font-size: 0.84rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="ph-bold ph-plus"></i> Crear Tarea en esta Fase
                    </button>
                </div>
            `;
        }

        gDiv.innerHTML = `
            <div class="task-group-header" style="background: var(--bg-surface); padding: 0.85rem 1rem; font-weight: 700; font-size: 0.95rem; color: var(--text-main); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); cursor: grab; flex-wrap: wrap; gap: 0.5rem;">
                <div class="group-info" style="display:flex; align-items:center; gap:0.55rem; flex: 1 1 auto; min-width: 0; flex-wrap: wrap;">
                    <i class="ph-bold ph-dots-six-vertical" style="color:var(--text-muted); font-size: 1.2rem; cursor: grab; flex-shrink: 0;"></i>
                    <span class="group-title-text" style="letter-spacing: -0.2px; font-weight: 700; word-break: break-word;">${g.name}</span>
                    <span style="background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); font-size: 0.72rem; padding: 0.15rem 0.55rem; border-radius: 9999px; font-weight: 700; flex-shrink: 0;">
                        ${g.tasks ? g.tasks.length : 0} tareas
                    </span>
                </div>
                <div class="group-actions" style="display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0;">
                    <button class="btn-action-add-task" onclick="openTaskModal(0, ${g.id})" title="Añadir Tarea"><i class="ph-bold ph-plus"></i></button>
                    <button class="btn-action" onclick="openGroupModal(${g.id}, '${g.name}')" title="Editar Fase" style="width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"><i class="ph-bold ph-pencil-simple"></i></button>
                    <button class="btn-action del" onclick="deleteGroup(${g.id})" title="Eliminar Fase" style="width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"><i class="ph-bold ph-trash"></i></button>
                </div>
            </div>
            <div class="task-list" data-group="${g.id}" style="padding: 1rem; display: flex; flex-direction: column; gap: 0.85rem; min-height: 50px;">
                ${tasksHtml}
            </div>
        `;
        container.appendChild(gDiv);
    });

    // Init Sortable for Groups
    new Sortable(container, {
        animation: 150,
        handle: '.task-group-header',
        onEnd: function () {
            let orders = [];
            container.querySelectorAll('.task-group').forEach((el, index) => {
                orders.push({ id: el.getAttribute('data-id'), order: index });
            });
            saveGroupOrder(orders);
        }
    });

    // Init Sortable for Tasks
    document.querySelectorAll('.task-list').forEach(list => {
        new Sortable(list, {
            group: 'shared',
            animation: 150,
            onEnd: function (evt) {
                let groupId = evt.to.getAttribute('data-group');
                let orders = [];
                evt.to.querySelectorAll('.task-card').forEach((el, index) => {
                    orders.push({ id: el.getAttribute('data-id'), order: index });
                });
                saveTaskOrder(groupId, orders);
            }
        });
    });
}

function openGroupModal(id = 0, currentName = '') {
    let modalHtml = `
        <div class="app-modal-dialog" style="width: 100%; border-radius: 22px; overflow: hidden;">
            <div class="app-modal-header" style="padding: 1.15rem 1.35rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <div class="app-modal-title-group" style="display: flex; align-items: center; gap: 0.85rem;">
                    <div class="app-modal-icon-badge" style="width: 40px; height: 40px; border-radius: 12px; background: color-mix(in srgb, var(--secondary-color, #10b981) 15%, transparent); color: var(--secondary-color, #10b981); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="ph-bold ph-folder-notch-open"></i>
                    </div>
                    <div class="app-modal-titles">
                        <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted);">Fases de Proyecto</span>
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">${id ? 'Editar Fase o Grupo' : 'Nueva Fase o Grupo'}</h3>
                    </div>
                </div>
                <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="app-modal-body" style="padding: 1.35rem;">
                <div class="app-form-group">
                    <label class="app-form-label"><i class="ph-bold ph-text-t"></i> Nombre de la Fase o Etapa</label>
                    <input id="swal-group-name" class="app-input-title" placeholder="Ej. FASE 01: Diseño & Conceptualización" value="${currentName.replace(/"/g, '&quot;')}">
                </div>
            </div>
            <div class="app-modal-footer" style="padding: 1rem 1.35rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--bg-surface);">
                <button onclick="Swal.close()" class="btn-app-cancel">Cancelar</button>
                <button id="swal-group-submit-btn" class="btn-app-submit" style="background: var(--secondary-color, #10b981);"><i class="ph-bold ph-check"></i> ${id ? 'Actualizar Grupo' : 'Crear Grupo'}</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: window.innerWidth < 600 ? '95vw' : '540px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad swal2-app-modal', actions: 'app-modal-actions' },
        didOpen: () => {
            const input = document.getElementById('swal-group-name');
            if (input) {
                input.focus();
                input.select();
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') document.getElementById('swal-group-submit-btn').click();
                });
            }
            document.getElementById('swal-group-submit-btn').addEventListener('click', () => {
                const val = input.value.trim();
                if (!val) {
                    input.focus();
                    return;
                }
                let fd = new FormData();
                fd.append('action', 'save_task_group');
                fd.append('id', id);
                fd.append('project_id', PROJECT_ID);
                fd.append('name', val);
                fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if(d.success) loadProjectTasks(); });
                Swal.close();
            });
        }
    });
}

function deleteGroup(id) {
    let modalHtml = `
        <div class="app-modal-dialog">
            <div class="app-modal-header">
                <div class="app-modal-title-group">
                    <div class="app-modal-icon-badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="ph-bold ph-trash"></i>
                    </div>
                    <div class="app-modal-titles">
                        <span>Zona de Peligro</span>
                        <h3>¿Eliminar Grupo?</h3>
                    </div>
                </div>
                <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="app-modal-body">
                <p style="margin:0; font-size:0.95rem; color:var(--text-muted); line-height:1.6;">
                    Se eliminará permanentemente este grupo de tareas junto con todas las tareas asignadas dentro de él. ¿Deseas continuar?
                </p>
            </div>
            <div class="app-modal-footer">
                <button onclick="Swal.close()" class="btn-app-cancel">Cancelar</button>
                <button id="swal-confirm-del-group" class="btn-app-submit" style="background: #ef4444; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);"><i class="ph-bold ph-trash"></i> Sí, Eliminar</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: window.innerWidth < 600 ? '95vw' : '500px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad swal2-app-modal', actions: 'app-modal-actions' },
        didOpen: () => {
            document.getElementById('swal-confirm-del-group').addEventListener('click', () => {
                let fd = new FormData();
                fd.append('action', 'delete_task_group');
                fd.append('id', id);
                fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if(d.success) loadProjectTasks(); });
                Swal.close();
            });
        }
    });
}

function openTaskModal(taskId = 0, groupId = 0, title = '', description = '', status = 'pending', startDate = '', dueDate = '', tags = '[]', subtasksJson = '[]', attachmentsJson = '[]') {
    let parsedSubtasks = [];
    try { parsedSubtasks = typeof subtasksJson === 'string' ? JSON.parse(subtasksJson) : (subtasksJson || []); } catch(e) { parsedSubtasks = []; }

    let taskAttachments = [];
    try { taskAttachments = typeof attachmentsJson === 'string' ? JSON.parse(attachmentsJson) : (attachmentsJson || []); } catch(e) { taskAttachments = []; }

    let modalHtml = `
        <div class="app-modal-dialog" style="width: 100%; border-radius: 24px; overflow: hidden;">
            <!-- Header tipo App -->
            <div class="app-modal-header" style="border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <div class="app-modal-icon-badge" style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(16, 185, 129, 0.15)); color: var(--primary-color, #6366f1); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="ph-bold ${taskId ? 'ph-check-square-offset' : 'ph-plus-circle'}"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.15rem; flex-wrap: wrap;">
                            <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted);">
                                <?= htmlspecialchars($project['title']) ?>
                            </span>
                            <span style="display: inline-block; width: 4px; height: 4px; border-radius: 50%; background: var(--text-muted); opacity: 0.5;"></span>
                            <span style="font-size: 0.72rem; font-weight: 700; color: var(--primary-color, #6366f1); background: color-mix(in srgb, var(--primary-color) 12%, transparent); padding: 1px 7px; border-radius: 6px;">
                                ${taskId ? 'TAREA #' + taskId : 'NUEVA TAREA'}
                            </span>
                        </div>
                        <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; letter-spacing: -0.3px; color: var(--text-main);">
                            ${taskId ? 'Configuración de Tarea' : 'Crear Nueva Tarea'}
                        </h3>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <button class="app-close-circle" onclick="Swal.close()" title="Cerrar (Esc)"><i class="ph-bold ph-x"></i></button>
                </div>
            </div>
            
            <!-- Cuerpo en 2 Columnas -->
            <div class="app-modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="task-modal-two-col">
                    <!-- Columna Izquierda (360px): Propiedades -->
                    <div class="task-modal-col-left">
                        <div class="app-form-group">
                            <label class="app-form-label"><i class="ph-bold ph-folder-notch-open"></i> Fase de Proyecto</label>
                            <select id="swal-task-group" class="app-input-title" style="font-size: 0.88rem; padding: 0.65rem 0.85rem; font-weight: 600; cursor: pointer; border-radius: 12px; height: auto;">
                                ${(window.currentProjectGroups || []).map(g => `<option value="${g.id}" ${g.id == groupId ? 'selected' : ''}>${g.name}</option>`).join('')}
                            </select>
                        </div>
                        
                        <div class="app-form-group">
                            <label class="app-form-label"><i class="ph-bold ph-text-t"></i> Título de la Tarea</label>
                            <input id="swal-task-title" class="app-input-title" placeholder="Ej. Diseño de Isologotipo y Variaciones..." value="${title}">
                        </div>
                        
                        <div class="app-form-group">
                            <label class="app-form-label"><i class="ph-bold ph-faders"></i> Estado del Flujo</label>
                            <div class="app-status-grid-new" id="app-status-group">
                                <div class="app-status-card ${status === 'pending' ? 'active' : ''}" data-val="pending">
                                    <div class="status-icon-wrap"><i class="ph-bold ph-hourglass-simple"></i></div>
                                    <div class="status-text-wrap">
                                        <span class="status-name">Pendiente</span>
                                        <span class="status-desc">Por iniciar</span>
                                    </div>
                                </div>
                                <div class="app-status-card ${status === 'in_progress' ? 'active' : ''}" data-val="in_progress">
                                    <div class="status-icon-wrap"><i class="ph-bold ph-arrows-clockwise"></i></div>
                                    <div class="status-text-wrap">
                                        <span class="status-name">En Proceso</span>
                                        <span class="status-desc">En ejecución</span>
                                    </div>
                                </div>
                                <div class="app-status-card ${status === 'review' ? 'active' : ''}" data-val="review">
                                    <div class="status-icon-wrap"><i class="ph-bold ph-magnifying-glass"></i></div>
                                    <div class="status-text-wrap">
                                        <span class="status-name">Revisión</span>
                                        <span class="status-desc">Control calidad</span>
                                    </div>
                                </div>
                                <div class="app-status-card ${status === 'completed' ? 'active' : ''}" data-val="completed">
                                    <div class="status-icon-wrap"><i class="ph-bold ph-check-circle"></i></div>
                                    <div class="status-text-wrap">
                                        <span class="status-name">Completado</span>
                                        <span class="status-desc">Finalizado</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="swal-task-status" value="${status}">
                        </div>
                        
                        <div class="app-form-group">
                            <label class="app-form-label"><i class="ph-bold ph-calendar-blank"></i> Plazos de Ejecución</label>
                            <div class="app-timeline-range-wrapper">
                                <div class="app-timeline-tile start-tile">
                                    <div class="app-timeline-icon start-icon">
                                        <i class="ph-bold ph-calendar-plus"></i>
                                    </div>
                                    <div class="app-timeline-content">
                                        <span class="app-timeline-tag">Inicio</span>
                                        <input type="date" id="swal-task-start" class="app-timeline-input" value="${startDate}">
                                    </div>
                                </div>
                                
                                <div class="app-timeline-tile due-tile">
                                    <div class="app-timeline-icon due-icon">
                                        <i class="ph-bold ph-calendar-check"></i>
                                    </div>
                                    <div class="app-timeline-content">
                                        <span class="app-timeline-tag">Límite</span>
                                        <input type="date" id="swal-task-due" class="app-timeline-input" value="${dueDate}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="app-form-group">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label class="app-form-label"><i class="ph-bold ph-tag"></i> Especialidad / Tags</label>
                                <button type="button" onclick="openTagManagerModal()" style="background:transparent; border:none; color:var(--primary-color, #6366f1); font-size:0.75rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:0.25rem;">
                                    <i class="ph-bold ph-gear"></i> Gestionar
                                </button>
                            </div>
                            <input id="swal-task-tags" value='${tags}' class="app-tagify" style="width: 100%;">
                        </div>
                        
                        <div class="app-form-group">
                            <label class="app-form-label"><i class="ph-bold ph-article"></i> Descripción & Notas</label>
                            <textarea id="swal-task-desc" class="app-textarea" style="min-height: 90px;" placeholder="Escribe las especificaciones, requerimientos clave y observaciones para esta tarea...">${description}</textarea>
                        </div>
                    </div>

                    <!-- Columna Derecha (1fr): Workspace -->
                    <div class="task-modal-col-right">
                        <!-- PANEL 1: SUBTAREAS -->
                        <div class="task-section-panel">
                            <div class="task-section-header">
                                <div class="task-section-title">
                                    <div style="width: 32px; height: 32px; border-radius: 10px; background: color-mix(in srgb, var(--secondary-color, #10b981) 15%, transparent); color: var(--secondary-color, #10b981); display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                                        <i class="ph-bold ph-list-checks"></i>
                                    </div>
                                    <div>
                                        <span style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Subtareas y Checklist</span>
                                        <div id="subtasks-progress-summary" style="font-size: 0.74rem; color: var(--text-muted); font-weight: 600; margin-top: 1px;">
                                            0 de ${parsedSubtasks.length} completadas
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="btn-add-subtask-row" style="background:color-mix(in srgb, var(--secondary-color, #10b981) 15%, transparent); color:var(--secondary-color, #10b981); border:1px solid color-mix(in srgb, var(--secondary-color, #10b981) 30%, transparent); padding:0.4rem 0.95rem; border-radius:10px; font-size:0.8rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:0.4rem; transition: all 0.2s ease;">
                                    <i class="ph-bold ph-plus"></i> Añadir Subtarea
                                </button>
                            </div>

                            <!-- Barra de progreso interactiva -->
                            <div style="width: 100%; height: 6px; background: var(--bg-color); border-radius: 9999px; overflow: hidden; margin-top: 0.15rem;">
                                <div id="subtasks-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 9999px; transition: width 0.3s ease;"></div>
                            </div>

                            <div id="subtasks-list-container" class="app-subtasks-container" style="max-height: 230px; overflow-y: auto;">
                                ${parsedSubtasks.length === 0 ? '<div id="no-subtasks-msg" style="color:var(--text-muted); font-size:0.84rem; text-align:center; padding:1.4rem 0;"><i class="ph-bold ph-check-square" style="font-size:1.75rem; display:block; margin-bottom:0.4rem; opacity:0.4;"></i>No hay subtareas registradas aún. Haz clic en "+ Añadir Subtarea".</div>' : ''}
                            </div>
                        </div>

                        <!-- PANEL 2: ARCHIVOS & GOOGLE DRIVE -->
                        <div class="task-section-panel">
                            <div class="task-section-header">
                                <div class="task-section-title">
                                    <div style="width: 32px; height: 32px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                                        <i class="ph-fill ph-google-drive-logo"></i>
                                    </div>
                                    <div>
                                        <span style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">Archivos en Google Drive</span>
                                        <div style="font-size: 0.74rem; color: var(--text-muted); font-weight: 600; margin-top: 1px;">
                                            Se almacenan directamente en la carpeta del proyecto
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if (!empty($project['drive_folder_url'])): ?>
                                    <a href="<?= htmlspecialchars($project['drive_folder_url']) ?>" target="_blank" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); text-decoration: none; padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="ph-bold ph-folder-notch-open"></i> Abrir Carpeta
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Dropzone de Subida -->
                            <div class="task-dropzone-box" id="task-dropzone-box" onclick="document.getElementById('task-file-upload-input').click()">
                                <input type="file" id="task-file-upload-input" style="display: none;" multiple>
                                <div class="task-dropzone-icon">
                                    <i class="ph-bold ph-cloud-arrow-up"></i>
                                </div>
                                <div class="task-dropzone-text">
                                    Haz clic o arrastra archivos para subirlos a Google Drive
                                </div>
                                <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.25rem;">
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #8b5cf6; background: rgba(139, 92, 246, 0.12); padding: 2px 7px; border-radius: 6px;">AI / PSD</span>
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #3b82f6; background: rgba(59, 130, 246, 0.12); padding: 2px 7px; border-radius: 6px;">SVG / PNG</span>
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.12); padding: 2px 7px; border-radius: 6px;">PDF</span>
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #f59e0b; background: rgba(245, 158, 11, 0.12); padding: 2px 7px; border-radius: 6px;">ZIP / Fuentes</span>
                                </div>
                                <div id="task-upload-progress" style="display:none; width: 100%; margin-top: 0.6rem;">
                                    <div style="width: 100%; height: 6px; background: rgba(59, 130, 246, 0.2); border-radius: 9999px; overflow: hidden;">
                                        <div id="task-upload-bar" style="width: 0%; height: 100%; background: #3b82f6; transition: width 0.3s;"></div>
                                    </div>
                                    <span id="task-upload-status-txt" style="font-size: 0.72rem; color: #3b82f6; margin-top: 0.25rem; display: block; font-weight: 600;">Subiendo archivo a Drive...</span>
                                </div>
                            </div>

                            <!-- Lista de archivos adjuntos -->
                            <div class="task-attachments-list" id="task-attachments-list">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer tipo App -->
            <div class="app-modal-footer" style="padding: 1rem 1.35rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface);">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
                    <i class="ph-bold ph-cloud-check" style="color: var(--secondary-color, #10b981); font-size: 1.15rem;"></i>
                    <span>Sincronización directa con Google Drive</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <button onclick="Swal.close()" class="btn-app-cancel">Cancelar</button>
                    <button id="custom-save-btn" class="btn-app-submit">
                        <i class="ph-bold ph-check"></i> ${taskId ? 'Guardar Cambios' : 'Crear Tarea'}
                    </button>
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: window.innerWidth < 900 ? '96vw' : '80vw',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad swal2-app-modal', actions: 'app-modal-actions' },
        didOpen: () => {
            const input = document.getElementById('swal-task-tags');
            
            // Format tagify whitelist
            let tagWhitelist = allTags.map(t => ({ value: t.name, color: t.color }));
            let tagify = new Tagify(input, {
                whitelist: tagWhitelist,
                enforceWhitelist: false,
                dropdown: { enabled: 0, maxItems: 15 },
                transformTag: (tagData) => {
                    let matched = allTags.find(t => t.name.toLowerCase() === tagData.value.toLowerCase());
                    let color = matched ? matched.color : '#6366f1';
                    tagData.color = color;
                    tagData.style = `--tag-bg: color-mix(in srgb, ${color} 16%, transparent); --tag-border: color-mix(in srgb, ${color} 40%, transparent); --tag-color: ${color};`;
                }
            });
            
            // Status Card Click Handler
            document.querySelectorAll('#app-status-group .app-status-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('#app-status-group .app-status-card').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    document.getElementById('swal-task-status').value = card.getAttribute('data-val');
                });
            });

            // Subtask Row Template & Progress
            function updateSubtasksProgress() {
                const items = document.querySelectorAll('.app-subtask-item');
                const total = items.length;
                let done = 0;
                items.forEach(it => {
                    if (it.querySelector('.app-subtask-check').checked) done++;
                });

                const summaryEl = document.getElementById('subtasks-progress-summary');
                const barEl = document.getElementById('subtasks-progress-bar');
                if (summaryEl) {
                    summaryEl.textContent = `${done} de ${total} completadas${total > 0 ? ' • ' + Math.round((done / total) * 100) + '%' : ''}`;
                }
                if (barEl) {
                    barEl.style.width = total > 0 ? Math.round((done / total) * 100) + '%' : '0%';
                }
            }

            const renderSubtaskRow = (st = { title: '', description: '', completed: 0 }) => {
                const noMsg = document.getElementById('no-subtasks-msg');
                if (noMsg) noMsg.remove();

                const div = document.createElement('div');
                div.className = 'app-subtask-item';
                div.innerHTML = `
                    <div class="app-subtask-header">
                        <input type="checkbox" class="app-subtask-check" ${st.completed == 1 ? 'checked' : ''}>
                        <input type="text" class="app-subtask-input-title" placeholder="Título de la subtarea..." value="${(st.title||'').replace(/"/g, '&quot;')}" style="${st.completed == 1 ? 'text-decoration: line-through; opacity: 0.6;' : ''}">
                        <button type="button" class="app-subtask-del" title="Eliminar"><i class="ph-bold ph-trash"></i></button>
                    </div>
                    <input type="text" class="app-subtask-input-desc" placeholder="Descripción breve o especificación (opcional)..." value="${(st.description||'').replace(/"/g, '&quot;')}">
                `;

                const chk = div.querySelector('.app-subtask-check');
                const titleInput = div.querySelector('.app-subtask-input-title');
                chk.addEventListener('change', () => {
                    if (chk.checked) {
                        titleInput.style.textDecoration = 'line-through';
                        titleInput.style.opacity = '0.6';
                    } else {
                        titleInput.style.textDecoration = 'none';
                        titleInput.style.opacity = '1';
                    }
                    updateSubtasksProgress();
                });

                div.querySelector('.app-subtask-del').addEventListener('click', () => {
                    div.remove();
                    updateSubtasksProgress();
                    if (document.querySelectorAll('.app-subtask-item').length === 0) {
                        document.getElementById('subtasks-list-container').innerHTML = '<div id="no-subtasks-msg" style="color:var(--text-muted); font-size:0.84rem; text-align:center; padding:1.4rem 0;"><i class="ph-bold ph-check-square" style="font-size:1.75rem; display:block; margin-bottom:0.4rem; opacity:0.4;"></i>No hay subtareas registradas aún. Haz clic en "+ Añadir Subtarea".</div>';
                    }
                });
                document.getElementById('subtasks-list-container').appendChild(div);
                updateSubtasksProgress();
            };

            // Render existing subtasks
            if (parsedSubtasks && parsedSubtasks.length > 0) {
                parsedSubtasks.forEach(st => renderSubtaskRow(st));
            }
            updateSubtasksProgress();

            // Add new subtask row button
            document.getElementById('btn-add-subtask-row').addEventListener('click', () => {
                renderSubtaskRow({ title: '', description: '', completed: 0 });
                const rows = document.querySelectorAll('.app-subtask-item');
                const lastRow = rows[rows.length - 1];
                if (lastRow) lastRow.querySelector('.app-subtask-input-title').focus();
            });

            // Attachment rendering & operations
            function renderTaskAttachmentsList() {
                const listEl = document.getElementById('task-attachments-list');
                if (!listEl) return;
                if (taskAttachments.length === 0) {
                    listEl.innerHTML = '<div style="text-align:center; padding:0.75rem; font-size:0.78rem; color:var(--text-muted);">No hay archivos adjuntos aún en esta tarea.</div>';
                    return;
                }

                listEl.innerHTML = taskAttachments.map((att, idx) => {
                    let ext = (att.ext || att.name.split('.').pop() || '').toLowerCase();
                    let iconClass = 'ph-file';
                    let iconColor = '#6366f1';
                    let bgIcon = 'rgba(99, 102, 241, 0.15)';
                    
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
                        iconClass = 'ph-image';
                        iconColor = '#3b82f6';
                        bgIcon = 'rgba(59, 130, 246, 0.15)';
                    } else if (ext === 'pdf') {
                        iconClass = 'ph-file-pdf';
                        iconColor = '#ef4444';
                        bgIcon = 'rgba(239, 68, 68, 0.15)';
                    } else if (['zip', 'rar', '7z'].includes(ext)) {
                        iconClass = 'ph-file-zip';
                        iconColor = '#f59e0b';
                        bgIcon = 'rgba(245, 158, 11, 0.15)';
                    } else if (['ai', 'psd', 'eps', 'fig'].includes(ext)) {
                        iconClass = 'ph-vector-three';
                        iconColor = '#8b5cf6';
                        bgIcon = 'rgba(139, 92, 246, 0.15)';
                    }

                    let sizeStr = att.size ? (att.size > 1048576 ? (att.size / 1048576).toFixed(1) + ' MB' : Math.round(att.size / 1024) + ' KB') : '';

                    return `
                        <div class="task-attachment-item">
                            <div class="task-attachment-left">
                                <div class="task-attachment-icon" style="background: ${bgIcon}; color: ${iconColor};">
                                    <i class="ph-bold ${iconClass}"></i>
                                </div>
                                <div class="task-attachment-meta">
                                    <span class="task-attachment-name" title="${att.name}">${att.name}</span>
                                    <span class="task-attachment-size">
                                        ${sizeStr ? sizeStr + ' • ' : ''}
                                        ${att.drive ? '<span style="color:#3b82f6; display:inline-flex; align-items:center; gap:3px;"><i class="ph-fill ph-google-drive-logo"></i> Google Drive</span>' : 'Local'}
                                    </span>
                                </div>
                            </div>
                            <div class="task-attachment-actions">
                                <a href="${att.url}" target="_blank" class="app-btn-icon-sm" title="Abrir / Descargar" style="text-decoration:none;">
                                    <i class="ph-bold ph-arrow-square-out"></i>
                                </a>
                                <button type="button" class="app-btn-icon-sm del-att-btn" data-idx="${idx}" title="Eliminar adjunto" style="color:#ef4444;">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');

                listEl.querySelectorAll('.del-att-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        let idx = parseInt(btn.getAttribute('data-idx'));
                        taskAttachments.splice(idx, 1);
                        renderTaskAttachmentsList();
                    });
                });
            }

            renderTaskAttachmentsList();

            // File Upload via Ajax (Drive or Local)
            const handleFileUpload = (files) => {
                if (!files || files.length === 0) return;
                const progressBox = document.getElementById('task-upload-progress');
                const progressBar = document.getElementById('task-upload-bar');

                if (progressBox) progressBox.style.display = 'block';
                if (progressBar) progressBar.style.width = '30%';

                let uploadPromises = Array.from(files).map(file => {
                    let fd = new FormData();
                    fd.append('action', 'upload_task_attachment');
                    fd.append('file', file);
                    fd.append('project_id', '<?php echo $id; ?>');
                    fd.append('task_id', taskId);

                    return fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success && res.attachment) {
                                taskAttachments.push(res.attachment);
                            } else {
                                Swal.showValidationMessage(res.message || 'Error al subir archivo a Drive.');
                            }
                        })
                        .catch(err => console.error(err));
                });

                Promise.all(uploadPromises).then(() => {
                    if (progressBar) progressBar.style.width = '100%';
                    renderTaskAttachmentsList();
                    setTimeout(() => {
                        if (progressBox) progressBox.style.display = 'none';
                        if (progressBar) progressBar.style.width = '0%';
                    }, 600);
                });
            };

            const fileInput = document.getElementById('task-file-upload-input');
            if (fileInput) {
                fileInput.addEventListener('change', () => {
                    handleFileUpload(fileInput.files);
                });
            }

            // Drag and drop events for dropzone
            const dropzone = document.getElementById('task-dropzone-box');
            if (dropzone) {
                ['dragenter', 'dragover'].forEach(name => {
                    dropzone.addEventListener(name, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('dragover');
                    });
                });
                ['dragleave', 'drop'].forEach(name => {
                    dropzone.addEventListener(name, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('dragover');
                    });
                });
                dropzone.addEventListener('drop', (e) => {
                    let dt = e.dataTransfer;
                    if (dt && dt.files) {
                        handleFileUpload(dt.files);
                    }
                });
            }
            
            document.getElementById('custom-save-btn').addEventListener('click', () => {
                let titleVal = document.getElementById('swal-task-title').value.trim();
                if(!titleVal) {
                    document.getElementById('swal-task-title').focus();
                    return;
                }

                // Gather subtasks
                let gatheredSubtasks = [];
                document.querySelectorAll('.app-subtask-item').forEach(item => {
                    let stTitle = item.querySelector('.app-subtask-input-title').value.trim();
                    let stDesc = item.querySelector('.app-subtask-input-desc').value.trim();
                    let stDone = item.querySelector('.app-subtask-check').checked ? 1 : 0;
                    if (stTitle) {
                        gatheredSubtasks.push({
                            title: stTitle,
                            description: stDesc,
                            completed: stDone
                        });
                    }
                });
                
                let groupSelectEl = document.getElementById('swal-task-group');
                let finalGroupId = groupSelectEl ? (parseInt(groupSelectEl.value) || groupId) : groupId;

                let fd = new FormData();
                fd.append('action', 'save_task');
                fd.append('id', taskId);
                fd.append('group_id', finalGroupId);
                fd.append('title', titleVal);
                fd.append('description', document.getElementById('swal-task-desc').value);
                fd.append('status', document.getElementById('swal-task-status').value);
                fd.append('start_date', document.getElementById('swal-task-start').value);
                fd.append('due_date', document.getElementById('swal-task-due').value);
                fd.append('tags', document.getElementById('swal-task-tags').value);
                fd.append('subtasks', JSON.stringify(gatheredSubtasks));
                fd.append('attachments', JSON.stringify(taskAttachments));
                
                triggerSyncStatus(true);
                fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { 
                    if(d.success) loadProjectTasks(); 
                    setTimeout(() => triggerSyncStatus(false), 500);
                });
                
                Swal.close();
            });
            
            if(!taskId) document.getElementById('swal-task-title').focus();
        }
    });
}

function deleteTask(id) {
    let modalHtml = `
        <div class="app-modal-dialog">
            <div class="app-modal-header">
                <div class="app-modal-title-group">
                    <div class="app-modal-icon-badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="ph-bold ph-trash"></i>
                    </div>
                    <div class="app-modal-titles">
                        <span>Zona de Peligro</span>
                        <h3>¿Eliminar Tarea?</h3>
                    </div>
                </div>
                <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="app-modal-body">
                <p style="margin:0; font-size:0.95rem; color:var(--text-muted); line-height:1.6;">
                    ¿Estás seguro de que deseas eliminar esta tarea? Esta acción no se puede deshacer.
                </p>
            </div>
            <div class="app-modal-footer">
                <button onclick="Swal.close()" class="btn-app-cancel">Cancelar</button>
                <button id="swal-confirm-del-task" class="btn-app-submit" style="background: #ef4444; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);"><i class="ph-bold ph-trash"></i> Sí, Eliminar</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: '500px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' },
        didOpen: () => {
            document.getElementById('swal-confirm-del-task').addEventListener('click', () => {
                let fd = new FormData();
                fd.append('action', 'delete_task');
                fd.append('id', id);
                triggerSyncStatus(true);
                fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { 
                    if(d.success) loadProjectTasks(); 
                    setTimeout(() => triggerSyncStatus(false), 500);
                });
                Swal.close();
            });
        }
    });
}

function triggerSyncStatus(isSyncing = true) {
    const syncEl = document.getElementById('app-sync-indicator');
    if (!syncEl) return;
    const label = syncEl.querySelector('.sync-label');
    if (isSyncing) {
        syncEl.classList.add('syncing');
        if (label) label.textContent = 'Guardando cambios...';
        syncEl.style.borderColor = '#f59e0b';
        syncEl.style.color = '#f59e0b';
    } else {
        syncEl.classList.remove('syncing');
        if (label) label.textContent = 'Sincronizado';
        syncEl.style.borderColor = 'var(--border-color)';
        syncEl.style.color = 'var(--text-muted)';
    }
}

function saveGroupOrder(orders) {
    triggerSyncStatus(true);
    let fd = new FormData();
    fd.append('action', 'reorder_groups');
    fd.append('orders', JSON.stringify(orders));
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
    .then(() => {
        setTimeout(() => triggerSyncStatus(false), 600);
    });
}

function saveTaskOrder(groupId, orders) {
    triggerSyncStatus(true);
    let fd = new FormData();
    fd.append('action', 'reorder_tasks');
    fd.append('group_id', groupId);
    fd.append('orders', JSON.stringify(orders));
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
    .then(() => {
        setTimeout(() => triggerSyncStatus(false), 600);
    });
}

function toggleSubtaskDone(subtaskId, isCompleted) {
    triggerSyncStatus(true);
    let fd = new FormData();
    fd.append('action', 'toggle_subtask');
    fd.append('id', subtaskId);
    fd.append('completed', isCompleted);
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { 
        if(d.success) loadProjectTasks(); 
        setTimeout(() => triggerSyncStatus(false), 500);
    });
}

function openTagManagerModal() {
    const renderTagManagerBody = () => {
        let tagItemsHtml = allTags.map(tag => `
            <div class="app-tag-mgmt-item" data-id="${tag.id}">
                <div style="display:flex; align-items:center; gap:0.65rem; flex:1;">
                    <input type="color" class="tag-color-picker" value="${tag.color || '#6366f1'}" style="width:32px; height:32px; padding:0; border:none; border-radius:8px; cursor:pointer; background:transparent;">
                    <input type="text" class="tag-name-input app-input-title" value="${(tag.name||'').replace(/"/g, '&quot;')}" style="font-size:0.9rem; padding:0.45rem 0.75rem; border-radius:10px;">
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <button type="button" class="btn-save-tag btn-app-submit" style="padding:0.45rem 0.85rem; font-size:0.78rem; background:var(--secondary-color, #10b981);"><i class="ph-bold ph-floppy-disk"></i></button>
                    <button type="button" class="btn-del-tag btn-action del" style="width:32px; height:32px; border-radius:8px;"><i class="ph-bold ph-trash"></i></button>
                </div>
            </div>
        `).join('');

        return `
            <div class="app-modal-dialog">
                <div class="app-modal-header">
                    <div class="app-modal-title-group">
                        <div class="app-modal-icon-badge" style="background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent); color: var(--primary-color, #6366f1);">
                            <i class="ph-bold ph-tag"></i>
                        </div>
                        <div class="app-modal-titles">
                            <span>Configuración de Proyecto</span>
                            <h3>Gestor de Etiquetas</h3>
                        </div>
                    </div>
                    <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
                </div>
                <div class="app-modal-body">
                    <!-- Create new tag -->
                    <div style="background:var(--bg-color); padding:1rem; border-radius:16px; border:1px solid var(--border-color); display:flex; flex-direction:column; gap:0.75rem;">
                        <label class="app-form-label"><i class="ph-bold ph-plus-circle"></i> Crear Nueva Etiqueta</label>
                        <div style="display:flex; gap:0.6rem; align-items:center;">
                            <input type="color" id="new-tag-color" value="#10b981" style="width:38px; height:38px; padding:0; border:none; border-radius:10px; cursor:pointer; background:transparent;">
                            <input type="text" id="new-tag-name" class="app-input-title" placeholder="Ej. Rediseño, Frontend, Urgente..." style="flex:1; font-size:0.92rem; padding:0.55rem 0.85rem;">
                            <button type="button" id="btn-create-tag-submit" class="btn-app-submit" style="padding:0.55rem 1.15rem; font-size:0.85rem; background:var(--secondary-color, #10b981);">
                                <i class="ph-bold ph-plus"></i> Crear
                            </button>
                        </div>
                    </div>

                    <!-- Existing tags list -->
                    <div class="app-form-group">
                        <label class="app-form-label"><i class="ph-bold ph-list-dashes"></i> Etiquetas Existentes (${allTags.length})</label>
                        <div id="tag-mgmt-list" style="display:flex; flex-direction:column; gap:0.65rem; max-height:40vh; overflow-y:auto; padding-right:0.25rem;">
                            ${allTags.length === 0 ? '<div style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding:1rem;">No hay etiquetas registradas.</div>' : tagItemsHtml}
                        </div>
                    </div>
                </div>
                <div class="app-modal-footer">
                    <button onclick="Swal.close()" class="btn-app-cancel">Listo / Volver</button>
                </div>
            </div>
        `;
    };

    Swal.fire({
        html: renderTagManagerBody(),
        width: '620px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' },
        didOpen: () => {
            const attachEvents = () => {
                // Create tag
                const createBtn = document.getElementById('btn-create-tag-submit');
                const newNameInput = document.getElementById('new-tag-name');
                const newColorInput = document.getElementById('new-tag-color');

                createBtn.onclick = () => {
                    let nameVal = newNameInput.value.trim();
                    let colorVal = newColorInput.value;
                    if(!nameVal) { newNameInput.focus(); return; }

                    let fd = new FormData();
                    fd.append('action', 'save_tag');
                    fd.append('id', 0);
                    fd.append('name', nameVal);
                    fd.append('color', colorVal);

                    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if(d.success) {
                            loadTagsForTasks();
                            allTags.push({ id: d.id, name: nameVal, color: colorVal });
                            document.querySelector('.swal2-container').innerHTML = '';
                            openTagManagerModal();
                            loadProjectTasks();
                        }
                    });
                };

                // Edit & Delete tags
                document.querySelectorAll('.app-tag-mgmt-item').forEach(item => {
                    let tagId = item.getAttribute('data-id');
                    let saveBtn = item.querySelector('.btn-save-tag');
                    let delBtn = item.querySelector('.btn-del-tag');
                    let nameInp = item.querySelector('.tag-name-input');
                    let colorInp = item.querySelector('.tag-color-picker');

                    saveBtn.onclick = () => {
                        let nameVal = nameInp.value.trim();
                        let colorVal = colorInp.value;
                        if(!nameVal) return;

                        let fd = new FormData();
                        fd.append('action', 'save_tag');
                        fd.append('id', tagId);
                        fd.append('name', nameVal);
                        fd.append('color', colorVal);

                        fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if(d.success) {
                                let tIdx = allTags.findIndex(t => t.id == tagId);
                                if(tIdx >= 0) allTags[tIdx] = { id: tagId, name: nameVal, color: colorVal };
                                saveBtn.innerHTML = '<i class="ph-bold ph-check"></i>';
                                setTimeout(() => { saveBtn.innerHTML = '<i class="ph-bold ph-floppy-disk"></i>'; }, 1500);
                                loadProjectTasks();
                            }
                        });
                    };

                    delBtn.onclick = () => {
                        let fd = new FormData();
                        fd.append('action', 'delete_tag');
                        fd.append('id', tagId);

                        fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if(d.success) {
                                allTags = allTags.filter(t => t.id != tagId);
                                item.remove();
                                loadProjectTasks();
                            }
                        });
                    };
                });
            };

            attachEvents();
        }
    });
}

function openTemplateManagerModal() {
    const renderTemplatesModal = () => {
        Swal.fire({
            html: `
                <div class="app-modal-dialog">
                    <div class="app-modal-header">
                        <div class="app-modal-title-group">
                            <div class="app-modal-icon-badge" style="background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent); color: var(--primary-color, #6366f1);">
                                <i class="ph-bold ph-magic-wand"></i>
                            </div>
                            <div class="app-modal-titles">
                                <span>Automatización de Flujos</span>
                                <h3>Plantillas de Procesos</h3>
                            </div>
                        </div>
                        <button class="app-close-circle" onclick="Swal.close()"><i class="ph-bold ph-x"></i></button>
                    </div>
                    <div class="app-modal-body" style="display:flex; flex-direction:column; gap:1.15rem;">
                        <!-- Action to save current project as template -->
                        <div style="background:var(--bg-color); border:1px solid var(--border-color); border-radius:18px; padding:1.15rem 1.25rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:700; font-size:0.95rem; color:var(--text-main);"><i class="ph-bold ph-bookmark-simple" style="color:var(--secondary-color, #10b981);"></i> Guardar Fases Actuales como Plantilla</div>
                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.2rem;">Convierte todas las fases y tareas de este proyecto en una nueva plantilla reutilizable.</div>
                            </div>
                            <button type="button" id="btn-save-as-template" class="btn-app-submit" style="padding:0.55rem 1.15rem; font-size:0.84rem; background:var(--secondary-color, #10b981); cursor:pointer; white-space:nowrap;">
                                <i class="ph-bold ph-floppy-disk"></i> Guardar como Plantilla
                            </button>
                        </div>

                        <div>
                            <label class="app-form-label" style="margin-bottom:0.6rem;"><i class="ph-bold ph-list-dashes"></i> Plantillas Disponibles</label>
                            <div id="templates-list-container" style="display:flex; flex-direction:column; gap:0.85rem; max-height:45vh; overflow-y:auto; padding-right:0.25rem;">
                                <div style="text-align:center; padding:2rem; color:var(--text-muted);"><i class="ph-bold ph-spinner-gap" style="animation:spin 1s linear infinite;"></i> Cargando plantillas...</div>
                            </div>
                        </div>
                    </div>
                    <div class="app-modal-footer">
                        <button onclick="Swal.close()" class="btn-app-cancel">Cerrar</button>
                    </div>
                </div>
            `,
            width: window.innerWidth < 800 ? '96vw' : '740px',
            showConfirmButton: false,
            showCancelButton: false,
            customClass: { popup: 'swal2-zero-pad swal2-app-modal', actions: 'app-modal-actions' },
            didOpen: () => {
                // Save current project as template
                document.getElementById('btn-save-as-template').onclick = () => {
                    Swal.fire({
                        html: `
                            <div class="app-modal-dialog">
                                <div class="app-modal-header">
                                    <div class="app-modal-title-group">
                                        <div class="app-modal-icon-badge">
                                            <i class="ph-bold ph-bookmark-simple"></i>
                                        </div>
                                        <div class="app-modal-titles">
                                            <span>Nueva Plantilla</span>
                                            <h3>Guardar Plantilla de Procesos</h3>
                                        </div>
                                    </div>
                                    <button class="app-close-circle" onclick="openTemplateManagerModal()"><i class="ph-bold ph-x"></i></button>
                                </div>
                                <div class="app-modal-body">
                                    <div class="app-form-group">
                                        <label class="app-form-label">Nombre de la Plantilla</label>
                                        <input type="text" id="swal-tmpl-name" class="app-input-title" placeholder="Ej. Identidad Corporativa Premium, Branding E-commerce...">
                                    </div>
                                    <div class="app-form-group">
                                        <label class="app-form-label">Descripción Breve</label>
                                        <textarea id="swal-tmpl-desc" class="app-textarea" placeholder="Describe cuándo utilizar este flujo de trabajo..."></textarea>
                                    </div>
                                </div>
                                <div class="app-modal-footer">
                                    <button onclick="openTemplateManagerModal()" class="btn-app-cancel">Cancelar</button>
                                    <button id="btn-confirm-save-tmpl" class="btn-app-submit"><i class="ph-bold ph-check"></i> Guardar Plantilla</button>
                                </div>
                            </div>
                        `,
                        width: window.innerWidth < 600 ? '95vw' : '560px',
                        showConfirmButton: false,
                        showCancelButton: false,
                        customClass: { popup: 'swal2-zero-pad swal2-app-modal', actions: 'app-modal-actions' },
                        didOpen: () => {
                            document.getElementById('btn-confirm-save-tmpl').onclick = () => {
                                let nameVal = document.getElementById('swal-tmpl-name').value.trim();
                                let descVal = document.getElementById('swal-tmpl-desc').value.trim();
                                if(!nameVal) { document.getElementById('swal-tmpl-name').focus(); return; }

                                // Gather current project tasks and groups
                                fetch('ajax/ajax_desarrollo_marca.php', {
                                    method: 'POST',
                                    body: new URLSearchParams({ action: 'get_project_tasks', project_id: PROJECT_ID })
                                })
                                .then(r => r.json())
                                .then(tasksData => {
                                    if(!tasksData.success || !tasksData.groups || tasksData.groups.length === 0) {
                                        alert('No hay fases en este proyecto para guardar como plantilla. Primero agrega al menos una fase.');
                                        return;
                                    }

                                    let structuredTemplate = tasksData.groups.map(g => ({
                                        name: g.name,
                                        tasks: (g.tasks || []).map(t => {
                                            let cleanTags = [];
                                            try {
                                                let pTags = JSON.parse(t.tags || '[]');
                                                cleanTags = pTags.map(pt => pt.value || pt.name || pt);
                                            } catch(e) {}
                                            return {
                                                title: t.title,
                                                description: t.description || '',
                                                tags: cleanTags,
                                                subtasks: (t.subtasks || []).map(st => ({
                                                    title: st.title,
                                                    description: st.description || '',
                                                    completed: 0
                                                }))
                                            };
                                        })
                                    }));

                                    let fd = new FormData();
                                    fd.append('action', 'save_template');
                                    fd.append('id', 0);
                                    fd.append('name', nameVal);
                                    fd.append('description', descVal);
                                    fd.append('template_data', JSON.stringify(structuredTemplate));

                                    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                                    .then(r => r.json())
                                    .then(saveRes => {
                                        if(saveRes.success) {
                                            openTemplateManagerModal();
                                        } else {
                                            alert('Error: ' + (saveRes.message || 'No se pudo guardar la plantilla'));
                                        }
                                    });
                                });
                            };
                        }
                    });
                };

                // Fetch and render templates list
                fetch('ajax/ajax_desarrollo_marca.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'get_templates' })
                })
                .then(r => r.json())
                .then(d => {
                    const listContainer = document.getElementById('templates-list-container');
                    if (!d.success || !d.templates || d.templates.length === 0) {
                        listContainer.innerHTML = '<div style="text-align:center; padding:1.5rem; color:var(--text-muted);">No hay plantillas registradas.</div>';
                        return;
                    }

                    listContainer.innerHTML = d.templates.map(tmpl => {
                        let parsed = [];
                        try { parsed = JSON.parse(tmpl.template_data || '[]'); } catch(e) {}

                        let phasesPreviewHtml = parsed.map(g => `
                            <span style="font-size:0.75rem; background:var(--bg-surface); border:1px solid var(--border-color); color:var(--text-muted); padding:0.25rem 0.6rem; border-radius:8px;">
                                ${g.name} (${g.tasks ? g.tasks.length : 0})
                            </span>
                        `).join('');

                        return `
                            <div class="template-card-row" style="background:var(--bg-color); border:1px solid var(--border-color); border-radius:18px; padding:1.15rem 1.25rem; display:flex; flex-direction:column; gap:0.75rem; transition:all 0.2s ease;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                                    <div style="flex:1;">
                                        <div style="font-weight:800; font-size:1.05rem; color:var(--text-main); margin-bottom:0.25rem; display:flex; align-items:center; gap:0.5rem;">
                                            <i class="ph-bold ph-folder-notch-open" style="color:var(--primary-color);"></i>
                                            ${tmpl.name}
                                        </div>
                                        <div style="font-size:0.84rem; color:var(--text-muted); line-height:1.4;">
                                            ${tmpl.description || 'Sin descripción'}
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:0.4rem;">
                                        <button type="button" class="btn-app-submit btn-apply-template" data-id="${tmpl.id}" style="padding:0.55rem 1.15rem; font-size:0.84rem; background:var(--secondary-color, #10b981); white-space:nowrap; flex-shrink:0; cursor:pointer;">
                                            <i class="ph-bold ph-lightning"></i> Cargar a Proyecto
                                        </button>
                                        <button type="button" class="btn-action del btn-delete-template" data-id="${tmpl.id}" title="Eliminar Plantilla" style="width:34px; height:34px; border-radius:10px;">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; border-top:1px solid var(--border-color); padding-top:0.65rem;">
                                    <span style="font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Fases (${parsed.length}):</span>
                                    ${phasesPreviewHtml}
                                </div>
                            </div>
                        `;
                    }).join('');

                    // Bind apply button
                    document.querySelectorAll('.btn-apply-template').forEach(btn => {
                        btn.onclick = () => {
                            let tmplId = btn.getAttribute('data-id');
                            btn.disabled = true;
                            btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="animation:spin 1s linear infinite;"></i> Aplicando...';

                            let fd = new FormData();
                            fd.append('action', 'apply_template');
                            fd.append('project_id', PROJECT_ID);
                            fd.append('template_id', tmplId);

                            fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    Swal.close();
                                    loadProjectTasks();
                                    loadTagsForTasks();
                                } else {
                                    alert('Error al aplicar plantilla: ' + (res.message || 'Error desconocido'));
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="ph-bold ph-lightning"></i> Cargar a Proyecto';
                                }
                            });
                        };
                    });

                    // Bind delete template button
                    document.querySelectorAll('.btn-delete-template').forEach(delBtn => {
                        delBtn.onclick = () => {
                            let tmplId = delBtn.getAttribute('data-id');
                            if(!confirm('¿Estás seguro de eliminar esta plantilla?')) return;

                            let fd = new FormData();
                            fd.append('action', 'delete_template');
                            fd.append('id', tmplId);

                            fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(res => {
                                if(res.success) {
                                    openTemplateManagerModal();
                                }
                            });
                        };
                    });
                });
            }
        });
    };

    renderTemplatesModal();
}

function updateViewTimers() {
    let now = new Date();
    document.querySelectorAll('.modern-timer').forEach(el => {
        let dueStr = el.getAttribute('data-due');
        let startStr = el.getAttribute('data-start');
        if(!dueStr) return;
        let due = new Date(dueStr + 'T23:59:59');
        let start = startStr ? new Date(startStr + 'T00:00:00') : new Date();
        let textEl = el.querySelector('.timer-text');
        let diff = 0;
        if (now < start) { diff = due - start; el.style.background = 'rgba(59, 130, 246, 0.85)'; el.classList.remove('expired'); } 
        else if (now >= start && now <= due) { diff = due - now; el.style.background = 'rgba(15, 23, 42, 0.75)'; el.classList.remove('expired'); } 
        else { el.classList.add('expired'); el.style.background = 'rgba(239, 68, 68, 0.85)'; textEl.innerHTML = 'Tiempo agotado'; return; }
        let days = Math.floor(diff / (1000 * 60 * 60 * 24));
        let hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        let mins = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
        let secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');
        textEl.innerHTML = days > 0 ? `${days}d ${hours}:${mins}:${secs}` : `${hours}:${mins}:${secs}`;
    });
}
setInterval(updateViewTimers, 1000);
updateViewTimers();
</script>

<?php require_once 'includes/footer.php'; ?>
