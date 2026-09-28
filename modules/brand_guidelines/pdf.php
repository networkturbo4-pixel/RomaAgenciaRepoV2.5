<?php
// modules/brand_guidelines/pdf.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'modules/brand_guidelines/helpers.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$database = new Database();
$db = $database->getConnection();

$id = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$id]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($slug)) {
    $stmt = $db->prepare("SELECT * FROM brand_guidelines WHERE slug = ?");
    $stmt->execute([$slug]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $bg = null;
}

if (!$bg) {
    die("Manual de marca no encontrado.");
}

$guidelineId = (int)$bg['id'];
$isLoggedIn = isset($_SESSION['user_id']);
$isUnlocked = !empty($_SESSION['bg_unlocked_' . $guidelineId]);

// Check privacy
if ($bg['is_public'] == 0 && !$isLoggedIn && !$isUnlocked) {
    die("Acceso denegado. Este manual es privado.");
}

// Fetch Global Settings
$stmt_set = $db->query("SELECT setting_key, setting_value FROM settings");
$global_settings = $stmt_set ? $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$agencyName = 'Roma Agencia Creativa';

// Helper to convert image path (local or Drive proxy) to base64 Data URI
function bg_img_to_base64($relativePath) {
    if (empty($relativePath)) return null;

    // 1. Check if it's a Drive Proxy URL
    if (strpos($relativePath, 'drive_proxy.php') !== false) {
        $parts = parse_url($relativePath);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['id'])) {
                require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';
                $drive = new GoogleDriveHelper();
                if ($drive->isConfigured()) {
                    $content = $drive->streamFile($q['id']);
                    if ($content) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($content) ?: 'image/jpeg';
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                }
            }
        }
    }

    // 2. Local File
    $clean = str_replace(['../', '..\\'], '', $relativePath);
    $fullPath = realpath(__DIR__ . '/../../' . ltrim($clean, '/'));
    if ($fullPath && file_exists($fullPath)) {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $mime = 'image/svg+xml';
        } elseif ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } else {
            $mime = 'image/jpeg';
        }
        $data = file_get_contents($fullPath);
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
    return null;
}

// Roma watermark logo
$b64RomaLogo = bg_img_to_base64('uploads/logo_light_1790398960.png');
if (!$b64RomaLogo) {
    $b64RomaLogo = bg_img_to_base64('assets/img/default-logo.png');
}

// Decode Data
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

// Base64 Images
$b64LogoPrimary = bg_img_to_base64($bg['logo_primary']);
$b64LogoDark = bg_img_to_base64($bg['logo_primary_dark']);
$b64LogoSymbol = bg_img_to_base64($bg['logo_symbol']);

$primaryHex = !empty($colors[0]['hex']) ? $colors[0]['hex'] : '#4F46E5';
$secondaryHex = !empty($colors[1]['hex']) ? $colors[1]['hex'] : '#EC4899';

