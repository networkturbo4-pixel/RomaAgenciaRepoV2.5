<?php
// sitemap.php
header("Content-Type: application/xml; charset=utf-8");
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Fetch Global Settings
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
$site_url = $stmt->fetchColumn();

if (empty($site_url)) {
    // Fallback if not configured
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $site_url = $protocol . '://' . $host;
}

$site_url = rtrim($site_url, '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Landing Page
echo "  <url>\n";
echo "    <loc>" . htmlspecialchars($site_url) . "/</loc>\n";
echo "    <changefreq>weekly</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// Login
echo "  <url>\n";
echo "    <loc>" . htmlspecialchars($site_url) . "/index.php?module=auth&amp;action=login</loc>\n";
echo "    <changefreq>monthly</changefreq>\n";
echo "    <priority>0.8</priority>\n";
echo "  </url>\n";

// Catalog
echo "  <url>\n";
echo "    <loc>" . htmlspecialchars($site_url) . "/catalogo</loc>\n";
echo "    <changefreq>weekly</changefreq>\n";
echo "    <priority>0.9</priority>\n";
echo "  </url>\n";

// Fetch all public services
$stmt_serv = $db->query("SELECT id, slug, updated_at, created_at FROM services WHERE deleted_at IS NULL AND visibility = 'public' AND status != 'paused'");
while ($srv = $stmt_serv->fetch(PDO::FETCH_ASSOC)) {
    $link = $site_url . "/servicio/" . $srv['id'];
    if (!empty($srv['slug'])) {
        $link .= "/" . urlencode($srv['slug']);
    }
    
    // Determine last mod
    $last_mod = !empty($srv['updated_at']) ? $srv['updated_at'] : $srv['created_at'];
    if ($last_mod) {
        $last_mod = date('Y-m-d', strtotime($last_mod));
    }
    
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($link) . "</loc>\n";
    if ($last_mod) {
        echo "    <lastmod>" . $last_mod . "</lastmod>\n";
    }
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
?>
