<?php
// public_post.php
require_once 'config/database.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exp = isset($_GET['exp']) ? (int)$_GET['exp'] : 0;
$sig = isset($_GET['sig']) ? $_GET['sig'] : '';

if ($id <= 0 || $exp <= 0 || empty($sig)) {
    die("Enlace no válido.");
}

if (time() > $exp) {
    die("Este enlace ha caducado.");
}

$secret = 'ROMA_SECRET_' . $id;
$expected_sig = md5($secret . $exp);
if ($sig !== $expected_sig) {
    die("Firma inválida o enlace corrupto.");
}

$db = (new Database())->getConnection();

// Fetch Global Settings
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$global_settings = [];
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Post and Project Month
$stmt = $db->prepare("
    SELECT mp.*, pm.month, pm.year, pm.id as month_id, w.brand_name 
    FROM month_posts mp
    JOIN project_months pm ON mp.month_id = pm.id
    JOIN projects p ON pm.project_id = p.id
    JOIN work_orders w ON p.work_order_id = w.id
    WHERE mp.id = ?
");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die("La publicación solicitada no existe.");
}

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$stmtAll = $db->prepare("SELECT id FROM month_posts WHERE month_id = ? ORDER BY CASE WHEN post_date IS NULL OR post_date = '0000-00-00' OR post_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC, post_date ASC, id ASC");
$stmtAll->execute([$post['month_id']]);
$allIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
$postIndexStr = str_pad($id, 2, '0', STR_PAD_LEFT);
foreach ($allIds as $idx => $pid) {
    if ($pid == $id) {
        $postIndexStr = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
        break;
    }
}

$title = htmlspecialchars($post['brand_name']) . " - Post " . $postIndexStr;

// Status colors
$statusColors = [
    'Borrador' => ['bg' => 'rgba(100, 116, 139, 0.15)', 'color' => '#94a3b8', 'label' => 'Borrador'],
    'En Revisión' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#fbbf24', 'label' => 'En Revisión'],
    'En Revisión (Con Cambios)' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#fbbf24', 'label' => 'En Revisión'],
    'Aprobado' => ['bg' => 'rgba(59, 130, 246, 0.15)', 'color' => '#60a5fa', 'label' => 'Aprobado'],
    'Publicado' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'color' => '#34d399', 'label' => 'Publicado'],
    'Archivado' => ['bg' => 'rgba(147, 51, 234, 0.15)', 'color' => '#a78bfa', 'label' => 'Archivado'],
];
$sc = $statusColors[$post['status']] ?? $statusColors['Borrador'];

// Fetch comments
$stmtComments = $db->prepare("SELECT * FROM post_comments WHERE post_id = ? ORDER BY created_at ASC");
$stmtComments->execute([$id]);
$comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
$pendingCount = 0;
foreach ($comments as $c) {
    if ($c['status'] === 'Pendiente') $pendingCount++;
}

function highlightCopy($text) {
    $text = strip_tags($text ?? '');
    $text = htmlspecialchars($text);
    $text = preg_replace('/(#[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ_]+)/u', '<span class="hashtag">$1</span>', $text);
    $text = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" class="link-inline">$1</a>', $text);
    return nl2br($text);
}

