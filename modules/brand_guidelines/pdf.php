<?php
// modules/brand_guidelines/pdf.php
// 1980x1080 (16:9 Widescreen Presentation PDF Generator) - 100% LIGHT MODE (Cover with Primary/Secondary Gradient)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

@ini_set('memory_limit', '512M');
@set_time_limit(180);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$database = new Database();
$db = $database->getConnection();

$id = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if ($id > 0) {
    $stmt = $db->prepare("SELECT bg.*, c.name as client_name FROM brand_guidelines bg LEFT JOIN clients c ON bg.client_id = c.id WHERE bg.id = ?");
    $stmt->execute([$id]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($slug)) {
    $stmt = $db->prepare("SELECT bg.*, c.name as client_name FROM brand_guidelines bg LEFT JOIN clients c ON bg.client_id = c.id WHERE bg.slug = ?");
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
    die("Acceso denegado. Este manual de marca es privado.");
}

// Fetch System Settings
$sysSettings = bg_get_system_settings($db);
$sysPrimary = !empty($sysSettings['primary_color']) ? $sysSettings['primary_color'] : '#262ecf';
$sysSecondary = !empty($sysSettings['secondary_color']) ? $sysSettings['secondary_color'] : '#081116';
$sysSiteName = !empty($sysSettings['site_name']) ? trim($sysSettings['site_name']) : 'Roma Agencia Creativa';

// Roma watermark logo in base64 (Light Mode)
$romaLogoLight = !empty($sysSettings['logo_light']) ? $sysSettings['logo_light'] : 'uploads/logo_light_1790398960.png';
$b64RomaLogo = bg_img_to_base64($romaLogoLight) ?: bg_img_to_base64('assets/img/default-logo.png');

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

$values = !empty($bg['values_json']) ? json_decode($bg['values_json'], true) : [];
if (!is_array($values)) $values = [];

// Base64 Images for Main Logos
$b64LogoPrimary = bg_img_to_base64($bg['logo_primary']);
$b64LogoDark = bg_img_to_base64($bg['logo_primary_dark']);
if (!$b64LogoDark) {
    $b64LogoDark = $b64LogoPrimary;
}
$b64LogoSymbol = bg_img_to_base64($bg['logo_symbol']);
if (!$b64LogoSymbol) {
    $b64LogoSymbol = $b64LogoPrimary;
}

$primaryHex = !empty($colors[0]['hex']) ? $colors[0]['hex'] : $sysPrimary;
$secondaryHex = !empty($colors[1]['hex']) ? $colors[1]['hex'] : '#ec4899';

// Generate SVG Gradient for Cover Slide (Primary + Secondary)
$svgCover = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540" viewBox="0 0 960 540">
  <defs>
    <linearGradient id="covGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:'.$sysPrimary.';stop-opacity:1" />
      <stop offset="55%" style="stop-color:#111936;stop-opacity:1" />
      <stop offset="100%" style="stop-color:'.$sysSecondary.';stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="960" height="540" fill="url(#covGrad)" />
</svg>';
$b64CoverBg = 'data:image/svg+xml;base64,' . base64_encode($svgCover);

// Browser print mode
$isPrintMode = isset($_GET['print']) && $_GET['print'] == '1';

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($bg['brand_name']); ?> - Brand Guidelines (1980x1080)</title>
    <style>
        /* 16:9 Widescreen Presentation (960pt x 540pt landscape) */
        @page {
            margin: 0;
            padding: 0;
            size: 960pt 540pt landscape;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-size: 10.5pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* EXACT 16:9 SLIDE CONTAINER - NO ACCIDENTAL OVERFLOW */
        .slide {
            width: 960pt;
            height: 538pt;
            max-height: 538pt;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .slide-break {
            page-break-after: always;
        }

        /* Slide Content Area (leaves 38pt for watermark footer) */
        .slide-inner {
            width: 960pt;
            height: 462pt;
            max-height: 462pt;
            padding: 18pt 40pt 0 40pt;
            overflow: hidden;
            position: relative;
            z-index: 2;
        }

        /* Slide Header Table */
        .slide-header-tbl {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2pt solid #f1f5f9;
            padding-bottom: 6pt;
            margin-bottom: 12pt;
        }
        .sh-kicker {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2pt;
            color: <?php echo $sysPrimary; ?>;
            margin-bottom: 2pt;
        }
        .sh-title {
            font-size: 18pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.4pt;
        }
        .sh-brand {
            font-size: 8.5pt;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.2pt;
            text-align: right;
            vertical-align: bottom;
            padding-bottom: 3pt;
        }

        /* Watermark Footer Table (Fixed on every slide) */
        .wm-footer-table {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 36pt;
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-top: 1pt solid #e2e8f0;
            z-index: 10;
        }
        .wm-footer-table td {
            vertical-align: middle;
            padding: 0 40pt;
        }
        .wm-td-left {
            text-align: left;
        }
        .wm-td-right {
            text-align: right;
        }
        .wm-logo-img {
            max-height: 16pt;
            max-width: 80pt;
            vertical-align: middle;
            margin-right: 8pt;
        }
        .wm-agency-text {
            font-size: 8pt;
            font-weight: 800;
            letter-spacing: 1.2pt;
            color: #64748b;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .wm-social-text {
            font-size: 8pt;
            color: #64748b;
            vertical-align: middle;
        }
        .wm-social-bold {
            color: #0f172a;
            font-weight: 800;
        }
        .wm-web-link {
            color: <?php echo $sysPrimary; ?>;
            font-weight: 800;
            letter-spacing: 0.3pt;
        }

        /* COVER SLIDE GRADIENT */
        .cover-slide {
            background-color: #0d1224;
        }
        .cover-bg-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 960pt;
            height: 538pt;
            z-index: 1;
        }
        .cover-inner {
            width: 960pt;
            height: 450pt;
            max-height: 450pt;
            padding: 16pt 45pt 0 45pt;
            position: relative;
            z-index: 2;
            color: #ffffff;
            overflow: hidden;
        }
        .cover-wm-footer {
            background: transparent;
            border-top: 1pt solid rgba(255, 255, 255, 0.15);
        }
        .cover-wm-footer .wm-agency-text {
            color: rgba(255, 255, 255, 0.7);
        }
        .cover-wm-footer .wm-social-text {
            color: rgba(255, 255, 255, 0.7);
        }
        .cover-wm-footer .wm-social-bold {
            color: #ffffff;
        }
        .cover-wm-footer .wm-web-link {
            color: #60a5fa;
        }

        /* TABLES & GRIDS */
        .tbl-2col { width: 100%; border-collapse: separate; border-spacing: 14pt 0; }
        .tbl-2col > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }

        .tbl-3col { width: 100%; border-collapse: separate; border-spacing: 12pt 0; }
        .tbl-3col > tbody > tr > td { width: 33.333%; vertical-align: top; padding: 0; }

        .tbl-4col { width: 100%; border-collapse: separate; border-spacing: 10pt 0; }
        .tbl-4col > tbody > tr > td { width: 25%; vertical-align: top; padding: 0; }

        .tbl-5col { width: 100%; border-collapse: separate; border-spacing: 8pt 0; }
        .tbl-5col > tbody > tr > td { width: 20%; vertical-align: top; padding: 0; }

        /* CARDS (100% LIGHT MODE) */
        .card-box {
            background: #f8fafc;
            border: 1pt solid #e2e8f0;
            border-radius: 8pt;
            padding: 12pt 14pt;
        }
        .card-white {
            background: #ffffff;
            border: 1pt solid #e2e8f0;
            border-radius: 8pt;
            padding: 12pt 14pt;
        }
        .card-dark {
            background: #0f172a;
            border: 1pt solid #1e293b;
            border-radius: 8pt;
            padding: 12pt 14pt;
            color: #ffffff;
        }

        /* TYPOGRAPHY IN PDF */
        .card-title {
            font-size: 11pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6pt 0;
        }
        .card-desc {
            font-size: 9pt;
            color: #475569;
            line-height: 1.45;
            margin: 0;
        }
        .tag-pill {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            font-size: 7.5pt;
            font-weight: 700;
            padding: 2.5pt 6.5pt;
            border-radius: 12pt;
            margin: 2pt 3pt 2pt 0;
        }
        .tag-pill-primary {
            background: #eff6ff;
            color: <?php echo $sysPrimary; ?>;
            border: 1pt solid #bfdbfe;
        }

        /* BLUEPRINT GRID */
        .blueprint-card {
            background: #f8fafc;
            border: 1pt dashed #0284c7;
            border-radius: 8pt;
            padding: 18pt;
            text-align: center;
        }
        .blueprint-marker {
            display: inline-block;
            background: #0284c7;
            color: #ffffff;
            font-weight: 800;
            font-size: 8pt;
            padding: 1pt 5pt;
            border-radius: 3pt;
        }

        /* USOS INCORRECTOS */
        .abuse-box-pdf {
            background: #fff5f5;
            border: 1pt solid #fecaca;
            border-radius: 8pt;
            padding: 10pt;
            text-align: center;
        }
        .abuse-badge-pdf {
            display: inline-block;
            background: #dc2626;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: 800;
            padding: 2pt 6pt;
            border-radius: 3pt;
            letter-spacing: 0.5pt;
            margin-bottom: 4pt;
        }
        .abuse-stage-pdf {
            height: 62pt;
            background: #ffffff;
            border: 1pt dashed #fca5a5;
            border-radius: 6pt;
            margin-bottom: 6pt;
        }

        /* COLOR CARD SWATCH */
        .color-card-pdf {
            background: #ffffff;
            border: 1pt solid #e2e8f0;
            border-radius: 8pt;
            overflow: hidden;
        }
        .color-swatch-box {
            height: 70pt;
            width: 100%;
            position: relative;
        }
        .color-badge {
            position: absolute;
            top: 5pt;
            left: 5pt;
            background: rgba(15, 23, 42, 0.7);
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: 800;
            padding: 1.5pt 4.5pt;
            border-radius: 3pt;
            text-transform: uppercase;
        }
        .color-tints-tbl {
            width: 100%;
            height: 10pt;
            border-collapse: collapse;
        }
        .color-tints-tbl td {
            height: 10pt;
            padding: 0;
        }
        .color-info-padding {
            padding: 8pt;
        }
        .color-name-title {
            font-size: 9pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 5pt 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .color-spec-row {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .color-spec-label {
            color: #64748b;
            font-weight: 800;
            width: 32pt;
            padding: 1pt 0;
        }
        .color-spec-val {
            color: #0f172a;
            font-family: monospace;
            font-weight: 700;
            text-align: right;
            padding: 1pt 0;
        }
    </style>
</head>
<body>

    <!-- ================= SLIDE 1: PORTADA WIDESCREEN CON DEGRADADO CORPORATIVO ================= -->
    <div class="slide slide-break cover-slide">
        <img src="<?php echo $b64CoverBg; ?>" class="cover-bg-img" alt="Background">
        <div class="cover-inner">
            <table style="width: 100%; height: 410pt; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: middle; text-align: center;">
                        <div style="font-size: 10pt; font-weight: 800; letter-spacing: 3pt; text-transform: uppercase; color: #93c5fd; margin-bottom: 10pt;">
                            MANUAL DE IDENTIDAD VISUAL • 1980 X 1080
                        </div>
                        <h1 style="font-size: 38pt; font-weight: 900; margin: 0 0 8pt 0; letter-spacing: -1pt; color: #ffffff;">
                            <?php echo htmlspecialchars($bg['brand_name']); ?>
                        </h1>
                        <?php if (!empty($bg['tagline'])): ?>
                            <p style="font-size: 13pt; color: #cbd5e1; max-width: 600pt; margin: 0 auto 20pt auto; line-height: 1.4;">
                                "<?php echo htmlspecialchars($bg['tagline']); ?>"
                            </p>
                        <?php else: ?>
                            <p style="font-size: 13pt; color: #cbd5e1; max-width: 600pt; margin: 0 auto 20pt auto; line-height: 1.4;">
                                Sistema integral de marca, arquitectura visual y especificaciones técnicas oficiales.
                            </p>
                        <?php endif; ?>

                        <!-- Stage Card with Dark Logo Version -->
                        <div style="background: rgba(255,255,255,0.08); border: 1pt solid rgba(255,255,255,0.22); border-radius: 12pt; padding: 18pt 36pt; display: inline-block; margin-bottom: 20pt;">
                            <?php if ($b64LogoDark): ?>
                                <img src="<?php echo $b64LogoDark; ?>" style="max-height: 60pt; max-width: 320pt; vertical-align: middle;" alt="<?php echo htmlspecialchars($bg['brand_name']); ?>">
                            <?php else: ?>
                                <span style="font-size: 26pt; font-weight: 900; color: #ffffff;"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Metadata Badges -->
                        <table style="width: 480pt; margin: 0 auto; border-collapse: collapse; font-size: 8.5pt; color: #cbd5e1;">
                            <tr>
                                <td style="text-align: left;"><strong style="color:#ffffff;">CLIENTE:</strong> <?php echo htmlspecialchars($bg['client_name'] ?? 'Uso Institucional'); ?></td>
                                <td style="text-align: center;"><strong style="color:#ffffff;">VERSIÓN:</strong> 2.5 Pro</td>
                                <td style="text-align: right;"><strong style="color:#ffffff;">FECHA:</strong> <?php echo date('d/m/Y'); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table cover-wm-footer">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: rgba(255,255,255,0.4); margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 2: ADN CORPORATIVO & FILOSOFÍA (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">01 / ADN CORPORATIVO</div>
                        <h2 class="sh-title">Propósito y Filosofía de Marca</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 02</td>
                </tr>
            </table>

            <table class="tbl-2col" style="margin-top: 6pt;">
                <tr>
                    <td>
                        <!-- Misión -->
                        <div class="card-box" style="margin-bottom: 12pt; height: 165pt;">
                            <div style="font-size: 9pt; font-weight: 800; color: #2563eb; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Misión Corporativa ]
                            </div>
                            <h3 class="card-title">Razón de Ser y Compromiso</h3>
                            <p class="card-desc">
                                <?php echo !empty($bg['mission']) ? nl2br(htmlspecialchars($bg['mission'])) : 'Desarrollar soluciones de alto impacto estratégico y creativo, conectando marcas con audiencias a través de experiencias significativas, consistentes y vanguardistas.'; ?>
                            </p>
                        </div>
                        <!-- Valores -->
                        <div class="card-box" style="height: 165pt;">
                            <div style="font-size: 9pt; font-weight: 800; color: #ec4899; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Pilares Éticos ]
                            </div>
                            <h3 class="card-title">Valores Fundamentales</h3>
                            <p class="card-desc" style="margin-bottom: 8pt;">Principios normativos que rigen cada interacción de la marca:</p>
                            <div>
                                <?php 
                                $valList = !empty($values) ? $values : ['Innovación Continua', 'Integridad Radical', 'Excelencia Operativa', 'Cercanía Humana', 'Impacto Medible'];
                                foreach ($valList as $v):
                                    $vTxt = is_array($v) ? ($v['name'] ?? '') : $v;
                                    if ($vTxt):
                                ?>
                                    <span class="tag-pill tag-pill-primary"><?php echo htmlspecialchars($vTxt); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <!-- Visión -->
                        <div class="card-box" style="margin-bottom: 12pt; height: 165pt;">
                            <div style="font-size: 9pt; font-weight: 800; color: #8b5cf6; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Visión Estratégica ]
                            </div>
                            <h3 class="card-title">Horizonte de Crecimiento</h3>
                            <p class="card-desc">
                                <?php echo !empty($bg['vision']) ? nl2br(htmlspecialchars($bg['vision'])) : 'Ser reconocidos como el estándar de excelencia e innovación en el ecosistema, impulsando la transformación integral y aportando valor continuo con proyección internacional.'; ?>
                            </p>
                        </div>
                        <!-- Tono de Voz -->
                        <div class="card-box" style="height: 165pt;">
                            <div style="font-size: 9pt; font-weight: 800; color: #10b981; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Personalidad de Comunicación ]
                            </div>
                            <h3 class="card-title">Tono de Voz Oficial</h3>
                            <p class="card-desc" style="margin-bottom: 8pt;">
                                <?php echo !empty($bg['tone_of_voice']) ? htmlspecialchars($bg['tone_of_voice']) : 'La comunicación debe ser en todo momento profesional, clara, empática y orientada a soluciones estratégicas.'; ?>
                            </p>
                            <div>
                                <span class="tag-pill">Profesional</span>
                                <span class="tag-pill">Confiable</span>
                                <span class="tag-pill">Innovador</span>
                                <span class="tag-pill">Directo</span>
                                <span class="tag-pill">Vanguardista</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 3: ARQUITECTURA VISUAL & LOGOTIPOS (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">02 / ARQUITECTURA VISUAL</div>
                        <h2 class="sh-title">Logotipo Principal y Versiones Oficiales</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 03</td>
                </tr>
            </table>

            <table class="tbl-2col" style="margin-top: 6pt;">
                <tr>
                    <!-- Logotipo Principal -->
                    <td style="width: 58%;">
                        <div class="card-white" style="height: 350pt; text-align: center;">
                            <div style="text-align: left; font-size: 8.5pt; font-weight: 800; color: <?php echo $sysPrimary; ?>; text-transform: uppercase; margin-bottom: 6pt;">
                                [ Logotipo Oficial en Positivo ]
                            </div>
                            <div style="height: 250pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center; padding: 20pt;">
                                    <?php if ($b64LogoPrimary): ?>
                                        <img src="<?php echo $b64LogoPrimary; ?>" style="max-height: 120pt; max-width: 380pt; vertical-align: middle;" alt="Logo Principal">
                                    <?php else: ?>
                                        <span style="font-size: 32pt; font-weight: 900; color: #0f172a;"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: 10pt;">
                                <h4 style="font-size: 10pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;">Aplicación Primaria para Fondos Claros</h4>
                                <p style="font-size: 8.5pt; color: #64748b; margin: 0;">Configuración estándar para toda comunicación institucional impresa y digital sobre fondo blanco o neutro.</p>
                            </div>
                        </div>
                    </td>
                    <!-- Variantes Negativo y Símbolo -->
                    <td style="width: 42%;">
                        <!-- Versión Fondo Oscuro -->
                        <div class="card-dark" style="height: 168pt; margin-bottom: 14pt; text-align: center;">
                            <div style="text-align: left; font-size: 8pt; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Versión Negativa / Fondos Oscuros ]
                            </div>
                            <div style="height: 100pt; display: table; width: 100%;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center;">
                                    <?php if ($b64LogoDark): ?>
                                        <img src="<?php echo $b64LogoDark; ?>" style="max-height: 55pt; max-width: 240pt; vertical-align: middle;" alt="Logo Oscuro">
                                    <?php else: ?>
                                        <span style="font-size: 18pt; font-weight: 900; color: #ffffff;"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: left; font-size: 8pt; color: #94a3b8;">Uso obligatorio sobre fondos oscuros o de alta absorción lumínica.</div>
                        </div>
                        <!-- Símbolo / Isotipo -->
                        <div class="card-white" style="height: 168pt; text-align: center;">
                            <div style="text-align: left; font-size: 8pt; font-weight: 800; color: <?php echo $sysPrimary; ?>; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Isotipo / Símbolo Independiente ]
                            </div>
                            <div style="height: 100pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center;">
                                    <?php if ($b64LogoSymbol): ?>
                                        <img src="<?php echo $b64LogoSymbol; ?>" style="max-height: 55pt; max-width: 120pt; vertical-align: middle;" alt="Isotipo">
                                    <?php else: ?>
                                        <span style="font-size: 24pt; font-weight: 900; color: #0f172a;"><?php echo substr($bg['brand_name'], 0, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: left; font-size: 8pt; color: #64748b; margin-top: 4pt;">Identificador condensado para avatares, favicon y aplicaciones reducidas.</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 4: VARIACIONES & SISTEMA DE ICONOS (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">03 / VARIACIONES DE MARCA</div>
                        <h2 class="sh-title">Sistemas Alternativos e Iconografía Oficial</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 04</td>
                </tr>
            </table>

            <!-- Row 1: Variations -->
            <div style="font-size: 9pt; font-weight: 800; color: <?php echo $sysPrimary; ?>; text-transform: uppercase; margin-bottom: 6pt;">
                [ Variaciones Oficiales de Disposición ]
            </div>
            <table class="tbl-3col" style="margin-bottom: 14pt;">
                <tr>
                    <?php 
                    $sampleVars = !empty($variations) ? array_slice($variations, 0, 3) : [
                        ['name' => 'Versión Horizontal', 'desc' => 'Para cabeceras web, banners y papelería alargada.', 'url' => ''],
                        ['name' => 'Versión Compacta', 'desc' => 'Disposición vertical centrada para etiquetas y packaging.', 'url' => ''],
                        ['name' => 'Versión Monocromática', 'desc' => 'Tinta plana 100% negro para sellos, grabados o faxes.', 'url' => '']
                    ];
                    while (count($sampleVars) < 3) {
                        $sampleVars[] = ['name' => 'Variación Auxiliar', 'desc' => 'Uso reservado para piezas editoriales especiales.', 'url' => ''];
                    }
                    foreach ($sampleVars as $v):
                        $vImg = !empty($v['url']) ? bg_img_to_base64($v['url']) : $b64LogoPrimary;
                    ?>
                    <td>
                        <div class="card-white" style="height: 150pt; text-align: center;">
                            <div style="height: 85pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%; margin-bottom: 6pt;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center; padding: 6pt;">
                                    <?php if ($vImg): ?>
                                        <img src="<?php echo $vImg; ?>" style="max-height: 48pt; max-width: 180pt; vertical-align: middle;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h4 style="font-size: 9.5pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;"><?php echo htmlspecialchars($v['name'] ?? 'Variación Oficial'); ?></h4>
                            <p style="font-size: 8pt; color: #64748b; margin: 0; line-height: 1.35;"><?php echo htmlspecialchars($v['desc'] ?? 'Alineación oficial para formatos específicos.'); ?></p>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>

            <!-- Row 2: Icons System -->
            <div style="font-size: 9pt; font-weight: 800; color: #ec4899; text-transform: uppercase; margin-bottom: 6pt;">
                [ Ecosistema de Iconos & Submarcas ]
            </div>
            <table class="tbl-4col">
                <tr>
                    <?php 
                    $sampleIcons = !empty($icons) ? array_slice($icons, 0, 4) : [
                        ['name' => 'App Icon / iOS', 'desc' => 'Esquinas redondeadas para sistemas móviles.'],
                        ['name' => 'Favicon Web', 'desc' => '32x32 px optimizado para pestañas de navegador.'],
                        ['name' => 'Submarca Reducida', 'desc' => 'Sello circular para redes sociales y perfiles.'],
                        ['name' => 'Favicon Dark', 'desc' => 'Versión contrastada para modo oscuro del navegador.']
                    ];
                    while (count($sampleIcons) < 4) {
                        $sampleIcons[] = ['name' => 'Icono Complementario', 'desc' => 'Optimizado para señalética digital.'];
                    }
                    foreach ($sampleIcons as $ico):
                        $icoImg = !empty($ico['url']) ? bg_img_to_base64($ico['url']) : $b64LogoSymbol;
                    ?>
                    <td>
                        <div class="card-white" style="height: 135pt; text-align: center;">
                            <div style="height: 65pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%; margin-bottom: 6pt;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center;">
                                    <?php if ($icoImg): ?>
                                        <img src="<?php echo $icoImg; ?>" style="max-height: 40pt; max-width: 60pt; vertical-align: middle;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h4 style="font-size: 8.5pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;"><?php echo htmlspecialchars($ico['name'] ?? 'Icono'); ?></h4>
                            <p style="font-size: 7.5pt; color: #64748b; margin: 0; line-height: 1.3;"><?php echo htmlspecialchars($ico['desc'] ?? 'Uso técnico en soportes reducidos.'); ?></p>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 5: NORMAS TÉCNICAS & ÁREA DE RESERVA (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">04 / NORMAS TÉCNICAS</div>
                        <h2 class="sh-title">Área de Protección y Reducción Mínima</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 05</td>
                </tr>
            </table>

            <table class="tbl-2col" style="margin-top: 6pt;">
                <tr>
                    <!-- Blueprint Clearspace Grid -->
                    <td style="width: 56%;">
                        <div class="card-white" style="height: 350pt; text-align: center;">
                            <div style="text-align: left; font-size: 8.5pt; font-weight: 800; color: #0284c7; text-transform: uppercase; margin-bottom: 6pt;">
                                [ Blueprint Técnico de Área de Reserva ]
                            </div>
                            <!-- Blueprint Box -->
                            <div class="blueprint-card" style="height: 230pt; margin-bottom: 10pt; position: relative;">
                                <div style="display: table; width: 100%; height: 100%;">
                                    <div style="display: table-cell; vertical-align: middle; text-align: center;">
                                        <div style="margin-bottom: 8pt;"><span class="blueprint-marker">X</span></div>
                                        <div>
                                            <span class="blueprint-marker" style="margin-right: 12pt;">X</span>
                                            <div style="display: inline-block; padding: 16pt 24pt; border: 1.5pt dashed #0284c7; background: #ffffff; border-radius: 6pt;">
                                                <?php if ($b64LogoPrimary): ?>
                                                    <img src="<?php echo $b64LogoPrimary; ?>" style="max-height: 65pt; max-width: 240pt; vertical-align: middle;">
                                                <?php else: ?>
                                                    <span style="font-size: 22pt; font-weight: 900; color: #0f172a;"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="blueprint-marker" style="margin-left: 12pt;">X</span>
                                        </div>
                                        <div style="margin-top: 8pt;"><span class="blueprint-marker">X</span></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; font-size: 8.5pt; color: #475569; line-height: 1.4;">
                                <strong>Regla X:</strong> La cota <span class="blueprint-marker" style="padding: 0 4pt; font-size: 7.5pt;">X</span> equivale a la altura del símbolo o isotipo. Ningún elemento gráfico, tipográfico o corte de guillotina debe invadir este perímetro de protección visual.
                            </div>
                        </div>
                    </td>
                    <!-- Minimum Sizes & Proportions -->
                    <td style="width: 44%;">
                        <!-- Tamaños Mínimos -->
                        <div class="card-box" style="height: 168pt; margin-bottom: 14pt;">
                            <div style="font-size: 8pt; font-weight: 800; color: <?php echo $sysPrimary; ?>; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Tamaños Mínimos Permitidos ]
                            </div>
                            <h3 class="card-title" style="margin-bottom: 8pt;">Legibilidad en Reducción</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                                <tr style="border-bottom: 1pt solid #e2e8f0;">
                                    <td style="padding: 6pt 0; color: #64748b;"><strong>Medios Impresos (Offset / Láser):</strong></td>
                                    <td style="padding: 6pt 0; text-align: right; font-weight: 800; color: #0f172a;">25 mm ancho</td>
                                </tr>
                                <tr style="border-bottom: 1pt solid #e2e8f0;">
                                    <td style="padding: 6pt 0; color: #64748b;"><strong>Medios Digitales (Pantallas):</strong></td>
                                    <td style="padding: 6pt 0; text-align: right; font-weight: 800; color: #0f172a;">90 px ancho</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6pt 0; color: #64748b;"><strong>Favicon / App Icon:</strong></td>
                                    <td style="padding: 6pt 0; text-align: right; font-weight: 800; color: #0f172a;">32 x 32 px</td>
                                </tr>
                            </table>
                            <p style="font-size: 7.5pt; color: #64748b; margin-top: 6pt;">Por debajo de estas cotas, debe emplearse obligatoriamente el Isotipo aislado.</p>
                        </div>
                        <!-- Invariabilidad -->
                        <div class="card-box" style="height: 168pt;">
                            <div style="font-size: 8pt; font-weight: 800; color: #ec4899; text-transform: uppercase; margin-bottom: 4pt;">
                                [ Bloqueo de Proporciones ]
                            </div>
                            <h3 class="card-title" style="margin-bottom: 6pt;">Geometría Invariable</h3>
                            <p class="card-desc" style="margin-bottom: 8pt;">
                                La relación entre el símbolo y el logotipo tipográfico está calibrada matemáticamente. Queda estrictamente prohibido alterar sus proporciones o distancias relativas.
                            </p>
                            <div style="background: #ffffff; border: 1pt solid #e2e8f0; border-radius: 4pt; padding: 6pt 10pt; font-size: 8pt; color: #334155;">
                                <strong style="color: #0f172a;">Constante:</strong> Relación de aspecto 1:1 bloqueada durante cualquier escala o reescalado de archivo vectorial.
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 6: USOS NO PERMITIDOS (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">05 / RESTRICCIONES NORMATIVAS</div>
                        <h2 class="sh-title">Usos Incorrectos de la Marca</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 06</td>
                </tr>
            </table>

            <table class="tbl-2col" style="margin-top: 6pt;">
                <tr>
                    <!-- Card 1: Distorsión -->
                    <td style="padding-bottom: 12pt;">
                        <div class="abuse-box-pdf">
                            <table class="abuse-stage-pdf" style="width: 100%;">
                                <tr>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($b64LogoPrimary): ?>
                                            <img src="<?php echo $b64LogoPrimary; ?>" style="height: 25pt; width: 220pt; vertical-align: middle;">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <span class="abuse-badge-pdf">✕ PROHIBIDO</span>
                            <h4 style="font-size: 9pt; font-weight: 800; color: #991b1b; margin: 2pt 0;">No Deformar ni Estirar</h4>
                            <p style="font-size: 7.5pt; color: #7f1d1d; margin: 0;">No alterar la escala horizontal ni vertical bajo ningún concepto.</p>
                        </div>
                    </td>
                    <!-- Card 2: Cambio de Color -->
                    <td style="padding-bottom: 12pt;">
                        <div class="abuse-box-pdf">
                            <table class="abuse-stage-pdf" style="width: 100%;">
                                <tr>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($b64LogoPrimary): ?>
                                            <div style="background: #22c55e; border-radius: 4pt; padding: 4pt 12pt; display: inline-block;">
                                                <img src="<?php echo $b64LogoDark; ?>" style="max-height: 38pt; vertical-align: middle;">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <span class="abuse-badge-pdf">✕ PROHIBIDO</span>
                            <h4 style="font-size: 9pt; font-weight: 800; color: #991b1b; margin: 2pt 0;">No Alterar la Paleta Oficial</h4>
                            <p style="font-size: 7.5pt; color: #7f1d1d; margin: 0;">No aplicar tintes, degradados o colores no aprobados en la guía.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <!-- Card 3: Fondos sin Contraste -->
                    <td>
                        <div class="abuse-box-pdf">
                            <table class="abuse-stage-pdf" style="width: 100%; background: #fbbf24;">
                                <tr>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($b64LogoPrimary): ?>
                                            <img src="<?php echo $b64LogoPrimary; ?>" style="max-height: 40pt; opacity: 0.3; vertical-align: middle;">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <span class="abuse-badge-pdf">✕ PROHIBIDO</span>
                            <h4 style="font-size: 9pt; font-weight: 800; color: #991b1b; margin: 2pt 0;">No Usar sin Contraste Suficiente</h4>
                            <p style="font-size: 7.5pt; color: #7f1d1d; margin: 0;">Evitar colocar el logotipo sobre fondos saturados o sin contraste WCAG.</p>
                        </div>
                    </td>
                    <!-- Card 4: Efectos y 3D -->
                    <td>
                        <div class="abuse-box-pdf">
                            <table class="abuse-stage-pdf" style="width: 100%;">
                                <tr>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($b64LogoPrimary): ?>
                                            <div style="border: 2pt solid #ef4444; border-radius: 6pt; padding: 2pt 10pt; display: inline-block;">
                                                <img src="<?php echo $b64LogoPrimary; ?>" style="max-height: 36pt; vertical-align: middle;">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <span class="abuse-badge-pdf">✕ PROHIBIDO</span>
                            <h4 style="font-size: 9pt; font-weight: 800; color: #991b1b; margin: 2pt 0;">No Añadir Efectos o Bordes Duros</h4>
                            <p style="font-size: 7.5pt; color: #7f1d1d; margin: 0;">Prohibido incorporar biseles, sombras 3D, contornos o brillos artificiales.</p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 7: SISTEMA CROMÁTICO CON TINTES (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">06 / SISTEMA CROMÁTICO</div>
                        <h2 class="sh-title">Paleta de Color y Valores Técnicos</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 07</td>
                </tr>
            </table>

            <?php 
            $shownColors = array_slice($colors, 0, 5);
            if (empty($shownColors)) {
                $shownColors = [
                    ['name' => 'Primario Real', 'role' => 'Primario', 'hex' => '#262ECF'],
                    ['name' => 'Secundario Profundo', 'role' => 'Secundario', 'hex' => '#081116'],
                    ['name' => 'Acento Vibrante', 'role' => 'Acento', 'hex' => '#EC4899'],
                    ['name' => 'Texto Principal', 'role' => 'Texto', 'hex' => '#0F172A'],
                    ['name' => 'Fondo Superficie', 'role' => 'Fondo', 'hex' => '#F8FAFC']
                ];
            }
            while (count($shownColors) < 5) {
                $shownColors[] = ['name' => 'Neutro Oficial', 'role' => 'Acento', 'hex' => '#64748B'];
            }
            ?>
            <table class="tbl-5col" style="margin-top: 8pt;">
                <tr>
                    <?php foreach ($shownColors as $col): 
                        $hex = strtoupper($col['hex'] ?? '#262ECF');
                        if (!str_starts_with($hex, '#')) $hex = '#' . $hex;
                        $rgb = !empty($col['rgb']) ? $col['rgb'] : bg_hex_to_rgb($hex)['str'];
                        $cmyk = !empty($col['cmyk']) ? $col['cmyk'] : bg_hex_to_cmyk($hex)['str'];
                        $pantone = !empty($col['pantone']) ? $col['pantone'] : 'Directo';
                        $role = $col['role'] ?? 'Color Oficial';
                    ?>
                    <td>
                        <div class="color-card-pdf" style="height: 335pt;">
                            <!-- Swatch Box Top -->
                            <div class="color-swatch-box" style="background-color: <?php echo $hex; ?>;">
                                <span class="color-badge"><?php echo htmlspecialchars($role); ?></span>
                            </div>
                            <!-- Tint Ladder -->
                            <table class="color-tints-tbl">
                                <tr>
                                    <td style="background-color: <?php echo $hex; ?>; opacity: 1.0;"></td>
                                    <td style="background-color: <?php echo $hex; ?>; opacity: 0.8;"></td>
                                    <td style="background-color: <?php echo $hex; ?>; opacity: 0.6;"></td>
                                    <td style="background-color: <?php echo $hex; ?>; opacity: 0.4;"></td>
                                    <td style="background-color: <?php echo $hex; ?>; opacity: 0.2;"></td>
                                </tr>
                            </table>
                            <div class="color-info-padding">
                                <h3 class="color-name-title"><?php echo htmlspecialchars($col['name'] ?? $hex); ?></h3>
                                <table class="color-spec-row" style="margin-top: 6pt;">
                                    <tr>
                                        <td class="color-spec-label">HEX</td>
                                        <td class="color-spec-val"><?php echo $hex; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="color-spec-label">RGB</td>
                                        <td class="color-spec-val"><?php echo htmlspecialchars($rgb); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="color-spec-label">CMYK</td>
                                        <td class="color-spec-val"><?php echo htmlspecialchars($cmyk); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="color-spec-label">PMS</td>
                                        <td class="color-spec-val"><?php echo htmlspecialchars($pantone); ?></td>
                                    </tr>
                                </table>
                                <div style="border-top: 1pt solid #f1f5f9; padding-top: 6pt; margin-top: 8pt; font-size: 7.5pt; color: #64748b; line-height: 1.35;">
                                    Uso técnico en soportes corporativos digitales y fórmulas de impresión cuatricromía.
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 8: TIPOGRAFÍAS CORPORATIVAS (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">07 / TIPOGRAFÍA CORPORATIVA</div>
                        <h2 class="sh-title">Familias Tipográficas y Jerarquía Editorial</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 08</td>
                </tr>
            </table>

            <?php 
            $shownFonts = array_slice($fonts, 0, 2);
            if (empty($shownFonts)) {
                $shownFonts = [
                    ['name' => 'Inter', 'role' => 'Tipografía Primaria / Titulares', 'weights' => 'Regular 400, Bold 700', 'usage' => 'Uso principal en títulos, encabezados institucionales y grandes destacados.'],
                    ['name' => 'Roboto', 'role' => 'Tipografía Secundaria / Cuerpo', 'weights' => 'Light 300, Regular 400', 'usage' => 'Textos continuos, contratos, documentos técnicos y plataformas digitales.']
                ];
            }
            $fPrim = $shownFonts[0] ?? ['name' => 'Inter', 'role' => 'Tipografía Primaria', 'weights' => 'Regular 400, Bold 700'];
            $fSec = $shownFonts[1] ?? ['name' => 'Roboto', 'role' => 'Tipografía Secundaria', 'weights' => 'Light 300, Regular 400'];
            ?>
            <table class="tbl-2col" style="margin-top: 6pt;">
                <tr>
                    <!-- Tipografía Primaria Specimen -->
                    <td style="width: 58%;">
                        <div class="card-white" style="height: 350pt;">
                            <div style="font-size: 8.5pt; font-weight: 800; color: <?php echo $sysPrimary; ?>; text-transform: uppercase; margin-bottom: 2pt;">
                                [ Familia Tipográfica Primaria ]
                            </div>
                            <h2 style="font-size: 26pt; font-weight: 900; color: #0f172a; margin: 0 0 4pt 0; letter-spacing: -0.5pt;">
                                <?php echo htmlspecialchars($fPrim['name']); ?>
                            </h2>
                            <p style="font-size: 8.5pt; color: #64748b; margin: 0 0 10pt 0;">
                                Pesos autorizados: <strong><?php echo htmlspecialchars($fPrim['weights'] ?? 'Regular 400, SemiBold 600, Bold 700'); ?></strong>
                            </p>

                            <!-- Specimen Box -->
                            <div style="background: #f8fafc; border: 1pt solid #e2e8f0; border-radius: 6pt; padding: 12pt; margin-bottom: 10pt;">
                                <div style="font-size: 13pt; font-weight: 700; color: #0f172a; letter-spacing: 1.5pt; line-height: 1.6; margin-bottom: 6pt;">
                                    A B C D E F G H I J K L M N Ñ O P Q R S T U V W X Y Z
                                </div>
                                <div style="font-size: 12pt; color: #334155; letter-spacing: 1.5pt; line-height: 1.6; margin-bottom: 6pt;">
                                    a b c d e f g h i j k l m n ñ o p q r s t u v w x y z
                                </div>
                                <div style="font-size: 11pt; color: #64748b; font-family: monospace;">
                                    0 1 2 3 4 5 6 7 8 9 &amp; @ € $ ! ? ( ) [ ] / \
                                </div>
                            </div>

                            <p style="font-size: 8.5pt; color: #475569; line-height: 1.45; margin: 0;">
                                <?php echo !empty($fPrim['usage']) ? htmlspecialchars($fPrim['usage']) : 'Tipografía de alta legibilidad, diseñada para proyectar modernidad y claridad en todos los canales oficiales de la marca.'; ?>
                            </p>
                        </div>
                    </td>
                    <!-- Tipografía Secundaria y Escala -->
                    <td style="width: 42%;">
                        <div class="card-box" style="height: 350pt;">
                            <div style="font-size: 8pt; font-weight: 800; color: #ec4899; text-transform: uppercase; margin-bottom: 2pt;">
                                [ Escala Jerárquica Editorial ]
                            </div>
                            <h3 class="card-title" style="margin-bottom: 10pt;">Jerarquía Tipográfica</h3>
                            
                            <!-- H1 Display -->
                            <div style="border-bottom: 1pt solid #e2e8f0; padding-bottom: 6pt; margin-bottom: 6pt;">
                                <span style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Titular Display (H1) • 28pt Bold</span>
                                <div style="font-size: 16pt; font-weight: 800; color: #0f172a; line-height: 1.2;">Innovación y Estrategia</div>
                            </div>
                            <!-- H2 Subtítulo -->
                            <div style="border-bottom: 1pt solid #e2e8f0; padding-bottom: 6pt; margin-bottom: 6pt;">
                                <span style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Subtítulo (H2) • 18pt SemiBold</span>
                                <div style="font-size: 12pt; font-weight: 700; color: #334155; line-height: 1.3;">Transformación Digital Integral</div>
                            </div>
                            <!-- Body Text -->
                            <div style="border-bottom: 1pt solid #e2e8f0; padding-bottom: 6pt; margin-bottom: 6pt;">
                                <span style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Cuerpo de Texto (Body) • 10.5pt Regular</span>
                                <div style="font-size: 9pt; color: #475569; line-height: 1.4;">
                                    Lectura continua optimizada para soportes impresos, contratos, manuales técnicos y aplicaciones digitales interactivas.
                                </div>
                            </div>
                            <!-- Micro Legal -->
                            <div>
                                <span style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Legal &amp; Marcas (Micro) • 8pt Regular</span>
                                <div style="font-size: 7.5pt; color: #94a3b8; line-height: 1.3;">
                                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($bg['brand_name']); ?>. Todos los derechos reservados.
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 9: APLICACIONES DE MARCA & MOCKUPS (100% LIGHT MODE) ================= -->
    <div class="slide slide-break">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">08 / APLICACIONES VISUALES</div>
                        <h2 class="sh-title">Ecosistema de Marca y Piezas Gráficas</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 09</td>
                </tr>
            </table>

            <table class="tbl-3col" style="margin-top: 8pt;">
                <tr>
                    <!-- Mockup 1: Papelería -->
                    <td>
                        <div class="card-white" style="height: 350pt; text-align: center;">
                            <div style="text-align: left; font-size: 8.5pt; font-weight: 800; color: #2563eb; text-transform: uppercase; margin-bottom: 6pt;">
                                [ Papelería &amp; Tarjetas ]
                            </div>
                            <div style="height: 220pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%; margin-bottom: 10pt;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center; padding: 10pt;">
                                    <div style="background: #ffffff; border: 1pt solid #e2e8f0; border-radius: 6pt; padding: 18pt 14pt; display: inline-block; width: 170pt; box-shadow: 0 4pt 6pt rgba(0,0,0,0.03);">
                                        <?php if ($b64LogoPrimary): ?>
                                            <img src="<?php echo $b64LogoPrimary; ?>" style="max-height: 30pt; max-width: 140pt; vertical-align: middle; margin-bottom: 12pt;">
                                        <?php endif; ?>
                                        <div style="border-top: 1pt solid #f1f5f9; padding-top: 8pt; text-align: left;">
                                            <div style="font-size: 8pt; font-weight: 800; color: #0f172a;">Director General</div>
                                            <div style="font-size: 7pt; color: #64748b;">contacto@romaagencia.com</div>
                                            <div style="font-size: 7pt; color: #64748b;">+51 900 000 000</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h4 style="font-size: 10pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;">Tarjetas Ejecutivas &amp; Sobres</h4>
                            <p style="font-size: 8pt; color: #64748b; margin: 0; line-height: 1.35;">Soporte couche mate de 350g con barniz sectorizado en el isotipo.</p>
                        </div>
                    </td>
                    <!-- Mockup 2: Digital & Mobile App -->
                    <td>
                        <div class="card-white" style="height: 350pt; text-align: center;">
                            <div style="text-align: left; font-size: 8.5pt; font-weight: 800; color: #ec4899; text-transform: uppercase; margin-bottom: 6pt;">
                                [ Ecosistema Digital ]
                            </div>
                            <div style="height: 220pt; background: #0f172a; border-radius: 6pt; display: table; width: 100%; margin-bottom: 10pt;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center; padding: 10pt;">
                                    <div style="background: #1e293b; border: 1.5pt solid #334155; border-radius: 12pt; padding: 14pt 10pt; display: inline-block; width: 140pt;">
                                        <div style="width: 25pt; height: 3pt; background: #475569; border-radius: 2pt; margin: 0 auto 10pt auto;"></div>
                                        <?php if ($b64LogoDark): ?>
                                            <img src="<?php echo $b64LogoDark; ?>" style="max-height: 24pt; max-width: 110pt; vertical-align: middle; margin-bottom: 10pt;">
                                        <?php endif; ?>
                                        <div style="background: rgba(255,255,255,0.06); border-radius: 4pt; padding: 6pt; font-size: 7pt; color: #94a3b8; text-align: left;">
                                            <span style="color: #60a5fa; font-weight: bold;">● Live Status</span><br>
                                            Brand Experience UI
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h4 style="font-size: 10pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;">App Mobile &amp; Web Responsive</h4>
                            <p style="font-size: 8pt; color: #64748b; margin: 0; line-height: 1.35;">Interfaces oscuras y claras con estricto apego al contraste cromático.</p>
                        </div>
                    </td>
                    <!-- Mockup 3: Merchandising / Signage -->
                    <td>
                        <div class="card-white" style="height: 350pt; text-align: center;">
                            <div style="text-align: left; font-size: 8.5pt; font-weight: 800; color: #10b981; text-transform: uppercase; margin-bottom: 6pt;">
                                [ Merchandising &amp; Rótulos ]
                            </div>
                            <div style="height: 220pt; background: #f8fafc; border: 1pt solid #f1f5f9; border-radius: 6pt; display: table; width: 100%; margin-bottom: 10pt;">
                                <div style="display: table-cell; vertical-align: middle; text-align: center; padding: 10pt;">
                                    <div style="background: #ffffff; border: 1pt solid #e2e8f0; border-radius: 6pt; padding: 20pt 16pt; display: inline-block; width: 160pt; text-align: center;">
                                        <div style="font-size: 8pt; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8pt;">Bolsa Corporativa</div>
                                        <?php if ($b64LogoSymbol): ?>
                                            <img src="<?php echo $b64LogoSymbol; ?>" style="max-height: 48pt; max-width: 60pt; vertical-align: middle; margin-bottom: 8pt;">
                                        <?php endif; ?>
                                        <div style="font-size: 7.5pt; font-weight: 800; color: <?php echo $sysPrimary; ?>;">ROMA ECO-PACK</div>
                                    </div>
                                </div>
                            </div>
                            <h4 style="font-size: 10pt; font-weight: 800; color: #0f172a; margin: 0 0 2pt 0;">Packaging &amp; Rótulo Corpóreo</h4>
                            <p style="font-size: 8pt; color: #64748b; margin: 0; line-height: 1.35;">Grabados sobre materiales nobles, acrílico y señalética exterior.</p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= SLIDE 10: CIERRE CORPORATIVO & CERTIFICACIÓN (100% LIGHT MODE) ================= -->
    <div class="slide">
        <div class="slide-inner">
            <table class="slide-header-tbl">
                <tr>
                    <td>
                        <div class="sh-kicker">09 / CIERRE Y CERTIFICACIÓN</div>
                        <h2 class="sh-title">Guía Oficial de Marca &amp; Soporte Corporativo</h2>
                    </td>
                    <td class="sh-brand"><?php echo htmlspecialchars($bg['brand_name']); ?> • PÁG. 10</td>
                </tr>
            </table>

            <table style="width: 100%; height: 350pt; border-collapse: collapse; margin-top: 6pt;">
                <tr>
                    <td style="vertical-align: middle; text-align: center;">
                        <div class="card-white" style="max-width: 620pt; margin: 0 auto; padding: 24pt 32pt; text-align: center; border: 1.5pt solid #e2e8f0; border-radius: 12pt;">
                            <?php if ($b64RomaLogo): ?>
                                <img src="<?php echo $b64RomaLogo; ?>" style="max-height: 38pt; vertical-align: middle; margin-bottom: 12pt;" alt="Roma Agencia">
                            <?php endif; ?>

                            <h3 style="font-size: 16pt; font-weight: 900; color: #0f172a; margin: 0 0 8pt 0; letter-spacing: -0.3pt;">
                                Certificación Oficial de Identidad Visual
                            </h3>
                            <p style="font-size: 9.5pt; color: #475569; line-height: 1.55; max-width: 520pt; margin: 0 auto 16pt auto;">
                                Este manual establece las directrices normativas obligatorias para la reproducción, diseño y aplicación de los activos de marca de <strong><?php echo htmlspecialchars($bg['brand_name']); ?></strong>. Cualquier alteración o soporte no contemplado en este documento requiere validación y aprobación previa por el departamento creativo de Roma Agencia.
                            </p>

                            <!-- Credentials Grid -->
                            <table style="width: 480pt; margin: 0 auto; border-collapse: collapse; font-size: 9pt;">
                                <tr>
                                    <td style="padding: 6pt 10pt; background: #f8fafc; border: 1pt solid #e2e8f0; border-radius: 6pt; width: 33.33%;">
                                        <div style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Portal Oficial</div>
                                        <div style="font-weight: 800; color: <?php echo $sysPrimary; ?>;">romaagencia.com</div>
                                    </td>
                                    <td style="width: 10pt;"></td>
                                    <td style="padding: 6pt 10pt; background: #f8fafc; border: 1pt solid #e2e8f0; border-radius: 6pt; width: 33.33%;">
                                        <div style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Mesa de Soporte</div>
                                        <div style="font-weight: 800; color: #0f172a;">contacto@romaagencia.com</div>
                                    </td>
                                    <td style="width: 10pt;"></td>
                                    <td style="padding: 6pt 10pt; background: #f8fafc; border: 1pt solid #e2e8f0; border-radius: 6pt; width: 33.33%;">
                                        <div style="font-size: 7.5pt; font-weight: 800; color: #64748b; text-transform: uppercase;">Redes Oficiales</div>
                                        <div style="font-weight: 800; color: #0f172a;">@romaagencia</div>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top: 18pt; font-size: 8pt; color: #94a3b8; border-top: 1pt solid #f1f5f9; padding-top: 10pt;">
                                Documento registrado bajo código: <strong>BG-<?php echo str_pad($bg['id'], 5, '0', STR_PAD_LEFT); ?>-PRO</strong> • Emisión: <?php echo date('Y'); ?> • Todos los derechos reservados Roma Agencia Creativa.
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table class="wm-footer-table">
            <tr>
                <td class="wm-td-left">
                    <?php if ($b64RomaLogo): ?>
                        <img src="<?php echo $b64RomaLogo; ?>" class="wm-logo-img" alt="Roma Agencia">
                    <?php endif; ?>
                    <span class="wm-agency-text"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?> • BRAND GUIDELINES</span>
                </td>
                <td class="wm-td-right">
                    <span class="wm-social-text">Redes Sociales: <span class="wm-social-bold">@romaagencia</span></span>
                    <span style="color: #cbd5e1; margin: 0 6pt;">•</span>
                    <span class="wm-web-link">romaagencia.com</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

if ($isPrintMode) {
    echo $html;
    exit;
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->setDefaultFont('Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, 960, 540], 'landscape');
$dompdf->render();

$cleanBrand = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $bg['brand_name']);
$filename = "Brand_Guidelines_{$cleanBrand}_1980x1080.pdf";

$dompdf->stream($filename, ["Attachment" => false]);
exit;
