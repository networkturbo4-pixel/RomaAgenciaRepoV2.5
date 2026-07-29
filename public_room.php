<?php
// public_room.php
// Página pública de sala de reunión — accesible sin login
require_once 'config/database.php';

$db = (new Database())->getConnection();

// Fetch Global Settings
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$global_settings = [];
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    die("Enlace no válido.");
}

// Fetch room
$stmt = $db->prepare("SELECT * FROM meeting_rooms WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    die("Sala no encontrada.");
}

// Fetch recordings
$stmtRec = $db->prepare("SELECT mrr.*, u.name as recorder_name FROM meeting_room_recordings mrr LEFT JOIN users u ON mrr.recorded_by = u.id WHERE mrr.room_id = ? ORDER BY mrr.recorded_at DESC LIMIT 20");
$stmtRec->execute([$room['id']]);
$recordings = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

$site_name = $global_settings['site_name'] ?? 'Roma Agencia';
$logo = $global_settings['logo_light'] ?? '';
$logo_dark = $global_settings['logo_dark'] ?? '';
$primary_color = $global_settings['primary_color'] ?? '#4f46e5';
$room_color = $room['color'] ?? $primary_color;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($room['name']); ?> | <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="Sala de reunión: <?php echo htmlspecialchars($room['name']); ?>. Únete ahora.">
    <meta name="theme-color" content="<?php echo $room_color; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --room-color: <?php echo $room_color; ?>;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #070709;
                --surface: #141417;
                --text: #ffffff;
                --muted: #9ca3af;
                --border: #27272a;
            }
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scale-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes live-dot { 0%,100% { transform: scale(1); } 50% { transform: scale(1.4); } }
        @keyframes float-icon { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes gradient-shift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        /* Header */
        .pr-header {
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }
        .pr-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .pr-logo img { max-height: 28px; object-fit: contain; }
        .pr-logo span { font-weight: 800; font-size: 1.1rem; color: var(--room-color); }

        @media (prefers-color-scheme: dark) {
            .pr-logo .logo-light { display: none; }
            .pr-logo .logo-dark { display: block; }
        }
        @media (prefers-color-scheme: light) {
            .pr-logo .logo-dark { display: none; }
            .pr-logo .logo-light { display: block; }
        }

        /* Hero */
        .pr-hero {
            text-align: center;
            padding: 4rem 2rem 3rem;
            animation: fadeInUp 0.5s ease-out;
            position: relative;
        }
        .pr-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 200px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--room-color) 8%, transparent), transparent 70%);
            pointer-events: none;
        }
        .pr-room-icon {
            width: 80px;
            height: 80px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            background: var(--room-color);
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 25px color-mix(in srgb, var(--room-color) 35%, transparent);
            position: relative;
            animation: float-icon 4s ease-in-out infinite;
        }
        .pr-hero h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
        }
        .pr-hero p {
            color: var(--muted);
            font-size: 1rem;
            max-width: 450px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }

        /* Live indicator */
        .pr-live-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 500;
        }
        .pr-live-status.active {
            border-color: color-mix(in srgb, #ef4444 30%, var(--border));
            color: #ef4444;
            font-weight: 600;
        }
        .pr-live-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--border);
        }
        .pr-live-status.active .pr-live-dot {
            background: #ef4444;
            animation: live-dot 1s ease-in-out infinite;
        }

        /* Join button */
        .pr-join-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 1rem 2.5rem;
            background: var(--room-color);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px color-mix(in srgb, var(--room-color) 40%, transparent);
        }
        .pr-join-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 30px color-mix(in srgb, var(--room-color) 50%, transparent);
        }
        .pr-join-btn:active { transform: scale(0.98); }
        .pr-join-btn i { font-size: 1.3rem; }

        /* Presence */
        .pr-presence {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .pr-presence-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 2px solid var(--bg);
            background: var(--room-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: -8px;
            overflow: hidden;
        }
        .pr-presence-avatar:first-child { margin-left: 0; }
        .pr-presence-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .pr-presence-text {
            font-size: 0.85rem;
            color: var(--muted);
            margin-left: 0.5rem;
        }

        /* Recordings Section */
        .pr-recordings {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem 3rem;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }
        .pr-recordings h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 1.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
        }
        .pr-recordings-count {
            background: color-mix(in srgb, var(--room-color) 12%, transparent);
            color: var(--room-color);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
        }

        .pr-rec-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }
        .pr-rec-item:hover {
            transform: translateX(3px);
            border-color: color-mix(in srgb, var(--room-color) 25%, var(--border));
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .pr-rec-thumb {
            width: 140px; height: 80px;
            border-radius: 10px;
            overflow: hidden;
            background: #0f172a;
            flex-shrink: 0;
            position: relative;
            cursor: pointer;
        }
        .pr-rec-thumb iframe { width: 100%; height: 100%; border: 0; pointer-events: none; }
        .pr-rec-thumb-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .pr-rec-item:hover .pr-rec-thumb-overlay { background: rgba(0,0,0,0.15); }
        .pr-rec-play {
            width: 36px; height: 36px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .pr-rec-info { flex: 1; min-width: 0; }
        .pr-rec-title { font-weight: 600; font-size: 0.95rem; margin: 0 0 0.2rem 0; }
        .pr-rec-meta { font-size: 0.8rem; color: var(--muted); display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .pr-rec-meta span { display: flex; align-items: center; gap: 0.25rem; }

        .pr-rec-empty {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }
        .pr-rec-empty i { font-size: 3rem; color: var(--border); margin-bottom: 0.5rem; }

        /* Footer */
        .pr-footer {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
        }

        /* Video Theater */
        .pr-theater {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            backdrop-filter: blur(8px);
        }
        .pr-theater.active { display: flex; animation: scale-in 0.3s ease-out; }
        .pr-theater-header {
            width: 90%; max-width: 960px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
        }
        .pr-theater-header h3 { color: #fff; margin: 0; font-size: 1rem; }
        .pr-theater-close {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            width: 38px; height: 38px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .pr-theater-close:hover { background: rgba(255,255,255,0.2); }
        .pr-theater-body {
            width: 90%; max-width: 960px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
        }
        .pr-theater-body iframe { width: 100%; height: 100%; border: 0; }

        /* Responsive */
        @media (max-width: 640px) {
            .pr-hero { padding: 3rem 1.5rem 2rem; }
            .pr-hero h1 { font-size: 1.5rem; }
            .pr-room-icon { width: 64px; height: 64px; font-size: 1.5rem; border-radius: 18px; }
            .pr-join-btn { width: 100%; justify-content: center; padding: 0.9rem; font-size: 1rem; }
            .pr-recordings { padding: 0 1rem 2rem; }
            .pr-rec-item { flex-direction: column; align-items: stretch; }
            .pr-rec-thumb { width: 100%; height: 160px; }
            .pr-header { padding: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="pr-header">
        <div class="pr-logo">
            <?php if($logo && $logo_dark): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" class="logo-light" alt="Logo">
                <img src="<?php echo htmlspecialchars($logo_dark); ?>" class="logo-dark" alt="Logo">
            <?php elseif($logo): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
            <?php else: ?>
                <span><?php echo htmlspecialchars($site_name); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero -->
    <div class="pr-hero">
        <div class="pr-room-icon" style="background: <?php echo htmlspecialchars($room_color); ?>;">
            <i class="ph ph-<?php echo htmlspecialchars($room['icon']); ?>"></i>
        </div>

        <div class="pr-live-status" id="pr-live-status">
            <span class="pr-live-dot"></span>
            <span id="pr-status-text">Verificando estado...</span>
        </div>

        <h1><?php echo htmlspecialchars($room['name']); ?></h1>
        <?php if($room['description']): ?>
            <p><?php echo htmlspecialchars($room['description']); ?></p>
        <?php else: ?>
            <p>Sala de reunión de <?php echo htmlspecialchars($site_name); ?>. Haz clic para unirte.</p>
        <?php endif; ?>

        <?php if($room['meet_link']): ?>
            <a href="<?php echo htmlspecialchars($room['meet_link']); ?>" target="_blank" class="pr-join-btn" style="background: <?php echo htmlspecialchars($room_color); ?>;">
                <i class="ph ph-video-camera"></i> Unirse a la Reunión
            </a>
        <?php endif; ?>

        <!-- Presence avatars -->
        <div class="pr-presence" id="pr-presence" style="display: none;"></div>
    </div>

    <!-- Recordings -->
    <?php if(!empty($recordings)): ?>
    <div class="pr-recordings">
        <h2>
            <i class="ph ph-play-circle" style="color: <?php echo htmlspecialchars($room_color); ?>;"></i> 
            Grabaciones
            <span class="pr-recordings-count" style="background: color-mix(in srgb, <?php echo htmlspecialchars($room_color); ?> 12%, transparent); color: <?php echo htmlspecialchars($room_color); ?>;"><?php echo count($recordings); ?></span>
        </h2>

        <?php foreach($recordings as $rec): 
            $embed = str_replace('/view', '/preview', $rec['recording_link']);
        ?>
        <div class="pr-rec-item">
            <div class="pr-rec-thumb" onclick="openTheater('<?php echo htmlspecialchars($embed); ?>', '<?php echo addslashes($rec['title'] ?? $room['name']); ?>')">
                <iframe src="<?php echo htmlspecialchars($embed); ?>" loading="lazy"></iframe>
                <div class="pr-rec-thumb-overlay">
                    <div class="pr-rec-play"><i class="ph-fill ph-play"></i></div>
                </div>
            </div>
            <div class="pr-rec-info">
                <h4 class="pr-rec-title"><?php echo htmlspecialchars($rec['title'] ?: 'Grabación #' . $rec['id']); ?></h4>
                <div class="pr-rec-meta">
                    <span><i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y', strtotime($rec['recorded_at'])); ?></span>
                    <?php if($rec['duration']): ?>
                        <span><i class="ph ph-timer"></i> <?php echo htmlspecialchars($rec['duration']); ?></span>
                    <?php endif; ?>
                    <?php if($rec['recorder_name']): ?>
                        <span><i class="ph ph-user"></i> <?php echo htmlspecialchars($rec['recorder_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="pr-footer">
        <?php echo htmlspecialchars($site_name); ?> · Sala de Reunión
    </div>

    <!-- Video Theater -->
    <div class="pr-theater" id="pr-theater">
        <div class="pr-theater-header">
            <h3 id="pr-theater-title"></h3>
            <button class="pr-theater-close" onclick="closeTheater()"><i class="ph ph-x"></i></button>
        </div>
        <div class="pr-theater-body">
            <iframe id="pr-theater-iframe" src="" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>

    <!-- Pusher for public presence (read-only, no auth needed for public channels) -->
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script>
        // Use a regular (non-presence) channel to display who's in the room
        // The presence data is polled from the room status since public users can't subscribe to presence channels
        document.addEventListener('DOMContentLoaded', () => {
            const statusEl = document.getElementById('pr-live-status');
            const statusText = document.getElementById('pr-status-text');

            // Simple poll to check if room has active members (via a lightweight AJAX endpoint)
            async function checkRoomStatus() {
                try {
                    const res = await fetch(`ajax/ajax_rooms.php?action=get_room_by_slug&slug=<?php echo urlencode($room['slug']); ?>`);
                    const data = await res.json();
                    if (data.success) {
                        statusText.textContent = 'Sala disponible · Haz clic para unirte';
                        statusEl.classList.remove('active');
                    }
                } catch(e) {
                    statusText.textContent = 'Sala disponible';
                }
            }
            checkRoomStatus();
        });

        function openTheater(embedUrl, title) {
            document.getElementById('pr-theater-iframe').src = embedUrl;
            document.getElementById('pr-theater-title').textContent = title;
            document.getElementById('pr-theater').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeTheater() {
            document.getElementById('pr-theater').classList.remove('active');
            document.getElementById('pr-theater-iframe').src = '';
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeTheater(); });
    </script>
</body>
</html>
