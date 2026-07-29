<?php
// modules/public/linktree.php
require_once 'config/database.php';

$db = (new Database())->getConnection();
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    echo "<h1>404 Not Found</h1>";
    exit();
}

$stmt = $db->prepare("SELECT * FROM linktrees WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$linktree = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$linktree) {
    echo "<h1>404 Not Found</h1><p>El perfil no existe o no está activo.</p>";
    exit();
}

// Update views
$stmtViews = $db->prepare("UPDATE linktrees SET views = views + 1 WHERE id = ?");
$stmtViews->execute([$linktree['id']]);

$stmtLinks = $db->prepare("SELECT * FROM linktree_links WHERE linktree_id = ? ORDER BY sort_order ASC");
$stmtLinks->execute([$linktree['id']]);
$links = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

$theme = $linktree['theme_config'] ? json_decode($linktree['theme_config'], true) : [];
$bgColor = $theme['bgColor'] ?? '#f4f4f5';
$textColor = $theme['textColor'] ?? '#18181b';
$btnColor = $theme['btnColor'] ?? '#ffffff';
$btnTextColor = $theme['btnTextColor'] ?? '#18181b';
$btnStyle = $theme['btnStyle'] ?? 'rounded-md'; // rounded-md, rounded-full, rounded-none
$fontFamily = $theme['fontFamily'] ?? 'Inter';
$hideWatermark = $theme['hideWatermark'] ?? false;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/";
$profileImage = $linktree['profile_image'] ? $base_url . $linktree['profile_image'] : $base_url . 'assets/images/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base_url) ?>">
    <title><?= htmlspecialchars($linktree['title']) ?></title>
    
    <!-- Open Graph SEO -->
    <meta property="og:title" content="<?= htmlspecialchars($linktree['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($linktree['bio']) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($profileImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($base_url . 'l/' . $linktree['slug']) ?>">
    <meta property="og:type" content="profile">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($profileImage) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fontFamily) ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: <?= $bgColor ?>;
            color: <?= $textColor ?>;
            font-family: '<?= htmlspecialchars($fontFamily) ?>', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
        }
        .bio-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .bio-header {
            text-align: center;
        }
        .bio-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid <?= $textColor ?>20;
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .bio-avatar:active {
            transform: scale(0.9);
        }
        .bio-avatar:hover {
            transform: scale(1.05) rotate(2deg);
        }
        .bio-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .bio-desc {
            font-size: 0.95rem;
            opacity: 0.9;
            white-space: pre-wrap;
        }
        .bio-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: <?= $btnColor ?>;
            color: <?= $btnTextColor ?>;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, opacity 0.2s;
            position: relative;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .bio-link:hover {
            transform: translateY(-2px);
            opacity: 0.95;
        }
        .bio-link.rounded-md { border-radius: 0.5rem; }
        .bio-link.rounded-full { border-radius: 9999px; }
        .bio-link.rounded-none { border-radius: 0; }
        
        .bio-link i {
            position: absolute;
            left: 1.25rem;
            font-size: 1.25rem;
        }

        /* Loading Premium */
        #loader {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: <?= $bgColor ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s;
        }
        .spinner {
            width: 40px; height: 40px;
            border: 4px solid <?= $textColor ?>20;
            border-top-color: <?= $textColor ?>;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .fade-in-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Premium Loader -->
    <div id="loader">
        <div class="spinner"></div>
    </div>
    <div class="bio-container">
        <header class="bio-header">
            <img src="<?= htmlspecialchars($profileImage) ?>" alt="<?= htmlspecialchars($linktree['title']) ?>" class="bio-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($linktree['title']) ?>&background=random'">
            <h1 class="bio-title">@<?= htmlspecialchars($linktree['slug']) ?></h1>
            <?php if($linktree['title'] && $linktree['title'] !== $linktree['slug']): ?>
                <h2 class="text-lg font-semibold mb-2 opacity-80"><?= htmlspecialchars($linktree['title']) ?></h2>
            <?php endif; ?>
            <p class="bio-desc"><?= htmlspecialchars($linktree['bio']) ?></p>
        </header>

        <main class="flex flex-col gap-4">
            <?php 
            // Helper functions for parsing URLs
            function getYouTubeId($url) {
                if (preg_match('/(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
                    return $matches[2];
                }
                return strlen($url) === 11 ? $url : $url;
            }
            
            function getSpotifyEmbedUrl($url) {
                if (preg_match('/spotify\.com\/(?:intl-[a-z]+\/)?(track|album|playlist|episode|show)\/([a-zA-Z0-9]+)/', $url, $matches)) {
                    return "https://open.spotify.com/embed/" . $matches[1] . "/" . $matches[2];
                }
                if (strpos($url, 'spotify.com/embed/') !== false) return $url;
                return str_replace('spotify.com/', 'spotify.com/embed/', $url); // basic fallback
            }

            $delay = 0.2;
            $now = new DateTime();
            
            foreach ($links as $link): 
                $type = $link['type'] ?? 'link';
                $meta = isset($link['meta_data']) && $link['meta_data'] ? json_decode($link['meta_data'], true) : [];
                
                // Programmable links logic
                if (!empty($meta['start_date']) && new DateTime($meta['start_date']) > $now) continue;
                if (!empty($meta['end_date']) && new DateTime($meta['end_date']) < $now) continue;
                
                $animStyle = "animation-delay: {$delay}s;";
                
                if ($type === 'link' || !$type):
            ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="bio-link <?= htmlspecialchars($btnStyle) ?> fade-in-up" style="<?= $animStyle ?>">
                    <?php if($link['icon']): ?>
                        <i class="ph <?= htmlspecialchars($link['icon']) ?>"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($link['title']) ?></span>
                </a>
            <?php elseif ($type === 'youtube'): 
                $rawVideoId = $meta['videoId'] ?? '';
                $videoId = htmlspecialchars(getYouTubeId($rawVideoId));
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1rem' : ($btnStyle === 'rounded-none' ? '0' : '0.5rem') ?>; overflow:hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.5rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">
                        <iframe src="https://www.youtube.com/embed/<?= $videoId ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                </div>
            <?php elseif ($type === 'spotify'): 
                $spotifyUrl = htmlspecialchars(getSpotifyEmbedUrl($meta['spotifyUrl'] ?? ''));
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1rem' : ($btnStyle === 'rounded-none' ? '0' : '0.5rem') ?>; overflow:hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.5rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <iframe src="<?= $spotifyUrl ?>" width="100%" height="80" frameborder="0" allowtransparency="true" allow="encrypted-media"></iframe>
                </div>
            <?php elseif ($type === 'text'): ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; margin-top:1rem; margin-bottom:0.5rem; text-align:center;">
                    <?php if($link['title']): ?>
                        <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:0.25rem;"><?= htmlspecialchars($link['title']) ?></h3>
                    <?php endif; ?>
                    <?php if(!empty($meta['text'])): ?>
                        <p style="font-size:0.9rem; opacity:0.9; white-space:pre-wrap;"><?= htmlspecialchars($meta['text']) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($type === 'faq'): ?>
                <details class="fade-in-up" style="<?= $animStyle ?> width:100%; background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; border-radius: <?= $btnStyle === 'rounded-full' ? '1rem' : ($btnStyle === 'rounded-none' ? '0' : '0.5rem') ?>; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding:1rem; cursor:pointer; text-align:left;">
                    <summary style="font-weight:600; font-size:0.95rem; outline:none;"><?= htmlspecialchars($link['title']) ?></summary>
                    <div style="margin-top:0.75rem; font-size:0.85rem; opacity:0.9; white-space:pre-wrap;"><?= htmlspecialchars($meta['answer'] ?? '') ?></div>
                </details>
            <?php elseif ($type === 'whatsapp'): 
                $phone = preg_replace('/[^0-9]/', '', $meta['phone'] ?? '');
                $message = urlencode($meta['message'] ?? '');
                $waUrl = "https://wa.me/{$phone}?text={$message}";
            ?>
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="bio-link fade-in-up" style="<?= $animStyle ?> background:#25D366; color:#ffffff; border-radius: <?= $btnStyle === 'rounded-full' ? '9999px' : ($btnStyle === 'rounded-none' ? '0' : '0.5rem') ?>;">
                    <i class="ph ph-whatsapp-logo"></i>
                    <span><?= htmlspecialchars($link['title'] ?: 'WhatsApp') ?></span>
                </a>
            <?php elseif ($type === 'map'): 
                $address = urlencode($meta['address'] ?? '');
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1rem' : ($btnStyle === 'rounded-none' ? '0' : '0.5rem') ?>; overflow:hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.5rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <iframe width="100%" height="250" frameborder="0" style="border:0" src="https://maps.google.com/maps?q=<?= $address ?>&t=&z=15&ie=UTF8&iwloc=&output=embed" allowfullscreen></iframe>
                </div>
            <?php 
                endif;
            $delay += 0.1;
            endforeach; 
            ?>
        </main>
        
        <?php if(!$hideWatermark): ?>
        <footer class="mt-8 text-center text-sm opacity-50 font-medium fade-in-up" style="animation-delay: <?= $delay ?>s">
            Creado con Roma
        </footer>
        <?php endif; ?>
    </div>
    
    <script>
        window.addEventListener('load', () => {
            const loader = document.getElementById('loader');
            loader.style.opacity = '0';
            setTimeout(() => loader.style.visibility = 'hidden', 500);
        });
    </script>
</body>
</html>
