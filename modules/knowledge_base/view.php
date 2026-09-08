<?php
// modules/knowledge_base/view.php
require_once 'modules/knowledge_base/helpers.php';

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$article_id) {
    if (!headers_sent()) {
        header("Location: index.php?module=knowledge_base&action=index");
        exit();
    } else {
        echo "<script>window.location.href = 'index.php?module=knowledge_base&action=index';</script>";
        exit();
    }
}

// Fetch article with category and author info FIRST
$stmt = $db->prepare("
    SELECT a.*, c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon,
           u.name as author_name, u.avatar as author_avatar
    FROM kb_articles a
    JOIN kb_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ? AND a.status = 'published'
    LIMIT 1
");
$stmt->execute([$article_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

$user_id = $_SESSION['user_id'] ?? 0;
$is_logged_in = !empty($user_id);
$is_client_portal = !empty($_SESSION['client_portal_id']);

if (!$article) {
    $is_public = true;
    $is_popup = true;
    require_once 'includes/header.php';
    echo "<div class='container' style='padding: 4rem 1rem; text-align: center;'>
            <h2>Artículo no encontrado</h2>
            <p>El artículo que buscas ha sido eliminado o no está publicado.</p>
            <a href='index.php?module=knowledge_base&action=index' class='btn btn-primary' style='border-radius: 8px; margin-top: 1rem;'>Volver a la Base de Conocimiento</a>
          </div>";
    require_once 'includes/footer.php';
    exit();
}

// Access Control Enforcement & Public View Mode:
$is_public_view = ($article['audience'] === 'public' && !$is_logged_in) || (isset($_GET['public']) && $_GET['public'] == '1');

if ($article['audience'] === 'public') {
    // Public article: Anyone with the link can view it freely
    if (!$is_logged_in || $is_public_view) {
        $is_public = true;
        $is_popup = true;     // Clean reading canvas without private CRM sidebar
        $allow_scroll = true; // Enables smooth vertical scrolling in full view
    }
} elseif ($article['audience'] === 'clients') {
    // Allowed for logged-in CRM users and portal clients
    if (!$is_logged_in && !$is_client_portal) {
        if (!headers_sent()) {
            header("Location: index.php?module=auth&action=login&redirect=" . urlencode("index.php?module=knowledge_base&action=view&id=" . $article_id));
            exit();
        } else {
            echo "<script>window.location.href = 'index.php?module=auth&action=login';</script>";
            exit();
        }
    }
} else {
    // 'internal' or 'all': Requires logged-in agency team session
    if (!$is_logged_in) {
        if (!headers_sent()) {
            header("Location: index.php?module=auth&action=login&redirect=" . urlencode("index.php?module=knowledge_base&action=view&id=" . $article_id));
            exit();
        } else {
            echo "<script>window.location.href = 'index.php?module=auth&action=login';</script>";
            exit();
        }
    }
}

// Compute base URL for absolute Open Graph assets (WhatsApp/Facebook)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$sys_base_url = (!empty($global_settings['site_url'])) ? rtrim($global_settings['site_url'], '/') : ($protocol . '://' . $host . ($scriptDir ? $scriptDir : ''));

// Open Graph Title & Description
$page_title = $article['title'] . ' | ' . ($global_settings['site_name'] ?? 'Roma Agencia');
$og_desc = !empty($article['summary']) ? $article['summary'] : '';
if (empty($og_desc) && !empty($article['content'])) {
    $plain_content = trim(preg_replace('/\s+/', ' ', strip_tags($article['content'])));
    $og_desc = mb_substr($plain_content, 0, 180);
    if (mb_strlen($plain_content) > 180) $og_desc .= '...';
}
if (empty($og_desc)) {
    $og_desc = 'Consulta el procedimiento y detalles de esta guía en ' . ($global_settings['site_name'] ?? 'Roma Agencia') . '.';
}

// Open Graph Image (YouTube thumbnail, first image in content, or agency logo)
$og_image = '';
if (!empty($article['video_id'])) {
    $og_image = 'https://img.youtube.com/vi/' . $article['video_id'] . '/hqdefault.jpg';
} elseif (!empty($article['content']) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $article['content'], $img_match)) {
    $found_src = $img_match[1];
    $og_image = (strpos($found_src, 'http') === 0) ? $found_src : rtrim($sys_base_url, '/') . '/' . ltrim($found_src, '/');
} elseif (!empty($global_settings['logo_light'])) {
    $logo_src = $global_settings['logo_light'];
    $og_image = (strpos($logo_src, 'http') === 0) ? $logo_src : rtrim($sys_base_url, '/') . '/' . ltrim($logo_src, '/');
}

$og_tags = [
    'title'       => $article['title'],
    'description' => $og_desc,
    'image'       => $og_image,
    'url'         => rtrim($sys_base_url, '/') . '/?k=' . $article['id'],
    'type'        => 'article'
];

require_once 'includes/header.php';

// Check role for actions (edit, admin)
$current_role_id = 0;
if ($is_logged_in) {
    $stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt_admin->execute([$user_id]);
    $current_role_id = (int)$stmt_admin->fetchColumn();
}
$session_role = $_SESSION['user_role'] ?? null;
$is_admin = ($current_role_id === 1 || $session_role == 1 || $session_role === 'Administrador');

// Increment view count (once per session per article)
if (!isset($_SESSION['kb_viewed_' . $article_id])) {
    $db->prepare("UPDATE kb_articles SET views_count = views_count + 1 WHERE id = ?")->execute([$article_id]);
    $_SESSION['kb_viewed_' . $article_id] = true;
    $article['views_count']++;
}

// Fetch related articles from same category (filter to public if viewing publicly)
$related_filter = $is_public_view ? " AND audience = 'public'" : "";
$stmtRelated = $db->prepare("
    SELECT id, title, summary, video_id, duration_minutes, audience, created_at 
    FROM kb_articles 
    WHERE category_id = ? AND id != ? AND status = 'published' {$related_filter}
    ORDER BY created_at DESC 
    LIMIT 4
");
$stmtRelated->execute([$article['category_id'], $article_id]);
$related_articles = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

$has_video = !empty($article['video_id']);
$embed_url = $has_video ? kb_get_youtube_embed($article['video_id']) : null;
?>

<style>
/* ==========================================================================
   MODERN APP-STYLE READER (LUMINOUS SAAS CANVAS)
   ========================================================================== */

/* Top Reading Progress Bar */
.kb-reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    height: 3.5px;
    background: linear-gradient(90deg, var(--primary-color), #38bdf8, #818cf8);
    width: 0%;
    z-index: 99999;
    transition: width 0.1s ease-out;
}

.kb-reader-container {
    max-width: 1540px;
    margin: 0 auto;
    padding: 1rem 1.5rem 4rem;
    font-family: var(--font-family, 'Inter', sans-serif);
    animation: kbReaderFadeIn 0.25s ease-out;
}

/* Public / Free Access Reader Canvas Optimization */
body.is-popup .content-wrapper {
    padding: 0 !important;
    padding-top: 0 !important;
}

.kb-reader-container.is-public-reader {
    max-width: 1280px;
    padding: 1.25rem 1.25rem 4rem;
}

@keyframes kbReaderFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 1. App Shell Sticky Topbar */
.kb-reader-topbar {
    position: sticky;
    top: 0.75rem;
    z-index: 100;
    background: color-mix(in srgb, var(--bg-surface) 92%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.kb-reader-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.84rem;
    min-width: 0;
    flex-wrap: nowrap;
    overflow: hidden;
}

.kb-reader-crumb {
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    transition: color 0.15s ease;
}

.kb-reader-crumb:hover {
    color: var(--primary-color);
}

.kb-crumb-divider {
    color: var(--border-color);
    font-size: 0.8rem;
}

.kb-reader-crumb-active {
    color: var(--color-title);
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 320px;
}

.kb-reader-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-reader-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    color: var(--color-title);
    padding: 0.5rem 0.9rem;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-reader-action:hover {
    background: var(--bg-color);
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-1px);
}

.btn-reader-action-primary {
    background: var(--primary-color);
    color: #ffffff;
    border-color: var(--primary-color);
}

.btn-reader-action-primary:hover {
    background: var(--primary-hover, #4338ca);
    color: #ffffff;
}

/* 2. Reader Main Workspace Grid (72% Main / 28% Inspector) */
.kb-reader-workspace {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.75rem;
    align-items: start;
    transition: grid-template-columns 0.3s ease;
}

.kb-reader-workspace.focus-mode {
    grid-template-columns: 1fr;
    max-width: 1000px;
    margin: 0 auto;
}

.kb-reader-workspace.focus-mode .kb-reader-inspector {
    display: none;
}

/* Main Reading Column */
.kb-reader-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Article Hero Card */
.kb-reader-hero-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.kb-hero-meta-badges {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1.15rem;
    flex-wrap: wrap;
}

.kb-reader-title {
    font-size: 2.15rem;
    font-weight: 800;
    color: var(--color-title);
    line-height: 1.25;
    margin: 0 0 1rem 0;
    letter-spacing: -0.025em;
}

.kb-reader-summary {
    font-size: 1.05rem;
    color: var(--color-text);
    line-height: 1.6;
    margin: 0 0 1.5rem 0;
    font-weight: 400;
    border-left: 3px solid var(--primary-color);
    padding-left: 1rem;
}

.kb-hero-author-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 1.25rem;
    border-top: 1px solid var(--border-color);
    font-size: 0.84rem;
    color: var(--text-muted);
    flex-wrap: wrap;
    gap: 1rem;
}

.kb-author-chip {
    display: flex;
    align-items: center;
    gap: 9px;
}

.kb-author-avatar-box {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #818cf8);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.kb-hero-stats-row {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.kb-hero-stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Video Player Box */
.kb-video-player-card {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 ratio */
    background: #000000;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-color);
}

.kb-video-player-iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/* Content Reader Canvas */
.kb-reader-body-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 1.6rem 2.25rem 2.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    font-size: 1.02rem;
    line-height: 1.85;
    color: var(--color-title);
}

.kb-reader-body-card.ql-editor {
    height: auto !important;
    overflow: visible !important;
}

/* Eliminate excessive top gap from the first element inside content container */
.kb-reader-body-card > :first-child,
#kbArticleBody > :first-child,
.kb-reader-body-card.ql-editor > :first-child,
.kb-reader-body-card > h1:first-child,
.kb-reader-body-card > h2:first-child,
.kb-reader-body-card > h3:first-child,
.kb-reader-body-card > h4:first-child,
.kb-reader-body-card > h5:first-child,
.kb-reader-body-card > h6:first-child,
.kb-reader-body-card > p:first-child,
.kb-reader-body-card > ol:first-child,
.kb-reader-body-card > ul:first-child,
.kb-reader-body-card > div:first-child,
.kb-reader-body-card > blockquote:first-child,
.kb-reader-body-card > pre:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

.kb-reader-body-card h1,
.kb-reader-body-card h2,
.kb-reader-body-card h3,
.kb-reader-body-card h4 {
    color: var(--color-title);
    font-weight: 700;
    margin-top: 2.25rem;
    margin-bottom: 0.85rem;
    line-height: 1.35;
    scroll-margin-top: 5.5rem;
}

.kb-reader-body-card h2 {
    font-size: 1.55rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
    letter-spacing: -0.015em;
}

.kb-reader-body-card h3 {
    font-size: 1.25rem;
}

.kb-reader-body-card p {
    margin-top: 0;
    margin-bottom: 1.35rem;
    color: var(--color-title);
}

.kb-reader-body-card > p:empty {
    display: none !important;
}

.kb-reader-body-card ul, 
.kb-reader-body-card ol {
    margin-bottom: 1.5rem;
    padding-left: 1.75rem;
}

.kb-reader-body-card li {
    margin-bottom: 0.45rem;
}

.kb-reader-body-card blockquote {
    border-left: 4px solid var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 7%, var(--bg-surface));
    padding: 1rem 1.5rem;
    border-radius: 0 12px 12px 0;
    margin: 1.5rem 0;
    font-style: italic;
    color: var(--color-title);
}

.kb-reader-body-card pre {
    background: #0f172a;
    color: #f8fafc;
    padding: 1.25rem;
    border-radius: 12px;
    overflow-x: auto;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 0.9rem;
    margin: 1.5rem 0;
    position: relative;
}

.kb-code-copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #e2e8f0;
    border-radius: 6px;
    font-size: 0.72rem;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.kb-code-copy-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Feedback Card */
.kb-feedback-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.75rem 2rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.kb-feedback-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-title);
    margin-bottom: 1rem;
}

