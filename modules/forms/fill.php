<?php
// modules/forms/fill.php — Public Modern SaaS & Native App-Style Form with Cover Banners & View Modes
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = (new Database())->getConnection();
}
if (!isset($global_settings)) {
    if (file_exists(__DIR__ . '/../../includes/functions.php')) {
        require_once __DIR__ . '/../../includes/functions.php';
        $global_settings = function_exists('get_settings') ? get_settings() : [];
    } else {
        $global_settings = [];
    }
}

$token = $_GET['token'] ?? $_GET['t'] ?? '';
if (empty($token)) { die("Enlace inválido."); }

$stmt = $db->prepare("SELECT * FROM form_templates WHERE (public_token = ? OR LEFT(public_token, 8) = ?) AND status = 'active' LIMIT 1");
$stmt->execute([$token, $token]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$form) { die("Formulario no encontrado o no está activo."); }
// Normalizar al token canónico completo
$token = $form['public_token'];

$fields = json_decode($form['fields_json'] ?: '[]', true);
$settings = json_decode($form['settings_json'] ?: '{}', true);

$showLogo = $settings['show_logo'] ?? true;
$reqName = $settings['require_name'] ?? true;
$reqEmail = $settings['require_email'] ?? true;
$isMultiStep = !empty($settings['multi_step']);

$viewStyle = $settings['view_style'] ?? 'hero_cover'; // 'hero_cover', 'slides', 'minimal'
$coverPreset = $settings['cover_image'] ?? 'nebula';
$welcomeScreen = !empty($settings['welcome_screen']);
$customAvatar = $settings['custom_avatar'] ?? '';

$logoUrl = $global_settings['logo_light'] ?? '';
$brandAvatarUrl = !empty($customAvatar) ? $customAvatar : $logoUrl;
$siteName = $global_settings['site_name'] ?? 'Roma Agencia';
$primaryColor = $global_settings['primary_color'] ?? '#4f46e5';

$coverStyles = [
    'nebula' => 'radial-gradient(circle at 20% 20%, #4338ca 0%, transparent 40%), radial-gradient(circle at 80% 80%, #7c3aed 0%, transparent 40%), radial-gradient(circle at 50% 50%, #1e1b4b 0%, #09090b 100%)',
    'cyber' => 'radial-gradient(circle at 80% 20%, #0ea5e9 0%, transparent 45%), radial-gradient(circle at 20% 80%, #10b981 0%, transparent 45%), linear-gradient(135deg, #020617 0%, #0f172a 100%)',
    'velvet' => 'radial-gradient(circle at 75% 30%, #e11d48 0%, transparent 40%), radial-gradient(circle at 25% 70%, #9333ea 0%, transparent 40%), linear-gradient(135deg, #18181b 0%, #09090b 100%)',
    'geometry' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #020617 100%)',
    'sunset' => 'radial-gradient(circle at 80% 20%, #f59e0b 0%, transparent 45%), radial-gradient(circle at 20% 80%, #ec4899 0%, transparent 45%), linear-gradient(135deg, #18181b 0%, #050505 100%)',
    'abstract' => "url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80') center/cover"
];

$coverBackground = $coverStyles[$coverPreset] ?? (
    (str_starts_with($coverPreset, 'http') || str_starts_with($coverPreset, 'data:'))
        ? "url('" . htmlspecialchars($coverPreset, ENT_QUOTES) . "') center/cover"
        : $coverStyles['nebula']
);

// Prepare steps structure
$isSlides = ($viewStyle === 'slides');
$steps = [];
$currentStep = [];

if ($reqName || $reqEmail) {
    $currentStep[] = ['type' => 'user_data_magic_field'];
    if ($isSlides) {
        $steps[] = $currentStep;
        $currentStep = [];
    }
}

foreach ($fields as $field) {
    if ($isSlides) {
        $steps[] = [$field];
    } else if ($isMultiStep && $field['type'] === 'divider') {
        if (!empty($currentStep)) $steps[] = $currentStep;
        $currentStep = [$field];
    } else {
        $currentStep[] = $field;
    }
}
if (!empty($currentStep)) $steps[] = $currentStep;
if (empty($steps)) $steps[] = [];
$totalSteps = count($steps);

// Configuración y Generación de Open Graph
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseUrl = (!empty($global_settings['site_url'])) ? rtrim($global_settings['site_url'], '/') : ($protocol . '://' . $host . ($scriptDir ? $scriptDir : ''));

$shortToken = substr($form['public_token'], 0, 8);
$ogUrl = $baseUrl . '/f/' . $shortToken;
$ogTitle = !empty($form['title']) ? $form['title'] : 'Formulario';
$cleanDesc = !empty($form['description']) ? trim(strip_tags($form['description'])) : '';
$ogDesc = !empty($cleanDesc) ? mb_strimwidth($cleanDesc, 0, 160, '...') : ('Completa este formulario en línea con ' . $siteName);

