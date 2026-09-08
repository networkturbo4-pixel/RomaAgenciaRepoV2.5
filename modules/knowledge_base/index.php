<?php
// modules/knowledge_base/index.php
require_once 'includes/header.php';
require_once 'modules/knowledge_base/helpers.php';

// Check permissions
$user_id = $_SESSION['user_id'] ?? 0;
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$user_id]);
$current_role_id = (int)$stmt_admin->fetchColumn();

$session_role = $_SESSION['user_role'] ?? null;
$is_admin = ($current_role_id === 1 || $session_role == 1 || $session_role === 'Administrador');

// Fetch categories with active articles count
$stmtCats = $db->query("
    SELECT c.*, 
           COUNT(a.id) as total_articles,
           SUM(CASE WHEN a.video_id IS NOT NULL AND a.video_id != '' THEN 1 ELSE 0 END) as total_videos
    FROM kb_categories c
    LEFT JOIN kb_articles a ON c.id = a.category_id AND a.status = 'published'
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.order_index ASC, c.name ASC
");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// Fetch all published articles
$stmtArts = $db->query("
    SELECT a.*, c.name as category_name, c.color as category_color, c.icon as category_icon,
           u.name as author_name, u.avatar as author_avatar
    FROM kb_articles a
    JOIN kb_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.status = 'published'
    ORDER BY a.created_at DESC
");
$articles = $stmtArts->fetchAll(PDO::FETCH_ASSOC);

$total_articles = count($articles);
$total_videos = 0;
foreach ($articles as $art) {
    if (!empty($art['video_id'])) {
        $total_videos++;
    }
}
?>

<style>
/* ==========================================================================
   MODERN APP-STYLE KNOWLEDGE BASE (LUMINOUS SAAS CANVAS)
   ========================================================================== */

.kb-app-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 1.25rem 1.75rem 3.5rem;
    font-family: var(--font-family, 'Inter', sans-serif);
    animation: kbAppFadeIn 0.25s ease-out;
}

@keyframes kbAppFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 1. App Top Header */
.kb-app-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.15rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    flex-wrap: wrap;
}

.kb-header-brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.kb-brand-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color) 0%, #818cf8 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
    flex-shrink: 0;
}

.kb-brand-titles {
    display: flex;
    flex-direction: column;
}

.kb-app-title {
    font-size: 1.28rem;
    font-weight: 800;
    color: var(--color-title);
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.kb-app-stats-pill {
    font-size: 0.76rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
}

.kb-stat-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--border-color);
}

/* Center App Spotlight Search */
.kb-app-search-box {
    flex: 1;
    max-width: 480px;
    position: relative;
}

.kb-app-search-input {
    width: 100%;
    padding: 0.7rem 4rem 0.7rem 2.6rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--color-title);
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
}

.kb-app-search-input:focus {
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.kb-search-left-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1.1rem;
    pointer-events: none;
}

.kb-kbd-shortcut {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-muted);
    pointer-events: none;
    user-select: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.kb-search-clear-btn {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 3px;
    display: none;
    border-radius: 50%;
    font-size: 1rem;
}

.kb-search-clear-btn:hover {
    color: var(--color-title);
}

/* Header Actions */
.kb-app-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.btn-view-toggle {
    display: inline-flex;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 3px;
    gap: 2px;
}

.btn-view-opt {
    border: none;
    background: transparent;
    color: var(--text-muted);
    padding: 6px 10px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.btn-view-opt.active {
    background: var(--bg-surface);
    color: var(--primary-color);
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

/* 2. Main App Workspace (Sidebar + Main Area) */
.kb-app-workspace {
    display: grid;
    grid-template-columns: 270px 1fr;
    gap: 1.5rem;
    align-items: start;
}

/* Left Sidebar Rail */
.kb-app-sidebar {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.25rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    position: sticky;
    top: 1.25rem;
}

.kb-sidebar-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 0.65rem;
    padding-left: 0.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kb-nav-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 1.35rem;
}

.kb-nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    border-radius: 11px;
    color: var(--color-title);
    text-decoration: none;
    font-size: 0.86rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.18s ease;
    background: transparent;
    user-select: none;
}

.kb-nav-item:hover {
    background: var(--bg-color);
    color: var(--primary-color);
}

.kb-nav-item.active {
    background: color-mix(in srgb, var(--primary-color) 9%, var(--bg-surface));
    border-color: color-mix(in srgb, var(--primary-color) 25%, transparent);
    color: var(--primary-color);
    font-weight: 700;
}

