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
$bgColor = $theme['bgColor'] ?? '#ffffff';
$textColor = $theme['textColor'] ?? '#18181b';
$btnColor = $theme['btnColor'] ?? '#f4f4f5';
$btnTextColor = $theme['btnTextColor'] ?? '#18181b';
$btnStyle = $theme['btnStyle'] ?? 'rounded-md'; // rounded-md, rounded-full, rounded-none
$fontFamily = $theme['fontFamily'] ?? 'Inter';
$hideWatermark = $theme['hideWatermark'] ?? false;
$verifiedBadge = $theme['verifiedBadge'] ?? false;
$socialBar = $theme['socialBar'] ?? [];

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/";
$defaultAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($linktree['title'] ?: 'Bio') . '&background=6366f1&color=fff&bold=true&size=200';
$profileImage = $linktree['profile_image'] ? $base_url . $linktree['profile_image'] : $defaultAvatar;

// Font Awesome 6 Brand Registry
function getBrandInfo($brand) {
    $brands = [
        'instagram' => ['name' => 'Instagram', 'icon' => 'fa-brands fa-instagram', 'color' => '#E1306C', 'bg' => 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)'],
        'tiktok' => ['name' => 'TikTok', 'icon' => 'fa-brands fa-tiktok', 'color' => '#000000', 'bg' => '#000000'],
        'whatsapp' => ['name' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp', 'color' => '#25D366', 'bg' => '#25D366'],
        'youtube' => ['name' => 'YouTube', 'icon' => 'fa-brands fa-youtube', 'color' => '#FF0000', 'bg' => '#FF0000'],
        'facebook' => ['name' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f', 'color' => '#1877F2', 'bg' => '#1877F2'],
        'x' => ['name' => 'X (Twitter)', 'icon' => 'fa-brands fa-x-twitter', 'color' => '#000000', 'bg' => '#000000'],
        'spotify' => ['name' => 'Spotify', 'icon' => 'fa-brands fa-spotify', 'color' => '#1DB954', 'bg' => '#1DB954'],
        'linkedin' => ['name' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in', 'color' => '#0A66C2', 'bg' => '#0A66C2'],
        'telegram' => ['name' => 'Telegram', 'icon' => 'fa-brands fa-telegram', 'color' => '#24A1DE', 'bg' => '#24A1DE'],
        'discord' => ['name' => 'Discord', 'icon' => 'fa-brands fa-discord', 'color' => '#5865F2', 'bg' => '#5865F2'],
        'twitch' => ['name' => 'Twitch', 'icon' => 'fa-brands fa-twitch', 'color' => '#9146FF', 'bg' => '#9146FF'],
        'threads' => ['name' => 'Threads', 'icon' => 'fa-brands fa-threads', 'color' => '#000000', 'bg' => '#000000'],
        'pinterest' => ['name' => 'Pinterest', 'icon' => 'fa-brands fa-pinterest-p', 'color' => '#E60023', 'bg' => '#E60023'],
        'github' => ['name' => 'GitHub', 'icon' => 'fa-brands fa-github', 'color' => '#24292F', 'bg' => '#24292F'],
        'email' => ['name' => 'Correo', 'icon' => 'fa-solid fa-envelope', 'color' => '#EA4335', 'bg' => 'linear-gradient(135deg, #EA4335, #D93025)'],
        'phone' => ['name' => 'Teléfono', 'icon' => 'fa-solid fa-phone', 'color' => '#10B981', 'bg' => 'linear-gradient(135deg, #10B981, #059669)']
    ];

    return $brands[$brand] ?? null;
}
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
    
    <!-- Icon Libraries: Font Awesome 6 & Phosphor -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fontFamily) ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: <?= $bgColor ?>;
            color: <?= $textColor ?>;
            font-family: '<?= htmlspecialchars($fontFamily) ?>', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3rem 1.25rem 2rem;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }
        .bio-container {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .bio-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .bio-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 0.85rem;
            border: 3.5px solid <?= $textColor ?>20;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .bio-avatar:hover {
            transform: scale(1.05);
        }
        .bio-title-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 0.25rem;
        }
        .bio-title {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .bio-slug {
            font-size: 0.88rem;
            font-weight: 600;
            opacity: 0.75;
            font-family: 'SF Mono', monospace;
            margin-bottom: 0.5rem;
        }
        .bio-desc {
            font-size: 0.92rem;
            opacity: 0.88;
            white-space: pre-wrap;
            line-height: 1.5;
            max-width: 360px;
            margin: 0 auto;
        }

        /* Social Bar */
        .bio-social-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .bio-social-icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            flex-shrink: 0;
        }
        .bio-social-icon-btn:hover {
            transform: translateY(-3px) scale(1.12);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }

        /* Link Buttons */
        .bio-link {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.95rem 1.25rem;
            background: <?= $btnColor ?>;
            color: <?= $btnTextColor ?>;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07);
            border: 1px solid <?= $btnTextColor ?>15;
            box-sizing: border-box;
        }
        .bio-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
            opacity: 0.98;
        }
        .bio-link:active {
            transform: scale(0.98);
        }
        .bio-link.rounded-md { border-radius: 14px; }
        .bio-link.rounded-full { border-radius: 9999px; }
        .bio-link.rounded-none { border-radius: 0; }

        /* Loading */
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
            width: 42px; height: 42px;
            border: 4px solid <?= $textColor ?>20;
            border-top-color: <?= $textColor ?>;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .fade-in-up {
            opacity: 0;
            transform: translateY(18px);
            animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div id="loader">
        <div class="spinner"></div>
    </div>

    <div class="bio-container">
        <header class="bio-header">
            <img src="<?= htmlspecialchars($profileImage) ?>" alt="<?= htmlspecialchars($linktree['title']) ?>" class="bio-avatar" onerror="this.src='<?= htmlspecialchars($defaultAvatar) ?>'">
            
            <div class="bio-title-wrap">
                <h1 class="bio-title"><?= htmlspecialchars($linktree['title']) ?></h1>
                <?php if($verifiedBadge): ?>
                    <i class="fa-solid fa-circle-check" style="color:#0284c7; font-size:1.15rem; margin-left:2px;" title="Cuenta Verificada"></i>
                <?php endif; ?>
            </div>

            <div class="bio-slug">@<?= htmlspecialchars($linktree['slug']) ?></div>
            
            <?php if($linktree['bio']): ?>
                <p class="bio-desc"><?= htmlspecialchars($linktree['bio']) ?></p>
            <?php endif; ?>

            <!-- Social Bar Icons -->
            <?php if (!empty($socialBar) && is_array($socialBar)): ?>
                <div class="bio-social-bar fade-in-up" style="animation-delay: 0.15s;">
                    <?php foreach ($socialBar as $s): 
                        $brandKey = $s['network'] ?? '';
                        $brandInfo = getBrandInfo($brandKey);
                        if (!$brandInfo) continue;
                        $url = $s['url'] ?? '#';
                        if ($brandKey === 'whatsapp' && !str_starts_with($url, 'http')) {
                            $phone = preg_replace('/[^0-9]/', '', $url);
                            $url = "https://wa.me/{$phone}";
                        } elseif ($brandKey === 'email' && !str_starts_with($url, 'mailto:')) {
                            $url = "mailto:{$url}";
                        } elseif ($brandKey === 'phone' && !str_starts_with($url, 'tel:')) {
                            $url = "tel:{$url}";
                        } elseif (!str_starts_with($url, 'http') && !str_starts_with($url, 'mailto:') && !str_starts_with($url, 'tel:')) {
                            $url = "https://{$url}";
                        }
                    ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer" class="bio-social-icon-btn" title="<?= htmlspecialchars($brandInfo['name']) ?>" style="background: <?= $brandInfo['bg'] ?>; color:#ffffff;">
                            <i class="<?= $brandInfo['icon'] ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </header>

        <main class="flex flex-col gap-3.5">
            <?php 
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
                return str_replace('spotify.com/', 'spotify.com/embed/', $url);
            }

            $delay = 0.2;
            $now = new DateTime();
            
            foreach ($links as $link): 
                $type = $link['type'] ?? 'link';
                $meta = isset($link['meta_data']) && $link['meta_data'] ? json_decode($link['meta_data'], true) : [];
                
                // Scheduling
                if (!empty($meta['start_date']) && new DateTime($meta['start_date']) > $now) continue;
                if (!empty($meta['end_date']) && new DateTime($meta['end_date']) < $now) continue;
                
                $animStyle = "animation-delay: {$delay}s;";
                $brandInfo = getBrandInfo($type);
                $useBrandColor = ($meta['useBrandColor'] ?? true) && $brandInfo;

                if ($brandInfo || $type === 'link' || !$type):
                    $cardBg = $useBrandColor ? $brandInfo['bg'] : $btnColor;
                    $cardTextColor = $useBrandColor ? '#ffffff' : $btnTextColor;
                    $linkUrl = $link['url'] ?? '#';
                    if (!str_starts_with($linkUrl, 'http') && !str_starts_with($linkUrl, 'mailto:') && !str_starts_with($linkUrl, 'tel:')) {
                        $linkUrl = "https://{$linkUrl}";
                    }
            ?>
                <a href="<?= htmlspecialchars($linkUrl) ?>" target="_blank" rel="noopener noreferrer" class="bio-link <?= htmlspecialchars($btnStyle) ?> fade-in-up" style="<?= $animStyle ?> background:<?= $cardBg ?>; color:<?= $cardTextColor ?>;">
                    <?php if($brandInfo): ?>
                        <i class="<?= $brandInfo['icon'] ?>" style="position:absolute; left:1.25rem; font-size:1.25rem; color:<?= $useBrandColor ? '#ffffff' : $brandInfo['color'] ?>;"></i>
                    <?php elseif(!empty($link['icon'])): ?>
                        <i class="<?= htmlspecialchars($link['icon']) ?>" style="position:absolute; left:1.25rem; font-size:1.25rem;"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-link" style="position:absolute; left:1.25rem; font-size:1.15rem; opacity:0.8;"></i>
                    <?php endif; ?>
                    <span style="padding:0 2rem;"><?= htmlspecialchars($link['title']) ?></span>
                </a>
            <?php elseif ($type === 'youtube'): 
                $rawVideoId = $meta['videoId'] ?? '';
                $videoId = htmlspecialchars(getYouTubeId($rawVideoId));
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1.25rem' : ($btnStyle === 'rounded-none' ? '0' : '0.85rem') ?>; overflow:hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.6rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; background:#000;">
                        <iframe src="https://www.youtube.com/embed/<?= $videoId ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                </div>
            <?php elseif ($type === 'spotify'): 
                $spotifyUrl = htmlspecialchars(getSpotifyEmbedUrl($meta['spotifyUrl'] ?? ''));
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1.25rem' : ($btnStyle === 'rounded-none' ? '0' : '0.85rem') ?>; overflow:hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.6rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <iframe src="<?= $spotifyUrl ?>" width="100%" height="80" frameborder="0" allowtransparency="true" allow="encrypted-media"></iframe>
                </div>
            <?php elseif ($type === 'text'): ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; margin-top:0.75rem; margin-bottom:0.25rem; text-align:center;">
                    <?php if($link['title']): ?>
                        <h3 style="font-size:1.15rem; font-weight:700; margin-bottom:0.25rem;"><?= htmlspecialchars($link['title']) ?></h3>
                    <?php endif; ?>
                    <?php if(!empty($meta['text'])): ?>
                        <p style="font-size:0.88rem; opacity:0.88; white-space:pre-wrap;"><?= htmlspecialchars($meta['text']) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($type === 'faq'): ?>
                <details class="fade-in-up" style="<?= $animStyle ?> width:100%; background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; border-radius: <?= $btnStyle === 'rounded-full' ? '1.25rem' : ($btnStyle === 'rounded-none' ? '0' : '0.85rem') ?>; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding:0.9rem 1.15rem; cursor:pointer; text-align:left; border:1px solid <?= $btnTextColor ?>15;">
                    <summary style="font-weight:600; font-size:0.92rem; outline:none;"><?= htmlspecialchars($link['title']) ?></summary>
                    <div style="margin-top:0.65rem; font-size:0.84rem; opacity:0.88; white-space:pre-wrap; line-height:1.45;"><?= htmlspecialchars($meta['answer'] ?? '') ?></div>
                </details>
            <?php elseif ($type === 'whatsapp'): 
                $phone = preg_replace('/[^0-9]/', '', $meta['phone'] ?? '');
                $message = urlencode($meta['message'] ?? '');
                $waUrl = "https://wa.me/{$phone}?text={$message}";
            ?>
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="bio-link fade-in-up" style="<?= $animStyle ?> background:#25D366; color:#ffffff; border-radius: <?= $btnStyle === 'rounded-full' ? '9999px' : ($btnStyle === 'rounded-none' ? '0' : '14px') ?>;">
                    <i class="fa-brands fa-whatsapp" style="position:absolute; left:1.25rem; font-size:1.25rem; color:#ffffff;"></i>
                    <span style="padding:0 2rem;"><?= htmlspecialchars($link['title'] ?: 'WhatsApp') ?></span>
                </a>
            <?php elseif ($type === 'map'): 
                $address = urlencode($meta['address'] ?? '');
            ?>
                <div class="fade-in-up" style="<?= $animStyle ?> width:100%; border-radius: <?= $btnStyle === 'rounded-full' ? '1.25rem' : ($btnStyle === 'rounded-none' ? '0' : '0.85rem') ?>; overflow:hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
                    <?php if($link['title']): ?>
                        <div style="background:<?= $btnColor ?>; color:<?= $btnTextColor ?>; padding:0.6rem; text-align:center; font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($link['title']) ?></div>
                    <?php endif; ?>
                    <iframe width="100%" height="240" frameborder="0" style="border:0" src="https://maps.google.com/maps?q=<?= $address ?>&t=&z=15&ie=UTF8&iwloc=&output=embed" allowfullscreen></iframe>
                </div>
            <?php 
                endif;
            $delay += 0.08;
            endforeach; 
            ?>
        </main>
        
        <?php if(!$hideWatermark): ?>
        <footer class="mt-8 text-center text-xs opacity-50 font-medium fade-in-up" style="animation-delay: <?= $delay ?>s">
            Creado con Roma Agencia
        </footer>
        <?php endif; ?>
    </div>
    
    <script>
        window.addEventListener('load', () => {
            const loader = document.getElementById('loader');
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.visibility = 'hidden', 500);
            }
        });
    </script>
</body>
</html>
