<?php
// modules/public/catalog.php
require_once 'config/database.php';
$is_public = true;

// Fetch Global Settings
$global_settings = [];
$stmt = $db->query("SELECT * FROM settings");
foreach ($stmt->fetchAll() as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch categories
$stmt_cat = $db->query("SELECT * FROM service_categories ORDER BY name ASC");
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Fetch active, public services
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

$where = "s.deleted_at IS NULL AND s.visibility = 'public' AND s.status != 'paused'";
$params = [];

if ($search !== '') {
    $where .= " AND (s.name LIKE :search OR s.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($category > 0) {
    $where .= " AND s.category_id = :cat";
    $params[':cat'] = $category;
}

$sql = "
    SELECT s.*, c.name as category_name, c.color_tag as category_color
    FROM services s
    LEFT JOIN service_categories c ON s.category_id = c.id
    WHERE $where
    ORDER BY s.created_at DESC
";

$stmt_serv = $db->prepare($sql);
$stmt_serv->execute($params);
$services = $stmt_serv->fetchAll(PDO::FETCH_ASSOC);

$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Nuestra Agencia');
$currency_code = trim(htmlspecialchars($global_settings['currency'] ?? 'USD'));
$currency_symbols = ['PEN' => 'S/', 'USD' => '$', 'EUR' => '€', 'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'COP' => '$'];
$currency = $currency_symbols[$currency_code] ?? $currency_code;

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo rtrim($base_url, '/\\') . '/'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php 
    $seo_title = "Catálogo de Servicios " . ($global_settings['seo_title_suffix'] ?? ' | ' . $site_name);
    $seo_desc = $global_settings['seo_description'] ?? 'Explora nuestro catálogo de servicios. Soluciones diseñadas para impulsar tu negocio al siguiente nivel con ' . $site_name . '.';
    $seo_keys = $global_settings['seo_keywords'] ?? 'Catálogo, Servicios, Soluciones, Agencia, ' . $site_name;
    ?>
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo_keys); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($site_name); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($seo_title); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($seo_desc); ?>">

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>;
            --primary-light: color-mix(in srgb, var(--primary-color), white 90%);
            --bg-color: #f4f5f7;
            --surface-color: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --radius-lg: 20px;
            --radius-md: 14px;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            font-size: 13px; 
            background: var(--bg-color); 
            color: var(--text-main); 
            margin: 0; 
            padding: 0; 
            -webkit-font-smoothing: antialiased;
        }
        
        /* App Header */
        .app-header { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            padding: 1rem 1.5rem; 
            border-bottom: 1px solid rgba(229, 231, 235, 0.5); 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .logo { font-size: 1.1rem; font-weight: 800; color: var(--primary-color); text-decoration: none; letter-spacing: -0.3px; }
        .contact-btn { 
            text-decoration: none; 
            color: #10b981; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 0.4rem; 
            background: rgba(16, 185, 129, 0.1); 
            padding: 0.5rem 0.8rem; 
            border-radius: 20px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .contact-btn:hover { background: rgba(16, 185, 129, 0.2); }
        
        /* Hero Section */
        .hero { 
            padding: 3rem 1.5rem; 
            text-align: center; 
            background: linear-gradient(135deg, var(--surface-color) 0%, var(--bg-color) 100%);
            border-bottom: 1px solid var(--border-color);
            width: 100%;
            box-sizing: border-box;
        }
        .hero h1 { font-size: 2rem; margin: 0 0 0.5rem 0; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main); word-break: break-word; }
        .hero p { font-size: 14px; color: var(--text-muted); max-width: 500px; margin: 0 auto; line-height: 1.5; word-break: break-word; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem 4rem 1.5rem; width: 100%; box-sizing: border-box; overflow-x: hidden; }
        
        /* Modern Filters */
        .filters { 
            display: flex; 
            gap: 0.75rem; 
            margin-bottom: 2rem; 
            background: var(--surface-color);
            padding: 0.75rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }
        .search-box { flex: 1; min-width: 0; position: relative; }
        .search-box input { 
            width: 100%; 
            padding: 0.75rem 1rem 0.75rem 2.5rem; 
            border: none; 
            background: var(--bg-color);
            border-radius: var(--radius-md); 
            font-size: 13px; 
            font-family: inherit; 
            color: var(--text-main);
            outline: none;
            transition: box-shadow 0.2s;
        }
        .search-box input:focus { box-shadow: 0 0 0 2px var(--primary-light); }
        .search-box i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; }
        
        .cat-tab {
            padding: 0.6rem 1.25rem;
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            border-radius: 30px;
            font-size: 13px;
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-weight: 500;
        }
        .cat-tab:hover {
            border-color: var(--primary-light);
            background: var(--bg-color);
        }
        .cat-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(var(--primary-rgb, 37, 99, 235), 0.2);
        }
        
        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        
        /* App Card */
        .card { 
            background: var(--surface-color); 
            border-radius: var(--radius-lg); 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            transition: transform 0.2s ease, box-shadow 0.2s ease; 
            display: flex; 
            flex-direction: column; 
            text-decoration: none; 
            color: inherit; 
            border: 1px solid rgba(229, 231, 235, 0.5); 
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px rgba(0,0,0,0.06); }
        
        .card-cover { width: 100%; height: 160px; background-size: cover; background-position: center; background-color: var(--bg-color); position: relative; }
        .card-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 2.5rem; opacity: 0.3; }
        
        .badge-out { position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(239, 68, 68, 0.95); color: white; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 11px; font-weight: 700; backdrop-filter: blur(4px); letter-spacing: 0.5px; }
        
        .card-countdown { position: absolute; bottom: 0; left: 0; width: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); padding: 0.4rem; text-align: center; color: white; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px; }
        .card-countdown i { color: #f59e0b; }
        
        .card-body { padding: 1.25rem; flex-grow: 1; display: flex; flex-direction: column; }
        .card-cat { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; display: inline-block; padding: 0.2rem 0.5rem; border-radius: 6px; }
        .card-title { font-size: 16px; font-weight: 700; margin: 0 0 0.5rem 0; line-height: 1.3; color: var(--text-main); }
        .card-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .card-footer { border-top: 1px solid var(--bg-color); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .price-label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 1px; display: block; letter-spacing: 0.5px; }
        .price-val { font-size: 18px; font-weight: 800; color: var(--primary-color); letter-spacing: -0.5px; }
        .btn-view { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--primary-light); 
            color: var(--primary-color); 
            border-radius: 50%; 
            transition: all 0.2s; 
        }
        .card:hover .btn-view { background: var(--primary-color); color: white; transform: translateX(2px); }

        /* Skeleton Loader */
        .skeleton {
            background: #e2e5e7;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0));
            background-size: 40px 100%;
            background-repeat: no-repeat;
            background-position: left -40px top 0;
            animation: shine 1s ease infinite;
        }
        @keyframes shine {
            to { background-position: right -40px top 0; }
        }
        .skel-card { border: 1px solid rgba(229, 231, 235, 0.5); border-radius: var(--radius-lg); background: var(--surface-color); overflow: hidden; display: flex; flex-direction: column; height: 380px; }
        .skel-cover { width: 100%; height: 160px; }
        .skel-body { padding: 1.25rem; flex-grow: 1; display: flex; flex-direction: column; gap: 0.75rem; }
        .skel-badge { width: 80px; height: 20px; border-radius: 6px; }
        .skel-title { width: 90%; height: 20px; border-radius: 4px; }
        .skel-desc { width: 100%; height: 14px; border-radius: 4px; }
        .skel-desc.short { width: 70%; }
        .skel-footer { border-top: 1px solid var(--bg-color); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .skel-price { width: 60px; height: 20px; border-radius: 4px; }
        .skel-btn { width: 36px; height: 36px; border-radius: 50%; }
        
        #realGrid { display: none; }
        #realGrid.loaded { display: block; }
        #skeletonGrid { display: grid; }

        /* Mobile specific adjustments */
        @media (max-width: 800px) {
            .hero { padding: 2rem 1.5rem; }
            .hero h1 { font-size: 1.75rem; }
            .filters { flex-direction: column; }
            .filter-select { width: 100%; }
            
            /* Carousel effect for the grid */
            .grid, #skeletonGrid {
                display: flex;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                gap: 1.25rem;
                padding-bottom: 2rem;
                margin-left: -1.5rem;
                margin-right: -1.5rem;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
                -webkit-overflow-scrolling: touch;
            }
            .grid::-webkit-scrollbar { display: none; }
            .card { flex: 0 0 85%; scroll-snap-align: center; }
            .skel-card { flex: 0 0 85%; scroll-snap-align: center; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <a href="catalogo" class="logo">
        <?php if(!empty($global_settings['logo_light'])): ?>
            <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo" style="height: 24px;">
        <?php else: ?>
            <?php echo $site_name; ?>
        <?php endif; ?>
    </a>
    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $global_settings['company_phone'] ?? ''); ?>" target="_blank" class="contact-btn">
        <i class="ph-fill ph-whatsapp-logo" style="font-size: 1.1rem;"></i> Chat
    </a>
</header>

<section class="hero">
    <h1>Servicios</h1>
    <p>Explora nuestras soluciones diseñadas para impulsar tu negocio al siguiente nivel.</p>
</section>

<main class="container">
    <div class="catalog-header" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
        <form method="GET" action="index.php" class="filters" style="margin-bottom: 0;" onsubmit="event.preventDefault(); filterServices();">
            <input type="hidden" name="module" value="public">
            <input type="hidden" name="action" value="catalog">
            
            <div class="search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" name="q" id="searchInput" placeholder="Buscar servicios..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="filterServices()">
            </div>
        </form>

        <div class="cat-tabs" style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; scrollbar-width: none;">
            <button class="cat-tab <?php echo $category == 0 ? 'active' : ''; ?>" onclick="setCategory(0, this)">Todos</button>
            <?php foreach ($categories as $cat): ?>
                <button class="cat-tab <?php echo $category == $cat['id'] ? 'active' : ''; ?>" onclick="setCategory(<?php echo $cat['id']; ?>, this)">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($services)): ?>
        <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
            <div style="width: 64px; height: 64px; background: var(--surface-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem auto; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <i class="ph ph-magnifying-glass" style="font-size: 1.8rem; color: var(--primary-color);"></i>
            </div>
            <h3 style="font-size: 16px; margin: 0 0 0.5rem 0; color: var(--text-main);">No encontramos resultados</h3>
            <p style="font-size: 13px; margin: 0 0 1rem 0;">Intenta buscar con otras palabras clave.</p>
            <a href="catalogo" style="color: var(--primary-color); text-decoration: none; font-weight: 600; font-size: 13px;">Ver todo</a>
        </div>
    <?php else: ?>
        <div id="skeletonGrid" class="grid">
            <?php for($i=0; $i<6; $i++): ?>
            <div class="skel-card skeleton">
                <div class="skel-cover"></div>
                <div class="skel-body">
                    <div class="skel-badge"></div>
                    <div class="skel-title"></div>
                    <div class="skel-desc"></div>
                    <div class="skel-desc short"></div>
                    <div class="skel-footer">
                        <div class="skel-price"></div>
                        <div class="skel-btn"></div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        
        <div id="realGrid">
            <?php 
            // Agrupar por categoría
            $groupedServices = [];
            foreach ($services as $srv) {
                $catId = $srv['category_id'] ?: 0;
                $catName = $srv['category_name'] ?: 'Otros Servicios';
                if (!isset($groupedServices[$catId])) {
                    $groupedServices[$catId] = ['name' => $catName, 'services' => []];
                }
                $groupedServices[$catId]['services'][] = $srv;
            }
            
            foreach ($groupedServices as $catId => $group): 
            ?>
            <div class="category-section" data-category-id="<?php echo $catId; ?>" style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; padding: 0 0.25rem;"><?php echo htmlspecialchars($group['name']); ?></h2>
                <div class="grid">
                    <?php foreach ($group['services'] as $srv): 
                        $link = "servicio/" . $srv['id'];
                        if (!empty($srv['slug'])) {
                            $link .= "/" . urlencode($srv['slug']);
                        }
                        
                        $catColor = !empty($srv['category_color']) ? $srv['category_color'] : '#6b7280';
                        list($r, $g, $b) = sscanf($catColor, "#%02x%02x%02x");
                        $rgbaColor = "rgba($r, $g, $b, 0.1)";
                    ?>
                    <a href="<?php echo $link; ?>" class="card" data-category-id="<?php echo $srv['category_id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($srv['name'])); ?>">
                        <div class="card-cover">
                            <?php if(!empty($srv['cover_image'])): ?>
                                <img src="uploads/services/<?php echo htmlspecialchars($srv['cover_image']); ?>" alt="<?php echo htmlspecialchars($srv['name']); ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            <?php else: ?>
                                <div class="card-placeholder"><i class="ph ph-image"></i></div>
                            <?php endif; ?>
                            
                            <?php if ($srv['status'] === 'out_of_stock'): ?>
                                <span class="badge-out">Agotado</span>
                            <?php elseif (!empty($srv['badge'])): ?>
                                <span class="badge-out" style="background: var(--primary-color);"><?php echo htmlspecialchars($srv['badge']); ?></span>
                            <?php endif; ?>
                            
                            <?php if (!empty($srv['countdown_end']) && strtotime($srv['countdown_end']) > time()): ?>
                                <div class="card-countdown" data-end="<?php echo strtotime($srv['countdown_end']) * 1000; ?>">
                                    <i class="ph ph-timer"></i> <span class="time-text">Calculando...</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($srv['category_name'])): ?>
                            <div><span class="card-cat" style="color: <?php echo $catColor; ?>; background-color: <?php echo $rgbaColor; ?>;"><?php echo htmlspecialchars($srv['category_name']); ?></span></div>
                            <?php endif; ?>
                            <h3 class="card-title">
                                <?php if(!empty($srv['icon'])): 
                                    $icn = trim($srv['icon']);
                                    if(strpos($icn, 'ph-') !== 0 && strpos($icn, ' ') === false) $icn = 'ph-' . $icn;
                                ?>
                                    <i class="ph <?php echo htmlspecialchars($icn); ?>" style="color: var(--primary-color); font-size: 1.1em; vertical-align: middle; margin-right: 2px;"></i> 
                                <?php endif; ?>
                                <?php echo htmlspecialchars($srv['name']); ?>
                            </h3>
                            <div class="card-desc"><?php echo htmlspecialchars($srv['description'] ?? ''); ?></div>
                            
                            <div class="card-footer">
                                <div>
                                    <?php if($srv['price_type'] === 'packages' || $srv['price_type'] === 'from'): ?>
                                        <span class="price-label">Desde</span>
                                    <?php else: ?>
                                        <span class="price-label">Precio</span>
                                    <?php endif; ?>
                                    <?php 
                                        $srv_currency_code = !empty($srv['currency']) ? trim($srv['currency']) : $currency_code;
                                        $srv_currency = $currency_symbols[$srv_currency_code] ?? $srv_currency_code;
                                    ?>
                                    <span class="price-val"><?php echo $srv_currency . ' ' . number_format($srv['price'], 2); ?></span>
                                </div>
                                <div class="btn-view">
                                    <i class="ph ph-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    // Skeleton loader
    document.addEventListener('DOMContentLoaded', function() {
        // Remove skeletons quickly without waiting for all images
        setTimeout(() => {
            document.getElementById('skeletonGrid').style.display = 'none';
            document.getElementById('realGrid').classList.add('loaded');
        }, 100);
    });

    let currentCategory = <?php echo $category; ?>;

    function setCategory(id, btn) {
        currentCategory = id;
        document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
        if(btn) btn.classList.add('active');
        filterServices();
    }

    function filterServices() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const catId = parseInt(card.getAttribute('data-category-id') || '0');
            const name = card.getAttribute('data-name') || '';
            const matchesCat = currentCategory === 0 || catId === currentCategory;
            const matchesQuery = name.includes(query);
            
            if (matchesCat && matchesQuery) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Hide empty category sections
        document.querySelectorAll('.category-section').forEach(section => {
            const visibleCards = Array.from(section.querySelectorAll('.card')).filter(c => c.style.display !== 'none');
            section.style.display = visibleCards.length > 0 ? 'block' : 'none';
        });
    }

    // Countdown logic
    function updateCountdowns() {
        document.querySelectorAll('.card-countdown').forEach(el => {
            const end = parseInt(el.getAttribute('data-end'));
            const now = Date.now();
            const diff = end - now;
            
            if (diff <= 0) {
                el.style.display = 'none';
                return;
            }
            
            const h = Math.floor(diff / (1000 * 60 * 60));
            const m = Math.floor((diff / (1000 * 60)) % 60);
            const s = Math.floor((diff / 1000) % 60);
            
            el.querySelector('.time-text').innerHTML = `${h}h ${m}m ${s}s`;
        });
    }
    
    setInterval(updateCountdowns, 1000);
    updateCountdowns();
</script>
</body>
</html>
