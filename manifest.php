<?php
header('Content-Type: application/manifest+json; charset=utf-8');
require_once 'config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'favicon', 'primary_color')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$appName = !empty($settings['site_name']) ? $settings['site_name'] : "App Manager";
$favicon = !empty($settings['favicon']) ? $settings['favicon'] : "assets/img/icon-192x192.png";
$primaryColor = !empty($settings['primary_color']) ? $settings['primary_color'] : "#5bb450";

$iconUrl = $favicon;

$manifest = [
    "name" => $appName,
    "short_name" => $appName,
    "description" => "Sistema de gestión y colaboración",
    "start_url" => "/",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => $primaryColor,
    "icons" => [
        [
            "src" => $iconUrl,
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $iconUrl,
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