$ogImage = '';
if (!empty($customAvatar)) {
    $ogImage = str_starts_with($customAvatar, 'http') ? $customAvatar : ($baseUrl . '/' . ltrim($customAvatar, '/'));
} elseif (!empty($settings['cover_image']) && (str_starts_with($settings['cover_image'], 'http') || str_starts_with($settings['cover_image'], 'uploads/'))) {
    $ogImage = str_starts_with($settings['cover_image'], 'http') ? $settings['cover_image'] : ($baseUrl . '/' . ltrim($settings['cover_image'], '/'));
} elseif (!empty($global_settings['logo_light'])) {
    $ogImage = str_starts_with($global_settings['logo_light'], 'http') ? $global_settings['logo_light'] : ($baseUrl . '/' . ltrim($global_settings['logo_light'], '/'));
} elseif (!empty($global_settings['logo_dark'])) {
    $ogImage = str_starts_with($global_settings['logo_dark'], 'http') ? $global_settings['logo_dark'] : ($baseUrl . '/' . ltrim($global_settings['logo_dark'], '/'));
} else {
    $ogImage = $baseUrl . '/assets/img/icon-512x512.png';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Primary Meta Tags -->
<title><?php echo htmlspecialchars($ogTitle); ?> | <?php echo htmlspecialchars($siteName); ?></title>
<meta name="title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta name="description" content="<?php echo htmlspecialchars($ogDesc); ?>">

<!-- Open Graph / Facebook / WhatsApp -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo htmlspecialchars($ogUrl); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($ogDesc); ?>">
<?php if (!empty($ogImage)): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
<meta property="og:image:alt" content="<?php echo htmlspecialchars($ogTitle); ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($siteName); ?>">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="<?php echo htmlspecialchars($ogUrl); ?>">
<meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($ogDesc); ?>">
<?php if (!empty($ogImage)): ?>
<meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<script>
    (function() {
        const saved = localStorage.getItem('roma_public_theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
</script>
<style>
/* Modern App Design Tokens */
:root {
    --app-bg: #f4f4f6;
    --app-frame: #ffffff;
    --app-surface: #f8fafc;
    --app-border: #e4e4e7;
    --app-border-hover: #cbd5e1;
    --app-text: #09090b;
    --app-text-muted: #71717a;
    --app-input: #f4f4f6;
    --app-accent: <?php echo htmlspecialchars($primaryColor); ?>;
    --app-accent-light: color-mix(in srgb, var(--app-accent) 12%, transparent);
    --app-accent-glow: color-mix(in srgb, var(--app-accent) 25%, transparent);
}

[data-theme="dark"] {
    --app-bg: #000000 !important; /* Fondo Negro Puro Requerido */
    --app-frame: #0d0d11;
    --app-surface: #141419;
    --app-border: rgba(255, 255, 255, 0.08);
    --app-border-hover: rgba(255, 255, 255, 0.18);
    --app-text: #ffffff;
    --app-text-muted: #8e8e93;
    --app-input: #16161c;
    --app-accent-light: color-mix(in srgb, var(--app-accent) 16%, transparent);
    --app-accent-glow: color-mix(in srgb, var(--app-accent) 30%, transparent);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--app-bg);
    color: var(--app-text);
    min-height: 100vh;
    font-size: 13px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem 4rem;
}

/* Unified App Container */
.app-container {
    width: 100%;
    max-width: 640px;
    background: var(--app-frame);
    border: 1px solid var(--app-border);
    border-radius: 24px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
}

/* App Header */
.app-header {
    padding: 0.95rem 1.5rem;
    border-bottom: 1px solid var(--app-border);
    background: color-mix(in srgb, var(--app-frame) 92%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    position: sticky;
    top: 0;
    z-index: 40;
}

.app-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--app-text);
}

.app-brand i {
    color: var(--app-accent);
    font-size: 1.2rem;
}

.app-header-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.app-step-pill {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--app-accent);
    background: var(--app-accent-light);
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--app-accent) 20%, transparent);
}

.app-theme-btn {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    color: var(--app-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.15s ease;
}

.app-theme-btn:hover {
    color: var(--app-text);
    border-color: var(--app-border-hover);
}

/* Sleek Progress Track */
.app-progress-bar {
    height: 3px;
    background: var(--app-border);
    width: 100%;
    position: relative;
}

.app-progress-fill {
    height: 100%;
    background: var(--app-accent);
    width: 0%;
    transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 0 8px var(--app-accent-glow);
}

/* Panoramic Cover Banner */
.app-cover-banner {
    height: 160px;
    width: 100%;
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 1.25rem 1.75rem;
    background-size: cover;
    background-position: center;
    flex-shrink: 0;
}

.app-cover-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0.65) 100%);
    pointer-events: none;
}

.app-brand-avatar-float {
    position: relative;
    z-index: 5;
    width: 58px;
    height: 58px;
    border-radius: 18px;
    background: var(--app-frame);
    border: 2px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
    margin-bottom: -29px;
    overflow: hidden;
    flex-shrink: 0;
}

.app-brand-avatar-float img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
}

[data-theme="dark"] .app-brand-avatar-float img {
    filter: brightness(0) invert(1);
}

.app-brand-avatar-float i {
    font-size: 1.85rem;
    color: var(--app-accent);
}

/* Welcome Screen */
.app-welcome-screen {
    display: flex;
    flex-direction: column;
    width: 100%;
    animation: fadeIn 0.35s ease;
}

.app-welcome-content {
    padding: 2.75rem 2rem 2.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.app-welcome-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--app-accent);
    background: var(--app-accent-light);
    border: 1px solid color-mix(in srgb, var(--app-accent) 25%, transparent);
    padding: 0.28rem 0.8rem;
    border-radius: 999px;
    margin-bottom: 1.25rem;
}

.app-welcome-title {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--app-text);
    line-height: 1.25;
    margin-bottom: 0.75rem;
    letter-spacing: -0.02em;
}

.app-welcome-desc {
    font-size: 0.925rem;
    color: var(--app-text-muted);
    line-height: 1.6;
    max-width: 480px;
    margin: 0 auto 1.75rem;
}

.app-welcome-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 2.25rem;
}

.app-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--app-text-muted);
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    padding: 0.4rem 0.85rem;
    border-radius: 12px;
}

.app-meta-chip i {
    color: var(--app-accent);
    font-size: 0.95rem;
}

.app-btn-start {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 0.95rem 2.25rem;
    background: var(--app-accent);
    color: #ffffff;
    border: none;
    border-radius: 16px;
    font-size: 1.05rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 8px 24px var(--app-accent-glow);
}

.app-btn-start:hover {
    filter: brightness(1.12);
    transform: translateY(-2px);
    box-shadow: 0 12px 30px var(--app-accent-glow);
}

.app-btn-start:active {
    transform: translateY(0);
}

.app-start-hint {
    margin-top: 0.85rem;
    font-size: 0.75rem;
    color: var(--app-text-muted);
}

/* App Body Area */
.app-body {
    padding: 2rem 1.85rem;
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
}

/* Form Hero Intro */
.app-hero-section {
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--app-border);
}

.app-hero-logo {
    max-height: 40px;
    max-width: 160px;
    object-fit: contain;
    margin-bottom: 1rem;
    display: block;
}
[data-theme="dark"] .app-hero-logo {
    filter: brightness(0) invert(1);
}

.app-hero-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--app-text);
    line-height: 1.25;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.app-hero-desc {
    font-size: 0.875rem;
    color: var(--app-text-muted);
    line-height: 1.6;
}

/* Question Section */
.app-question-block {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.app-q-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.app-q-number {
    font-size: 0.72rem;
    font-weight: 800;
    font-family: monospace;
    color: var(--app-accent);
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    flex-shrink: 0;
    margin-top: 1px;
}

.app-q-label-box {
    flex: 1;
}

.app-q-label {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-text);
    line-height: 1.35;
    display: block;
}

/* Slides Mode Enhanced Question Typography */
.slides-mode .app-q-label {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.3;
}

.slides-mode .app-q-desc {
    font-size: 0.875rem;
    margin-top: 0.4rem;
}

.app-q-label .req-star {
    color: #ef4444;
    margin-left: 0.25rem;
}

.app-q-desc {
    font-size: 0.8125rem;
    color: var(--app-text-muted);
    margin-top: 0.25rem;
    line-height: 1.45;
}

/* Modern App Inputs */
.app-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.app-input-icon {
    position: absolute;
    left: 1rem;
    font-size: 1.15rem;
    color: var(--app-text-muted);
    pointer-events: none;
    transition: color 0.15s ease;
}

