<?php
// modules/forms/index.php — List of form templates (Modern App UI)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}
require_once 'includes/header.php';

$stmt = $db->query("SELECT ft.*, 
    (SELECT COUNT(*) FROM form_submissions WHERE template_id = ft.id) as submission_count,
    u.name as creator_name
    FROM form_templates ft 
    LEFT JOIN users u ON ft.created_by = u.id
    ORDER BY ft.created_at DESC");
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics
$totalForms = count($forms);
$activeForms = 0;
$draftForms = 0;
$totalSubmissions = 0;
foreach ($forms as $f) {
    if ($f['status'] === 'active') $activeForms++;
    if ($f['status'] === 'draft') $draftForms++;
    $totalSubmissions += (int)($f['submission_count'] ?? 0);
}
?>

<style>
/* Base Form Layout Variables & Scoped Styles */
.forms-app-container {
    padding: 0.25rem 0 2rem;
}

/* Header Area */
.forms-app-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
}

.forms-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.forms-header-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.forms-header-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-title);
    line-height: 1.2;
}

.forms-header-desc {
    margin: 0.2rem 0 0;
    color: var(--text-muted);
    font-size: 0.8125rem;
}

.btn-new-form {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-color);
    color: #ffffff;
    font-weight: 600;
    font-size: 0.8125rem;
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 35%, transparent);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-new-form:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--primary-color) 45%, transparent);
    color: #ffffff;
}

/* Metrics Row */
.forms-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.forms-metric-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
}

.forms-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
}

.metric-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.metric-icon-total { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.metric-icon-active { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.metric-icon-subs { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
.metric-icon-draft { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

.metric-val {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--color-title);
    line-height: 1.1;
}

.metric-lbl {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 0.15rem;
}

/* Filter & Search Bar */
.forms-toolbar {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 0.75rem 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.forms-search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.forms-search-box i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
    pointer-events: none;
}

.forms-search-input {
    width: 100%;
    padding: 0.5rem 1rem 0.5rem 2.4rem;
    border: 1px solid var(--border-color);
    border-radius: 9px;
    background: var(--bg-color);
    color: var(--color-title);
    font-size: 0.8125rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.forms-search-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 12%, transparent);
}

.forms-filter-pills {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.filter-pill {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    user-select: none;
}

.filter-pill:hover {
    color: var(--color-title);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}

.filter-pill.active {
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, transparent);
}

.filter-pill .pill-count {
    font-size: 0.7rem;
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.06);
}
[data-theme="dark"] .filter-pill .pill-count {
    background: rgba(255,255,255,0.08);
}
.filter-pill.active .pill-count {
    background: color-mix(in srgb, var(--primary-color) 25%, transparent);
    color: var(--primary-color);
}

/* Forms Grid */
.forms-app-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}

/* Modern Form Card */
.form-app-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .form-app-card {
    background: #0e0e12;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}

.form-app-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.09);
    border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color));
}

[data-theme="dark"] .form-app-card:hover {
    box-shadow: 0 14px 36px rgba(0, 0, 0, 0.65);
    border-color: var(--primary-color);
}

/* Visual Cover Banner */
.form-card-cover {
    height: 115px;
    width: 100%;
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 0.85rem 1.15rem;
    background-size: cover;
    background-position: center;
}

.form-card-cover-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.65) 100%);
    pointer-events: none;
}

.form-card-avatar {
    position: relative;
    z-index: 2;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: var(--bg-surface);
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.28);
    margin-bottom: -22px;
    overflow: hidden;
    flex-shrink: 0;
}

[data-theme="dark"] .form-card-avatar {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.15);
}

.form-card-avatar i {
    font-size: 1.4rem;
    color: var(--primary-color);
}

.form-card-avatar img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
}

[data-theme="dark"] .form-card-avatar img {
    filter: brightness(0) invert(1);
}

.form-card-status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 0.28rem 0.65rem;
    border-radius: 20px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.badge-active {
    background: rgba(16, 185, 129, 0.9);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

.badge-draft {
    background: rgba(245, 158, 11, 0.9);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}

.badge-archived {
    background: rgba(100, 116, 139, 0.9);
    color: #ffffff;
}

/* Card Body Content */
.form-card-body {
    padding: 1.6rem 1.25rem 1.25rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.85rem;
}

.form-card-title-link {
    text-decoration: none;
    color: var(--color-title);
    display: block;
    transition: color 0.15s;
}

.form-card-title {
    font-size: 1.12rem;
    font-weight: 700;
    margin: 0 0 0.35rem;
    line-height: 1.3;
    color: var(--color-title);
}

.form-card-title-link:hover .form-card-title {
    color: var(--primary-color);
}

.form-card-desc {
    font-size: 0.8125rem;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.45em;
}

/* Metadata Chips Row */
.form-card-meta-chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    padding: 0.65rem 0;
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}

