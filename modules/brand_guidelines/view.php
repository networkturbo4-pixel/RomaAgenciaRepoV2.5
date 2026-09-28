<?php
// modules/brand_guidelines/view.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'modules/brand_guidelines/helpers.php';

$database = new Database();
$db = $database->getConnection();

$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if (!empty($slug)) {
    $stmt = $db->prepare("
        SELECT bg.*, c.name as client_name 
        FROM brand_guidelines bg
        LEFT JOIN clients c ON bg.client_id = c.id
        WHERE bg.slug = ?
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($id > 0) {
    $stmt = $db->prepare("
        SELECT bg.*, c.name as client_name 
        FROM brand_guidelines bg
        LEFT JOIN clients c ON bg.client_id = c.id
        WHERE bg.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $bg = null;
}

// 404 Not Found
if (!$bg) {
    $is_public = true;
    $is_popup = true;
    require_once 'includes/header.php';
    echo "
    <div style='min-height: 80vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem;'>
        <div style='width: 72px; height: 72px; border-radius: 20px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 1.5rem;'>
            <i class='ph-bold ph-warning-circle'></i>
        </div>
        <h1 style='font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;'>Manual de Marca no encontrado</h1>
        <p style='color: var(--text-muted); max-width: 460px; margin-bottom: 1.5rem;'>El enlace que buscas no existe o ha sido modificado.</p>
        <a href='index.php?module=brand_guidelines&action=index' class='btn btn-primary' style='border-radius: 12px; font-weight: 700; padding: 0.65rem 1.25rem;'>
            Volver al inicio
        </a>
    </div>";
    require_once 'includes/footer.php';
    exit();
}

$guidelineId = (int)$bg['id'];
$isLoggedIn = isset($_SESSION['user_id']);
$isUnlocked = !empty($_SESSION['bg_unlocked_' . $guidelineId]);

// Check Privacy: if private and neither logged-in nor unlocked
if ($bg['is_public'] == 0 && !$isLoggedIn && !$isUnlocked) {
    $is_public = true;
    $is_popup = true;
    $page_title = "Acceso Protegido - " . htmlspecialchars($bg['brand_name']);
    require_once 'includes/header.php';
    ?>
    <div style="min-height: 85vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;">
        <div style="background: var(--bg-surface, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 24px; padding: 2.5rem 2rem; max-width: 440px; width: 100%; text-align: center; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);">
            <div style="width: 68px; height: 68px; border-radius: 20px; background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 1.5rem;">
                <i class="ph-bold ph-lock-key"></i>
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; margin: 0 0 0.35rem; color: var(--text-main);"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 1.75rem;">Este manual de marca es privado. Ingresa el PIN o contraseña de acceso para continuar.</p>
            
            <form id="unlockForm" onsubmit="handleUnlock(event)">
                <div style="margin-bottom: 1.25rem;">
                    <input type="password" id="unlockPassword" required placeholder="PIN o Contraseña" 
                           style="width: 100%; box-sizing: border-box; background: var(--bg-body, #f8fafc); border: 1px solid var(--border-color, #cbd5e1); border-radius: 12px; padding: 0.8rem 1rem; font-size: 1rem; text-align: center; letter-spacing: 2px; outline: none; transition: all 0.2s;">
                </div>
                <div id="unlockError" style="display:none; color: #ef4444; font-size: 0.82rem; margin-bottom: 1rem; font-weight: 600;"></div>
                <button type="submit" id="unlockBtn" class="btn" style="width: 100%; background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%); color: white; border: none; border-radius: 12px; padding: 0.8rem; font-weight: 700; font-size: 0.95rem; cursor: pointer; box-shadow: 0 6px 16px -2px rgba(236, 72, 153, 0.45);">
                    <i class="ph-bold ph-key"></i> Desbloquear Manual
                </button>
            </form>
        </div>
    </div>
    <script>
    function handleUnlock(e) {
        e.preventDefault();
        const btn = document.getElementById('unlockBtn');
        const err = document.getElementById('unlockError');
        const pass = document.getElementById('unlockPassword').value;
        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="animation: spin 1s infinite linear;"></i> Verificando...';
        err.style.display = 'none';

        fetch('modules/brand_guidelines/ajax.php?action=verify_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ guideline_id: '<?php echo $guidelineId; ?>', password: pass })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-key"></i> Desbloquear Manual';
            if (data.success) {
                window.location.reload();
            } else {
                err.textContent = data.message || 'Contraseña incorrecta';
                err.style.display = 'block';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-key"></i> Desbloquear Manual';
            err.textContent = 'Error de conexión';
            err.style.display = 'block';
        });
    }
    </script>
    <?php
    require_once 'includes/footer.php';
    exit();
}

// Increment View Counter (throttled by session)
if (empty($_SESSION['bg_viewed_' . $guidelineId])) {
    $_SESSION['bg_viewed_' . $guidelineId] = true;
    $db->prepare("UPDATE brand_guidelines SET views_count = views_count + 1 WHERE id = ?")->execute([$guidelineId]);
}

// Check if public/standalone presentation mode is requested or if user is guest
$isStandalone = !$isLoggedIn || (isset($_GET['view']) && $_GET['view'] === 'public');

if ($isStandalone) {
    $is_public = true;
    $is_popup = true;
}

$page_title = htmlspecialchars($bg['brand_name']) . " - Brand Guidelines";

// Decode fields
$variations = !empty($bg['logo_variations_json']) ? json_decode($bg['logo_variations_json'], true) : [];
if (!is_array($variations)) $variations = [];

$icons = !empty($bg['icons_json']) ? json_decode($bg['icons_json'], true) : [];
if (!is_array($icons)) $icons = [];

$colors = !empty($bg['colors_json']) ? json_decode($bg['colors_json'], true) : [];
if (!is_array($colors)) $colors = [];

$fonts = !empty($bg['fonts_json']) ? json_decode($bg['fonts_json'], true) : [];
if (!is_array($fonts)) $fonts = [];

$incorrectUses = !empty($bg['incorrect_uses_json']) ? json_decode($bg['incorrect_uses_json'], true) : [];
if (!is_array($incorrectUses)) $incorrectUses = [];

$applications = !empty($bg['applications_json']) ? json_decode($bg['applications_json'], true) : [];
if (!is_array($applications)) $applications = [];

$primaryHex = !empty($colors[0]['hex']) ? $colors[0]['hex'] : '#4F46E5';
$secondaryHex = !empty($colors[1]['hex']) ? $colors[1]['hex'] : '#EC4899';

$shortUrl = bg_get_short_url($bg['slug']);
$paramUrl = bg_get_param_url($bg['slug']);

require_once 'includes/header.php';
?>

<!-- Dynamic Google Fonts for Brand Fonts -->
<?php foreach ($fonts as $f): 
    if (!empty($f['name'])):
        $fontQuery = urlencode(trim($f['name'])) . ':wght@300;400;500;600;700;800';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?php echo $fontQuery; ?>&display=swap">
<?php endif; endforeach; ?>

<style>
/* ==========================================================================
   BRAND GUIDELINES PRESENTATION SYSTEM (PREMIUM DESIGN)
   ========================================================================== */
:root {
    --bg-primary-brand: <?php echo $primaryHex; ?>;
    --bg-accent-brand: <?php echo $secondaryHex; ?>;
}

.bgv-wrapper {
    background: var(--bg-body, #0a0b0e);
    min-height: 100vh;
    color: var(--text-main, #0f172a);
    font-family: var(--font-family, 'Inter', sans-serif);
    padding-bottom: 5rem;
}

[data-theme="dark"] .bgv-wrapper {
    background: #090a0f;
    color: #f8fafc;
}

/* Internal CRM Toolbar */
.bgv-crm-bar {
    background: var(--bg-surface, #ffffff);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 0.75rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

[data-theme="dark"] .bgv-crm-bar {
    background: #12141c;
    border-color: rgba(255, 255, 255, 0.08);
}

/* Hero Stage */
.bgv-hero-stage {
    position: relative;
    padding: 4.5rem 2rem 3.5rem;
    text-align: center;
    background: radial-gradient(circle at 50% 20%, color-mix(in srgb, var(--bg-primary-brand) 18%, transparent) 0%, transparent 70%);
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    overflow: hidden;
}

.bgv-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.85rem;
    border-radius: 9999px;
    background: color-mix(in srgb, var(--bg-primary-brand) 15%, transparent);
    color: var(--bg-primary-brand);
    border: 1px solid color-mix(in srgb, var(--bg-primary-brand) 30%, transparent);
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.75px;
    margin-bottom: 1rem;
}

.bgv-hero-title {
    font-size: clamp(2.2rem, 5vw, 3.75rem);
    font-weight: 900;
    letter-spacing: -1.5px;
    margin: 0 0 0.75rem;
    line-height: 1.1;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bgv-hero-title {
    color: #ffffff;
}

.bgv-hero-tagline {
    font-size: clamp(1.05rem, 2vw, 1.35rem);
    color: var(--text-muted, #64748b);
    max-width: 650px;
    margin: 0 auto 2.5rem;
    line-height: 1.5;
    font-weight: 500;
}

/* Interactive Logo Showcase Canvas */
.bgv-logo-stage {
    max-width: 780px;
    margin: 0 auto;
    border-radius: 28px;
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.12);
    padding: 3.5rem 2rem;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: background 0.3s ease, border-color 0.3s ease;
}

.bgv-logo-stage img {
    max-height: 140px;
    max-width: 85%;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.04));
    transition: transform 0.3s ease;
}

.bgv-logo-stage img:hover {
    transform: scale(1.03);
}

/* Canvas Mode Switcher Controls */
.bgv-canvas-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
    background: var(--bg-surface, #ffffff);
    padding: 0.35rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
}

[data-theme="dark"] .bgv-canvas-controls {
    background: #141721;
    border-color: rgba(255, 255, 255, 0.1);
}

.bgv-canvas-btn {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.2s;
}

.bgv-canvas-btn:hover {
    transform: scale(1.15);
}

.bgv-canvas-btn.active {
    border-color: #ec4899;
}

/* Sticky Navigation Sub-Menu */
.bgv-nav-bar {
    position: sticky;
    top: <?php echo $isStandalone ? '0' : '53px'; ?>;
    z-index: 90;
    background: color-mix(in srgb, var(--bg-surface, #ffffff) 85%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 0.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    overflow-x: auto;
    scrollbar-width: none;
}

[data-theme="dark"] .bgv-nav-bar {
    background: color-mix(in srgb, #0e1017 85%, transparent);
    border-color: rgba(255, 255, 255, 0.08);
}

.bgv-nav-link {
    color: var(--text-muted, #64748b);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.45rem 0.95rem;
    border-radius: 10px;
    white-space: nowrap;
    transition: all 0.2s;
}

.bgv-nav-link:hover, .bgv-nav-link.active {
    color: var(--text-main, #0f172a);
    background: var(--bg-body, #f8fafc);
}

[data-theme="dark"] .bgv-nav-link:hover, [data-theme="dark"] .bgv-nav-link.active {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

/* Sections Content */
.bgv-content-wrap {
    max-width: 1240px;
    margin: 0 auto;
    padding: 3rem 1.75rem;
}

.bgv-section {
    margin-bottom: 5rem;
    scroll-margin-top: 120px;
}

.bgv-section-header {
    margin-bottom: 2rem;
}

.bgv-section-kicker {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #ec4899;
    display: block;
    margin-bottom: 0.35rem;
}

.bgv-section-title {
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: -0.6px;
    margin: 0 0 0.5rem;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bgv-section-title {
    color: #ffffff;
}

.bgv-section-desc {
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
    margin: 0;
    max-width: 680px;
    line-height: 1.5;
}

/* Color Swatch Card */
.bgv-color-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.5rem;
}

.bgv-color-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.04);
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

[data-theme="dark"] .bgv-color-card {
    background: #14161f;
    border-color: rgba(255, 255, 255, 0.08);
}

.bgv-color-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px -4px rgba(0, 0, 0, 0.12);
}

.bgv-color-preview-block {
    height: 150px;
    width: 100%;
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 1rem;
    box-sizing: border-box;
}

.bgv-color-role-tag {
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.22rem 0.6rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bgv-color-body {
    padding: 1.25rem 1.4rem;
}

.bgv-color-name {
    font-size: 1.15rem;
    font-weight: 800;
    margin: 0 0 1rem;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bgv-color-name {
    color: #ffffff;
}

.bgv-code-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
    font-size: 0.82rem;
}

[data-theme="dark"] .bgv-code-row {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

.bgv-code-label {
    font-weight: 700;
    color: var(--text-muted, #94a3b8);
}

.bgv-code-value {
    font-family: monospace;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: color 0.2s;
}

[data-theme="dark"] .bgv-code-value {
    color: #ffffff;
}

.bgv-code-value:hover {
    color: #ec4899;
}

/* Logo Variation Cards */
.bgv-var-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.bgv-var-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 22px;
    overflow: hidden;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.25rem;
    box-shadow: 0 4px 16px -2px rgba(0,0,0,0.03);
}

[data-theme="dark"] .bgv-var-card {
    background: #14161f;
    border-color: rgba(255, 255, 255, 0.08);
}

.bgv-var-stage {
    min-height: 180px;
    border-radius: 16px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

[data-theme="dark"] .bgv-var-stage {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.08);
}

.bgv-var-stage img {
    max-height: 100px;
    max-width: 85%;
    object-fit: contain;
}

/* Typography Specimen */
.bgv-font-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    padding: 2.25rem;
    margin-bottom: 1.75rem;
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .bgv-font-card {
    background: #14161f;
    border-color: rgba(255, 255, 255, 0.08);
}

.bgv-font-alphabet {
    font-size: clamp(1.75rem, 3.5vw, 2.5rem);
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 1rem;
    letter-spacing: -0.5px;
}

.bgv-type-tester-input {
    width: 100%;
    box-sizing: border-box;
    background: var(--bg-body, #f8fafc);
    border: 1px dashed var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 0.85rem 1.2rem;
    font-size: 1.15rem;
    outline: none;
    color: var(--text-main, #0f172a);
    margin-top: 1rem;
}

[data-theme="dark"] .bgv-type-tester-input {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

/* Incorrect Uses Cards */
.bgv-incorrect-card {
    background: rgba(239, 68, 68, 0.04);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 18px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

[data-theme="dark"] .bgv-incorrect-card {
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.25);
}

.bgv-incorrect-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
</style>

<div class="bgv-wrapper">
    <!-- Optional CRM Action Bar for logged-in users -->
    <?php if ($isLoggedIn): ?>
    <div class="bgv-crm-bar">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <a href="index.php?module=brand_guidelines&action=index" class="btn btn-sm btn-secondary" style="border-radius:8px;">
                <i class="ph-bold ph-arrow-left"></i> Volver a Manuales
            </a>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
                <?php echo htmlspecialchars($bg['brand_name']); ?>
            </span>
            <span class="badge" style="font-size: 0.72rem; background: <?php echo $bg['is_public'] == 1 ? 'rgba(16,185,129,0.15)' : 'rgba(245,158,11,0.15)'; ?>; color: <?php echo $bg['is_public'] == 1 ? '#10b981' : '#f59e0b'; ?>; border-radius: 9999px; padding: 0.2rem 0.55rem;">
                <?php echo $bg['is_public'] == 1 ? 'Público' : 'Protegido con PIN'; ?>
            </span>
        </div>

        <div style="display:flex; align-items:center; gap:0.6rem;">
            <a href="index.php?module=brand_guidelines&action=edit&id=<?php echo $guidelineId; ?>" class="btn btn-sm btn-secondary" style="border-radius:8px;">
                <i class="ph-bold ph-pencil"></i> Editar
            </a>

            <a href="index.php?module=brand_guidelines&action=pdf&id=<?php echo $guidelineId; ?>&download=1" class="btn btn-sm btn-secondary" style="border-radius:8px;">
                <i class="ph-bold ph-file-pdf"></i> Descargar PDF
            </a>

            <button type="button" class="btn btn-sm" style="background:#ec4899; color:white; border-radius:8px;" onclick="copyToClipboard('<?php echo htmlspecialchars($shortUrl); ?>', 'Enlace corto copiado')">
                <i class="ph-bold ph-share-network"></i> Compartir
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- HERO STAGE -->
    <header class="bgv-hero-stage">
        <div class="bgv-hero-badge">
            <i class="ph-bold ph-sparkle"></i> Manual de Identidad Visual
        </div>

        <h1 class="bgv-hero-title"><?php echo htmlspecialchars($bg['brand_name']); ?></h1>

        <?php if (!empty($bg['tagline'])): ?>
            <p class="bgv-hero-tagline"><?php echo htmlspecialchars($bg['tagline']); ?></p>
        <?php endif; ?>

        <!-- Primary Logo Showcase Container -->
        <div class="bgv-logo-stage" id="heroLogoStage">
            <?php if (!empty($bg['logo_primary'])): ?>
                <img src="<?php echo htmlspecialchars($bg['logo_primary']); ?>" id="heroLogoImg" alt="<?php echo htmlspecialchars($bg['brand_name']); ?>">
            <?php else: ?>
                <h2 style="font-size: 3.5rem; font-weight: 900; letter-spacing: -1px; margin: 0; background: linear-gradient(135deg, <?php echo $primaryHex; ?>, <?php echo $secondaryHex; ?>); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?php echo htmlspecialchars($bg['brand_name']); ?>
                </h2>
            <?php endif; ?>
        </div>

        <!-- Canvas Background Testing Controls -->
        <div style="display:flex; flex-direction:column; align-items:center; margin-top: 1rem;">
            <div class="bgv-canvas-controls">
                <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-right:0.25rem;">Probar fondo:</span>
                <button type="button" class="bgv-canvas-btn active" style="background: #ffffff;" title="Fondo Blanco" onclick="setLogoBackground('#ffffff', 'light')"></button>
                <button type="button" class="bgv-canvas-btn" style="background: #f1f5f9;" title="Fondo Gris Claro" onclick="setLogoBackground('#f1f5f9', 'light')"></button>
                <button type="button" class="bgv-canvas-btn" style="background: #0f172a;" title="Fondo Oscuro Onyx" onclick="setLogoBackground('#0f172a', 'dark')"></button>
                <button type="button" class="bgv-canvas-btn" style="background: <?php echo $primaryHex; ?>;" title="Fondo Color Primario" onclick="setLogoBackground('<?php echo $primaryHex; ?>', 'dark')"></button>
            </div>

            <?php if (!empty($bg['logo_primary']) && ($bg['allow_asset_download'] == 1 || $isLoggedIn)): ?>
            <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($bg['logo_primary']); ?>" 
               class="btn btn-sm" 
               style="margin-top: 1rem; border-radius: 9999px; background: rgba(255, 255, 255, 0.08); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1.1rem;">
                <i class="ph-bold ph-download-simple"></i> Descargar Logo Principal
            </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- STICKY SUB-NAVIGATION -->
    <nav class="bgv-nav-bar">
        <a href="#about" class="bgv-nav-link">Filosofía</a>
        <a href="#logos" class="bgv-nav-link">Logotipo & Variaciones</a>
        <?php if (!empty($icons)): ?>
        <a href="#icons" class="bgv-nav-link">Iconografía</a>
        <?php endif; ?>
        <a href="#colors" class="bgv-nav-link">Sistema Cromático</a>
        <?php if (!empty($fonts)): ?>
        <a href="#typography" class="bgv-nav-link">Tipografías</a>
        <?php endif; ?>
        <a href="#rules" class="bgv-nav-link">Normas de Uso</a>
        <?php if (!empty($applications)): ?>
        <a href="#applications" class="bgv-nav-link">Aplicaciones</a>
        <?php endif; ?>
        <a href="index.php?module=brand_guidelines&action=pdf&id=<?php echo $guidelineId; ?>&download=1" class="bgv-nav-link" style="color: #ec4899;">
            <i class="ph-bold ph-file-pdf"></i> Descargar PDF
        </a>
    </nav>

    <!-- MAIN BODY CONTENT -->
    <main class="bgv-content-wrap">

        <!-- 1. ACERCA DE LA MARCA & FILOSOFÍA -->
        <section class="bgv-section" id="about">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">01 / Filosofía</span>
                <h2 class="bgv-section-title">Esencia & Propósito</h2>
                <p class="bgv-section-desc">Fundamentos y pilares conceptuales que guían la voz, la promesa y el comportamiento de la marca.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                <?php if (!empty($bg['description'])): ?>
                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.75rem; color: #ec4899;">
                        <i class="ph-bold ph-bookmark"></i> Sobre la Marca
                    </h3>
                    <p style="color: var(--text-muted); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($bg['description'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($bg['mission'])): ?>
                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.75rem; color: #10b981;">
                        <i class="ph-bold ph-compass"></i> Misión
                    </h3>
                    <p style="color: var(--text-muted); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($bg['mission'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($bg['vision'])): ?>
                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.75rem; color: #3b82f6;">
                        <i class="ph-bold ph-binoculars"></i> Visión
                    </h3>
                    <p style="color: var(--text-muted); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($bg['vision'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($bg['tone_of_voice'])): ?>
                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.75rem; color: #8b5cf6;">
                        <i class="ph-bold ph-megaphone"></i> Tono de Voz & Comunicación
                    </h3>
                    <p style="color: var(--text-muted); line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($bg['tone_of_voice']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 2. LOGOTIPO & VARIACIONES -->
        <section class="bgv-section" id="logos">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">02 / Identidad Visual</span>
                <h2 class="bgv-section-title">Logotipo, Isotipo y Variaciones</h2>
                <p class="bgv-section-desc">Construcción y adaptaciones del identificador visual para diferentes soportes y fondos.</p>
            </div>

            <div class="bgv-var-grid">
                <!-- Principal Light -->
                <?php if (!empty($bg['logo_primary'])): ?>
                <div class="bgv-var-card">
                    <div class="bgv-var-stage" style="background:#ffffff;">
                        <img src="<?php echo htmlspecialchars($bg['logo_primary']); ?>" alt="Versión Principal">
                    </div>
                    <div>
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 0.35rem;">Logo Principal (Fondo Claro)</h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 1rem;">Versión de uso prioritario sobre fondos blancos o claros.</p>
                        <?php if ($bg['allow_asset_download'] == 1 || $isLoggedIn): ?>
                        <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($bg['logo_primary']); ?>" class="btn btn-sm btn-secondary" style="border-radius:10px; width:100%; justify-content:center;">
                            <i class="ph-bold ph-download-simple"></i> Descargar Archivo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Principal Dark / Negativo -->
                <?php if (!empty($bg['logo_primary_dark'])): ?>
                <div class="bgv-var-card">
                    <div class="bgv-var-stage" style="background:#0f172a;">
                        <img src="<?php echo htmlspecialchars($bg['logo_primary_dark']); ?>" alt="Versión Fondo Oscuro">
                    </div>
                    <div>
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 0.35rem;">Logo para Fondo Oscuro</h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 1rem;">Versión en blanco o invertida para aplicaciones en modo oscuro.</p>
                        <?php if ($bg['allow_asset_download'] == 1 || $isLoggedIn): ?>
                        <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($bg['logo_primary_dark']); ?>" class="btn btn-sm btn-secondary" style="border-radius:10px; width:100%; justify-content:center;">
                            <i class="ph-bold ph-download-simple"></i> Descargar Archivo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Isotipo / Símbolo -->
                <?php if (!empty($bg['logo_symbol'])): ?>
                <div class="bgv-var-card">
                    <div class="bgv-var-stage">
                        <img src="<?php echo htmlspecialchars($bg['logo_symbol']); ?>" alt="Isotipo Oficial">
                    </div>
                    <div>
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 0.35rem;">Isotipo / Símbolo</h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 1rem;">Elemento icónico para avatares, sellos y favicon.</p>
                        <?php if ($bg['allow_asset_download'] == 1 || $isLoggedIn): ?>
                        <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($bg['logo_symbol']); ?>" class="btn btn-sm btn-secondary" style="border-radius:10px; width:100%; justify-content:center;">
                            <i class="ph-bold ph-download-simple"></i> Descargar Archivo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dynamic Variations -->
                <?php foreach ($variations as $v): ?>
                <div class="bgv-var-card">
                    <div class="bgv-var-stage" style="background: <?php echo ($v['bg_type'] ?? '') === 'dark' ? '#0f172a' : 'var(--bg-body)'; ?>;">
                        <?php if (!empty($v['url'])): ?>
                            <img src="<?php echo htmlspecialchars($v['url']); ?>" alt="<?php echo htmlspecialchars($v['name']); ?>">
                        <?php else: ?>
                            <i class="ph-bold ph-image" style="font-size: 2.5rem; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 0.35rem;"><?php echo htmlspecialchars($v['name']); ?></h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 1rem;"><?php echo htmlspecialchars($v['desc'] ?: 'Variación autorizada de la identidad.'); ?></p>
                        <?php if (!empty($v['url']) && ($bg['allow_asset_download'] == 1 || $isLoggedIn)): ?>
                        <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($v['url']); ?>" class="btn btn-sm btn-secondary" style="border-radius:10px; width:100%; justify-content:center;">
                            <i class="ph-bold ph-download-simple"></i> Descargar Archivo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 3. ICONOGRAFÍA -->
        <?php if (!empty($icons)): ?>
        <section class="bgv-section" id="icons">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">03 / Entorno Digital</span>
                <h2 class="bgv-section-title">Iconografía & Elementos Digitales</h2>
                <p class="bgv-section-desc">Favicons, avatares y elementos para interfaces y ecosistemas digitales.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.25rem;">
                <?php foreach ($icons as $ico): ?>
                <div class="bgv-var-card" style="text-align: center; align-items: center;">
                    <div style="width: 80px; height: 80px; border-radius: 20px; background: var(--bg-body); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; padding: 12px; margin-bottom: 0.5rem;">
                        <?php if (!empty($ico['url'])): ?>
                            <img src="<?php echo htmlspecialchars($ico['url']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="ph-bold ph-app-window" style="font-size: 2rem; color: #ec4899;"></i>
                        <?php endif; ?>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 800; margin: 0 0 0.25rem;"><?php echo htmlspecialchars($ico['name']); ?></h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.85rem;"><?php echo htmlspecialchars($ico['desc']); ?></p>
                    <?php if (!empty($ico['url']) && ($bg['allow_asset_download'] == 1 || $isLoggedIn)): ?>
                    <a href="index.php?module=brand_guidelines&action=download_asset&id=<?php echo $guidelineId; ?>&file=<?php echo urlencode($ico['url']); ?>" class="btn btn-sm btn-secondary" style="border-radius: 8px; font-size: 0.78rem;">
                        <i class="ph-bold ph-download-simple"></i> Descargar
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 4. SISTEMA CROMÁTICO (COLORES) -->
        <section class="bgv-section" id="colors">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">04 / Color</span>
                <h2 class="bgv-section-title">Sistema Cromático</h2>
                <p class="bgv-section-desc">Paleta de colores corporativos oficial. Haz clic en cualquier código para copiarlo al portapapeles.</p>
            </div>

            <div class="bgv-color-grid">
                <?php foreach ($colors as $c): 
                    $hex = htmlspecialchars($c['hex'] ?? '#000000');
                    $rgb = htmlspecialchars($c['rgb'] ?? '');
                    $cmyk = htmlspecialchars($c['cmyk'] ?? '');
                    $pantone = htmlspecialchars($c['pantone'] ?? '');
                ?>
                <div class="bgv-color-card">
                    <div class="bgv-color-preview-block" style="background: <?php echo $hex; ?>;">
                        <span class="bgv-color-role-tag"><?php echo htmlspecialchars($c['role'] ?? 'Primario'); ?></span>
                    </div>
                    <div class="bgv-color-body">
                        <h4 class="bgv-color-name"><?php echo htmlspecialchars($c['name']); ?></h4>

                        <div class="bgv-code-row">
                            <span class="bgv-code-label">HEX</span>
                            <span class="bgv-code-value" onclick="copyToClipboard('<?php echo $hex; ?>', 'Código <?php echo $hex; ?> copiado')">
                                <?php echo $hex; ?> <i class="ph-bold ph-copy" style="font-size: 0.85rem; color: #ec4899;"></i>
                            </span>
                        </div>

                        <?php if (!empty($rgb)): ?>
                        <div class="bgv-code-row">
                            <span class="bgv-code-label">RGB</span>
                            <span class="bgv-code-value" onclick="copyToClipboard('rgb(<?php echo $rgb; ?>)', 'Código RGB copiado')">
                                <?php echo $rgb; ?> <i class="ph-bold ph-copy" style="font-size: 0.85rem; color: #ec4899;"></i>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($cmyk)): ?>
                        <div class="bgv-code-row">
                            <span class="bgv-code-label">CMYK</span>
                            <span class="bgv-code-value" onclick="copyToClipboard('<?php echo $cmyk; ?>', 'Código CMYK copiado')">
                                <?php echo $cmyk; ?> <i class="ph-bold ph-copy" style="font-size: 0.85rem; color: #ec4899;"></i>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($pantone)): ?>
                        <div class="bgv-code-row">
                            <span class="bgv-code-label">PANTONE</span>
                            <span class="bgv-code-value" onclick="copyToClipboard('<?php echo $pantone; ?>', 'Código Pantone copiado')">
                                <?php echo $pantone; ?> <i class="ph-bold ph-copy" style="font-size: 0.85rem; color: #ec4899;"></i>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. TIPOGRAFÍAS CORPORATIVAS -->
        <?php if (!empty($fonts)): ?>
        <section class="bgv-section" id="typography">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">05 / Tipografía</span>
                <h2 class="bgv-section-title">Tipografías Oficiales</h2>
                <p class="bgv-section-desc">Jerarquías tipográficas, pesos y normas de composición para medios impresos y digitales.</p>
            </div>

            <?php foreach ($fonts as $f): 
                $fName = htmlspecialchars($f['name'] ?? 'Inter');
            ?>
            <div class="bgv-font-card" style="font-family: '<?php echo $fName; ?>', sans-serif;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom: 1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <span style="font-size:0.8rem; font-weight:800; color:#ec4899; text-transform:uppercase; letter-spacing:0.8px;"><?php echo htmlspecialchars($f['role'] ?? 'Fuente'); ?></span>
                        <h3 style="font-size: 2.25rem; font-weight: 800; margin: 0; color: var(--text-main);"><?php echo $fName; ?></h3>
                    </div>
                    <div>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.85rem; padding: 0.35rem 0.85rem; border-radius: 8px;">
                            Pesos: <?php echo htmlspecialchars($f['weights'] ?? 'Regular, Bold'); ?>
                        </span>
                    </div>
                </div>

                <div class="bgv-font-alphabet">
                    Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj Kk Ll Mm Nn Ññ Oo Pp Qq Rr Ss Tt Uu Vv Ww Xx Yy Zz
                </div>
                <div style="font-size: 1.25rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                    0123456789 • !@#$%&/()=?¿¡*+-.,
                </div>

                <?php if (!empty($f['usage'])): ?>
                <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; border-left: 3px solid #ec4899; padding-left: 1rem; margin: 0 0 1rem 0;">
                    <strong>Uso:</strong> <?php echo htmlspecialchars($f['usage']); ?>
                </p>
                <?php endif; ?>

                <!-- Live Type Tester -->
                <input type="text" class="bgv-type-tester-input" placeholder="Escribe aquí para probar la tipografía en tiempo real..." value="Roma Agencia Creativa - Excelencia en Branding">
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- 6. NORMAS DE USO & ÁREA DE SEGURIDAD -->
        <section class="bgv-section" id="rules">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">06 / Normativas</span>
                <h2 class="bgv-section-title">Área de Seguridad y Usos Prohibidos</h2>
                <p class="bgv-section-desc">Criterios indispensables para mantener la integridad, legibilidad y coherencia visual de la marca.</p>
            </div>

            <!-- Clear space & min sizes -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.15rem; font-weight:800; margin:0 0 0.6rem; color:#10b981;">
                        <i class="ph-bold ph-shield-check"></i> Área de Seguridad (Clear Space)
                    </h3>
                    <p style="color:var(--text-muted); line-height:1.6; margin:0;">
                        <?php echo !empty($bg['safe_zone_rules']) ? nl2br(htmlspecialchars($bg['safe_zone_rules'])) : 'El logotipo debe disponer de un espacio de respeto perimetral libre de cualquier elemento tipográfico, gráfico o de borde, asegurando una visualización óptima.'; ?>
                    </p>
                </div>

                <div class="bge-card-panel" style="margin-bottom:0;">
                    <h3 style="font-size:1.15rem; font-weight:800; margin:0 0 0.6rem; color:#3b82f6;">
                        <i class="ph-bold ph-ruler"></i> Tamaño Mínimo
                    </h3>
                    <p style="color:var(--text-muted); line-height:1.6; margin:0;">
                        <?php echo !empty($bg['min_size_rules']) ? nl2br(htmlspecialchars($bg['min_size_rules'])) : 'Para medios digitales: mínimo 60px de ancho. Para impresión física: mínimo 25mm de ancho. En tamaños inferiores, emplear únicamente el isotipo o símbolo.'; ?>
                    </p>
                </div>
            </div>

            <!-- Incorrect uses -->
            <?php if (!empty($incorrectUses)): ?>
            <h3 style="font-size: 1.35rem; font-weight: 800; margin: 2rem 0 1rem; color: #ef4444; display:flex; align-items:center; gap:0.5rem;">
                <i class="ph-bold ph-prohibit"></i> Usos No Permitidos
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <?php foreach ($incorrectUses as $u): ?>
                <div class="bgv-incorrect-card">
                    <div class="bgv-incorrect-icon">
                        <i class="ph-bold ph-x"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 0.98rem; font-weight: 800; margin: 0 0 0.25rem; color: #ef4444;">
                            <?php echo htmlspecialchars($u['title']); ?>
                        </h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.45;">
                            <?php echo htmlspecialchars($u['desc']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- 7. APLICACIONES & MOCKUPS -->
        <?php if (!empty($applications)): ?>
        <section class="bgv-section" id="applications">
            <div class="bgv-section-header">
                <span class="bgv-section-kicker">07 / Aplicaciones</span>
                <h2 class="bgv-section-title">Universo Visual & Aplicaciones</h2>
                <p class="bgv-section-desc">Ejemplos de aplicación de la identidad en soportes corporativos, comerciales y promocionales.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                <?php foreach ($applications as $app): ?>
                <div class="bgv-var-card" style="padding:0; overflow:hidden;">
                    <div style="width: 100%; height: 240px; background: var(--bg-body); overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <?php if (!empty($app['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($app['image_url']); ?>" style="width:100%; height:100%; object-fit:cover; transition:transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <?php else: ?>
                            <i class="ph-bold ph-image" style="font-size:3rem; color:var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 1.25rem 1.5rem;">
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 0.35rem;"><?php echo htmlspecialchars($app['title']); ?></h4>
                        <?php if (!empty($app['desc'])): ?>
                            <p style="font-size:0.85rem; color:var(--text-muted); margin:0;"><?php echo htmlspecialchars($app['desc']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 8. CENTRO DE DESCARGAS & EXPORTACIÓN -->
        <section class="bgv-section" id="downloads" style="text-align: center; background: radial-gradient(circle at center, color-mix(in srgb, #ec4899 12%, transparent) 0%, transparent 70%); border: 1px solid var(--border-color); border-radius: 28px; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 1.5rem;">
                <i class="ph-bold ph-file-arrow-down"></i>
            </div>
            <h2 style="font-size: 2.2rem; font-weight: 900; margin: 0 0 0.5rem;">Descarga el Manual Completo</h2>
            <p style="color: var(--text-muted); max-width: 540px; margin: 0 auto 2rem; font-size: 1rem;">
                Obtén la versión editorial de este manual de marca en formato PDF lista para enviar a imprenta o compartir con tu equipo.
            </p>

            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="index.php?module=brand_guidelines&action=pdf&id=<?php echo $guidelineId; ?>&download=1" 
                   class="btn" 
                   style="background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%); color: white; border-radius: 14px; padding: 0.85rem 1.75rem; font-weight: 800; font-size: 1rem; box-shadow: 0 8px 24px -4px rgba(236, 72, 153, 0.45); display: inline-flex; align-items: center; gap: 0.6rem;">
                    <i class="ph-bold ph-file-pdf" style="font-size: 1.25rem;"></i> Descargar Manual en PDF
                </a>

                <button type="button" 
                        class="btn btn-secondary" 
                        onclick="copyToClipboard('<?php echo htmlspecialchars($shortUrl); ?>', 'Enlace corto copiado al portapapeles')"
                        style="border-radius: 14px; padding: 0.85rem 1.5rem; font-weight: 700; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-bold ph-link-simple"></i> Copiar Enlace Compartible
                </button>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Interactive Canvas background changer
function setLogoBackground(bgHex, mode) {
    const stage = document.getElementById('heroLogoStage');
    const img = document.getElementById('heroLogoImg');
    stage.style.background = bgHex;

    document.querySelectorAll('.bgv-canvas-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    // If dark background and dark logo exists, optionally swap or adjust
    <?php if (!empty($bg['logo_primary_dark'])): ?>
    if (mode === 'dark') {
        img.src = "<?php echo htmlspecialchars($bg['logo_primary_dark']); ?>";
    } else {
        img.src = "<?php echo htmlspecialchars($bg['logo_primary']); ?>";
    }
    <?php endif; ?>
}

// Copy to Clipboard
function copyToClipboard(text, message) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message || 'Copiado al portapapeles');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showToast(message || 'Copiado al portapapeles');
        } catch (e) {
            prompt('Copia este enlace:', text);
        }
        document.body.removeChild(textArea);
    }
}

function showToast(title) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
    Toast.fire({
        icon: 'success',
        title: title
    });
}

// Live Type Tester Input Listener
document.querySelectorAll('.bgv-type-tester-input').forEach(input => {
    input.addEventListener('input', function() {
        const alphabet = this.parentElement.querySelector('.bgv-font-alphabet');
        if (alphabet) {
            alphabet.textContent = this.value || 'Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj 1234567890';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