.app-input-wrap:focus-within .app-input-icon {
    color: var(--app-accent);
}

.app-input {
    width: 100%;
    padding: 0.85rem 1.1rem;
    border: 1px solid var(--app-border);
    border-radius: 14px;
    font-size: 0.9rem;
    font-family: inherit;
    background: var(--app-surface);
    color: var(--app-text);
    outline: none;
    transition: all 0.18s ease;
}

.app-input.has-icon {
    padding-left: 2.75rem;
}

.app-input:focus {
    border-color: var(--app-accent);
    box-shadow: 0 0 0 2px var(--app-accent-light);
}

.app-textarea {
    min-height: 110px;
    resize: vertical;
    line-height: 1.5;
}

/* App Selectable Options */
.app-options-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.app-opt-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.9rem 1.15rem;
    border: 1px solid var(--app-border);
    border-radius: 14px;
    background: var(--app-surface);
    color: var(--app-text);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.app-opt-row:hover {
    border-color: var(--app-border-hover);
    background: color-mix(in srgb, var(--app-accent) 4%, var(--app-surface));
}

.app-opt-row.selected {
    border-color: var(--app-accent);
    background: var(--app-accent-light);
    color: var(--app-text);
    font-weight: 600;
}

.app-opt-check {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.app-opt-check.checkbox-style {
    border-radius: 5px;
}

.app-opt-row.selected .app-opt-check {
    border-color: var(--app-accent);
    background: var(--app-accent);
    color: #ffffff;
}

.app-opt-row input[type="radio"],
.app-opt-row input[type="checkbox"] {
    display: none;
}

/* File Dropzone */
.app-file-dropzone {
    border: 1.5px dashed var(--app-border);
    border-radius: 16px;
    padding: 2rem 1.5rem;
    text-align: center;
    color: var(--app-text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
    background: var(--app-surface);
}

.app-file-dropzone:hover {
    border-color: var(--app-accent);
    background: var(--app-accent-light);
}

.app-file-dropzone i.cloud-icon {
    font-size: 2.5rem;
    color: var(--app-accent);
    display: block;
    margin-bottom: 0.5rem;
}

.app-file-dropzone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 10;
    width: 100%;
    height: 100%;
}

/* Rating Scale */
.app-scale-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.25rem 0;
}

.app-scale-item input { display: none; }

.app-scale-pill {
    width: 44px;
    height: 44px;
    border: 1px solid var(--app-border);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-text);
    background: var(--app-surface);
    cursor: pointer;
    transition: all 0.15s ease;
}

.app-scale-item:hover .app-scale-pill {
    border-color: var(--app-accent);
    color: var(--app-accent);
}

.app-scale-item input:checked + .app-scale-pill {
    background: var(--app-accent);
    border-color: var(--app-accent);
    color: #ffffff;
    box-shadow: 0 4px 12px var(--app-accent-glow);
}

/* Color Swatches */
.app-color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.app-color-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
}

.app-color-box {
    position: relative;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    border: 2px solid var(--app-border);
    padding: 2px;
    cursor: pointer;
    background: var(--app-surface);
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.app-color-box:hover {
    border-color: var(--app-accent);
    transform: scale(1.08);
}

.app-color-box.selected {
    border-color: var(--app-accent);
    transform: scale(1.1);
    box-shadow: 0 4px 14px var(--app-accent-glow);
}

.app-color-inner {
    width: 100%;
    height: 100%;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1rem;
}

/* Icon Cards */
.app-icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.65rem;
}

.app-icon-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border: 1px solid var(--app-border);
    border-radius: 14px;
    background: var(--app-surface);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.app-icon-card:hover {
    border-color: var(--app-border-hover);
    transform: translateY(-1px);
}

.app-icon-card.selected {
    border-color: var(--app-accent);
    background: var(--app-accent-light);
}

.app-icon-badge {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--app-accent-light);
    color: var(--app-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.app-icon-card.selected .app-icon-badge {
    background: var(--app-accent);
    color: #ffffff;
}

.app-icon-title {
    flex: 1;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--app-text);
}

.app-icon-check {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.app-icon-card.selected .app-icon-check {
    background: var(--app-accent);
    border-color: var(--app-accent);
    color: #ffffff;
}

/* Comparative Visual Cards (Images & Icons) */
.app-compare-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 0.35rem;
}

.app-compare-card {
    display: flex;
    flex-direction: column;
    border: 2px solid var(--app-border);
    border-radius: 18px;
    background: var(--app-surface);
    cursor: pointer;
    overflow: hidden;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    user-select: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.app-compare-card:hover {
    border-color: var(--app-border-hover);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.app-compare-card.selected {
    border-color: var(--app-accent);
    background: var(--app-surface);
    box-shadow: 0 0 0 1.5px var(--app-accent), 0 10px 30px var(--app-accent-glow);
    transform: translateY(-2px);
}

.app-compare-media-container {
    position: relative;
    width: 100%;
    overflow: hidden;
    background: var(--app-surface-sub);
}

.app-compare-img-wrap {
    position: relative;
    width: 100%;
    height: 180px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--app-surface-sub);
}

.app-compare-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}

.app-compare-card:hover .app-compare-img-wrap img {
    transform: scale(1.05);
}

.app-compare-badge-overlay {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 2;
}

.app-compare-pill {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 9px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.65);
    color: #ffffff;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.app-compare-icon-wrap {
    height: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: radial-gradient(circle at 50% 50%, var(--app-accent-light) 0%, transparent 70%);
}

.app-compare-icon-badge {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: var(--app-accent-light);
    color: var(--app-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    transition: all 0.25s ease;
}

.app-compare-card.selected .app-compare-icon-badge {
    background: var(--app-accent);
    color: #ffffff;
    transform: scale(1.08);
}

.app-compare-body {
    padding: 1rem 1.15rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
}

.app-compare-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.app-compare-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-text);
    line-height: 1.3;
}

.app-compare-desc {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--app-text-muted);
    line-height: 1.4;
}

.app-compare-check {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--app-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    font-size: 0.8rem;
    flex-shrink: 0;
    transition: all 0.18s ease;
    background: var(--app-surface-sub);
}

.app-compare-card.selected .app-compare-check {
    background: var(--app-accent);
    border-color: var(--app-accent);
    color: #ffffff;
}

@media (max-width: 600px) {
    .app-compare-grid {
        grid-template-columns: 1fr;
    }
    .app-compare-img-wrap {
        height: 160px;
    }
}

/* Action Bar */
.app-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--app-border);
}

.app-btn-submit {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.9rem 1.5rem;
    background: var(--app-accent);
    color: #ffffff;
    border: none;
    border-radius: 14px;
    font-size: 0.95rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.18s ease;
    box-shadow: 0 4px 14px var(--app-accent-glow);
}