.form-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.28rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--color-title);
}

[data-theme="dark"] .form-meta-chip {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.08);
}

.form-meta-chip i {
    color: var(--primary-color);
    font-size: 0.85rem;
}

.form-meta-chip.chip-date {
    margin-left: auto;
    color: var(--text-muted);
    font-weight: 500;
}

/* Card Actions Bar */
.form-card-actions-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.35rem;
}

.btn-card-action {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.6rem 0.65rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--color-title);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
}

[data-theme="dark"] .btn-card-action {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.08);
}

.btn-card-action:hover {
    background: var(--bg-surface);
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-card-action.action-edit {
    background: color-mix(in srgb, var(--primary-color) 12%, var(--bg-surface));
    border-color: color-mix(in srgb, var(--primary-color) 30%, transparent);
    color: var(--primary-color);
}

.btn-card-action.action-edit:hover {
    background: var(--primary-color);
    color: #ffffff;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
}

.badge-sub-count {
    background: var(--primary-color);
    color: #ffffff;
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    font-weight: 700;
}

.btn-card-action.action-edit .badge-sub-count {
    background: #ffffff;
    color: var(--primary-color);
}

.btn-card-icon-delete {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.18s ease;
    flex-shrink: 0;
}

[data-theme="dark"] .btn-card-icon-delete {
    background: #141419;
    border-color: rgba(255, 255, 255, 0.08);
}

.btn-card-icon-delete:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.3);
    transform: scale(1.05);
}

/* Empty State Modern */
.forms-empty-box {
    background: var(--bg-surface);
    border: 1.5px dashed var(--border-color);
    border-radius: 20px;
    padding: 4rem 1.5rem;
    text-align: center;
    max-width: 600px;
    margin: 1.5rem auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}

.empty-icon-wrapper {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 16px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.forms-empty-box h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0 0 0.5rem;
}

.forms-empty-box p {
    font-size: 0.85rem;
    color: var(--text-muted);
    max-width: 420px;
    margin: 0 auto 1.5rem;
    line-height: 1.5;
}

/* Share & Delete Modals (Glassmorphism & Clean Shadows) */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1060;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-app-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    width: 100%;
    max-width: 480px;
    padding: 1.75rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    animation: modalScaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

@keyframes modalScaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.modal-close-btn {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.15s;
}

.modal-close-btn:hover {
    color: var(--color-title);
    background: var(--bg-surface);
}

.share-header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1rem;
}

.share-link-wrapper {
    display: flex;
    gap: 0.5rem;
    margin: 1.25rem 0 1.5rem;
}

.share-link-input {
    flex: 1;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-color);
    color: var(--color-title);
    font-size: 0.8125rem;
    font-family: monospace;
    outline: none;
}

.btn-copy-link {
    background: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    transition: all 0.15s;
}

.btn-copy-link:hover {
    opacity: 0.9;
}

.share-actions-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.btn-share-whatsapp {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.65rem;
    border-radius: 10px;
    background: rgba(37, 211, 102, 0.12);
    border: 1px solid rgba(37, 211, 102, 0.3);
    color: #25d366;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s;
}

.btn-share-whatsapp:hover {
    background: #25d366;
    color: #ffffff;
}

.btn-share-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.65rem;
    border-radius: 10px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--color-title);
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s;
}

.btn-share-preview:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}
</style>

