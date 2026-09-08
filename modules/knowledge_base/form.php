<?php
// modules/knowledge_base/form.php
require_once 'modules/knowledge_base/helpers.php';

// Safe redirect helper that works even if headers were sent
function kb_form_redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href = " . json_encode($url) . ";</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
        exit();
    }
}

// Check permissions (Admin or special role)
$user_id = $_SESSION['user_id'] ?? 0;
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$user_id]);
$current_role_id = (int)$stmt_admin->fetchColumn();

$session_role = $_SESSION['user_role'] ?? null;
$is_admin = ($current_role_id === 1 || $session_role == 1 || $session_role === 'Administrador');

if (!$is_admin) {
    require_once 'includes/header.php';
    echo "<div class='container' style='padding: 3rem 1rem; text-align: center;'>
            <h2>Acceso Restringido</h2>
            <p>Solo los administradores tienen permiso para crear o editar artículos.</p>
            <a href='index.php?module=knowledge_base&action=index' class='btn btn-primary'>Volver</a>
          </div>";
    require_once 'includes/footer.php';
    exit();
}

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($article_id > 0);

$article = [
    'id' => 0,
    'category_id' => 0,
    'title' => '',
    'slug' => '',
    'summary' => '',
    'content' => '',
    'video_url' => '',
    'video_id' => '',
    'duration_minutes' => 5,
    'audience' => 'all',
    'status' => 'published'
];

if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM kb_articles WHERE id = ? LIMIT 1");
    $stmt->execute([$article_id]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $article = $found;
    } else {
        kb_form_redirect("index.php?module=knowledge_base&action=index");
    }
}

// Handle Form Submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $duration_minutes = max(1, (int)($_POST['duration_minutes'] ?? 5));
    $audience = in_array($_POST['audience'] ?? '', ['public', 'all', 'internal', 'clients']) ? $_POST['audience'] : 'all';
    $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';

    if (empty($title)) {
        $error = 'El título del artículo es obligatorio.';
    } elseif ($category_id <= 0) {
        $error = 'Debes seleccionar una categoría.';
    } else {
        $video_id = !empty($video_url) ? kb_get_youtube_id($video_url) : null;
        $slug = kb_slugify($title);

        if ($is_edit) {
            $stmtUp = $db->prepare("
                UPDATE kb_articles SET 
                    category_id = ?, 
                    title = ?, 
                    slug = ?, 
                    summary = ?, 
                    content = ?, 
                    video_url = ?, 
                    video_id = ?, 
                    duration_minutes = ?, 
                    audience = ?, 
                    status = ?
                WHERE id = ?
            ");
            $stmtUp->execute([
                $category_id, $title, $slug, $summary, $content,
                $video_url, $video_id, $duration_minutes, $audience, $status,
                $article_id
            ]);

            kb_form_redirect("index.php?module=knowledge_base&action=view&id={$article_id}&msg=updated");
        } else {
            $stmtIn = $db->prepare("
                INSERT INTO kb_articles 
                (category_id, title, slug, summary, content, video_url, video_id, duration_minutes, audience, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIn->execute([
                $category_id, $title, $slug, $summary, $content,
                $video_url, $video_id, $duration_minutes, $audience, $status,
                $_SESSION['user_id'] ?? null
            ]);
            $new_id = $db->lastInsertId();

            kb_form_redirect("index.php?module=knowledge_base&action=view&id={$new_id}&msg=created");
        }
    }
}

// Now include header for rendering the page
require_once 'includes/header.php';

// Fetch all active categories
$stmtCats = $db->query("SELECT * FROM kb_categories WHERE is_active = 1 ORDER BY order_index ASC, name ASC");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Form Styling */
.kb-form-container {
    max-width: 1540px;
    margin: 0 auto;
    padding: var(--space-4) var(--space-6) var(--space-8);
    font-family: var(--font-family);
    animation: kbFadeIn 0.3s ease-out;
}

.kb-form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

/* 2-Column Grid Layout: 40% Left / 60% Right */
.kb-form-grid-layout {
    display: grid;
    grid-template-columns: 40fr 60fr;
    gap: 1.5rem;
    align-items: stretch;
    margin-bottom: 1.5rem;
}

.kb-form-col-left {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.kb-form-col-right {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.kb-form-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 1.75rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    margin-bottom: 1.5rem;
}

.kb-form-card-editor {
    height: 100%;
    display: flex;
    flex-direction: column;
    margin-bottom: 0 !important;
}

.kb-form-card-editor .kb-editor-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.kb-form-card-editor #quillEditor {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 520px;
}

.kb-form-card-editor #quillEditor .ql-editor {
    flex: 1;
    min-height: 480px;
}

.kb-form-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--color-title);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.75rem;
}

.kb-video-preview-box {
    margin-top: 1rem;
    background: var(--bg-color);
    border: 1px dashed var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    display: none;
}

.kb-video-preview-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.kb-video-preview-thumb {
    width: 140px;
    height: 79px;
    border-radius: 10px;
    object-fit: cover;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.kb-audience-selector {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.65rem;
    margin-top: 0.5rem;
}

.kb-audience-card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    cursor: pointer;
    background: var(--bg-color);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.kb-audience-card:hover {
    border-color: var(--primary-color);
}

.kb-audience-card.active {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, var(--bg-surface));
}

.kb-audience-card input {
    display: none;
}

@media (max-width: 1080px) {
    .kb-form-grid-layout {
        grid-template-columns: 1fr;
    }
    .kb-audience-selector {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .kb-form-card-editor #quillEditor {
        min-height: 380px;
    }
    .kb-form-card-editor #quillEditor .ql-editor {
        min-height: 350px;
    }
}

/* Quick Blocks Bar above Quill */
.kb-quick-blocks-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1rem;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-bottom: none;
    border-radius: 14px 14px 0 0;
    flex-wrap: wrap;
}

.kb-quick-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-right: 0.25rem;
}

.kb-quick-btns-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.btn-quick-block {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    color: var(--color-title);
    padding: 0.4rem 0.75rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.btn-quick-block:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 6%, var(--bg-surface));
    transform: translateY(-1px);
}

.btn-quick-block-accent {
    background: color-mix(in srgb, var(--primary-color) 10%, var(--bg-surface));
    border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
    color: var(--primary-color);
}
.btn-quick-block-accent:hover {
    background: var(--primary-color);
    color: #ffffff;
    border-color: var(--primary-color);
}

/* Quill & Image Manipulations */
.kb-editor-wrapper {
    position: relative;
    min-height: 420px;
    background: var(--bg-surface);
    border-radius: 0 0 14px 14px;
}

#quillEditor {
    min-height: 380px;
    font-size: 1.05rem;
    font-family: var(--font-family);
    border-radius: 0 0 14px 14px;
}

#quillEditor .ql-toolbar.ql-snow {
    border-color: var(--border-color);
    background: var(--bg-surface);
}