function renderMedia($urlStr) {
    if (!$urlStr) {
        return '<div class="empty-media"><i class="ph ph-image"></i><p>Arte en proceso</p></div>';
    }
    $mediaList = json_decode($urlStr, true);
    if (!is_array($mediaList) || count($mediaList) === 0) {
        $mediaList = !empty($urlStr) ? [$urlStr] : [];
    }
    if (empty($mediaList)) return '<div class="empty-media"><i class="ph ph-image"></i><p>Arte en proceso</p></div>';
    
    // public_post only handles single media for now (first item)
    $url = is_string($mediaList[0]) ? $mediaList[0] : '';
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
                    <i class="ph ph-instagram-logo"></i> Instagram Reel
                </div>
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(225,48,108,0.1);border:2px solid rgba(225,48,108,0.25);display:flex;align-items:center;justify-content:center;">
                    <i class="ph ph-instagram-logo" style="font-size:1.8rem;color:#E1306C;"></i>
                </div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.25);word-break:break-all;text-align:center;max-width:260px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:Inter,sans-serif;box-shadow:0 4px 15px rgba(225,48,108,0.35);">
                    <i class="ph ph-play"></i> Ver en Instagram
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
                    <i class="ph ph-facebook-logo"></i> Facebook Reel
                </div>
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(24,119,242,0.12);border:2px solid rgba(24,119,242,0.25);display:flex;align-items:center;justify-content:center;">
                    <i class="ph ph-facebook-logo" style="font-size:1.8rem;color:#1877F2;"></i>
                </div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.25);word-break:break-all;text-align:center;max-width:260px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#1877F2;color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:Inter,sans-serif;box-shadow:0 4px 15px rgba(24,119,242,0.4);">
                    <i class="ph ph-play"></i> Ver en Facebook
                </a>
            </div>';
        }
        // Pinterest
        elseif (preg_match('/pinterest|pin\.it/i', $url)) {
            $short = mb_strlen($url) > 55 ? mb_substr($url, 0, 52).'…' : $url;
            return '
            <div style="background:linear-gradient(135deg,#2c070a,#3d090d,#1a0405);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.2rem;padding:2rem;border:1px solid rgba(230,0,35,0.25);position:relative;overflow:hidden;min-height:220px;">
                <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(230,0,35,0.15),transparent 70%);pointer-events:none;"></div>
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(230,0,35,0.15);border:1px solid rgba(230,0,35,0.3);padding:5px 14px;border-radius:30px;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;color:#ff6b81;text-transform:uppercase;font-family:Inter,sans-serif;">
                    <i class="ph ph-pinterest-logo"></i> Pinterest Pin
                </div>
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(230,0,35,0.12);border:2px solid rgba(230,0,35,0.25);display:flex;align-items:center;justify-content:center;">
                    <i class="ph ph-pinterest-logo" style="font-size:1.8rem;color:#E60023;"></i>
                </div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.25);word-break:break-all;text-align:center;max-width:260px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">'.htmlspecialchars($short).'</div>
                <a href="'.htmlspecialchars($url).'" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#E60023;color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:Inter,sans-serif;box-shadow:0 4px 15px rgba(230,0,35,0.4);">
                    <i class="ph ph-play"></i> Ver en Pinterest
                </a>
            </div>';
        }
        // Google Drive
        elseif (preg_match('/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/', $url, $drMatch)) {
            return '<div style="width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000;"><iframe width="100%" height="100%" src="https://drive.google.com/file/d/'.$drMatch[1].'/preview" frameborder="0" allowfullscreen style="border:none;"></iframe></div>';
        }
        // Fallback external link
        else {
            return '<a href="'.htmlspecialchars($url).'" target="_blank" class="video-external-link"><i class="ph ph-play-circle"></i> Ver Video Externo</a>';
        }
    } else {
        return '<img src="'.htmlspecialchars($url).'" class="media-image" alt="Arte" onclick="openLightbox(this.src)">';
    }
}