<div class="forms-app-container">
    <!-- Top Header -->
    <div class="forms-app-header">
        <div class="forms-header-left">
            <div class="forms-header-icon">
                <i class="ph-fill ph-note-pencil"></i>
            </div>
            <div>
                <h1 class="forms-header-title">Formularios</h1>
                <p class="forms-header-desc">Gestiona plantillas de brief, recopila respuestas y comparte formularios interactivos</p>
            </div>
        </div>
        <a href="index.php?module=forms&action=builder" class="btn-new-form">
            <i class="ph-bold ph-plus"></i> Nuevo Formulario
        </a>
    </div>

    <!-- Quick Metrics -->
    <div class="forms-metrics-grid">
        <div class="forms-metric-card">
            <div class="metric-icon-box metric-icon-total">
                <i class="ph-fill ph-files"></i>
            </div>
            <div>
                <div class="metric-val"><?php echo $totalForms; ?></div>
                <div class="metric-lbl">Total Formularios</div>
            </div>
        </div>
        <div class="forms-metric-card">
            <div class="metric-icon-box metric-icon-active">
                <i class="ph-fill ph-check-circle"></i>
            </div>
            <div>
                <div class="metric-val"><?php echo $activeForms; ?></div>
                <div class="metric-lbl">Publicados y Activos</div>
            </div>
        </div>
        <div class="forms-metric-card">
            <div class="metric-icon-box metric-icon-subs">
                <i class="ph-fill ph-envelope-open"></i>
            </div>
            <div>
                <div class="metric-val"><?php echo $totalSubmissions; ?></div>
                <div class="metric-lbl">Respuestas Recibidas</div>
            </div>
        </div>
        <div class="forms-metric-card">
            <div class="metric-icon-box metric-icon-draft">
                <i class="ph-fill ph-pencil-simple-line"></i>
            </div>
            <div>
                <div class="metric-val"><?php echo $draftForms; ?></div>
                <div class="metric-lbl">Borradores</div>
            </div>
        </div>
    </div>

    <!-- Toolbar: Search and Filter Pills -->
    <div class="forms-toolbar">
        <div class="forms-search-box">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" id="formsSearch" class="forms-search-input" placeholder="Buscar formulario por título o descripción..." oninput="filterForms()">
        </div>
        <div class="forms-filter-pills">
            <div class="filter-pill active" onclick="setStatusFilter('all', this)">
                Todos <span class="pill-count"><?php echo $totalForms; ?></span>
            </div>
            <div class="filter-pill" onclick="setStatusFilter('active', this)">
                <i class="ph-fill ph-circle" style="font-size: 0.55rem; color: #10b981;"></i> Publicados <span class="pill-count"><?php echo $activeForms; ?></span>
            </div>
            <div class="filter-pill" onclick="setStatusFilter('draft', this)">
                <i class="ph-fill ph-circle" style="font-size: 0.55rem; color: #f59e0b;"></i> Borradores <span class="pill-count"><?php echo $draftForms; ?></span>
            </div>
        </div>
    </div>

    <?php if (empty($forms)): ?>
    <!-- Empty State -->
    <div class="forms-empty-box">
        <div class="empty-icon-wrapper">
            <i class="ph-fill ph-file-plus"></i>
        </div>
        <h3>Aún no tienes formularios creados</h3>
        <p>Crea briefs y cuestionarios interactivos para compartir con tus clientes y recibir información de manera ágil y organizada.</p>
        <a href="index.php?module=forms&action=builder" class="btn-new-form">
            <i class="ph-bold ph-plus"></i> Crear mi primer formulario
        </a>
    </div>
    <?php else: ?>
    <!-- Forms Grid -->
    <div class="forms-app-grid" id="formsGrid">
        <?php foreach($forms as $form): 
            $statusClass = 'badge-' . $form['status'];
            $statusLabel = ['active' => 'Publicado', 'draft' => 'Borrador', 'archived' => 'Archivado'][$form['status']] ?? $form['status'];
            $fields = json_decode($form['fields_json'] ?: '[]', true);
            $fieldCount = count($fields);
            $subCount = (int)($form['submission_count'] ?? 0);
            $dateStr = date('d/m/Y', strtotime($form['created_at']));

            $settings = json_decode($form['settings_json'] ?: '{}', true);
            $coverPreset = $settings['cover_image'] ?? 'nebula';
            $viewStyle = $settings['view_style'] ?? 'hero_cover';

            $coverStyles = [
                'nebula' => 'radial-gradient(circle at 20% 20%, #4338ca 0%, transparent 40%), radial-gradient(circle at 80% 80%, #7c3aed 0%, transparent 40%), radial-gradient(circle at 50% 50%, #1e1b4b 0%, #09090b 100%)',
                'cyber' => 'radial-gradient(circle at 80% 20%, #0ea5e9 0%, transparent 45%), radial-gradient(circle at 20% 80%, #10b981 0%, transparent 45%), linear-gradient(135deg, #020617 0%, #0f172a 100%)',
                'velvet' => 'radial-gradient(circle at 75% 30%, #e11d48 0%, transparent 40%), radial-gradient(circle at 25% 70%, #9333ea 0%, transparent 40%), linear-gradient(135deg, #18181b 0%, #09090b 100%)',
                'geometry' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #020617 100%)',
                'sunset' => 'radial-gradient(circle at 80% 20%, #f59e0b 0%, transparent 45%), radial-gradient(circle at 20% 80%, #ec4899 0%, transparent 45%), linear-gradient(135deg, #18181b 0%, #050505 100%)',
                'abstract' => "url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80') center/cover"
            ];

            $coverBg = $coverStyles[$coverPreset] ?? (
                (str_starts_with($coverPreset, 'http') || str_starts_with($coverPreset, 'data:'))
                    ? "url('" . htmlspecialchars($coverPreset, ENT_QUOTES) . "') center/cover"
                    : $coverStyles['nebula']
            );

            $viewStyleNames = [
                'hero_cover' => 'Portada',
                'slides' => 'Diapositivas',
                'minimal' => 'Minimal'
            ];
            $styleName = $viewStyleNames[$viewStyle] ?? 'Portada';
        ?>
        <div class="form-app-card" data-status="<?php echo htmlspecialchars($form['status']); ?>" data-title="<?php echo htmlspecialchars(strtolower($form['title'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($form['description'] ?? '')); ?>">
            <!-- Visual Cover Header -->
            <div class="form-card-cover" style="background: <?php echo $coverBg; ?>;">
                <div class="form-card-cover-overlay"></div>
                <span class="form-card-status-badge <?php echo $statusClass; ?>">
                    <i class="ph-fill <?php echo $form['status']==='active'?'ph-check-circle':($form['status']==='draft'?'ph-pencil-simple':'ph-archive'); ?>"></i>
                    <?php echo $statusLabel; ?>
                </span>
                <div class="form-card-avatar">
                    <?php if(!empty($global_settings['logo_light'])): ?>
                        <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo">
                    <?php else: ?>
                        <i class="ph-bold ph-shield-check"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Body -->
            <div class="form-card-body">
                <div>
                    <a href="index.php?module=forms&action=builder&id=<?php echo $form['id']; ?>" class="form-card-title-link">
                        <h3 class="form-card-title" title="<?php echo htmlspecialchars($form['title']); ?>"><?php echo htmlspecialchars($form['title']); ?></h3>
                    </a>
                    <p class="form-card-desc"><?php echo htmlspecialchars($form['description'] ?: 'Sin descripción adicional para este formulario.'); ?></p>
                </div>

                <div class="form-card-meta-chips">
                    <div class="form-meta-chip" title="Respuestas recibidas">
                        <i class="ph-bold ph-envelope-open"></i>
                        <span><strong><?php echo $subCount; ?></strong> <?php echo $subCount === 1 ? 'respuesta' : 'respuestas'; ?></span>
                    </div>
                    <div class="form-meta-chip" title="Campos configurados">
                        <i class="ph-bold ph-list-numbers"></i>
                        <span><strong><?php echo $fieldCount; ?></strong> campos</span>
                    </div>
                    <div class="form-meta-chip" title="Estilo de formulario">
                        <i class="ph-bold <?php echo $viewStyle==='slides'?'ph-slides':($viewStyle==='minimal'?'ph-rows':'ph-paint-brush'); ?>"></i>
                        <span><?php echo $styleName; ?></span>
                    </div>
                    <div class="form-meta-chip chip-date" title="Fecha de creación">
                        <i class="ph ph-calendar-blank"></i>
                        <span><?php echo $dateStr; ?></span>
                    </div>
                </div>

                <div class="form-card-actions-bar">
                    <a href="index.php?module=forms&action=builder&id=<?php echo $form['id']; ?>" class="btn-card-action action-edit" title="Editar Formulario">
                        <i class="ph-bold ph-pencil-simple"></i>
                        <span>Editar</span>
                    </a>
                    
                    <?php if($form['status'] === 'active' && !empty($form['public_token'])): ?>
                    <button type="button" class="btn-card-action" onclick="shareForm('<?php echo htmlspecialchars($form['public_token']); ?>', '<?php echo htmlspecialchars(addslashes($form['title'])); ?>')" title="Compartir Enlace">
                        <i class="ph-bold ph-share-network"></i>
                        <span>Compartir</span>
                    </button>
                    <?php endif; ?>

                    <a href="index.php?module=forms&action=submissions&id=<?php echo $form['id']; ?>" class="btn-card-action" title="Ver Respuestas">
                        <i class="ph-bold ph-tray"></i>
                        <span>Respuestas</span>
                        <?php if($subCount > 0): ?>
                            <span class="badge-sub-count"><?php echo $subCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <button type="button" class="btn-card-icon-delete" onclick="deleteForm(<?php echo $form['id']; ?>)" title="Eliminar Formulario">
                        <i class="ph-bold ph-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
        <i class="ph ph-magnifying-glass" style="font-size: 2rem; opacity: 0.4; display: block; margin-bottom: 0.5rem;"></i>
        <p style="font-size: 0.9rem; font-weight: 500;">No se encontraron formularios con ese criterio de búsqueda.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modern Share Modal -->