// If print mode requested in browser
$isPrintMode = isset($_GET['print']) && $_GET['print'] == '1';

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Brand Guidelines - <?php echo htmlspecialchars($bg['brand_name']); ?></title>
    <style>
        /* 16:9 Widescreen Presentation (1920x1080 scale at 72dpi = 1440pt x 810pt) */
        @page {
            margin: 0;
            padding: 0;
            size: 1440pt 810pt landscape;
        }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-size: 14pt;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .slide {
            width: 1440pt;
            height: 810pt;
            page-break-after: always;
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
            padding: 50pt 70pt 75pt 70pt;
            background: #ffffff;
        }
        .slide:last-child {
            page-break-after: avoid;
        }

        /* WATERMARK / FOOTER ON EVERY SLIDE */
        .watermark-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 52pt;
            padding: 0 60pt;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            background: #ffffff;
            z-index: 100;
        }

        .wm-left {
            display: flex;
            align-items: center;
            gap: 12pt;
        }
        .wm-logo {
            max-height: 24pt;
            max-width: 90pt;
            object-fit: contain;
        }
        .wm-agency-name {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #64748b;
            text-transform: uppercase;
        }

        .wm-right {
            display: flex;
            align-items: center;
            gap: 15pt;
            font-size: 10pt;
            color: #64748b;
        }
        .wm-socials {
            display: flex;
            align-items: center;
            gap: 10pt;
        }
        .wm-socials strong {
            color: #0f172a;
        }
        .wm-sep {
            color: #cbd5e1;
        }
        .wm-web strong {
            color: #ec4899;
            letter-spacing: 0.5px;
        }

        /* Cover Slide */
        .cover-slide {
            background: #090b10;
            color: #ffffff;
            padding: 80pt 70pt 75pt 70pt;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }
        .cover-slide .watermark-footer {
            background: #090b10;
            border-top-color: rgba(255, 255, 255, 0.1);
        }
        .cover-slide .wm-agency-name {
            color: #94a3b8;
        }
        .cover-slide .wm-right {
            color: #94a3b8;
        }
        .cover-slide .wm-socials strong {
            color: #ffffff;
        }
        .cover-slide .wm-web strong {
            color: #ec4899;
        }

        .cover-kicker {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: <?php echo $secondaryHex; ?>;
            margin-bottom: 12pt;
        }
        .cover-title {
            font-size: 48pt;
            font-weight: 900;
            margin: 0 0 10pt;
            line-height: 1.05;
            letter-spacing: -1.5px;
            color: #ffffff;
        }
        .cover-tagline {
            font-size: 18pt;
            color: #94a3b8;
            max-width: 800pt;
            margin: 0 auto;
        }
        .cover-logo-stage {
            background: #ffffff;
            border-radius: 24pt;
            padding: 40pt 60pt;
            margin: 40pt auto 0;
            max-width: 650pt;
            min-height: 170pt;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 25pt 50pt rgba(0, 0, 0, 0.5);
        }
        .cover-logo-stage img {
            max-height: 140pt;
            max-width: 90%;
            object-fit: contain;
        }

        /* Slide Headers */
        .slide-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16pt;
            margin-bottom: 30pt;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .slide-header-left {
            display: flex;
            flex-direction: column;
            gap: 4pt;
        }
        .slide-kicker {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #ec4899;
        }
        .slide-title {
            font-size: 26pt;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .slide-header-brand {
            font-size: 13pt;
            font-weight: 800;
            color: <?php echo $primaryHex; ?>;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Two Columns Layout */
        .grid-2col {
            display: flex;
            gap: 30pt;
            width: 100%;
        }
        .col-half {
            flex: 1;
        }

        /* Philosophy Cards */
        .phil-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16pt;
            padding: 22pt 26pt;
            margin-bottom: 18pt;
        }
        .phil-card strong {
            font-size: 15pt;
            font-weight: bold;
            display: block;
            margin-bottom: 8pt;
        }
        .phil-card p {
            font-size: 12pt;
            color: #475569;
            margin: 0;
            line-height: 1.55;
        }

        /* Logo Variation Boxes in 16:9 */
        .logo-box-pdf {
            border-radius: 18pt;
            border: 1px solid #e2e8f0;
            padding: 30pt 25pt;
            text-align: center;
            height: 260pt;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
            background: #ffffff;
        }
        .logo-box-pdf.dark {
            background: #0f172a;
            border-color: #1e293b;
            color: #ffffff;
        }
        .logo-img-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 140pt;
        }
        .logo-img-wrap img {
            max-height: 120pt;
            max-width: 85%;
            object-fit: contain;
        }
        .logo-label {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 12pt;
        }
        .logo-desc {
            font-size: 10pt;
            color: #64748b;
            margin-top: 4pt;
        }
        .logo-box-pdf.dark .logo-desc {
            color: #94a3b8;
        }

        /* Color Palette Slide */
        .color-row-pdf {
            display: flex;
            gap: 20pt;
            width: 100%;
        }
        .color-pill-pdf {
            flex: 1;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18pt;
            overflow: hidden;
            box-shadow: 0 4pt 12pt rgba(0,0,0,0.03);
        }
        .color-swatch-top {
            height: 170pt;
            width: 100%;
            position: relative;
        }
        .color-role-badge {
            position: absolute;
            bottom: 12pt;
            left: 12pt;
            background: rgba(0,0,0,0.55);
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            padding: 4pt 10pt;
            border-radius: 6pt;
            text-transform: uppercase;
        }
        .color-details {
            padding: 16pt 18pt;
        }
        .color-title-pdf {
            font-size: 15pt;
            font-weight: bold;
            margin: 0 0 10pt;
            color: #0f172a;
        }
        .color-code-line {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            padding: 4pt 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .color-code-line span.label {
            color: #94a3b8;
            font-weight: bold;
        }
        .color-code-line span.val {
            font-family: monospace;
            font-weight: bold;
            color: #0f172a;
        }

        /* Typography Slide */
        .font-specimen-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18pt;
            padding: 26pt 32pt;
            margin-bottom: 20pt;
        }
        .font-alphabet-large {
            font-size: 28pt;
            font-weight: bold;
            line-height: 1.15;
            color: #0f172a;
            margin: 12pt 0;
            letter-spacing: -0.5px;
        }

        /* Rules Slide */
        .prohibit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15pt;
        }
        .prohibit-card {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 14pt;
            padding: 16pt 20pt;
        }
        .prohibit-card strong {
            color: #e11d48;
            font-size: 13pt;
            display: block;
            margin-bottom: 4pt;
        }
        .prohibit-card p {
            color: #475569;
            font-size: 10.5pt;
            margin: 0;
            line-height: 1.45;
        }

        @media print {
            .no-print { display: none !important; }
            .slide { width: 100vw; height: 100vh; }
        }
    </style>
