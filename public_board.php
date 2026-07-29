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
    // Si se envió un PIN por POST
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
    
    // Mostrar pantalla de PIN
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
                --bg: #f1f5f9;
                --card-bg: white;
                --text-main: #1e293b;
                --text-muted: #64748b;
                --border: #e2e8f0;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #0f172a;
                    --card-bg: #1e293b;
                    --text-main: #f8fafc;
                    --text-muted: #94a3b8;
                    --border: #334155;
                }
            }
            body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .auth-card { background: var(--card-bg); padding: 3rem 2rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 90%; }
            h1 { font-size: 1.5rem; color: var(--text-main); margin-bottom: 0.5rem; }
            p { color: var(--text-muted); font-size: 1.1rem; margin-bottom: 2rem; }
            input[type="text"] { width: 100%; padding: 1rem; font-size: 2rem; text-align: center; letter-spacing: 5px; border: 2px solid var(--border); border-radius: 12px; margin-bottom: 1.5rem; box-sizing: border-box; background: var(--bg); color: var(--text-main); }
            input[type="text"]:focus { outline: none; border-color: #6366f1; }
            button { width: 100%; padding: 1rem; background: #6366f1; color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: 0.2s; }
            button:hover { background: #4f46e5; }
            .error { color: #ef4444; margin-bottom: 1rem; font-weight: 500; }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <h1>Acceso Protegido</h1>
            <p>Ingresa el PIN de 6 dígitos proporcionado por la agencia para ver tu tablero.</p>
            <?php if ($errorMsg): ?><div class="error"><?php echo $errorMsg; ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="pin" maxlength="6" autocomplete="off" autofocus required placeholder="------">
                <button type="submit">Ingresar</button>
            </form>
        </div>
    <!-- LIGHTBOX MODAL -->
<div class="lightbox-modal" id="lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-close"><i class="ph ph-x"></i></div>
    <img src="" class="lightbox-img" id="lightbox-img">
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
    // Check if there are active (unresolved/pending) comments to disable the approve button
    $p['has_active_comments'] = count($p['comments']) > 0;
    
    $posts[] = $p;
}

function renderPreviewBox($urlStr, $isRef = true) {
    if (!$urlStr) {
        return '<div style="background:var(--surface-hover); display:flex; align-items:center; justify-content:center; height:250px; border-radius:12px; color:var(--text-muted);"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
    }

    $mediaList = json_decode($urlStr, true);
    // Fix: plain URL (not JSON) must be treated as single-item array
    if (!is_array($mediaList) || count($mediaList) === 0) {
        $mediaList = !empty($urlStr) ? [$urlStr] : [];
    }
    if (empty($mediaList)) {
        return '<div style="background:var(--surface-hover); display:flex; align-items:center; justify-content:center; height:250px; border-radius:12px; color:var(--text-muted);"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
    }

    if (count($mediaList) > 1) {
        $html = '<div class="swiper-media" style="width:100%;height:100%;border-radius:12px;overflow:hidden;position:relative;font-size:0;"><div class="swiper-wrapper" style="height:100%;">';
        foreach($mediaList as $mItem) {
            $html .= '<div class="media-slide" style="background:var(--surface-hover);border:1px solid var(--border-color);border-radius:12px;cursor:pointer;" onclick="openLightbox(\''.htmlspecialchars($mItem).'\')"><img src="'.htmlspecialchars($mItem).'" style="width:100%;height:100%;object-fit:contain;display:block;margin:0 auto;border-radius:12px;"></div>';
        }
        $html .= '</div><div class="swiper-button-next" style="color:var(--primary);transform:scale(0.6);"></div><div class="swiper-button-prev" style="color:var(--primary);transform:scale(0.6);"></div><div class="swiper-pagination"></div></div>';
        return $html;
    }

    $url = $mediaList[0];
    $isDriveImage = preg_match('/drive\.google\.com\/(uc\?export=view&id=|thumbnail\?id=)([\w-]+)/i', $url);
    $isVideoLink = !$isDriveImage && preg_match('/(youtu\.be|youtube\.com|tiktok\.com|\.mp4|\.webm|\.mov|drive\.google\.com|instagram\.com|facebook\.com|fb\.watch|pinterest\.com|pin\.it)/i', $url);

    if ($isVideoLink) {
        // YouTube
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $ytMatch)) {
            $isShort = strpos($url, 'shorts') !== false;
            if ($isShort) {
                return '<div style="width:100%;max-width:320px;margin:0 auto;aspect-ratio:9/16;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="https://www.youtube.com/embed/'.$ytMatch[1].'?autoplay=0" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
            }
            return '<div style="width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="https://www.youtube.com/embed/'.$ytMatch[1].'?autoplay=0" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
        }
        // MP4
        elseif (preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url)) {
            return '<video controls style="width: 100%; max-height: 500px; object-fit: contain; border-radius: 12px; background: #000;"><source src="'.htmlspecialchars($url).'" type="video/mp4"></video>';
        }
        // TikTok with video ID
        elseif (preg_match('/tiktok\.com\/@[\w.]+\/video\/(\d+)/', $url, $tkMatch)) {
            return '<div style="width:100%;max-width:320px;margin:0 auto;aspect-ratio:9/16;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="https://www.tiktok.com/embed/v2/'.$tkMatch[1].'" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
        }
        // TikTok (any other tiktok URL)
        elseif (preg_match('/tiktok\.com/i', $url)) {
            return '<div style="width:100%;max-width:320px;margin:0 auto;aspect-ratio:9/16;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="'.htmlspecialchars($url).'" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
        }
        // Instagram — Meta blocks embeds, show social card
        elseif (preg_match('/instagram\.com\/(?:p|reel|tv)\//i', $url)) {
            $short = mb_strlen($url) > 55 ? mb_substr($url, 0, 52).'…' : $url;
            return '
            <div style="background:linear-gradient(135deg,#1a0a12,#2d0f1e,#1a0a12);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.2rem;padding:2rem;border:1px solid rgba(225,48,108,0.2);position:relative;overflow:hidden;min-height:220px;">
                <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(225,48,108,0.12),transparent 70%);pointer-events:none;"></div>
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(225,48,108,0.12);border:1px solid rgba(225,48,108,0.3);padding:5px 14px;border-radius:30px;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;color:#f06292;text-transform:uppercase;font-family:Inter,sans-serif;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 256 256"><path d="M128,80a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160ZM176,24H80A56.06,56.06,0,0,0,24,80v96a56.06,56.06,0,0,0,56,56h96a56.06,56.06,0,0,0,56-56V80A56.06,56.06,0,0,0,176,24Zm40,152a40,40,0,0,1-40,40H80a40,40,0,0,1-40-40V80A40,40,0,0,1,80,40h96a40,40,0,0,1,40,40ZM192,76a12,12,0,1,1-12-12A12,12,0,0,1,192,76Z"/></svg>
                    Instagram Reel
                </div>
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(225,48,108,0.1);border:2px solid rgba(225,48,108,0.25);display:flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#E1306C" viewBox="0 0 256 256"><path d="M240,128a15.74,15.74,0,0,1-7.6,13.51L88.32,229.65a16,16,0,0,1-16.2.3A15.86,15.86,0,0,1,64,216.13V39.87a15.86,15.86,0,0,1,8.12-13.82,16,16,0,0,1,16.2.3L232.4,114.49A15.74,15.74,0,0,1,240,128Z"/></svg>
                </div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.25);word-break:break-all;text-align:center;max-width:260px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:Inter,sans-serif;box-shadow:0 4px 15px rgba(225,48,108,0.35);">
                    ▶ Ver en Instagram
                </a>
            </div>';
        }
        // Facebook — Meta blocks embeds, show social card
        elseif (preg_match('/facebook\.com|fb\.watch/i', $url)) {
            $short = mb_strlen($url) > 55 ? mb_substr($url, 0, 52).'…' : $url;
            return '
            <div style="background:linear-gradient(135deg,#0d1b3e,#1a2a5e,#0a1628);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.2rem;padding:2rem;border:1px solid rgba(24,119,242,0.25);position:relative;overflow:hidden;min-height:220px;">
                <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(24,119,242,0.15),transparent 70%);pointer-events:none;"></div>
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(24,119,242,0.15);border:1px solid rgba(24,119,242,0.3);padding:5px 14px;border-radius:30px;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;color:#60a5fa;text-transform:uppercase;font-family:Inter,sans-serif;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm8,191.63V152h24a8,8,0,0,0,0-16H136V112a16,16,0,0,1,16-16h16a8,8,0,0,0,0-16H152a32,32,0,0,0-32,32v24H96a8,8,0,0,0,0,16h24v63.63a88,88,0,1,1,16,0Z"/></svg>
                    Facebook Reel
                </div>
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(24,119,242,0.12);border:2px solid rgba(24,119,242,0.25);display:flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="#1877F2" viewBox="0 0 256 256"><path d="M240,128a15.74,15.74,0,0,1-7.6,13.51L88.32,229.65a16,16,0,0,1-16.2.3A15.86,15.86,0,0,1,64,216.13V39.87a15.86,15.86,0,0,1,8.12-13.82,16,16,0,0,1,16.2.3L232.4,114.49A15.74,15.74,0,0,1,240,128Z"/></svg>
                </div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.25);word-break:break-all;text-align:center;max-width:260px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#1877F2;color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:Inter,sans-serif;box-shadow:0 4px 15px rgba(24,119,242,0.4);">
                    ▶ Ver en Facebook
                </a>
            </div>';
        }
        // Google Drive
        elseif (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/', $url, $drMatch)) {
            return '<div style="width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="https://drive.google.com/file/d/'.$drMatch[1].'/preview" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
        }
        // Fallback external link
        else {
            return '<a href="'.htmlspecialchars($url).'" target="_blank" style="display:block; padding:2rem; background:rgba(59,130,246,0.1); color:#3b82f6; text-align:center; border-radius:12px; text-decoration:none; font-weight:700;">Ver Video <br><span style="font-size:0.8rem; font-weight:normal;">Enlace Externo</span></a>';
        }
    } else {
        return '<img src="'.htmlspecialchars($url).'" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain; background: var(--surface-hover); border: 1px solid var(--border-color); cursor: pointer;" onclick="openLightbox(\''.htmlspecialchars($url).'\')">';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tablero | <?php echo $title; ?></title>
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <style>
        :root {
            --primary: #059b6c;
            --primary-hover: #047857;
            --bg: #f5f5f7;
            --card-bg: #ffffff;
            --text-main: #1d1d1f;
            --text-muted: #86868b;
            --border-color: #d2d2d7;
            --surface: #f5f5f7;
            --surface-hover: #fafafa;
            --radius: 20px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 13px;
            background: var(--bg);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .board-container {
            width: 100%;
            max-width: 450px;
            background: var(--card-bg);
            height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* Base / Mobile overrides */
        .btn-approve-desktop {
            display: none !important;
            margin-top: 1rem;
            width: auto;
        }
        @media (min-width: 600px) {
            .board-container {
                height: 90vh;
                border-radius: var(--radius);
                overflow: hidden;
                box-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
            }
        }
        @media (min-width: 1000px) {
            .board-container {
                max-width: 1100px;
            }
            .tabs {
                display: none !important;
            }
            .content-area {
                display: grid !important;
                grid-template-columns: 1fr 1.3fr 1fr;
                gap: 1.5rem;
                padding: 1.5rem !important;
                height: 100%;
                box-sizing: border-box;
            }
            .tab-content {
                display: flex !important;
                flex-direction: column;
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
                height: 100%;
                min-height: 0;
                min-width: 0;
            }
            .tab-content .content-box {
                flex: 1;
                display: flex;
                flex-direction: column;
                min-height: 0;
            }

            .col-title {
                display: block !important;
            }
            .btn-approve-mobile {
                display: none !important;
            }
            .btn-approve-desktop {
                display: inline-flex !important;
            }
        }
        
        .col-title {
            display: none;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0.75rem;
        }
        #mainSwiper { width: 100%; flex: 1; }
        #mainSwiper > .swiper-wrapper > .swiper-slide {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow-y: auto;
            padding-bottom: 90px;
            box-sizing: border-box;
        }
        
        /* HEADER */
        .card-header {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(245,245,247,0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .post-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            letter-spacing: -0.01em;
        }
        .date-pill {
            font-size: 11px;
            font-weight: 500;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: transparent;
        }
        .date-pill i { font-size: 12px; }

        .status-pill {
            font-size: 10px;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            letter-spacing: 0.3px;
        }
        .status-pill i { font-size: 10px; }
        
        .status-pill.publicado {
            background: rgba(5,155,108,0.1);
            color: #059b6c;
        }
        .status-pill.aprobado { 
            background: rgba(59,130,246,0.1); 
            color: #3b82f6; 
        }
        .status-pill.rechazado { 
            background: rgba(249,115,22,0.1); 
            color: #f97316; 
        }
        .status-pill.pendiente {
            background: rgba(100,116,139,0.1);
            color: #64748b;
        }
        
        /* MEDIA SWITCHER */
        .media-switcher {
            display: flex;
            background: rgba(0,0,0,0.04);
            border-radius: 8px;
            padding: 3px;
            margin-bottom: 0.75rem;
            flex-shrink: 0;
        }
        .media-switch-btn {
            flex: 1;
            padding: 0.4rem 0.5rem;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .media-switch-btn.active {
            background: white;
            color: var(--text-main);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
        }
        .media-pane { display: none; font-size: 0; height: 100%; }
        .media-pane.active { display: block; animation: fadeIn 0.3s; font-size: 0; height: 100%; }
        .media-pane > * { font-size: 13px; height: 100%; }
        
        .media-slide {
            flex-shrink: 0;
            width: 100%;
            height: 100%;
            position: relative;
            transition-property: transform;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* GRID NAV BUTTON */
        .btn-grid-nav {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: rgba(0,0,0,0.04);
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .btn-grid-nav:hover {
            background: rgba(0,0,0,0.08);
            color: var(--text-main);
        }

        /* GRID DRAWER */
        .grid-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9998;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .grid-drawer-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .grid-drawer {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            max-width: 90vw;
            height: 100vh;
            background: #fff;
            z-index: 9999;
            box-shadow: -8px 0 30px rgba(0,0,0,0.12);
            transition: right 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }
        .grid-drawer.active {
            right: 0;
        }
        .grid-drawer-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .grid-drawer-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text-main);
        }
        .grid-drawer-close {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: none;
            background: rgba(0,0,0,0.04);
            color: var(--text-muted);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .grid-drawer-close:hover {
            background: rgba(0,0,0,0.08);
            color: var(--text-main);
        }
        .grid-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        .grid-drawer-body::-webkit-scrollbar { width: 4px; }
        .grid-drawer-body::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }
        .grid-drawer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .grid-thumb {
            border-radius: 14px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            background: #f8f9fb;
            position: relative;
        }
        .grid-thumb:hover {
            border-color: rgba(99,102,241,0.3);
            box-shadow: 0 4px 12px rgba(99,102,241,0.1);
            transform: translateY(-2px);
        }
        .grid-thumb.active {
            border-color: var(--primary);
            box-shadow: 0 4px 16px rgba(5,155,108,0.15);
        }
        .grid-thumb-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
            background: #f1f3f8;
        }
        .grid-thumb-placeholder {
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1f3f8 0%, #e8ecf4 100%);
            color: var(--text-muted);
            font-size: 2rem;
        }
        .grid-thumb-info {
            padding: 0.6rem 0.75rem;
        }
        .grid-thumb-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
            letter-spacing: -0.01em;
        }
        .grid-thumb-meta {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .grid-thumb-status {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 5px;
            letter-spacing: 0.3px;
        }
        .grid-thumb-date {
            font-size: 10px;
            color: var(--text-muted);
        }
        
        /* CUSTOM MODAL */
        .custom-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .custom-modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .custom-modal {
            background: #ffffff;
            width: 90%;
            max-width: 320px;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            transform: scale(0.95);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .custom-modal-overlay.active .custom-modal {
            transform: scale(1);
        }
        .custom-modal-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
        .custom-modal-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-main); letter-spacing: -0.01em; }
        .custom-modal-text { font-size: 13px; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.5; }
        .custom-modal-buttons { display: flex; gap: 10px; justify-content: center; }
        .btn-modal { flex: 1; padding: 0.6rem 1rem; border-radius: 980px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; }
        .btn-modal-cancel { background: rgba(0,0,0,0.05); color: var(--text-main); }
        .btn-modal-cancel:hover { background: rgba(0,0,0,0.1); }
        .custom-modal-textarea {
            width: 100%;
            min-height: 80px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 0.75rem;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            margin-bottom: 1rem;
            box-sizing: border-box;
            background: #f9fafb;
            transition: border-color 0.2s;
            display: none;
        }
        .custom-modal-textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }
        .btn-modal-confirm { background: var(--primary); color: white; }
        .btn-modal-confirm:hover { background: var(--primary-hover); transform: scale(1.02); }

        /* LIGHTBOX */
        .lightbox-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .lightbox-modal.active { display: flex; animation: fadeIn 0.3s; }
        .lightbox-img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.4); }

        /* TABS */
        .tabs {
            display: flex;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 1rem;
            gap: 0;
        }
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 0.6rem 0;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            color: var(--text-main);
            border-bottom-color: var(--text-main);
            font-weight: 600;
        }
        
        /* CONTENT AREA */
        .content-area {
            padding: 0 1.5rem;
            flex: 1;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.25s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        
        .content-box {
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 12px;
            padding: 1.25rem;
            font-size: 13px;
            color: #424245;
            line-height: 1.7;
            min-height: 200px;
            background: #fafafa;
        }
        
        /* BUTTON */
        .btn-approve {
            width: auto;
            margin: 1rem 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 980px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .btn-approve:hover:not(:disabled) { 
            background: var(--primary-hover); 
            transform: scale(1.02);
        }
        .btn-approve:active:not(:disabled) {
            transform: scale(0.98);
        }
        .btn-approve.disabled-gray {
            background: rgba(0,0,0,0.08);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .btn-approve.disabled-green {
            background: rgba(5,155,108,0.12);
            color: #059b6c;
            cursor: not-allowed;
        }
        
        .btn-approve-mobile {
            position: sticky;
            bottom: 70px;
            z-index: 50;
            width: calc(100% - 3rem);
            margin: 1rem auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        /* BOTTOM NAV */
        .bottom-nav {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: rgba(255,255,255,0.72);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            z-index: 10;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        .nav-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .nav-btn:hover { background: rgba(0,0,0,0.04); color: var(--text-main); }
        .nav-btn.swiper-button-disabled { opacity: 0.3; cursor: not-allowed; }
        .nav-btn.swiper-button-disabled:hover { background: transparent; color: var(--text-muted); }
        
        .comment-item { 
            margin-bottom: 0.6rem; 
            padding: 0.85rem 1rem; 
            border: none; 
            border-radius: 14px; 
            background: linear-gradient(135deg, #f8f9fb 0%, #f1f3f8 100%); 
            white-space: normal;
            border-left: 3px solid #c7d2fe;
            transition: all 0.2s ease;
            position: relative;
        }
        .comment-item:hover {
            background: linear-gradient(135deg, #f0f2f8 0%, #e8ecf4 100%);
            border-left-color: #818cf8;
            box-shadow: 0 2px 8px rgba(99,102,241,0.08);
        }
        .comment-item:last-child { margin-bottom: 0; }
        .comment-header { 
            display: flex; 
            align-items: center; 
            font-size: 11.5px; 
            color: var(--text-muted); 
            margin-bottom: 0.5rem; 
            gap: 6px; 
        }
        .comment-date {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-weight: 500;
            color: #6b7280;
        }
        .comment-date i { font-size: 13px; color: #a5b4fc; }
        .comment-status {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .comment-status-pendiente,
        .comment-status { background: #fef3c7; color: #d97706; }
        .comment-status-levantado { background: #d1fae5; color: #059669; }
        .comment-actions {
            margin-left: auto;
            display: flex;
            gap: 2px;
        }
        .comment-action-btn {
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 13px;
        }
        .comment-action-btn.edit { color: #818cf8; }
        .comment-action-btn.edit:hover { background: #eef2ff; color: #6366f1; }
        .comment-action-btn.delete { color: #fca5a5; }
        .comment-action-btn.delete:hover { background: #fef2f2; color: #ef4444; }
        .comment-text { font-size: 13px; color: var(--text-main); line-height: 1.55; white-space: pre-wrap; }
        .comments-scroll-area {
            flex: 1; 
            overflow-y: auto; 
            margin-bottom: 1rem;
            padding-right: 4px;
        }
        .comments-scroll-area::-webkit-scrollbar { width: 4px; }
        .comments-scroll-area::-webkit-scrollbar-track { background: transparent; }
        .comments-scroll-area::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }
        .comments-scroll-area::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
        .comment-textarea { 
            width: 100%; 
            border: 1px solid rgba(0,0,0,0.08); 
            border-radius: 10px; 
            padding: 0.65rem 0.75rem; 
            margin-bottom: 0.6rem; 
            font-family: inherit; 
            font-size: 13px; 
            resize: vertical; 
            box-sizing: border-box;
            background: #fafafa;
            transition: border-color 0.2s;
            outline: none;
        }
        .comment-textarea:focus {
            border-color: var(--primary);
            background: white;
        }
        .btn-submit-comment { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 0.55rem 1rem; 
            border-radius: 980px; 
            font-weight: 600; 
            font-size: 12px;
            cursor: pointer; 
            width: 100%; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-submit-comment:hover {
            background: var(--primary-hover);
            transform: scale(1.01);
        }
        .btn-submit-comment:active {
            transform: scale(0.98);
        }
        
    </style>
</head>
<body>

<div class="board-container">
    <div class="swiper" id="mainSwiper">
        <div class="swiper-wrapper">
            <?php 
            $index = 1;
            foreach ($posts as $p): 
                // Determine Status Pill
                $statusClass = '';
                $statusIcon = 'ph-tag';
                $lStatus = mb_strtolower($p['status'], 'UTF-8');
                
                if ($lStatus === 'publicado') {
                    $statusClass = 'publicado';
                } elseif ($lStatus === 'aprobado') {
                    $statusClass = 'aprobado';
                } elseif (strpos($lStatus, 'rechazado') !== false || strpos($lStatus, 'corrección') !== false || strpos($lStatus, 'revisión') !== false || strpos($lStatus, 'revision') !== false) {
                    $statusClass = 'rechazado';
                } else {
                    $statusClass = 'pendiente';
                }

                // Determine Button State
                $btnClass = '';
                $btnText = 'APROBAR CONTENIDO';
                $btnDisabled = '';
                if ($p['status'] === 'Aprobado' || $p['status'] === 'Publicado') {
                    $btnClass = 'disabled-green';
                    $btnText = 'APROBADO';
                    $btnDisabled = 'disabled';
                } elseif ($p['has_active_comments']) {
                    $btnClass = 'disabled-gray';
                    $btnText = 'EN REVISIÓN';
                    $btnDisabled = 'disabled';
                }
            ?>
            <div class="swiper-slide" data-post-id="<?php echo $p['id']; ?>">
                <div class="card-header">
                    <div class="post-title">
                        POST <?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>
                        <div class="date-pill">
                            <i class="ph ph-calendar-blank"></i> <?php echo empty($p['post_date']) || $p['post_date'] === '0000-00-00 00:00:00' ? 'Sin Fecha' : date('d M Y', strtotime($p['post_date'])); ?>
                        </div>
                        <div class="status-pill <?php echo $statusClass; ?>">
                            <i class="ph <?php echo $statusIcon; ?>"></i> <?php echo htmlspecialchars($p['status']); ?>
                        </div>
                    </div>
                    <button class="btn-grid-nav" onclick="openGridDrawer()" title="Ver todos los posts">
                        <i class="ph ph-squares-four"></i>
                    </button>
                </div>
                
                <div class="tabs">
                    <div class="tab-btn active" onclick="switchTab(this, 'media-<?php echo $p['id']; ?>')">Multimedia</div>
                    <div class="tab-btn" onclick="switchTab(this, 'copy-<?php echo $p['id']; ?>')">Copy del post</div>
                    <div class="tab-btn" onclick="switchTab(this, 'comment-<?php echo $p['id']; ?>')">Comentario</div>
                </div>
                
                <div class="content-area">
                    <!-- COPY -->
                    <div class="tab-content" id="copy-<?php echo $p['id']; ?>">
                        <div class="col-title">Copy del Post</div>
                        <div class="content-box" style="white-space: pre-wrap;"><?php echo empty($p['copy_text']) ? 'Sin copy asignado.' : $p['copy_text']; ?></div>
                        
                        <button class="btn-approve btn-approve-desktop <?php echo $btnClass; ?>" <?php echo $btnDisabled; ?> onclick="approvePost(<?php echo $p['id']; ?>, this)">
                            <?php echo $btnText; ?>
                        </button>
                    </div>
                    
                    <!-- MULTIMEDIA -->
                    <div class="tab-content active" id="media-<?php echo $p['id']; ?>">
                        <div class="col-title">Multimedia</div>
                        <div class="media-switcher">
                            <button type="button" class="media-switch-btn active" onclick="switchMedia(this, 'ref-<?php echo $p['id']; ?>', 'final-<?php echo $p['id']; ?>')">Referencia gráfica</button>
                            <button type="button" class="media-switch-btn" onclick="switchMedia(this, 'final-<?php echo $p['id']; ?>', 'ref-<?php echo $p['id']; ?>')">Post Terminado</button>
                        </div>
                        <div class="content-box" style="padding: 0; overflow: hidden; border: none; background: transparent;">
                            <div class="media-pane active" id="ref-<?php echo $p['id']; ?>">
                                <?php echo renderPreviewBox($p['reference_image_link'] ?? null, true); ?>
                            </div>
                            <div class="media-pane" id="final-<?php echo $p['id']; ?>">
                                <?php if(empty($p['image_link'])): ?>
                                    <div style="background:var(--surface); display:flex; flex-direction:column; align-items:center; justify-content:center; height:250px; border-radius:12px; color:var(--text-muted); opacity: 0.6; border: 1px solid var(--border-color);">
                                        <i class="ph ph-image" style="font-size:3rem; margin-bottom:1rem;"></i>
                                        <p style="font-size: 13px; font-weight:600;">Aún no se ha subido el diseño final.</p>
                                    </div>
                                <?php else: ?>
                                    <?php echo renderPreviewBox($p['image_link'], false); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- COMENTARIO -->
                    <div class="tab-content" id="comment-<?php echo $p['id']; ?>">
                        <div class="col-title">Comentarios</div>
                        <div class="content-box" style="padding: 1rem; min-height: 200px; display: flex; flex-direction: column;">
                            <div class="comments-scroll-area">
                            <?php if(empty($p['comments'])): ?>
                                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); opacity:0.6; padding-top: 3rem;">
                                    <i class="ph ph-chat-centered-text" style="font-size:3rem; margin-bottom:1rem;"></i>
                                    <p style="font-size: 13px; font-weight:600; text-align:center;">Aún no hay comentarios en este post.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($p['comments'] as $c): ?>
                                    <div class="comment-item">
                                        <div class="comment-header">
                                            <div class="comment-date">
                                                <i class="ph ph-clock"></i>
                                                <?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?>
                                            </div>
                                            <div class="comment-status"><?php echo $c['status']; ?></div>
                                            <div class="comment-actions">
                                                <button class="comment-action-btn edit" onclick="editComment(<?php echo $c['id']; ?>, this)"><i class="ph ph-pencil-simple"></i></button>
                                                <button class="comment-action-btn delete" onclick="deleteComment(<?php echo $c['id']; ?>)"><i class="ph ph-trash"></i></button>
                                            </div>
                                        </div>
                                        <div class="comment-text"><?php echo htmlspecialchars($c['comment_text']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </div>
                            
                            <form class="comment-form" onsubmit="submitComment(event, <?php echo $p['id']; ?>)" style="margin-top: auto;">
                                <textarea name="comment_text" class="comment-textarea" rows="3" placeholder="Escribe tu feedback o corrección aquí ..."></textarea>
                                <button type="submit" class="btn-submit-comment"><i class="ph ph-paper-plane-right" style="font-size: 1.1rem;"></i> Enviar Comentario</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <button class="btn-approve btn-approve-mobile <?php echo $btnClass; ?>" <?php echo $btnDisabled; ?> onclick="approvePost(<?php echo $p['id']; ?>, this)">
                    <?php echo $btnText; ?>
                </button>
            </div>
            <?php $index++; endforeach; ?>
        </div>
    </div>
    
    <div class="bottom-nav">
        <button class="nav-btn" id="btn-prev"><i class="ph ph-arrow-left"></i> Anterior</button>
        <button class="nav-btn" id="btn-next">Siguiente <i class="ph ph-arrow-right"></i></button>
    </div>
</div>

<script>
    // Custom Modal Logic
    function showModal(title, text, type = 'confirm') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('customModal');
            const icon = document.getElementById('modalIcon');
            const btnCancel = document.getElementById('modalBtnCancel');
            const btnConfirm = document.getElementById('modalBtnConfirm');
            const modalText = document.getElementById('modalText');
            const modalTextarea = document.getElementById('modalTextarea');
            
            document.getElementById('modalTitle').innerText = title;
            
            // Reset textarea
            modalTextarea.style.display = 'none';
            modalText.style.display = 'block';
            modalText.innerText = text;
            
            if (type === 'confirm') {
                icon.innerHTML = '<i class="ph ph-question" style="color: #3b82f6;"></i>';
                btnCancel.style.display = 'block';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'error') {
                icon.innerHTML = '<i class="ph ph-warning-circle" style="color: #ef4444;"></i>';
                btnCancel.style.display = 'none';
                btnConfirm.style.background = '#ef4444';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'success') {
                icon.innerHTML = '<i class="ph ph-check-circle" style="color: #059b6c;"></i>';
                btnCancel.style.display = 'none';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Aceptar';
            } else if (type === 'input') {
                icon.innerHTML = '<i class="ph ph-pencil-simple" style="color: #818cf8;"></i>';
                btnCancel.style.display = 'block';
                btnConfirm.style.background = 'var(--primary)';
                btnConfirm.innerText = 'Guardar';
                modalText.style.display = 'none';
                modalTextarea.style.display = 'block';
                modalTextarea.value = text;
                setTimeout(() => modalTextarea.focus(), 100);
            }

            overlay.classList.add('active');

            const handleConfirm = () => { 
                cleanup(); 
                if (type === 'input') {
                    resolve(modalTextarea.value);
                } else {
                    resolve(true); 
                }
            };
            const handleCancel = () => { cleanup(); resolve(false); };

            const cleanup = () => {
                overlay.classList.remove('active');
                btnConfirm.removeEventListener('click', handleConfirm);
                btnCancel.removeEventListener('click', handleCancel);
            };

            btnConfirm.addEventListener('click', handleConfirm);
            btnCancel.addEventListener('click', handleCancel);
        });
    }

    // Lightbox Logic
    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.add('active');
    }
    function closeLightbox(e) {
        if(e.target.id === 'lightbox' || e.target.closest('.lightbox-close')) {
            document.getElementById('lightbox').classList.remove('active');
        }
    }
    
    // Switch Media Logic
    function switchMedia(btn, showId, hideId) {
        const switcher = btn.closest('.media-switcher');
        switcher.querySelectorAll('.media-switch-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        document.getElementById(hideId).classList.remove('active');
        document.getElementById(showId).classList.add('active');
    }

    // Initialize Swiper
    const swiper = new Swiper('#mainSwiper', {
        navigation: {
            nextEl: '#btn-next',
            prevEl: '#btn-prev',
        },
        allowTouchMove: false, // Prevents accidental swiping while scrolling text
    });
    
    // Initialize nested media swipers
    const mediaSwipers = new Swiper('.swiper-media', {
        slideClass: 'media-slide',
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        nested: true,
        spaceBetween: 10,
    });

    // Tab Switching Logic
    function switchTab(btn, targetId) {
        // Find parent slide
        const slide = btn.closest('.swiper-slide');
        
        // Remove active class from all tabs in this slide
        slide.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        slide.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Add active class to clicked tab
        btn.classList.add('active');
        document.getElementById(targetId).classList.add('active');
        
        // El botón mobile se mantiene visible gracias al CSS sticky, 
        // ya no lo ocultamos al cambiar de tab.
    }

    // Approve Post Logic
    async function approvePost(postId, btnEl) {
        const confirmed = await showModal('Confirmar', '¿Confirmas que apruebas este contenido?', 'confirm');
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('id', postId);
        formData.append('status', 'Aprobado');

        const originalText = btnEl.innerText;
        btnEl.innerText = 'PROCESANDO...';
        btnEl.disabled = true;

        try {
            const response = await fetch('modules/month_board/ajax_update_post_status.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                // Update UI visually
                btnEl.className = 'btn-approve disabled-green';
                btnEl.innerText = 'APROBADO';
                
                // Update Pill
                const pill = btnEl.closest('.swiper-slide').querySelector('.status-pill');
                pill.className = 'status-pill aprobado';
                pill.innerHTML = '<i class="ph ph-check-circle-fill"></i> Aprobado';
                
                await showModal('¡Listo!', 'El contenido ha sido aprobado correctamente.', 'success');
            } else {
                await showModal('Error', 'Error al aprobar: ' + result.error, 'error');
                btnEl.innerText = originalText;
                btnEl.disabled = false;
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión.', 'error');
            btnEl.innerText = originalText;
            btnEl.disabled = false;
        }
    }

    // Submit Comment Logic
    async function submitComment(e, postId) {
        e.preventDefault();
        const form = e.target;
        const textarea = form.querySelector('textarea');
        const text = textarea.value.trim();
        const btn = form.querySelector('button');
        
        if (!text) return;

        btn.disabled = true;
        btn.innerText = 'Enviando...';

        const fd = new FormData();
        fd.append('post_id', postId);
        fd.append('month_id', <?php echo $id; ?>);
        fd.append('comment_text', text);
        fd.append('comment_phase', 'Parrilla Final');

        try {
            const response = await fetch('ajax_save_public_comment.php', {
                method: 'POST',
                body: fd
            });
            const result = await response.json();
            
            if (result.success) {
                // Reload to show the comment and update button status
                location.reload();
            } else {
                await showModal('Error', 'Error: ' + result.error, 'error');
                btn.disabled = false;
                btn.innerText = 'Enviar Comentario';
            }
        } catch (err) {
            console.error(err);
            await showModal('Error', 'Error de conexión.', 'error');
            btn.disabled = false;
            btn.innerText = 'Enviar Comentario';
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
            const result = await response.json();
            if (result.success) {
                commentTextEl.innerText = newText;
                await showModal('¡Listo!', 'Comentario actualizado correctamente.', 'success');
            } else {
                await showModal('Error', 'Error: ' + result.error, 'error');
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión.', 'error');
        }
    }

    // Delete Comment Logic
    async function deleteComment(commentId) {
        const confirmed = await showModal('Eliminar Comentario', '¿Estás seguro de que deseas eliminar este comentario?', 'confirm');
        if (!confirmed) return;

        const fd = new FormData();
        fd.append('id', commentId);

        try {
            const response = await fetch('ajax_delete_public_comment.php', { method: 'POST', body: fd });
            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                await showModal('Error', 'Error: ' + result.error, 'error');
            }
        } catch (e) {
            console.error(e);
            await showModal('Error', 'Error de conexión.', 'error');
        }
    }
</script>

<!-- CUSTOM MODAL -->
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
        <div class="grid-drawer-title"><i class="ph ph-squares-four" style="margin-right: 6px;"></i> Todos los Posts</div>
        <button class="grid-drawer-close" onclick="closeGridDrawer()"><i class="ph ph-x"></i></button>
    </div>
    <div class="grid-drawer-body">
        <div class="grid-drawer-grid">
            <?php 
            $thumbIndex = 1;
            foreach($posts as $tp): 
                // Get first image for thumbnail
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
                // Status class
                $tStatus = strtolower(trim(explode('(', $tp['status'] ?? '')[0]));
                $tStatusMap = [
                    'borrador' => 'background:#f3f4f6;color:#6b7280;',
                    'en revisión' => 'background:#fff7ed;color:#f97316;',
                    'aprobado' => 'background:rgba(59,130,246,0.1);color:#3b82f6;',
                    'publicado' => 'background:rgba(5,155,108,0.1);color:#059b6c;',
                ];
                $tStatusStyle = $tStatusMap[$tStatus] ?? 'background:#f3f4f6;color:#6b7280;';
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
                        elseif (preg_match('/youtu/i', $thumbSrc)) { $platformColor = '#ff0000'; $platformIcon = 'ph-youtube-logo'; }
                        elseif (preg_match('/instagram/i', $thumbSrc)) { $platformColor = '#E1306C'; $platformIcon = 'ph-instagram-logo'; }
                        elseif (preg_match('/facebook|fb\.watch/i', $thumbSrc)) { $platformColor = '#1877F2'; $platformIcon = 'ph-facebook-logo'; }
                        elseif (preg_match('/pinterest|pin\.it/i', $thumbSrc)) { $platformColor = '#E60023'; $platformIcon = 'ph-pinterest-logo'; }
                    }
                }
                ?>
                <?php if ($isVideo): ?>
                    <div class="grid-thumb-placeholder" style="background:linear-gradient(135deg,#f8f9fb,#eef1f6);">
                        <div style="width:40px;height:40px;border-radius:50%;background:<?php echo $platformColor; ?>;display:flex;align-items:center;justify-content:center;opacity:0.9;">
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
                    <div class="grid-thumb-meta">
                        <span class="grid-thumb-status" style="<?php echo $tStatusStyle; ?>"><?php echo htmlspecialchars($tp['status']); ?></span>
                    </div>
                </div>
            </div>
            <?php $thumbIndex++; endforeach; ?>
        </div>
    </div>
</div>

<!-- LIGHTBOX MODAL -->
<div class="lightbox-modal" id="lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-close"><i class="ph ph-x"></i></div>
    <img src="" class="lightbox-img" id="lightbox-img">
</div>

<script>
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
        closeGridDrawer();
    }
</script>
</body>
</html>