<div class="modal-overlay" id="shareFormModal">
    <div class="modal-app-card">
        <button class="modal-close-btn" onclick="closeShareModal()"><i class="ph ph-x"></i></button>
        <div style="text-align: center;">
            <div class="share-header-icon">
                <i class="ph-bold ph-link-simple-horizontal"></i>
            </div>
            <h3 style="margin: 0 0 0.35rem; font-size: 1.15rem; font-weight: 700; color: var(--color-title);">Compartir Formulario</h3>
            <p id="shareFormTitle" style="margin: 0; font-size: 0.85rem; color: var(--text-muted);"></p>
        </div>

        <div class="share-link-wrapper">
            <input type="text" id="shareFormLink" class="share-link-input" readonly>
            <button type="button" class="btn-copy-link" id="btnCopyLink" onclick="copyFormLink()">
                <i class="ph-bold ph-copy"></i> Copiar
            </button>
        </div>

        <div class="share-actions-row">
            <a id="shareFormWhatsapp" href="#" target="_blank" class="btn-share-whatsapp">
                <i class="ph-fill ph-whatsapp-logo" style="font-size: 1.1rem;"></i> Enviar WhatsApp
            </a>
            <a id="shareFormPreview" href="#" target="_blank" class="btn-share-preview">
                <i class="ph-bold ph-arrow-square-out"></i> Abrir Formulario
            </a>
        </div>
    </div>