.kb-nav-item-left {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.kb-cat-dot-icon {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: #ffffff;
    flex-shrink: 0;
}

.kb-nav-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kb-nav-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 9999px;
    background: var(--bg-color);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
}

.kb-nav-item.active .kb-nav-badge {
    background: var(--primary-color);
    color: #ffffff;
    border-color: var(--primary-color);
}

/* Right Main Content Area */
.kb-app-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Active Context & Toolbar Bar */
.kb-main-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.85rem;
}

.kb-current-view-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--color-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.kb-current-view-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 2px 0 0 0;
}

.kb-sort-dropdown {
    padding: 0.45rem 0.85rem;
    border-radius: 9px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--color-title);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    outline: none;
}

/* 3. Cards Grid View */
.kb-articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(315px, 1fr));
    gap: 1.35rem;
}

.kb-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: inherit;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.02);
    position: relative;
    cursor: pointer;
}

.kb-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.07);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
}

[data-theme="dark"] .kb-card:hover {
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.4);
    border-color: rgba(99, 102, 241, 0.5);
}

/* Card Header: Video 16:9 or Compact App Gradient */
.kb-card-media {
    position: relative;
    width: 100%;
    overflow: hidden;
    display: block;
    text-decoration: none;
}

.kb-card-media-video {
    padding-top: 56.25%; /* 16:9 ratio */
    background: #0f172a;
}

.kb-card-media-banner {
    height: 110px; /* Clean compact height, no black void */
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.kb-banner-glass-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(0,0,0,0.18);
    transition: transform 0.25s ease;
}

.kb-card:hover .kb-banner-glass-icon {
    transform: scale(1.08) rotate(3deg);
}

.kb-card-thumb {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}

.kb-card:hover .kb-card-thumb {
    transform: scale(1.05);
}

.kb-card-play-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.kb-card:hover .kb-card-play-overlay {
    background: rgba(0, 0, 0, 0.4);
}

.kb-card-play-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #ef4444;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.5);
    transition: transform 0.2s ease;
}

.kb-card:hover .kb-card-play-btn {
    transform: scale(1.12);
}

.kb-card-duration-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(15, 23, 42, 0.85);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 7px;
    border-radius: 6px;
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid rgba(255,255,255,0.12);
}

