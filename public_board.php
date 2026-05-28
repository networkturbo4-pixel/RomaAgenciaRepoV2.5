<?php
// public_board.php
require_once 'config/database.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Enlace no válido.");
}

$db = (new Database())->getConnection();

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
            body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .auth-card { background: white; padding: 3rem 2rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 90%; }
            h1 { font-size: 1.5rem; color: #1e293b; margin-bottom: 0.5rem; }
            p { color: #64748b; font-size: 1.1rem; margin-bottom: 2rem; }
            input[type="text"] { width: 100%; padding: 1rem; font-size: 2rem; text-align: center; letter-spacing: 5px; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 1.5rem; box-sizing: border-box; }
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
    </body>
    </html>
    <?php
    exit();
}

// Fetch Posts
$stmtPosts = $db->prepare("SELECT * FROM month_posts WHERE month_id = ? ORDER BY post_date ASC");
$stmtPosts->execute([$id]);
$posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

function renderPreviewBox($urlStr, $isRef = true) {
    if (!$urlStr) {
        return '<div style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; height:250px; border-radius:12px; color:#cbd5e1;"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
    }

    $mediaList = json_decode($urlStr, true);
    // Fix: plain URL (not JSON) must be treated as single-item array
    if (!is_array($mediaList) || count($mediaList) === 0) {
        $mediaList = !empty($urlStr) ? [$urlStr] : [];
    }
    if (empty($mediaList)) {
        return '<div style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; height:250px; border-radius:12px; color:#cbd5e1;"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
    }

    if (count($mediaList) > 1) {
        $html = '<div class="swiper" style="width: 100%; height: 400px; border-radius: 12px; overflow: hidden; position: relative;">
                    <div class="swiper-wrapper">';
        foreach($mediaList as $mItem) {
            $html .= '<div class="swiper-slide" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                        <img src="'.htmlspecialchars($mItem).'" style="width: 100%; height: 100%; object-fit: contain;">
                      </div>';
        }
        $html .= '</div>
                  <div class="swiper-button-next" style="color: var(--primary); transform: scale(0.6); text-shadow: 0 1px 3px rgba(0,0,0,0.2);"></div>
                  <div class="swiper-button-prev" style="color: var(--primary); transform: scale(0.6); text-shadow: 0 1px 3px rgba(0,0,0,0.2);"></div>
                  <div class="swiper-pagination"></div>
                </div>';
        return $html;
    }

    $url = $mediaList[0];
    $isDriveImage = preg_match('/drive\.google\.com\/(uc\?export=view&id=|thumbnail\?id=)([\w-]+)/i', $url);
    $isVideoLink = !$isDriveImage && preg_match('/(youtu\.be|youtube\.com|tiktok\.com|\.mp4|drive\.google\.com|instagram\.com|facebook\.com|fb\.watch)/i', $url);

    if ($isVideoLink) {
        // YouTube
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $ytMatch)) {
            return '<iframe width="100%" height="300" src="https://www.youtube.com/embed/'.$ytMatch[1].'?autoplay=0" frameborder="0" allowfullscreen style="border-radius:12px;"></iframe>';
        }
        // MP4
        elseif (preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url)) {
            return '<video controls style="width: 100%; height: 300px; object-fit: contain; border-radius: 12px; background: #000;"><source src="'.htmlspecialchars($url).'" type="video/mp4"></video>';
        }
        // TikTok with video ID
        elseif (preg_match('/tiktok\.com\/@[\w.]+\/video\/(\d+)/', $url, $tkMatch)) {
            return '<iframe width="100%" height="400" src="https://www.tiktok.com/embed/v2/'.$tkMatch[1].'" frameborder="0" allowfullscreen style="border-radius:12px;"></iframe>';
        }
        // TikTok (any other tiktok URL)
        elseif (preg_match('/tiktok\.com/i', $url)) {
            return '<iframe width="100%" height="400" src="'.htmlspecialchars($url).'" frameborder="0" allowfullscreen style="border-radius:12px;"></iframe>';
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
            return '<iframe width="100%" height="300" src="https://drive.google.com/file/d/'.$drMatch[1].'/preview" frameborder="0" allowfullscreen style="border-radius:12px;"></iframe>';
        }
        // Fallback external link
        else {
            return '<a href="'.htmlspecialchars($url).'" target="_blank" style="display:block; padding:2rem; background:#eff6ff; color:#3b82f6; text-align:center; border-radius:12px; text-decoration:none; font-weight:700;">Ver Video <br><span style="font-size:0.8rem; font-weight:normal;">Enlace Externo</span></a>';
        }
    } else {
        return '<img src="'.htmlspecialchars($url).'" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain; background: #f8fafc; border: 1px solid #e2e8f0;">';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablero de Aprobación | <?php echo $title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --success-hover: #059669;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        
        .header { text-align: center; margin-bottom: 3rem; }
        .header h1 { font-size: 2.5rem; margin: 0; color: var(--text-main); letter-spacing: -0.05em; }
        .header p { font-size: 1.25rem; color: var(--text-muted); margin-top: 0.5rem; }

        /* Modern Toggle Switcher */
        .toggle-container { display: flex; justify-content: center; margin-bottom: 3rem; position: sticky; top: 1rem; z-index: 100; }
        .toggle-wrapper { background: white; padding: 0.5rem; border-radius: 100px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); display: inline-flex; position: relative; border: 1px solid #e2e8f0; }
        .toggle-btn { padding: 1rem 2rem; font-size: 1.1rem; font-weight: 700; border-radius: 100px; cursor: pointer; transition: all 0.3s ease; position: relative; z-index: 2; border: none; background: transparent; color: var(--text-muted); }
        .toggle-btn.active { color: white; }
        .toggle-highlight { position: absolute; top: 0.5rem; bottom: 0.5rem; left: 0.5rem; background: var(--primary); border-radius: 100px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1; }

        /* Views */
        .view-content { display: none; animation: fadeIn 0.4s ease; }
        .view-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Cards */
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 2rem; }
        @media (max-width: 768px) { .grid-cards { grid-template-columns: 1fr; } }
        
        .card { background: var(--surface); border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .post-date { font-weight: 800; font-size: 1.25rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .platform-tag { padding: 0.4rem 0.8rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; font-size: 0.9rem; color: var(--text-main); }
        
        .card-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: 1.5rem; }
        
        .concept-title { font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.3; }
        
        .media-box { background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 0.5rem; }
        
        .text-content { background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px dashed #cbd5e1; font-size: 0.95rem; line-height: 1.6; color: var(--text-main); flex: 1; }
        
        /* Action Buttons */
        .card-actions { padding: 1.5rem; border-top: 1px solid #e2e8f0; display: flex; gap: 1rem; background: #f8fafc; }
        .btn { flex: 1; padding: 1.25rem; font-size: 0.95rem; font-weight: 800; border-radius: 12px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: transform 0.2s, background 0.2s; color: white; }
        .btn:hover { transform: translateY(-2px); }
        .btn-approve { background: var(--success); }
        .btn-approve:hover { background: var(--success-hover); }
        .btn-reject { background: var(--danger); }
        .btn-reject:hover { background: var(--danger-hover); }
        
        .status-badge { width: 100%; text-align: center; padding: 1rem; font-size: 0.95rem; font-weight: 800; border-radius: 12px; }
        .status-approved { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 2px solid var(--success); }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 2px solid var(--danger); }
        .status-review { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 2px solid #f59e0b; }

        .empty-state { text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; border: 2px dashed #cbd5e1; color: var(--text-muted); }

        /* Modal for comments */
        .comment-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: 0.3s; padding: 1rem; }
        .comment-modal-overlay.active { opacity: 1; visibility: visible; }
        .comment-modal-content { background: white; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transform: translateY(20px); transition: 0.3s; }
        .comment-modal-overlay.active .comment-modal-content { transform: translateY(0); }
        .comment-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .comment-modal-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; }
        .comment-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .comment-form-group { margin-bottom: 1.5rem; }
        .comment-form-group label { display: block; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem; }
        .comment-textarea { width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 0.95rem; resize: vertical; box-sizing: border-box; }
        .comment-file { display: block; width: 100%; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <p>Tablero de Revisión</p>
        <h1><?php echo htmlspecialchars($monthData['brand_name']); ?> - <?php echo $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?></h1>
    </div>

    <!-- Toggle Switcher -->
    <div class="toggle-container">
        <div class="toggle-wrapper" id="view-toggle">
            <div class="toggle-highlight" id="toggle-bg"></div>
            <button class="toggle-btn active" onclick="switchView('grilla', this)"><i class="ph ph-squares-four"></i> Fase de Diseño</button>
            <button class="toggle-btn" onclick="switchView('parrilla', this)"><i class="ph ph-calendar-check"></i> Parrilla Final</button>
        </div>
    </div>

    <!-- VISTA: GRILLA DE DISEÑO (Fase 1) -->
    <div id="view-grilla" class="view-content active">
        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <i class="ph ph-empty" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h2>No hay publicaciones planificadas</h2>
                <p>La agencia aún no ha subido contenido a este tablero.</p>
            </div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($posts as $p): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="post-date"><i class="ph ph-calendar-blank"></i> <?php echo date('d M', strtotime($p['post_date'])); ?></div>
                        <div class="platform-tag"><?php echo htmlspecialchars($p['platform'] ?: 'General'); ?></div>
                    </div>
                    <div class="card-body">
                        <h2 class="concept-title"><?php echo htmlspecialchars($p['concept']); ?></h2>
                        
                        <div>
                            <strong style="color:var(--text-muted); font-size:0.9rem; text-transform:uppercase; margin-bottom:0.5rem; display:block;">Referencia Visual</strong>
                            <div class="media-box">
                                <?php echo renderPreviewBox($p['reference_image_link'], true); ?>
                            </div>
                        </div>

                        <div style="flex: 1; display: flex; flex-direction: column;">
                            <strong style="color:var(--text-muted); font-size:0.9rem; text-transform:uppercase; margin-bottom:0.5rem; display:block;">Brief / Instrucciones</strong>
                            <div class="text-content">
                                <?php echo empty($p['design_brief']) ? '<em>Sin instrucciones detalladas.</em>' : $p['design_brief']; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Botones de Acción (Fase de Diseño) -->
                    <div class="card-actions" id="actions-grilla-<?php echo $p['id']; ?>">
                        <?php if ($p['status'] === 'Aprobado' || $p['status'] === 'Publicado'): ?>
                            <div class="status-badge status-approved"><i class="ph ph-check-circle"></i> Aprobado por el Cliente</div>
                        <?php elseif (strpos(strtolower($p['status']), 'rechazado') !== false || strpos(strtolower($p['status']), 'corrección') !== false || strpos(strtolower($p['status']), 'revisión') !== false): ?>
                            <div class="status-badge status-rejected"><i class="ph ph-x-circle"></i> Corrección Solicitada</div>
                        <?php else: ?>
                            <button class="btn btn-reject" onclick="openCommentModal(<?php echo $p['id']; ?>, 'Fase de Diseño')"><i class="ph ph-chat-circle-text"></i> Solicitar Cambios</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- VISTA: PARRILLA DE CONTENIDOS (Fase 2) -->
    <div id="view-parrilla" class="view-content">
        <div class="grid-cards">
            <?php foreach ($posts as $p): ?>
            <div class="card">
                <div class="card-header">
                    <div class="post-date"><i class="ph ph-calendar-blank"></i> <?php echo date('d M', strtotime($p['post_date'])); ?></div>
                    <div class="platform-tag"><?php echo htmlspecialchars($p['platform'] ?: 'General'); ?></div>
                </div>
                <div class="card-body" style="padding-bottom: 0;">
                    <h2 class="concept-title"><?php echo htmlspecialchars($p['concept']); ?></h2>
                    
                    <div>
                        <strong style="color:var(--text-muted); font-size:0.9rem; text-transform:uppercase; margin-bottom:0.5rem; display:block;">Arte / Video Final</strong>
                        <div class="media-box">
                            <?php echo renderPreviewBox($p['image_link'], false); ?>
                        </div>
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column; margin-bottom: 1.5rem;">
                        <strong style="color:var(--text-muted); font-size:0.9rem; text-transform:uppercase; margin-bottom:0.5rem; display:block;">Copy (Texto de la publicación)</strong>
                        <div class="text-content" style="background: white;">
                            <?php echo empty($p['copy_text']) ? '<em>Sin texto redactado aún.</em>' : $p['copy_text']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de Acción (Aprobar/Rechazar) -->
                <div class="card-actions" id="actions-<?php echo $p['id']; ?>">
                    <?php if ($p['status'] === 'Aprobado' || $p['status'] === 'Publicado'): ?>
                        <div class="status-badge status-approved"><i class="ph ph-check-circle"></i> Aprobado por el Cliente</div>
                    <?php elseif (strpos(strtolower($p['status']), 'rechazado') !== false || strpos(strtolower($p['status']), 'corrección') !== false || strpos(strtolower($p['status']), 'revisión') !== false): ?>
                        <div class="status-badge status-rejected"><i class="ph ph-x-circle"></i> Corrección Solicitada</div>
                    <?php else: ?>
                        <button class="btn btn-reject" onclick="openCommentModal(<?php echo $p['id']; ?>, 'Parrilla Final')"><i class="ph ph-chat-circle-text"></i> Dejar Comentario</button>
                        <button class="btn btn-approve" onclick="updatePostStatus(<?php echo $p['id']; ?>, 'Aprobado')"><i class="ph ph-check-circle"></i> Aprobar Post</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Dejar Comentario -->
<div class="comment-modal-overlay" id="comment-modal">
    <div class="comment-modal-content">
        <div class="comment-modal-header">
            <h3 class="comment-modal-title">Dejar un Comentario</h3>
            <button class="comment-close" onclick="closeCommentModal()">&times;</button>
        </div>
        <form id="comment-form" onsubmit="submitComment(event)">
            <input type="hidden" name="post_id" id="comment_post_id">
            <input type="hidden" name="month_id" value="<?php echo $id; ?>">
            <input type="hidden" name="comment_phase" id="comment_phase" value="Parrilla Final">
            <div class="comment-form-group">
                <label>Comentario / Instrucciones de corrección *</label>
                <textarea name="comment_text" class="comment-textarea" rows="4" required placeholder="Escribe aquí tu comentario..."></textarea>
            </div>
            <div class="comment-form-group">
                <label>Adjuntar Foto de Referencia (Opcional)</label>
                <input type="file" name="image_file" class="comment-file" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; font-size:1rem; padding:1rem; background:var(--primary); color:white; border:none; border-radius:12px; font-weight:700; cursor:pointer;" id="btn-submit-comment">Enviar Comentario</button>
        </form>
    </div>
</div>

<script>
function switchView(viewId, btnElement) {
    // Esconder todas
    document.querySelectorAll('.view-content').forEach(el => el.classList.remove('active'));
    // Quitar active de botones
    document.querySelectorAll('.toggle-btn').forEach(el => el.classList.remove('active'));
    
    // Mostrar elegida
    document.getElementById('view-' + viewId).classList.add('active');
    btnElement.classList.add('active');
    
    // Animar el fondo (Highlight)
    const highlight = document.getElementById('toggle-bg');
    if (viewId === 'parrilla') {
        highlight.style.left = '50%';
        highlight.style.width = 'calc(50% - 0.5rem)';
    } else {
        highlight.style.left = '0.5rem';
        highlight.style.width = 'calc(50% - 0.5rem)';
    }
}

// Inicializar el ancho del highlight y Swiper
window.addEventListener('DOMContentLoaded', () => {
    const highlight = document.getElementById('toggle-bg');
    if (highlight) highlight.style.width = 'calc(50% - 0.5rem)';

    // Initialize Swipers
    document.querySelectorAll('.swiper').forEach(function(el) {
        new Swiper(el, {
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    });
});

function openCommentModal(postId, phase = 'Parrilla Final') {
    document.getElementById('comment_post_id').value = postId;
    document.getElementById('comment_phase').value = phase;
    document.getElementById('comment-form').reset();
    document.getElementById('comment-modal').classList.add('active');
}

function closeCommentModal() {
    document.getElementById('comment-modal').classList.remove('active');
}

async function submitComment(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-comment');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Enviando...';
    btn.disabled = true;

    const formData = new FormData(document.getElementById('comment-form'));

    try {
        const response = await fetch('ajax_save_public_comment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            closeCommentModal();
            // Actualizar el estado visual del post en ambas vistas si es necesario
            const postId = document.getElementById('comment_post_id').value;
            const containerParrilla = document.getElementById('actions-' + postId);
            if (containerParrilla) containerParrilla.innerHTML = '<div class="status-badge status-rejected"><i class="ph ph-x-circle"></i> Corrección Solicitada</div>';
            const containerGrilla = document.getElementById('actions-grilla-' + postId);
            if (containerGrilla) containerGrilla.innerHTML = '<div class="status-badge status-rejected"><i class="ph ph-x-circle"></i> Corrección Solicitada</div>';
            
            alert('Comentario enviado correctamente. La agencia ha sido notificada.');
        } else {
            alert('Error al enviar el comentario: ' + (result.error || 'Desconocido'));
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
    }

    btn.innerHTML = originalText;
    btn.disabled = false;
}

async function updatePostStatus(postId, newStatus) {
    if (newStatus.includes('Revisión')) {
        if (!confirm('¿Deseas solicitar cambios en esta publicación? La agencia será notificada.')) return;
    } else {
        if (!confirm('¿Confirmas que apruebas esta publicación para salir al aire?')) return;
    }

    const formData = new FormData();
    formData.append('id', postId);
    formData.append('status', newStatus);

    try {
        const response = await fetch('modules/month_board/ajax_update_post_status.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            const container = document.getElementById('actions-' + postId);
            if (newStatus === 'Aprobado') {
                container.innerHTML = '<div class="status-badge status-approved"><i class="ph ph-check-circle"></i> Aprobado por el Cliente</div>';
            } else {
                container.innerHTML = '<div class="status-badge status-rejected"><i class="ph ph-x-circle"></i> Corrección Solicitada</div>';
            }
        } else {
            alert('Error al actualizar el estado: ' + result.error);
        }
    } catch (e) {
        console.error(e);
        alert('Error de conexión.');
    }
}
</script>

</body>
</html>