#quillEditor .ql-container.ql-snow {
    border-color: var(--border-color);
    border-radius: 0 0 14px 14px;
}

#quillEditor .ql-editor {
    min-height: 380px;
    padding: 1.5rem;
    font-size: 1.02rem;
    line-height: 1.8;
}

/* Ensure images never overflow */
#quillEditor .ql-editor img {
    max-width: 100% !important;
    height: auto !important;
    border-radius: 12px;
    cursor: pointer;
    transition: outline 0.15s ease, filter 0.15s ease;
    display: inline-block;
}

#quillEditor .ql-editor img:hover {
    filter: brightness(0.96);
}

#quillEditor .ql-editor img.kb-selected-img {
    outline: 3px solid var(--primary-color) !important;
    outline-offset: 3px !important;
    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.25) !important;
}

/* Floating Image Toolbar */
.kb-image-floating-toolbar {
    position: absolute;
    z-index: 1000;
    display: none;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.35);
    color: #f8fafc;
    font-size: 0.8rem;
    user-select: none;
    animation: fadeInToolbar 0.15s ease-out;
}

@keyframes fadeInToolbar {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.kb-img-toolbar-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.kb-img-toolbar-tag {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 600;
    margin-right: 2px;
}

.kb-img-toolbar-divider {
    width: 1px;
    height: 18px;
    background: #334155;
    margin: 0 4px;
}

.kb-img-btn {
    background: #334155;
    border: none;
    color: #f8fafc;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.kb-img-btn:hover {
    background: #475569;
    color: #ffffff;
}

.kb-img-btn.active {
    background: var(--primary-color);
    color: #ffffff;
}

.kb-img-btn-view {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}
.kb-img-btn-view:hover {
    background: #10b981;
    color: #ffffff;
}

.kb-img-btn-delete {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}
.kb-img-btn-delete:hover {
    background: #ef4444;
    color: #ffffff;
}

/* Gallery Block & Grid Styles */
.kb-gallery-block {
    margin: 1.75rem 0;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem;
    position: relative;
}

.kb-gallery-block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.kb-gallery-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--color-title);
    display: flex;
    align-items: center;
    gap: 6px;
}

.kb-gallery-del-btn {
    background: none;
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #ef4444;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.kb-gallery-del-btn:hover {
    background: #ef4444;
    color: white;
}

.kb-gallery-grid {
    display: grid;
    gap: 0.85rem;
}

.kb-gallery-grid.cols-2 {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.kb-gallery-grid.cols-3 {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.kb-gallery-grid.cols-4 {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.kb-gallery-item {
    position: relative;
    display: block;
    aspect-ratio: 4 / 3;
    border-radius: 10px;
    overflow: hidden;
    background: #0f172a;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    cursor: pointer;
    text-decoration: none;
}

.kb-gallery-item img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    display: block;
    border-radius: 0 !important;
    margin: 0 !important;
    border: none !important;
    transition: transform 0.3s ease;
}

.kb-gallery-item:hover img {
    transform: scale(1.06);
}

.kb-gallery-zoom-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    opacity: 0.85;
    transition: all 0.2s ease;
    pointer-events: none;
}

.kb-gallery-item:hover .kb-gallery-zoom-badge {
    opacity: 1;
    transform: scale(1.1);
    background: var(--primary-color);
}

/* Callouts */
blockquote.kb-callout-tip {
    border-left: 4px solid #10b981 !important;
    background: rgba(16, 185, 129, 0.08) !important;
    color: var(--color-title) !important;
    border-radius: 0 12px 12px 0;
    padding: 1rem 1.25rem;
    margin: 1.5rem 0;
    font-style: normal;
}

blockquote.kb-callout-warning {
    border-left: 4px solid #f59e0b !important;
    background: rgba(245, 158, 11, 0.08) !important;
    color: var(--color-title) !important;
    border-radius: 0 12px 12px 0;
    padding: 1rem 1.25rem;
    margin: 1.5rem 0;
    font-style: normal;
}

/* Gallery Modal Styles */
.kb-gallery-dropzone {
    border: 2px dashed var(--border-color);
    border-radius: 14px;
    padding: 2rem 1.5rem;
    text-align: center;
    background: var(--bg-color);
    cursor: pointer;
    transition: all 0.2s ease;
}
.kb-gallery-dropzone:hover, .kb-gallery-dropzone.dragover {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 5%, var(--bg-color));
}

.kb-gallery-picker-previews {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.6rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 4px;
}

.kb-picker-thumb {
    position: relative;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.kb-picker-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.kb-picker-thumb-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.kb-columns-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.kb-col-option input {
    display: none;
}

.kb-col-box {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem;
    text-align: center;
    cursor: pointer;
    background: var(--bg-color);
    transition: all 0.2s ease;
}

.kb-col-option input:checked + .kb-col-box {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 8%, var(--bg-surface));
    box-shadow: 0 0 0 1px var(--primary-color);
}

.kb-col-icon {
    display: block;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--color-title);
    margin-bottom: 2px;
}