$dateFmt = (!empty($post['post_date']) && $post['post_date'] !== '0000-00-00' && $post['post_date'] !== '0000-00-00 00:00:00') ? date('d M Y', strtotime($post['post_date'])) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $title; ?></title>
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary: #667eea;
            --primary-hover: #5a6fd6;
            --accent: #764ba2;
            --shadow-card: 0 4px 24px rgba(0,0,0,0.06);
            --radius: 16px;
            --radius-sm: 10px;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            opacity: 0;
            animation: fadeInPage 0.6s ease-out forwards;
        }
        @keyframes fadeInPage { to { opacity: 1; } }

        /* ===== HERO HEADER ===== */
        .hero {
            background: linear-gradient(135deg, #059b6c 0%, #034b35 45%, #011a12 100%);
            padding: 2.5rem 2rem 2rem;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 40% -20%, rgba(12, 235, 161, 0.25), transparent 70%);
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-center {
            text-align: center;
            padding: 1rem 0 1.5rem;
        }
        .hero-post-number {
            font-size: 3.5rem;
            font-weight: 900;
            color: white;
            letter-spacing: 2px;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .hero-post-number span {
            color: white;
        }
        .hero-concept {
            color: #94a3b8;
            font-size: 1.1rem;
            font-weight: 400;
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        .hero-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.4rem 1rem;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 700;
            backdrop-filter: blur(8px);
        }
        .hero-badge-date {
            background: rgba(96, 165, 250, 0.12);
            border: 1px solid rgba(96, 165, 250, 0.25);
            color: #60a5fa;
        }
        .hero-badge-status {
            border: 1px solid transparent;
        }

        /* ===== MOBILE TABS ===== */
        .mobile-tabs {
            display: none;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .mobile-tabs-inner {
            display: flex;
            max-width: 640px;
            margin: 0 auto;
        }
        .mobile-tab {
            flex: 1;
            padding: 0.85rem 0.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            position: relative;
            transition: color 0.2s;
        }
        .mobile-tab.active {
            color: var(--primary);
        }
        .mobile-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 10%;
            width: 80%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px 3px 0 0;
        }
        .mobile-tab-badge {
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 99px;
            min-width: 18px;
            text-align: center;
        }

        /* ===== MEDIA SWITCHER ===== */
        .media-switcher {
            display: flex;
            background: var(--surface);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        .media-switch-btn {
            flex: 1;
            padding: 8px 12px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: transparent;
        }
        .media-switch-btn.active {
            background: var(--surface-hover);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .media-pane {
            display: none;
            animation: fadeIn 0.3s ease forwards;
        }
        .media-pane.active {
            display: block;
        }

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ===== GRID ===== */
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 380px 380px;
            gap: 1.5rem;
            align-items: start;
        }

        /* ===== CARDS ===== */
        .col-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.5s ease-out forwards;
        }
        .col-card:nth-child(1) { animation-delay: 0.1s; }
        .col-card:nth-child(2) { animation-delay: 0.2s; }
        .col-card:nth-child(3) { animation-delay: 0.3s; }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        .col-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }
        .col-title i { font-size: 1.1rem; color: var(--primary); }

        /* ===== COPY TEXT ===== */
        .copy-text {
            font-size: 13px;
            line-height: 1.75;
            white-space: pre-wrap;
            color: #334155;
        }
        .hashtag { color: var(--primary); font-weight: 600; }
        .link-inline { color: var(--primary); text-decoration: underline; }
        .platforms-section {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--border-color);
        }
        .section-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
        }
        .platform-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .platform-tag {
            background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08));
            border: 1px solid rgba(102,126,234,0.15);
            padding: 0.35rem 0.85rem;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .platform-tag i { color: var(--primary); }

        /* ===== MEDIA ===== */
        .media-image {
            width: 100%;
            border-radius: var(--radius-sm);
            display: block;
            cursor: zoom-in;
            transition: transform 0.2s;
        }
        .media-image:hover { transform: scale(1.01); }
        .empty-media {
            background: #f8fafc;
            padding: 3rem 2rem;
            text-align: center;
            border-radius: var(--radius-sm);
            color: #94a3b8;
            border: 2px dashed #e2e8f0;
        }
        .empty-media i { font-size: 3rem; margin-bottom: 0.5rem; display: block; }
        .empty-media p { margin: 0; font-weight: 500; }
        .video-wrapper {
            position: relative;
            padding-top: 56.25%;
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .video-wrapper iframe {
            position: absolute; inset: 0;
            width: 100%; height: 100%; border: none;
        }
        .video-wrapper.video-vertical { padding-top: 177%; }
        .media-video {
            width: 100%;
            border-radius: var(--radius-sm);
        }
        .video-external-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            text-align: center;
            padding: 1rem;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .video-external-link:hover { transform: translateY(-2px); }
        .media-block { margin-bottom: 1.5rem; }
        .media-block:last-child { margin-bottom: 0; }
        .media-block-ref { opacity: 0.85; }

        /* ===== COMMENTS ===== */
        .comments-col-inner {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
        }
        .comments-count {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-left: auto;
        }
        .comments-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.5rem;
            margin-bottom: 1rem;
        }
        .comments-list::-webkit-scrollbar { width: 4px; }
        .comments-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        .comment-item {
            background: linear-gradient(135deg, #f8f9fb 0%, #f1f3f8 100%);
            border: none;
            border-radius: 14px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.6rem;
            border-left: 3px solid #c7d2fe;
            opacity: 0;
            animation: commentSlide 0.3s ease-out forwards;
            transition: all 0.2s ease;
            position: relative;
        }
        .comment-item:hover {
            background: linear-gradient(135deg, #f0f2f8 0%, #e8ecf4 100%);
            border-left-color: #818cf8;
            box-shadow: 0 2px 8px rgba(99,102,241,0.08);
        }
        @keyframes commentSlide {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .comment-header {
            display: flex;
            align-items: center;
            font-size: 11.5px;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            gap: 6px;
        }
        .comment-date { display: flex; align-items: center; gap: 4px; font-weight: 500; color: #6b7280; }
        .comment-date i { font-size: 13px; color: #a5b4fc; }
        .comment-text {
            font-size: 0.9rem;
            line-height: 1.55;
            white-space: pre-wrap;
            color: var(--text-main);
        }
        .comment-status {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-Pendiente { background: #fef3c7; color: #d97706; }
        .status-Levantado { background: #d1fae5; color: #059669; }
        .comment-action-btn {
            width: 26px;
            height: 26px;
            display: inline-flex;
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
        .comments-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 3rem 1rem;
        }
        .comments-empty i { font-size: 3rem; opacity: 0.25; margin-bottom: 1rem; display: block; }
        .comments-empty p { font-weight: 500; }

        /* Comment Form */
        .comment-form {
            border-top: 1px solid var(--border-color);
            padding-top: 1.25rem;
        }
        .comment-textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 90px;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
            background: #fafbfc;
        }
        .comment-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
            background: white;
        }
        .btn-submit {
            background: linear-gradient(135deg, #059b6c 0%, #034b35 100%);
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(5,155,108,0.25);
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,155,108,0.4); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ===== LIGHTBOX ===== */
        .lightbox {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .lightbox.active { display: flex; opacity: 1; }
        .lightbox img {
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            cursor: default;
        }
        .lightbox-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 44px; height: 44px;
            border-radius: 50%;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.2); }

        /* ===== TOAST ===== */
        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f172a;
            color: white;
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            z-index: 10000;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .toast.visible { transform: translateX(-50%) translateY(0); }
        .toast-icon { color: #34d399; font-size: 1.2rem; }

        /* ===== CUSTOM MODAL ===== */
        .custom-modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 2000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s;
        }
        .custom-modal {
            background: var(--bg-card); width: 90%; max-width: 400px;
            border-radius: var(--radius); padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: transform 0.3s;
        }
        .custom-modal-overlay.show { display: flex; opacity: 1; }
        .custom-modal-overlay.show .custom-modal { transform: translateY(0); }
        .custom-modal h3 { font-size: 1.2rem; color: var(--text-main); margin-bottom: 0.5rem; }
        .custom-modal p { font-size: 0.95rem; color: var(--text-muted); }
        .custom-modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-cancel { background: #e2e8f0; color: #475569; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background: #cbd5e1; }
        .btn-confirm { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); }
        .btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(239, 68, 68, 0.4); }
        .btn-confirm.btn-edit-mode { background: linear-gradient(135deg, #059b6c 0%, #034b35 100%); box-shadow: 0 4px 10px rgba(5,155,108,0.3); }
        .btn-confirm.btn-edit-mode:hover { box-shadow: 0 6px 14px rgba(5,155,108,0.4); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .grid-3 { grid-template-columns: 1fr 1fr; }
            .comments-col { grid-column: 1 / -1; }
            .comments-col-inner { max-height: none; }
        }
        @media (max-width: 768px) {
            .hero { padding: 1.5rem 1rem 1.25rem; }
            .hero-post-number { font-size: 2.5rem; }
            .hero-concept { font-size: 0.95rem; }
            .container { padding: 0; }

            .mobile-tabs { display: block; }

            .grid-3 {
                grid-template-columns: 1fr;
                gap: 0;
                padding: 1rem 1rem 5rem 1rem;
            }
            .col-card {
                border-radius: var(--radius);
                margin-bottom: 0;
                display: none;
                opacity: 1;
                transform: none;
                animation: none;
                min-height: calc(100vh - 150px);
            }
            .col-card.mobile-active {
                display: block;
                animation: mobileTabIn 0.25s ease-out;
            }
            @keyframes mobileTabIn {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .comments-col-inner { max-height: none; }
        }
        @media (max-width: 480px) {
            .hero-post-number { font-size: 2rem; }
            .hero-meta { gap: 0.5rem; }
            .hero-badge { font-size: 0.8rem; padding: 0.35rem 0.75rem; }
        }
    </style>
</head>
<body>

<!-- HERO -->
<div class="hero">
    <div class="hero-inner">
        <div class="hero-center">
            <div class="hero-post-number">POST <span><?php echo $postIndexStr; ?></span></div>
            <?php if(!empty($post['concept'])): ?>
                <p class="hero-concept"><?php echo htmlspecialchars($post['concept']); ?></p>
            <?php endif; ?>
            <div class="hero-meta">
                <?php if($dateFmt): ?>
                <div class="hero-badge hero-badge-date">
                    <i class="ph ph-calendar-blank"></i> <?php echo $dateFmt; ?>
                </div>
                <?php endif; ?>
                <div class="hero-badge hero-badge-status" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['color']; ?>; border-color: <?php echo $sc['color']; ?>33;">
                    <i class="ph ph-circle-fill" style="font-size: 0.5rem;"></i> <?php echo htmlspecialchars($sc['label']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MOBILE TABS -->
<div class="mobile-tabs">
    <div class="mobile-tabs-inner">
        <button class="mobile-tab active" data-tab="0" onclick="switchTab(0)">
            <i class="ph ph-text-align-left"></i> Copy
        </button>
        <button class="mobile-tab" data-tab="1" onclick="switchTab(1)">
            <i class="ph ph-image"></i> Media
        </button>
        <button class="mobile-tab" data-tab="2" onclick="switchTab(2)">
            <i class="ph ph-chat-teardrop-text"></i> Comentarios
            <?php if($pendingCount > 0): ?>
            <span class="mobile-tab-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">
    <div class="grid-3">
        <!-- COPY -->
        <div class="col-card mobile-active" id="tab-panel-0">
            <div class="col-title"><i class="ph ph-text-align-left"></i> Copy del Post</div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 1rem 0 0.5rem; color: var(--text-main);"><?php echo !empty($post['concept']) ? htmlspecialchars($post['concept']) : 'Post ' . $postIndexStr; ?></h3>
            <div class="copy-text"><?php echo highlightCopy($post['copy_text']); ?></div>
            <?php if(!empty($post['platform'])): ?>
            <div class="platforms-section">
                <div class="section-label">Plataformas</div>
                <div class="platform-tags">
                    <?php 
                    $platforms = explode(',', $post['platform']);
                    foreach($platforms as $plat): 
                        $plat = trim($plat);
                        if(empty($plat)) continue;
                    ?>
                    <span class="platform-tag"><i class="ph ph-hash"></i> <?php echo htmlspecialchars($plat); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- MEDIA -->
        <div class="col-card" id="tab-panel-1">
            <div class="col-title"><i class="ph ph-image"></i> Multimedia</div>
            
            <?php if(!empty($post['reference_image_link'])): ?>
            <div class="media-switcher">
                <button class="media-switch-btn active" onclick="switchMediaTab('referencia')">Referencia Gráfica</button>
                <button class="media-switch-btn" onclick="switchMediaTab('terminado')">Arte Terminado</button>
            </div>
            <?php endif; ?>

            <div class="media-pane<?php echo !empty($post['reference_image_link']) ? ' active' : ''; ?>" id="media-referencia">
                <div class="media-block media-block-ref">
                    <?php echo renderMedia($post['reference_image_link'] ?? ''); ?>
                </div>
            </div>

            <div class="media-pane<?php echo empty($post['reference_image_link']) ? ' active' : ''; ?>" id="media-terminado">
                <div class="media-block">
                    <?php if(empty($post['reference_image_link'])): ?>
                    <div class="section-label">Arte Terminado</div>
                    <?php endif; ?>
                    <?php echo renderMedia($post['image_link'] ?? ''); ?>
                </div>
            </div>
        </div>

        <!-- COMMENTS -->
        <div class="col-card comments-col" id="tab-panel-2">
            <div class="comments-col-inner">
                <div class="col-title">
                    <i class="ph ph-chat-teardrop-text"></i> Comentarios del Cliente
                    <span class="comments-count"><?php echo count($comments); ?> comentario<?php echo count($comments) !== 1 ? 's' : ''; ?></span>
                </div>
                
                <div class="comments-list" id="comments-list">
                    <?php if(empty($comments)): ?>
                        <div class="comments-empty">
                            <i class="ph ph-chats"></i>
                            <p>Aún no hay comentarios en este post.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($comments as $ci => $c): ?>
                        <div class="comment-item" style="animation-delay: <?php echo $ci * 0.06; ?>s;">
                            <div class="comment-header">
                                <span class="comment-date"><i class="ph ph-clock"></i> <?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?></span>
                                <span class="comment-status status-<?php echo $c['status']; ?>"><?php echo $c['status']; ?></span>
                                <div class="comment-actions" style="margin-left: auto; display: flex; gap: 2px;">
                                    <button type="button" class="comment-action-btn edit" onclick="editComment(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes(str_replace(["\r", "\n"], ['', '\n'], $c['comment_text']))); ?>')" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                    <button type="button" class="comment-action-btn delete" onclick="deleteComment(<?php echo $c['id']; ?>)" title="Eliminar"><i class="ph ph-trash"></i></button>
                                </div>
                            </div>
                            <div class="comment-text" id="comment-text-<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['comment_text']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="comment-form">
                    <form id="public-comment-form">
                        <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                        <textarea name="comment" class="comment-textarea" placeholder="Escribe tu feedback o corrección aquí..." required></textarea>
                        <button type="submit" class="btn-submit" id="btn-submit-comment">
                            <i class="ph ph-paper-plane-right"></i> Enviar Comentario
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()"><i class="ph ph-x"></i></button>
    <img id="lightbox-img" src="" alt="">
</div>

<!-- TOAST -->
<div class="toast" id="toast">
    <span class="toast-icon"><i class="ph-fill ph-check-circle"></i></span>
    <span id="toast-text">¡Comentario enviado!</span>
</div>

<!-- CUSTOM MODAL -->
<div id="custom-modal-overlay" class="custom-modal-overlay">
    <div class="custom-modal">
        <h3 id="custom-modal-title">Título</h3>
        <p id="custom-modal-message">Mensaje</p>
        <div id="custom-modal-input-container" style="display:none; margin-top:1rem;">
            <textarea id="custom-modal-input" class="comment-textarea" style="min-height: 80px;"></textarea>
        </div>
        <div class="custom-modal-actions">
            <button class="btn-cancel" onclick="closeCustomModal()">Cancelar</button>
            <button id="custom-modal-confirm-btn" class="btn-confirm">Aceptar</button>
        </div>
    </div>
</div>

<script>
/* ===== MOBILE TABS ===== */
function switchTab(index) {
    document.querySelectorAll('.mobile-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.col-card').forEach(p => p.classList.remove('mobile-active'));
    document.querySelector(`.mobile-tab[data-tab="${index}"]`).classList.add('active');
    document.getElementById('tab-panel-' + index).classList.add('mobile-active');
}

function switchMediaTab(tabId) {
    document.querySelectorAll('.media-switch-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.media-pane').forEach(p => p.classList.remove('active'));
    
    event.currentTarget.classList.add('active');
    document.getElementById('media-' + tabId).classList.add('active');
}

/* ===== LIGHTBOX ===== */
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

/* ===== TOAST ===== */
function showToast(text) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-text').textContent = text;
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 3500);
}

/* ===== AUTO-SCROLL COMMENTS ===== */
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('comments-list');
    if (list) list.scrollTop = list.scrollHeight;
});

/* ===== COMMENT FORM ===== */
document.getElementById('public-comment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-comment');
    const fd = new FormData(this);
    
    const payload = new FormData();
    payload.append('post_id', fd.get('post_id'));
    payload.append('comment_text', fd.get('comment'));
    payload.append('comment_phase', 'Fase de Diseño'); 
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';
    
    fetch('ajax_save_public_comment.php', {
        method: 'POST',
        body: payload
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            showToast('¡Comentario enviado con éxito!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (res.error || 'No se pudo guardar.'));
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-paper-plane-right"></i> Enviar Comentario';
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-paper-plane-right"></i> Enviar Comentario';
    });
});

function deleteComment(id) {
    showCustomModal({
        title: 'Eliminar Comentario',
        message: '¿Estás seguro de eliminar este comentario? Esta acción no se puede deshacer.',
        type: 'confirm',
        onConfirm: () => {
            const fd = new FormData();
            fd.append('id', id);
            fetch('ajax_delete_public_comment.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else showToast('Error: ' + res.error);
            });
        }
    });
}

function editComment(id, currentText) {
    showCustomModal({
        title: 'Editar Comentario',
        type: 'input',
        inputValue: currentText,
        onConfirm: (newText) => {
            if (newText !== null && newText.trim() !== '' && newText !== currentText) {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('comment_text', newText);
                fetch('ajax_edit_public_comment.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.success) location.reload();
                    else showToast('Error: ' + res.error);
                });
            }
        }
    });
}

let modalConfirmCallback = null;

function showCustomModal(options) {
    document.getElementById('custom-modal-title').innerText = options.title || 'Atención';
    const msgEl = document.getElementById('custom-modal-message');
    if (options.message) {
        msgEl.style.display = 'block';
        msgEl.innerText = options.message;
    } else {
        msgEl.style.display = 'none';
    }
    
    const inputContainer = document.getElementById('custom-modal-input-container');
    const inputEl = document.getElementById('custom-modal-input');
    const confirmBtn = document.getElementById('custom-modal-confirm-btn');

    if (options.type === 'input') {
        inputContainer.style.display = 'block';
        inputEl.value = options.inputValue || '';
        confirmBtn.className = 'btn-confirm btn-edit-mode';
        confirmBtn.innerHTML = '<i class="ph ph-check"></i> Guardar';
    } else {
        inputContainer.style.display = 'none';
        confirmBtn.className = 'btn-confirm';
        confirmBtn.innerHTML = '<i class="ph ph-trash"></i> Eliminar';
    }

    modalConfirmCallback = options.onConfirm;
    
    const overlay = document.getElementById('custom-modal-overlay');
    overlay.style.display = 'flex';
    // trigger reflow
    void overlay.offsetWidth;
    overlay.classList.add('show');
}

function closeCustomModal() {
    const overlay = document.getElementById('custom-modal-overlay');
    overlay.classList.remove('show');
    setTimeout(() => { overlay.style.display = 'none'; }, 300);
}

document.getElementById('custom-modal-confirm-btn').addEventListener('click', () => {
    if (modalConfirmCallback) {
        const inputEl = document.getElementById('custom-modal-input');
        modalConfirmCallback(inputEl.value);
    }
    closeCustomModal();
});
</script>

</body>
</html>