.kb-feedback-btns {
    display: inline-flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

.kb-btn-vote {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    padding: 0.65rem 1.25rem;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--color-title);
    cursor: pointer;
    transition: all 0.2s ease;
}

.kb-btn-vote:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.kb-btn-vote.active-yes {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    color: #10b981;
}

.kb-btn-vote.active-no {
    background: rgba(239, 68, 68, 0.15);
    border-color: #ef4444;
    color: #ef4444;
}

/* 3. Right Sticky Inspector Column */
.kb-reader-inspector {
    position: sticky;
    top: 5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.kb-inspector-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}

.kb-inspector-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-title);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Table of Contents (TOC) */
.kb-toc-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 280px;
    overflow-y: auto;
    padding-right: 4px;
}

.kb-toc-link {
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.82rem;
    line-height: 1.4;
    padding: 5px 8px;
    border-radius: 8px;
    transition: all 0.15s ease;
    display: block;
    border-left: 2px solid transparent;
}

.kb-toc-link:hover {
    color: var(--primary-color);
    background: var(--bg-color);
}

.kb-toc-link.active {
    color: var(--primary-color);
    font-weight: 700;
    border-left-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, var(--bg-surface));
}

.kb-toc-h3 {
    padding-left: 1.25rem;
    font-size: 0.78rem;
}