.kb-col-desc {
    display: block;
    font-size: 0.72rem;
    color: var(--text-muted);
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

<div class="kb-form-container">

    <div class="kb-form-header">
        <div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">
                <a href="index.php?module=knowledge_base&action=index" style="color: var(--color-link); text-decoration: none;">
                    <i class="ph ph-arrow-left"></i> Base de Conocimiento
                </a>
            </div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--color-title); margin: 0;">
                <?php echo $is_edit ? 'Editar Artículo o Video' : 'Nuevo Artículo o Video'; ?>
            </h1>
        </div>

        <div>
            <a href="index.php?module=knowledge_base&action=index" class="btn btn-outline" style="border-radius: 10px;">
                Cancelar
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
            <i class="ph ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="kbArticleForm">
        <input type="hidden" name="content" id="quillContentInput">

        <div class="kb-form-grid-layout">
            <!-- Left Column: 40% (Información Principal + Video YouTube) -->
            <div class="kb-form-col-left">
                <!-- General Info Card -->
                <div class="kb-form-card">
                    <div class="kb-form-section-title">
                        <i class="ph ph-article"></i> Información Principal
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label for="title" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Título del Artículo / Proceso *</label>
                        <input type="text" id="title" name="title" class="form-control" required 
                               value="<?php echo htmlspecialchars($article['title']); ?>" 
                               placeholder="Ej. Protocolo de Entrega de Identidad Visual y Formatos" 
                               style="font-size: 1.05rem; padding: 0.8rem 1rem; border-radius: 10px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label for="category_id" style="font-weight: 600; margin: 0;">Categoría / Área *</label>
                            <button type="button" id="btnManageCategories" onclick="openCatModal()" style="background: none; border: none; color: var(--primary-color); font-size: 0.82rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 0;">
                                <i class="ph ph-gear-six"></i> Gestionar Categorías
                            </button>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <select id="category_id" name="category_id" class="form-control" required style="padding: 0.75rem 1rem; border-radius: 10px; flex: 1;">
                                <option value="">-- Seleccionar Categoría --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $article['category_id'] == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="btnQuickAddCategory" onclick="openCatModal(true)" class="btn btn-outline" style="border-radius: 10px; padding: 0 0.9rem; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; font-size: 0.85rem;" title="Crear nueva categoría">
                                <i class="ph ph-plus"></i> Nueva
                            </button>
                        </div>
                    </div>

                    <!-- Tiempo Estimado & Estado de Publicación -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group">
                            <label for="duration_minutes" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Tiempo (Minutos)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" max="180" 
                                   value="<?php echo (int)$article['duration_minutes']; ?>" 
                                   style="padding: 0.75rem 1rem; border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label for="status" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Estado</label>
                            <select id="status" name="status" class="form-control" style="padding: 0.75rem 1rem; border-radius: 10px;">
                                <option value="published" <?php echo $article['status'] === 'published' ? 'selected' : ''; ?>>Publicado</option>
                                <option value="draft" <?php echo $article['status'] === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                            </select>
                        </div>
                    </div>

                    <!-- Audience Target Selection -->
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="font-weight: 600; margin-bottom: 0.25rem; display: block;">Audiencia & Visibilidad</label>
                        <span style="font-size: 0.82rem; color: var(--text-muted); display: block; margin-bottom: 0.6rem;">¿Quiénes podrán consultar este procedimiento o video?</span>
                        
                        <div class="kb-audience-selector">
                            <label class="kb-audience-card <?php echo $article['audience'] === 'public' ? 'active' : ''; ?>" id="audCardPublic">
                                <input type="radio" name="audience" value="public" <?php echo $article['audience'] === 'public' ? 'checked' : ''; ?>>
                                <div style="font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 6px;">
                                    <i class="ph ph-broadcast" style="color: #0891b2;"></i> Público (Acceso Libre)
                                </div>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">Accesible para cualquier usuario con el enlace, sin iniciar sesión.</span>
                            </label>

                            <label class="kb-audience-card <?php echo $article['audience'] === 'all' ? 'active' : ''; ?>" id="audCardAll">
                                <input type="radio" name="audience" value="all" <?php echo $article['audience'] === 'all' ? 'checked' : ''; ?>>
                                <div style="font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 6px;">
                                    <i class="ph ph-globe" style="color: #10b981;"></i> General
                                </div>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">Visible para el equipo y también para clientes.</span>
                            </label>

                            <label class="kb-audience-card <?php echo $article['audience'] === 'internal' ? 'active' : ''; ?>" id="audCardInternal">
                                <input type="radio" name="audience" value="internal" <?php echo $article['audience'] === 'internal' ? 'checked' : ''; ?>>
                                <div style="font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 6px;">
                                    <i class="ph ph-lock-key" style="color: #ef4444;"></i> Solo Interno
                                </div>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">Procedimientos exclusivos para el equipo de la agencia.</span>
                            </label>

                            <label class="kb-audience-card <?php echo $article['audience'] === 'clients' ? 'active' : ''; ?>" id="audCardClients">
                                <input type="radio" name="audience" value="clients" <?php echo $article['audience'] === 'clients' ? 'checked' : ''; ?>>
                                <div style="font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 6px;">
                                    <i class="ph ph-user-check" style="color: #3b82f6;"></i> Solo Clientes
                                </div>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">Guías orientadas a clientes y su portal.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Resumen Corto -->
                    <div class="form-group">
                        <label for="summary" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Resumen Corto (Para la tarjeta)</label>
                        <textarea id="summary" name="summary" class="form-control" rows="3" 
                                  placeholder="Breve descripción de qué aprenderán en esta guía..." 
                                  style="padding: 0.75rem 1rem; border-radius: 10px; resize: vertical;"><?php echo htmlspecialchars($article['summary']); ?></textarea>
                    </div>
                </div>

                <!-- YouTube Video Integration Card -->
                <div class="kb-form-card" style="margin-bottom: 0;">
                    <div class="kb-form-section-title">
                        <i class="ph ph-youtube-logo" style="color: #ef4444;"></i> Video Tutorial de YouTube (Opcional)
                    </div>

                    <div class="form-group">
                        <label for="video_url" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Enlace de YouTube</label>
                        <input type="url" id="video_url" name="video_url" class="form-control" 
                               value="<?php echo htmlspecialchars($article['video_url']); ?>" 
                               placeholder="https://www.youtube.com/watch?v=... o https://youtu.be/..." 
                               style="padding: 0.8rem 1rem; border-radius: 10px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: block;">
                            Acepta videos públicos, no listados (unlisted), shorts o enlaces cortos.
                        </span>
                    </div>

                    <!-- Dynamic Live Video Preview -->
                    <div class="kb-video-preview-box" id="kbVideoPreviewBox">
                        <div class="kb-video-preview-inner">
                            <img id="kbPreviewThumb" src="" alt="Miniatura" class="kb-video-preview-thumb">
                            <div>
                                <div style="font-weight: 700; color: var(--color-title); margin-bottom: 4px;">
                                    <i class="ph-fill ph-check-circle" style="color: #10b981;"></i> Video detectado correctamente
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    ID de YouTube: <strong id="kbPreviewId" style="font-family: monospace; color: var(--primary-color);"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: 60% (Contenido y Procedimiento Detallado) -->
            <div class="kb-form-col-right">
                <div class="kb-form-card kb-form-card-editor">
                    <div class="kb-form-section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="ph ph-text-align-left"></i> Contenido y Procedimiento Detallado
                        </div>
                    </div>

                    <!-- Quick Blocks Bar -->
                    <div class="kb-quick-blocks-bar">
                        <span class="kb-quick-label"><i class="ph ph-sparkle"></i> Bloques:</span>
                        <div class="kb-quick-btns-group">
                            <button type="button" class="btn-quick-block" id="btnQuickUploadImage" title="Subir imagen optimizada y redimensionable">
                                <i class="ph ph-image"></i> Subir Imagen
                            </button>
                            <button type="button" class="btn-quick-block btn-quick-block-accent" id="btnQuickInsertGallery" title="Crear cuadrícula de fotos con visor interactivo">
                                <i class="ph ph-images"></i> Galería de Fotos
                            </button>
                            <button type="button" class="btn-quick-block" id="btnQuickInsertTip" title="Insertar bloque de consejo / tip">
                                <i class="ph ph-lightbulb"></i> Tip
                            </button>
                            <button type="button" class="btn-quick-block" id="btnQuickInsertAlert" title="Insertar bloque de advertencia">
                                <i class="ph ph-warning-circle"></i> Alerta
                            </button>
                        </div>
                    </div>

                    <!-- Hidden File Input for Single Image -->
                    <input type="file" id="kbSingleImageInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;">

                    <div class="kb-editor-wrapper" style="position: relative;">
                        <!-- Floating Toolbar for Selected Image -->
                        <div id="kbImageFloatingToolbar" class="kb-image-floating-toolbar" style="display: none;">
                            <div class="kb-img-toolbar-group">
                                <span class="kb-img-toolbar-tag">Tamaño:</span>
                                <button type="button" class="kb-img-btn" data-width="25%" title="25% ancho">25%</button>
                                <button type="button" class="kb-img-btn" data-width="50%" title="50% ancho">50%</button>
                                <button type="button" class="kb-img-btn" data-width="75%" title="75% ancho">75%</button>
                                <button type="button" class="kb-img-btn" data-width="100%" title="100% ancho">100%</button>
                            </div>
                            <div class="kb-img-toolbar-divider"></div>
                            <div class="kb-img-toolbar-group">
                                <span class="kb-img-toolbar-tag">Alinear:</span>
                                <button type="button" class="kb-img-btn" data-align="left" title="Flotar a la izquierda">
                                    <i class="ph ph-text-align-left"></i>
                                </button>
                                <button type="button" class="kb-img-btn" data-align="center" title="Centrado">
                                    <i class="ph ph-text-align-center"></i>
                                </button>
                                <button type="button" class="kb-img-btn" data-align="right" title="Flotar a la derecha">
                                    <i class="ph ph-text-align-right"></i>
                                </button>
                            </div>
                            <div class="kb-img-toolbar-divider"></div>
                            <div class="kb-img-toolbar-group">
                                <button type="button" class="kb-img-btn kb-img-btn-view" id="kbImgBtnPreview" title="Abrir en Visor Grande">
                                    <i class="ph ph-magnifying-glass-plus"></i> Ver Grande
                                </button>
                                <button type="button" class="kb-img-btn kb-img-btn-delete" id="kbImgBtnDelete" title="Eliminar Imagen">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </div>

                        <div id="quillEditor"><?php echo $article['content']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 0; border-top: 1px solid var(--border-color); margin-top: 0.5rem;">
            <div>
                <?php if ($is_edit): ?>
                <button type="button" class="btn btn-outline" id="kbDeleteArticleBtn" data-id="<?php echo $article['id']; ?>" style="color: #ef4444; border-color: rgba(239,68,68,0.3); border-radius: 10px;">
                    <i class="ph ph-trash"></i> Eliminar Artículo
                </button>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <a href="index.php?module=knowledge_base&action=index" class="btn btn-light" style="border-radius: 10px;">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.65rem 1.75rem;">
                    <i class="ph ph-floppy-disk"></i> <?php echo $is_edit ? 'Guardar Cambios' : 'Publicar Artículo'; ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Modal: Gestión de Categorías en Formulario -->
    <div id="modal-form-categories" class="modal-overlay" onclick="if (event.target === this) closeCatModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="modal-content" style="background: var(--bg-surface); max-width: 620px; width: 92%; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-folders" style="color: var(--primary-color);"></i> Gestión de Categorías
                </h3>
                <button type="button" class="btn-close-modal" id="btnCloseCatModalForm" onclick="closeCatModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 4px;">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
                
                <!-- Existing categories list -->
                <div style="font-weight: 700; font-size: 0.88rem; color: var(--color-title); margin-bottom: 0.6rem;">
                    Categorías Disponibles
                </div>
                <div id="modalCatList" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem; max-height: 180px; overflow-y: auto; padding-right: 4px;">
                    <!-- Rendered via JS -->
                </div>

                <!-- Form to Add or Edit Category -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--color-title); margin-bottom: 0.85rem;" id="modalCatFormTitle">
                        <i class="ph ph-plus-circle"></i> Nueva Categoría
                    </div>
                    <form id="modalCategoryForm">
                        <input type="hidden" name="id" id="modalCatId" value="0">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Nombre *</label>
                                <input type="text" name="name" id="modalCatName" class="form-control" required placeholder="Ej. Marketing Digital" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
                            </div>
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Color</label>
                                <input type="color" name="color" id="modalCatColor" value="#4f46e5" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid var(--border-color); padding: 2px; cursor: pointer;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 4px;">Icono</label>
                                <select name="icon" id="modalCatIcon" class="form-control" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
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
                                <input type="text" name="description" id="modalCatDesc" class="form-control" placeholder="Breve descripción del área" style="padding: 0.6rem 0.8rem; border-radius: 8px;">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                            <button type="button" class="btn btn-light" id="modalCatResetBtn" style="border-radius: 8px; font-size: 0.85rem;" onclick="resetModalCatForm()">Limpiar</button>
                            <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-size: 0.85rem; font-weight: 600;">Guardar Categoría</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal: Insertar Galería de Fotos con Visor -->
    <div id="modal-insert-gallery" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="modal-content" style="background: var(--bg-surface); max-width: 680px; width: 92%; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 40px rgba(0,0,0,0.3); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-images" style="color: var(--primary-color);"></i> Galería de Fotos con Visor Interactivo
                </h3>
                <button type="button" class="btn-close-modal" id="btnCloseGalleryModal" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 4px;">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto;">
                
                <!-- Dropzone for Multi-Image Upload -->
                <div class="kb-gallery-dropzone" id="kbGalleryDropzone">
                    <i class="ph ph-cloud-arrow-up" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 8px; display: block;"></i>
                    <div style="font-weight: 700; font-size: 1rem; color: var(--color-title); margin-bottom: 4px;">
                        Haz clic o arrastra fotos aquí
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                        Selecciona una o varias imágenes (JPG, PNG, WEBP o GIF)
                    </div>
                    <button type="button" class="btn btn-outline" style="border-radius: 10px; font-size: 0.88rem; pointer-events: none;">
                        <i class="ph ph-folder-open"></i> Explorar Archivos
                    </button>
                    <input type="file" id="kbGalleryFileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;">
                </div>

                <!-- Preview Grid of Selected Photos -->
                <div id="kbGalleryPreviewContainer" style="display: none; margin-top: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <span style="font-size: 0.88rem; font-weight: 700; color: var(--color-title);" id="kbGallerySelectedCount">0 fotos seleccionadas</span>
                        <button type="button" class="btn btn-link" style="font-size: 0.8rem; color: #ef4444; padding: 0; text-decoration: none; cursor: pointer; border: none; background: none;" id="kbGalleryClearAllBtn">
                            <i class="ph ph-trash"></i> Quitar todas
                        </button>
                    </div>
                    <div id="kbGalleryPreviewsList" class="kb-gallery-picker-previews"></div>
                </div>

                <!-- Gallery Layout and Title Options -->
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                    <div style="margin-bottom: 1.25rem;">
                        <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 6px;">
                            Distribución en Columnas
                        </label>
                        <div class="kb-columns-selector">
                            <label class="kb-col-option">
                                <input type="radio" name="gallery_cols" value="2">
                                <div class="kb-col-box">
                                    <span class="kb-col-icon">2 Columnas</span>
                                    <span class="kb-col-desc">Fotos grandes (Comparativas)</span>
                                </div>
                            </label>
                            <label class="kb-col-option">
                                <input type="radio" name="gallery_cols" value="3" checked>
                                <div class="kb-col-box">
                                    <span class="kb-col-icon">3 Columnas</span>
                                    <span class="kb-col-desc">Recomendado (Balanceado)</span>
                                </div>
                            </label>
                            <label class="kb-col-option">
                                <input type="radio" name="gallery_cols" value="4">
                                <div class="kb-col-box">
                                    <span class="kb-col-icon">4 Columnas</span>
                                    <span class="kb-col-desc">Mosaico compacto</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="kbGalleryTitleInput" style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 6px;">
                            Título de la Galería (Opcional)
                        </label>
                        <input type="text" id="kbGalleryTitleInput" class="form-control" placeholder="Ej: Capturas del flujo de diseño, Antes y Después..." style="padding: 0.65rem 0.9rem; border-radius: 10px;">
                    </div>
                </div>

                <!-- Upload Progress Indicator -->
                <div id="kbGalleryUploadProgress" style="display: none; margin-top: 1rem; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem 1rem; text-align: center;">
                    <div class="loader" style="margin: 0 auto 8px auto; width: 28px; height: 28px;"></div>
                    <div id="kbGalleryProgressText" style="font-size: 0.85rem; font-weight: 600; color: var(--color-title);">Subiendo imágenes...</div>
                </div>

            </div>

            <div class="modal-footer" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-light" id="btnCancelGalleryModal" style="border-radius: 10px;">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnConfirmInsertGallery" style="border-radius: 10px; font-weight: 600; padding: 0.65rem 1.5rem;">
                    <i class="ph ph-check"></i> Insertar Galería en Artículo
                </button>
            </div>

        </div>
    </div>