.app-btn-submit:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

.app-btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.app-btn-prev {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.9rem 1.25rem;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    color: var(--app-text);
    border-radius: 14px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.app-btn-prev:hover {
    border-color: var(--app-border-hover);
}

/* Keyboard hint in slides */
.app-slide-hint {
    font-size: 0.72rem;
    color: var(--app-text-muted);
    text-align: center;
    margin-top: 0.5rem;
}

/* Success Confirmation Screen */
.app-success-screen {
    padding: 3.5rem 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.app-success-icon {
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(16, 185, 129, 0.25);
    box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
}

.app-success-screen h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--app-text);
    margin-bottom: 0.5rem;
}

.app-success-screen p {
    color: var(--app-text-muted);
    font-size: 0.875rem;
    max-width: 420px;
    margin: 0 auto 1.5rem;
    line-height: 1.6;
}

.app-correlativo-box {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    padding: 0.6rem 1.15rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-family: monospace;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--app-accent);
}

.app-copy-btn {
    background: none;
    border: none;
    color: var(--app-text-muted);
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.app-copy-btn:hover { color: var(--app-text); }

.app-footer-brand {
    margin-top: 1.75rem;
    font-size: 0.75rem;
    color: var(--app-text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-step {
    animation: fadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
</head>
<body class="<?php echo $isSlides ? 'slides-mode' : ''; ?>">

<div class="app-container">
    <!-- Header -->
    <div class="app-header">
        <div class="app-brand">
            <i class="ph-bold ph-shield-check"></i>
            <span><?php echo htmlspecialchars($siteName); ?></span>
        </div>
        <div class="app-header-right">
            <span class="app-step-pill" id="stepCounterPill">0% completado</span>
            <button type="button" class="app-theme-btn" onclick="toggleTheme()" title="Cambiar tema">
                <i class="ph-bold ph-moon" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <!-- Progress Track -->
    <div class="app-progress-bar">
        <div class="app-progress-fill" id="progressBar"></div>
    </div>

    <!-- Optional Welcome Screen -->
    <?php if($welcomeScreen): ?>
    <div class="app-welcome-screen" id="welcomeScreen">
        <?php if($viewStyle !== 'minimal'): ?>
        <div class="app-cover-banner" style="background: <?php echo $coverBackground; ?>;">
            <div class="app-cover-overlay"></div>
            <div class="app-brand-avatar-float">
                <?php if($showLogo && $brandAvatarUrl): ?>
                    <img src="<?php echo htmlspecialchars($brandAvatarUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
                <?php else: ?>
                    <i class="ph-bold ph-shield-check"></i>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="app-welcome-content">
            <div class="app-welcome-badge">
                <i class="ph-bold ph-sparkle"></i> Formulario Oficial
            </div>
            <h1 class="app-welcome-title"><?php echo htmlspecialchars($form['title']); ?></h1>
            <?php if($form['description']): ?>
                <p class="app-welcome-desc"><?php echo htmlspecialchars($form['description']); ?></p>
            <?php endif; ?>

            <div class="app-welcome-meta">
                <div class="app-meta-chip">
                    <i class="ph-bold ph-clock"></i>
                    <span>~2 min</span>
                </div>
                <div class="app-meta-chip">
                    <i class="ph-bold ph-shield-check"></i>
                    <span>Seguro y Cifrado</span>
                </div>
                <div class="app-meta-chip">
                    <i class="ph-bold ph-list-numbers"></i>
                    <span><?php echo count($fields); ?> preguntas</span>
                </div>
            </div>

            <button type="button" class="app-btn-start" onclick="startForm()">
                <span>Comenzar Formulario</span>
                <i class="ph-bold ph-arrow-right"></i>
            </button>
            <span class="app-start-hint">o presiona <strong>Enter ↵</strong> para comenzar</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Form Wrapper -->
    <div id="mainFormWrap" style="<?php echo $welcomeScreen ? 'display: none;' : 'display: block;'; ?>">
        <?php if($viewStyle === 'hero_cover'): ?>
        <!-- Visual Cover Banner for Hero Cover Mode -->
        <div class="app-cover-banner" id="formCoverBanner" style="background: <?php echo $coverBackground; ?>;">
            <div class="app-cover-overlay"></div>
            <div class="app-brand-avatar-float">
                <?php if($showLogo && $brandAvatarUrl): ?>
                    <img src="<?php echo htmlspecialchars($brandAvatarUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
                <?php else: ?>
                    <i class="ph-bold ph-shield-check"></i>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Body Area -->
        <div class="app-body">
            <!-- Form Hero Intro (Only if no welcome screen or in non-slides view) -->
            <?php if(!$welcomeScreen && $viewStyle !== 'slides'): ?>
            <div class="app-hero-section" id="formHero">
                <?php if($viewStyle === 'minimal' && $showLogo && $brandAvatarUrl): ?>
                    <img src="<?php echo htmlspecialchars($brandAvatarUrl); ?>" class="app-hero-logo" alt="<?php echo htmlspecialchars($siteName); ?>">
                <?php endif; ?>
                <h1 class="app-hero-title"><?php echo htmlspecialchars($form['title']); ?></h1>
                <?php if($form['description']): ?>
                    <p class="app-hero-desc"><?php echo htmlspecialchars($form['description']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form id="publicForm" enctype="multipart/form-data">
                <?php $qIdx = 0; ?>
                <?php foreach($steps as $sIndex => $stepFields): ?>
                <div class="form-step" id="step_<?php echo $sIndex; ?>" style="<?php echo $sIndex === 0 ? '' : 'display:none;'; ?>">
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach($stepFields as $field): ?>
                            <?php if($field['type'] === 'user_data_magic_field'): ?>
                                <div class="app-question-block" style="padding-bottom: 1.5rem; border-bottom: 1px solid var(--app-border);">
                                    <div class="app-q-header">
                                        <div class="app-q-number"><i class="ph-bold ph-user"></i></div>
                                        <div class="app-q-label-box">
                                            <label class="app-q-label">Tus Datos de Contacto</label>
                                            <p class="app-q-desc">Ingresa tus datos para confirmar la recepción.</p>
                                        </div>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-top: 0.25rem;">
                                        <?php if($reqName): ?>
                                        <div>
                                            <label style="display:block; font-size:0.8125rem; font-weight:600; color:var(--app-text); margin-bottom:0.35rem;">
                                                Nombre completo <span style="color:#ef4444;">*</span>
                                            </label>
                                            <div class="app-input-wrap">
                                                <i class="ph-bold ph-user app-input-icon"></i>
                                                <input type="text" name="respondent_name" class="app-input has-icon" placeholder="Ingresa tu nombre completo..." required>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if($reqEmail): ?>
                                        <div>
                                            <label style="display:block; font-size:0.8125rem; font-weight:600; color:var(--app-text); margin-bottom:0.35rem;">
                                                Correo electrónico <span style="color:#ef4444;">*</span>
                                            </label>
                                            <div class="app-input-wrap">
                                                <i class="ph-bold ph-envelope-simple app-input-icon"></i>
                                                <input type="email" name="respondent_email" class="app-input has-icon" placeholder="correo@ejemplo.com" required>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif($field['type'] === 'divider'): ?>
                                <div style="padding: 1.25rem 0 0.5rem; <?php echo $sIndex > 0 ? '' : 'border-top: 1px solid var(--app-border);'; ?>">
                                    <div style="font-size: 0.72rem; font-weight: 700; color: var(--app-accent); text-transform: uppercase; margin-bottom: 0.25rem;">Sección</div>
                                    <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--app-text);"><?php echo htmlspecialchars($field['label']); ?></h3>
                                    <?php if(!empty($field['description'])): ?>
                                        <p style="font-size: 0.875rem; color: var(--app-text-muted); margin-top: 0.35rem; line-height: 1.5;"><?php echo htmlspecialchars($field['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php 
                                    $qIdx++; 
                                    $qStr = $qIdx < 10 ? '0' . $qIdx : $qIdx;
                                ?>
                                <div class="app-question-block">
                                    <div class="app-q-header">
                                        <div class="app-q-number"><?php echo $qStr; ?></div>
                                        <div class="app-q-label-box">
                                            <label class="app-q-label">
                                                <?php echo htmlspecialchars($field['label']); ?>
                                                <?php if(!empty($field['required'])): ?><span class="req-star">*</span><?php endif; ?>
                                            </label>
                                            <?php if(!empty($field['description'])): ?>
                                                <p class="app-q-desc"><?php echo htmlspecialchars($field['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if($field['type']==='text'): ?>
                                        <div class="app-input-wrap">
                                            <i class="ph-bold ph-text-aa app-input-icon"></i>
                                            <input type="text" name="field_<?php echo $field['id']; ?>" class="app-input has-icon" placeholder="<?php echo htmlspecialchars($field['placeholder'] ?: 'Escribe tu respuesta...'); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                        </div>

                                    <?php elseif($field['type']==='email'): ?>
                                        <div class="app-input-wrap">
                                            <i class="ph-bold ph-envelope-simple app-input-icon"></i>
                                            <input type="email" name="field_<?php echo $field['id']; ?>" class="app-input has-icon" placeholder="<?php echo htmlspecialchars($field['placeholder'] ?: 'correo@ejemplo.com'); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                        </div>

                                    <?php elseif($field['type']==='phone'): ?>
                                        <div class="app-input-wrap">
                                            <i class="ph-bold ph-phone app-input-icon"></i>
                                            <input type="tel" name="field_<?php echo $field['id']; ?>" class="app-input has-icon" placeholder="<?php echo htmlspecialchars($field['placeholder'] ?: '+51 999 999 999'); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                        </div>

                                    <?php elseif($field['type']==='date'): ?>
                                        <div class="app-input-wrap">
                                            <i class="ph-bold ph-calendar-blank app-input-icon"></i>
                                            <input type="date" name="field_<?php echo $field['id']; ?>" class="app-input has-icon" <?php echo !empty($field['required'])?'required':''; ?>>
                                        </div>

                                    <?php elseif($field['type']==='textarea'): ?>
                                        <textarea name="field_<?php echo $field['id']; ?>" class="app-input app-textarea" placeholder="<?php echo htmlspecialchars($field['placeholder'] ?: 'Escribe los detalles aquí...'); ?>" <?php echo !empty($field['required'])?'required':''; ?>></textarea>

                                    <?php elseif($field['type']==='select' || $field['type']==='checkbox'): ?>
                                        <?php 
                                            $isMulti = isset($field['is_multi']) ? $field['is_multi'] : ($field['type'] === 'checkbox');
                                            $inputType = $isMulti ? 'checkbox' : 'radio';
                                        ?>
                                        <div class="app-options-list">
                                            <?php foreach(($field['options']??[]) as $opt): ?>
                                                <?php $isOther = ($opt === 'Otro'); ?>
                                                <label class="app-opt-row">
                                                    <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" value="<?php echo htmlspecialchars($opt); ?>" <?php echo !empty($field['required']) && !$isOther ? 'required data-req="true"' : ''; ?> onchange="handleOptionChange(this)">
                                                    <div class="app-opt-check <?php echo $isMulti ? 'checkbox-style' : ''; ?>">
                                                        <i class="ph-bold ph-check" style="font-size: 0.75rem;"></i>
                                                    </div>
                                                    <span style="flex:1;"><?php echo htmlspecialchars($opt); ?></span>
                                                    <?php if($isOther): ?>
                                                        <input type="text" name="field_<?php echo $field['id']; ?>_other" class="app-input other-input" style="display:none; margin-left: 0.5rem; padding: 0.4rem 0.75rem; width: auto; flex: 1;" placeholder="Especifica...">
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif($field['type']==='dropdown'): ?>
                                        <?php $isMulti = !empty($field['is_multi']); ?>
                                        <select name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" class="app-input" <?php echo !empty($field['required'])?'required':''; ?> <?php echo $isMulti ? 'multiple style="height:auto"' : ''; ?>>
                                            <?php if(!$isMulti): ?><option value="">Selecciona una opción...</option><?php endif; ?>
                                            <?php foreach(($field['options']??[]) as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif($field['type']==='file'): ?>
                                        <?php 
                                            $maxCount = $field['file_max_count'] ?? 1;
                                            $maxSize = $field['file_max_size'] ?? 10;
                                            $accept = '';
                                            if(!empty($field['file_restrict']) && !empty($field['file_types'])) {
                                                $mimes = [];
                                                foreach($field['file_types'] as $t) {
                                                    if($t === 'Documento') $mimes[] = '.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt';
                                                    if($t === 'PDF') $mimes[] = '.pdf';
                                                    if($t === 'Imagen') $mimes[] = 'image/*';
                                                    if($t === 'Video') $mimes[] = 'video/*';
                                                    if($t === 'Audio') $mimes[] = 'audio/*';
                                                }
                                                $accept = 'accept="' . implode(',', $mimes) . '"';
                                            }
                                            $isMultiple = $maxCount > 1;
                                        ?>
                                        <div class="app-file-dropzone" id="fz_<?php echo $field['id']; ?>">
                                            <i class="ph-bold ph-cloud-arrow-up cloud-icon"></i>
                                            <div style="font-weight: 700; color: var(--app-text); margin-bottom: 2px;">Arrastra tus archivos o haz clic aquí</div>
                                            <div style="font-size: 0.75rem; color: var(--app-text-muted);">Máximo <?php echo $maxSize; ?> MB <?php echo $isMultiple ? '('.$maxCount.' archivos)' : ''; ?></div>
                                            <div id="fn_<?php echo $field['id']; ?>" style="margin-top: 10px;"></div>
                                            <input type="file" name="file_<?php echo $field['id']; ?><?php echo $isMultiple ? '[]' : ''; ?>" <?php echo $accept; ?> <?php echo $isMultiple ? 'multiple' : ''; ?> <?php echo !empty($field['required'])?'required':''; ?> onchange="handleAsyncUpload(this, '<?php echo $field['id']; ?>', <?php echo $maxSize; ?>, <?php echo $maxCount; ?>)">
                                        </div>

                                    <?php elseif($field['type']==='range'): ?>
                                        <?php $mn=$field['range_min']??1; $mx=$field['range_max']??5; $lMin=$field['range_label_min']??''; $lMax=$field['range_label_max']??''; ?>
                                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; font-weight:600; color:var(--app-text-muted); margin-bottom:0.5rem;">
                                            <span><?php echo htmlspecialchars($lMin); ?></span>
                                            <span><?php echo htmlspecialchars($lMax); ?></span>
                                        </div>
                                        <div class="app-scale-wrap">
                                            <?php for($n=$mn; $n<=$mx; $n++): ?>
                                            <label class="app-scale-item">
                                                <input type="radio" name="field_<?php echo $field['id']; ?>" value="<?php echo $n; ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                                <div class="app-scale-pill"><?php echo $n; ?></div>
                                            </label>
                                            <?php endfor; ?>
                                        </div>

                                    <?php elseif($field['type']==='number_range'): ?>
                                        <?php $nrMin=$field['nr_min']??18; $nrMax=$field['nr_max']??65; $nrStep=$field['nr_step']??1; ?>
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <select name="field_<?php echo $field['id']; ?>_from" class="app-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                                <option value="">Desde</option>
                                                <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                                <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <span style="font-weight: 800; color: var(--app-text-muted);">—</span>
                                            <select name="field_<?php echo $field['id']; ?>_to" class="app-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                                <option value="">Hasta</option>
                                                <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                                <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                    <?php elseif($field['type']==='color'): ?>
                                        <?php $colorOpts=$field['color_options']??['#4f46e5']; ?>
                                        <div class="app-color-grid">
                                            <?php foreach($colorOpts as $ci => $color): ?>
                                            <label class="app-color-item">
                                                <input type="checkbox" name="field_<?php echo $field['id']; ?>[]" value="<?php echo htmlspecialchars($color); ?>" style="display:none;" onchange="handleColorToggle(this)">
                                                <div class="app-color-box">
                                                    <div class="app-color-inner" style="background: <?php echo htmlspecialchars($color); ?>;">
                                                        <i class="ph-bold ph-check" style="display:none;"></i>
                                                    </div>
                                                </div>
                                                <span style="font-size:0.7rem; color:var(--app-text-muted); font-family:monospace;"><?php echo htmlspecialchars($color); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif($field['type']==='icon_card'): ?>
                                        <?php 
                                            $iconOpts=$field['icon_options']??[]; 
                                            $isMulti=$field['icon_multi']??false; 
                                            $inputType=$isMulti?'checkbox':'radio'; 
                                        ?>
                                        <div class="app-icon-grid">
                                            <?php foreach($iconOpts as $oi => $opt): ?>
                                            <label class="app-icon-card">
                                                <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti?'[]':''; ?>" value="<?php echo htmlspecialchars($opt['text']); ?>" <?php echo !empty($field['required']) ? 'required data-req="true"' : ''; ?> style="display:none;" onchange="handleIconCardChange(this, <?php echo $isMulti ? 'true' : 'false'; ?>)">
                                                <div class="app-icon-badge"><i class="ph-bold <?php echo htmlspecialchars($opt['icon']); ?>"></i></div>
                                                <span class="app-icon-title"><?php echo htmlspecialchars($opt['text']); ?></span>
                                                <div class="app-icon-check"><i class="ph-bold ph-check" style="font-size: 0.75rem;"></i></div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif($field['type']==='image_compare'): ?>
                                        <?php 
                                            $compOpts = $field['compare_options'] ?? [];
                                            $isMulti = !empty($field['compare_multi']);
                                            $inputType = $isMulti ? 'checkbox' : 'radio';
                                        ?>
                                        <div class="app-compare-grid">
                                            <?php foreach($compOpts as $oi => $cOpt): ?>
                                            <?php 
                                                $isImg = ($cOpt['opt_type'] ?? 'image') === 'image';
                                                $val = !empty($cOpt['title']) ? $cOpt['title'] : ('Opción ' . chr(65 + $oi));
                                            ?>
                                            <label class="app-compare-card">
                                                <input type="<?php echo $inputType; ?>" 
                                                       name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" 
                                                       value="<?php echo htmlspecialchars($val); ?>" 
                                                       <?php echo !empty($field['required']) ? 'required data-req="true"' : ''; ?> 
                                                       style="display:none;" 
                                                       onchange="handleCompareCardChange(this, <?php echo $isMulti ? 'true' : 'false'; ?>)">
                                                
                                                <div class="app-compare-media-container">
                                                    <?php if($isImg && !empty($cOpt['image'])): ?>
                                                        <div class="app-compare-img-wrap">
                                                            <img src="<?php echo htmlspecialchars($cOpt['image']); ?>" alt="<?php echo htmlspecialchars($val); ?>" loading="lazy">
                                                            <div class="app-compare-badge-overlay">
                                                                <span class="app-compare-pill">Opción <?php echo chr(65 + $oi); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="app-compare-icon-wrap">
                                                            <div class="app-compare-icon-badge">
                                                                <i class="ph-bold <?php echo htmlspecialchars($cOpt['icon'] ?? 'ph-image'); ?>"></i>
                                                            </div>
                                                            <span class="app-compare-pill">Opción <?php echo chr(65 + $oi); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="app-compare-body">
                                                    <div class="app-compare-header-row">
                                                        <h4 class="app-compare-title"><?php echo htmlspecialchars($val); ?></h4>
                                                        <div class="app-compare-check">
                                                            <i class="ph-bold ph-check"></i>
                                                        </div>
                                                    </div>
                                                    <?php if(!empty($cOpt['desc'])): ?>
                                                        <p class="app-compare-desc"><?php echo htmlspecialchars($cOpt['desc']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="app-actions">
                        <?php if(($isMultiStep || $isSlides) && $sIndex > 0): ?>
                            <button type="button" class="app-btn-prev" onclick="goToStep(<?php echo $sIndex-1; ?>)">
                                <i class="ph-bold ph-arrow-left"></i> Anterior
                            </button>
                        <?php endif; ?>

                        <?php if(($isMultiStep || $isSlides) && $sIndex < count($steps) - 1): ?>
                            <button type="button" class="app-btn-submit" onclick="goToStep(<?php echo $sIndex+1; ?>)">
                                Siguiente <i class="ph-bold ph-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <button type="submit" class="app-btn-submit" id="submitBtn">
                                <i class="ph-bold ph-paper-plane-tilt"></i> Enviar Respuestas
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if($isSlides): ?>
                        <div class="app-slide-hint">o presiona <strong>Enter ↵</strong> para avanzar</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </form>

            <!-- Success Screen -->
            <div class="app-success-screen" id="successScreen" style="display: none;">
                <div class="app-success-icon">
                    <i class="ph-bold ph-check"></i>
                </div>
                <h2>¡Respuestas Recibidas con Éxito!</h2>
                <p>Gracias por enviarnos tu información. Hemos recibido todas tus respuestas y nuestro equipo se pondrá en contacto contigo pronto.</p>
                <div>
                    <div class="app-correlativo-box">
                        <i class="ph-bold ph-hash"></i>
                        <span id="successCorrelativo">BRIEF-0000</span>
                        <button type="button" class="app-copy-btn" onclick="copyCorrelativo()" title="Copiar código">
                            <i class="ph-bold ph-copy"></i>
                        </button>
                    </div>
                </div>
                <div style="margin-top: 0.75rem;">
                    <button type="button" class="app-btn-prev" onclick="window.location.reload()" style="display: inline-flex;">
                        <i class="ph-bold ph-arrow-clockwise"></i> Enviar otra respuesta
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="app-footer-brand">
    <i class="ph-bold ph-shield-check" style="color: var(--app-accent);"></i>
    <span>Plataforma Oficial <strong><?php echo htmlspecialchars($siteName); ?></strong></span>
</div>

<script>
let currentStepIdx = 0;
const totalSteps = <?php echo $totalSteps; ?>;
const isSlidesMode = <?php echo $isSlides ? 'true' : 'false'; ?>;
let correlativoCode = '';

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const target = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', target);
    localStorage.setItem('roma_public_theme', target);
    updateThemeIcon();
}

function updateThemeIcon() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = isDark ? 'ph-bold ph-sun' : 'ph-bold ph-moon';
    }
}
updateThemeIcon();

function startForm() {
    const welcome = document.getElementById('welcomeScreen');
    const mainWrap = document.getElementById('mainFormWrap');
    if (welcome) welcome.style.display = 'none';
    if (mainWrap) mainWrap.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    updateProgress();
}

function handleOptionChange(input) {
    const group = input.closest('.app-options-list');
    if (!group) return;
    if (input.type === 'radio') {
        group.querySelectorAll('.app-opt-row').forEach(lbl => lbl.classList.remove('selected'));
        if (input.checked) input.closest('.app-opt-row').classList.add('selected');
    } else {
        input.closest('.app-opt-row').classList.toggle('selected', input.checked);
        const reqBoxes = group.querySelectorAll('input[data-req="true"]');
        const anyChecked = group.querySelectorAll('input[type="checkbox"]:checked').length > 0;
        reqBoxes.forEach(b => b.required = !anyChecked);
    }

    const otherInput = input.closest('.app-opt-row').querySelector('.other-input');
    if (otherInput) {
        if (input.checked) {
            otherInput.style.display = 'block';
            otherInput.required = true;
            otherInput.focus();
        } else {
            otherInput.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
    }
    updateProgress();

    // Auto-advance in slides mode on single-choice radio selection
    if (isSlidesMode && input.type === 'radio' && input.checked && !otherInput && currentStepIdx < totalSteps - 1) {
        setTimeout(() => { goToStep(currentStepIdx + 1); }, 260);
    }
}

function handleIconCardChange(input, isMulti) {
    const card = input.closest('.app-icon-card');
    const container = input.closest('.app-icon-grid');
    if (!isMulti) {
        container.querySelectorAll('.app-icon-card').forEach(c => c.classList.remove('selected'));
        if (input.checked) card.classList.add('selected');
    } else {
        card.classList.toggle('selected', input.checked);
        const reqBoxes = container.querySelectorAll('input[data-req="true"]');
        const anyChecked = container.querySelectorAll('input[type="checkbox"]:checked').length > 0;
        reqBoxes.forEach(b => b.required = !anyChecked);
    }
    updateProgress();

    // Auto-advance in slides mode on single-choice card selection
    if (isSlidesMode && !isMulti && input.checked && currentStepIdx < totalSteps - 1) {
        setTimeout(() => { goToStep(currentStepIdx + 1); }, 260);
    }
}

function handleCompareCardChange(input, isMulti) {
    const card = input.closest('.app-compare-card');
    const container = input.closest('.app-compare-grid');
    if (!isMulti) {
        container.querySelectorAll('.app-compare-card').forEach(c => c.classList.remove('selected'));
        if (input.checked) card.classList.add('selected');
    } else {
        card.classList.toggle('selected', input.checked);
        const reqBoxes = container.querySelectorAll('input[data-req="true"]');
        const anyChecked = container.querySelectorAll('input[type="checkbox"]:checked').length > 0;
        reqBoxes.forEach(b => b.required = !anyChecked);
    }
    updateProgress();

    // Auto-advance in slides mode on single-choice compare selection
    if (isSlidesMode && !isMulti && input.checked && currentStepIdx < totalSteps - 1) {
        setTimeout(() => { goToStep(currentStepIdx + 1); }, 300);
    }
}

function handleColorToggle(input) {
    const box = input.closest('.app-color-item').querySelector('.app-color-box');
    const check = box.querySelector('.ph-check');
    if (input.checked) {
        box.classList.add('selected');
        if (check) check.style.display = 'inline-block';
    } else {
        box.classList.remove('selected');
        if (check) check.style.display = 'none';
    }
    updateProgress();
}

window.goToStep = function(nextIdx) {
    if (nextIdx > currentStepIdx) {
        const stepEl = document.getElementById('step_' + currentStepIdx);
        if (!stepEl) return;
        const inputs = stepEl.querySelectorAll('input, select, textarea');
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].reportValidity()) return;
        }
    }
    document.getElementById('step_' + currentStepIdx).style.display = 'none';
    document.getElementById('step_' + nextIdx).style.display = 'block';

    const hero = document.getElementById('formHero');
    if (hero) hero.style.display = (nextIdx > 0) ? 'none' : 'block';

    currentStepIdx = nextIdx;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    updateProgress();
};

const form = document.getElementById('publicForm');
const totalInputs = form ? form.querySelectorAll('input,textarea,select').length : 0;

function updateProgress() {
    if (!form) return;
    let filled = 0;
    form.querySelectorAll('input,textarea,select').forEach(el => {
        if (el.type === 'radio' || el.type === 'checkbox') {
            if (el.checked) filled++;
        } else if (el.value.trim()) {
            filled++;
        }
    });

    let pct = 0;
    if (isSlidesMode && totalSteps > 0) {
        pct = Math.min(100, Math.round(((currentStepIdx + 1) / totalSteps) * 100));
    } else {
        pct = Math.min(100, Math.round((filled / Math.max(totalInputs, 1)) * 100));
    }

    document.getElementById('progressBar').style.width = pct + '%';
    
    const pill = document.getElementById('stepCounterPill');
    if (totalSteps > 1) {
        pill.textContent = `Paso ${currentStepIdx + 1} de ${totalSteps} · ${pct}%`;
    } else {
        pill.textContent = `${pct}% completado`;
    }
}

if (form) {
    form.addEventListener('input', updateProgress);
}
updateProgress();

// Keyboard shortcuts (Enter to start or advance)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const welcome = document.getElementById('welcomeScreen');
        if (welcome && welcome.style.display !== 'none') {
            e.preventDefault();
            startForm();
            return;
        }
        if (isSlidesMode && currentStepIdx < totalSteps - 1) {
            if (e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                goToStep(currentStepIdx + 1);
            }
        }
    }
});

let pendingUploads = 0;

function handleAsyncUpload(input, fieldId, maxSizeMB, maxCount) {
    const maxBytes = maxSizeMB * 1024 * 1024;
    const files = input.files;
    if (files.length > maxCount) {
        alert('No puedes subir más de ' + maxCount + ' archivo(s).');
        input.value = '';
        document.getElementById('fn_' + fieldId).innerHTML = '';
        return;
    }
    let html = '';
    const validFiles = [];
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > maxBytes) {
            alert('El archivo ' + files[i].name + ' excede el límite de ' + maxSizeMB + ' MB.');
            input.value = '';
            document.getElementById('fn_' + fieldId).innerHTML = '';
            return;
        }
        validFiles.push(files[i]);
        const fName = files[i].name;
        const isImg = files[i].type.startsWith('image/');
        const icon = isImg ? 'ph-image' : (fName.toLowerCase().endsWith('.pdf') ? 'ph-file-pdf' : 'ph-file-text');
        
        html += `
        <div style="display:flex; align-items:center; gap:10px; background:var(--app-frame); padding:8px 12px; border-radius:10px; border:1px solid var(--app-border); margin-bottom:6px; text-align:left;">
            <div style="width:32px; height:32px; border-radius:8px; background:var(--app-accent-light); color:var(--app-accent); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                <i class="ph-bold ${icon}"></i>
            </div>
            <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:0.8125rem; font-weight:600; color:var(--app-text);">
                ${fName}<br>
                <span id="status_${fieldId}_${i}" style="color:#f59e0b; font-size:0.72rem;"><i class="ph-bold ph-spinner ph-spin"></i> Subiendo...</span>
            </div>
            <div style="font-size:0.72rem; color:var(--app-text-muted);">${(files[i].size/1024/1024).toFixed(1)} MB</div>
        </div>`;
    }

    document.querySelectorAll(`input[name="temp_file_${fieldId}[]"]`).forEach(el => el.remove());
    document.querySelectorAll(`input[name="temp_name_${fieldId}[]"]`).forEach(el => el.remove());
    document.getElementById('fn_' + fieldId).innerHTML = html;

    if (validFiles.length === 0) return;

    const fd = new FormData();
    for (let i = 0; i < validFiles.length; i++) {
        fd.append('file_' + fieldId + '[]', validFiles[i]);
    }

    pendingUploads++;
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Subiendo archivos...';
    }

    fetch('index.php?module=forms&action=ajax_upload_temp', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            let pathsHtml = '';
            res.files.forEach((f, idx) => {
                pathsHtml += `<input type="hidden" name="temp_file_${fieldId}[]" value="${f.tmp_path}">`;
                pathsHtml += `<input type="hidden" name="temp_name_${fieldId}[]" value="${f.original_name}">`;
                const statusEl = document.getElementById(`status_${fieldId}_${idx}`);
                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.innerHTML = '<i class="ph-bold ph-check-circle"></i> Listo';
                }
            });
            document.getElementById('fn_' + fieldId).insertAdjacentHTML('beforeend', pathsHtml);
        } else {
            alert('Error al subir archivos: ' + (res.error || 'Desconocido'));
            document.getElementById('fn_' + fieldId).innerHTML = '';
            input.value = '';
        }
    })
    .catch(err => {
        alert('Error de conexión al subir archivos');
        document.getElementById('fn_' + fieldId).innerHTML = '';
        input.value = '';
    })
    .finally(() => {
        pendingUploads--;
        if (pendingUploads <= 0 && submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Respuestas';
        }
    });
}