/* Resource Specs List */
.kb-spec-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    font-size: 0.84rem;
}

.kb-spec-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kb-spec-label {
    color: var(--text-muted);
}

.kb-spec-val {
    font-weight: 600;
    color: var(--color-title);
}

/* Related Guides Widget */
.kb-related-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.kb-related-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0.6rem;
    border-radius: 12px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.kb-related-item:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
}

.kb-related-icon-box {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: var(--bg-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.1rem;
    flex-shrink: 0;
    border: 1px solid var(--border-color);
}

.kb-related-info {
    min-width: 0;
    flex: 1;
}

.kb-related-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-title);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}

.kb-related-duration {
    font-size: 0.72rem;
    color: var(--text-muted);
}

/* 4. Mobile & Tablet Optimization */
@media (max-width: 1080px) {
    .kb-reader-workspace {
        grid-template-columns: 1fr;
    }

    .kb-reader-inspector {
        position: static;
        order: 2;
    }

    .kb-reader-body-card {
        padding: 1.35rem 1.65rem 1.75rem;
    }

    .kb-reader-hero-card {
        padding: 1.5rem;
    }

    .kb-reader-title {
        font-size: 1.65rem;
    }

    .btn-reader-text {
        display: none;
    }
}

@media (max-width: 768px) {
    body.is-popup .content-wrapper {
        padding: 0 !important;
        padding-top: 0 !important;
    }

    .kb-reader-main {
        gap: 0.85rem !important;
    }

    .kb-reader-container.is-public-reader {
        padding: 0.5rem 0.45rem 2.5rem !important;
    }

    .kb-reader-container.is-public-reader .kb-reader-hero-card {
        padding: 1.15rem 0.95rem !important;
        border-radius: 14px;
        margin-bottom: 0 !important;
    }

    .kb-reader-container .kb-reader-body-card,
    .kb-reader-container.is-public-reader .kb-reader-body-card {
        padding: 0.85rem 0.95rem 1.35rem !important;
        padding-top: 0.75rem !important;
        border-radius: 14px;
    }

    .kb-reader-container.is-public-reader .kb-inspector-card {
        padding: 1rem 0.85rem !important;
        border-radius: 14px;
    }

    .kb-reader-container.is-public-reader .kb-reader-title {
        font-size: 1.4rem !important;
        line-height: 1.28;
    }

    .kb-reader-container.is-public-reader .kb-hero-author-bar {
        margin-top: 0.85rem !important;
        padding-top: 0.75rem !important;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}

@media (max-width: 640px) {
    .kb-reader-container {
        padding: 0.75rem 0.85rem 3rem;
    }

    .kb-reader-topbar {
        position: static;
        padding: 0.65rem 0.85rem;
    }

    .kb-reader-crumb-active {
        max-width: 140px;
    }
}

/* Modal Compartir Enlace Corto */
.modal-share-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
    padding: 1rem;
}

