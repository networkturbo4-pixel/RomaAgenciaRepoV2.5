<?php
// modules/brand_guidelines/view.php
// Standalone 1980x1080 (16:9) Presentation & Brand Guidelines Landing Page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';

$database = new Database();
$db = $database->getConnection();
bg_ensure_proposals_columns($db);

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

$baseUrl = bg_get_base_url();

// Fetch System Settings
$sysSettings = bg_get_system_settings($db);
$sysPrimary = !empty($sysSettings['primary_color']) ? $sysSettings['primary_color'] : '#262ecf';
$sysSecondary = !empty($sysSettings['secondary_color']) ? $sysSettings['secondary_color'] : '#081116';
$sysSiteName = !empty($sysSettings['site_name']) ? trim($sysSettings['site_name']) : 'Roma Agencia Creativa';

// 404 Standalone Landing
if (!$bg) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manual no encontrado - <?php echo htmlspecialchars($sysSiteName); ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: #081116; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem; }
            .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 28px; padding: 3rem 2.5rem; max-width: 500px; width: 100%; box-shadow: 0 25pt 50pt rgba(0,0,0,0.5); }
            .icon { width: 76px; height: 76px; border-radius: 22px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 1.5rem; }
            h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.75rem; }
            p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; }
            a { display: inline-flex; align-items: center; gap: 0.5rem; background: <?php echo $sysPrimary; ?>; color: white; text-decoration: none; padding: 0.85rem 1.8rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 14px -2px rgba(38, 46, 207, 0.5); }
            a:hover { transform: translateY(-2px); box-shadow: 0 8px 22px -3px rgba(38, 46, 207, 0.7); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon"><i class="ph-bold ph-warning-circle"></i></div>
            <h1>Manual de Marca no encontrado</h1>
            <p>El enlace que buscas no existe, ha expirado o el identificador de la marca ha cambiado.</p>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>"><i class="ph-bold ph-arrow-left"></i> Ir a Inicio</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$guidelineId = (int)$bg['id'];
$isLoggedIn = isset($_SESSION['user_id']);
$isUnlocked = !empty($_SESSION['bg_unlocked_' . $guidelineId]);

// Check Privacy: Standalone PIN / Password Lock Screen (NO CRM SIDEBAR, NO ROMITA)
if ($bg['is_public'] == 0 && !$isLoggedIn && !$isUnlocked) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Protegido — <?php echo htmlspecialchars($bg['brand_name']); ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background: <?php echo $sysSecondary; ?> radial-gradient(circle at 50% 10%, rgba(38, 46, 207, 0.22) 0%, transparent 60%);
                color: #f8fafc;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                align-items: center;
                padding: 2rem 1.5rem;
            }
            .lock-card {
                background: rgba(15, 23, 42, 0.85);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                border-radius: 28px;
                padding: 3rem 2.5rem;
                max-width: 440px;
                width: 100%;
                text-align: center;
                box-shadow: 0 30px 60px -15px rgba(0,0,0,0.7);
                margin: auto 0;
            }
            .lock-icon-wrap {
                width: 72px;
                height: 72px;
                border-radius: 22px;
                background: rgba(38, 46, 207, 0.18);
                color: <?php echo $sysPrimary; ?>;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 34px;
                margin: 0 auto 1.5rem;
                border: 1px solid rgba(38, 46, 207, 0.35);
            }
            .lock-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.4rem; color: #ffffff; }
            .lock-desc { color: #94a3b8; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.75rem; }
            .lock-input {
                width: 100%;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 14px;
                padding: 0.85rem 1rem;
                font-size: 1.1rem;
                text-align: center;
                letter-spacing: 3px;
                color: #ffffff;
                outline: none;
                margin-bottom: 1.25rem;
                transition: all 0.2s;
            }
            .lock-input:focus {
                border-color: <?php echo $sysPrimary; ?>;
                box-shadow: 0 0 0 3px rgba(38, 46, 207, 0.3);
            }
            .lock-btn {
                width: 100%;
                background: <?php echo $sysPrimary; ?>;
                color: white;
                border: none;
                border-radius: 14px;
                padding: 0.95rem;
                font-weight: 700;
                font-size: 1rem;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
                box-shadow: 0 8px 20px -3px rgba(38, 46, 207, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }
            .lock-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 25px -4px rgba(38, 46, 207, 0.7); }
            .lock-error { color: #ef4444; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; display: none; }
            
            /* Watermark Footer */
            .watermark-bar {
                width: 100%;
                max-width: 900px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 1.5rem;
                border-top: 1px solid rgba(255,255,255,0.08);
                color: #64748b;
                font-size: 0.85rem;
            }
            .watermark-bar a { color: <?php echo $sysPrimary; ?>; text-decoration: none; font-weight: 700; }
        </style>
    </head>
    <body>
        <div></div>
        <div class="lock-card">
            <div class="lock-icon-wrap"><i class="ph-bold ph-lock-key"></i></div>
            <h2 class="lock-title"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
            <p class="lock-desc">Este manual de identidad es privado. Ingresa el PIN o contraseña de acceso para desbloquear la presentación.</p>
            <form id="unlockForm" onsubmit="handleUnlock(event)">
                <input type="password" id="unlockPassword" required placeholder="PIN o Contraseña" class="lock-input" autofocus>
                <div id="unlockError" class="lock-error"></div>
                <button type="submit" id="unlockBtn" class="lock-btn">
                    <i class="ph-bold ph-key"></i> Desbloquear Manual
                </button>
            </form>
        </div>

        <!-- Watermark Footer -->
        <div class="watermark-bar">
            <div>
                <strong style="color:#94a3b8; letter-spacing:1px;"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?></strong>
            </div>
            <div>
                <span>Redes: <strong style="color:white;">@romaagencia</strong></span>
                <span style="margin: 0 8px; opacity: 0.4;">•</span>
                <a href="https://romaagencia.com" target="_blank">romaagencia.com</a>
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
    </body>
    </html>
    <?php
    exit();
}

// Increment View Counter (throttled by session)
if (empty($_SESSION['bg_viewed_' . $guidelineId])) {
    $_SESSION['bg_viewed_' . $guidelineId] = true;
    $db->prepare("UPDATE brand_guidelines SET views_count = views_count + 1 WHERE id = ?")->execute([$guidelineId]);
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

$values = !empty($bg['values_json']) ? json_decode($bg['values_json'], true) : [];
if (!is_array($values)) $values = [];

$proposals = !empty($bg['logo_proposals_json']) ? json_decode($bg['logo_proposals_json'], true) : [];
if (!is_array($proposals)) $proposals = [];
$showProposals = !empty($bg['show_proposals']);
$hasActiveProposals = $showProposals && !empty($proposals);

// Primary & Secondary Brand Colors
$primaryHex = !empty($colors[0]['hex']) ? $colors[0]['hex'] : '#2563EB';
$secondaryHex = !empty($colors[1]['hex']) ? $colors[1]['hex'] : '#EC4899';

// Logo Assets
$logoPrimaryUrl = !empty($bg['logo_primary']) ? bg_asset_url($bg['logo_primary']) : '';
$logoDarkUrl = !empty($bg['logo_primary_dark']) ? bg_asset_url($bg['logo_primary_dark']) : '';
if (empty($logoDarkUrl)) {
    if (file_exists(__DIR__ . '/../../uploads/logo_dark_1790398960.png')) {
        $logoDarkUrl = bg_asset_url('uploads/logo_dark_1790398960.png');
    } else {
        $logoDarkUrl = $logoPrimaryUrl;
    }
}
$logoSymbolUrl = !empty($bg['logo_symbol']) ? bg_asset_url($bg['logo_symbol']) : $logoPrimaryUrl;

// Roma Agency Watermark Logos (Light and Dark)
$romaLogoLightUrl = !empty($sysSettings['logo_light']) ? bg_asset_url($sysSettings['logo_light']) : '';
if (empty($romaLogoLightUrl) && file_exists(__DIR__ . '/../../uploads/logo_light_1790398960.png')) {
    $romaLogoLightUrl = bg_asset_url('uploads/logo_light_1790398960.png');
}
if (empty($romaLogoLightUrl)) {
    $romaLogoLightUrl = bg_asset_url('assets/img/default-logo.png');
}

$romaLogoDarkUrl = !empty($sysSettings['logo_dark']) ? bg_asset_url($sysSettings['logo_dark']) : '';
if (empty($romaLogoDarkUrl) && file_exists(__DIR__ . '/../../uploads/logo_dark_1790398960.png')) {
    $romaLogoDarkUrl = bg_asset_url('uploads/logo_dark_1790398960.png');
}
if (empty($romaLogoDarkUrl)) {
    $romaLogoDarkUrl = $romaLogoLightUrl;
}
$romaLogoUrl = $romaLogoDarkUrl;

$pdfDownloadUrl = "index.php?module=brand_guidelines&action=pdf&slug=" . urlencode($bg['slug']) . "&download=1";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars($baseUrl . '/'); ?>">
    <title><?php echo htmlspecialchars($bg['brand_name']); ?> — Brand Guidelines Oficial (1980x1080)</title>
    
    <!-- OpenGraph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($bg['brand_name']); ?> - Brand Guidelines">
    <meta property="og:description" content="<?php echo htmlspecialchars($bg['tagline'] ?: $bg['description'] ?: 'Manual de Identidad Visual de Marca.'); ?>">
    <?php if (!empty($logoPrimaryUrl)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($logoPrimaryUrl); ?>">
    <?php endif; ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php foreach ($fonts as $f): 
        if (!empty($f['name'])):
            $isCustom = ($f['source'] ?? '') === 'custom' || !empty($f['file_url']);
            if (!$isCustom):
                $fq = urlencode(trim($f['name'])) . ':wght@300;400;500;600;700;800;900';
    ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?php echo $fq; ?>&display=swap">
    <?php 
            else: 
                if (!empty($f['file_url'])):
    ?>
    <style>
        @font-face {
            font-family: '<?php echo addslashes(trim($f['name'])); ?>';
            src: url('<?php echo htmlspecialchars(bg_asset_url($f['file_url'])); ?>');
            font-weight: 100 900;
            font-display: swap;
        }
    </style>
    <?php 
                endif;
            endif;
        endif; 
    endforeach; ?>

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --sys-primary: <?php echo $sysPrimary; ?>;
            --sys-secondary: <?php echo $sysSecondary; ?>;
            --brand-primary: <?php echo $primaryHex; ?>;
            --brand-secondary: <?php echo $secondaryHex; ?>;
            --bg-dark: #07090e;
            --surface-dark: #0d101a;
            --surface-card: rgba(255, 255, 255, 0.04);
            --surface-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            user-select: none;
        }

        /* ADMIN TOP BAR (IF LOGGED IN AS CRM USER) */
        .admin-top-bar {
            background: rgba(8, 17, 22, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 0.45rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            position: sticky;
            top: 0;
            z-index: 9999;
        }
        .admin-top-bar a {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.2s;
        }
        .admin-top-bar a:hover {
            background: rgba(255, 255, 255, 0.16);
        }
        .admin-top-bar .btn-edit-manual {
            background: var(--sys-primary);
            box-shadow: 0 2px 10px rgba(38, 46, 207, 0.4);
        }

        /* HEADER / CONTROLS BAR */
        .deck-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 2rem;
            background: rgba(8, 11, 18, 0.88);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--surface-border);
            z-index: 100;
        }
        .dh-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .dh-logo-preview {
            max-height: 28px;
            max-width: 90px;
            object-fit: contain;
        }
        .dh-brand-name {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .dh-brand-badge {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            background: rgba(38, 46, 207, 0.18);
            color: var(--sys-primary);
            border: 1px solid rgba(38, 46, 207, 0.35);
            letter-spacing: 0.5px;
        }

        /* Center Navigation Controls */
        .dh-nav-controls {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .nav-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .nav-btn:hover:not(:disabled) {
            background: var(--sys-primary);
            border-color: var(--sys-primary);
            transform: translateY(-1px);
        }
        .nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .slide-counter-badge {
            font-family: 'Plus Jakarta Sans', monospace;
            font-size: 0.92rem;
            font-weight: 800;
            color: #cbd5e1;
            padding: 0.4rem 0.95rem;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Right Actions */
        .dh-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            position: relative;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.62rem 1.15rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .btn-pdf {
            background: var(--sys-primary);
            color: white;
            box-shadow: 0 4px 14px -2px rgba(38, 46, 207, 0.5);
        }
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px -3px rgba(38, 46, 207, 0.7);
            filter: brightness(1.1);
        }
        .btn-toggle-view, .btn-fullscreen, .btn-share {
            background: rgba(255, 255, 255, 0.06);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-toggle-view:hover, .btn-fullscreen:hover, .btn-share:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .btn-theme-toggle {
            background: rgba(255, 255, 255, 0.06);
            color: #f59e0b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 1.15rem;
            padding: 0.62rem 0.85rem;
        }
        .btn-theme-toggle:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fbbf24;
        }

        /* Mobile More Actions Button & Dropdown */
        .mobile-only-btn {
            display: none !important;
        }
        .desktop-action-btn {
            display: inline-flex;
        }
        .mobile-actions-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: rgba(13, 16, 26, 0.96);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 18px;
            padding: 0.65rem;
            min-width: 250px;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.06);
            z-index: 10000;
            flex-direction: column;
            gap: 0.35rem;
        }
        .mobile-actions-dropdown.show {
            display: flex;
            animation: madFadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes madFadeIn {
            from { opacity: 0; transform: translateY(-8px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .mad-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0.95rem;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #f1f5f9;
            text-decoration: none;
            background: transparent;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            transition: all 0.15s;
        }
        .mad-item:hover, .mad-item:active {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .mad-item i {
            font-size: 1.15rem;
            color: var(--sys-primary);
        }

        /* LIGHT MODE THEME FOR PUBLIC PRESENTATION */
        [data-theme="light"] {
            --bg-dark: #f8fafc;
            --surface-dark: #ffffff;
            --surface-card: #ffffff;
            --surface-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        [data-theme="light"] body {
            background: #f1f5f9;
            color: #0f172a;
        }
        [data-theme="light"] .deck-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        [data-theme="light"] .dh-brand-name {
            color: #0f172a;
        }
        [data-theme="light"] .slide-counter-badge {
            background: #f8fafc;
            color: #0f172a;
            border-color: #e2e8f0;
        }
        [data-theme="light"] .nav-btn {
            background: #ffffff;
            color: #0f172a;
            border-color: #cbd5e1;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
        }
        [data-theme="light"] .nav-btn:hover:not(:disabled) {
            background: var(--sys-primary);
            border-color: var(--sys-primary);
            color: #ffffff;
        }
        [data-theme="light"] .btn-action:not(.btn-pdf) {
            background: #ffffff;
            color: #0f172a;
            border-color: #cbd5e1;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
        }
        [data-theme="light"] .btn-action:not(.btn-pdf):hover {
            background: #f1f5f9;
        }
        [data-theme="light"] .btn-theme-toggle {
            color: #d97706;
            background: #ffffff;
            border-color: #cbd5e1;
        }
        [data-theme="light"] .deck-canvas-16-9 {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 16px 45px -10px rgba(15, 23, 42, 0.07), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
        }
        [data-theme="light"] .sh-title {
            color: #0f172a;
        }
        [data-theme="light"] .sh-top {
            border-bottom-color: #e2e8f0;
        }
        [data-theme="light"] .sh-brand-badge {
            color: #64748b;
        }
        [data-theme="light"] .glass-card {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #0f172a;
            box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
        }
        [data-theme="light"] .card-body {
            color: #334155;
        }
        [data-theme="light"] .slide-watermark-footer {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }
        [data-theme="light"] .wm-agency-name {
            color: #64748b;
        }
        [data-theme="light"] .wm-social-item {
            color: #0f172a;
        }

        /* Light mode dropdown */
        [data-theme="light"] .mobile-actions-dropdown {
            background: rgba(255, 255, 255, 0.98);
            border-color: #cbd5e1;
            box-shadow: 0 16px 40px -8px rgba(15, 23, 42, 0.14), 0 0 0 1px rgba(0, 0, 0, 0.04);
        }
        [data-theme="light"] .mad-item {
            color: #0f172a;
        }
        [data-theme="light"] .mad-item:hover, [data-theme="light"] .mad-item:active {
            background: #f1f5f9;
        }

        /* Agency and Brand Logo switching */
        .wm-agency-logo-light { display: none !important; }
        .wm-agency-logo-dark { display: block !important; }
        [data-theme="light"] .wm-agency-logo-light { display: block !important; }
        [data-theme="light"] .wm-agency-logo-dark { display: none !important; }

        .dh-logo-light { display: none; }
        .dh-logo-dark { display: block; }
        [data-theme="light"] .dh-logo-light { display: block; }
        [data-theme="light"] .dh-logo-dark { display: none; }

        /* Light mode for color cards */
        [data-theme="light"] .color-card-pro {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 6px 20px -3px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            color: #0f172a;
        }
        [data-theme="light"] .color-title-h3 {
            color: #0f172a;
        }
        [data-theme="light"] .color-copy-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        [data-theme="light"] .color-copy-row:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        [data-theme="light"] .ccr-val {
            color: #0f172a;
        }

        /* Light mode for mockups */
        [data-theme="light"] .live-mockup-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 6px 20px -3px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            color: #0f172a;
        }
        [data-theme="light"] .lmc-number-title {
            color: #0f172a;
        }
        [data-theme="light"] .lmc-footer-note {
            color: #64748b;
            border-top-color: #f1f5f9;
        }

        /* Light mode typography specimen card */
        [data-theme="light"] .font-specimen-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 6px 20px -3px rgba(15, 23, 42, 0.06);
            color: #0f172a;
        }
        [data-theme="light"] .font-specimen-title {
            color: #0f172a;
        }
        [data-theme="light"] .font-weight-pill {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        [data-theme="light"] .font-alphabet-stage {
            background: #f8fafc;
            color: #0f172a;
            border-color: #e2e8f0;
        }
        [data-theme="light"] .font-usage-text {
            color: #475569;
        }
        [data-theme="light"] .font-tester-field {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        /* ================= 16:9 PRESENTATION STAGE ================= */
        .deck-stage-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 2rem;
            position: relative;
        }

        /* Exact 16:9 ratio container scaling up to 1980x1080 */
        .deck-canvas-16-9 {
            width: 100%;
            max-width: 1920px;
            aspect-ratio: 16 / 9;
            max-height: calc(100vh - 120px);
            background: var(--surface-dark);
            border: 1px solid var(--surface-border);
            border-radius: 24px;
            box-shadow: 0 30px 90px -20px rgba(0, 0, 0, 0.85);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Slide Viewport */
        .slides-track {
            flex: 1;
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .slide-frame {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.98) translateY(10px);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 3.25rem 4.5rem 5.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .slide-frame.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1) translateY(0);
            z-index: 10;
        }

        /* PINNED WATERMARK FOOTER (ON EVERY SLIDE) */
        .slide-watermark-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: rgba(8, 11, 18, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 3.5rem;
            z-index: 50;
        }
        .wm-left-col {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .wm-agency-logo {
            max-height: 24px;
            max-width: 100px;
            object-fit: contain;
            opacity: 0.9;
        }
        .wm-agency-name {
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .wm-right-col {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .wm-social-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #e2e8f0;
            font-weight: 600;
        }
        .wm-social-item i {
            color: var(--sys-primary);
            font-size: 1.05rem;
        }
        .wm-site-link {
            color: var(--sys-primary);
            text-decoration: none;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: opacity 0.2s;
        }
        .wm-site-link:hover {
            opacity: 0.85;
        }

        /* SLIDE HEADER */
        .sh-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.9rem;
            margin-bottom: 1.75rem;
        }
        .sh-kicker {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--brand-primary);
            margin-bottom: 0.3rem;
        }
        .sh-title {
            font-size: 2.1rem;
            font-weight: 900;
            letter-spacing: -0.75px;
            color: #ffffff;
        }
        .sh-brand-badge {
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: #64748b;
            text-transform: uppercase;
        }

        /* ================= COVER SLIDE ================= */
        .slide-cover {
            text-align: center;
            justify-content: center !important;
            align-items: center;
            padding-bottom: 5.5rem;
            background: 
                radial-gradient(circle at 80% 20%, rgba(38, 46, 207, 0.45) 0%, transparent 55%),
                radial-gradient(circle at 20% 80%, rgba(38, 46, 207, 0.25) 0%, transparent 50%),
                linear-gradient(135deg, var(--sys-secondary, #081116) 0%, #0d1226 40%, #151a3d 75%, var(--sys-primary, #262ecf) 140%);
            position: relative;
            overflow: hidden;
        }
        .cover-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        .cover-title {
            font-size: 4.4rem;
            font-weight: 900;
            letter-spacing: -2px;
            line-height: 1.05;
            margin-bottom: 0.85rem;
            background: linear-gradient(180deg, #ffffff 40%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .cover-tagline {
            font-size: 1.35rem;
            color: #cbd5e1;
            max-width: 800px;
            margin: 0 auto 2.25rem;
            line-height: 1.4;
            font-weight: 500;
        }
        .cover-logo-stage-dark {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 2.25rem 4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.7);
            max-width: 520px;
        }
        .cover-logo-stage-dark img {
            max-height: 120px;
            max-width: 420px;
            object-fit: contain;
            filter: drop-shadow(0 10px 25px rgba(0,0,0,0.5));
        }

        /* GRIDS */
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }

        /* CARDS */
        .glass-card {
            background: var(--surface-card);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            padding: 1.75rem 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.2s;
        }
        .glass-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.06);
        }
        .card-label {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-body {
            font-size: 1.02rem;
            line-height: 1.6;
            color: #cbd5e1;
        }

        /* LOGO BOXES */
        .logo-display-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            min-height: 270px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #0f172a;
        }
        .logo-display-card.dark {
            background: #090a10;
            border-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }
        .logo-img-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1.5rem 0;
        }
        .logo-img-container img {
            max-height: 140px;
            max-width: 85%;
            object-fit: contain;
        }
        .logo-card-title {
            font-weight: 800;
            font-size: 1.05rem;
            margin-bottom: 0.25rem;
        }
        .logo-card-desc {
            font-size: 0.82rem;
            color: #64748b;
        }
        .logo-display-card.dark .logo-card-desc {
            color: #94a3b8;
        }

        /* ================= COLOR CARDS WITH TINTS & COPIES ================= */
        .color-card-pro {
            background: #0d1220;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            color: #f8fafc;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .color-card-pro:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px -4px rgba(0, 0, 0, 0.6);
        }
        .color-swatch-main {
            height: 140px;
            width: 100%;
            position: relative;
            padding: 1rem;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .color-role-tag {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.25rem 0.65rem;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .color-tints-bar {
            display: flex;
            height: 24px;
            width: 100%;
        }
        .color-tint-step {
            flex: 1;
            height: 100%;
            position: relative;
        }
        .color-info-pane {
            padding: 1.25rem 1.4rem;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        .color-title-h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }
        .color-copy-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.45rem 0.65rem;
            border-radius: 9px;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            cursor: pointer;
            transition: all 0.15s;
        }
        .color-copy-row:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(255, 255, 255, 0.16);
        }
        .ccr-label { font-weight: 800; color: #94a3b8; font-size: 0.75rem; }
        .ccr-val { font-family: monospace; font-weight: 800; color: #f8fafc; }
        .ccr-icon { color: #64748b; font-size: 0.9rem; }
        .color-copy-row:hover .ccr-icon { color: var(--sys-primary); }

        /* ================= USOS INCORRECTOS VISUALES ================= */
        .abuse-card {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 20px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.2s;
        }
        .abuse-card:hover {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.09);
        }
        .abuse-stage {
            height: 120px;
            width: 100%;
            background: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            margin-bottom: 1rem;
            overflow: hidden;
            position: relative;
        }
        .abuse-stage img {
            max-height: 80px;
            max-width: 80%;
            object-fit: contain;
        }
        .abuse-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #ef4444;
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .abuse-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #fca5a5;
            margin-bottom: 0.25rem;
        }
        .abuse-desc {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        /* ================= RETÍCULA & CLEARSPACE BLUEPRINT ================= */
        .blueprint-card {
            background: radial-gradient(rgba(56, 189, 248, 0.08) 1px, transparent 1px) 0 0 / 22px 22px, #0b1220;
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 24px;
            padding: 2.5rem;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 380px;
            box-shadow: inset 0 0 40px rgba(0, 0, 0, 0.5);
        }
        .blueprint-logo-box {
            position: relative;
            padding: 40px;
            border: 2px dashed rgba(56, 189, 248, 0.6);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .blueprint-logo-box img {
            max-height: 110px;
            max-width: 320px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        .bp-bracket-top, .bp-bracket-bottom, .bp-bracket-left, .bp-bracket-right {
            position: absolute;
            color: #38bdf8;
            font-family: monospace;
            font-size: 0.85rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bp-bracket-top { top: 10px; left: 50%; transform: translateX(-50%); }
        .bp-bracket-bottom { bottom: 10px; left: 50%; transform: translateX(-50%); }
        .bp-bracket-left { left: 10px; top: 50%; transform: translateY(-50%); }
        .bp-bracket-right { right: 10px; top: 50%; transform: translateY(-50%); }

        /* ================= MOCKUPS EN VIVO (IMAGES 2, 3, 4, 5) ================= */
        .live-mockup-card {
            background: #0f1422;
            border-radius: 20px;
            padding: 1.75rem;
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.04);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .live-mockup-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -4px rgba(0, 0, 0, 0.6);
        }
        .lmc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .lmc-number-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .lmc-num {
            color: var(--sys-primary);
            font-weight: 900;
        }
        .lmc-footer-note {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 1.25rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .lmc-tag {
            color: var(--sys-primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
        }

        /* 01. Browser Mockup */
        .browser-mockup {
            background: #f1f5f9;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .browser-bar {
            background: #e2e8f0;
            padding: 0.5rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .traffic-lights {
            display: flex;
            gap: 5px;
        }
        .traffic-light {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .tl-red { background: #ef4444; }
        .tl-yellow { background: #f59e0b; }
        .tl-green { background: #10b981; }
        .browser-tab {
            background: #ffffff;
            padding: 0.3rem 0.85rem;
            border-radius: 8px 8px 0 0;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #1e293b;
        }
        .browser-tab img {
            width: 16px;
            height: 16px;
            object-fit: contain;
        }
        .browser-viewport {
            background: #ffffff;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .favicon-zoom-box {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .favicon-zoom-box img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        /* 02. Social Profile Mockup */
        .social-phone-frame {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1rem 1.25rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
        }
        .phone-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        .dynamic-island {
            width: 60px;
            height: 14px;
            background: #000000;
            border-radius: 10px;
        }
        .social-profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.85rem;
        }
        .social-avatar-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #f8fafc;
            border: 2px solid var(--sys-primary);
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .social-avatar-circle img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: contain;
        }
        .social-stats {
            display: flex;
            gap: 1rem;
            text-align: center;
            font-size: 0.78rem;
        }
        .social-stat-num { font-weight: 900; color: #0f172a; }
        .social-stat-lbl { color: #64748b; font-size: 0.7rem; }

        /* 03. Google Search Mockup */
        .google-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
        }
        .google-logo-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }
        .google-brand-colored {
            font-size: 1.2rem;
            font-weight: 900;
            color: #4285f4;
            letter-spacing: -0.5px;
        }
        .google-search-bar {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 0.35rem 0.85rem;
            font-size: 0.8rem;
            color: #475569;
        }
        .google-snippet {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }
        .google-snippet-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }
        .google-snippet-avatar img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }
        .google-link-title {
            color: #1a0dab;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            display: block;
            margin-bottom: 0.2rem;
        }
        .google-url { font-size: 0.75rem; color: #4d5156; margin-bottom: 0.35rem; }
        .google-desc { font-size: 0.8rem; color: #4d5156; line-height: 1.4; }

        /* 04. Desktop Navbar 1440px */
        .desktop-nav-mockup {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }
        .nav-mockup-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .nav-mockup-logo img {
            max-height: 24px;
            object-fit: contain;
        }
        .nav-mockup-links {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
        }
        .nav-mockup-cta {
            background: #0f172a;
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .nav-mockup-hero {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* 06. App Icon & Dock */
        .dock-shelf-mockup {
            background: linear-gradient(180deg, #64748b 0%, #334155 100%);
            border-radius: 20px;
            padding: 1.75rem 1.25rem 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .glass-dock {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 26px;
            padding: 0.6rem 1rem;
            display: flex;
            gap: 0.85rem;
        }
        .dock-app-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .dock-app-icon img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }

        /* 08. Detail Scale & Monochrome */
        .scale-retention-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            padding: 1.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .scale-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .scale-item span {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
        }
        .monochrome-split-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .mono-box {
            border-radius: 14px;
            height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        .mono-box.white {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }
        .mono-box.black {
            background: #0f172a;
            color: #ffffff;
        }
        .mono-box img {
            max-height: 44px;
            max-width: 70%;
            object-fit: contain;
        }

        /* 10. Email Signature */
        .email-compose-window {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .ecw-header {
            background: #f8fafc;
            padding: 0.6rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
        }
        .ecw-body {
            padding: 1.25rem;
            font-size: 0.88rem;
            line-height: 1.5;
            color: #334155;
        }
        .email-signature-block {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .sig-logo-img {
            max-height: 38px;
            object-fit: contain;
        }
        .sig-meta {
            border-left: 2px solid var(--sys-primary);
            padding-left: 0.85rem;
            font-size: 0.8rem;
            line-height: 1.4;
        }

        /* 11. Business Cards Isometric */
        .cards-stage-iso {
            height: 200px;
            position: relative;
            perspective: 800px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-iso-front, .card-iso-back {
            width: 240px;
            height: 140px;
            border-radius: 12px;
            position: absolute;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.25);
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s;
        }
        .card-iso-front {
            background: var(--brand-primary);
            color: white;
            transform: rotateX(15deg) rotateY(-20deg) rotateZ(3deg) translate(-25px, -15px);
            z-index: 1;
        }
        .card-iso-front img {
            max-height: 36px;
            filter: brightness(0) invert(1);
            object-fit: contain;
        }
        .card-iso-back {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            transform: rotateX(15deg) rotateY(-20deg) rotateZ(3deg) translate(35px, 20px);
            z-index: 2;
        }
        .card-iso-back img {
            max-height: 26px;
            object-fit: contain;
        }

        /* ================= PROPOSALS SLIDE (PITCH MODE) ================= */
        .slide-frame[data-slide="proposals"] {
            justify-content: flex-start !important;
            gap: 1rem !important;
        }
        .slide-frame[data-slide="proposals"] .sh-top {
            margin-bottom: 0.25rem !important;
            flex-shrink: 0 !important;
        }
        .prop-nav-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.25rem;
            margin-bottom: 1.25rem;
            overflow-x: auto;
            padding: 0.35rem 0.25rem 0.65rem;
            scrollbar-width: thin;
            flex-shrink: 0 !important;
            min-height: 52px;
            position: relative;
            z-index: 20;
        }
        .prop-tab-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 0.6rem 1.15rem;
            border-radius: 9999px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
            user-select: none;
            flex-shrink: 0;
        }
        [data-theme="light"] .prop-tab-pill {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #64748b;
        }
        .prop-tab-pill:hover {
            border-color: #f59e0b;
            color: #f8fafc;
            transform: translateY(-1px);
        }
        [data-theme="light"] .prop-tab-pill:hover {
            color: #0f172a;
        }
        .prop-tab-pill.active {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.25) 100%);
            border-color: #f59e0b;
            color: #fbbf24;
            box-shadow: 0 4px 14px -2px rgba(245, 158, 11, 0.3);
        }
        [data-theme="light"] .prop-tab-pill.active {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.2) 100%);
            border-color: #d97706;
            color: #b45309;
        }
        .prop-pill-num {
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.08);
            font-weight: 800;
        }
        .prop-tab-pill.active .prop-pill-num {
            background: #f59e0b;
            color: #0f172a;
        }

        .prop-view-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
            align-items: stretch;
            flex-shrink: 0;
            width: 100%;
        }
        @media (max-width: 992px) {
            .prop-view-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .prop-showcase-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            position: relative;
        }
        [data-theme="light"] .prop-showcase-card {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
        }

        .prop-stage-canvas {
            min-height: 280px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: background 0.3s;
        }
        .prop-stage-canvas.bg-light {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .prop-stage-canvas.bg-dark {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .prop-stage-canvas.bg-blueprint {
            background: #0b1e38;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.12) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.12) 1px, transparent 1px);
            background-size: 20px 20px;
            border: 1px solid rgba(59, 130, 246, 0.25);
        }
        .prop-stage-img {
            max-height: 190px;
            max-width: 90%;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.12));
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .prop-stage-img:hover {
            transform: scale(1.04);
        }

        .prop-stage-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .prop-stage-toggle-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            padding: 0.35rem 0.75rem;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        [data-theme="light"] .prop-stage-toggle-btn {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
        }
        .prop-stage-toggle-btn.active, .prop-stage-toggle-btn:hover {
            background: var(--sys-primary);
            border-color: var(--sys-primary);
            color: #ffffff;
        }

        .prop-info-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        [data-theme="light"] .prop-info-card {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
        }

        .prop-winner-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25));
            border: 1px solid #10b981;
            color: #10b981;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .prop-mockup-preview-box {
            background: #000000;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 1.25rem;
            max-height: 180px;
        }
        .prop-mockup-preview-box img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: transform 0.4s;
        }
        .prop-mockup-preview-box img:hover {
            transform: scale(1.05);
        }

        /* 12. Letterhead */
        .letterhead-mockup {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 200px;
        }
        .lh-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.75rem;
        }
        .lh-header img {
            max-height: 28px;
            object-fit: contain;
        }
        .lh-lines {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin: 1.25rem 0;
        }
        .lh-line {
            height: 6px;
            border-radius: 4px;
            background: #f1f5f9;
        }
        .lh-footer {
            border-top: 1px solid #f1f5f9;
            padding-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        /* DOTS INDICATOR */
        .deck-dots-bar {
            position: absolute;
            bottom: 75px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 60;
        }
        .deck-dot {
            width: 9px;
            height: 9px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.25s;
        }
        .deck-dot.active {
            width: 28px;
            background: var(--sys-primary);
            box-shadow: 0 0 10px rgba(38, 46, 207, 0.7);
        }

        /* TOAST COPIED */
        .copy-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #10b981;
            color: white;
            padding: 0.85rem 1.6rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.5);
            display: none;
            align-items: center;
            gap: 0.6rem;
            z-index: 10000;
            animation: toastPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes toastPop {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* CONTINUOUS SCROLL MODE STYLES */
        .scroll-mode-container {
            display: none;
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem 6rem;
            gap: 3.5rem;
            flex-direction: column;
        }
        .scroll-section {
            background: var(--surface-dark);
            border: 1px solid var(--surface-border);
            border-radius: 28px;
            padding: 3.5rem 4rem;
            position: relative;
            box-shadow: 0 20px 50px -10px rgba(0,0,0,0.5);
        }
        [data-theme="light"] .scroll-section {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.05);
        }

        /* FLOATING NAVIGATION DOCK (MOBILE & TABLET CONVENIENCE) */
        .floating-deck-dock {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: none;
            align-items: center;
            gap: 0.85rem;
            padding: 0.45rem 0.85rem;
            background: rgba(13, 16, 26, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 9999px;
            box-shadow: 0 16px 40px -8px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.06);
            z-index: 1000;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s;
        }
        [data-theme="light"] .floating-deck-dock {
            background: rgba(255, 255, 255, 0.94);
            border-color: rgba(203, 213, 225, 0.9);
            box-shadow: 0 16px 40px -8px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.04);
        }
        .dock-nav-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        [data-theme="light"] .dock-nav-btn {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .dock-nav-btn:hover:not(:disabled) {
            background: var(--sys-primary);
            border-color: var(--sys-primary);
            color: #ffffff;
            transform: scale(1.05);
        }
        .dock-nav-btn:active:not(:disabled) {
            transform: scale(0.95);
        }
        .dock-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .dock-counter-badge {
            font-family: 'Plus Jakarta Sans', monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: #ffffff;
            padding: 0.35rem 0.85rem;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 1px;
            white-space: nowrap;
        }
        [data-theme="light"] .dock-counter-badge {
            color: #0f172a;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        /* ================= FULL RESPONSIVE MEDIA QUERIES ================= */
        @media (max-width: 1200px) {
            .deck-canvas-16-9 {
                aspect-ratio: auto;
                min-height: calc(100vh - 120px);
                max-height: none;
            }
            /* FIX FOR GHOST SLIDES VOID ON TABLET & MOBILE */
            .slide-frame {
                position: relative;
                height: auto;
                min-height: calc(100vh - 180px);
                padding: 2.5rem 2.25rem 6.5rem;
            }
            .slide-frame:not(.active) {
                display: none !important;
            }
            .slide-frame.active {
                display: flex !important;
                animation: slideFadeInMobile 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            @keyframes slideFadeInMobile {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .grid-4 { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
            .grid-3 { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        }

        @media (max-width: 900px) {
            .deck-header {
                padding: 0.65rem 1rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                position: sticky;
                top: 0;
            }
            .dh-brand {
                gap: 0.55rem;
                flex-shrink: 1;
                min-width: 0;
            }
            .dh-brand-badge { display: none; }
            .dh-brand-name {
                font-size: 0.95rem;
                max-width: 140px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .dh-logo-preview {
                max-height: 24px;
                max-width: 50px;
            }
            
            /* Center Navigation Controls in header */
            .dh-nav-controls {
                display: flex !important;
                gap: 0.4rem;
                align-items: center;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                z-index: 10;
            }
            .nav-btn {
                width: 36px;
                height: 36px;
                font-size: 1.05rem;
                border-radius: 9px;
            }
            .slide-counter-badge {
                font-size: 0.85rem;
                padding: 0.35rem 0.75rem;
                border-radius: 9px;
                min-width: 68px;
                text-align: center;
            }

            /* Right actions on mobile: only Theme toggle + More options dropdown */
            .dh-actions {
                gap: 0.35rem;
                flex-shrink: 0;
                position: relative;
            }
            .desktop-action-btn {
                display: none !important;
            }
            .mobile-only-btn {
                display: inline-flex !important;
                padding: 0.55rem 0.75rem;
                font-size: 1.15rem;
            }
            .btn-theme-toggle {
                padding: 0.55rem 0.75rem;
                font-size: 1.15rem;
            }

            /* Hide bottom floating dock because navigation is centered in top header */
            .floating-deck-dock {
                display: none !important;
            }

            .deck-stage-wrapper {
                padding: 0.5rem;
            }
            .deck-canvas-16-9 {
                border-radius: 18px;
            }
            .slide-frame {
                padding: 1.5rem 1.15rem 4.5rem;
            }
            
            /* Optimal Badges & Typography on mobile */
            .cover-kicker {
                font-size: 0.75rem;
                padding: 0.35rem 0.85rem;
                margin-bottom: 1.25rem;
                letter-spacing: 1.2px;
            }
            .cover-title {
                font-size: clamp(2rem, 6.5vw, 3.2rem);
                letter-spacing: -1px;
                line-height: 1.12;
            }
            .cover-tagline {
                font-size: 1rem;
                margin-bottom: 1.75rem;
                line-height: 1.45;
                padding: 0 0.5rem;
            }
            .cover-logo-stage-dark {
                padding: 1.5rem 2.25rem;
                border-radius: 22px;
                max-width: 90%;
            }
            .cover-logo-stage-dark img {
                max-height: 80px;
                max-width: 100%;
            }

            .sh-kicker {
                font-size: 0.75rem;
                letter-spacing: 1.6px;
            }
            .sh-title {
                font-size: clamp(1.4rem, 4.5vw, 1.85rem);
                line-height: 1.25;
            }
            .sh-top {
                margin-bottom: 1.35rem;
                padding-bottom: 0.75rem;
            }
            .sh-brand-badge {
                font-size: 0.75rem;
            }

            /* Grids */
            .grid-4, .grid-3, .grid-2 {
                grid-template-columns: 1fr;
                gap: 1.75rem;
            }

            /* Color cards spacing and layout */
            .color-card-pro {
                margin-bottom: 0.75rem;
            }
            .color-swatch-main {
                height: 125px;
            }
            .color-info-pane {
                padding: 1.25rem;
                gap: 0.6rem;
            }
            .color-copy-row {
                padding: 0.5rem 0.75rem;
            }

            /* Watermark footer on mobile */
            .slide-watermark-footer {
                padding: 0 1rem;
                font-size: 0.75rem;
                height: 50px;
            }
            .wm-left-col {
                gap: 0.5rem;
            }
            .wm-agency-logo {
                max-height: 20px;
                max-width: 80px;
            }
            .wm-agency-name {
                font-size: 0.75rem;
                letter-spacing: 1px;
            }
            .wm-social-item span {
                display: none;
            }
            .wm-right-col {
                gap: 0.75rem;
            }
            .deck-dots-bar {
                display: none;
            }

            /* Mockups responsive styles */
            .live-mockup-card {
                padding: 1.35rem;
                border-radius: 18px;
            }
            .desktop-nav-mockup .nav-mockup-bar {
                padding: 0.55rem 0.75rem;
                gap: 0.45rem;
            }
            .desktop-nav-mockup .nav-mockup-logo img {
                max-height: 18px;
            }
            .desktop-nav-mockup .nav-mockup-links {
                gap: 0.5rem;
                font-size: 0.7rem;
            }
            .desktop-nav-mockup .nav-mockup-links span:nth-child(2) {
                display: none; /* Hide middle link so navigation stays clean without colliding */
            }
            .desktop-nav-mockup .nav-mockup-cta {
                font-size: 0.68rem;
                padding: 0.28rem 0.6rem;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .desktop-nav-mockup .nav-mockup-hero {
                padding: 0.95rem;
                gap: 0.65rem;
            }
            .desktop-nav-mockup .nav-mockup-hero > div:first-child {
                flex: 1;
                min-width: 0;
            }
            .desktop-nav-mockup .nav-mockup-hero > div:last-child {
                width: 42px !important;
                height: 42px !important;
                flex-shrink: 0;
            }

            .cards-stage-iso {
                perspective: 500px;
                height: 150px;
            }
            .card-iso-front, .card-iso-back {
                width: 175px;
                height: 110px;
                padding: 0.65rem;
            }
            .card-iso-front {
                transform: rotateX(12deg) rotateY(-18deg) rotateZ(2deg) translate(-15px, -5px);
            }
            .card-iso-back {
                transform: rotateX(12deg) rotateY(-18deg) rotateZ(2deg) translate(15px, 10px);
            }

            .dock-shelf-mockup {
                padding: 1.25rem 0.85rem;
            }
            .glass-dock {
                gap: 0.5rem;
                padding: 0.5rem 0.75rem;
            }
            .dock-app-icon {
                width: 40px;
                height: 40px;
            }
            .dock-app-icon img {
                width: 22px;
                height: 22px;
            }

            .blueprint-card {
                padding: 1.5rem 1rem;
                min-height: 260px;
            }
            .blueprint-logo-box {
                padding: 24px 16px;
            }
            .blueprint-logo-box img {
                max-height: 70px;
                max-width: 100%;
            }

            /* Scroll mode on tablet */
            .scroll-mode-container {
                padding: 1.5rem 1rem 6rem;
                gap: 2rem;
            }
            .scroll-section {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
        }

        @media (max-width: 600px) {
            .deck-header {
                padding: 0.55rem 0.75rem;
            }
            .dh-brand-name {
                display: none; /* Hide brand name text on ultra-narrow screens to give maximum room to centered navigation */
            }
            .dh-logo-preview {
                max-height: 22px;
                max-width: 40px;
            }
            .cover-kicker {
                font-size: 0.72rem;
                padding: 0.3rem 0.75rem;
                margin-bottom: 1rem;
            }
            .cover-logo-stage-dark {
                padding: 1.25rem 1.5rem;
                border-radius: 20px;
            }
            .cover-logo-stage-dark img {
                max-height: 65px;
            }
            .sh-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
            }
            .sh-brand-badge {
                font-size: 0.75rem;
            }
            .color-swatch-main {
                height: 110px;
            }
            .live-mockup-card {
                padding: 1.15rem;
                border-radius: 16px;
            }
            .cards-stage-iso {
                transform: scale(0.9);
                height: 140px;
            }
            .dock-shelf-mockup {
                padding: 1.15rem 0.75rem;
            }
            .glass-dock {
                gap: 0.45rem;
                padding: 0.45rem 0.65rem;
            }
            .dock-app-icon {
                width: 38px;
                height: 38px;
            }
            .dock-app-icon img {
                width: 20px;
                height: 20px;
            }
            .glass-card input[type="text"] {
                font-size: 16px !important; /* Prevents auto-zoom on iOS */
            }
            .font-specimen-card {
                padding: 1.25rem;
            }
            .font-alphabet-stage {
                font-size: 0.95rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

    <!-- Internal CRM Top Bar (Only visible if logged in) -->
    <?php if ($isLoggedIn): ?>
    <div class="admin-top-bar">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <span style="color:#94a3b8;"><i class="ph-bold ph-shield-check"></i> Manual de Marca CRM</span>
            <span style="opacity:0.3;">|</span>
            <span style="font-weight:700; color:white;"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
            <span style="font-size:0.75rem; background:rgba(255,255,255,0.08); padding:0.15rem 0.5rem; border-radius:6px; color:#cbd5e1;">
                <?php echo $bg['is_public'] == 1 ? 'Público' : 'Privado'; ?>
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:0.6rem;">
            <a href="index.php?module=brand_guidelines&action=edit&id=<?php echo $guidelineId; ?>" class="btn-edit-manual">
                <i class="ph-bold ph-pencil-simple"></i> Editar Manual
            </a>
            <a href="index.php?module=brand_guidelines&action=index">
                <i class="ph-bold ph-squares-four"></i> Panel CRM
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Deck Header & Navigation Bar -->
    <header class="deck-header">
        <div class="dh-brand">
            <?php if (!empty($logoDarkUrl)): ?>
                <img src="<?php echo htmlspecialchars($logoDarkUrl); ?>" alt="Logo Dark" class="dh-logo-preview dh-logo-dark">
            <?php endif; ?>
            <?php if (!empty($logoPrimaryUrl)): ?>
                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Logo Light" class="dh-logo-preview dh-logo-light">
            <?php endif; ?>
            <span class="dh-brand-name"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
            <span class="dh-brand-badge">Brand Guidelines 16:9</span>
        </div>

        <div class="dh-nav-controls" id="slideDeckControls">
            <button class="nav-btn" onclick="prevSlide()" id="prevBtn" title="Diapositiva Anterior (Flecha Izquierda)"><i class="ph-bold ph-caret-left"></i></button>
            <div class="slide-counter-badge" id="slideIndicator">01 / 10</div>
            <button class="nav-btn" onclick="nextSlide()" id="nextBtn" title="Siguiente Diapositiva (Flecha Derecha)"><i class="ph-bold ph-caret-right"></i></button>
        </div>

        <div class="dh-actions">
            <button class="btn-action btn-theme-toggle" id="themeToggleBtn" onclick="toggleDeckTheme()" title="Modo Claro / Modo Oscuro">
                <i class="ph-bold ph-sun" id="themeIcon"></i>
            </button>
            
            <!-- Desktop Action Buttons -->
            <button class="btn-action btn-share desktop-action-btn" onclick="copyShareLink()" title="Copiar enlace amigable">
                <i class="ph-bold ph-share-network"></i> <span>Compartir</span>
            </button>
            <button class="btn-action btn-toggle-view desktop-action-btn" id="viewModeBtn" onclick="toggleViewMode()" title="Cambiar a Vista Scroll">
                <i class="ph-bold ph-rows"></i> <span>Scroll</span>
            </button>
            <a href="<?php echo htmlspecialchars($pdfDownloadUrl); ?>" class="btn-action btn-pdf desktop-action-btn" title="Descargar Manual en PDF 1980x1080">
                <i class="ph-bold ph-file-pdf"></i> <span>Descargar PDF</span>
            </a>
            <button class="btn-action btn-fullscreen desktop-action-btn" onclick="toggleFullscreen()" title="Pantalla Completa (F)">
                <i class="ph-bold ph-arrows-out"></i>
            </button>

            <!-- Mobile More Actions Toggle Button -->
            <button class="btn-action btn-more-actions mobile-only-btn" id="mobileMoreBtn" onclick="toggleMobileActionsMenu(event)" title="Más Opciones">
                <i class="ph-bold ph-dots-three-vertical"></i>
            </button>

            <!-- Mobile Actions Dropdown Popover -->
            <div class="mobile-actions-dropdown" id="mobileActionsDropdown">
                <a href="<?php echo htmlspecialchars($pdfDownloadUrl); ?>" class="mad-item" onclick="closeMobileActionsMenu()">
                    <i class="ph-bold ph-file-pdf"></i> <span>Descargar PDF (1980x1080)</span>
                </a>
                <button type="button" class="mad-item" onclick="copyShareLink(); closeMobileActionsMenu();">
                    <i class="ph-bold ph-share-network"></i> <span>Compartir Manual</span>
                </button>
                <button type="button" class="mad-item" onclick="toggleViewMode(); closeMobileActionsMenu();">
                    <i class="ph-bold ph-rows"></i> <span id="madViewText">Modo Scroll Continuo</span>
                </button>
                <button type="button" class="mad-item" onclick="toggleFullscreen(); closeMobileActionsMenu();">
                    <i class="ph-bold ph-arrows-out"></i> <span>Pantalla Completa</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Deck Presentation Stage (16:9 Widescreen) -->
    <main class="deck-stage-wrapper" id="deckStage">
        <div class="deck-canvas-16-9" id="canvas169">
            
            <div class="slides-track" id="slidesTrack">
                
                <!-- ================= SLIDE 1: PORTADA CON DEGRADADO DEL SISTEMA & LOGO DARK ================= -->
                <div class="slide-frame slide-cover active" data-slide="1">
                    <div>
                        <div class="cover-kicker"><i class="ph-bold ph-sparkle"></i> Brand Identity Guidelines • 1980 x 1080</div>
                        <h1 class="cover-title"><?php echo htmlspecialchars($bg['brand_name']); ?></h1>
                        <?php if (!empty($bg['tagline'])): ?>
                            <div class="cover-tagline">"<?php echo htmlspecialchars($bg['tagline']); ?>"</div>
                        <?php endif; ?>

                        <!-- Showing the dark-version logo specifically for dark background -->
                        <div class="cover-logo-stage-dark">
                            <?php if (!empty($logoDarkUrl)): ?>
                                <img src="<?php echo htmlspecialchars($logoDarkUrl); ?>" alt="<?php echo htmlspecialchars($bg['brand_name']); ?>">
                            <?php else: ?>
                                <h2 style="font-size:2.5rem; font-weight:900; color:#ffffff; margin:0;"><?php echo htmlspecialchars($bg['brand_name']); ?></h2>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($bg['client_name'])): ?>
                            <div style="margin-top:1.75rem; font-size:0.9rem; color:#94a3b8; letter-spacing:1.5px; text-transform:uppercase;">
                                Cliente Oficial: <strong style="color:white;"><?php echo htmlspecialchars($bg['client_name']); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($hasActiveProposals): ?>
                <!-- ================= SLIDE PITCH: PROPUESTAS DE LOGOTIPO ================= -->
                <div class="slide-frame" data-slide="proposals">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker" style="color: #f59e0b; display: inline-flex; align-items: center; gap: 0.4rem;">
                                <i class="ph-bold ph-lightbulb"></i> Pitch Creativo • Propuestas de Diseño
                            </div>
                            <h2 class="sh-title">Exploración Conceptual de Logotipo</h2>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <span style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b; font-size: 0.78rem; font-weight: 800; padding: 0.35rem 0.85rem; border-radius: 9999px;">
                                Modo Pitch Activo
                            </span>
                            <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                        </div>
                    </div>

                    <!-- Proposal Selector Navigation Pills -->
                    <div class="prop-nav-bar" id="proposalNavBar">
                        <?php foreach ($proposals as $idx => $prop): 
                            $pTitle = !empty($prop['title']) ? $prop['title'] : ('Opción ' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT));
                            $isWin = !empty($prop['is_selected']);
                        ?>
                        <button type="button" class="prop-tab-pill <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="selectProposalView(<?php echo $idx; ?>)" id="propTabBtn_<?php echo $idx; ?>">
                            <span class="prop-pill-num"><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?></span>
                            <span><?php echo htmlspecialchars($pTitle); ?></span>
                            <?php if ($isWin): ?>
                                <i class="ph-bold ph-trophy" style="color: #10b981;" title="Propuesta Seleccionada"></i>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Proposal Content Cards (One shown at a time) -->
                    <?php foreach ($proposals as $idx => $prop): 
                        $pTitle = !empty($prop['title']) ? $prop['title'] : ('Opción ' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT));
                        $pConcept = $prop['concept'] ?? '';
                        $pLogoUrl = !empty($prop['logo_url']) ? bg_asset_url($prop['logo_url']) : '';
                        $pLogoDarkUrl = !empty($prop['logo_dark_url']) ? bg_asset_url($prop['logo_dark_url']) : '';
                        $pLogoGridUrl = !empty($prop['logo_grid_url']) ? bg_asset_url($prop['logo_grid_url']) : '';
                        $pMockupUrl = !empty($prop['mockup_url']) ? bg_asset_url($prop['mockup_url']) : '';
                        $isWin = !empty($prop['is_selected']);

                        $imgLight = $pLogoUrl;
                        $imgDark = !empty($pLogoDarkUrl) ? $pLogoDarkUrl : $pLogoUrl;
                        $imgGrid = !empty($pLogoGridUrl) ? $pLogoGridUrl : $pLogoUrl;
                    ?>
                    <div class="prop-view-grid proposal-content-panel" id="proposalPanel_<?php echo $idx; ?>" style="<?php echo $idx === 0 ? '' : 'display:none;'; ?>">
                        
                        <!-- Left Column: Visual Showcase Stage -->
                        <div class="prop-showcase-card">
                            <div class="prop-stage-controls">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span style="font-size:0.78rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Entorno de visualización:</span>
                                </div>
                                <div style="display:flex; gap:0.4rem;">
                                    <button type="button" class="prop-stage-toggle-btn active" onclick="setProposalCanvasBg(<?php echo $idx; ?>, 'light', this)" title="Ver versión sobre fondo claro">
                                        <i class="ph-bold ph-sun"></i> Claro
                                    </button>
                                    <button type="button" class="prop-stage-toggle-btn" onclick="setProposalCanvasBg(<?php echo $idx; ?>, 'dark', this)" title="Ver versión sobre fondo oscuro">
                                        <i class="ph-bold ph-moon"></i> Oscuro
                                        <?php if (!empty($pLogoDarkUrl)): ?>
                                            <span style="font-size:0.62rem; background:#818cf8; color:#0f172a; padding:1px 5px; border-radius:4px; font-weight:800; margin-left:2px;">HQ</span>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" class="prop-stage-toggle-btn" onclick="setProposalCanvasBg(<?php echo $idx; ?>, 'blueprint', this)" title="Ver construcción con retícula / blueprint">
                                        <i class="ph-bold ph-grid-four"></i> Retícula
                                        <?php if (!empty($pLogoGridUrl)): ?>
                                            <span style="font-size:0.62rem; background:#0ea5e9; color:#0f172a; padding:1px 5px; border-radius:4px; font-weight:800; margin-left:2px;">HQ</span>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>

                            <div class="prop-stage-canvas bg-light" id="propCanvas_<?php echo $idx; ?>">
                                <?php if (!empty($imgLight)): ?>
                                    <img src="<?php echo htmlspecialchars($imgLight); ?>" alt="<?php echo htmlspecialchars($pTitle); ?>" class="prop-stage-img" id="propStageImg_<?php echo $idx; ?>"
                                         data-img-light="<?php echo htmlspecialchars($imgLight); ?>"
                                         data-img-dark="<?php echo htmlspecialchars($imgDark); ?>"
                                         data-img-grid="<?php echo htmlspecialchars($imgGrid); ?>">
                                <?php else: ?>
                                    <div style="text-align:center; color:var(--text-muted); padding:2rem;">
                                        <i class="ph-bold ph-paint-brush" style="font-size:3rem; opacity:0.4; margin-bottom:0.5rem;"></i>
                                        <div style="font-weight:700;">Sin archivo de logotipo adjunto</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($pMockupUrl)): ?>
                            <div style="margin-top: 0.5rem;">
                                <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                                    <i class="ph-bold ph-device-mobile"></i> Mockup en Contexto Real
                                </div>
                                <div class="prop-mockup-preview-box">
                                    <img src="<?php echo htmlspecialchars($pMockupUrl); ?>" alt="Mockup <?php echo htmlspecialchars($pTitle); ?>" loading="lazy">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column: Creative Rationale & Concept -->
                        <div class="prop-info-card">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-bottom:0.75rem; flex-wrap:wrap;">
                                    <span style="font-size:0.8rem; font-weight:800; color:#f59e0b; letter-spacing:1px; text-transform:uppercase;">
                                        Propuesta <?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <?php if ($isWin): ?>
                                    <span class="prop-winner-badge">
                                        <i class="ph-bold ph-check-circle"></i> Opción Ganadora Oficial
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <h3 style="font-size: 1.6rem; font-weight: 900; margin-bottom: 1rem; color: var(--text-main);">
                                    <?php echo htmlspecialchars($pTitle); ?>
                                </h3>

                                <div class="glass-card" style="border-left: 4px solid #f59e0b; margin-bottom: 1.25rem;">
                                    <div class="card-label" style="color: #f59e0b;"><i class="ph-bold ph-lightbulb-filament"></i> Racional Creativo & Fundamento Visual</div>
                                    <div class="card-body" style="font-size: 0.95rem; line-height: 1.7; color: var(--text-main);">
                                        <?php echo !empty($pConcept) ? nl2br(htmlspecialchars($pConcept)) : '<em>Esta propuesta representa la identidad visual sintetizada en base a los valores y objetivos estratégicos de la marca.</em>'; ?>
                                    </div>
                                </div>

                                <div style="background: rgba(255, 255, 255, 0.04); border: 1px dashed rgba(255, 255, 255, 0.12); border-radius: 16px; padding: 1rem 1.25rem;">
                                    <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase;">Criterios de Evaluación:</div>
                                    <ul style="font-size: 0.84rem; color: var(--text-muted); margin: 0; padding-left: 1.2rem; line-height: 1.6;">
                                        <li>Legibilidad y reproducción en diversos tamaños (digital e impreso).</li>
                                        <li>Conexión con el público objetivo y propuesta de valor de la marca.</li>
                                        <li>Distinción frente a la competencia del sector.</li>
                                    </ul>
                                </div>
                            </div>

                            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                                <div style="font-size:0.78rem; color:var(--text-muted);">
                                    ¿Deseas elegir esta opción? Comunícate con nuestro equipo creativo.
                                </div>
                                <?php if (!empty($imgLight)): ?>
                                <a href="<?php echo htmlspecialchars($imgLight); ?>" download id="propDownloadBtn_<?php echo $idx; ?>" class="prop-stage-toggle-btn" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem;">
                                    <i class="ph-bold ph-download-simple"></i> Descargar Asset
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- ================= SLIDE 2: FILOSOFÍA & ADN ================= -->
                <div class="slide-frame" data-slide="2">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">01 / Fundamentos</div>
                            <h2 class="sh-title">Filosofía & Esencia de Marca</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-2">
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <?php if (!empty($bg['description'])): ?>
                            <div class="glass-card" style="border-left: 4px solid var(--brand-primary);">
                                <div class="card-label" style="color:var(--brand-primary);"><i class="ph-bold ph-target"></i> Propósito & Acerca de la Marca</div>
                                <div class="card-body"><?php echo nl2br(htmlspecialchars($bg['description'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($bg['tone_of_voice'])): ?>
                            <div class="glass-card" style="border-left: 4px solid #8b5cf6;">
                                <div class="card-label" style="color:#8b5cf6;"><i class="ph-bold ph-chat-circle-dots"></i> Tono de Voz & Comunicación</div>
                                <div class="card-body"><?php echo htmlspecialchars($bg['tone_of_voice']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <?php if (!empty($bg['mission'])): ?>
                            <div class="glass-card" style="border-left: 4px solid #10b981;">
                                <div class="card-label" style="color:#10b981;"><i class="ph-bold ph-compass"></i> Misión Corporativa</div>
                                <div class="card-body"><?php echo nl2br(htmlspecialchars($bg['mission'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($bg['vision'])): ?>
                            <div class="glass-card" style="border-left: 4px solid #0ea5e9;">
                                <div class="card-label" style="color:#0ea5e9;"><i class="ph-bold ph-binoculars"></i> Visión Estratégica</div>
                                <div class="card-body"><?php echo nl2br(htmlspecialchars($bg['vision'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($values)): ?>
                            <div class="glass-card" style="border-left: 4px solid #ec4899;">
                                <div class="card-label" style="color:#ec4899;"><i class="ph-bold ph-heart"></i> Valores Fundamentales</div>
                                <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.5rem;">
                                    <?php foreach ($values as $v): 
                                        $vName = is_array($v) ? ($v['name'] ?? '') : $v;
                                        if (!empty($vName)):
                                    ?>
                                        <span style="background:rgba(236,72,153,0.15); color:#f472b6; border:1px solid rgba(236,72,153,0.3); padding:0.35rem 0.85rem; border-radius:10px; font-size:0.85rem; font-weight:700;">
                                            <?php echo htmlspecialchars($vName); ?>
                                        </span>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ================= SLIDE 3: LOGOTIPO PRINCIPAL & VERSIONES ================= -->
                <div class="slide-frame" data-slide="3">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">02 / Identidad Visual</div>
                            <h2 class="sh-title">Logotipo Principal & Versiones</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-<?php echo !empty($bg['logo_symbol']) ? '3' : '2'; ?>">
                        <!-- Fondo Claro -->
                        <div class="logo-display-card">
                            <div class="logo-img-container">
                                <?php if (!empty($logoPrimaryUrl)): ?>
                                    <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Logo Claro">
                                <?php else: ?>
                                    <span style="font-weight:700; color:#64748b;">Logo Principal</span>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:center;">
                                <div class="logo-card-title">Versión Oficial Fondo Claro</div>
                                <div class="logo-card-desc">Uso prioritario en fondos blancos o neutros claros.</div>
                            </div>
                        </div>

                        <!-- Fondo Oscuro -->
                        <div class="logo-display-card dark">
                            <div class="logo-img-container">
                                <?php if (!empty($logoDarkUrl)): ?>
                                    <img src="<?php echo htmlspecialchars($logoDarkUrl); ?>" alt="Logo Negativo">
                                <?php elseif (!empty($logoPrimaryUrl)): ?>
                                    <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Logo Negativo" style="filter: brightness(0) invert(1);">
                                <?php else: ?>
                                    <span style="font-weight:700; color:#94a3b8;">Logo Negativo</span>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:center;">
                                <div class="logo-card-title">Versión Negativa / Fondo Oscuro</div>
                                <div class="logo-card-desc">Para aplicaciones sobre fondos oscuros o fotográficos.</div>
                            </div>
                        </div>

                        <!-- Isotipo / Símbolo -->
                        <?php if (!empty($bg['logo_symbol'])): ?>
                        <div class="logo-display-card">
                            <div class="logo-img-container">
                                <img src="<?php echo htmlspecialchars(bg_asset_url($bg['logo_symbol'])); ?>" alt="Isotipo">
                            </div>
                            <div style="text-align:center;">
                                <div class="logo-card-title">Isotipo / Símbolo Aislado</div>
                                <div class="logo-card-desc">Favicons, avatares de redes y sellos de marca.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ================= SLIDE 4: RETÍCULA DE CONSTRUCCIÓN & ÁREA DE SEGURIDAD ================= -->
                <div class="slide-frame" data-slide="4">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">03 / Proporciones & Grid</div>
                            <h2 class="sh-title">Retícula & Área de Seguridad</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-2">
                        <!-- Blueprint Stage -->
                        <div class="blueprint-card">
                            <div class="blueprint-logo-box">
                                <div class="bp-bracket-top">[ X ]</div>
                                <div class="bp-bracket-bottom">[ X ]</div>
                                <div class="bp-bracket-left">[ X ]</div>
                                <div class="bp-bracket-right">[ X ]</div>
                                <?php if (!empty($logoPrimaryUrl)): ?>
                                    <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Blueprint Logo">
                                <?php else: ?>
                                    <h3 style="color:#ffffff; font-weight:900; margin:0;"><?php echo htmlspecialchars($bg['brand_name']); ?></h3>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Technical Specs -->
                        <div style="display:flex; flex-direction:column; gap:1.25rem;">
                            <div class="glass-card" style="border-left: 4px solid #38bdf8;">
                                <div class="card-label" style="color:#38bdf8;"><i class="ph-bold ph-brackets-angle"></i> Módulo de Aislamiento "X"</div>
                                <div class="card-body">
                                    <?php echo !empty($bg['safe_zone_rules']) ? nl2br(htmlspecialchars($bg['safe_zone_rules'])) : 'El margen perimetral "X" equivale a la altura proporcional del isotipo o letra base. Ningún texto, titular, margen de página ni elemento gráfico invasivo debe penetrar esta área de protección bajo ninguna circunstancia.'; ?>
                                </div>
                            </div>

                            <div class="glass-card" style="border-left: 4px solid #10b981;">
                                <div class="card-label" style="color:#10b981;"><i class="ph-bold ph-arrows-in"></i> Dimensiones Mínimas de Reproducción</div>
                                <div class="card-body">
                                    <?php echo !empty($bg['min_size_rules']) ? nl2br(htmlspecialchars($bg['min_size_rules'])) : '<strong>Impresión Offset / Digital:</strong> Ancho mínimo de 25mm a 300 DPI.<br><strong>Pantallas Digitales:</strong> Ancho mínimo de 80px para el logotipo completo.<br><strong>Favicons / Avatares:</strong> 16px / 32px utilizando exclusivamente el isotipo.'; ?>
                                </div>
                            </div>

                            <div class="glass-card" style="border-left: 4px solid #f59e0b;">
                                <div class="card-label" style="color:#f59e0b;"><i class="ph-bold ph-grid-four"></i> Retícula Modular</div>
                                <div class="card-body">
                                    Alineación milimétrica con retícula base de 8px para consistencia en interfaces web, móviles, impresos y señalética arquitectónica.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= SLIDE 5: USOS INCORRECTOS VISUALES (NO PERMITIDOS) ================= -->
                <div class="slide-frame" data-slide="5">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">04 / Normativa de Uso</div>
                            <h2 class="sh-title">Usos Incorrectos del Logotipo</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-3" style="row-gap: 1.5rem;">
                        <!-- 1. Deformación / Estiramiento -->
                        <div class="abuse-card">
                            <div class="abuse-stage">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Deformado" style="transform: scaleX(1.4) scaleY(0.65);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No estirar ni condensar</div>
                            <div class="abuse-desc">Nunca alterar las proporciones originales horizontales o verticales.</div>
                        </div>

                        <!-- 2. Alteración de Colores -->
                        <div class="abuse-card">
                            <div class="abuse-stage">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Colores no aprobados" style="filter: hue-rotate(150deg) saturate(3);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No cambiar los colores</div>
                            <div class="abuse-desc">No aplicar tonos o degradados ajenos a la paleta institucional oficial.</div>
                        </div>

                        <!-- 3. Rotación / Inclinación -->
                        <div class="abuse-card">
                            <div class="abuse-stage">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Rotado" style="transform: rotate(-16deg);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No rotar ni inclinar</div>
                            <div class="abuse-desc">El logotipo debe mantenerse siempre estrictamente horizontal a 0°.</div>
                        </div>

                        <!-- 4. Sombras duras / 3D -->
                        <div class="abuse-card">
                            <div class="abuse-stage">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Sombra dura" style="filter: drop-shadow(6px 8px 0px #ef4444);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No añadir sombras o 3D</div>
                            <div class="abuse-desc">Prohibido aplicar biseles, relieves o sombras paralelas desestabilizadoras.</div>
                        </div>

                        <!-- 5. Falta de Contraste -->
                        <div class="abuse-card">
                            <div class="abuse-stage" style="background: #eab308;">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Bajo contraste" style="opacity: 0.45; filter: contrast(0.5);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No usar sin contraste</div>
                            <div class="abuse-desc">Asegurar siempre el contraste óptimo WCAG sobre fondos saturados o complejos.</div>
                        </div>

                        <!-- 6. Recorte / Fragmentación -->
                        <div class="abuse-card">
                            <div class="abuse-stage">
                                <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Recortado" style="clip-path: inset(25% 0 15% 15%);">
                            </div>
                            <span class="abuse-badge"><i class="ph-bold ph-x-circle"></i> Prohibido</span>
                            <div class="abuse-title">No recortar el símbolo</div>
                            <div class="abuse-desc">No mutilar, recortar ni omitir elementos constitutivos de la marca.</div>
                        </div>
                    </div>
                </div>

                <!-- ================= SLIDE 6: SISTEMA CROMÁTICO CON TINTS & CODES ================= -->
                <div class="slide-frame" data-slide="6">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">05 / Colorimetría</div>
                            <h2 class="sh-title">Sistema Cromático Corporativo</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-<?php echo min(max(count($colors), 1), 5); ?>">
                        <?php foreach (array_slice($colors, 0, 5) as $col): 
                            $hex = strtoupper($col['hex'] ?? '#000000');
                            $rgb = !empty($col['rgb']) ? $col['rgb'] : bg_hex_to_rgb($hex)['str'];
                            $cmyk = !empty($col['cmyk']) ? $col['cmyk'] : bg_hex_to_cmyk($hex)['str'];
                            $pantone = !empty($col['pantone']) ? $col['pantone'] : 'Directo';
                            $role = $col['role'] ?? 'Color Oficial';
                        ?>
                        <div class="color-card-pro">
                            <div class="color-swatch-main" style="background-color: <?php echo $hex; ?>;">
                                <span class="color-role-tag"><?php echo htmlspecialchars($role); ?></span>
                                <span style="background:rgba(255,255,255,0.25); backdrop-filter:blur(5px); color:#ffffff; font-size:0.65rem; font-weight:800; padding:2px 6px; border-radius:4px;">100%</span>
                            </div>
                            
                            <!-- Tint Strip (100%, 80%, 60%, 40%, 20%) -->
                            <div class="color-tints-bar">
                                <div class="color-tint-step" style="background-color: <?php echo $hex; ?>; opacity: 1.0;" title="100%"></div>
                                <div class="color-tint-step" style="background-color: <?php echo $hex; ?>; opacity: 0.8;" title="80%"></div>
                                <div class="color-tint-step" style="background-color: <?php echo $hex; ?>; opacity: 0.6;" title="60%"></div>
                                <div class="color-tint-step" style="background-color: <?php echo $hex; ?>; opacity: 0.4;" title="40%"></div>
                                <div class="color-tint-step" style="background-color: <?php echo $hex; ?>; opacity: 0.2;" title="20%"></div>
                            </div>

                            <div class="color-info-pane">
                                <h3 class="color-title-h3"><?php echo htmlspecialchars($col['name'] ?? $hex); ?></h3>
                                
                                <div class="color-copy-row" onclick="copyColorCode('<?php echo $hex; ?>', 'HEX')" title="Copiar código HEX">
                                    <span class="ccr-label">HEX</span>
                                    <span class="ccr-val"><?php echo $hex; ?></span>
                                    <i class="ph-bold ph-copy ccr-icon"></i>
                                </div>
                                <div class="color-copy-row" onclick="copyColorCode('<?php echo htmlspecialchars($rgb); ?>', 'RGB')" title="Copiar código RGB">
                                    <span class="ccr-label">RGB</span>
                                    <span class="ccr-val"><?php echo htmlspecialchars($rgb); ?></span>
                                    <i class="ph-bold ph-copy ccr-icon"></i>
                                </div>
                                <div class="color-copy-row" onclick="copyColorCode('<?php echo htmlspecialchars($cmyk); ?>', 'CMYK')" title="Copiar código CMYK">
                                    <span class="ccr-label">CMYK</span>
                                    <span class="ccr-val"><?php echo htmlspecialchars($cmyk); ?></span>
                                    <i class="ph-bold ph-copy ccr-icon"></i>
                                </div>
                                <div class="color-copy-row" onclick="copyColorCode('<?php echo htmlspecialchars($pantone); ?>', 'PANTONE')" title="Copiar Pantone">
                                    <span class="ccr-label">PMS</span>
                                    <span class="ccr-val"><?php echo htmlspecialchars($pantone); ?></span>
                                    <i class="ph-bold ph-copy ccr-icon"></i>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ================= SLIDE 7: TIPOGRAFÍAS OFICIALES ================= -->
                <div class="slide-frame" data-slide="7">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">06 / Tipografía</div>
                            <h2 class="sh-title">Tipografías Oficiales & Escalas</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-2">
                        <?php 
                        $shownFonts = array_slice($fonts, 0, 2);
                        if (empty($shownFonts)) {
                            $shownFonts = [
                                ['name' => 'Inter', 'role' => 'Titulares y Marca', 'weights' => 'Regular 400, Bold 700', 'usage' => 'Uso principal en títulos, encabezados y destacados.'],
                                ['name' => 'Roboto', 'role' => 'Cuerpo de Texto y Editorial', 'weights' => 'Light 300, Regular 400', 'usage' => 'Textos continuos, párrafos y documentación.']
                            ];
                        }
                        foreach ($shownFonts as $idx => $f): 
                            $isCustom = ($f['source'] ?? '') === 'custom' || !empty($f['file_url']);
                            $fontFamily = !empty($f['name']) ? "'" . addslashes(trim($f['name'])) . "', sans-serif" : 'sans-serif';
                            $weightsList = !empty($f['weights']) ? array_map('trim', explode(',', $f['weights'])) : ['Regular 400', 'Bold 700'];
                        ?>
                        <div class="glass-card font-specimen-card" style="font-family: <?php echo $fontFamily; ?>;">
                            <div class="fsc-header">
                                <div class="fsc-role">
                                    <?php echo htmlspecialchars($f['role'] ?? ($idx == 0 ? 'Tipografía Primaria' : 'Tipografía Secundaria')); ?>
                                </div>
                                <?php if ($isCustom): ?>
                                    <span class="fsc-source-badge custom">
                                        <i class="ph-bold ph-upload-simple"></i> Archivo Tipográfico
                                    </span>
                                <?php else: ?>
                                    <span class="fsc-source-badge google">
                                        <i class="ph-bold ph-google-logo"></i> Google Fonts
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="font-specimen-title">
                                <?php echo htmlspecialchars($f['name']); ?>
                            </div>

                            <div class="font-weights-wrap">
                                <?php foreach ($weightsList as $w): ?>
                                    <span class="font-weight-pill">
                                        <?php echo htmlspecialchars($w); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="font-alphabet-stage">
                                Aa Bb Cc Dd Ee Ff Gg Hh Ii Jj Kk Ll Mm Nn Ññ Oo Pp Qq Rr Ss Tt Uu Vv Ww Xx Yy Zz<br>
                                0 1 2 3 4 5 6 7 8 9 & @ € $ ! ? ( ) [ ]
                            </div>

                            <div class="font-usage-text">
                                <?php echo !empty($f['usage']) ? htmlspecialchars($f['usage']) : 'Tipografía corporativa seleccionada para transmitir la identidad, jerarquía y legibilidad visual de la marca.'; ?>
                            </div>

                            <!-- Interactive Real-time Text Tester -->
                            <input type="text" class="font-tester-field" value="<?php echo htmlspecialchars($bg['brand_name']); ?> — Diseñando identidades que conectan y perduran." placeholder="Escribe aquí para probar la fuente..." title="Prueba escribir cualquier texto">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ================= SLIDE 8: MOCKUPS DIGITALES & NAVEGACIÓN (IMAGES 2 & 3) ================= -->
                <div class="slide-frame" data-slide="8">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">07 / Mockups en Vivo</div>
                            <h2 class="sh-title">Presencia Digital & Navegación Web</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-2">
                        <!-- 01 Browser Tab & Favicon (Image 2) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">01</span> Pestaña de Navegador & Favicon</div>
                                    <i class="ph-bold ph-compass" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="browser-mockup">
                                    <div class="browser-bar">
                                        <div class="traffic-lights">
                                            <div class="traffic-light tl-red"></div>
                                            <div class="traffic-light tl-yellow"></div>
                                            <div class="traffic-light tl-green"></div>
                                        </div>
                                        <div class="browser-tab">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="Favicon">
                                            <span><?php echo htmlspecialchars(strtolower(preg_replace('/[^a-z0-9]/i', '', $bg['brand_name']))); ?>.com</span>
                                        </div>
                                    </div>
                                    <div class="browser-viewport">
                                        <div class="favicon-zoom-box">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="Favicon 4x">
                                        </div>
                                        <div>
                                            <div style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:var(--sys-primary); letter-spacing:1px; margin-bottom:0.2rem;">
                                                INSPECCIÓN DE FAVICON
                                            </div>
                                            <div style="font-size:1.05rem; font-weight:900; color:#0f172a; margin-bottom:0.25rem;">
                                                Fuente 16px mostrada a 4x
                                            </div>
                                            <div style="font-size:0.8rem; color:#64748b; line-height:1.4;">
                                                Verificación de retención y legibilidad de rasgos geométricos en tamaños micro.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>16px browser favicon with enlarged inspection</span>
                                <span class="lmc-tag">Isotipo / Logomark</span>
                            </div>
                        </div>

                        <!-- 02 Social Profile (Image 2) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">02</span> Perfil Social Mobile (Instagram / TikTok)</div>
                                    <i class="ph-bold ph-device-mobile" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="social-phone-frame">
                                    <div class="phone-top-bar">
                                        <span>9:41</span>
                                        <div class="dynamic-island"></div>
                                        <span><i class="ph-bold ph-wifi-high"></i> <i class="ph-bold ph-battery-full"></i></span>
                                    </div>
                                    <div class="social-profile-header">
                                        <div class="social-avatar-circle">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="Avatar">
                                        </div>
                                        <div style="flex:1;">
                                            <div style="font-weight:900; font-size:0.95rem; color:#0f172a; margin-bottom:0.35rem;">
                                                @<?php echo htmlspecialchars(strtolower(preg_replace('/[^a-z0-9]/i', '', $bg['brand_name']))); ?>
                                            </div>
                                            <div class="social-stats">
                                                <div><div class="social-stat-num">128</div><div class="social-stat-lbl">Posts</div></div>
                                                <div><div class="social-stat-num">24.8K</div><div class="social-stat-lbl">Followers</div></div>
                                                <div><div class="social-stat-num">312</div><div class="social-stat-lbl">Following</div></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="font-size:0.8rem; color:#475569; line-height:1.35; margin-bottom:0.75rem;">
                                        <strong><?php echo htmlspecialchars($bg['brand_name']); ?></strong> • Perfil Oficial<br>
                                        <?php echo htmlspecialchars($bg['tagline'] ?: 'Diseño, innovación e identidad estratégica.'); ?>
                                    </div>
                                    <button style="width:100%; background:#f1f5f9; border:none; border-radius:8px; padding:0.4rem; font-size:0.8rem; font-weight:700; color:#0f172a; cursor:pointer;">
                                        Siguiendo
                                    </button>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>88px circular avatar crop context</span>
                                <span class="lmc-tag">Avatar Social</span>
                            </div>
                        </div>

                        <!-- 03 Google Search Snippet (Image 3) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">03</span> Ficha en Resultados de Búsqueda Google</div>
                                    <i class="ph-bold ph-google-logo" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="google-card">
                                    <div class="google-logo-row">
                                        <span class="google-brand-colored">Google</span>
                                        <div class="google-search-bar"><?php echo htmlspecialchars(strtolower($bg['brand_name'])); ?></div>
                                    </div>
                                    <div class="google-snippet">
                                        <div class="google-snippet-avatar">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="Google Favicon">
                                        </div>
                                        <div>
                                            <div class="google-url">https://www.<?php echo htmlspecialchars(strtolower(preg_replace('/[^a-z0-9]/i', '', $bg['brand_name']))); ?>.com</div>
                                            <a href="javascript:void(0)" class="google-link-title"><?php echo htmlspecialchars($bg['brand_name']); ?> — Sitio Web Oficial</a>
                                            <div class="google-desc">
                                                <?php echo htmlspecialchars($bg['description'] ?: 'Estrategia, diseño y tecnología para marcas que lideran su industria con excelencia.'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>48px search-result identity signal</span>
                                <span class="lmc-tag">SEO & SERP</span>
                            </div>
                        </div>

                        <!-- 04 Desktop 1440px Web Navigation (Image 3) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">04</span> Cabecera Web Desktop (Contexto 1440px)</div>
                                    <i class="ph-bold ph-browsers" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="desktop-nav-mockup">
                                    <div class="nav-mockup-bar">
                                        <div class="nav-mockup-logo">
                                            <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Nav Logo">
                                        </div>
                                        <div class="nav-mockup-links">
                                            <span>Nosotros</span>
                                            <span>Servicios</span>
                                            <span>Contacto</span>
                                        </div>
                                        <div class="nav-mockup-cta">Comenzar →</div>
                                    </div>
                                    <div class="nav-mockup-hero">
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:var(--sys-primary); margin-bottom:0.2rem;">
                                                IDEAS / PRODUCTOS / SERVICIOS
                                            </div>
                                            <div style="font-size:1.15rem; font-weight:900; color:#0f172a; line-height:1.2; margin-bottom:0.3rem;">
                                                Construido para lo que viene.
                                            </div>
                                            <div style="font-size:0.75rem; color:#64748b;">Trabajo reflexivo para marcas que exigen más.</div>
                                        </div>
                                        <div style="width:48px; height:48px; border-radius:50%; background:var(--brand-primary); opacity:0.85; flex-shrink:0;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>1440px desktop navigation context</span>
                                <span class="lmc-tag">Primary Lockup</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= SLIDE 9: MOCKUPS MOBILE & APP ICON (IMAGES 3 & 4) ================= -->
                <div class="slide-frame" data-slide="9">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">08 / Mockups en Vivo</div>
                            <h2 class="sh-title">Mobile UI, Icono de App & Retención</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-3">
                        <!-- 05 Mobile Header & 06 App Icon -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">05</span> Cabecera Mobile</div>
                                    <i class="ph-bold ph-device-mobile-camera" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:0.85rem 1rem;">
                                    <div class="phone-top-bar" style="margin-bottom:0.5rem;">
                                        <span>9:41</span>
                                        <div class="dynamic-island" style="width:50px; height:12px;"></div>
                                        <span><i class="ph-bold ph-battery-full"></i></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:0.6rem; margin-bottom:0.75rem;">
                                        <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Mobile Header" style="max-height:22px; object-fit:contain;">
                                        <i class="ph-bold ph-list" style="font-size:1.2rem; color:#0f172a;"></i>
                                    </div>
                                    <div style="text-align:center; padding:1.25rem 0.5rem; background:#f8fafc; border-radius:12px;">
                                        <div style="font-size:0.7rem; font-weight:800; color:var(--sys-primary); text-transform:uppercase;">BIENVENIDO</div>
                                        <div style="font-size:1.1rem; font-weight:900; color:#0f172a; margin-top:0.2rem;">Hecho para el futuro</div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>390px iPhone viewport scale</span>
                                <span class="lmc-tag">Mobile UI</span>
                            </div>
                        </div>

                        <!-- 07 iPhone Dock Icon (Image 4) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">06</span> Dock de iOS & Squircle</div>
                                    <i class="ph-bold ph-app-window" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="dock-shelf-mockup">
                                    <div style="font-size:0.75rem; color:rgba(255,255,255,0.7); margin-bottom:0.85rem; font-weight:600;">iOS Home Dock Experience</div>
                                    <div class="glass-dock">
                                        <div class="dock-app-icon" style="background:#22c55e;"><i class="ph-fill ph-phone" style="color:white; font-size:1.4rem;"></i></div>
                                        <div class="dock-app-icon" style="background:#3b82f6;"><i class="ph-fill ph-chat-circle" style="color:white; font-size:1.4rem;"></i></div>
                                        <div class="dock-app-icon" style="background:#f8fafc;"><i class="ph-bold ph-compass" style="color:#0284c7; font-size:1.4rem;"></i></div>
                                        <!-- Brand Icon -->
                                        <div class="dock-app-icon" style="background:var(--brand-primary);" title="<?php echo htmlspecialchars($bg['brand_name']); ?>">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="App Icon" style="filter: brightness(0) invert(1);">
                                        </div>
                                    </div>
                                    <div style="width:80px; height:4px; background:rgba(255,255,255,0.6); border-radius:4px; margin-top:1.25rem;"></div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>iOS Squircle Dock application</span>
                                <span class="lmc-tag">App Icon</span>
                            </div>
                        </div>

                        <!-- 08 Small size & Monochrome (Image 4) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">07</span> Escalas & Monocromo</div>
                                    <i class="ph-bold ph-shield-check" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div>
                                    <!-- Scale retention -->
                                    <div class="scale-retention-row">
                                        <div class="scale-item">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="width:16px; height:16px; object-fit:contain;">
                                            <span>16px</span>
                                        </div>
                                        <div class="scale-item">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="width:24px; height:24px; object-fit:contain;">
                                            <span>24px</span>
                                        </div>
                                        <div class="scale-item">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="width:32px; height:32px; object-fit:contain;">
                                            <span>32px</span>
                                        </div>
                                        <div class="scale-item">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="width:48px; height:48px; object-fit:contain;">
                                            <span>48px</span>
                                        </div>
                                    </div>

                                    <!-- Monochrome boxes -->
                                    <div class="monochrome-split-row">
                                        <div class="mono-box white">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="filter: brightness(0);" alt="Negro">
                                            <span style="font-size:0.7rem; font-weight:800; color:#64748b;">Black on white</span>
                                        </div>
                                        <div class="mono-box black">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" style="filter: brightness(0) invert(1);" alt="Blanco">
                                            <span style="font-size:0.7rem; font-weight:800; color:#94a3b8;">White on black</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>Tests detail retention without color</span>
                                <span class="lmc-tag">Contraste Puro</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= SLIDE 10: MOCKUPS PAPELERÍA & COMUNICACIÓN (IMAGE 5) ================= -->
                <div class="slide-frame" data-slide="10">
                    <div class="sh-top">
                        <div>
                            <div class="sh-kicker">09 / Identidad Corporativa</div>
                            <h2 class="sh-title">Papelería Institucional & Correo</h2>
                        </div>
                        <span class="sh-brand-badge"><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                    </div>

                    <div class="grid-3">
                        <!-- 10 Email Signature (Image 5) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">08</span> Firma de Correo Oficial</div>
                                    <i class="ph-bold ph-envelope-simple" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="email-compose-window">
                                    <div class="ecw-header">
                                        <div class="traffic-lights">
                                            <div class="traffic-light tl-red"></div>
                                            <div class="traffic-light tl-yellow"></div>
                                            <div class="traffic-light tl-green"></div>
                                        </div>
                                        <span>Nuevo Mensaje</span>
                                        <i class="ph-bold ph-arrows-out-simple"></i>
                                    </div>
                                    <div class="ecw-body">
                                        <div style="color:#64748b; font-size:0.78rem; margin-bottom:0.5rem;">Para: cliente@empresa.com</div>
                                        <div style="color:#64748b; font-size:0.78rem; margin-bottom:0.75rem; border-bottom:1px solid #f1f5f9; padding-bottom:0.35rem;">Asunto: Presentación de Marca</div>
                                        <p style="font-size:0.82rem; margin-bottom:0.85rem;">Hola, adjunto la propuesta de manual de identidad para su revisión.</p>
                                        <div class="email-signature-block">
                                            <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" class="sig-logo-img" alt="Logo Firma">
                                            <div class="sig-meta">
                                                <strong>Equipo Creativo</strong><br>
                                                <span style="color:#64748b; font-size:0.75rem;"><?php echo htmlspecialchars($bg['brand_name']); ?></span><br>
                                                <span style="color:var(--sys-primary); font-size:0.75rem;">contacto@romaagencia.com</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>Compact horizontal communication context</span>
                                <span class="lmc-tag">Email Signature</span>
                            </div>
                        </div>

                        <!-- 11 Business Cards Isometric (Image 5) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">09</span> Tarjetas de Presentación</div>
                                    <i class="ph-bold ph-identification-card" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="cards-stage-iso">
                                    <!-- Front Card -->
                                    <div class="card-iso-front">
                                        <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Front Card">
                                        <div style="font-size:0.72rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; opacity:0.85;">Oficial</div>
                                    </div>
                                    <!-- Back Card -->
                                    <div class="card-iso-back">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <img src="<?php echo htmlspecialchars($logoSymbolUrl); ?>" alt="Back Card">
                                            <div style="font-size:0.65rem; color:#64748b; font-weight:800;">romaagencia.com</div>
                                        </div>
                                        <div>
                                            <div style="font-weight:900; font-size:0.82rem; color:#0f172a;">Director Creativo</div>
                                            <div style="font-size:0.7rem; color:#64748b;">+51 998 289 752</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>Isometric realistic front & reverse cards</span>
                                <span class="lmc-tag">Business Cards</span>
                            </div>
                        </div>

                        <!-- 12 Letterhead (Image 5) -->
                        <div class="live-mockup-card">
                            <div>
                                <div class="lmc-header">
                                    <div class="lmc-number-title"><span class="lmc-num">10</span> Hoja Membretada (A4)</div>
                                    <i class="ph-bold ph-file-text" style="font-size:1.3rem; color:var(--sys-primary);"></i>
                                </div>
                                <div class="letterhead-mockup">
                                    <div class="lh-header">
                                        <img src="<?php echo htmlspecialchars($logoPrimaryUrl); ?>" alt="Letterhead Logo">
                                        <span style="font-size:0.7rem; font-weight:800; color:#64748b; letter-spacing:1px;">DOCUMENTO OFICIAL</span>
                                    </div>
                                    <div class="lh-lines">
                                        <div class="lh-line" style="width:90%;"></div>
                                        <div class="lh-line" style="width:75%;"></div>
                                        <div class="lh-line" style="width:85%;"></div>
                                        <div class="lh-line" style="width:60%;"></div>
                                        <div class="lh-line" style="width:70%;"></div>
                                    </div>
                                    <div class="lh-footer">
                                        <span><?php echo htmlspecialchars($bg['brand_name']); ?></span>
                                        <span>romaagencia.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="lmc-footer-note">
                                <span>A4 Corporate Letterhead layout</span>
                                <span class="lmc-tag">Membretado A4</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Dots Navigation Bar -->
            <div class="deck-dots-bar" id="dotsBar"></div>

            <!-- Pinned Watermark Footer at the bottom of the 16:9 Canvas -->
            <footer class="slide-watermark-footer">
                <div class="wm-left-col">
                    <img src="<?php echo htmlspecialchars($romaLogoDarkUrl); ?>" alt="Roma Agencia" class="wm-agency-logo wm-agency-logo-dark">
                    <img src="<?php echo htmlspecialchars($romaLogoLightUrl); ?>" alt="Roma Agencia" class="wm-agency-logo wm-agency-logo-light">
                    <span class="wm-agency-name"><?php echo htmlspecialchars(strtoupper($sysSiteName)); ?></span>
                </div>
                <div class="wm-right-col">
                    <div class="wm-social-item" title="Instagram Roma Agencia">
                        <i class="ph-bold ph-instagram-logo"></i>
                        <span>@romaagencia</span>
                    </div>
                    <div class="wm-social-item" title="TikTok Roma Agencia">
                        <i class="ph-bold ph-tiktok-logo"></i>
                        <span>@romaagencia</span>
                    </div>
                    <span style="opacity: 0.3;">•</span>
                    <a href="https://romaagencia.com" target="_blank" class="wm-site-link">
                        <i class="ph-bold ph-globe"></i> romaagencia.com
                    </a>
                </div>
            </footer>

        </div>
    </main>
    
    <!-- Floating Bottom Navigation Dock (Optimized for Mobile & Tablet Thumb Reach) -->
    <div class="floating-deck-dock" id="floatingDock" role="navigation" aria-label="Navegación de diapositivas">
        <button class="dock-nav-btn" onclick="prevSlide()" id="dockPrevBtn" title="Diapositiva Anterior" aria-label="Anterior">
            <i class="ph-bold ph-caret-left"></i>
        </button>
        <div class="dock-counter-badge" id="dockSlideIndicator">01 / 10</div>
        <button class="dock-nav-btn" onclick="nextSlide()" id="dockNextBtn" title="Siguiente Diapositiva" aria-label="Siguiente">
            <i class="ph-bold ph-caret-right"></i>
        </button>
    </div>

    <!-- Alternative Continuous Scroll Mode Container -->
    <div class="scroll-mode-container" id="scrollContainer"></div>

    <!-- Toast Notification -->
    <div class="copy-toast" id="copyToast">
        <i class="ph-bold ph-check-circle" style="font-size: 1.25rem;"></i>
        <span id="copyToastMsg">Código copiado al portapapeles</span>
    </div>

    <script>
    // ---------------- SLIDE PRESENTATION ENGINE (1980 x 1080) ----------------
    let currentSlide = 0;
    const slides = Array.from(document.querySelectorAll('.slide-frame'));
    const totalSlides = slides.length;
    const dotsBar = document.getElementById('dotsBar');
    const indicator = document.getElementById('slideIndicator');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dockIndicator = document.getElementById('dockSlideIndicator');
    const dockPrevBtn = document.getElementById('dockPrevBtn');
    const dockNextBtn = document.getElementById('dockNextBtn');
    const floatingDock = document.getElementById('floatingDock');

    // Build Dots
    if (dotsBar) {
        slides.forEach((_, idx) => {
            const dot = document.createElement('div');
            dot.className = 'deck-dot' + (idx === 0 ? ' active' : '');
            dot.title = `Ir a diapositiva ${idx + 1}`;
            dot.onclick = () => goToSlide(idx);
            dotsBar.appendChild(dot);
        });
    // Check URL query param or hash for initial slide
    const urlParams = new URLSearchParams(window.location.search);
    const slideParam = urlParams.get('slide');
    if (slideParam) {
        if (!isNaN(parseInt(slideParam))) {
            const requested = parseInt(slideParam) - 1;
            if (requested >= 0 && requested < totalSlides) {
                currentSlide = requested;
            }
        } else {
            const foundIdx = slides.findIndex(s => s.getAttribute('data-slide') === slideParam);
            if (foundIdx !== -1) currentSlide = foundIdx;
        }
    } else if (window.location.hash) {
        const hashName = window.location.hash.replace('#', '');
        const foundIdx = slides.findIndex(s => s.getAttribute('data-slide') === hashName);
        if (foundIdx !== -1) currentSlide = foundIdx;
    }

    function updateDeckUI() {
        slides.forEach((s, idx) => {
            s.classList.toggle('active', idx === currentSlide);
        });
        if (dotsBar) {
            const dots = dotsBar.querySelectorAll('.deck-dot');
            dots.forEach((d, idx) => {
                d.classList.toggle('active', idx === currentSlide);
            });
        }
        const numStr = (currentSlide + 1).toString().padStart(2, '0');
        const totStr = totalSlides.toString().padStart(2, '0');
        const countText = `${numStr} / ${totStr}`;
        
        if (indicator) indicator.textContent = countText;
        if (prevBtn) prevBtn.disabled = currentSlide === 0;
        if (nextBtn) nextBtn.disabled = currentSlide === totalSlides - 1;

        if (dockIndicator) dockIndicator.textContent = countText;
        if (dockPrevBtn) dockPrevBtn.disabled = currentSlide === 0;
        if (dockNextBtn) dockNextBtn.disabled = currentSlide === totalSlides - 1;
    }
    if (currentSlide !== 0) {
        updateDeckUI();
    }

    function goToSlide(idx) {
        if (idx >= 0 && idx < totalSlides) {
            currentSlide = idx;
            updateDeckUI();

            // Reset scroll position of the newly active slide
            if (slides[currentSlide]) {
                slides[currentSlide].scrollTop = 0;
            }
            
            // On smaller screens, scroll stage smoothly into view
            if (window.innerWidth <= 900) {
                const stage = document.getElementById('canvas169');
                if (stage) {
                    const rect = stage.getBoundingClientRect();
                    if (rect.top < 0 || rect.top > 200) {
                        stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        }
    }

    function prevSlide() {
        if (currentSlide > 0) goToSlide(currentSlide - 1);
    }

    function nextSlide() {
        if (currentSlide < totalSlides - 1) goToSlide(currentSlide + 1);
    }

    // Keyboard Navigation
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
            e.preventDefault();
            nextSlide();
        } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
            e.preventDefault();
            prevSlide();
        } else if (e.key === 'f' || e.key === 'F') {
            toggleFullscreen();
        }
    });

    // Touch Swipe Navigation for Mobile (With Vertical Scroll Discrimination)
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    const stageElement = document.getElementById('canvas169');
    
    if (stageElement) {
        stageElement.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        stageElement.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, { passive: true });
    }

    function handleSwipe() {
        const diffX = touchEndX - touchStartX;
        const diffY = touchEndY - touchStartY;
        // Only trigger if horizontal swipe is clearly more pronounced than vertical scrolling
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 45) {
            if (diffX < 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
    }

    // ---------------- PROPOSALS SLIDE INTERACTIVITY ----------------
    function selectProposalView(idx) {
        document.querySelectorAll('.proposal-content-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.prop-tab-pill').forEach(b => b.classList.remove('active'));

        const activePanel = document.getElementById('proposalPanel_' + idx);
        const activeBtn = document.getElementById('propTabBtn_' + idx);
        if (activePanel) activePanel.style.display = 'grid';
        if (activeBtn) activeBtn.classList.add('active');
    }

    function setProposalCanvasBg(idx, mode, btn) {
        const canvas = document.getElementById('propCanvas_' + idx);
        const img = document.getElementById('propStageImg_' + idx);
        const downloadBtn = document.getElementById('propDownloadBtn_' + idx);
        if (!canvas) return;

        canvas.classList.remove('bg-light', 'bg-dark', 'bg-blueprint');
        canvas.classList.add('bg-' + mode);

        if (img) {
            let newSrc = '';
            if (mode === 'light') {
                newSrc = img.getAttribute('data-img-light');
            } else if (mode === 'dark') {
                newSrc = img.getAttribute('data-img-dark');
            } else if (mode === 'blueprint') {
                newSrc = img.getAttribute('data-img-grid');
            }
            if (newSrc && newSrc !== '') {
                img.src = newSrc;
                if (downloadBtn) {
                    downloadBtn.href = newSrc;
                }
            }
        }

        const parent = btn.parentElement;
        if (parent) {
            parent.querySelectorAll('.prop-stage-toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    }

    // Copy Color To Clipboard
    function copyColorCode(code, type) {
        navigator.clipboard.writeText(code).then(() => {
            showToast(`¡${type} ${code} copiado al portapapeles!`);
        });
    }

    // Copy Friendly Share Link
    function copyShareLink() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            showToast('¡Enlace del manual copiado al portapapeles!');
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('copyToast');
        const text = document.getElementById('copyToastMsg');
        text.textContent = msg;
        toast.style.display = 'flex';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 2500);
    }

    // Toggle Fullscreen
    function toggleFullscreen() {
        const stage = document.getElementById('canvas169');
        if (!document.fullscreenElement) {
            if (stage.requestFullscreen) {
                stage.requestFullscreen();
            } else if (stage.webkitRequestFullscreen) {
                stage.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    // Toggle View Mode (16:9 Presentation vs Continuous Scroll)
    let isScrollMode = false;
    function toggleViewMode() {
        isScrollMode = !isScrollMode;
        const deckStage = document.getElementById('deckStage');
        const scrollContainer = document.getElementById('scrollContainer');
        const deckControls = document.getElementById('slideDeckControls');
        const viewBtn = document.getElementById('viewModeBtn');

        if (isScrollMode) {
            deckStage.style.display = 'none';
            if (deckControls) {
                deckControls.style.opacity = '0.3';
                deckControls.style.pointerEvents = 'none';
            }
            if (floatingDock) floatingDock.style.display = 'none';
            viewBtn.innerHTML = '<i class="ph-bold ph-presentation"></i> <span>Diapositivas</span>';
            const madText = document.getElementById('madViewText');
            if (madText) madText.textContent = 'Modo Diapositivas (16:9)';

            // Clone slides into vertical sections if empty
            if (scrollContainer.children.length === 0) {
                slides.forEach((s) => {
                    const sec = document.createElement('div');
                    sec.className = 'scroll-section';
                    sec.innerHTML = s.innerHTML;
                    scrollContainer.appendChild(sec);
                });
            }
            scrollContainer.style.display = 'flex';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            scrollContainer.style.display = 'none';
            deckStage.style.display = 'flex';
            if (deckControls) {
                deckControls.style.opacity = '1';
                deckControls.style.pointerEvents = 'auto';
            }
            viewBtn.innerHTML = '<i class="ph-bold ph-rows"></i> <span>Scroll</span>';
            const madText = document.getElementById('madViewText');
            if (madText) madText.textContent = 'Modo Scroll Continuo';
            goToSlide(currentSlide);
        }
    }

    // Mobile Actions Menu Popover
    function toggleMobileActionsMenu(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('mobileActionsDropdown');
        if (menu) {
            menu.classList.toggle('show');
        }
    }

    function closeMobileActionsMenu() {
        const menu = document.getElementById('mobileActionsDropdown');
        if (menu) {
            menu.classList.remove('show');
        }
    }

    window.addEventListener('click', (e) => {
        const menu = document.getElementById('mobileActionsDropdown');
        const btn = document.getElementById('mobileMoreBtn');
        if (menu && menu.classList.contains('show')) {
            if ((!btn || !btn.contains(e.target)) && !menu.contains(e.target)) {
                closeMobileActionsMenu();
            }
        }
    });

    // Theme Toggle (Dark / Light Mode)
    function toggleDeckTheme() {
        const isLight = document.body.getAttribute('data-theme') === 'light';
        const newTheme = isLight ? 'dark' : 'light';
        document.body.setAttribute('data-theme', newTheme);
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = isLight ? 'ph-bold ph-sun' : 'ph-bold ph-moon';
        }
        localStorage.setItem('roma_deck_theme', newTheme);
    }
    const savedTheme = localStorage.getItem('roma_deck_theme');
    if (savedTheme) {
        document.body.setAttribute('data-theme', savedTheme);
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = savedTheme === 'light' ? 'ph-bold ph-moon' : 'ph-bold ph-sun';
        }
    }

    updateDeckUI();
    </script>
</body>
</html>
