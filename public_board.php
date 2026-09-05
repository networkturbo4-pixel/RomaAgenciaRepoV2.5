<?php
// public_board.php
require_once 'config/database.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Enlace no válido.");
}

$db = (new Database())->getConnection();

// Fetch Global Settings
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$global_settings = [];
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Month Data
$stmt = $db->prepare("
    SELECT pm.*, w.brand_name 
    FROM project_months pm
    JOIN projects p ON pm.project_id = p.id
    JOIN work_orders w ON p.work_order_id = w.id
    WHERE pm.id = ?
");
$stmt->execute([$id]);
$monthData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$monthData) {
    die("El tablero solicitado no existe.");
}

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$title = htmlspecialchars($monthData['brand_name']) . " - " . $monthNames[$monthData['month']] . ' ' . $monthData['year'];

// Comprobar si requiere PIN
$isProtected = !empty($monthData['pin']);
$isAuthenticated = isset($_SESSION['public_auth_' . $id]) && $_SESSION['public_auth_' . $id] === true;

if ($isProtected && !$isAuthenticated) {
    $errorMsg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
        if (trim($_POST['pin']) === $monthData['pin']) {
            $_SESSION['public_auth_' . $id] = true;
            header("Location: public_board.php?id=" . $id);
            exit();
        } else {
            $errorMsg = "El PIN ingresado es incorrecto.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Restringido | <?php echo $title; ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #000000;
                --card-bg: #0d0d10;
                --text-main: #f8fafc;
                --text-muted: #9ca3af;
                --border: #26262b;
                --primary: #10b981;
                --primary-hover: #059669;
            }
            body { 
                font-family: 'Inter', sans-serif; 
                background: var(--bg); 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh; 
                margin: 0; 
                color: var(--text-main);
                background-image: radial-gradient(circle at 50% 0%, rgba(16, 185, 129, 0.12), transparent 60%);
            }
            .auth-card { 
                background: var(--card-bg); 
                padding: 3rem 2.25rem; 
                border-radius: 24px; 
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.08); 
                text-align: center; 
                max-width: 400px; 
                width: 90%; 
            }
            .auth-icon {
                width: 56px;
                height: 56px;
                background: rgba(16, 185, 129, 0.12);
                border: 1px solid rgba(16, 185, 129, 0.25);
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
                color: var(--primary);
                font-size: 1.75rem;
            }
            h1 { font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem; letter-spacing: -0.02em; }
            p { color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 2rem; }
            input[type="text"] { 
                width: 100%; 
                padding: 0.9rem; 
                font-size: 1.8rem; 
                text-align: center; 
                letter-spacing: 8px; 
                border: 2px solid var(--border); 
                border-radius: 14px; 
                margin-bottom: 1.5rem; 
                box-sizing: border-box; 
                background: rgba(0,0,0,0.5); 
                color: var(--text-main); 
                font-weight: 700;
                outline: none;
                transition: all 0.2s;
            }
            input[type="text"]:focus { 
                border-color: var(--primary); 
                box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            }
            button { 
                width: 100%; 
                padding: 0.9rem; 
                background: var(--primary); 
                color: white; 
                border: none; 
                border-radius: 14px; 
                font-size: 0.95rem; 
                font-weight: 700; 
                cursor: pointer; 
                transition: 0.2s; 
                box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            }
            button:hover { background: var(--primary-hover); transform: translateY(-1px); }
            .error { color: #f87171; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600; }
        </style>
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
    </head>
    <body>
        <div class="auth-card">
            <div class="auth-icon"><i class="ph-bold ph-lock-key"></i></div>
            <h1>Acceso Protegido</h1>
            <p>Ingresa el PIN de 6 dígitos para acceder al tablero de <strong><?php echo htmlspecialchars($monthData['brand_name']); ?></strong>.</p>
            <?php if ($errorMsg): ?><div class="error"><?php echo $errorMsg; ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="pin" maxlength="6" autocomplete="off" autofocus required placeholder="••••••">
                <button type="submit">Ingresar al Tablero</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Fetch Posts
$stmtPosts = $db->prepare("SELECT * FROM month_posts WHERE month_id = ? ORDER BY CASE WHEN post_date IS NULL OR post_date = '0000-00-00' OR post_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC, post_date ASC, id ASC");
$stmtPosts->execute([$id]);
$postsRaw = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

// Extract all post IDs
$postIds = array_column($postsRaw, 'id');
$allComments = [];
if (!empty($postIds)) {
    $inQuery = implode(',', array_fill(0, count($postIds), '?'));
    $stmtComments = $db->prepare("SELECT * FROM post_comments WHERE post_id IN ($inQuery) ORDER BY created_at ASC");
    $stmtComments->execute($postIds);
    $allComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
}

// Organize posts and attach comments
$posts = [];
foreach ($postsRaw as $p) {
    $p['comments'] = [];
    foreach ($allComments as $c) {
        if ($c['post_id'] == $p['id']) {
            $p['comments'][] = $c;
        }
    }
    $p['has_active_comments'] = count($p['comments']) > 0;
    $posts[] = $p;
}

function renderPreviewBox($urlStr, $isRef = true) {
    if (!$urlStr) {
        return '<div class="media-empty-state"><i class="ph ph-image"></i><span>Sin recurso adjunto</span></div>';
    }

    $mediaList = json_decode($urlStr, true);
    if (!is_array($mediaList) || count($mediaList) === 0) {
        $mediaList = !empty($urlStr) ? [$urlStr] : [];
    }
    if (empty($mediaList)) {
        return '<div class="media-empty-state"><i class="ph ph-image"></i><span>Sin recurso adjunto</span></div>';
    }

    if (count($mediaList) > 1) {
        $html = '<div class="swiper swiper-media app-media-swiper"><div class="swiper-wrapper media-swiper-wrapper">';
        foreach($mediaList as $mItem) {
            $html .= '<div class="media-slide"><img src="'.htmlspecialchars($mItem).'" class="app-media-single-img" onclick="openLightbox(\''.htmlspecialchars($mItem).'\')" alt="Slide"></div>';
        }
        $html .= '</div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div><div class="swiper-pagination"></div></div>';
        return $html;
    }

    $url = $mediaList[0];
    $isDriveImage = preg_match('/drive\.google\.com\/(uc\?export=view&id=|thumbnail\?id=)([\w-]+)/i', $url);
    $isVideoLink = !$isDriveImage && preg_match('/(youtu\.be|youtube\.com|tiktok\.com|\.mp4|\.webm|\.mov|drive\.google\.com|instagram\.com|facebook\.com|fb\.watch|pinterest\.com|pin\.it)/i', $url);

    if ($isVideoLink) {
        // YouTube
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $ytMatch)) {
            $isShort = strpos($url, 'shorts') !== false;
            $aspect = $isShort ? 'aspect-ratio: 9/16; max-width: 340px;' : 'aspect-ratio: 16/9;';
            return '<div class="app-video-embed" style="'.$aspect.'"><iframe src="https://www.youtube.com/embed/'.$ytMatch[1].'?autoplay=0" frameborder="0" allowfullscreen></iframe></div>';
        }
        // MP4
        elseif (preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url)) {
            return '<video controls class="app-native-video"><source src="'.htmlspecialchars($url).'" type="video/mp4"></video>';
        }
        // TikTok with numeric video ID
        elseif (preg_match('/tiktok\.com\/(?:@[^\/]+\/video\/|v\/|embed\/v2\/|embed\/)?(\d{15,25})/i', $url, $tkMatch)) {
            return '<div class="app-video-embed" style="aspect-ratio: 9/16; max-width: 340px;"><iframe src="https://www.tiktok.com/embed/v2/'.$tkMatch[1].'" frameborder="0" allowfullscreen></iframe></div>';
        }
        // TikTok general/short URL (CSP blocks direct iframes, show interactive card)
        elseif (preg_match('/tiktok\.com/i', $url)) {
            $short = mb_strlen($url) > 45 ? mb_substr($url, 0, 42).'…' : $url;
            return '
            <div class="app-social-card tt-card">
                <div class="app-social-badge"><i class="ph-bold ph-tiktok-logo"></i> TikTok Video</div>
                <div class="app-social-icon tt-icon"><i class="ph-bold ph-tiktok-logo"></i></div>
                <div class="app-social-url">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" class="app-social-btn tt-btn">
                    <i class="ph-bold ph-arrow-square-out"></i> Ver en TikTok
                </a>
            </div>';
        }
        // Instagram
        elseif (preg_match('/instagram\.com\/(?:p|reel|tv)\//i', $url)) {
            $short = mb_strlen($url) > 45 ? mb_substr($url, 0, 42).'…' : $url;
            return '
            <div class="app-social-card ig-card">
                <div class="app-social-badge"><i class="ph-bold ph-instagram-logo"></i> Instagram Reel</div>
                <div class="app-social-icon ig-icon"><i class="ph-fill ph-play"></i></div>
                <div class="app-social-url">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" class="app-social-btn ig-btn">
                    <i class="ph-bold ph-arrow-square-out"></i> Abrir en Instagram
                </a>
            </div>';
        }
        // Facebook
        elseif (preg_match('/facebook\.com|fb\.watch/i', $url)) {
            $short = mb_strlen($url) > 45 ? mb_substr($url, 0, 42).'…' : $url;
            return '
            <div class="app-social-card fb-card">
                <div class="app-social-badge"><i class="ph-bold ph-facebook-logo"></i> Facebook Post</div>
                <div class="app-social-icon fb-icon"><i class="ph-fill ph-play"></i></div>
                <div class="app-social-url">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" class="app-social-btn fb-btn">
                    <i class="ph-bold ph-arrow-square-out"></i> Abrir en Facebook
                </a>
            </div>';
        }
        // Pinterest
        elseif (preg_match('/pinterest\.com|pin\.it/i', $url)) {
            $short = mb_strlen($url) > 45 ? mb_substr($url, 0, 42).'…' : $url;
            return '
            <div class="app-social-card pin-card">
                <div class="app-social-badge"><i class="ph-bold ph-pinterest-logo"></i> Pinterest Pin</div>
                <div class="app-social-icon pin-icon"><i class="ph-bold ph-pinterest-logo"></i></div>
                <div class="app-social-url">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" class="app-social-btn pin-btn">
                    <i class="ph-bold ph-arrow-square-out"></i> Ver en Pinterest
                </a>
            </div>';
        }
        // Google Drive
        elseif (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/', $url, $drMatch)) {
            return '<div class="app-video-embed" style="aspect-ratio: 16/9;"><iframe src="https://drive.google.com/file/d/'.$drMatch[1].'/preview" frameborder="0" allowfullscreen></iframe></div>';
        }
        else {
            return '<a href="'.htmlspecialchars($url).'" target="_blank" class="app-external-link-card"><i class="ph-bold ph-link"></i><span>Ver Recurso en Enlace Externo</span></a>';
        }
    } else {
        return '<img src="'.htmlspecialchars($url).'" class="app-media-single-img" onclick="openLightbox(\''.htmlspecialchars($url).'\')" alt="Recurso">';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $title; ?></title>
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <style>
        :root {
            --app-bg: #050507;
            --app-card: #0c0c0f;
            --app-surface: #131318;
            --app-subtle: #191920;
            --app-border: #24242e;
            --app-border-light: #1b1b22;
            --app-text-title: #f8fafc;
            --app-text-body: #d1d5db;
            --app-text-muted: #9ca3af;
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-light: rgba(16, 185, 129, 0.15);
            --primary-glow: rgba(16, 185, 129, 0.35);
            --radius-app: 28px;
            --radius-card: 20px;
            --radius-pill: 9999px;
            --shadow-app: 0 30px 80px -15px rgba(0, 0, 0, 0.95), 0 0 0 1px rgba(255, 255, 255, 0.08);
            --shadow-card: 0 4px 16px rgba(0, 0, 0, 0.5);
            --shadow-float: 0 16px 36px -4px rgba(0, 0, 0, 0.8);
        }

        [data-theme="light"] {
            --app-bg: #f4f5f8;
            --app-card: #ffffff;
            --app-surface: #ffffff;
            --app-subtle: #f8fafc;
            --app-border: #e2e8f0;
            --app-border-light: #f1f5f9;
            --app-text-title: #0f172a;
            --app-text-body: #334155;
            --app-text-muted: #64748b;
            --primary: #059b6c;
            --primary-hover: #047857;
            --primary-light: rgba(5, 155, 108, 0.1);
            --primary-glow: rgba(5, 155, 108, 0.25);
            --shadow-app: 0 25px 70px -15px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(15, 23, 42, 0.06);
            --shadow-card: 0 4px 12px rgba(0, 0, 0, 0.04);
            --shadow-float: 0 12px 30px -4px rgba(0, 0, 0, 0.12);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
            background: var(--app-bg);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh;
            color: var(--app-text-body);
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        .app-backdrop-glow {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 50% -10%, rgba(16, 185, 129, 0.08), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Large App Shell */
        .board-container {
            width: 100%;
            max-width: 100%;
            background: var(--app-card);
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        @media (min-width: 900px) {
            body {
                padding: 1.25rem;
            }
            .board-container {
                max-width: 1560px;
                width: 96vw;
                height: 94vh;
                height: 94dvh;
                min-height: 720px;
                border-radius: var(--radius-app);
                border: 1px solid var(--app-border);
                box-shadow: var(--shadow-app);
            }
        }

        /* Top App Header */
        .card-header {
            height: 68px;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--app-card);
            border-bottom: 1px solid var(--app-border);
            flex-shrink: 0;
            z-index: 30;
            padding: 0.65rem 1rem;
            gap: 0.5rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            min-width: 0;
            flex: 1;
        }

        .post-index-badge {
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: var(--app-text-title);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }

        .date-pill {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.28rem 0.65rem;
            border-radius: var(--radius-pill);
            color: var(--app-text-muted);
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .status-pill {
            font-size: 0.7rem;
            font-weight: 800;
            padding: 0.28rem 0.65rem;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .status-pill.publicado {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-pill.aprobado {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .status-pill.rechazado {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .status-pill.pendiente {
            background: rgba(100, 116, 139, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(100, 116, 139, 0.3);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-shrink: 0;
        }

        .btn-app-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid var(--app-border);
            background: var(--app-subtle);
            color: var(--app-text-title);
            font-size: 1.15rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-app-icon:hover {
            background: color-mix(in srgb, var(--primary) 15%, var(--app-subtle));
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        @media (max-width: 600px) {
            .card-header {
                padding: 0.55rem 0.75rem;
                gap: 0.35rem;
            }
            .header-left {
                gap: 0.35rem;
            }
            .post-index-badge {
                font-size: 0.95rem;
            }
            .date-pill, .status-pill {
                font-size: 0.64rem;
                padding: 0.2rem 0.5rem;
            }
            .header-actions {
                gap: 0.3rem;
            }
            .btn-app-icon {
                width: 34px;
                height: 34px;
                font-size: 1rem;
                border-radius: 10px;
            }
            
            /* Full height media / video frame on responsive */
            .media-display-frame {
                min-height: 420px !important;
                height: calc(100vh - 250px) !important;
                max-height: 740px !important;
                padding: 0.4rem !important;
            }
            .media-pane {
                min-height: 400px !important;
            }
            .app-video-embed {
                height: 100% !important;
                max-width: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .app-video-embed iframe {
                height: 100% !important;
                width: 100% !important;
            }
            .app-native-video {
                height: 100% !important;
                max-height: 100% !important;
                width: 100% !important;
            }
        }

        /* Swiper Main Posts Container */
        #mainSwiper {
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .main-posts-wrapper {
            height: 100%;
            display: flex;
        }
        .main-post-slide {
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
            overflow: hidden;
            box-sizing: border-box;
            background: var(--app-card);
            flex-shrink: 0;
        }

        /* Mobile Segmented Control Bar */
        .tabs {
            display: flex;
            padding: 0.6rem 1rem;
            background: var(--app-card);
            border-bottom: 1px solid var(--app-border);
            gap: 8px;
            flex-shrink: 0;
            z-index: 20;
        }
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 0.6rem 0.35rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--app-text-muted);
            cursor: pointer;
            border-radius: 12px;
            background: var(--app-subtle);
            border: 1px solid transparent;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            user-select: none;
        }
        .tab-btn.active {
            background: var(--app-surface);
            color: var(--app-text-title);
            border-color: var(--app-border);
            box-shadow: var(--shadow-card);
        }

        /* Main Scrollable Canvas */
        .content-scroll-wrap {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: 1rem;
            padding-bottom: 80px;
        }
        .content-scroll-wrap::-webkit-scrollbar { width: 6px; }
        .content-scroll-wrap::-webkit-scrollbar-thumb { background: var(--app-border); border-radius: 4px; }

        /* Mobile Base Tab Visibility */
        .tab-content {
            display: none !important;
        }
        .tab-content.active {
            display: block !important;
            animation: fadeInTab 0.2s ease-out;
        }
        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-approve-desktop {
            display: none !important;
        }
        .btn-approve-mobile {
            display: flex !important;
        }

        /* Desktop Layout (3 Columns Studio Grid) */
        @media (min-width: 900px) {
            .tabs {
                display: none !important;
            }
            .content-scroll-wrap {
                padding: 1.25rem 1.5rem 74px 1.5rem !important;
            }
            .content-area {
                display: grid !important;
                grid-template-columns: minmax(280px, 340px) 1.5fr minmax(300px, 380px) !important;
                gap: 1.35rem !important;
                height: 100% !important;
                min-height: 0 !important;
                align-items: stretch !important;
            }
            .tab-content {
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                min-height: 0 !important;
            }
            .btn-approve-mobile {
                display: none !important;
            }
            .btn-approve-desktop {
                display: flex !important;
            }
        }

        /* Cards in Studio View */
        .studio-column-card {
            background: var(--app-surface);
            border: 1px solid var(--app-border);
            border-radius: var(--radius-card);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-card);
            min-height: 300px;
            height: 100%;
        }

        .studio-column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--app-border-light);
            flex-shrink: 0;
            gap: 0.5rem;
        }

        .studio-column-title {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--app-text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .studio-column-title i {
            font-size: 1rem;
            color: var(--primary);
        }

        /* Copy Studio */
        .copy-text-box {
            flex: 1;
            font-size: 0.94rem;
            line-height: 1.7;
            color: var(--app-text-title);
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            border-radius: 14px;
            padding: 1.25rem;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-y: auto;
            min-height: 220px;
            max-height: 520px;
        }

        .btn-copy-clipboard {
            padding: 0.4rem 0.75rem;
            border-radius: 10px;
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            color: var(--app-text-muted);
            font-size: 0.74rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-copy-clipboard:hover {
            color: var(--app-text-title);
            border-color: var(--primary);
        }

        /* Multimedia Studio */
        .media-switcher {
            display: flex;
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            border-radius: 14px;
            padding: 4px;
            margin-bottom: 1rem;
            flex-shrink: 0;
            gap: 6px;
        }
        .media-switch-btn {
            flex: 1;
            padding: 0.55rem 0.75rem;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: var(--app-text-muted);
            font-weight: 700;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
        }
        .media-switch-btn.active {
            background: var(--app-surface);
            color: var(--app-text-title);
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
            border: 1px solid var(--app-border);
        }

        .media-display-frame {
            flex: 1;
            min-height: 380px;
            height: 100%;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            position: relative;
            padding: 0.75rem;
        }
        .media-pane { display: none; width: 100%; height: 100%; min-height: 360px; }
        .media-pane.active { display: flex; flex-direction: column; width: 100%; height: 100%; align-items: center; justify-content: center; animation: fadeInMedia 0.25s ease-out; }
        @keyframes fadeInMedia { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

        .app-media-single-img {
            max-width: 100%;
            max-height: 540px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: auto;
            border-radius: 12px;
            cursor: zoom-in;
            transition: transform 0.2s ease;
        }
        .app-media-single-img:hover {
            transform: scale(1.015);
        }

        /* Swiper Nested Carousel */
        .app-media-swiper {
            width: 100% !important;
            height: 100% !important;
            min-height: 360px !important;
            max-height: 560px !important;
            position: relative !important;
            border-radius: 14px !important;
            overflow: hidden !important;
        }
        .media-swiper-wrapper {
            display: flex !important;
            width: 100% !important;
            height: 100% !important;
        }
        .media-slide {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 100% !important;
            width: 100% !important;
            flex-shrink: 0 !important;
            cursor: zoom-in;
        }
        .media-slide img {
            max-width: 100% !important;
            max-height: 520px !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 12px !important;
            display: block !important;
            margin: auto !important;
        }
        .swiper-button-next, .swiper-button-prev {
            color: var(--primary) !important;
            background: var(--app-surface) !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 50% !important;
            box-shadow: var(--shadow-float) !important;
            border: 1px solid var(--app-border) !important;
            transition: transform 0.2s ease !important;
            z-index: 10 !important;
        }
        .swiper-button-next::after, .swiper-button-prev::after {
            font-size: 16px !important;
            font-weight: 900 !important;
        }
        .swiper-button-next:hover, .swiper-button-prev:hover {
            transform: scale(1.1) !important;
        }
        .swiper-pagination-bullet {
            background: var(--app-text-muted) !important;
            opacity: 0.5 !important;
        }
        .swiper-pagination-bullet-active {
            background: var(--primary) !important;
            opacity: 1 !important;
            width: 22px !important;
            border-radius: 6px !important;
        }

        .app-video-embed {
            width: 100%;
            margin: 0 auto;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
        }
        .app-video-embed iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .app-native-video {
            width: 100%;
            max-height: 540px;
            object-fit: contain;
            border-radius: 14px;
            background: #000;
        }

        /* Social Embed Cards */
        .app-social-card {
            width: 100%;
            min-height: 240px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 1.75rem;
            text-align: center;
        }
        .ig-card {
            background: linear-gradient(135deg, #180812, #240b1a, #10040a);
            border: 1px solid rgba(225, 48, 108, 0.35);
        }
        .fb-card {
            background: linear-gradient(135deg, #090e18, #0e1726, #05080e);
            border: 1px solid rgba(24, 119, 242, 0.35);
        }
        .tt-card {
            background: linear-gradient(135deg, #04090c, #0c151b, #04090c);
            border: 1px solid rgba(105, 201, 208, 0.35);
        }
        .pin-card {
            background: linear-gradient(135deg, #180808, #260c0c, #100404);
            border: 1px solid rgba(230, 0, 35, 0.35);
        }
        .app-social-badge {
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ig-card .app-social-badge { background: rgba(225,48,108,0.18); color: #f472b6; border: 1px solid rgba(225,48,108,0.35); }
        .fb-card .app-social-badge { background: rgba(24,119,242,0.18); color: #60a5fa; border: 1px solid rgba(24,119,242,0.35); }
        .tt-card .app-social-badge { background: rgba(105,201,208,0.18); color: #69c9d0; border: 1px solid rgba(105,201,208,0.35); }
        .pin-card .app-social-badge { background: rgba(230,0,35,0.18); color: #f87171; border: 1px solid rgba(230,0,35,0.35); }
        .app-social-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .ig-icon { background: rgba(225,48,108,0.18); color: #e1306c; border: 1px solid rgba(225,48,108,0.35); }
        .fb-icon { background: rgba(24,119,242,0.18); color: #1877f2; border: 1px solid rgba(24,119,242,0.35); }
        .tt-icon { background: rgba(105,201,208,0.18); color: #69c9d0; border: 1px solid rgba(105,201,208,0.35); }
        .pin-icon { background: rgba(230,0,35,0.18); color: #e60023; border: 1px solid rgba(230,0,35,0.35); }
        .app-social-url { font-size: 0.74rem; color: rgba(255,255,255,0.5); max-width: 260px; word-break: break-all; }
        .app-social-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.65rem 1.35rem;
            border-radius: 20px;
            color: white;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .ig-btn { background: linear-gradient(135deg, #f09433, #dc2743, #bc1888); box-shadow: 0 4px 15px rgba(225,48,108,0.35); }
        .fb-btn { background: #1877f2; box-shadow: 0 4px 15px rgba(24,119,242,0.35); }
        .tt-btn { background: linear-gradient(135deg, #00f2fe, #fe2c55); color: #000; font-weight: 800; box-shadow: 0 4px 15px rgba(0,242,254,0.3); }
        .pin-btn { background: #e60023; box-shadow: 0 4px 15px rgba(230,0,35,0.35); }
        .app-social-btn:hover { transform: scale(1.03); }

        .media-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            color: var(--app-text-muted);
            padding: 3.5rem 1rem;
        }
        .media-empty-state i { font-size: 3rem; opacity: 0.4; }
        .media-empty-state span { font-size: 0.85rem; font-weight: 600; }

        /* Comments Studio */
        .comments-timeline {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 180px;
            max-height: 420px;
        }
        .comments-timeline::-webkit-scrollbar { width: 4px; }
        .comments-timeline::-webkit-scrollbar-thumb { background: var(--app-border); border-radius: 4px; }

        .comment-item {
            padding: 0.95rem 1.1rem;
            border-radius: 16px;
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            border-left: 3px solid #818cf8;
            transition: all 0.2s ease;
        }
        .comment-item:hover {
            border-left-color: var(--primary);
            transform: translateY(-1px);
        }
        .comment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.45rem;
            font-size: 0.74rem;
            color: var(--app-text-muted);
        }
        .comment-date { display: flex; align-items: center; gap: 4px; font-weight: 600; }
        .comment-status {
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .comment-status-pendiente, .comment-status { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .comment-status-levantado { background: rgba(16, 185, 129, 0.15); color: #059669; }

        .comment-actions { display: flex; gap: 4px; }
        .comment-action-btn {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--app-text-muted);
            transition: all 0.15s;
        }
        .comment-action-btn.edit:hover { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
        .comment-action-btn.delete:hover { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .comment-text {
            font-size: 0.86rem;
            color: var(--app-text-title);
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .comment-attached-thumb {
            margin-top: 0.65rem;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            max-width: 190px;
            max-height: 140px;
            cursor: pointer;
            border: 1px solid var(--app-border);
            background: #000;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        .comment-attached-thumb:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 16px rgba(0,0,0,0.35);
        }
        .comment-attached-thumb img {
            width: 100%;
            height: 100%;
            max-height: 140px;
            object-fit: cover;
            display: block;
        }
        .comment-attached-thumb .thumb-zoom-hint {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.55);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 0.74rem;
            font-weight: 700;
            opacity: 0;
            transition: opacity 0.2s;
            backdrop-filter: blur(2px);
        }
        .comment-attached-thumb:hover .thumb-zoom-hint {
            opacity: 1;
        }

        .comment-form {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-top: auto;
        }
        .comment-textarea {
            width: 100%;
            border: 1px solid var(--app-border);
            border-radius: 14px;
            padding: 0.85rem;
            font-family: inherit;
            font-size: 0.84rem;
            resize: none;
            background: var(--app-subtle);
            color: var(--app-text-title);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .comment-textarea:focus {
            border-color: var(--primary);
            background: var(--app-surface);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Comment image preview before sending */
        .comment-img-preview-box {
            display: none;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.65rem;
            background: var(--app-subtle);
            border: 1px dashed var(--primary);
            border-radius: 12px;
            position: relative;
            animation: fadeInMedia 0.2s ease;
        }
        .comment-img-preview-box.active {
            display: flex;
        }
        .comment-img-preview-box .preview-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--app-border);
            transition: transform 0.15s;
        }
        .comment-img-preview-box .preview-thumb:hover {
            transform: scale(1.05);
        }
        .comment-img-preview-box .preview-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .comment-img-preview-box .preview-name {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--app-text-title);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .comment-img-preview-box .preview-tip {
            font-size: 0.68rem;
            color: var(--app-text-muted);
        }
        .comment-img-preview-box .btn-remove-preview {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: none;
            border-radius: 8px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .comment-img-preview-box .btn-remove-preview:hover {
            background: #ef4444;
            color: #fff;
        }

        .comment-form-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-attach-media {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0.72rem 1rem;
            border-radius: 14px;
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            color: var(--app-text-title);
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-attach-media:hover {
            background: var(--app-surface);
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-attach-media i { font-size: 1.05rem; }

        .btn-submit-comment {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            background: var(--primary);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px var(--primary-glow);
        }
        .btn-submit-comment:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* Action Buttons (Approve Button) */
        .btn-approve {
            padding: 0.85rem 1.5rem;
            border-radius: var(--radius-pill);
            border: none;
            font-weight: 800;
            font-size: 0.84rem;
            letter-spacing: 0.4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-approve.active-approve {
            background: var(--primary);
            color: white;
            box-shadow: 0 6px 20px var(--primary-glow);
        }
        .btn-approve.active-approve:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-approve.disabled-green {
            background: rgba(16, 185, 129, 0.16);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            cursor: default;
        }
        .btn-approve.disabled-gray {
            background: var(--app-subtle);
            color: var(--app-text-muted);
            border: 1px solid var(--app-border);
            cursor: not-allowed;
        }

        .btn-approve-mobile {
            margin: 1.25rem 0 0 0;
            box-shadow: var(--shadow-float);
        }

        /* Bottom App Navigation Bar */
        .bottom-nav {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 64px;
            background: var(--app-card);
            border-top: 1px solid var(--app-border);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 30;
        }

        .nav-btn {
            background: var(--app-subtle);
            border: 1px solid var(--app-border);
            color: var(--app-text-title);
            padding: 0.55rem 1.15rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .nav-btn:hover:not(.swiper-button-disabled) {
            background: color-mix(in srgb, var(--primary) 15%, var(--app-subtle));
            border-color: var(--primary);
            color: var(--primary);
        }
        .nav-btn.swiper-button-disabled {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
        }

        .nav-counter-pill {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--app-text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .nav-counter-pill strong {
            color: var(--app-text-title);
        }

        /* Grid Drawer */
        .grid-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            z-index: 9998;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .grid-drawer-overlay.active { opacity: 1; pointer-events: auto; }
        .grid-drawer {
            position: fixed;
            top: 0;
            right: -440px;
            width: 420px;
            max-width: 90vw;
            height: 100vh;
            height: 100dvh;
            background: var(--app-card);
            z-index: 9999;
            box-shadow: -10px 0 40px rgba(0,0,0,0.6);
            transition: right 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--app-border);
        }
        .grid-drawer.active { right: 0; }
        .grid-drawer-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--app-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .grid-drawer-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--app-text-title);
            letter-spacing: -0.01em;
        }
        .grid-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
        }
        .grid-drawer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.95rem;
        }
        .grid-thumb {
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid var(--app-border);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--app-subtle);
            position: relative;
        }
        .grid-thumb:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-float);
        }
        .grid-thumb.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .grid-thumb-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
            background: var(--app-subtle);
        }
        .grid-thumb-placeholder {
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--app-subtle);
            color: var(--app-text-muted);
            font-size: 1.75rem;
        }
        .grid-thumb-info {
            padding: 0.7rem 0.8rem;
            background: var(--app-surface);
        }
        .grid-thumb-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--app-text-title);
            margin-bottom: 4px;
        }

        /* Lightbox */
        .lightbox-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,0.94);
            backdrop-filter: blur(12px);
            align-items: center;
            justify-content: center;
        }
        .lightbox-modal.active { display: flex; animation: fadeInMedia 0.25s; }
        .lightbox-img {
            max-width: 92%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
        }
        .lightbox-close {
            position: absolute;
            top: 24px;
            right: 24px;
            color: white;
            font-size: 1.6rem;
            cursor: pointer;
            background: rgba(255,255,255,0.15);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.3); }

        /* Dialog Modal */
        .custom-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .custom-modal-overlay.active { opacity: 1; pointer-events: auto; }
        .custom-modal {
            background: var(--app-card);
            width: 90%;
            max-width: 380px;
            border-radius: 22px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-float);
            border: 1px solid var(--app-border);
            transform: scale(0.95);
            transition: transform 0.2s;
        }
        .custom-modal-overlay.active .custom-modal { transform: scale(1); }
        .custom-modal-icon { font-size: 3rem; margin-bottom: 0.85rem; }
        .custom-modal-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--app-text-title); }
        .custom-modal-text { font-size: 0.88rem; color: var(--app-text-muted); margin-bottom: 1.5rem; line-height: 1.5; }
        .custom-modal-buttons { display: flex; gap: 10px; justify-content: center; }
        .btn-modal { flex: 1; padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.84rem; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; }
        .btn-modal-cancel { background: var(--app-subtle); color: var(--app-text-title); border: 1px solid var(--app-border); }
        .btn-modal-cancel:hover { background: var(--app-border); }
        .custom-modal-textarea {
            width: 100%;
            min-height: 100px;
            border: 1px solid var(--app-border);
            border-radius: 14px;
            padding: 0.85rem;
            font-family: inherit;
            font-size: 0.86rem;
            resize: vertical;
            margin-bottom: 1.25rem;
            box-sizing: border-box;
            background: var(--app-subtle);
            color: var(--app-text-title);
            outline: none;
            display: none;
        }
        .custom-modal-textarea:focus { border-color: var(--primary); background: var(--app-card); }
        .btn-modal-confirm { background: var(--primary); color: white; box-shadow: 0 4px 14px var(--primary-glow); }
        .btn-modal-confirm:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Toast Feedback */
        .app-toast {
            position: fixed;
            bottom: 84px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--app-text-title);
            color: var(--app-card);
            padding: 0.65rem 1.35rem;
            border-radius: var(--radius-pill);
            font-size: 0.8rem;
            font-weight: 700;
            box-shadow: var(--shadow-float);
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .app-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>

<div class="app-backdrop-glow"></div>

<div class="board-container">
    <div class="swiper" id="mainSwiper">
        <div class="main-posts-wrapper">
            <?php 
            $index = 1;
            $totalPosts = count($posts);
            foreach ($posts as $p): 
                $statusClass = '';
                $statusIcon = 'ph-tag';
                $lStatus = mb_strtolower($p['status'], 'UTF-8');
                
                if ($lStatus === 'publicado') {
                    $statusClass = 'publicado';
                    $statusIcon = 'ph-check-circle-fill';
                } elseif ($lStatus === 'aprobado') {
                    $statusClass = 'aprobado';
                    $statusIcon = 'ph-seal-check-fill';
                } elseif (strpos($lStatus, 'rechazado') !== false || strpos($lStatus, 'corrección') !== false || strpos($lStatus, 'revisión') !== false || strpos($lStatus, 'revision') !== false) {
                    $statusClass = 'rechazado';
                    $statusIcon = 'ph-clock-countdown-fill';
                } else {
                    $statusClass = 'pendiente';
                    $statusIcon = 'ph-circle-dashed';
                }

                $btnClass = 'active-approve';
                $isApproved = ($p['status'] === 'Aprobado' || $p['status'] === 'Publicado');
                $btnDisabled = $isApproved ? 'disabled' : '';
                $btnClass = $isApproved ? 'disabled-green' : 'active-approve';
                $btnText = $isApproved ? ($p['status'] === 'Publicado' ? 'Publicado' : 'Aprobado') : 'Aprobar Referencia';
            ?>
            <div class="main-post-slide" data-post-id="<?php echo $p['id']; ?>" data-index="<?php echo $index; ?>" data-is-approved="<?php echo $isApproved ? '1' : '0'; ?>">
                
                <!-- TOP APP BAR -->
                <div class="card-header">
                    <div class="header-left">
                        <div class="post-index-badge">
                            POST <?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>
                        </div>
                        
                        <?php if (!empty($p['end_date']) && $p['end_date'] !== '0000-00-00' && $p['end_date'] !== '0000-00-00 00:00:00'): ?>
                        <div class="date-pill" title="Fecha de Entrega">
                            <i class="ph-bold ph-flag-checkered" style="color: var(--primary);"></i> <?php echo date('d M Y', strtotime($p['end_date'])); ?>
                        </div>
                        <?php elseif (!empty($p['post_date']) && $p['post_date'] !== '0000-00-00 00:00:00'): ?>
                        <div class="date-pill" title="Fecha Programada">
                            <i class="ph ph-calendar"></i> <?php echo date('d M Y', strtotime($p['post_date'])); ?>
                        </div>
                        <?php else: ?>
                        <div class="date-pill">
                            <i class="ph ph-calendar"></i> Sin Fecha
                        </div>
                        <?php endif; ?>

                        <div class="status-pill <?php echo $statusClass; ?>">
                            <i class="ph <?php echo $statusIcon; ?>"></i> <?php echo htmlspecialchars($p['status']); ?>
                        </div>
                    </div>
                    
                    <div class="header-actions">
                        <button class="btn-app-icon" id="btn-theme-toggle" onclick="toggleAppTheme()" title="Alternar tema claro/oscuro">
                            <i class="ph ph-sun" id="theme-icon"></i>
                        </button>
                        <button class="btn-app-icon" onclick="openGridDrawer()" title="Ver todos los posts">
                            <i class="ph-bold ph-squares-four"></i>
                        </button>
                    </div>
                </div>
                
                <!-- MOBILE SEGMENTED CONTROL BAR -->
                <div class="tabs">
                    <div class="tab-btn active" onclick="switchTab(this, 'media-<?php echo $p['id']; ?>')">
                        <i class="ph-bold ph-image"></i> Multimedia
                    </div>
                    <div class="tab-btn tab-btn-copy-label" onclick="switchTab(this, 'copy-<?php echo $p['id']; ?>')">
                        <i class="ph-bold ph-notepad"></i> Descripción
                    </div>
                    <div class="tab-btn" onclick="switchTab(this, 'comment-<?php echo $p['id']; ?>')">
                        <i class="ph-bold ph-chat-circle-dots"></i> Comentarios <?php echo count($p['comments']) > 0 ? '('.count($p['comments']).')' : ''; ?>
                    </div>
                </div>
                
                <!-- CONTENT CANVAS -->
                <div class="content-scroll-wrap">
                    <div class="content-area">
                        
                        <!-- COLUMN 1: BRIEF / COPY DEL POST -->
                        <div class="tab-content" id="copy-<?php echo $p['id']; ?>">
                            <div class="studio-column-card">
                                <!-- PANE 1: DESCRIPCIÓN / IDEA REFERENCIAL (Para Referencia Gráfica) -->
                                <div class="copy-pane" id="brief-pane-<?php echo $p['id']; ?>">
                                    <div class="studio-column-header">
                                        <span class="studio-column-title"><i class="ph-bold ph-notepad"></i> Descripción / Idea Referencial</span>
                                        <button type="button" class="btn-copy-clipboard" onclick="copyPostText(this, 'brief-text-<?php echo $p['id']; ?>')">
                                            <i class="ph ph-copy"></i> <span>Copiar</span>
                                        </button>
                                    </div>
                                    <div class="copy-text-box" id="brief-text-<?php echo $p['id']; ?>"><?php 
                                        if (!empty($p['design_brief'])) {
                                            echo nl2br(htmlspecialchars($p['design_brief']));
                                        } elseif (!empty($p['concept'])) {
                                            echo nl2br(htmlspecialchars($p['concept']));
                                        } else {
                                            echo 'Sin descripción o idea asignada a esta referencia.';
                                        }
                                    ?></div>
                                </div>

                                <!-- PANE 2: COPY DEL POST (Para Post Terminado) -->
                                <div class="copy-pane" id="copy-pane-<?php echo $p['id']; ?>" style="display: none;">
                                    <div class="studio-column-header">
                                        <span class="studio-column-title"><i class="ph-bold ph-text-align-left"></i> Copy del Post</span>
                                        <button type="button" class="btn-copy-clipboard" onclick="copyPostText(this, 'copy-text-<?php echo $p['id']; ?>')">
                                            <i class="ph ph-copy"></i> <span>Copiar</span>
                                        </button>
                                    </div>
                                    <div class="copy-text-box" id="copy-text-<?php echo $p['id']; ?>"><?php echo empty($p['copy_text']) ? 'Sin copy asignado a esta publicación.' : nl2br(htmlspecialchars($p['copy_text'])); ?></div>
                                </div>
                                
                                <button class="btn-approve btn-approve-desktop <?php echo $btnClass; ?>" <?php echo $btnDisabled; ?> onclick="approvePost(<?php echo $p['id']; ?>, this, 'referencia')">
                                    <i class="ph-bold ph-check"></i> <span class="btn-approve-text"><?php echo $btnText; ?></span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- COLUMN 2: MULTIMEDIA -->
                        <div class="tab-content active" id="media-<?php echo $p['id']; ?>">
                            <div class="studio-column-card">
                                <div class="studio-column-header">
                                    <span class="studio-column-title"><i class="ph-bold ph-image"></i> Multimedia</span>
                                    <span style="font-size: 0.74rem; font-weight: 700; color: var(--app-text-muted);"><i class="ph ph-arrows-out-simple"></i> Clic para zoom</span>
                                </div>
                                
                                <div class="media-switcher">
                                    <button type="button" class="media-switch-btn active" onclick="switchMedia(this, 'ref-<?php echo $p['id']; ?>', 'final-<?php echo $p['id']; ?>')">Referencia gráfica</button>
                                    <button type="button" class="media-switch-btn" onclick="switchMedia(this, 'final-<?php echo $p['id']; ?>', 'ref-<?php echo $p['id']; ?>')">Post Terminado</button>
                                </div>

                                <div class="media-display-frame">
                                    <div class="media-pane active" id="ref-<?php echo $p['id']; ?>">
                                        <?php echo renderPreviewBox($p['reference_image_link'] ?? null, true); ?>
                                    </div>
                                    <div class="media-pane" id="final-<?php echo $p['id']; ?>">
                                        <?php if(empty($p['image_link'])): ?>
                                            <div class="media-empty-state">
                                                <i class="ph ph-paint-brush"></i>
                                                <span>Aún no se ha subido el diseño final.</span>
                                            </div>
                                        <?php else: ?>
                                            <?php echo renderPreviewBox($p['image_link'], false); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- COLUMN 3: COMENTARIOS -->
                        <div class="tab-content" id="comment-<?php echo $p['id']; ?>">
                            <div class="studio-column-card">
                                <div class="studio-column-header">
                                    <span class="studio-column-title"><i class="ph-bold ph-chat-circle-dots"></i> Comentarios</span>
                                    <span style="font-size: 0.74rem; font-weight: 800; color: var(--primary); background: var(--primary-light); padding: 3px 10px; border-radius: 12px;">
                                        <?php echo count($p['comments']); ?> <?php echo count($p['comments']) === 1 ? 'Mensaje' : 'Mensajes'; ?>
                                    </span>
                                </div>

                                <div class="comments-timeline">
                                    <?php if(empty($p['comments'])): ?>
                                        <div class="media-empty-state" style="padding: 3rem 1rem;">
                                            <i class="ph ph-chat-centered-text"></i>
                                            <span>No hay comentarios registrados.</span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($p['comments'] as $c): ?>
                                            <div class="comment-item">
                                                <div class="comment-header">
                                                    <div class="comment-date">
                                                        <i class="ph ph-clock"></i>
                                                        <?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?>
                                                    </div>
                                                    <div class="comment-status"><?php echo htmlspecialchars($c['status']); ?></div>
                                                    <div class="comment-actions">
                                                        <button class="comment-action-btn edit" onclick="editComment(<?php echo $c['id']; ?>, this)" title="Editar comentario"><i class="ph ph-pencil-simple"></i></button>
                                                        <button class="comment-action-btn delete" onclick="deleteComment(<?php echo $c['id']; ?>, this)" title="Eliminar"><i class="ph ph-trash"></i></button>
                                                    </div>
                                                </div>
                                                <div class="comment-text"><?php echo htmlspecialchars($c['comment_text']); ?></div>
                                                <?php if(!empty($c['image_link'])): ?>
                                                    <div class="comment-attached-thumb" onclick="openLightbox('<?php echo htmlspecialchars($c['image_link']); ?>')" title="Clic para ampliar en visor">
                                                        <img src="<?php echo htmlspecialchars($c['image_link']); ?>" alt="Adjunto" loading="lazy">
                                                        <div class="thumb-zoom-hint"><i class="ph-bold ph-magnifying-glass-plus"></i> Ampliar</div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if(!empty($c['audio_link'])): ?>
                                                    <audio controls style="width:100%;margin-top:6px;height:32px;border-radius:8px;">
                                                        <source src="<?php echo htmlspecialchars($c['audio_link']); ?>" type="audio/webm">
                                                    </audio>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <form class="comment-form" onsubmit="submitComment(event, <?php echo $p['id']; ?>)">
                                    <textarea name="comment_text" class="comment-textarea" rows="2" placeholder="Escribe tu feedback o pega una imagen (Ctrl + V)..." onpaste="handleCommentPaste(event, this, <?php echo $p['id']; ?>)"></textarea>
                                    
                                    <!-- Miniatura previa de imagen adjunta / pegada -->
                                    <div class="comment-img-preview-box" id="img-preview-<?php echo $p['id']; ?>">
                                        <img src="" class="preview-thumb" alt="Vista previa" onclick="openLightbox(this.src)" title="Clic para ampliar">
                                        <div class="preview-info">
                                            <span class="preview-name"><i class="ph-bold ph-image"></i> Imagen lista</span>
                                            <span class="preview-tip">Clic en miniatura para ampliar</span>
                                        </div>
                                        <button type="button" class="btn-remove-preview" onclick="removeCommentImage(<?php echo $p['id']; ?>)" title="Quitar imagen">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </div>

                                    <input type="file" name="image_file" accept="image/*" class="comment-file-input" id="file-input-<?php echo $p['id']; ?>" style="display:none;" onchange="handleCommentFileChange(event, <?php echo $p['id']; ?>)">

                                    <div class="comment-form-actions">
                                        <button type="button" class="btn-attach-media" onclick="document.getElementById('file-input-<?php echo $p['id']; ?>').click()" title="Adjuntar imagen desde tu dispositivo">
                                            <i class="ph-bold ph-image"></i> <span>Adjuntar imagen</span>
                                        </button>
                                        <button type="submit" class="btn-submit-comment">
                                            <i class="ph-bold ph-paper-plane-right"></i> Enviar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    <!-- Mobile Approve Action Button -->
                    <button class="btn-approve btn-approve-mobile <?php echo $btnClass; ?>" <?php echo $btnDisabled; ?> onclick="approvePost(<?php echo $p['id']; ?>, this, 'referencia')">
                        <i class="ph-bold ph-check"></i> <span class="btn-approve-text"><?php echo $btnText; ?></span>
                    </button>
                </div>
            </div>
            <?php $index++; endforeach; ?>
        </div>
    </div>
    
    <!-- BOTTOM APP NAVIGATION BAR -->
    <div class="bottom-nav">
        <button class="nav-btn" id="btn-prev"><i class="ph-bold ph-arrow-left"></i> Anterior</button>
        <div class="nav-counter-pill" id="app-counter-pill">
            <span>Post <strong id="current-post-idx">1</strong> de <strong><?php echo $totalPosts; ?></strong></span>
        </div>
        <button class="nav-btn" id="btn-next">Siguiente <i class="ph-bold ph-arrow-right"></i></button>
    </div>
</div>

<!-- TOAST FEEDBACK -->
<div class="app-toast" id="appToast"><i class="ph-bold ph-check-circle"></i> <span id="toastMsg">Copiado al portapapeles</span></div>

<!-- CUSTOM DIALOG MODAL -->
<div class="custom-modal-overlay" id="customModal">
    <div class="custom-modal">
        <div class="custom-modal-icon" id="modalIcon"></div>
        <div class="custom-modal-title" id="modalTitle"></div>
        <div class="custom-modal-text" id="modalText"></div>
        <textarea class="custom-modal-textarea" id="modalTextarea"></textarea>
        <div class="custom-modal-buttons">
            <button class="btn-modal btn-modal-cancel" id="modalBtnCancel">Cancelar</button>
            <button class="btn-modal btn-modal-confirm" id="modalBtnConfirm">Aceptar</button>
        </div>
    </div>
</div>

<!-- GRID NAV DRAWER -->
<div class="grid-drawer-overlay" id="gridOverlay" onclick="closeGridDrawer()"></div>
<div class="grid-drawer" id="gridDrawer">
    <div class="grid-drawer-header">
        <div>
            <div class="grid-drawer-title"><i class="ph-bold ph-squares-four" style="color: var(--primary); margin-right: 6px;"></i> <?php echo htmlspecialchars($monthData['brand_name']) . ' - ' . $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?></div>
            <?php if (!empty($monthData['due_date'])): ?>
            <div style="font-size: 0.74rem; color: var(--app-text-muted); margin-top: 4px; display: flex; align-items: center; gap: 4px;" id="public-month-timer" data-due="<?php echo htmlspecialchars($monthData['due_date']); ?>" data-start="<?php echo htmlspecialchars($monthData['start_date'] ?? ''); ?>">
                <i class="ph-fill ph-hourglass-high" style="color: var(--primary);"></i> <span id="public-timer-text">Calculando entrega...</span>
            </div>
            <?php endif; ?>
        </div>
        <button class="btn-app-icon" onclick="closeGridDrawer()"><i class="ph-bold ph-x"></i></button>
    </div>
    <div class="grid-drawer-body">
        <div class="grid-drawer-grid">
            <?php 
            $thumbIndex = 1;
            foreach($posts as $tp): 
                $thumbSrc = '';
                $imgField = $tp['reference_image_link'] ?? ($tp['image_link'] ?? '');
                if (!empty($imgField)) {
                    $decoded = json_decode($imgField, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $thumbSrc = is_string($decoded[0]) ? $decoded[0] : '';
                    } elseif (!empty($imgField)) {
                        $thumbSrc = $imgField;
                    }
                }
                
                $tStatus = strtolower(trim(explode('(', $tp['status'] ?? '')[0]));
                $tStatusMap = [
                    'borrador' => 'background:rgba(100,116,139,0.18);color:#94a3b8;',
                    'en revisión' => 'background:rgba(245,158,11,0.18);color:#fbbf24;',
                    'aprobado' => 'background:rgba(59,130,246,0.18);color:#60a5fa;',
                    'publicado' => 'background:rgba(16,185,129,0.18);color:#34d399;',
                ];
                $tStatusStyle = $tStatusMap[$tStatus] ?? 'background:rgba(100,116,139,0.18);color:#94a3b8;';
            ?>
            <div class="grid-thumb" data-slide-index="<?php echo $thumbIndex - 1; ?>" onclick="navigateToPost(<?php echo $thumbIndex - 1; ?>)">
                <?php 
                $isVideo = false;
                $platformIcon = 'ph-video';
                $platformColor = '#3b82f6';
                if (!empty($thumbSrc)) {
                    $isDriveImage = preg_match('/drive\.google\.com\/(uc\?export=view&id=|thumbnail\?id=)([\w-]+)/i', $thumbSrc);
                    if (!$isDriveImage && preg_match('/(\.mp4|\.webm|\.mov|drive\.google\.com|youtu\.be|youtube\.com|tiktok\.com|instagram\.com|facebook\.com|fb\.watch|pinterest\.com|pin\.it)/i', $thumbSrc)) {
                        $isVideo = true;
                        if (preg_match('/tiktok/i', $thumbSrc)) { $platformColor = '#000'; $platformIcon = 'ph-tiktok-logo'; }
                        elseif (preg_match('/youtu/i', $thumbSrc)) { $platformColor = '#ef4444'; $platformIcon = 'ph-youtube-logo'; }
                        elseif (preg_match('/instagram/i', $thumbSrc)) { $platformColor = '#e1306c'; $platformIcon = 'ph-instagram-logo'; }
                        elseif (preg_match('/facebook|fb\.watch/i', $thumbSrc)) { $platformColor = '#1877f2'; $platformIcon = 'ph-facebook-logo'; }
                    }
                }
                ?>
                <?php if ($isVideo): ?>
                    <div class="grid-thumb-placeholder" style="background:var(--app-subtle);">
                        <div style="width:40px;height:40px;border-radius:50%;background:<?php echo $platformColor; ?>;display:flex;align-items:center;justify-content:center;">
                            <i class="ph <?php echo $platformIcon; ?>" style="font-size:1.2rem;color:white;"></i>
                        </div>
                    </div>
                <?php elseif (!empty($thumbSrc)): ?>
                    <img class="grid-thumb-img" src="<?php echo htmlspecialchars($thumbSrc); ?>" alt="Post <?php echo $thumbIndex; ?>" loading="lazy">
                <?php else: ?>
                    <div class="grid-thumb-placeholder"><i class="ph ph-image"></i></div>
                <?php endif; ?>
                <div class="grid-thumb-info">
                    <div class="grid-thumb-title">POST <?php echo str_pad($thumbIndex, 2, '0', STR_PAD_LEFT); ?></div>
                    <span class="status-pill" style="<?php echo $tStatusStyle; ?> font-size: 0.65rem; padding: 2px 8px;"><?php echo htmlspecialchars($tp['status']); ?></span>
                </div>
            </div>
            <?php $thumbIndex++; endforeach; ?>
        </div>
    </div>
</div>

<!-- LIGHTBOX MODAL -->
<div class="lightbox-modal" id="lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-close"><i class="ph-bold ph-x"></i></div>
    <img src="" class="lightbox-img" id="lightbox-img" alt="Lightbox Preview">
</div>

<script>
    // Theme Management
    function initTheme() {
        const saved = localStorage.getItem('public_theme') || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
        applyTheme(saved);
    }
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('public_theme', theme);
        const icon = document.getElementById('theme-icon');
        if (icon) {
            icon.className = theme === 'dark' ? 'ph-bold ph-sun' : 'ph-bold ph-moon';
        }
    }
    function toggleAppTheme() {
        const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        applyTheme(current);
    }
    initTheme();

    // Dialog Modal
    function showModal(title, text, type = 'confirm') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('customModal');
            const icon = document.getElementById('modalIcon');
            const btnCancel = document.getElementById('modalBtnCancel');
            const btnConfirm = document.getElementById('modalBtnConfirm');
            const modalText = document.getElementById('modalText');
            const modalTextarea = document.getElementById('modalTextarea');
            
            document.getElementById('modalTitle').innerText = title;
            modalTextarea.style.display = 'none';
            modalText.style.display = 'block';
            modalText.innerText = text;
            
            if (type === 'confirm') {
                icon.innerHTML = '<i class="ph-bold ph-question" style="color: #3b82f6;"></i>';
                btnCancel.style.display = 'block';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'error') {
                icon.innerHTML = '<i class="ph-bold ph-warning-circle" style="color: #ef4444;"></i>';
                btnCancel.style.display = 'none';
                btnConfirm.style.background = '#ef4444';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'success') {
                icon.innerHTML = '<i class="ph-bold ph-check-circle" style="color: #10b981;"></i>';
                btnCancel.style.display = 'none';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'input') {
                icon.innerHTML = '<i class="ph-bold ph-pencil-simple" style="color: #818cf8;"></i>';
                btnCancel.style.display = 'block';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Guardar';
                modalText.style.display = 'none';
                modalTextarea.style.display = 'block';
                modalTextarea.value = text;
            }

            overlay.classList.add('active');

            const handleConfirm = () => {
                cleanup();
                resolve(type === 'input' ? modalTextarea.value : true);
            };
            const handleCancel = () => {
                cleanup();
                resolve(false);
            };
            const cleanup = () => {
                overlay.classList.remove('active');
                btnConfirm.removeEventListener('click', handleConfirm);
                btnCancel.removeEventListener('click', handleCancel);
            };

            btnConfirm.addEventListener('click', handleConfirm);
            btnCancel.addEventListener('click', handleCancel);
        });
    }

    // Lightbox Logic with Touch Pinch-to-Zoom & Pan
    let lbScale = 1;
    let lbStartX = 0, lbStartY = 0;
    let lbPosX = 0, lbPosY = 0;
    let lbInitialDist = 0;
    let lbLastTap = 0;

    function openLightbox(src) {
        const img = document.getElementById('lightbox-img');
        img.src = src;
        lbScale = 1;
        lbPosX = 0;
        lbPosY = 0;
        img.style.transform = `translate(0px, 0px) scale(1)`;
        document.getElementById('lightbox').classList.add('active');
    }

    function closeLightbox(e) {
        if(e.target.id === 'lightbox' || e.target.closest('.lightbox-close')) {
            document.getElementById('lightbox').classList.remove('active');
            const img = document.getElementById('lightbox-img');
            if (img) {
                lbScale = 1;
                lbPosX = 0;
                lbPosY = 0;
                img.style.transform = `translate(0px, 0px) scale(1)`;
            }
        }
    }

    // Touch events for Lightbox Pinch-to-Zoom & Pan
    document.addEventListener('DOMContentLoaded', function() {
        const lbImg = document.getElementById('lightbox-img');
        if (!lbImg) return;

        lbImg.addEventListener('touchstart', function(e) {
            if (e.touches.length === 2) {
                lbInitialDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
            } else if (e.touches.length === 1) {
                lbStartX = e.touches[0].clientX - lbPosX;
                lbStartY = e.touches[0].clientY - lbPosY;
                
                // Double tap detection
                const now = Date.now();
                if (now - lbLastTap < 300) {
                    e.preventDefault();
                    lbScale = lbScale > 1 ? 1 : 2.5;
                    if (lbScale === 1) { lbPosX = 0; lbPosY = 0; }
                    lbImg.style.transform = `translate(${lbPosX}px, ${lbPosY}px) scale(${lbScale})`;
                }
                lbLastTap = now;
            }
        }, { passive: false });

        lbImg.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2) {
                e.preventDefault();
                const currentDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                if (lbInitialDist > 0) {
                    const diff = currentDist / lbInitialDist;
                    lbScale = Math.min(Math.max(1, lbScale * diff), 4);
                    lbInitialDist = currentDist;
                    lbImg.style.transform = `translate(${lbPosX}px, ${lbPosY}px) scale(${lbScale})`;
                }
            } else if (e.touches.length === 1 && lbScale > 1) {
                e.preventDefault();
                lbPosX = e.touches[0].clientX - lbStartX;
                lbPosY = e.touches[0].clientY - lbStartY;
                lbImg.style.transform = `translate(${lbPosX}px, ${lbPosY}px) scale(${lbScale})`;
            }
        }, { passive: false });

        lbImg.addEventListener('touchend', function(e) {
            if (e.touches.length < 2) {
                lbInitialDist = 0;
            }
        });
    });
    
    // Switch Media Logic (Referencia vs Post Terminado)
    function switchMedia(btn, showId, hideId) {
        const slide = btn.closest('.main-post-slide');
        const postId = slide.dataset.postId;
        const switcher = btn.closest('.media-switcher');
        switcher.querySelectorAll('.media-switch-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const hideEl = document.getElementById(hideId);
        const showEl = document.getElementById(showId);
        if (hideEl) hideEl.classList.remove('active');
        if (showEl) {
            showEl.classList.add('active');
            const nestedSwiper = showEl.querySelector('.swiper-media');
            if (nestedSwiper && nestedSwiper.swiper) {
                nestedSwiper.swiper.update();
            }
        }

        const isRef = showId.startsWith('ref-');
        
        // Toggle Column 1 Panes
        const briefPane = document.getElementById('brief-pane-' + postId);
        const copyPane = document.getElementById('copy-pane-' + postId);
        if (briefPane && copyPane) {
            if (isRef) {
                briefPane.style.display = 'block';
                copyPane.style.display = 'none';
            } else {
                briefPane.style.display = 'none';
                copyPane.style.display = 'block';
            }
        }

        // Update Mobile Tab label
        const copyTabBtn = slide.querySelector('.tab-btn-copy-label');
        if (copyTabBtn) {
            copyTabBtn.innerHTML = isRef 
                ? '<i class="ph-bold ph-notepad"></i> Descripción' 
                : '<i class="ph-bold ph-text-align-left"></i> Copy del post';
        }

        // Update Approval Buttons (Desktop & Mobile)
        const isApproved = slide.dataset.isApproved === '1';
        slide.querySelectorAll('.btn-approve').forEach(b => {
            const isMobileBtn = b.classList.contains('btn-approve-mobile');
            if (isApproved) {
                b.className = 'btn-approve ' + (isMobileBtn ? 'btn-approve-mobile' : 'btn-approve-desktop') + ' disabled-green';
                b.innerHTML = isRef ? '<i class="ph-bold ph-check"></i> Referencia Aprobada' : '<i class="ph-bold ph-check"></i> Publicado';
                b.disabled = true;
            } else {
                b.className = 'btn-approve ' + (isMobileBtn ? 'btn-approve-mobile' : 'btn-approve-desktop') + ' active-approve';
                b.innerHTML = isRef 
                    ? '<i class="ph-bold ph-check"></i> <span class="btn-approve-text">Aprobar Referencia</span>' 
                    : '<i class="ph-bold ph-rocket-launch"></i> <span class="btn-approve-text">Aprobar y Publicar</span>';
                b.disabled = false;
                b.setAttribute('onclick', `approvePost(${postId}, this, '${isRef ? "referencia" : "terminado"}')`);
            }
        });
    }

    // Active Post Persistence
    const currentMonthId = <?php echo (int)$id; ?>;
    const totalPostsCount = <?php echo (int)$totalPosts; ?>;

    function getInitialSlideIndex() {
        if (window.location.hash && window.location.hash.startsWith('#post-')) {
            const hNum = parseInt(window.location.hash.replace('#post-', ''), 10);
            if (!isNaN(hNum) && hNum >= 1 && hNum <= totalPostsCount) {
                return hNum - 1;
            }
        }
        const saved = sessionStorage.getItem('public_board_active_slide_' + currentMonthId);
        if (saved !== null) {
            const sNum = parseInt(saved, 10);
            if (!isNaN(sNum) && sNum >= 0 && sNum < totalPostsCount) {
                return sNum;
            }
        }
        return 0;
    }

    const startSlideIdx = getInitialSlideIndex();

    // Initialize Main Swiper with unique slide & wrapper classes
    const swiper = new Swiper('#mainSwiper', {
        slideClass: 'main-post-slide',
        wrapperClass: 'main-posts-wrapper',
        initialSlide: startSlideIdx,
        navigation: {
            nextEl: '#btn-next',
            prevEl: '#btn-prev',
        },
        allowTouchMove: false,
        observer: true,
        observeParents: true,
        on: {
            init: function () {
                const idx = this.activeIndex + 1;
                const counterEl = document.getElementById('current-post-idx');
                if (counterEl) counterEl.innerText = idx;
            },
            slideChange: function () {
                const idx = this.activeIndex + 1;
                const counterEl = document.getElementById('current-post-idx');
                if (counterEl) counterEl.innerText = idx;

                sessionStorage.setItem('public_board_active_slide_' + currentMonthId, this.activeIndex);
                history.replaceState(null, '', '#post-' + idx);

                const currentSlide = this.slides[this.activeIndex];
                if (currentSlide) {
                    currentSlide.querySelectorAll('.swiper-media').forEach(function(sEl) {
                        if (sEl.swiper) sEl.swiper.update();
                    });
                }
            }
        }
    });

    // Update counter on initial load
    const initCounterEl = document.getElementById('current-post-idx');
    if (initCounterEl) initCounterEl.innerText = swiper.activeIndex + 1;
    
    // Initialize nested media swipers with unique classes
    document.querySelectorAll('.swiper-media').forEach(function(sEl) {
        new Swiper(sEl, {
            slideClass: 'media-slide',
            wrapperClass: 'media-swiper-wrapper',
            navigation: {
                nextEl: sEl.querySelector('.swiper-button-next'),
                prevEl: sEl.querySelector('.swiper-button-prev'),
            },
            pagination: {
                el: sEl.querySelector('.swiper-pagination'),
                clickable: true,
            },
            nested: true,
            spaceBetween: 12,
            observer: true,
            observeParents: true,
            observeSlideChildren: true,
        });
    });

    // Mobile Tab Switching
    function switchTab(btn, targetId) {
        const slide = btn.closest('.main-post-slide');
        slide.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        slide.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const targetEl = document.getElementById(targetId);
        if (targetEl) {
            targetEl.classList.add('active');
            const nestedSwiper = targetEl.querySelector('.swiper-media');
            if (nestedSwiper && nestedSwiper.swiper) {
                nestedSwiper.swiper.update();
            }
        }
    }

    // Clipboard Copy
    function copyPostText(btn, elementId) {
        const textEl = document.getElementById(elementId);
        if (!textEl) return;
        const text = textEl.innerText;
        navigator.clipboard.writeText(text).then(() => {
            showToast('¡Texto copiado al portapapeles!');
        }).catch(() => {
            showToast('No se pudo copiar el texto');
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('appToast');
        const toastMsg = document.getElementById('toastMsg');
        if (!toast || !toastMsg) return;
        toastMsg.innerText = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    // Approve Post Logic (Referencia vs Post Terminado)
    async function approvePost(postId, btnEl, type = 'referencia') {
        const isTerminado = type === 'terminado';
        const modalTitle = isTerminado ? 'Aprobar y Publicar' : 'Aprobar Referencia';
        const modalMsg = isTerminado 
            ? '¿Confirmas que deseas aprobar y dar conformidad para publicar este contenido final?' 
            : '¿Confirmas que deseas aprobar la Referencia Gráfica de esta publicación?';
        const targetStatus = isTerminado ? 'Publicado' : 'Aprobado';

        const confirmed = await showModal(modalTitle, modalMsg, 'confirm');
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('id', postId);
        formData.append('status', targetStatus);

        const originalHtml = btnEl.innerHTML;
        btnEl.innerText = 'PROCESANDO...';
        btnEl.disabled = true;

        try {
            const response = await fetch('modules/month_board/ajax_update_post_status.php', {
                method: 'POST',
                body: formData
            });
            const textResponse = await response.text();
            let result;
            try {
                result = JSON.parse(textResponse);
            } catch (err) {
                throw new Error('Respuesta no válida del servidor (' + response.status + ')');
            }
            
            if (result.success) {
                const slide = btnEl.closest('.main-post-slide');
                slide.dataset.isApproved = '1';

                slide.querySelectorAll('.btn-approve').forEach(b => {
                    const isMobileBtn = b.classList.contains('btn-approve-mobile');
                    b.className = 'btn-approve ' + (isMobileBtn ? 'btn-approve-mobile' : 'btn-approve-desktop') + ' disabled-green';
                    b.innerHTML = isTerminado ? '<i class="ph-bold ph-check"></i> Publicado' : '<i class="ph-bold ph-check"></i> Referencia Aprobada';
                    b.disabled = true;
                });
                
                const pill = slide.querySelector('.status-pill');
                if (pill) {
                    pill.className = isTerminado ? 'status-pill publicado' : 'status-pill aprobado';
                    pill.innerHTML = isTerminado ? '<i class="ph ph-check-circle-fill"></i> Publicado' : '<i class="ph ph-seal-check-fill"></i> Aprobado';
                }
                
                await showModal('¡Completado!', isTerminado ? 'El contenido ha sido marcado como Publicado.' : 'La referencia gráfica ha sido aprobada.', 'success');
            } else {
                await showModal('Error', 'Error al aprobar: ' + result.error, 'error');
                btnEl.innerHTML = originalHtml;
                btnEl.disabled = false;
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión.', 'error');
            btnEl.innerHTML = originalHtml;
            btnEl.disabled = false;
        }
    }

    // Comment Image Attachments (Paste & File input)
    const commentAttachedFiles = new Map();

    function handleCommentPaste(event, textarea, postId) {
        if (!event.clipboardData || !event.clipboardData.items) return;
        const items = event.clipboardData.items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const file = items[i].getAsFile();
                if (file) {
                    event.preventDefault();
                    setCommentAttachedImage(postId, file);
                    showToast('¡Captura pegada con éxito!');
                    break;
                }
            }
        }
    }

    function handleCommentFileChange(event, postId) {
        if (event.target.files && event.target.files[0]) {
            setCommentAttachedImage(postId, event.target.files[0]);
            showToast('Imagen seleccionada');
        }
    }

    function setCommentAttachedImage(postId, file) {
        if (!file) return;
        commentAttachedFiles.set(postId, file);
        const previewBox = document.getElementById('img-preview-' + postId);
        if (previewBox) {
            const thumbImg = previewBox.querySelector('.preview-thumb');
            const reader = new FileReader();
            reader.onload = function(e) {
                thumbImg.src = e.target.result;
                previewBox.classList.add('active');
            };
            reader.readAsDataURL(file);
        }
    }

    function removeCommentImage(postId) {
        commentAttachedFiles.delete(postId);
        const fileInput = document.getElementById('file-input-' + postId);
        if (fileInput) fileInput.value = '';
        const previewBox = document.getElementById('img-preview-' + postId);
        if (previewBox) {
            const thumbImg = previewBox.querySelector('.preview-thumb');
            if (thumbImg) thumbImg.src = '';
            previewBox.classList.remove('active');
        }
    }

    // Submit Comment Logic
    async function submitComment(e, postId) {
        e.preventDefault();
        const form = e.target;
        const textarea = form.querySelector('textarea');
        const text = textarea ? textarea.value.trim() : '';
        const btn = form.querySelector('.btn-submit-comment');
        const attachedImg = commentAttachedFiles.get(postId);
        
        if (!text && !attachedImg) {
            await showModal('Atención', 'Por favor escribe un mensaje o adjunta una imagen.', 'alert');
            return;
        }

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';

        const fd = new FormData();
        fd.append('post_id', postId);
        fd.append('month_id', currentMonthId);
        fd.append('comment_text', text);
        fd.append('comment_phase', 'Parrilla Final');
        if (attachedImg) {
            fd.append('image_file', attachedImg, attachedImg.name || ('screenshot_' + Date.now() + '.png'));
        }

        try {
            const response = await fetch('ajax_save_public_comment.php', {
                method: 'POST',
                body: fd
            });
            const textResponse = await response.text();
            let result;
            try {
                result = JSON.parse(textResponse);
            } catch (jsonErr) {
                console.error('Invalid server response:', textResponse);
                throw new Error('Respuesta no válida del servidor');
            }
            
            if (result.success) {
                sessionStorage.setItem('public_board_active_slide_' + currentMonthId, swiper.activeIndex);
                window.location.hash = '#post-' + (swiper.activeIndex + 1);
                location.reload();
            } else {
                await showModal('Error', 'Error: ' + (result.error || 'No se pudo guardar el comentario'), 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (err) {
            console.error(err);
            await showModal('Error', 'Error al procesar el comentario: ' + (err.message || 'Error de conexión.'), 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Edit Comment Logic
    async function editComment(commentId, btnEl) {
        const commentItem = btnEl.closest('.comment-item');
        const commentTextEl = commentItem.querySelector('.comment-text');
        const currentText = commentTextEl.innerText;

        const newText = await showModal('Editar Comentario', currentText, 'input');
        if (newText === false || newText === null) return;
        if (newText.trim() === '' || newText === currentText) return;

        const fd = new FormData();
        fd.append('id', commentId);
        fd.append('comment_text', newText);

        try {
            const response = await fetch('ajax_edit_public_comment.php', { method: 'POST', body: fd });
            const textResponse = await response.text();
            let result;
            try {
                result = JSON.parse(textResponse);
            } catch (jsonErr) {
                throw new Error('Respuesta no válida del servidor');
            }
            if (result.success) {
                commentTextEl.innerText = newText;
                showToast('Comentario actualizado');
            } else {
                await showModal('Error', 'Error: ' + (result.error || 'No se pudo editar'), 'error');
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión: ' + (e.message || ''), 'error');
        }
    }

    // Delete Comment Logic
    async function deleteComment(commentId, btnEl) {
        const confirmed = await showModal('Eliminar Comentario', '¿Estás seguro de eliminar este comentario?', 'confirm');
        if (!confirmed) return;

        const fd = new FormData();
        fd.append('id', commentId);

        try {
            let response = await fetch('ajax_delete_public_comment.php', { method: 'POST', body: fd });
            if (!response.ok && response.status === 404) {
                response = await fetch('modules/month_board/ajax_delete_public_comment.php', { method: 'POST', body: fd });
            }
            const textResponse = await response.text();
            let result;
            try {
                result = JSON.parse(textResponse);
            } catch (jsonErr) {
                throw new Error('Respuesta no válida del servidor (' + response.status + ')');
            }
            if (result.success) {
                const commentItem = btnEl.closest('.comment-item');
                commentItem.style.opacity = '0';
                setTimeout(() => commentItem.remove(), 300);
                showToast('Comentario eliminado');
            } else {
                await showModal('Error', 'Error: ' + (result.error || 'No se pudo eliminar'), 'error');
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión: ' + (e.message || ''), 'error');
        }
    }

    // Grid Drawer Logic
    function openGridDrawer() {
        const currentSlide = swiper.activeIndex;
        document.querySelectorAll('.grid-thumb').forEach(t => {
            t.classList.remove('active');
            if (parseInt(t.dataset.slideIndex) === currentSlide) {
                t.classList.add('active');
            }
        });
        document.getElementById('gridOverlay').classList.add('active');
        document.getElementById('gridDrawer').classList.add('active');
    }

    function closeGridDrawer() {
        document.getElementById('gridOverlay').classList.remove('active');
        document.getElementById('gridDrawer').classList.remove('active');
    }

    function navigateToPost(slideIndex) {
        swiper.slideTo(slideIndex);
        sessionStorage.setItem('public_board_active_slide_' + currentMonthId, slideIndex);
        history.replaceState(null, '', '#post-' + (slideIndex + 1));
        closeGridDrawer();
    }

    function updatePublicMonthTimer() {
        const el = document.getElementById('public-month-timer');
        if (!el) return;
        const dueStr = el.getAttribute('data-due');
        const startStr = el.getAttribute('data-start');
        const textEl = document.getElementById('public-timer-text');
        if (!dueStr || !textEl) return;
        const now = new Date();
        const due = new Date(dueStr + 'T23:59:59');
        const start = startStr ? new Date(startStr + 'T00:00:00') : new Date();
        if (now < start) {
            textEl.textContent = 'Inicia pronto';
            return;
        }
        if (now > due) {
            textEl.textContent = 'Tiempo concluido';
            return;
        }
        const diff = due - now;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        const mins = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
        const secs = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
        textEl.textContent = `Tiempo restante: ${days > 0 ? days + 'd ' : ''}${hours}:${mins}:${secs}`;
    }
    document.addEventListener('DOMContentLoaded', updatePublicMonthTimer);
    updatePublicMonthTimer();
    setInterval(updatePublicMonthTimer, 1000);
</script>
</body>
</html>