</head>
<body>

    <?php if ($isPrintMode): ?>
    <div class="no-print" style="background:#090b10; color:white; padding:15px 35px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:9999;">
        <span style="font-weight:bold; font-size:16px;">Manual de Marca (1980 x 1080): <?php echo htmlspecialchars($bg['brand_name']); ?></span>
        <div>
            <button onclick="window.print()" style="background:linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%); color:white; border:none; padding:10px 22px; border-radius:10px; font-weight:bold; font-size:14px; cursor:pointer;">
                Imprimir / Guardar como PDF Widescreen
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================= SLIDE 1: PORTADA WIDESCREEN ================= -->
    <div class="slide cover-slide">
        <div>
            <div class="cover-kicker">Brand Identity Guidelines</div>
            <h1 class="cover-title"><?php echo htmlspecialchars($bg['brand_name']); ?></h1>
            <?php if (!empty($bg['tagline'])): ?>
                <div class="cover-tagline"><?php echo htmlspecialchars($bg['tagline']); ?></div>
            <?php endif; ?>

            <div class="cover-logo-stage">
                <?php if ($b64LogoPrimary): ?>
                    <img src="<?php echo $b64LogoPrimary; ?>" alt="<?php echo htmlspecialchars($bg['brand_name']); ?>">
                <?php else: ?>
                    <h2 style="font-size: 38pt; color: #0f172a; margin: 0; font-weight:900;"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
                <?php endif; ?>
            </div>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 2: FILOSOFÍA & ESENCIA ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">01 / Fundamentos</span>
                <h2 class="slide-title">Filosofía & Esencia de Marca</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div class="grid-2col">
            <div class="col-half">
                <?php if (!empty($bg['description'])): ?>
                <div class="phil-card" style="border-left: 5pt solid <?php echo $primaryHex; ?>;">
                    <strong style="color: <?php echo $primaryHex; ?>;">Propósito & Acerca de la Marca</strong>
                    <p><?php echo nl2br(htmlspecialchars($bg['description'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($bg['tone_of_voice'])): ?>
                <div class="phil-card" style="border-left: 5pt solid #8b5cf6;">
                    <strong style="color: #8b5cf6;">Tono de Voz y Comunicación</strong>
                    <p><?php echo htmlspecialchars($bg['tone_of_voice']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-half">
                <?php if (!empty($bg['mission'])): ?>
                <div class="phil-card" style="border-left: 5pt solid #10b981;">
                    <strong style="color: #10b981;">Misión Corporativa</strong>
                    <p><?php echo nl2br(htmlspecialchars($bg['mission'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($bg['vision'])): ?>
                <div class="phil-card" style="border-left: 5pt solid #3b82f6;">
                    <strong style="color: #3b82f6;">Visión Estratégica</strong>
                    <p><?php echo nl2br(htmlspecialchars($bg['vision'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 3: LOGOTIPO OFICIAL ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">02 / Identidad Visual</span>
                <h2 class="slide-title">Logotipo Principal & Versión Fondo Oscuro</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div class="grid-2col" style="gap: 40pt; margin-top: 15pt;">
            <!-- Versión Claro -->
            <div class="col-half">
                <div class="logo-box-pdf">
                    <div class="logo-img-wrap">
                        <?php if ($b64LogoPrimary): ?>
                            <img src="<?php echo $b64LogoPrimary; ?>" alt="Logo Fondo Claro">
                        <?php else: ?>
                            <h2 style="font-size:24pt; margin:0;"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="logo-label">Logotipo Principal (Fondo Claro)</div>
                        <div class="logo-desc">Aplicación preferencial sobre fondos blancos, neutros y papelería corporativa.</div>
                    </div>
                </div>
            </div>

            <!-- Versión Oscuro -->
            <div class="col-half">
                <div class="logo-box-pdf dark">
                    <div class="logo-img-wrap">
                        <?php if ($b64LogoDark): ?>
                            <img src="<?php echo $b64LogoDark; ?>" alt="Logo Fondo Oscuro">
                        <?php elseif ($b64LogoPrimary): ?>
                            <img src="<?php echo $b64LogoPrimary; ?>" alt="Logo Fondo Oscuro">
                        <?php else: ?>
                            <h2 style="font-size:24pt; margin:0;"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="logo-label">Logotipo en Negativo (Fondo Oscuro)</div>
                        <div class="logo-desc">Aplicación sobre fondos oscuros, fotografías de alto contraste o entornos dark mode.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 4: ISOTIPO & VARIACIONES ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">03 / Adaptaciones</span>
                <h2 class="slide-title">Isotipo, Iconografía y Variaciones</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div style="display:flex; gap:25pt; width:100%; margin-top:10pt;">
            <!-- Isotipo -->
            <?php if ($b64LogoSymbol): ?>
            <div style="flex:1;">
                <div class="logo-box-pdf" style="height:250pt;">
                    <div class="logo-img-wrap">
                        <img src="<?php echo $b64LogoSymbol; ?>" alt="Isotipo Oficial">
                    </div>
                    <div>
                        <div class="logo-label">Isotipo / Símbolo</div>
                        <div class="logo-desc">Elemento síntesis para avatares de redes y sellos.</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Variations & Icons -->
            <?php 
            $shownItems = array_slice($variations, 0, 3);
            foreach ($shownItems as $v):
                $b64Var = bg_img_to_base64($v['url'] ?? '');
                if ($b64Var):
            ?>
            <div style="flex:1;">
                <div class="logo-box-pdf" style="height:250pt;">
                    <div class="logo-img-wrap">
                        <img src="<?php echo $b64Var; ?>" alt="<?php echo htmlspecialchars($v['name']); ?>">
                    </div>
                    <div>
                        <div class="logo-label"><?php echo htmlspecialchars($v['name']); ?></div>
                        <div class="logo-desc"><?php echo htmlspecialchars($v['desc'] ?: 'Variación autorizada.'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 5: SISTEMA CROMÁTICO (COLORES) ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">04 / Colorimetría</span>
                <h2 class="slide-title">Sistema Cromático Corporativo</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div class="color-row-pdf">
            <?php 
            $pdfColors = array_slice($colors, 0, 5);
            foreach ($pdfColors as $c): 
                $hex = htmlspecialchars($c['hex'] ?? '#000000');
            ?>
            <div class="color-pill-pdf">
                <div class="color-swatch-top" style="background: <?php echo $hex; ?>;">
                    <span class="color-role-badge"><?php echo htmlspecialchars($c['role'] ?? 'Primario'); ?></span>
                </div>
                <div class="color-details">
                    <h4 class="color-title-pdf"><?php echo htmlspecialchars($c['name']); ?></h4>
                    <div class="color-code-line">
                        <span class="label">HEX</span>
                        <span class="val"><?php echo $hex; ?></span>
                    </div>
                    <?php if (!empty($c['rgb'])): ?>
                    <div class="color-code-line">
                        <span class="label">RGB</span>
                        <span class="val"><?php echo htmlspecialchars($c['rgb']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['cmyk'])): ?>
                    <div class="color-code-line">
                        <span class="label">CMYK</span>
                        <span class="val"><?php echo htmlspecialchars($c['cmyk']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['pantone'])): ?>
                    <div class="color-code-line">
                        <span class="label">PANTONE</span>
                        <span class="val"><?php echo htmlspecialchars($c['pantone']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 6: TIPOGRAFÍAS ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">05 / Tipografía</span>
                <h2 class="slide-title">Tipografías Oficiales & Escalas</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div style="width: 100%;">
            <?php foreach ($fonts as $f): ?>
            <div class="font-specimen-box">
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <span style="font-size:12pt; font-weight:800; color:#ec4899; text-transform:uppercase; letter-spacing:1.5px;"><?php echo htmlspecialchars($f['role'] ?? 'Fuente'); ?></span>
                    <span style="font-size:11pt; color:#64748b; font-weight:bold;">Pesos: <?php echo htmlspecialchars($f['weights'] ?? 'Regular, Bold'); ?></span>
                </div>
                <div style="font-size:26pt; font-weight:900; color:#0f172a; margin-top:4pt;"><?php echo htmlspecialchars($f['name'] ?? 'Inter'); ?></div>
                <div class="font-alphabet-large">
                    Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj Kk Ll Mm Nn Ññ Oo Pp Qq Rr Ss Tt Uu Vv Ww Xx Yy Zz 0123456789
                </div>
                <?php if (!empty($f['usage'])): ?>
                <div style="font-size:11pt; color:#475569; margin-top:8pt;">
                    <strong>Aplicación:</strong> <?php echo htmlspecialchars($f['usage']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 7: ÁREA DE SEGURIDAD & USOS PROHIBIDOS ================= -->
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">06 / Normativas</span>
                <h2 class="slide-title">Área de Seguridad & Usos Prohibidos</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div class="grid-2col" style="margin-bottom:20pt;">
            <div class="col-half">
                <div class="phil-card" style="border-left: 5pt solid #10b981; margin-bottom:0;">
                    <strong style="color:#10b981; font-size:14pt;">Área de Seguridad Perimetral</strong>
                    <p style="font-size:11pt;"><?php echo !empty($bg['safe_zone_rules']) ? nl2br(htmlspecialchars($bg['safe_zone_rules'])) : 'El logotipo debe disponer de un espacio de respeto perimetral libre de interferencias gráficas o textos.'; ?></p>
                </div>
            </div>

            <div class="col-half">
                <div class="phil-card" style="border-left: 5pt solid #3b82f6; margin-bottom:0;">
                    <strong style="color:#3b82f6; font-size:14pt;">Tamaño Mínimo Recomendado</strong>
                    <p style="font-size:11pt;"><?php echo !empty($bg['min_size_rules']) ? nl2br(htmlspecialchars($bg['min_size_rules'])) : 'Digital: 60px de ancho. Impreso: 25mm de ancho. En tamaños menores usar únicamente el isotipo.'; ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($incorrectUses)): ?>
        <h3 style="font-size:15pt; color:#e11d48; margin: 15pt 0 10pt; font-weight:bold;">Usos No Permitidos</h3>
        <div class="prohibit-grid">
            <?php foreach ($incorrectUses as $u): ?>
            <div class="prohibit-card">
                <strong>✕ <?php echo htmlspecialchars($u['title']); ?></strong>
                <p><?php echo htmlspecialchars($u['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SLIDE 8: APLICACIONES & MOCKUPS ================= -->
    <?php if (!empty($applications)): ?>
    <div class="slide">
        <div class="slide-header">
            <div class="slide-header-left">
                <span class="slide-kicker">07 / Aplicaciones</span>
                <h2 class="slide-title">Universo Visual & Aplicaciones</h2>
            </div>
            <span class="slide-header-brand"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
        </div>

        <div style="display:flex; gap:20pt; width:100%;">
            <?php 
            $shownApps = array_slice($applications, 0, 4);
            foreach ($shownApps as $app): 
                $b64App = bg_img_to_base64($app['image_url'] ?? '');
                if ($b64App):
            ?>
            <div style="flex:1; border:1px solid #e2e8f0; border-radius:16pt; overflow:hidden; background:#ffffff;">
                <img src="<?php echo $b64App; ?>" style="width:100%; height:200pt; object-fit:cover;">
                <div style="padding:12pt 16pt;">
                    <div style="font-weight:bold; font-size:13pt; color:#0f172a;"><?php echo htmlspecialchars($app['title']); ?></div>
                    <?php if (!empty($app['desc'])): ?>
                    <div style="font-size:10pt; color:#64748b; margin-top:3pt;"><?php echo htmlspecialchars($app['desc']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-footer">
            <div class="wm-left">
                <?php if ($b64RomaLogo): ?>
                    <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo" alt="Roma Agencia">
                <?php endif; ?>
                <span class="wm-agency-name">ROMA AGENCIA CREATIVA</span>
            </div>
            <div class="wm-right">
                <div class="wm-socials">
                    <span>IG / FB / TT: <strong>@romaagencia</strong></span>
                    <span class="wm-sep">•</span>
                    <span class="wm-web"><strong>romaagencia.com</strong></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

// If print view requested (rendered in browser for instant print or review)
if ($isPrintMode) {
    echo $html;
    exit();
}

// Generate Widescreen 16:9 PDF via Dompdf
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    // 1440pt x 810pt = 1920x1080 (16:9 widescreen presentation)
    $dompdf->setPaper([0, 0, 1440, 810], 'landscape');
    $dompdf->render();

    $cleanFilename = "Manual_de_Marca_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bg['brand_name']) . "_1980x1080.pdf";
    $dompdf->stream($cleanFilename, ["Attachment" => true]);
    exit();
} catch (Exception $e) {
    echo "<script>window.location.href = 'index.php?module=brand_guidelines&action=pdf&id={$guidelineId}&print=1';</script>";
    exit();
}
