<?php
// modules/public/service.php
require_once 'config/database.php';
$is_public = true;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?module=public&action=catalog");
    exit;
}

// Fetch Service
$stmt = $db->prepare("
    SELECT s.*, c.name as category_name, c.color_tag as category_color 
    FROM services s
    LEFT JOIN service_categories c ON s.category_id = c.id
    WHERE s.id = ? AND s.deleted_at IS NULL
");
$stmt->execute([$id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo "<div style='text-align:center; padding:4rem; font-family:sans-serif;'><h2>Servicio no encontrado o no disponible.</h2><a href='index.php?module=public&action=catalog'>Volver al catálogo</a></div>";
    exit;
}

// Increment views
$db->prepare("UPDATE services SET views = views + 1 WHERE id = ?")->execute([$id]);

// Fetch related data
$stmt_feat = $db->prepare("SELECT * FROM service_features WHERE service_id = ? ORDER BY sort_order ASC");
$stmt_feat->execute([$id]);
$features = $stmt_feat->fetchAll(PDO::FETCH_ASSOC);
$benefits = array_filter($features, function($f) { return $f['type'] !== 'deliverable'; });
$deliverables = array_filter($features, function($f) { return $f['type'] === 'deliverable'; });

$stmt_faq = $db->prepare("SELECT * FROM service_faqs WHERE service_id = ? ORDER BY sort_order ASC");
$stmt_faq->execute([$id]);
$faqs = $stmt_faq->fetchAll(PDO::FETCH_ASSOC);

$stmt_pre = $db->prepare("SELECT * FROM service_prerequisites WHERE service_id = ? ORDER BY sort_order ASC");
$stmt_pre->execute([$id]);
$prereqs = $stmt_pre->fetchAll(PDO::FETCH_ASSOC);

$stmt_pkg = $db->prepare("SELECT * FROM service_packages WHERE service_id = ? ORDER BY sort_order ASC");
$stmt_pkg->execute([$id]);
$packages = $stmt_pkg->fetchAll(PDO::FETCH_ASSOC);

$stmt_gal = $db->prepare("SELECT * FROM service_gallery WHERE service_id = ? ORDER BY sort_order ASC");
$stmt_gal->execute([$id]);
$gallery = $stmt_gal->fetchAll(PDO::FETCH_ASSOC);

$stmt_rel = $db->prepare("
    SELECT s.id, s.name, s.cover_image, s.price, s.slug, s.price_type, s.currency
    FROM service_relations sr
    JOIN services s ON sr.related_service_id = s.id
    WHERE sr.service_id = ? AND s.deleted_at IS NULL AND s.visibility = 'public'
");
$stmt_rel->execute([$id]);
$related = $stmt_rel->fetchAll(PDO::FETCH_ASSOC);

$addons = [];
if (!empty($service['has_addons'])) {
    $stmt_addon = $db->prepare("SELECT * FROM service_addons WHERE service_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt_addon->execute([$id]);
    $addons = $stmt_addon->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Global Settings
$global_settings = [];
$stmt_set = $db->query("SELECT * FROM settings");
foreach ($stmt_set->fetchAll() as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Nuestra Agencia');
$currency_code = !empty($service['currency']) ? trim($service['currency']) : trim(htmlspecialchars($global_settings['currency'] ?? 'USD'));
$currency_symbols = ['PEN' => 'S/', 'USD' => '$', 'EUR' => '€', 'MXN' => '$', 'ARS' => '$', 'CLP' => '$', 'COP' => '$'];
$currency = $currency_symbols[$currency_code] ?? $currency_code;
$whatsapp = preg_replace('/[^0-9]/', '', $global_settings['company_phone'] ?? '');

$page_title = htmlspecialchars($service['meta_title'] ?: $service['name']) . " | " . $site_name;
$page_desc = htmlspecialchars($service['meta_description'] ?: strip_tags($service['description']));
$og_image = !empty($service['og_image']) ? 'uploads/services/' . $service['og_image'] : (!empty($service['cover_image']) ? 'uploads/services/' . $service['cover_image'] : '');
// Make URL absolute if possible
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
if ($og_image) {
    $og_image_url = $base_url . '/' . ltrim($og_image, '/');
} else {
    $og_image_url = '';
}
$current_url = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo rtrim($base_url, '/\\') . '/'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_desc; ?>">
    
    <!-- Open Graph / Redes Sociales -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $page_desc; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:type" content="product">
    <?php if ($og_image_url): ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
        <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css"/>
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
        .btn-back { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--surface-color); 
            color: var(--text-main); 
            border-radius: 50%; 
            border: 1px solid var(--border-color);
            transition: all 0.2s; 
            text-decoration: none;
        }
        /* Container Layout */
        .container { max-width: 1350px; margin: 0 auto 4rem auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; align-items: stretch; width: 100%; overflow-x: hidden; box-sizing: border-box; }
        @media (min-width: 900px) { .container { display: grid; grid-template-columns: 1fr 450px; margin-top: 1.5rem; overflow-x: visible; gap: 2rem; } }
        @media (max-width: 600px) { .container { padding: 1rem; } }
        
        /* Main Content */
        .content { background: var(--surface-color); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(229, 231, 235, 0.5); width: 100%; box-sizing: border-box; }
        @media (max-width: 600px) { .content { padding: 1rem; border-radius: var(--radius-md); } }
        
        .cover-img { width: 100%; max-height: 350px; object-fit: cover; border-radius: var(--radius-md); margin-bottom: 1.5rem; background: var(--bg-color); }
        
        .cat-tag { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 0.75rem; border: 1px solid; letter-spacing: 0.5px; }
        h1 { font-size: 22px; margin: 0 0 0.75rem 0; font-weight: 800; line-height: 1.2; letter-spacing: -0.5px; color: var(--text-main); word-break: break-word; }
        .desc { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem; word-break: break-word; }
        
        .section-title { font-size: 16px; font-weight: 700; margin: 2rem 0 1rem 0; display: flex; align-items: center; gap: 0.4rem; color: var(--text-main); }
        
        /* Lists */
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; width: 100%; box-sizing: border-box; }
        .feature-item { 
            display: flex; 
            gap: 1rem; 
            align-items: flex-start; 
            padding: 1.25rem; 
            background: var(--surface-color); 
            border-radius: var(--radius-md); 
            border: 1px solid var(--border-color); 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .feature-item:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
        }
        .feature-icon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary-color);
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .feature-icon-wrap.gift-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        .feature-item:hover .feature-icon-wrap:not(.gift-icon) {
            background: var(--primary-color);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 10px var(--primary-light);
        }
        .feature-item:hover .feature-icon-wrap.gift-icon {
            background: #f59e0b;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2);
        }
        .feature-icon { font-size: 1.35rem; }
        .feature-title { font-weight: 700; font-size: 14px; margin-bottom: 0.35rem; color: var(--text-main); word-break: break-word; }
        .feature-desc { color: var(--text-muted); font-size: 13px; line-height: 1.5; word-break: break-word; }

        /* Gallery */
        .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; width: 100%; box-sizing: border-box; }
        @media (min-width: 600px) { .gallery-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; } }
        .gallery-item { width: 100%; aspect-ratio: 1; position: relative; border-radius: var(--radius-md); overflow: hidden; cursor: pointer; border: 1px solid var(--border-color); }
        .gallery-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .gallery-item:hover img { transform: scale(1.05); }

        /* Gallery Media Cards - PDF & Web */
        .gallery-media-card { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.75rem 0.5rem; box-sizing: border-box; overflow: hidden; transition: all 0.3s ease; }
        .gallery-media-card::before { content: ''; position: absolute; inset: 0; opacity: 0; transition: opacity 0.3s ease; }
        .gallery-item:hover .gallery-media-card::before { opacity: 1; }

        /* PDF Card */
        .gallery-card-pdf { background: linear-gradient(145deg, #fff5f5, #ffe3e3, #ffc9c9); }
        .gallery-card-pdf::before { background: linear-gradient(145deg, #ffe3e3, #ffb3b3, #ff8787); }
        .gallery-card-pdf .media-icon-wrap { width: 42px; height: 42px; background: linear-gradient(135deg, #ff6b6b, #e03131); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 4px 12px rgba(224, 49, 49, 0.25); position: relative; z-index: 1; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .gallery-item:hover .gallery-card-pdf .media-icon-wrap { transform: scale(1.1) translateY(-2px); box-shadow: 0 6px 16px rgba(224, 49, 49, 0.35); }
        .gallery-card-pdf .media-icon-wrap i { color: white; font-size: 1.3rem; }
        .gallery-card-pdf .media-card-title { font-size: 0.65rem; color: #c92a2a; font-weight: 700; text-align: center; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; line-height: 1.3; position: relative; z-index: 1; letter-spacing: -0.01em; }
        .gallery-card-pdf .media-card-badge { font-size: 0.55rem; color: white; background: linear-gradient(135deg, #ff6b6b, #e03131); padding: 2px 6px; border-radius: 4px; margin-top: 4px; font-weight: 700; letter-spacing: 0.05em; position: relative; z-index: 1; }

        /* Web Card */
        .gallery-card-web { background: linear-gradient(145deg, #e7f5ff, #d0ebff, #a5d8ff); }
        .gallery-card-web::before { background: linear-gradient(145deg, #d0ebff, #99cdff, #74c0fc); }
        .gallery-card-web .media-icon-wrap { width: 42px; height: 42px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 4px 12px rgba(25, 113, 194, 0.15); position: relative; z-index: 1; transition: transform 0.3s ease, box-shadow 0.3s ease; overflow: hidden; }
        .gallery-card-web .media-icon-wrap img { width: 28px; height: 28px; object-fit: contain; position: static; }
        .gallery-item:hover .gallery-card-web .media-icon-wrap { transform: scale(1.1) translateY(-2px); box-shadow: 0 6px 16px rgba(25, 113, 194, 0.25); }
        .gallery-card-web .media-card-title { font-size: 0.65rem; color: #1864ab; font-weight: 700; text-align: center; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; word-break: break-all; line-height: 1.3; position: relative; z-index: 1; letter-spacing: -0.01em; }
        .gallery-card-web .media-card-domain { font-size: 0.55rem; color: #1971c2; opacity: 0.7; text-align: center; margin-top: 2px; position: relative; z-index: 1; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .gallery-card-web .media-card-badge { font-size: 0.55rem; color: white; background: linear-gradient(135deg, #339af0, #1971c2); padding: 2px 6px; border-radius: 4px; margin-top: 4px; font-weight: 700; letter-spacing: 0.05em; position: relative; z-index: 1; }

        /* FAQs */
        .faq-item { margin-bottom: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: var(--surface-color); }
        .faq-q { padding: 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-main); }
        .faq-a { padding: 1rem; background: var(--bg-color); color: var(--text-muted); border-top: 1px solid var(--border-color); display: none; line-height: 1.5; font-size: 13px; }
        .faq-item.open .faq-a { display: block; }

        /* Sidebar CTA */
        .sidebar-box { background: var(--surface-color); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid rgba(229, 231, 235, 0.5); position: sticky; top: 5rem; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-box.hide-on-mobile { transform: translateY(120%); }
        @media (max-width: 900px) { 
            .sidebar-box { 
                position: fixed; 
                top: auto;
                bottom: 0; 
                left: 0; 
                right: 0; 
                z-index: 900; 
                border-radius: 24px 24px 0 0; 
                box-shadow: 0 -10px 30px rgba(0,0,0,0.1); 
                padding: 1.25rem 1.5rem; 
                border: none;
                border-top: 1px solid rgba(229, 231, 235, 0.5);
                max-height: 80vh;
                overflow-y: auto;
            } 
            .container { padding-bottom: 120px !important; }
            .meta-info { display: none !important; }
            .mobile-cta-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; }
            .mobile-cta-row .price-col { flex: 1; }
            .mobile-cta-row .btn-col { flex: 1; }
        }
        @media (min-width: 900px) {
            .mobile-cta-row { display: block; }
        }
        
        .price-big { font-size: 28px; font-weight: 800; color: var(--primary-color); margin-bottom: 0.25rem; letter-spacing: -1px; }
        @media (max-width: 900px) { .price-big { font-size: 22px; margin-bottom: 0; } }
        .price-label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .btn-cta { display: flex; align-items: center; justify-content: center; gap: 0.4rem; width: 100%; padding: 0.85rem; color: white; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; margin-top: 1.25rem; transition: transform 0.1s; border: none; cursor: pointer; }
        @media (max-width: 900px) { .btn-cta { margin-top: 0; } }
        .btn-cta:active { transform: scale(0.98); }
        .btn-whatsapp { background: #10b981; }
        .btn-whatsapp:hover { background: #059669; }
        
        .meta-info { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-muted); font-size: 12px; }
        .meta-info i { color: var(--primary-color); font-size: 1.1rem; }

        /* Packages */
        .packages-grid { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.75rem; }
        .pkg-card { border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem; cursor: pointer; transition: all 0.2s; background: var(--surface-color); }
        .pkg-card.selected { border-color: var(--primary-color); background: var(--primary-light); }
        .pkg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        .pkg-name { font-weight: 700; font-size: 13px; color: var(--text-main); }
        .pkg-price { font-weight: 800; color: var(--primary-color); font-size: 13px; }
        .pkg-desc { font-size: 11px; color: var(--text-muted); }

        /* Addons */
        .addons-wrapper { margin-bottom: 1.25rem; padding-top: 1rem; border-top: 1px dashed var(--border-color); }
        .addon-item { background: var(--bg-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .addon-info { flex: 1; }
        .addon-name { font-weight: 700; font-size: 13px; color: var(--text-main); margin-bottom: 0.25rem; }
        .addon-price { font-size: 12px; color: var(--text-muted); }
        .addon-price .strike { text-decoration: line-through; color: var(--text-muted); font-size: 0.85em; }
        .discounted { color: #10b981; font-weight: 700; }

        /* Addons Accordion */
        .addons-accordion { margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem; width: 100%; }
        .addons-toggle { display: none; width: 100%; background: none; border: none; padding: 0.5rem 0; font-family: inherit; font-size: 13px; font-weight: 600; color: var(--text-main); cursor: pointer; text-align: left; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
        .addons-toggle i { transition: transform 0.3s ease; }
        .addons-content { display: block; }
        
        @media (max-width: 900px) {
            .addons-accordion { margin-top: 0.5rem; border-top: none; padding-top: 0.5rem; }
            .addons-toggle { display: flex; }
            .addons-content { max-height: 0; overflow-y: auto; transition: max-height 0.3s ease; }
            .addons-accordion.open .addons-content { max-height: 250px; }
            .addons-accordion.open .addons-toggle i { transform: rotate(180deg); }
            /* When open on mobile, ensure we have some space above */
            .addons-accordion.open { border-top: 1px solid var(--border-color); margin-top: 1rem; padding-top: 1rem; }
        }
        
        /* Addon Quantity Controls */
        .addon-qty-ctrl { display: inline-flex; align-items: center; background: var(--bg-color); border-radius: 20px; overflow: hidden; padding: 3px; }
        .addon-qty-ctrl button { background: var(--surface-color); border: none; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; color: var(--text-main); transition: all 0.2s; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-weight: 700; font-size: 15px; }
        .addon-qty-ctrl button:hover { transform: scale(1.05); color: var(--primary-color); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .addon-qty-ctrl button:active { transform: scale(0.95); }
        .addon-qty-ctrl input { width: 34px; text-align: center; border: none; font-size: 13px; font-weight: 700; background: transparent; color: var(--text-main); pointer-events: none; }
        
        /* Modern Switcher */
        .addon-switch { position: relative; display: inline-block; width: 44px; height: 24px; margin-top: 2px; }
        .addon-switch input { opacity: 0; width: 0; height: 0; }
        .addon-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-color); transition: .4s; border-radius: 24px; }
        .addon-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .addon-switch input:checked + .addon-slider { background-color: var(--primary-color); }
        .addon-switch input:checked + .addon-slider:before { transform: translateX(20px); }

        /* Packages */
        .packages-grid { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem; }
        .pkg-card { position: relative; border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem 1rem 1rem 3.2rem; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); background: var(--surface-color); }
        .pkg-card::before { content: ""; position: absolute; left: 1rem; top: 1.15rem; width: 1.2rem; height: 1.2rem; border-radius: 50%; border: 2px solid #cbd5e1; transition: all 0.2s; background: transparent; }
        .pkg-card:hover { border-color: #cbd5e1; }
        .pkg-card.selected { border-color: var(--primary-color); background: #f0fdf4; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.08); }
        .pkg-card.selected::before { border-color: var(--primary-color); border-width: 5px; }
        .pkg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; }
        .pkg-name { font-weight: 700; font-size: 14px; color: var(--text-main); }
        .pkg-price { font-weight: 800; color: var(--primary-color); font-size: 14px; }
        .pkg-desc { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

        /* Packages Accordion (Mobile) */
        .packages-toggle { display: none; width: 100%; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem 1.25rem; font-family: inherit; cursor: pointer; text-align: left; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; transition: background 0.2s; }
        .packages-toggle:hover { background: #f1f5f9; }
        .packages-toggle i { transition: transform 0.3s ease; color: var(--text-muted); }
        .packages-content { display: block; }
        .desktop-pkg-label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 0.5rem; }
        
        @media (max-width: 900px) {
            .desktop-pkg-label { display: none; }
            .packages-toggle { display: flex; }
            .packages-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
            .packages-accordion.open .packages-content { max-height: 350px; overflow-y: auto; padding-right: 0.5rem; }
            .packages-accordion.open .packages-toggle i { transform: rotate(180deg); }
        }

        /* Video */
        .video-wrapper { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .video-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

        /* Pricing Table */
        .pricing-table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color); background: var(--surface-color); box-shadow: 0 4px 20px rgba(0,0,0,0.03); table-layout: fixed; }
        .pricing-table th, .pricing-table td { padding: 1.5rem 1.25rem; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); text-align: left; vertical-align: top; min-width: 200px; }
        .pricing-table th:last-child, .pricing-table td:last-child { border-right: none; }
        .pricing-table tr:last-child td { border-bottom: none; }
        .pricing-table th { background: #f8fafc; font-weight: 700; position: relative; }
        .pricing-table th::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--primary-color); opacity: 0.85; }
        .pricing-table td { font-size: 13px; color: var(--text-main); line-height: 1.6; background: #ffffff; word-wrap: break-word; }
        .pkg-th-name { font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem; line-height: 1.3; }
        .pkg-th-price { color: var(--primary-color); font-size: 18px; font-weight: 800; margin-top: 0.5rem; }
        
        @media (max-width: 900px) {
            .pricing-table { width: max-content; min-width: 100%; }
            .pricing-table th, .pricing-table td { min-width: 43vw; width: 43vw; padding: 1.25rem 1rem; }
        }

        /* Related */
        .related-grid { display: flex; overflow-x: auto; gap: 1rem; padding-bottom: 1rem; margin-top: 1rem; scroll-snap-type: x mandatory; }
        .related-grid::-webkit-scrollbar { height: 6px; }
        .related-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        .rel-card { flex: 0 0 200px; scroll-snap-align: start; background: var(--surface-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; text-decoration: none; color: inherit; transition: transform 0.2s; }
        .rel-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .rel-cover { width: 100%; height: 100px; background-size: cover; background-position: center; background-color: var(--bg-color); }
        .rel-body { padding: 0.75rem; }
        .rel-title { font-weight: 700; font-size: 12px; margin: 0 0 0.25rem 0; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rel-price { font-weight: 700; color: var(--primary-color); font-size: 12px; }
    </style>
</head>
<body>

<header class="app-header">
    <a href="index.php?module=public&action=catalog" class="btn-back"><i class="ph ph-arrow-left"></i></a>
    <a href="index.php?module=public&action=catalog" class="logo">
        <?php if(!empty($global_settings['logo_light'])): ?>
            <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo" style="height: 20px;">
        <?php else: ?>
            <?php echo $site_name; ?>
        <?php endif; ?>
    </a>
    <div style="width: 36px;"></div> <!-- Spacer for centering -->
</header>

<main class="container">
    <div class="content">
        <?php if(!empty($service['cover_image'])): ?>
            <img src="uploads/services/<?php echo htmlspecialchars($service['cover_image']); ?>" class="cover-img" alt="<?php echo htmlspecialchars($service['name']); ?>">
        <?php endif; ?>

        <?php
            $catColor = !empty($service['category_color']) ? $service['category_color'] : '#6b7280';
            list($r, $g, $b) = sscanf($catColor, "#%02x%02x%02x");
            $rgbaColor = "rgba($r, $g, $b, 0.1)";
        ?>
        <?php if(!empty($service['category_name'])): ?>
            <div class="cat-tag" style="color: <?php echo $catColor; ?>; background-color: <?php echo $rgbaColor; ?>; border-color: <?php echo $catColor; ?>;"><?php echo htmlspecialchars($service['category_name']); ?></div>
        <?php endif; ?>
        
        <h1 style="display: flex; align-items: center; gap: 0.5rem;">
            <?php if(!empty($service['icon'])): 
                $icn = trim($service['icon']);
                if(strpos($icn, 'ph-') !== 0 && strpos($icn, ' ') === false) $icn = 'ph-' . $icn;
            ?>
                <i class="ph <?php echo htmlspecialchars($icn); ?>" style="color: var(--primary-color); font-size: 1.1em;"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($service['name']); ?>
        </h1>
        <div class="desc"><?php echo nl2br(htmlspecialchars($service['description'] ?? '')); ?></div>

        <?php if(!empty($service['video_url'])): 
            $vid = $service['video_url'];
            $embed = '';
            if (strpos($vid, 'youtube.com') !== false || strpos($vid, 'youtu.be') !== false) {
                preg_match("/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/", $vid, $matches);
                if (isset($matches[1])) $embed = "https://www.youtube.com/embed/" . $matches[1];
            } elseif (strpos($vid, 'vimeo.com') !== false) {
                preg_match("/(?:vimeo\.com\/)(\d+)/", $vid, $matches);
                if (isset($matches[1])) $embed = "https://player.vimeo.com/video/" . $matches[1];
            }
            if ($embed):
        ?>
            <div class="video-wrapper">
                <iframe src="<?php echo $embed; ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php endif; endif; ?>

        <?php if(!empty($gallery)): ?>
            <h3 class="section-title"><i class="ph ph-images"></i> Galería</h3>
            <div class="gallery-grid">
                <?php foreach($gallery as $g): ?>
                    <?php if (isset($g['media_type']) && $g['media_type'] === 'video'): ?>
                        <?php
                            $ytId = '';
                            if (preg_match('/[?&]v=([^&]+)/', $g['image_path'], $matches)) {
                                $ytId = $matches[1];
                            }
                        ?>
                        <a href="<?php echo htmlspecialchars($g['image_path']); ?>" data-fancybox="gallery" class="gallery-item">
                            <div style="position:relative; width:100%; height:100%; background:#000; display:flex; align-items:center; justify-content:center;">
                                <i class="ph-fill ph-play-circle" style="color:white; font-size:2rem; z-index:2; position:absolute;"></i>
                                <?php if ($ytId): ?>
                                    <img src="https://img.youtube.com/vi/<?php echo $ytId; ?>/mqdefault.jpg" style="opacity:0.6; object-fit:cover; width:100%; height:100%;" alt="Video">
                                <?php else: ?>
                                    <i class="ph ph-video-camera" style="color:var(--text-muted); font-size:2.5rem; position:absolute;"></i>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php elseif (isset($g['media_type']) && $g['media_type'] === 'pdf'): ?>
                        <?php $pdfTitle = !empty($g['title']) ? htmlspecialchars($g['title']) : 'Documento PDF'; ?>
                        <a href="uploads/services/gallery/<?php echo htmlspecialchars($g['image_path']); ?>" data-fancybox="gallery" data-type="pdf" class="gallery-item" title="<?php echo $pdfTitle; ?>">
                            <div class="gallery-media-card gallery-card-pdf">
                                <div class="media-icon-wrap">
                                    <i class="ph-fill ph-file-pdf"></i>
                                </div>
                                <span class="media-card-title"><?php echo $pdfTitle; ?></span>
                                <span class="media-card-badge">PDF</span>
                            </div>
                        </a>
                    <?php elseif (isset($g['media_type']) && $g['media_type'] === 'web'): ?>
                        <?php 
                            $webTitle = !empty($g['title']) ? htmlspecialchars($g['title']) : htmlspecialchars($g['image_path']); 
                            $thumb = !empty($g['thumbnail_url']) ? htmlspecialchars($g['thumbnail_url']) : 'https://www.google.com/s2/favicons?domain=' . urlencode($g['image_path']) . '&sz=128';
                            $parsedUrl = parse_url($g['image_path']);
                            $domain = $parsedUrl['host'] ?? $g['image_path'];
                        ?>
                        <a href="<?php echo htmlspecialchars($g['image_path']); ?>" data-fancybox="gallery" data-type="iframe" class="gallery-item" title="<?php echo $webTitle; ?>">
                            <div class="gallery-media-card gallery-card-web">
                                <div class="media-icon-wrap">
                                    <img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($domain); ?>">
                                </div>
                                <span class="media-card-title"><?php echo $webTitle; ?></span>
                                <span class="media-card-domain"><?php echo htmlspecialchars($domain); ?></span>
                                <span class="media-card-badge">WEB</span>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="uploads/services/gallery/<?php echo htmlspecialchars($g['image_path']); ?>" data-fancybox="gallery" data-type="image" class="gallery-item">
                            <img src="uploads/services/gallery/<?php echo htmlspecialchars($g['image_path']); ?>" alt="Gallery Image">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if($service['price_type'] === 'packages' && !empty($packages)): ?>
            <h3 class="section-title"><i class="ph ph-table"></i> Comparar Paquetes</h3>
            <div style="overflow-x: auto; margin-bottom: 2rem; padding-bottom: 0.5rem;">
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <?php foreach($packages as $pkg): ?>
                                <th>
                                    <div class="pkg-th-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                                    <div class="pkg-th-price"><?php echo $currency . ' ' . number_format($pkg['price'], 2); ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach($packages as $pkg): ?>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($pkg['description'])); ?>
                                    
                                    <?php 
                                        $pkgFeatures = !empty($pkg['features']) ? json_decode($pkg['features'], true) : [];
                                        if(!empty($pkgFeatures) && is_array($pkgFeatures)): 
                                    ?>
                                        <ul style="list-style: none; margin: 1rem 0 0 0; padding-left: 0; color: var(--text-muted); font-size: 13px;">
                                            <?php foreach($pkgFeatures as $feat): ?>
                                                <li style="margin-bottom: 0.35rem; display: flex; align-items: flex-start; gap: 4px;">
                                                    <span style="color: var(--primary-color);">•</span>
                                                    <span><?php echo htmlspecialchars($feat); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if(!empty($pkg['delivery_time'])): ?>
                                        <div style="margin-top: 1rem; color: var(--text-main); font-weight: 600;"><i class="ph ph-clock"></i> Entrega en <?php echo htmlspecialchars($pkg['delivery_time']); ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if(!empty($benefits)): ?>
            <h3 class="section-title"><i class="ph ph-check-circle"></i> Características Principales</h3>
            <div class="feature-grid">
                <?php foreach($benefits as $b): ?>
                    <div class="feature-item">
                        <div class="feature-icon-wrap">
                            <i class="ph ph-check-circle feature-icon"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="feature-title"><?php echo htmlspecialchars($b['title']); ?></div>
                            <?php if(!empty($b['description'])): ?>
                            <div class="feature-desc"><?php echo nl2br(htmlspecialchars($b['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($deliverables)): ?>
            <h3 class="section-title"><i class="ph ph-package"></i> ¿Qué vas a recibir?</h3>
            <div class="feature-grid">
                <?php foreach($deliverables as $d): ?>
                    <div class="feature-item">
                        <div class="feature-icon-wrap gift-icon">
                            <i class="ph ph-gift feature-icon"></i>
                        </div>
                        <div style="flex: 1;">
                            <div class="feature-title"><?php echo htmlspecialchars($d['title']); ?></div>
                            <?php if(!empty($d['description'])): ?>
                            <div class="feature-desc"><?php echo nl2br(htmlspecialchars($d['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($prereqs)): ?>
            <h3 class="section-title"><i class="ph ph-clipboard-text"></i> Requisitos Previos</h3>
            <div style="background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <ul style="color: var(--text-muted); line-height: 1.6; padding-left: 1.25rem; margin: 0; font-size: 13px;">
                    <?php foreach($prereqs as $p): ?>
                        <li style="margin-bottom: 0.5rem;">
                            <strong style="color: var(--text-main);"><?php echo htmlspecialchars($p['title']); ?></strong>
                            <?php if(!empty($p['description'])): ?><br><span style="font-size:12px;"><?php echo htmlspecialchars($p['description']); ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if(!empty($faqs)): ?>
            <h3 class="section-title"><i class="ph ph-question"></i> Preguntas Frecuentes</h3>
            <div>
                <?php foreach($faqs as $f): ?>
                    <div class="faq-item" onclick="this.classList.toggle('open')">
                        <div class="faq-q">
                            <?php echo htmlspecialchars($f['question']); ?>
                            <i class="ph ph-caret-down"></i>
                        </div>
                        <div class="faq-a">
                            <?php echo nl2br(htmlspecialchars($f['answer'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($related)): ?>
            <h3 class="section-title"><i class="ph ph-link"></i> También podría interesarte</h3>
            <div class="related-grid">
                <?php foreach($related as $rel): 
                    $rel_link = "servicio/" . $rel['id'];
                    if (!empty($rel['slug'])) $rel_link .= "/" . urlencode($rel['slug']);
                    
                    $rel_curr_code = !empty($rel['currency']) ? trim($rel['currency']) : trim(htmlspecialchars($global_settings['currency'] ?? 'USD'));
                    $rel_curr = $currency_symbols[$rel_curr_code] ?? $rel_curr_code;
                ?>
                <a href="<?php echo $rel_link; ?>" class="rel-card">
                    <div class="rel-cover" <?php if(!empty($rel['cover_image'])): ?>style="background-image: url('uploads/services/<?php echo htmlspecialchars($rel['cover_image']); ?>');"<?php endif; ?>></div>
                    <div class="rel-body">
                        <h4 class="rel-title"><?php echo htmlspecialchars($rel['name']); ?></h4>
                        <div class="rel-price"><?php echo $rel_curr . ' ' . number_format($rel['price'], 2); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar CTA -->
    <div>
        <div class="sidebar-box" id="stickySidebar">
            <?php if($service['status'] === 'out_of_stock'): ?>
                <div style="text-align: center; color: #ef4444; font-weight: 800; font-size: 18px; margin-bottom: 0.5rem;">
                    <i class="ph ph-warning-circle"></i> Servicio Agotado
                </div>
                <p style="text-align: center; color: var(--text-muted); font-size: 13px;">Por el momento no estamos aceptando pedidos para este servicio.</p>
            <?php else: ?>
                
                <?php if($service['price_type'] === 'packages' && !empty($packages)): ?>
                    <div style="margin-bottom: 1.25rem;" class="pkg-wrapper packages-accordion">
                        <span class="desktop-pkg-label">Selecciona un paquete:</span>
                        <button type="button" class="packages-toggle" onclick="this.parentElement.classList.toggle('open')">
                            <div style="display:flex; flex-direction:column; gap:0.25rem;">
                                <span class="price-label" style="margin:0; text-transform:uppercase;">Paquete seleccionado</span>
                                <span style="font-weight:800; color:var(--primary-color); font-size:14px;" id="selectedPkgLabel"><?php echo htmlspecialchars($packages[0]['name']); ?></span>
                            </div>
                            <i class="ph-bold ph-caret-down"></i>
                        </button>
                        <div class="packages-content">
                            <div class="packages-grid">
                                <?php foreach($packages as $idx => $pkg): ?>
                                    <div class="pkg-card <?php echo $idx === 0 ? 'selected' : ''; ?>" onclick="selectPackage(this, '<?php echo htmlspecialchars($pkg['name']); ?>', <?php echo $pkg['price']; ?>)">
                                        <div class="pkg-header">
                                            <div class="pkg-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                                            <div class="pkg-price"><?php echo $currency . ' ' . number_format($pkg['price'], 2); ?></div>
                                        </div>
                                        <div class="pkg-desc"><?php echo nl2br(htmlspecialchars($pkg['description'])); ?></div>
                                        
                                        <?php 
                                            $pkgFeatures = !empty($pkg['features']) ? json_decode($pkg['features'], true) : [];
                                            if(!empty($pkgFeatures) && is_array($pkgFeatures)): 
                                        ?>
                                            <div style="margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.2rem;">
                                                <?php foreach($pkgFeatures as $feat): ?>
                                                    <div style="font-size: 11px; color: var(--text-muted); display: flex; gap: 4px; align-items: flex-start;">
                                                        <span style="color: var(--primary-color);">•</span>
                                                        <span><?php echo htmlspecialchars($feat); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        let selectedPkg = '<?php echo htmlspecialchars($packages[0]['name']); ?>';
                        let currentBasePrice = <?php echo $packages[0]['price']; ?>;
                        function selectPackage(el, name, price) {
                            document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('selected'));
                            el.classList.add('selected');
                            selectedPkg = name;
                            currentBasePrice = parseFloat(price);
                            const lbl = document.getElementById('selectedPkgLabel');
                            if(lbl) lbl.innerText = name;
                            const acc = el.closest('.packages-accordion');
                            if(acc) acc.classList.remove('open');
                            calculateTotal();
                        }
                    </script>
                <?php else: ?>
                    <script>
                        let selectedPkg = '';
                        let currentBasePrice = <?php echo $service['price']; ?>;
                    </script>
                <?php endif; ?>

                <?php 
                    $initialPrice = ($service['price_type'] === 'packages' && !empty($packages)) ? $packages[0]['price'] : $service['price'];
                    $priceLabel = ($service['price_type'] === 'packages' && !empty($packages)) ? 'Total estimado' : ($service['price_type'] === 'from' ? 'Precio desde' : 'Precio del servicio');
                ?>
                <div class="mobile-cta-row" style="flex-wrap: wrap; width: 100%;">
                    <div style="display:flex; justify-content:space-between; width:100%; gap:1rem;">
                        <div class="price-col" style="flex:1;">
                            <span class="price-label"><?php echo $priceLabel; ?></span>
                            <div class="price-big" id="displayPrice"><?php echo $currency . ' ' . number_format($initialPrice, 2); ?></div>
                        </div>
                        <div class="btn-col" style="flex:1;">
                            <?php 
                                $message = "Hola, me interesa el servicio de *" . htmlspecialchars($service['name']) . "*.";
                            ?>
                            <a href="https://wa.me/<?php echo $whatsapp; ?>?text=<?php echo urlencode($message); ?>" id="waBtn" target="_blank" class="btn-cta btn-whatsapp">
                                <i class="ph-fill ph-whatsapp-logo" style="font-size: 1.2rem;"></i> Lo quiero
                            </a>
                        </div>
                    </div>
                    
                    <?php if(!empty($addons)): ?>
                        <div class="addons-accordion">
                            <button type="button" class="addons-toggle" onclick="this.parentElement.classList.toggle('open')">
                                Agregar complementos opcionales <i class="ph-bold ph-caret-down"></i>
                            </button>
                            <div class="addons-content" id="addonsRenderArea">
                                <?php foreach($addons as $idx => $addon): ?>
                                    <div class="addon-item">
                                        <div class="addon-info">
                                            <div class="addon-name"><?php echo htmlspecialchars($addon['name']); ?></div>
                                            <div class="addon-price" id="addonPrice_<?php echo $idx; ?>">+<?php echo $currency; ?> <?php echo number_format($addon['price'], 2); ?></div>
                                        </div>
                                        <div>
                                            <?php if($addon['type'] === 'quantity'): ?>
                                                <div class="addon-qty-ctrl">
                                                    <button type="button" onclick="updateAddonQty(<?php echo $idx; ?>, -1)">-</button>
                                                    <input type="text" id="addonQty_<?php echo $idx; ?>" value="0" readonly>
                                                    <button type="button" onclick="updateAddonQty(<?php echo $idx; ?>, 1)">+</button>
                                                </div>
                                            <?php else: ?>
                                                <label class="addon-switch">
                                                    <input type="checkbox" id="addonCheck_<?php echo $idx; ?>" onchange="updateAddonCheck(<?php echo $idx; ?>)">
                                                    <span class="addon-slider"></span>
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                    let addonsData = <?php echo json_encode($addons ?? []); ?>;
                    let addonState = {};
                    if (typeof addonsData !== 'undefined') {
                        addonsData.forEach((a, idx) => {
                            addonState[idx] = {
                                type: a.type,
                                qty: a.type === 'quantity' ? 0 : false,
                                basePrice: parseFloat(a.price || 0),
                                tiers: []
                            };
                            try {
                                addonState[idx].tiers = typeof a.pricing_tiers === 'string' ? JSON.parse(a.pricing_tiers) : (a.pricing_tiers || []);
                            } catch(e) {}
                        });
                    }

                    function updateAddonQty(idx, delta) {
                        if (!addonState[idx]) return;
                        let newQty = addonState[idx].qty + delta;
                        if (newQty < 0) newQty = 0;
                        addonState[idx].qty = newQty;
                        
                        document.querySelectorAll('#addonQty_' + idx).forEach(el => el.value = newQty);
                        calculateTotal();
                    }

                    function updateAddonCheck(idx) {
                        if (!addonState[idx]) return;
                        // For checkboxes, there might be multiple depending on mobile/desktop view, but IDs should be unique.
                        // We take the first one or use event target if passed, but let's just query one.
                        let isChecked = false;
                        document.querySelectorAll('#addonCheck_' + idx).forEach(el => {
                            if (el.checked) isChecked = true;
                        });
                        addonState[idx].qty = isChecked;
                        
                        // Sync others
                        document.querySelectorAll('#addonCheck_' + idx).forEach(el => {
                            el.checked = isChecked;
                        });
                        calculateTotal();
                    }

                    function calculateTotal() {
                        let total = typeof currentBasePrice !== 'undefined' ? currentBasePrice : 0;
                        let addonsMsg = [];
                        
                        if (typeof addonsData !== 'undefined') {
                            addonsData.forEach((a, idx) => {
                                const state = addonState[idx];
                                if (!state) return;
                                
                                let currentUnitPrice = state.basePrice;
                                
                                if (state.type === 'quantity') {
                                    if (state.qty > 0) {
                                        // Check tiers
                                        let matchedTierPrice = null;
                                        // Sort tiers by min_qty descending to find the highest applicable tier
                                        let sortedTiers = [...state.tiers].sort((a,b) => b.min_qty - a.min_qty);
                                        for (let tier of sortedTiers) {
                                            if (state.qty >= tier.min_qty) {
                                                matchedTierPrice = parseFloat(tier.price);
                                                break;
                                            }
                                        }
                                        
                                        if (matchedTierPrice !== null) {
                                            currentUnitPrice = matchedTierPrice;
                                        }
                                        
                                        total += (currentUnitPrice * state.qty);
                                        addonsMsg.push(`▫️ ${state.qty}x ${a.name} ...... <?php echo $currency; ?> ${(currentUnitPrice * state.qty).toFixed(2)}`);
                                        
                                        // Update UI price for this addon
                                        let priceHtml = '';
                                        if (matchedTierPrice !== null && matchedTierPrice < state.basePrice) {
                                            priceHtml = `<span class="strike"><?php echo $currency; ?>${state.basePrice.toFixed(2)}</span> <span class="discounted"><?php echo $currency; ?>${currentUnitPrice.toFixed(2)} c/u</span>`;
                                        } else {
                                            priceHtml = `+<?php echo $currency; ?> ${currentUnitPrice.toFixed(2)} c/u`;
                                        }
                                        document.querySelectorAll('#addonPrice_' + idx).forEach(el => el.innerHTML = priceHtml);
                                        
                                    } else {
                                        document.querySelectorAll('#addonPrice_' + idx).forEach(el => el.innerHTML = `+<?php echo $currency; ?> ${state.basePrice.toFixed(2)} c/u`);
                                    }
                                } else {
                                    if (state.qty === true) {
                                        total += state.basePrice;
                                        addonsMsg.push(`▫️ 1x ${a.name} ...... <?php echo $currency; ?> ${state.basePrice.toFixed(2)}`);
                                    }
                                }
                            });
                        }
                        
                        document.getElementById('displayPrice').innerText = '<?php echo $currency; ?> ' + total.toFixed(2);
                        
                        // Update WA button
                        const waBtn = document.getElementById('waBtn');
                        if (waBtn) {
                            const baseUrl = "https://wa.me/<?php echo $whatsapp; ?>?text=";
                            let msg = "🧾 *NUEVA COTIZACIÓN* 🧾\n\n";
                            
                            if (typeof selectedPkg !== 'undefined' && selectedPkg !== '') {
                                msg += "*Servicio:* <?php echo htmlspecialchars($service['name']); ?>\n*Paquete:* " + selectedPkg + "\n\n";
                            } else {
                                msg += "*Servicio:* <?php echo htmlspecialchars($service['name']); ?>\n\n";
                            }
                            
                            if (addonsMsg.length > 0) {
                                msg += "*Complementos Opcionales:*\n" + addonsMsg.join("\n") + "\n\n";
                            }
                            
                            msg += "➖➖➖➖➖➖➖➖➖➖\n";
                            msg += "*Total Estimado:* <?php echo $currency; ?> " + total.toFixed(2) + "\n";
                            msg += "➖➖➖➖➖➖➖➖➖➖\n\n";
                            msg += "Hola, me interesa contratar este servicio.";
                            
                            waBtn.href = baseUrl + encodeURIComponent(msg);
                        }
                    }
                    
                    // Initialize
                    document.addEventListener('DOMContentLoaded', () => {
                        calculateTotal();
                    });
                </script>

                <div class="meta-info">
                    <?php if(!empty($service['delivery_time']) && $service['price_type'] !== 'packages'): ?>
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <i class="ph ph-clock"></i> Tiempo: <strong><?php echo htmlspecialchars($service['delivery_time']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                        <i class="ph ph-shield-check"></i> Compra segura y protegida
                    </div>
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                        <i class="ph ph-headset"></i> Soporte garantizado
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind('[data-fancybox="gallery"]', {
        Toolbar: { display: { left: ["infobar"], middle: [], right: ["close"] } }
    });

    // Smart auto-hide sticky bottom bar on scroll down
    let lastScrollY = window.scrollY;
    let sidebar = document.getElementById('stickySidebar');
    let ticking = false;

    window.addEventListener('scroll', function() {
        if (window.innerWidth > 900) return; // Only on mobile
        
        lastScrollY = window.scrollY;
        if (!ticking) {
            window.requestAnimationFrame(function() {
                if (lastScrollY > 150) {
                    // if scrolling down, hide it. if scrolling up, show it.
                    // to determine direction:
                    // wait, we need to compare with previous scroll position inside the handler.
                }
                ticking = false;
            });
            ticking = true;
        }
    });

    let prevScrollpos = window.pageYOffset;
    window.onscroll = function() {
        if (window.innerWidth > 900) {
            sidebar.classList.remove('hide-on-mobile');
            return;
        }
        let currentScrollPos = window.pageYOffset;
        if (prevScrollpos > currentScrollPos || currentScrollPos < 50) {
            // Scrolling UP or at the top
            sidebar.classList.remove('hide-on-mobile');
        } else {
            // Scrolling DOWN
            sidebar.classList.add('hide-on-mobile');
        }
        prevScrollpos = currentScrollPos;
    }
</script>

</body>
</html>
