<?php
// modules/public/presentation.php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$token = trim($_GET['token'] ?? ($_GET['id'] ?? ''));

if (empty($token)) {
    http_response_code(404);
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#475569;'>
        <h2>404 — Presentación no encontrada</h2>
        <p>No se proporcionó un enlace válido de presentación.</p>
    </div>");
}

$stmt = $db->prepare("
    SELECT p.*, u.name as creator_name, u.avatar as creator_avatar 
    FROM deckcraft_presentations p 
    LEFT JOIN users u ON p.created_by = u.id 
    WHERE (p.share_token = ? OR p.id = ?)
");
$stmt->execute([$token, $token]);
$presentation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$presentation || empty($presentation['is_public'])) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; text-align:center; padding:60px; color:#475569;'>
        <h2>🔒 Presentación privada o no disponible</h2>
        <p>Esta presentación no existe o el propietario ha deshabilitado el acceso público.</p>
    </div>");
}

$page_title = htmlspecialchars($presentation['title']) . " — Presentación";

// Cálculo robusto de Base URL para evitar errores 404 en mod_rewrite (/ppt/{token})
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$base_url = $protocol . '://' . $host . ($script_dir ? $script_dir : '') . '/';

$drive_file_id = $presentation['drive_file_id'] ?? null;
$drive_file_url = $presentation['drive_file_url'] ?? null;
$local_file_path = !empty($presentation['file_path']) ? $presentation['file_path'] : null;

// Si el archivo local no existe pero está en Google Drive, descargarlo para el visor
if ((empty($local_file_path) || !file_exists($local_file_path)) && !empty($drive_file_id)) {
    require_once 'includes/GoogleDriveHelper.php';
    $driveHelper = new GoogleDriveHelper();
    if ($driveHelper->isConfigured()) {
        $upload_dir = 'uploads/deckcraft/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $safe_filename = 'deck_' . $presentation['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $presentation['title']) . '.pptx';
        $local_dest = $upload_dir . $safe_filename;
        if ($driveHelper->downloadFile($drive_file_id, $local_dest)) {
            $local_file_path = $local_dest;
            $db->prepare("UPDATE deckcraft_presentations SET file_path = ? WHERE id = ?")->execute([$local_dest, $presentation['id']]);
        }
    }
}

// Configurar URL del reproductor DeckCraft
$deck_load_param = '';
if (!empty($local_file_path) && file_exists($local_file_path)) {
    $deck_load_param = '&load=' . urlencode($base_url . $local_file_path);
} elseif ($presentation['theme'] === 'blank') {
    $deck_load_param = '&blank=1';
}

$deckcraft_viewer_url = $base_url . "assets/apps/deckcraft/index.html?show" . $deck_load_param;
$drive_preview_url = !empty($drive_file_id) ? "https://drive.google.com/file/d/{$drive_file_id}/preview" : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base_url) ?>">
    <title><?= $page_title ?></title>
    
    <!-- Meta tags Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($presentation['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(!empty($presentation['description']) ? $presentation['description'] : 'Presentación interactiva en pantalla completa') ?>">
    <meta property="og:type" content="article">
    
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #f8fafc;
        }

        /* Topbar de presentación pública */
        .pub-header {
            height: 48px;
            width: 100vw;
            background: #18191c;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            z-index: 100;
        }

        .pub-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .pub-logo-badge {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(234, 88, 12, 0.4);
        }

        .pub-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 450px;
        }

        .pub-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 700;
            background: rgba(234, 88, 12, 0.15);
            color: #f97316;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            border: 1px solid rgba(234, 88, 12, 0.3);
        }

        .pub-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pub-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: #e2e8f0;
        }

        .pub-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
        }

        .pub-btn-active {
            background: rgba(234, 88, 12, 0.2);
            border-color: #ea580c;
            color: #f97316;
        }

        .pub-btn-primary {
            background: #ea580c;
            border-color: #ea580c;
            color: #ffffff;
        }

        .pub-btn-primary:hover {
            background: #f97316;
            border-color: #f97316;
        }

        /* Iframe contenedor */
        .pub-viewport {
            width: 100vw;
            height: calc(100vh - 48px);
            position: relative;
            background: #090a0f;
        }

        .pub-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #111216;
        }
    </style>