if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (pendingUploads > 0) {
            alert('Por favor espera a que terminen de subirse los archivos.');
            return;
        }

        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Procesando...';
        btn.disabled = true;

        const fd = new FormData(form);
        const dataObj = {};
        for (const [key, val] of fd.entries()) {
            if (key.startsWith('field_') || key.startsWith('temp_file_') || key.startsWith('temp_name_')) {
                if (dataObj[key]) {
                    if (!Array.isArray(dataObj[key])) dataObj[key] = [dataObj[key]];
                    dataObj[key].push(val);
                } else {
                    dataObj[key] = val;
                }
            }
        }

        const submitFd = new FormData();
        submitFd.append('token', '<?php echo htmlspecialchars($token); ?>');
        submitFd.append('data_json', JSON.stringify(dataObj));
        submitFd.append('respondent_name', fd.get('respondent_name') || '');
        submitFd.append('respondent_email', fd.get('respondent_email') || '');

        try {
            const res = await fetch('index.php?module=forms&action=ajax_submit_form', { method: 'POST', body: submitFd });
            const data = await res.json();
            if (data.success) {
                form.style.display = 'none';
                const hero = document.getElementById('formHero');
                if (hero) hero.style.display = 'none';
                const cover = document.getElementById('formCoverBanner');
                if (cover) cover.style.display = 'none';
                document.getElementById('successScreen').style.display = 'flex';
                correlativoCode = data.correlativo || '';
                document.getElementById('successCorrelativo').textContent = correlativoCode;
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('stepCounterPill').textContent = '100%';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                alert(data.error || 'Error al enviar');
                btn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Respuestas';
                btn.disabled = false;
            }
        } catch (err) {
            alert('Error de conexión');
            btn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Respuestas';
            btn.disabled = false;
        }
    });
}

function copyCorrelativo() {
    if (!correlativoCode) return;
    navigator.clipboard.writeText(correlativoCode).then(() => {
        alert('¡Código copiado: ' + correlativoCode + '!');
    });
}
</script>
</body>
</html>