.modal-share-overlay.active {
    display: flex !important;
}

.modal-share-box {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    padding: 1.75rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    animation: toastSlideUp 0.2s ease-out;
}
</style>

<!-- Top Reading Progress Indicator -->
<div class="kb-reading-progress" id="kbReadingProgressBar"></div>

<div class="kb-reader-container <?php echo $is_public_view ? 'is-public-reader' : ''; ?>">

    <?php if (!$is_public_view): ?>
    <!-- 1. Sticky App Shell Topbar (Solo visible para equipo/usuarios autenticados) -->
    <nav class="kb-reader-topbar">
        <div class="kb-reader-breadcrumbs">
            <a href="index.php?module=knowledge_base&action=index" class="kb-reader-crumb">
                <i class="ph ph-book-open"></i> <span class="btn-reader-text">Base de Conocimiento</span>
            </a>
            <i class="ph ph-caret-right kb-crumb-divider"></i>
            <a href="index.php?module=knowledge_base&action=index" class="kb-reader-crumb" style="color: <?php echo htmlspecialchars($article['category_color']); ?>; font-weight: 700;">
                <i class="ph <?php echo htmlspecialchars($article['category_icon'] ?: 'ph-folder'); ?>"></i>
                <?php echo htmlspecialchars($article['category_name']); ?>
            </a>
            <i class="ph ph-caret-right kb-crumb-divider"></i>
            <span class="kb-reader-crumb-active">
                <?php echo htmlspecialchars($article['title']); ?>
            </span>
        </div>

        <div class="kb-reader-actions">
            <a href="index.php?module=knowledge_base&action=index" class="btn-reader-action" title="Volver al Hub">
                <i class="ph ph-arrow-left"></i> <span class="btn-reader-text">Volver</span>
            </a>
            
            <button type="button" class="btn-reader-action" id="kbOpenShareModalBtn" title="Compartir enlace corto">
                <i class="ph ph-share-network"></i> <span class="btn-reader-text">Compartir</span>
            </button>

            <button type="button" class="btn-reader-action" id="kbToggleFocusModeBtn" title="Modo Enfoque (Ocultar lateral)">
                <i class="ph ph-arrows-out-simple"></i>
            </button>

            <?php if ($is_admin || (!empty($_SESSION['user_id']) && $article['created_by'] == $_SESSION['user_id'])): ?>
            <a href="index.php?module=knowledge_base&action=form&id=<?php echo $article['id']; ?>" class="btn-reader-action btn-reader-action-primary" title="Editar este procedimiento">
                <i class="ph ph-pencil-simple"></i> <span class="btn-reader-text">Editar</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- 2. Workspace: Main Reader + Sticky Inspector -->
    <div class="kb-reader-workspace" id="kbReaderWorkspace">
        
        <!-- Left Main Reader -->
        <main class="kb-reader-main">
            
            <!-- Hero Card -->
            <div class="kb-reader-hero-card">
                <div class="kb-hero-meta-badges">
                    <span class="badge" style="background: color-mix(in srgb, <?php echo htmlspecialchars($article['category_color']); ?> 14%, transparent); color: <?php echo htmlspecialchars($article['category_color']); ?>; font-weight: 700; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                        <i class="ph <?php echo htmlspecialchars($article['category_icon'] ?: 'ph-folder'); ?>"></i>
                        <?php echo htmlspecialchars($article['category_name']); ?>
                    </span>

                    <?php if ($article['audience'] === 'public'): ?>
                        <span class="badge" style="background: rgba(6, 182, 212, 0.12); color: #0891b2; font-weight: 700; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                            <i class="ph ph-broadcast"></i> Público (Acceso Libre)
                        </span>
                    <?php elseif ($article['audience'] === 'internal'): ?>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 600; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                            <i class="ph ph-lock-key"></i> Exclusivo Equipo Interno
                        </span>
                    <?php elseif ($article['audience'] === 'clients'): ?>
                        <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; font-weight: 600; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                            <i class="ph ph-user-check"></i> Para Clientes
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 600; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                            <i class="ph ph-globe"></i> General
                        </span>
                    <?php endif; ?>

                    <?php if ($has_video): ?>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 700; padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;">
                            <i class="ph-fill ph-youtube-logo"></i> Video Tutorial
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="kb-reader-title"><?php echo htmlspecialchars($article['title']); ?></h1>
                
                <?php if (!empty($article['summary'])): ?>
                <p class="kb-reader-summary"><?php echo htmlspecialchars($article['summary']); ?></p>
                <?php endif; ?>

                <div class="kb-hero-author-bar">
                    <div class="kb-author-chip">
                        <div class="kb-author-avatar-box">
                            <?php if (!empty($article['author_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($article['author_avatar']); ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?php echo substr($article['author_name'] ?: 'R', 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <span>Elaborado por <strong><?php echo htmlspecialchars($article['author_name'] ?: 'Equipo Roma'); ?></strong></span>
                    </div>

                    <div class="kb-hero-stats-row">
                        <div class="kb-hero-stat-item" title="Última actualización">
                            <i class="ph ph-calendar"></i> <?php echo date('d M, Y', strtotime($article['updated_at'])); ?>
                        </div>
                        <div class="kb-hero-stat-item" title="Tiempo de lectura estimado">
                            <i class="ph ph-clock"></i> ~<?php echo (int)$article['duration_minutes']; ?> min
                        </div>
                        <div class="kb-hero-stat-item" title="Visualizaciones totales">
                            <i class="ph ph-eye"></i> <?php echo (int)$article['views_count']; ?> vistas
                        </div>
                    </div>
                </div>
            </div>

            <!-- YouTube Video Player (If Present) -->
            <?php if ($has_video): ?>
            <div class="kb-video-player-card">
                <iframe class="kb-video-player-iframe" 
                        src="<?php echo htmlspecialchars($embed_url); ?>" 
                        title="<?php echo htmlspecialchars($article['title']); ?>" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen>
                </iframe>
            </div>
            <?php endif; ?>

            <!-- Article Content Canvas -->
            <?php
            $clean_article_body = preg_replace('/^(?:<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>\s*)+/i', '', trim($article['content'] ?? ''));
            $clean_article_body = preg_replace('/(?:<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>\s*)+$/i', '', $clean_article_body);
            ?>
            <article class="kb-reader-body-card ql-editor" id="kbArticleBody">
                <?php echo $clean_article_body; ?>
            </article>

            <!-- Feedback & Rating -->
            <div class="kb-feedback-card" id="kbFeedbackBox">
                <div class="kb-feedback-title">¿Te fue útil esta guía o procedimiento?</div>
                <div class="kb-feedback-btns">
                    <button type="button" class="kb-btn-vote" id="voteYesBtn" data-art-id="<?php echo $article['id']; ?>">
                        <i class="ph ph-thumbs-up"></i> Sí, me sirvió (<span id="yesCount"><?php echo (int)$article['helpful_yes']; ?></span>)
                    </button>
                    <button type="button" class="kb-btn-vote" id="voteNoBtn" data-art-id="<?php echo $article['id']; ?>">
                        <i class="ph ph-thumbs-down"></i> No resolvió mi duda (<span id="noCount"><?php echo (int)$article['helpful_no']; ?></span>)
                    </button>
                </div>
                <div id="voteMessage" style="display: none; font-size: 0.85rem; color: #10b981; font-weight: 600; margin-top: 10px;">
                    ¡Gracias por tu valoración! Nos ayuda a mantener la documentación al día.
                </div>
            </div>
        </main>

        <!-- Right Sticky Inspector Column -->
        <aside class="kb-reader-inspector">
            
            <!-- Table of Contents (TOC) -->
            <div class="kb-inspector-card" id="kbTocCard">
                <div class="kb-inspector-header">
                    <span><i class="ph ph-list-bullets"></i> En esta guía</span>
                </div>
                <nav class="kb-toc-nav" id="kbTocNav">
                    <span style="font-size: 0.78rem; color: var(--text-muted); font-style: italic;">Sin secciones detectadas</span>
                </nav>
            </div>

            <!-- Specs & Info Card -->
            <div class="kb-inspector-card">
                <div class="kb-inspector-header">
                    <span><i class="ph ph-info"></i> Ficha del Recurso</span>
                </div>
                <div class="kb-spec-list">
                    <div class="kb-spec-row">
                        <span class="kb-spec-label">Área / Categoría:</span>
                        <span class="kb-spec-val" style="color: <?php echo htmlspecialchars($article['category_color']); ?>;">
                            <?php echo htmlspecialchars($article['category_name']); ?>
                        </span>
                    </div>
                    <div class="kb-spec-row">
                        <span class="kb-spec-label">Acceso:</span>
                        <span class="kb-spec-val">
                            <?php 
                            if ($article['audience'] === 'public') echo 'Público (Acceso Libre)';
                            elseif ($article['audience'] === 'internal') echo 'Solo Equipo';
                            elseif ($article['audience'] === 'clients') echo 'Clientes & Equipo';
                            else echo 'General';
                            ?>
                        </span>
                    </div>
                    <div class="kb-spec-row">
                        <span class="kb-spec-label">Duración:</span>
                        <span class="kb-spec-val">~<?php echo (int)$article['duration_minutes']; ?> min</span>
                    </div>
                    <div class="kb-spec-row">
                        <span class="kb-spec-label">Lecturas:</span>
                        <span class="kb-spec-val"><?php echo (int)$article['views_count']; ?></span>
                    </div>
                    <div class="kb-spec-row">
                        <span class="kb-spec-label">Actualizado:</span>
                        <span class="kb-spec-val"><?php echo date('d/m/Y', strtotime($article['updated_at'])); ?></span>
                    </div>
                </div>

                <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline" onclick="openShareModal()" style="width: 100%; border-radius: 9px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="ph ph-link-simple"></i> Enlace Corto
                    </button>
                </div>
            </div>

            <!-- Related Articles Card -->
            <?php if (!empty($related_articles)): ?>
            <div class="kb-inspector-card">
                <div class="kb-inspector-header">
                    <span><i class="ph ph-stack"></i> Más de esta área</span>
                </div>
                <div class="kb-related-list">
                    <?php foreach ($related_articles as $rel): ?>
                    <a href="<?php echo $is_public_view ? '?k=' . $rel['id'] : 'index.php?module=knowledge_base&action=view&id=' . $rel['id']; ?>" class="kb-related-item">
                        <div class="kb-related-icon-box">
                            <?php if (!empty($rel['video_id'])): ?>
                                <i class="ph-fill ph-play-circle" style="color: #ef4444;"></i>
                            <?php else: ?>
                                <i class="ph ph-file-text"></i>
                            <?php endif; ?>
                        </div>
                        <div class="kb-related-info">
                            <div class="kb-related-name"><?php echo htmlspecialchars($rel['title']); ?></div>
                            <div class="kb-related-duration">
                                <i class="ph ph-clock"></i> <?php echo (int)$rel['duration_minutes']; ?> min
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

</div>

<!-- Modal Compartir Enlace Corto -->
<div class="modal-share-overlay" id="modalShareShortlink">
    <div class="modal-share-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="font-weight: 700; font-size: 1.1rem; color: var(--color-title); display: flex; align-items: center; gap: 8px;">
                <i class="ph ph-share-network" style="color: var(--primary-color);"></i> Compartir Procedimiento
            </div>
            <button type="button" onclick="closeShareModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Usa este enlace corto y directo para compartir esta guía con el equipo o clientes por chat, WhatsApp o correo:
        </p>

        <!-- Short URL Input Box -->
        <div style="display: flex; gap: 8px; margin-bottom: 1.25rem;">
            <input type="text" id="kbShortLinkInput" readonly class="form-control" style="font-family: monospace; font-size: 0.88rem; padding: 0.65rem 0.85rem; border-radius: 10px; background: var(--bg-color); color: var(--color-title);">
            <button type="button" class="btn btn-primary" id="kbCopyShortBtn" style="border-radius: 10px; white-space: nowrap; font-weight: 600; padding: 0.65rem 1rem; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ph ph-copy"></i> Copiar
            </button>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-outline" id="kbShareWhatsAppBtn" style="flex: 1; border-radius: 10px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #10b981; border-color: rgba(16,185,129,0.3);">
                <i class="ph-fill ph-whatsapp-logo" style="font-size: 1.1rem;"></i> WhatsApp
            </button>

            <button type="button" class="btn btn-outline" id="kbNativeShareBtn" style="display: none; flex: 1; border-radius: 10px; font-size: 0.85rem; align-items: center; justify-content: center; gap: 6px;">
                <i class="ph ph-export"></i> Compartir Móvil
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Reading Progress Bar
    const progressBar = document.getElementById('kbReadingProgressBar');
    window.addEventListener('scroll', () => {
        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight > 0) {
            const progress = (window.scrollY / totalHeight) * 100;
            progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
        }
    });

    // 2. Focus Mode Toggle
    const focusBtn = document.getElementById('kbToggleFocusModeBtn');
    const workspace = document.getElementById('kbReaderWorkspace');
    let isFocus = false;
    if (focusBtn && workspace) {
        focusBtn.addEventListener('click', () => {
            isFocus = !isFocus;
            workspace.classList.toggle('focus-mode', isFocus);
            focusBtn.innerHTML = isFocus ? '<i class="ph ph-arrows-in-simple"></i>' : '<i class="ph ph-arrows-out-simple"></i>';
            focusBtn.title = isFocus ? 'Salir de Modo Enfoque' : 'Modo Enfoque';
        });
    }

    // 3. Dynamic Table of Contents (TOC) with ScrollSpy
    const articleBody = document.getElementById('kbArticleBody');
    const tocNav = document.getElementById('kbTocNav');
    const tocCard = document.getElementById('kbTocCard');

    if (articleBody) {
        // Strip leading empty paragraphs or line breaks dynamically
        while (articleBody.firstElementChild && 
               articleBody.firstElementChild.tagName === 'P' && 
               (articleBody.firstElementChild.innerHTML.trim() === '<br>' || 
                articleBody.firstElementChild.innerHTML.trim() === '' || 
                articleBody.firstElementChild.textContent.trim() === '')) {
            articleBody.firstElementChild.remove();
        }
    }

    if (articleBody && tocNav) {
        const headings = articleBody.querySelectorAll('h1, h2, h3');
        if (headings.length > 0) {
            tocNav.innerHTML = '';
            headings.forEach((h, idx) => {
                const id = h.id || 'heading-' + idx;
                h.id = id;

                const a = document.createElement('a');
                a.href = '#' + id;
                a.className = 'kb-toc-link' + (h.tagName === 'H3' ? ' kb-toc-h3' : '');
                a.textContent = h.textContent.trim();
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    h.scrollIntoView({ behavior: 'smooth' });
                });
                tocNav.appendChild(a);
            });

            // ScrollSpy
            const tocLinks = tocNav.querySelectorAll('.kb-toc-link');
            window.addEventListener('scroll', () => {
                let currentId = '';
                headings.forEach(h => {
                    const top = h.getBoundingClientRect().top;
                    if (top <= 140) {
                        currentId = h.id;
                    }
                });
                tocLinks.forEach(link => {
                    link.classList.toggle('active', link.getAttribute('href') === '#' + currentId);
                });
            });
        } else {
            if (tocCard) tocCard.style.display = 'none';
        }
    }

    // 4. Code Blocks Copy Helper
    document.querySelectorAll('.kb-reader-body-card pre').forEach(pre => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'kb-code-copy-btn';
        btn.innerHTML = '<i class="ph ph-copy"></i> Copiar';
        btn.addEventListener('click', () => {
            const code = pre.querySelector('code') ? pre.querySelector('code').innerText : pre.innerText;
            navigator.clipboard.writeText(code).then(() => {
                btn.innerHTML = '<i class="ph ph-check"></i> ¡Copiado!';
                setTimeout(() => btn.innerHTML = '<i class="ph ph-copy"></i> Copiar', 2000);
            });
        });
        pre.appendChild(btn);
    });

    // 5. Short Link Sharing Modal & Logic
    const articleId = <?php echo (int)$article['id']; ?>;
    const origin = window.location.origin;
    const pathname = window.location.pathname.replace(/\/index\.php$/, '');
    const shortUrl = origin + pathname + '/?k=' + articleId;
    const articleTitle = <?php echo json_encode($article['title']); ?>;

    const shortInput = document.getElementById('kbShortLinkInput');
    if (shortInput) shortInput.value = shortUrl;

    const copyBtn = document.getElementById('kbCopyShortBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(shortUrl).then(() => {
                copyBtn.innerHTML = '<i class="ph ph-check"></i> ¡Copiado!';
                setTimeout(() => copyBtn.innerHTML = '<i class="ph ph-copy"></i> Copiar', 2000);
            }).catch(() => {
                shortInput.select();
                document.execCommand('copy');
                copyBtn.innerHTML = '<i class="ph ph-check"></i> ¡Copiado!';
                setTimeout(() => copyBtn.innerHTML = '<i class="ph ph-copy"></i> Copiar', 2000);
            });
        });
    }

    const waBtn = document.getElementById('kbShareWhatsAppBtn');
    if (waBtn) {
        waBtn.addEventListener('click', () => {
            const text = encodeURIComponent(`Consulta este procedimiento: *${articleTitle}*\n${shortUrl}`);
            window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
        });
    }

    // Native Web Share on Mobile
    const nativeBtn = document.getElementById('kbNativeShareBtn');
    if (navigator.share && nativeBtn) {
        nativeBtn.style.display = 'inline-flex';
        nativeBtn.addEventListener('click', () => {
            navigator.share({
                title: articleTitle,
                text: 'Guía: ' + articleTitle,
                url: shortUrl
            }).catch(() => {});
        });
    }

    const openShareBtn = document.getElementById('kbOpenShareModalBtn');
    if (openShareBtn) {
        openShareBtn.addEventListener('click', () => {
            if (navigator.share && window.innerWidth <= 768) {
                // On mobile, trigger direct native sheet!
                navigator.share({
                    title: articleTitle,
                    text: 'Guía: ' + articleTitle,
                    url: shortUrl
                }).catch(() => openShareModal());
            } else {
                openShareModal();
            }
        });
    }

    // 6. Feedback Voting
    const voteYes = document.getElementById('voteYesBtn');
    const voteNo = document.getElementById('voteNoBtn');
    const voteMsg = document.getElementById('voteMessage');

    function sendVote(type) {
        const artId = voteYes.dataset.artId;
        const formData = new FormData();
        formData.append('action_type', 'vote_feedback');
        formData.append('article_id', artId);
        formData.append('vote_type', type);

        fetch('index.php?module=knowledge_base&action=ajax', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (type === 'yes') {
                    voteYes.classList.add('active-yes');
                    document.getElementById('yesCount').textContent = data.helpful_yes;
                } else {
                    voteNo.classList.add('active-no');
                    document.getElementById('noCount').textContent = data.helpful_no;
                }
                voteYes.disabled = true;
                voteNo.disabled = true;
                voteMsg.style.display = 'block';
            }
        })
        .catch(err => console.error('Error voting:', err));
    }

    if (voteYes && voteNo) {
        voteYes.addEventListener('click', () => sendVote('yes'));
        voteNo.addEventListener('click', () => sendVote('no'));
    }

    // 7. Lightbox / Fancybox
    if (window.Fancybox) {
        document.querySelectorAll('.kb-reader-body-card img').forEach((img) => {
            const parentA = img.closest('a');
            if (parentA && parentA.hasAttribute('data-fancybox')) return;
            img.style.cursor = 'zoom-in';
            img.setAttribute('title', 'Clic para ampliar');
            img.addEventListener('click', () => {
                Fancybox.show([{
                    src: img.src,
                    type: 'image',
                    caption: img.alt || 'Imagen'
                }]);
            });
        });

        Fancybox.bind('.kb-reader-body-card [data-fancybox]', {
            Thumbs: { autoStart: true }
        });
    }
});

function openShareModal() {
    const modal = document.getElementById('modalShareShortlink');
    if (modal) modal.classList.add('active');
}

function closeShareModal() {
    const modal = document.getElementById('modalShareShortlink');
    if (modal) modal.classList.remove('active');
}

document.addEventListener('click', (e) => {
    const modal = document.getElementById('modalShareShortlink');
    if (e.target === modal) closeShareModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