</div>

<!-- Quill.js Script -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Register BlockEmbed for Galleries so Quill natively preserves galleries as unified blocks
    const BlockEmbed = Quill.import('blots/block/embed');
    class KbGalleryBlot extends BlockEmbed {
        static create(value) {
            let node = super.create();
            node.setAttribute('class', 'kb-gallery-block');
            node.setAttribute('contenteditable', 'false');
            node.innerHTML = typeof value === 'string' ? value : (value.html || '');
            return node;
        }
        static value(node) {
            return { html: node.innerHTML };
        }
    }
    KbGalleryBlot.blotName = 'kbGallery';
    KbGalleryBlot.tagName = 'div';
    KbGalleryBlot.className = 'kb-gallery-block';
    Quill.register(KbGalleryBlot);

    // Extend Image Blot so Quill preserves width and inline style on images
    const BaseImage = Quill.import('formats/image');
    class CustomImage extends BaseImage {
        static formats(domNode) {
            return {
                src: domNode.getAttribute('src'),
                style: domNode.getAttribute('style'),
                class: domNode.getAttribute('class'),
                width: domNode.style.width || domNode.getAttribute('width')
            };
        }
        format(name, value) {
            if (name === 'style') {
                if (value) this.domNode.setAttribute('style', value);
                else this.domNode.removeAttribute('style');
            } else if (name === 'width') {
                if (value) {
                    this.domNode.setAttribute('width', value);
                    this.domNode.style.width = value;
                } else {
                    this.domNode.removeAttribute('width');
                    this.domNode.style.width = '';
                }
            } else {
                super.format(name, value);
            }
        }
    }
    Quill.register(CustomImage, true);

    // 1. Initialize Quill Editor
    const quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: 'Escribe aquí los pasos detallados, checklists, capturas e instrucciones...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    // 1.1 Override toolbar image icon to use clean server uploader instead of Base64
    quill.getModule('toolbar').addHandler('image', () => {
        const input = document.getElementById('kbSingleImageInput');
        if (input) input.click();
    });

    // 1.2 Single Image Upload logic
    const singleImageInput = document.getElementById('kbSingleImageInput');
    const btnQuickUpload = document.getElementById('btnQuickUploadImage');

    if (btnQuickUpload && singleImageInput) {
        btnQuickUpload.addEventListener('click', () => singleImageInput.click());
    }

    function uploadAndInsertSingleImage(file) {
        if (!file) return;
        const fd = new FormData();
        fd.append('action_type', 'upload_image');
        fd.append('image', file);

        const originalBtnHtml = btnQuickUpload ? btnQuickUpload.innerHTML : '';
        if (btnQuickUpload) {
            btnQuickUpload.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Subiendo...';
            btnQuickUpload.disabled = true;
        }

        fetch('index.php?module=knowledge_base&action=ajax', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.url) {
                const range = quill.getSelection(true) || { index: quill.getLength() };
                quill.insertEmbed(range.index, 'image', data.url);
                quill.setSelection(range.index + 1);

                setTimeout(() => {
                    const insertedImgs = quill.root.querySelectorAll(`img[src="${data.url}"]`);
                    if (insertedImgs.length > 0) {
                        const newImg = insertedImgs[insertedImgs.length - 1];
                        newImg.style.maxWidth = '100%';
                        newImg.style.borderRadius = '12px';
                        newImg.style.display = 'block';
                        newImg.style.margin = '1.5rem auto';
                        selectImage(newImg);
                    }
                }, 100);
            } else {
                alert(data.error || 'Error al subir la imagen');
            }
        })
        .catch(err => alert('Error de conexión al subir la imagen'))
        .finally(() => {
            if (singleImageInput) singleImageInput.value = '';
            if (btnQuickUpload) {
                btnQuickUpload.innerHTML = originalBtnHtml;
                btnQuickUpload.disabled = false;
            }
        });
    }

    if (singleImageInput) {
        singleImageInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                uploadAndInsertSingleImage(e.target.files[0]);
            }
        });
    }

    // Intercept image pastes from clipboard
    quill.root.addEventListener('paste', (e) => {
        const clipboardData = e.clipboardData || window.clipboardData;
        if (clipboardData && clipboardData.files && clipboardData.files.length > 0) {
            const file = clipboardData.files[0];
            if (file.type.startsWith('image/')) {
                e.preventDefault();
                uploadAndInsertSingleImage(file);
            }
        }
    });

    // 1.3 Floating Image Resizer & Alignment Toolbar
    let currentSelectedImg = null;
    const floatToolbar = document.getElementById('kbImageFloatingToolbar');
    const editorWrapper = document.querySelector('.kb-editor-wrapper');

    function selectImage(img) {
        if (currentSelectedImg && currentSelectedImg !== img) {
            currentSelectedImg.classList.remove('kb-selected-img');
        }
        currentSelectedImg = img;
        img.classList.add('kb-selected-img');

        positionToolbar(img);
        floatToolbar.style.display = 'flex';
        updateToolbarActiveState(img);
    }

    function deselectImage() {
        if (currentSelectedImg) {
            currentSelectedImg.classList.remove('kb-selected-img');
            currentSelectedImg = null;
        }
        if (floatToolbar) floatToolbar.style.display = 'none';
    }

    function positionToolbar(img) {
        if (!img || !floatToolbar || !editorWrapper) return;
        const imgRect = img.getBoundingClientRect();
        const wrapperRect = editorWrapper.getBoundingClientRect();

        let top = imgRect.top - wrapperRect.top - 48;
        if (top < 10) {
            top = imgRect.bottom - wrapperRect.top + 10;
        }

        let left = (imgRect.left - wrapperRect.left) + (imgRect.width / 2) - 180;
        if (left < 10) left = 10;
        if (left + 360 > wrapperRect.width) left = Math.max(10, wrapperRect.width - 370);

        floatToolbar.style.top = top + 'px';
        floatToolbar.style.left = left + 'px';
    }

    function updateToolbarActiveState(img) {
        if (!img || !floatToolbar) return;
        const w = img.style.width || '100%';
        floatToolbar.querySelectorAll('[data-width]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.width === w);
        });

        const isCenter = img.style.margin && img.style.margin.includes('auto');
        const isLeft = img.style.float === 'left';
        const isRight = img.style.float === 'right';

        floatToolbar.querySelectorAll('[data-align]').forEach(btn => {
            const a = btn.dataset.align;
            if (a === 'center') btn.classList.toggle('active', isCenter || (!isLeft && !isRight));
            else if (a === 'left') btn.classList.toggle('active', isLeft);
            else if (a === 'right') btn.classList.toggle('active', isRight);
        });
    }

    quill.root.addEventListener('click', (e) => {
        if (e.target.tagName === 'IMG' && !e.target.closest('.kb-gallery-item')) {
            selectImage(e.target);
        } else if (!e.target.closest('.kb-image-floating-toolbar')) {
            deselectImage();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.kb-editor-wrapper') && !e.target.closest('.kb-image-floating-toolbar')) {
            deselectImage();
        }
    });

    if (floatToolbar) {
        floatToolbar.querySelectorAll('[data-width]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!currentSelectedImg) return;
                currentSelectedImg.style.width = btn.dataset.width;
                updateToolbarActiveState(currentSelectedImg);
                positionToolbar(currentSelectedImg);
            });
        });

        floatToolbar.querySelectorAll('[data-align]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!currentSelectedImg) return;
                const align = btn.dataset.align;
                if (align === 'center') {
                    currentSelectedImg.style.display = 'block';
                    currentSelectedImg.style.float = 'none';
                    currentSelectedImg.style.margin = '1.5rem auto';
                } else if (align === 'left') {
                    currentSelectedImg.style.display = 'inline-block';
                    currentSelectedImg.style.float = 'left';
                    currentSelectedImg.style.margin = '0.5rem 1.5rem 1rem 0';
                } else if (align === 'right') {
                    currentSelectedImg.style.display = 'inline-block';
                    currentSelectedImg.style.float = 'right';
                    currentSelectedImg.style.margin = '0.5rem 0 1rem 1.5rem';
                }
                updateToolbarActiveState(currentSelectedImg);
                positionToolbar(currentSelectedImg);
            });
        });

        const btnPreview = document.getElementById('kbImgBtnPreview');
        if (btnPreview) {
            btnPreview.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!currentSelectedImg) return;
                if (window.Fancybox) {
                    Fancybox.show([{
                        src: currentSelectedImg.src,
                        type: 'image',
                        caption: currentSelectedImg.alt || 'Vista previa'
                    }]);
                } else {
                    window.open(currentSelectedImg.src, '_blank');
                }
            });
        }

        const btnDelImg = document.getElementById('kbImgBtnDelete');
        if (btnDelImg) {
            btnDelImg.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!currentSelectedImg) return;
                const imgToRemove = currentSelectedImg;
                deselectImage();
                imgToRemove.remove();
                quill.update();
            });
        }
    }

    window.addEventListener('resize', () => { if (currentSelectedImg) positionToolbar(currentSelectedImg); });
    window.addEventListener('scroll', () => { if (currentSelectedImg) positionToolbar(currentSelectedImg); }, true);

    // 1.4 Quick Blocks: Tip and Warning Callouts
    const btnQuickTip = document.getElementById('btnQuickInsertTip');
    const btnQuickAlert = document.getElementById('btnQuickInsertAlert');

    if (btnQuickTip) {
        btnQuickTip.addEventListener('click', () => {
            const range = quill.getSelection(true) || { index: quill.getLength() };
            const html = '<blockquote class="kb-callout-tip"><strong>💡 Tip / Consejo:</strong> Escribe aquí tu recomendación o sugerencia clave para este paso...</blockquote><p><br></p>';
            quill.clipboard.dangerouslyPasteHTML(range.index, html);
        });
    }

    if (btnQuickAlert) {
        btnQuickAlert.addEventListener('click', () => {
            const range = quill.getSelection(true) || { index: quill.getLength() };
            const html = '<blockquote class="kb-callout-warning"><strong>⚠️ Advertencia / Importante:</strong> Ten en cuenta este detalle antes de ejecutar este procedimiento...</blockquote><p><br></p>';
            quill.clipboard.dangerouslyPasteHTML(range.index, html);
        });
    }

    // 1.5 Gallery Creator Modal & Batch Uploader
    const modalGallery = document.getElementById('modal-insert-gallery');
    const btnQuickGallery = document.getElementById('btnQuickInsertGallery');
    const btnCloseGallery = document.getElementById('btnCloseGalleryModal');
    const btnCancelGallery = document.getElementById('btnCancelGalleryModal');
    const galleryDropzone = document.getElementById('kbGalleryDropzone');
    const galleryFileInput = document.getElementById('kbGalleryFileInput');
    const galleryPreviewsList = document.getElementById('kbGalleryPreviewsList');
    const galleryPreviewContainer = document.getElementById('kbGalleryPreviewContainer');
    const gallerySelectedCount = document.getElementById('kbGallerySelectedCount');
    const galleryClearAllBtn = document.getElementById('kbGalleryClearAllBtn');
    const btnConfirmGallery = document.getElementById('btnConfirmInsertGallery');
    const galleryUploadProgress = document.getElementById('kbGalleryUploadProgress');
    const galleryProgressText = document.getElementById('kbGalleryProgressText');
    const galleryTitleInput = document.getElementById('kbGalleryTitleInput');

    let selectedGalleryFiles = [];

    function openGalleryModal() {
        if (modalGallery) modalGallery.style.display = 'flex';
        selectedGalleryFiles = [];
        renderGalleryPreviews();
        if (galleryTitleInput) galleryTitleInput.value = '';
        if (galleryUploadProgress) galleryUploadProgress.style.display = 'none';
    }

    function closeGalleryModal() {
        if (modalGallery) modalGallery.style.display = 'none';
        selectedGalleryFiles = [];
        renderGalleryPreviews();
    }

    if (btnQuickGallery) btnQuickGallery.addEventListener('click', openGalleryModal);
    if (btnCloseGallery) btnCloseGallery.addEventListener('click', closeGalleryModal);
    if (btnCancelGallery) btnCancelGallery.addEventListener('click', closeGalleryModal);
    if (modalGallery) {
        modalGallery.addEventListener('click', (e) => {
            if (e.target === modalGallery) closeGalleryModal();
        });
    }

    if (galleryDropzone && galleryFileInput) {
        galleryDropzone.addEventListener('click', () => galleryFileInput.click());
        galleryDropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            galleryDropzone.classList.add('dragover');
        });
        galleryDropzone.addEventListener('dragleave', () => galleryDropzone.classList.remove('dragover'));
        galleryDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            galleryDropzone.classList.remove('dragover');
            if (e.dataTransfer.files) {
                handleNewGalleryFiles(e.dataTransfer.files);
            }
        });

        galleryFileInput.addEventListener('change', (e) => {
            if (e.target.files) {
                handleNewGalleryFiles(e.target.files);
            }
        });
    }

    function handleNewGalleryFiles(files) {
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            if (f.type.startsWith('image/')) {
                selectedGalleryFiles.push(f);
            }
        }
        galleryFileInput.value = '';
        renderGalleryPreviews();
    }

    function renderGalleryPreviews() {
        if (!galleryPreviewContainer || !galleryPreviewsList) return;
        if (selectedGalleryFiles.length === 0) {
            galleryPreviewContainer.style.display = 'none';
            galleryPreviewsList.innerHTML = '';
            return;
        }

        galleryPreviewContainer.style.display = 'block';
        gallerySelectedCount.textContent = `${selectedGalleryFiles.length} foto${selectedGalleryFiles.length > 1 ? 's' : ''} seleccionada${selectedGalleryFiles.length > 1 ? 's' : ''}`;
        galleryPreviewsList.innerHTML = '';

        selectedGalleryFiles.forEach((file, index) => {
            const thumbBox = document.createElement('div');
            thumbBox.className = 'kb-picker-thumb';

            const img = document.createElement('img');
            const reader = new FileReader();
            reader.onload = (e) => { img.src = e.target.result; };
            reader.readAsDataURL(file);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'kb-picker-thumb-remove';
            delBtn.innerHTML = '<i class="ph ph-x"></i>';
            delBtn.title = 'Quitar foto';
            delBtn.onclick = (e) => {
                e.stopPropagation();
                selectedGalleryFiles.splice(index, 1);
                renderGalleryPreviews();
            };

            thumbBox.appendChild(img);
            thumbBox.appendChild(delBtn);
            galleryPreviewsList.appendChild(thumbBox);
        });
    }

    if (galleryClearAllBtn) {
        galleryClearAllBtn.addEventListener('click', () => {
            selectedGalleryFiles = [];
            renderGalleryPreviews();
        });
    }

    if (btnConfirmGallery) {
        btnConfirmGallery.addEventListener('click', () => {
            if (selectedGalleryFiles.length === 0) {
                alert('Por favor selecciona al menos una imagen para la galería.');
                return;
            }

            const colsRadio = document.querySelector('input[name="gallery_cols"]:checked');
            const cols = colsRadio ? colsRadio.value : '3';
            const title = galleryTitleInput ? galleryTitleInput.value.trim() : '';

            btnConfirmGallery.disabled = true;
            galleryUploadProgress.style.display = 'block';
            galleryProgressText.textContent = `Subiendo ${selectedGalleryFiles.length} imagen${selectedGalleryFiles.length > 1 ? 'es' : ''}...`;

            const fd = new FormData();
            fd.append('action_type', 'upload_image');
            selectedGalleryFiles.forEach(file => {
                fd.append('images[]', file);
            });

            fetch('index.php?module=knowledge_base&action=ajax', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.urls && data.urls.length > 0) {
                    const galId = 'kb-gal-' + Date.now();
                    const escapeHtml = (str) => String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);

                    let itemsHtml = data.urls.map((url, idx) => {
                        const caption = title ? `${escapeHtml(title)} (${idx + 1}/${data.urls.length})` : `Foto ${idx + 1} de ${data.urls.length}`;
                        return `
                            <a href="${url}" data-fancybox="${galId}" data-caption="${caption}" class="kb-gallery-item">
                                <img src="${url}" alt="${escapeHtml(title || 'Galería')}">
                                <span class="kb-gallery-zoom-badge"><i class="ph ph-magnifying-glass-plus"></i></span>
                            </a>
                        `;
                    }).join('');

                    let blockHtml = `
                        <div class="kb-gallery-block-header">
                            <div class="kb-gallery-title">
                                <i class="ph ph-images" style="color: var(--primary-color);"></i>
                                <span>${title ? escapeHtml(title) : 'Galería de Imágenes (' + data.urls.length + ' fotos)'}</span>
                            </div>
                            <button type="button" class="kb-gallery-del-btn" onclick="this.closest('.kb-gallery-block').remove()" title="Eliminar toda la galería">
                                <i class="ph ph-trash"></i> Quitar Galería
                            </button>
                        </div>
                        <div class="kb-gallery-grid cols-${cols}">
                            ${itemsHtml}
                        </div>
                    `;

                    const range = quill.getSelection(true) || { index: quill.getLength() };
                    quill.insertEmbed(range.index, 'kbGallery', { html: blockHtml });
                    quill.insertText(range.index + 1, '\n');

                    closeGalleryModal();
                } else {
                    alert(data.error || 'Error al subir las imágenes de la galería');
                }
            })
            .catch(err => alert('Error de conexión al subir la galería'))
            .finally(() => {
                btnConfirmGallery.disabled = false;
                galleryUploadProgress.style.display = 'none';
            });
        });
    }

    // Bind lightbox on gallery items inside editor so author can preview immediately
    quill.root.addEventListener('click', (e) => {
        const galleryItem = e.target.closest('.kb-gallery-item');
        if (galleryItem) {
            e.preventDefault();
            const galleryBlock = galleryItem.closest('.kb-gallery-block');
            if (galleryBlock && window.Fancybox) {
                const items = Array.from(galleryBlock.querySelectorAll('.kb-gallery-item')).map(a => ({
                    src: a.getAttribute('href'),
                    caption: a.getAttribute('data-caption') || '',
                    type: 'image'
                }));
                const startIndex = Array.from(galleryBlock.querySelectorAll('.kb-gallery-item')).indexOf(galleryItem);
                Fancybox.show(items, { startIndex: Math.max(0, startIndex) });
            }
        }
    });

    // 2. Audience Selector Cards
    const audCards = document.querySelectorAll('.kb-audience-card');
    audCards.forEach(card => {
        card.addEventListener('click', () => {
            audCards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            card.querySelector('input[type="radio"]').checked = true;
        });
    });

    // 3. YouTube URL Live Detection
    const videoInput = document.getElementById('video_url');
    const previewBox = document.getElementById('kbVideoPreviewBox');
    const previewThumb = document.getElementById('kbPreviewThumb');
    const previewId = document.getElementById('kbPreviewId');

    function extractYouTubeId(url) {
        if (!url) return null;
        url = url.trim();
        if (/^[a-zA-Z0-9_-]{11}$/.test(url)) return url;
        const regExp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/i;
        const match = url.match(regExp);
        return match ? match[1] : null;
    }

    function checkVideoPreview() {
        const url = videoInput.value.trim();
        const id = extractYouTubeId(url);
        if (id) {
            previewThumb.src = `https://img.youtube.com/vi/${id}/hqdefault.jpg`;
            previewId.textContent = id;
            previewBox.style.display = 'block';
        } else {
            previewBox.style.display = 'none';
        }
    }

    videoInput.addEventListener('input', checkVideoPreview);
    checkVideoPreview(); // Run initially if editing

    // 4. Form Submission: sync Quill content
    const form = document.getElementById('kbArticleForm');
    const contentInput = document.getElementById('quillContentInput');

    form.addEventListener('submit', (e) => {
        contentInput.value = quill.root.innerHTML;
    });

    // 5. Delete Article action
    const deleteBtn = document.getElementById('kbDeleteArticleBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            if (confirm('¿Estás seguro de que deseas eliminar este artículo permanentemente?')) {
                const artId = deleteBtn.dataset.id;
                const fd = new FormData();
                fd.append('action_type', 'delete_article');
                fd.append('article_id', artId);

                fetch('index.php?module=knowledge_base&action=ajax', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php?module=knowledge_base&action=index';
                    } else {
                        alert(data.error || 'Error al eliminar');
                    }
                })
                .catch(err => alert('Error de conexión'));
            }
        });
    }

    // 6. Category Management Modal & Sync in Form
    let currentCategories = <?php echo json_encode($categories); ?>;

    window.openCatModal = function(focusName = false) {
        const modal = document.getElementById('modal-form-categories');
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        if (typeof window.renderModalCatList === 'function') {
            window.renderModalCatList();
        }
        if (focusName) {
            setTimeout(() => {
                document.getElementById('modalCatName')?.focus();
            }, 100);
        }
    };

    window.closeCatModal = function() {
        const modal = document.getElementById('modal-form-categories');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }
        if (typeof window.resetModalCatForm === 'function') {
            window.resetModalCatForm();
        }
    };

    const btnManage = document.getElementById('btnManageCategories');
    const btnQuickAdd = document.getElementById('btnQuickAddCategory');
    const btnClose = document.getElementById('btnCloseCatModalForm');
    const modalFormCat = document.getElementById('modal-form-categories');
    const catForm = document.getElementById('modalCategoryForm');

    if (btnManage) btnManage.addEventListener('click', () => window.openCatModal());
    if (btnQuickAdd) btnQuickAdd.addEventListener('click', () => window.openCatModal(true));
    if (btnClose) btnClose.addEventListener('click', () => window.closeCatModal());
    if (modalFormCat) {
        modalFormCat.addEventListener('click', (e) => {
            if (e.target === modalFormCat) window.closeCatModal();
        });
    }

    window.renderModalCatList = function() {
        const container = document.getElementById('modalCatList');
        if (!container) return;
        if (!currentCategories || currentCategories.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem;">No hay categorías creadas aún.</div>';
            return;
        }
        container.innerHTML = currentCategories.map(cat => `
            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-color); padding: 0.5rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 24px; height: 24px; border-radius: 6px; background: ${cat.color || '#4f46e5'}; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                        <i class="ph ${cat.icon || 'ph-book-open'}"></i>
                    </span>
                    <span style="font-weight: 600; color: var(--color-title); font-size: 0.85rem;">${cat.name}</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">(${cat.total_articles || 0} artículos)</span>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="btn-kb-quick" style="width: 26px; height: 26px;" title="Editar categoría" onclick="editModalCat(${cat.id})">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    ${parseInt(cat.total_articles || 0) === 0 ? `
                        <button type="button" class="btn-kb-quick btn-kb-del" style="width: 26px; height: 26px;" title="Eliminar categoría" onclick="deleteModalCat(${cat.id})">
                            <i class="ph ph-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    };

    window.syncSelectOptions = function(selectedId) {
        const select = document.getElementById('category_id');
        if (!select) return;
        const currentVal = selectedId || select.value;
        select.innerHTML = '<option value="">-- Seleccionar Categoría --</option>' + 
            currentCategories.map(c => `<option value="${c.id}" ${c.id == currentVal ? 'selected' : ''}>${c.name}</option>`).join('');
    };

    window.resetModalCatForm = function() {
        const catId = document.getElementById('modalCatId');
        const catName = document.getElementById('modalCatName');
        const catColor = document.getElementById('modalCatColor');
        const catIcon = document.getElementById('modalCatIcon');
        const catDesc = document.getElementById('modalCatDesc');
        const formTitle = document.getElementById('modalCatFormTitle');

        if (catId) catId.value = '0';
        if (catName) catName.value = '';
        if (catColor) catColor.value = '#4f46e5';
        if (catIcon) catIcon.value = 'ph-paint-brush-broad';
        if (catDesc) catDesc.value = '';
        if (formTitle) formTitle.innerHTML = '<i class="ph ph-plus-circle"></i> Nueva Categoría';
    };

    window.editModalCat = function(id) {
        const cat = currentCategories.find(c => c.id == id);
        if (!cat) return;
        document.getElementById('modalCatId').value = cat.id;
        document.getElementById('modalCatName').value = cat.name;
        document.getElementById('modalCatColor').value = cat.color || '#4f46e5';
        document.getElementById('modalCatIcon').value = cat.icon || 'ph-book-open';
        document.getElementById('modalCatDesc').value = cat.description || '';
        document.getElementById('modalCatFormTitle').innerHTML = '<i class="ph ph-pencil-simple"></i> Editar Categoría: ' + cat.name;
        document.getElementById('modalCatName').focus();
    };

    window.deleteModalCat = function(id) {
        const cat = currentCategories.find(c => c.id == id);
        const catName = cat ? cat.name : 'esta categoría';
        if (confirm(`¿Estás seguro de eliminar la categoría "${catName}"?`)) {
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
                    currentCategories = data.categories || [];
                    window.renderModalCatList();
                    window.syncSelectOptions();
                } else {
                    alert(data.error || 'Error al eliminar');
                }
            })
            .catch(err => alert('Error de conexión'));
        }
    };

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
                    currentCategories = data.categories || [];
                    window.renderModalCatList();
                    window.syncSelectOptions(data.cat_id);
                    window.resetModalCatForm();
                    window.closeCatModal();
                } else {
                    alert(data.error || 'Error al guardar categoría');
                }
            })
            .catch(err => alert('Error de conexión'));
        });
    }

    window.renderModalCatList();
});
</script>

<?php require_once 'includes/footer.php'; ?>