</div>

<!-- Modern Delete Confirm Modal -->
<div class="modal-overlay" id="deleteFormModal" style="z-index: 1070;">
    <div class="modal-app-card" style="max-width: 400px; text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1rem;">
            <i class="ph-bold ph-trash"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem; color: var(--color-title); font-size: 1.15rem; font-weight: 700;">¿Eliminar formulario?</h3>
        <p style="margin: 0 0 1.5rem; color: var(--text-muted); font-size: 0.85rem; line-height: 1.45;">Se eliminarán también las respuestas asociadas. Esta acción no se puede deshacer.</p>
        
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" class="btn-card-action" onclick="document.getElementById('deleteFormModal').classList.remove('active')" style="padding: 0.65rem 1.25rem;">Cancelar</button>
            <button type="button" class="btn-new-form" id="btnConfirmDeleteForm" style="background: #ef4444; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35); padding: 0.65rem 1.25rem;">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'all';

function setStatusFilter(status, el) {
    currentStatusFilter = status;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    filterForms();
}

function filterForms() {
    const q = (document.getElementById('formsSearch')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.form-app-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const title = card.dataset.title || '';
        const desc = card.dataset.desc || '';
        const status = card.dataset.status || '';

        const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);
        const matchesQuery = (!q || title.includes(q) || desc.includes(q));

        if (matchesStatus && matchesQuery) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noResults = document.getElementById('noResultsMsg');
    if (noResults) {
        noResults.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

function shareForm(token, title) {
    const basePath = window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/$/, '');
    const shortToken = (token && token.length > 8) ? token.substring(0, 8) : token;
    const url = window.location.origin + (basePath ? basePath : '') + '/f/' + shortToken;
    document.getElementById('shareFormLink').value = url;
    document.getElementById('shareFormTitle').textContent = title;
    document.getElementById('shareFormWhatsapp').href = 'https://wa.me/?text=' + encodeURIComponent('Hola, por favor completa este formulario: ' + url);
    document.getElementById('shareFormPreview').href = url;
    document.getElementById('shareFormModal').classList.add('active');
}

function closeShareModal() {
    document.getElementById('shareFormModal').classList.remove('active');
}

function copyFormLink() {
    const input = document.getElementById('shareFormLink');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('btnCopyLink');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="ph-bold ph-check"></i> ¡Copiado!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

let formToDeleteId = null;
function deleteForm(id) {
    formToDeleteId = id;
    document.getElementById('btnConfirmDeleteForm').onclick = async function() {
        const fd = new FormData();
        fd.append('id', formToDeleteId);
        try {
            const res = await fetch('index.php?module=forms&action=ajax_delete_template', {method:'POST', body: fd});
            const data = await res.json();
            if (data.success) window.location.reload();
            else alert(data.error || 'Error al eliminar.');
        } catch (e) {
            alert('Error de conexión.');
        }
        document.getElementById('deleteFormModal').classList.remove('active');
    };
    document.getElementById('deleteFormModal').classList.add('active');
}

// Close modals on backdrop click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