</head>
<body>

    <!-- Header superior del Visor Público -->
    <header class="pub-header">
        <div class="pub-left">
            <div class="pub-logo-badge" title="DeckCraft">
                <i class="ph-bold ph-projector-screen-chart"></i>
            </div>
            <h1 class="pub-title"><?= htmlspecialchars($presentation['title']) ?></h1>
            <span class="pub-badge">
                <i class="ph-fill ph-slideshow"></i> Presentación
            </span>
        </div>

        <div class="pub-right">
            <!-- Selector de Visor si Drive Preview está disponible -->
            <?php if (!empty($drive_preview_url)): ?>
            <button type="button" class="pub-btn pub-btn-active" id="btnModeDeck" onclick="switchViewer('deck')" title="Modo presentación interactiva">
                <i class="ph-bold ph-play"></i>
                <span class="d-none d-md-inline">Diapositivas</span>
            </button>
            <button type="button" class="pub-btn" id="btnModeDrive" onclick="switchViewer('drive')" title="Vista previa en Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i>
                <span class="d-none d-md-inline">Drive Preview</span>
            </button>
            <?php endif; ?>

            <!-- Descargar PPTX si existe archivo -->
            <?php if (!empty($local_file_path) && file_exists($local_file_path)): ?>
            <a href="<?= htmlspecialchars($base_url . $local_file_path) ?>" download class="pub-btn pub-btn-primary" title="Descargar archivo PowerPoint">
                <i class="ph-bold ph-download-simple"></i>
                <span class="d-none d-sm-inline">Descargar PPTX</span>
            </a>
            <?php endif; ?>

            <!-- Abrir en Google Drive -->
            <?php if (!empty($drive_file_url)): ?>
            <a href="<?= htmlspecialchars($drive_file_url) ?>" target="_blank" class="pub-btn" title="Abrir en Google Drive">
                <i class="ph-bold ph-arrow-square-out"></i>
                <span class="d-none d-md-inline">Google Drive</span>
            </a>
            <?php endif; ?>

            <!-- Botón Pantalla Completa -->
            <button type="button" class="pub-btn" onclick="toggleFullscreen()" title="Pantalla completa">
                <i class="ph-bold ph-arrows-out" id="fsIcon"></i>
                <span>Pantalla Completa</span>
            </button>
        </div>
    </header>

    <!-- Contenedor del Slide Show -->
    <main class="pub-viewport" id="pubViewport">
        <iframe 
            id="mainViewerFrame"
            src="<?= htmlspecialchars($deckcraft_viewer_url) ?>" 
            class="pub-iframe" 
            title="Presentación"
            allow="fullscreen; clipboard-read; clipboard-write; autoplay"
            allowfullscreen>
        </iframe>
    </main>

    <script>
    const deckUrl = <?= json_encode($deckcraft_viewer_url) ?>;
    const driveUrl = <?= json_encode($drive_preview_url) ?>;

    function switchViewer(mode) {
        const frame = document.getElementById('mainViewerFrame');
        const btnDeck = document.getElementById('btnModeDeck');
        const btnDrive = document.getElementById('btnModeDrive');

        if (mode === 'drive' && driveUrl) {
            frame.src = driveUrl;
            if (btnDrive) btnDrive.classList.add('pub-btn-active');
            if (btnDeck) btnDeck.classList.remove('pub-btn-active');
        } else {
            frame.src = deckUrl;
            if (btnDeck) btnDeck.classList.add('pub-btn-active');
            if (btnDrive) btnDrive.classList.remove('pub-btn-active');
        }
    }

    function toggleFullscreen() {
        const vp = document.getElementById('pubViewport');
        const icon = document.getElementById('fsIcon');
        if (!document.fullscreenElement) {
            if (vp.requestFullscreen) {
                vp.requestFullscreen();
            } else if (vp.webkitRequestFullscreen) {
                vp.webkitRequestFullscreen();
            }
            if (icon) {
                icon.classList.remove('ph-arrows-out');
                icon.classList.add('ph-arrows-in');
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
            if (icon) {
                icon.classList.remove('ph-arrows-in');
                icon.classList.add('ph-arrows-out');
            }
        }
    }

    document.addEventListener('fullscreenchange', () => {
        const icon = document.getElementById('fsIcon');
        if (!document.fullscreenElement && icon) {
            icon.classList.remove('ph-arrows-in');
            icon.classList.add('ph-arrows-out');
        }
    });
    </script>
</body>
</html>
