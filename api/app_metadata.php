<?php
// api/app_metadata.php
// Endpoint público ligero para sincronizar en vivo el nombre del sistema, favicon y colores con la APK Android y PWA
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'site_description', 'favicon', 'logo_light', 'primary_color')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocol = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'romaagencia.lat';
    $scriptDir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    $baseUrl = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '');

    $faviconRel = $settings['favicon'] ?? ($settings['logo_light'] ?? '');
    $faviconUrl = '';
    $faviconVersion = '1';
    if (!empty($faviconRel)) {
        if (strpos($faviconRel, 'http') === 0) {
            $faviconUrl = $faviconRel;
        } else {
            $localFile = dirname(__DIR__) . '/' . ltrim($faviconRel, '/');
            if (is_file($localFile)) {
                $faviconVersion = (string)filemtime($localFile);
            }
            $faviconUrl = rtrim($baseUrl, '/') . '/' . ltrim($faviconRel, '/') . '?v=' . $faviconVersion;
        }
    }

    echo json_encode([
        'success'         => true,
        'site_name'       => !empty($settings['site_name']) ? $settings['site_name'] : 'ROMA',
        'description'     => $settings['site_description'] ?? '',
        'favicon_url'     => $faviconUrl,
        'favicon_version' => $faviconVersion,
        'primary_color'   => $settings['primary_color'] ?? '#4f46e5',
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success'       => false,
        'site_name'     => 'ROMA',
        'favicon_url'   => '',
        'primary_color' => '#4f46e5'
    ]);
}
