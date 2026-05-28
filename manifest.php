<?php
header('Content-Type: application/manifest+json; charset=utf-8');
require_once 'config/database.php';

$db = (new Database())->getConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'favicon')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$appName = !empty($settings['site_name']) ? $settings['site_name'] : "App Manager";
$favicon = !empty($settings['favicon']) ? $settings['favicon'] : "assets/img/icon-192x192.png";

// Si el favicon no empieza con slash o http, agregamos una barra inicial relativa
// para asegurarnos de que el PWA sepa dnde buscarlo, aunque en la mayora de los casos
// es relativo al manifest.
$iconUrl = $favicon;

$manifest = [
    "name" => $appName,
    "short_name" => $appName,
    "description" => "Sistema de gestin y colaboracin",
    "start_url" => "/",
    "display" => "standalone",
    "background_color" => "#0f172a",
    "theme_color" => "#0ea5e9",
    "icons" => [
        [
            "src" => $iconUrl,
            "sizes" => "192x192",
            "type" => "image/png" // Podra ser jpg o ico, pero Chrome suele ignorar el type si el archivo es vlido.
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