/* Card Body */
.kb-card-body {
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.kb-card-tags {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 0.65rem;
    flex-wrap: wrap;
}

.kb-badge-cat {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2.5px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.kb-badge-audience {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2.5px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-aud-public {
    background: rgba(6, 182, 212, 0.12);
    color: #0891b2;
}

.badge-aud-all {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.badge-aud-internal {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.badge-aud-clients {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
}

.kb-card-title {
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0 0 0.4rem 0;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.kb-card-title-link {
    color: var(--color-title);
    text-decoration: none;
    transition: color 0.18s ease;
}

.kb-card:hover .kb-card-title-link {
    color: var(--primary-color);
}

.kb-card-desc {
    font-size: 0.84rem;
    color: var(--color-text);
    line-height: 1.45;
    margin: 0 0 1rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.kb-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    font-size: 0.76rem;
    color: var(--text-muted);
}

.kb-card-stat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-kb-quick {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-kb-quick:hover {
    color: var(--primary-color);
    border-color: var(--primary-color);
    transform: translateY(-1px);
}

.btn-kb-del:hover {
    color: #ef4444 !important;
    border-color: #ef4444 !important;
}

/* 4. List View Mode */
.kb-articles-grid.kb-list-mode {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.kb-articles-grid.kb-list-mode .kb-card {
    flex-direction: row;
    align-items: center;
    border-radius: 12px;
    padding: 0.65rem 1rem;
}

.kb-articles-grid.kb-list-mode .kb-card-media {
    width: 72px;
    height: 48px;
    border-radius: 8px;
    flex-shrink: 0;
    padding-top: 0 !important;
    margin-right: 1rem;
}

.kb-articles-grid.kb-list-mode .kb-card-media-banner {
    height: 48px;
}

.kb-articles-grid.kb-list-mode .kb-banner-glass-icon {
    width: 32px;
    height: 32px;
    font-size: 1.15rem;
    border-radius: 8px;
}

.kb-articles-grid.kb-list-mode .kb-card-play-btn {
    width: 24px;
    height: 24px;
    font-size: 0.75rem;
}

.kb-articles-grid.kb-list-mode .kb-card-body {
    padding: 0;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex: 1;
}

.kb-articles-grid.kb-list-mode .kb-card-desc {
    display: none;
}

.kb-articles-grid.kb-list-mode .kb-card-footer {
    border-top: none;
    padding-top: 0;
    gap: 1rem;
}

/* 5. Mobile & Tablet Responsiveness */
@media (max-width: 992px) {
    .kb-app-workspace {
        grid-template-columns: 1fr;
    }
    
    .kb-app-sidebar {
        position: static;
        padding: 0.85rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .kb-nav-list {
        flex-direction: row;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        padding-bottom: 0.25rem;
    }

    .kb-nav-item {
        white-space: nowrap;
        padding: 0.5rem 0.85rem;
        border-radius: 10px;
        background: var(--bg-color);
    }

    .kb-sidebar-section-title {
        display: none;
    }

    .kb-app-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .kb-app-search-box {
        max-width: 100%;
    }
}

@media (max-width: 640px) {
    .kb-app-container {
        padding: 1rem 1rem 3rem;
    }

    .kb-articles-grid {
        grid-template-columns: 1fr;
    }

    .kb-app-actions {
        width: 100%;
        justify-content: space-between;
    }
}

/* Empty State */
.kb-empty-state {
    text-align: center;
    padding: 3.5rem 2rem;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: 18px;
    grid-column: 1 / -1;
    display: none;
}

.kb-empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 0.85rem;
}

/* Toast Notification */
.kb-toast-popup {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1e293b;
    color: #ffffff;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: toastSlideUp 0.2s ease-out;
}

@keyframes toastSlideUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-overlay.active {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.modal-overlay.active .modal-content {
    transform: scale(1) translateY(0) !important;
    opacity: 1 !important;
    visibility: visible !important;
}
</style>

<div class="kb-app-container">

    <!-- 1. App Top Header Bar -->
    <header class="kb-app-header">
        <div class="kb-header-brand">
            <div class="kb-brand-icon-box">
                <i class="ph ph-book-open"></i>
            </div>
            <div class="kb-brand-titles">
                <h1 class="kb-app-title">Base de Conocimiento</h1>
                <div class="kb-app-stats-pill">
                    <span><strong id="statArticlesCount"><?php echo $total_articles; ?></strong> guías operativas</span>
                    <span class="kb-stat-dot"></span>
                    <span><strong id="statVideosCount"><?php echo $total_videos; ?></strong> videos tutoriales</span>
                </div>
            </div>
        </div>

        <!-- Spotlight Live Search -->
        <div class="kb-app-search-box">
            <i class="ph ph-magnifying-glass kb-search-left-icon"></i>
            <input type="text" id="kbSearchInput" class="kb-app-search-input" placeholder="Buscar guías, procesos, videos..." autocomplete="off">
            <span class="kb-kbd-shortcut" id="kbKbdShortcut">Ctrl K</span>
            <button type="button" id="kbSearchClear" class="kb-search-clear-btn" title="Limpiar búsqueda"><i class="ph ph-x"></i></button>
        </div>

        <!-- Right Quick Actions -->
        <div class="kb-app-actions">
            <!-- View Mode Switcher -->
            <div class="btn-view-toggle">
                <button type="button" class="btn-view-opt active" id="btnViewGrid" title="Vista en Cuadrícula">
                    <i class="ph ph-squares-four"></i>
                </button>
                <button type="button" class="btn-view-opt" id="btnViewList" title="Vista en Lista Compacta">
                    <i class="ph ph-list-dashes"></i>
                </button>
            </div>

            <?php if ($is_admin): ?>
            <button type="button" class="btn btn-outline" id="kbOpenCategoryModalBtn" style="border-radius: 10px; font-size: 0.85rem; padding: 0.55rem 0.95rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ph ph-folders"></i> Categorías
            </button>
            <a href="index.php?module=knowledge_base&action=form" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; padding: 0.55rem 1.1rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ph ph-plus-circle"></i> Nuevo Artículo
            </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- 2. Main App Workspace -->
    <div class="kb-app-workspace">
        
        <!-- Left Sidebar Navigation -->
        <aside class="kb-app-sidebar">
            <div class="kb-sidebar-section-title">
                <span>Áreas y Categorías</span>
            </div>
            <nav class="kb-nav-list" id="kbCatNav">
                <button type="button" class="kb-nav-item active" data-cat="all">
                    <div class="kb-nav-item-left">
                        <div class="kb-cat-dot-icon" style="background: var(--primary-color);">
                            <i class="ph ph-stack"></i>
                        </div>
                        <span class="kb-nav-label">Todas las Áreas</span>
                    </div>
                    <span class="kb-nav-badge"><?php echo $total_articles; ?></span>
                </button>

                <?php foreach ($categories as $cat): ?>
                <button type="button" class="kb-nav-item" data-cat="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>">
                    <div class="kb-nav-item-left">
                        <div class="kb-cat-dot-icon" style="background: <?php echo htmlspecialchars($cat['color']); ?>;">
                            <i class="ph <?php echo htmlspecialchars($cat['icon'] ?: 'ph-folder'); ?>"></i>
                        </div>
                        <span class="kb-nav-label"><?php echo htmlspecialchars($cat['name']); ?></span>
                    </div>
                    <span class="kb-nav-badge"><?php echo (int)$cat['total_articles']; ?></span>
                </button>
                <?php endforeach; ?>
            </nav>

            <div class="kb-sidebar-section-title">
                <span>Filtros Rápidos</span>
            </div>
            <nav class="kb-nav-list" id="kbQuickFilters">
                <button type="button" class="kb-nav-item active" data-filter="all">
                    <div class="kb-nav-item-left">
                        <i class="ph ph-globe" style="color: #6366f1; font-size: 1.1rem;"></i>
                        <span class="kb-nav-label">Todos los accesos</span>
                    </div>
                </button>
                <button type="button" class="kb-nav-item" data-filter="video">
                    <div class="kb-nav-item-left">
                        <i class="ph-fill ph-youtube-logo" style="color: #ef4444; font-size: 1.1rem;"></i>
                        <span class="kb-nav-label">Con Video Tutorial</span>
                    </div>
                    <span class="kb-nav-badge"><?php echo $total_videos; ?></span>
                </button>
                <button type="button" class="kb-nav-item" data-filter="public">
                    <div class="kb-nav-item-left">
                        <i class="ph ph-broadcast" style="color: #0891b2; font-size: 1.1rem;"></i>
                        <span class="kb-nav-label">Acceso Público</span>
                    </div>
                </button>
                <button type="button" class="kb-nav-item" data-filter="internal">
                    <div class="kb-nav-item-left">
                        <i class="ph ph-lock-key" style="color: #ef4444; font-size: 1.1rem;"></i>
                        <span class="kb-nav-label">Solo Equipo Interno</span>
                    </div>
                </button>
                <button type="button" class="kb-nav-item" data-filter="clients">
                    <div class="kb-nav-item-left">
                        <i class="ph ph-user-check" style="color: #3b82f6; font-size: 1.1rem;"></i>
                        <span class="kb-nav-label">Portal de Clientes</span>
                    </div>
                </button>
            </nav>
        </aside>

        <!-- Right Content Canvas -->
        <main class="kb-app-main">
            
            <!-- Subhead context bar -->
            <div class="kb-main-toolbar">
                <div>
                    <h2 class="kb-current-view-title" id="kbActiveViewTitle">
                        <i class="ph ph-squares-four" style="color: var(--primary-color);"></i> Todas las Guías y Procedimientos
                    </h2>
                    <p class="kb-current-view-desc" id="kbActiveViewDesc">
                        Explorando los recursos y estándares publicados.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Ordenar:</span>
                    <select id="kbSortSelect" class="kb-sort-dropdown">
                        <option value="recent">Más Recientes</option>
                        <option value="views">Más Vistos</option>
                        <option value="title">Alfabético (A-Z)</option>
                        <option value="duration">Duración</option>
                    </select>
                </div>
            </div>

            <!-- Articles Grid / List Container -->
            <div class="kb-articles-grid" id="kbArticlesGrid">
                <?php foreach ($articles as $art): 
                    $has_video = !empty($art['video_id']);
                    $thumb_url = $has_video ? kb_get_youtube_thumbnail($art['video_id']) : null;
                    $view_url = "index.php?module=knowledge_base&action=view&id={$art['id']}";
                    $short_url = "?k={$art['id']}";
                ?>
                <div class="kb-card" 
                     data-id="<?php echo $art['id']; ?>"
                     data-category="<?php echo $art['category_id']; ?>"
                     data-audience="<?php echo htmlspecialchars($art['audience']); ?>"
                     data-has-video="<?php echo $has_video ? '1' : '0'; ?>"
                     data-views="<?php echo (int)$art['views_count']; ?>"
                     data-duration="<?php echo (int)$art['duration_minutes']; ?>"
                     data-date="<?php echo strtotime($art['created_at']); ?>"
                     data-title="<?php echo htmlspecialchars(mb_strtolower($art['title'])); ?>"
                     data-desc="<?php echo htmlspecialchars(mb_strtolower($art['summary'])); ?>"
                     onclick="if (!event.target.closest('.kb-card-actions') && !event.target.closest('.btn-kb-quick')) window.location.href='<?php echo $view_url; ?>';">
                    
                    <!-- Media Header: 16:9 for Video, Sleek Compact Banner for Docs -->
                    <?php if ($has_video): ?>
                    <a href="<?php echo $view_url; ?>" class="kb-card-media kb-card-media-video" tabindex="-1">
                        <img src="<?php echo htmlspecialchars($thumb_url); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>" class="kb-card-thumb" loading="lazy">
                        <div class="kb-card-play-overlay">
                            <div class="kb-card-play-btn">
                                <i class="ph-fill ph-play"></i>
                            </div>
                        </div>
                        <div class="kb-card-duration-badge">
                            <i class="ph ph-clock"></i> <?php echo (int)$art['duration_minutes']; ?> min
                        </div>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $view_url; ?>" class="kb-card-media kb-card-media-banner" tabindex="-1" style="background: linear-gradient(135deg, color-mix(in srgb, <?php echo htmlspecialchars($art['category_color']); ?> 28%, #0f172a), color-mix(in srgb, <?php echo htmlspecialchars($art['category_color']); ?> 55%, #1e1b4b));">
                        <div class="kb-banner-glass-icon">
                            <i class="ph <?php echo htmlspecialchars($art['category_icon'] ?: 'ph-book-open'); ?>"></i>
                        </div>
                        <div class="kb-card-duration-badge">
                            <i class="ph ph-clock"></i> <?php echo (int)$art['duration_minutes']; ?> min
                        </div>
                    </a>
                    <?php endif; ?>

                    <!-- Card Body -->
                    <div class="kb-card-body">
                        <div class="kb-card-tags">
                            <span class="kb-badge-cat" style="background: color-mix(in srgb, <?php echo htmlspecialchars($art['category_color']); ?> 12%, transparent); color: <?php echo htmlspecialchars($art['category_color']); ?>;">
                                <i class="ph <?php echo htmlspecialchars($art['category_icon'] ?: 'ph-folder'); ?>"></i>
                                <?php echo htmlspecialchars($art['category_name']); ?>
                            </span>

                            <?php if ($art['audience'] === 'public'): ?>
                                <span class="kb-badge-audience badge-aud-public" title="Público para cualquier usuario con el enlace">
                                    <i class="ph ph-broadcast"></i> Público
                                </span>
                            <?php elseif ($art['audience'] === 'internal'): ?>
                                <span class="kb-badge-audience badge-aud-internal" title="Exclusivo para el equipo">
                                    <i class="ph ph-lock-key"></i> Interno
                                </span>
                            <?php elseif ($art['audience'] === 'clients'): ?>
                                <span class="kb-badge-audience badge-aud-clients" title="Visible para clientes">
                                    <i class="ph ph-user-check"></i> Clientes
                                </span>
                            <?php else: ?>
                                <span class="kb-badge-audience badge-aud-all" title="Acceso general">
                                    <i class="ph ph-globe"></i> General
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="kb-card-title">
                            <a href="<?php echo $view_url; ?>" class="kb-card-title-link">
                                <?php echo htmlspecialchars($art['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="kb-card-desc"><?php echo htmlspecialchars($art['summary'] ?: 'Haz clic para leer el procedimiento completo y ver los recursos.'); ?></p>

                        <div class="kb-card-footer">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <div class="kb-card-stat" title="Visualizaciones">
                                    <i class="ph ph-eye"></i> <?php echo (int)$art['views_count']; ?>
                                </div>
                                <div class="kb-card-stat" title="Fecha de publicación">
                                    <i class="ph ph-calendar"></i> <?php echo date('d M, Y', strtotime($art['created_at'])); ?>
                                </div>
                            </div>

                            <div class="kb-card-actions" style="display: flex; gap: 5px; align-items: center;" onclick="event.stopPropagation();">
                                <!-- Short Link Copy -->
                                <button type="button" class="btn-kb-quick" title="Copiar enlace corto" onclick="copyShortLink('<?php echo $art['id']; ?>')">
                                    <i class="ph ph-share-network"></i>
                                </button>

                                <?php if ($is_admin): ?>
                                <a href="index.php?module=knowledge_base&action=form&id=<?php echo $art['id']; ?>" class="btn-kb-quick" title="Editar artículo">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                <button type="button" class="btn-kb-quick btn-kb-del" title="Eliminar artículo" onclick="quickDeleteArticle(<?php echo $art['id']; ?>, '<?php echo htmlspecialchars(addslashes($art['title'])); ?>')">
                                    <i class="ph ph-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Empty State Filter/Search -->
                <div class="kb-empty-state" id="kbEmptyState">
                    <div class="kb-empty-icon">
                        <i class="ph ph-magnifying-glass"></i>
                    </div>
                    <h3 style="font-size: 1.25rem; color: var(--color-title); margin-bottom: 0.5rem;">No se encontraron artículos</h3>
                    <p style="color: var(--color-text); max-width: 420px; margin: 0 auto 1.5rem auto; font-size: 0.88rem;">No hay recursos que coincidan con la búsqueda o el filtro actual.</p>
                    <button type="button" class="btn btn-outline" id="kbResetFiltersBtn" style="border-radius: 10px;">
                        Restablecer Filtros
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Gestión de Categorías -->
    <?php if ($is_admin): ?>
    <div id="modal-kb-categories" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="modal-content" style="background: var(--bg-surface); max-width: 620px; width: 92%; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-folders" style="color: var(--primary-color);"></i> Gestión de Categorías
                </h3>
                <button type="button" class="btn-close-modal" id="kbCloseCatModalBtn" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 4px;">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
                
                <!-- Category List -->
                <div style="font-weight: 700; font-size: 0.88rem; color: var(--color-title); margin-bottom: 0.6rem;">
                    Categorías Disponibles
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem; max-height: 200px; overflow-y: auto; padding-right: 4px;">
                    <?php foreach ($categories as $cat): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.85rem; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo htmlspecialchars($cat['color']); ?>;"></span>
                            <i class="ph <?php echo htmlspecialchars($cat['icon'] ?: 'ph-folder'); ?>" style="color: <?php echo htmlspecialchars($cat['color']); ?>;"></i>
                            <strong style="font-size: 0.88rem; color: var(--color-title);"><?php echo htmlspecialchars($cat['name']); ?></strong>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">(<?php echo (int)$cat['total_articles']; ?> guías)</span>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn-kb-quick" title="Editar categoría" onclick='editCategory(<?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, "UTF-8"); ?>)'>
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <?php if ((int)$cat['total_articles'] === 0): ?>
                            <button type="button" class="btn-kb-quick btn-kb-del" title="Eliminar categoría" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>')">
                                <i class="ph ph-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Create or Edit Category Form -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--color-title); margin-bottom: 1rem;" id="catFormHeading">
                        <i class="ph ph-plus-circle"></i> Nueva Categoría
                    </div>
                    <form id="kbCategoryForm">
                        <input type="hidden" name="id" id="catFormId" value="0">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Nombre *</label>
                                <input type="text" name="name" id="catFormName" class="form-control" required placeholder="Ej. Marketing Digital" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
                            </div>
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Color</label>
                                <input type="color" name="color" id="catFormColor" value="#4f46e5" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid var(--border-color); padding: 2px; cursor: pointer;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Icono Phosphor</label>
                                <select name="icon" id="catFormIcon" class="form-control" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
                                    <option value="ph-paint-brush-broad">Pincel / Diseño</option>
                                    <option value="ph-book-open">Libro / Guía</option>
                                    <option value="ph-student">Educación / Onboarding</option>
                                    <option value="ph-users-three">Clientes / Equipo</option>
                                    <option value="ph-receipt">Ventas / Finanzas</option>
                                    <option value="ph-video-camera">Video / Audiovisual</option>
                                    <option value="ph-gear">Sistema / Técnico</option>
                                    <option value="ph-sparkle">IA / Creatividad</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Descripción</label>
                                <input type="text" name="description" id="catFormDesc" class="form-control" placeholder="Breve descripción del área" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                            <button type="button" class="btn btn-light" id="catFormResetBtn" style="border-radius: 8px; font-size: 0.85rem;" onclick="resetCatForm()">Limpiar</button>
                            <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-size: 0.85rem; font-weight: 600;">Guardar Categoría</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('kbSearchInput');
    const searchClear = document.getElementById('kbSearchClear');
    const catItems = document.querySelectorAll('#kbCatNav .kb-nav-item');
    const quickFilterItems = document.querySelectorAll('#kbQuickFilters .kb-nav-item');
    const articles = Array.from(document.querySelectorAll('#kbArticlesGrid .kb-card'));
    const gridContainer = document.getElementById('kbArticlesGrid');
    const emptyState = document.getElementById('kbEmptyState');
    const resetBtn = document.getElementById('kbResetFiltersBtn');
    const activeViewTitle = document.getElementById('kbActiveViewTitle');
    const activeViewDesc = document.getElementById('kbActiveViewDesc');
    const btnViewGrid = document.getElementById('btnViewGrid');
    const btnViewList = document.getElementById('btnViewList');
    const sortSelect = document.getElementById('kbSortSelect');

    let currentCategory = 'all';
    let currentFilter = 'all';
    let currentQuery = '';

    // View Mode Toggle (Grid vs List) with LocalStorage
    const savedView = localStorage.getItem('kb_view_mode') || 'grid';
    applyViewMode(savedView);

    btnViewGrid.addEventListener('click', () => {
        applyViewMode('grid');
        localStorage.setItem('kb_view_mode', 'grid');
    });

    btnViewList.addEventListener('click', () => {
        applyViewMode('list');
        localStorage.setItem('kb_view_mode', 'list');
    });

    function applyViewMode(mode) {
        if (mode === 'list') {
            gridContainer.classList.add('kb-list-mode');
            btnViewList.classList.add('active');
            btnViewGrid.classList.remove('active');
        } else {
            gridContainer.classList.remove('kb-list-mode');
            btnViewGrid.classList.add('active');
            btnViewList.classList.remove('active');
        }
    }

    // Filter Cards Function
    function filterCards() {
        let visibleCount = 0;

        articles.forEach(card => {
            const cardCat = card.dataset.category;
            const cardAud = card.dataset.audience;
            const cardHasVideo = card.dataset.hasVideo === '1';
            const title = card.dataset.title || '';
            const desc = card.dataset.desc || '';

            // 1. Quick Filter Check
            let matchFilter = true;
            if (currentFilter === 'video') {
                matchFilter = cardHasVideo;
            } else if (currentFilter === 'public') {
                matchFilter = (cardAud === 'public');
            } else if (currentFilter === 'internal') {
                matchFilter = (cardAud === 'internal');
            } else if (currentFilter === 'clients') {
                matchFilter = (cardAud === 'clients' || cardAud === 'all' || cardAud === 'public');
            }

            // 2. Category Filter Check
            let matchCat = true;
            if (currentCategory !== 'all') {
                matchCat = (cardCat === currentCategory);
            }

            // 3. Search Query
            let matchSearch = true;
            if (currentQuery.trim() !== '') {
                const q = currentQuery.toLowerCase().trim();
                matchSearch = title.includes(q) || desc.includes(q);
            }

            if (matchFilter && matchCat && matchSearch) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCount === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }

    // Category Nav Item Click
    catItems.forEach(item => {
        item.addEventListener('click', () => {
            catItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentCategory = item.dataset.cat;

            if (currentCategory === 'all') {
                activeViewTitle.innerHTML = '<i class="ph ph-squares-four" style="color: var(--primary-color);"></i> Todas las Guías y Procedimientos';
                activeViewDesc.textContent = 'Explorando todos los recursos y estándares publicados.';
            } else {
                const catName = item.dataset.name || 'Categoría';
                activeViewTitle.innerHTML = `<i class="ph ph-folder" style="color: var(--primary-color);"></i> ${catName}`;
                activeViewDesc.textContent = `Procedimientos y recursos clasificados en ${catName}.`;
            }
            filterCards();
        });
    });

    // Quick Filters Item Click
    quickFilterItems.forEach(item => {
        item.addEventListener('click', () => {
            quickFilterItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentFilter = item.dataset.filter;
            filterCards();
        });
    });

    // Sorting
    sortSelect.addEventListener('change', () => {
        const val = sortSelect.value;
        const sorted = [...articles].sort((a, b) => {
            if (val === 'views') {
                return (parseInt(b.dataset.views) || 0) - (parseInt(a.dataset.views) || 0);
            } else if (val === 'duration') {
                return (parseInt(b.dataset.duration) || 0) - (parseInt(a.dataset.duration) || 0);
            } else if (val === 'title') {
                return (a.dataset.title || '').localeCompare(b.dataset.title || '');
            } else { // 'recent'
                return (parseInt(b.dataset.date) || 0) - (parseInt(a.dataset.date) || 0);
            }
        });

        sorted.forEach(card => gridContainer.appendChild(card));
        gridContainer.appendChild(emptyState);
    });

    // Spotlight Search & Shortcut Ctrl+K
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    searchInput.addEventListener('input', (e) => {
        currentQuery = e.target.value;
        searchClear.style.display = currentQuery.length > 0 ? 'block' : 'none';
        filterCards();
    });

    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        currentQuery = '';
        searchClear.style.display = 'none';
        searchInput.focus();
        filterCards();
    });

    // Reset Filters
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        currentQuery = '';
        searchClear.style.display = 'none';

        catItems.forEach(i => i.classList.remove('active'));
        catItems[0].classList.add('active');
        currentCategory = 'all';

        quickFilterItems.forEach(i => i.classList.remove('active'));
        quickFilterItems[0].classList.add('active');
        currentFilter = 'all';

        activeViewTitle.innerHTML = '<i class="ph ph-squares-four" style="color: var(--primary-color);"></i> Todas las Guías y Procedimientos';
        activeViewDesc.textContent = 'Explorando todos los recursos y estándares publicados.';

        filterCards();
    });

    // Category Modal
    window.openKbCategoryModal = function() {
        const catModal = document.getElementById('modal-kb-categories');
        if (catModal) {
            catModal.classList.add('active');
            catModal.style.display = 'flex';
        }
    };

    window.closeKbCategoryModal = function() {
        const catModal = document.getElementById('modal-kb-categories');
        if (catModal) {
            catModal.classList.remove('active');
            catModal.style.display = 'none';
        }
        if (typeof resetCatForm === 'function') resetCatForm();
    };

    const openCatBtn = document.getElementById('kbOpenCategoryModalBtn');
    const catModal = document.getElementById('modal-kb-categories');
    const closeCatBtn = document.getElementById('kbCloseCatModalBtn');
    const catForm = document.getElementById('kbCategoryForm');

    if (openCatBtn) openCatBtn.addEventListener('click', window.openKbCategoryModal);
    if (closeCatBtn) closeCatBtn.addEventListener('click', window.closeKbCategoryModal);

    if (catModal) {
        catModal.addEventListener('click', (e) => {
            if (e.target === catModal) window.closeKbCategoryModal();
        });
    }

    if (catForm) {
        catForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(catForm);
            fd.append('action_type', 'save_category');

            fetch('index.php?module=knowledge_base&action=ajax', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error al guardar categoría');
                }
            })
            .catch(err => alert('Error de conexión'));
        });
    }
});

// Short Link Generator & Copy
window.copyShortLink = function(articleId) {
    // Generate clean short URL: http(s)://domain.com/path/?k=123
    const origin = window.location.origin;
    const pathname = window.location.pathname.replace(/\/index\.php$/, '');
    const shortUrl = origin + pathname + '/?k=' + articleId;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shortUrl).then(() => {
            showToast('¡Enlace corto copiado al portapapeles!');
        }).catch(() => fallbackCopy(shortUrl));
    } else {
        fallbackCopy(shortUrl);
    }
};

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('¡Enlace corto copiado al portapapeles!');
    } catch (e) {
        prompt('Copia este enlace corto:', text);
    }
    document.body.removeChild(ta);
}

function showToast(msg) {
    const existing = document.querySelector('.kb-toast-popup');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'kb-toast-popup';
    toast.innerHTML = `<i class="ph-fill ph-check-circle" style="color: #10b981; font-size: 1.15rem;"></i> <span>${msg}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 2800);
}

function resetCatForm() {
    document.getElementById('catFormId').value = '0';
    document.getElementById('catFormName').value = '';
    document.getElementById('catFormColor').value = '#4f46e5';
    document.getElementById('catFormIcon').value = 'ph-paint-brush-broad';
    document.getElementById('catFormDesc').value = '';
    document.getElementById('catFormHeading').innerHTML = '<i class="ph ph-plus-circle"></i> Nueva Categoría';
}

function editCategory(cat) {
    document.getElementById('catFormId').value = cat.id;
    document.getElementById('catFormName').value = cat.name;
    document.getElementById('catFormColor').value = cat.color || '#4f46e5';
    document.getElementById('catFormIcon').value = cat.icon || 'ph-book-open';
    document.getElementById('catFormDesc').value = cat.description || '';
    document.getElementById('catFormHeading').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Categoría: ' + cat.name;
    document.getElementById('catFormName').focus();
}

function deleteCategory(id, name) {
    if (confirm(`¿Estás seguro de eliminar la categoría "${name}"?`)) {
        const fd = new FormData();
        fd.append('action_type', 'delete_category');
        fd.append('id', id);

        fetch('index.php?module=knowledge_base&action=ajax', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Error al eliminar');
            }
        })
        .catch(err => alert('Error de conexión'));
    }
}

function quickDeleteArticle(id, title) {
    if (confirm(`¿Estás seguro de que deseas eliminar permanentemente "${title}"?`)) {
        const fd = new FormData();
        fd.append('action_type', 'delete_article');
        fd.append('article_id', id);

        fetch('index.php?module=knowledge_base&action=ajax', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Error al eliminar');
            }
        })
        .catch(err => alert('Error de conexión'));
    }
}
</script>
