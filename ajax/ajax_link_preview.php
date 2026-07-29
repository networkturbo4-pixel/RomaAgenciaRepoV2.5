<?php
error_reporting(0);
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));
$url = $data->url ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'URL inválida']);
    exit;
}

$domain = parse_url($url, PHP_URL_HOST);

// Fetch favicon from Google's service (server-side, no CORS issues)
$faviconB64 = '';
$faviconCh = curl_init('https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=32');
curl_setopt($faviconCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($faviconCh, CURLOPT_TIMEOUT, 3);
curl_setopt($faviconCh, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($faviconCh, CURLOPT_SSL_VERIFYPEER, false);
$faviconData = curl_exec($faviconCh);
$faviconMime = curl_getinfo($faviconCh, CURLINFO_CONTENT_TYPE);
curl_close($faviconCh);
if ($faviconData && strlen($faviconData) > 100) {
    $mime = $faviconMime ?: 'image/png';
    $faviconB64 = 'data:' . $mime . ';base64,' . base64_encode($faviconData);
}

// Basic cURL fetch for the page
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml',
    'Accept-Language: es-ES,es;q=0.9,en;q=0.8'
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if (!$html || $httpCode >= 400) {
    // Site blocked or unreachable - return clean fallback with domain name
    echo json_encode([
        'success' => true,
        'title' => ucfirst(explode('.', $domain)[0] === 'www' ? explode('.', $domain)[1] : explode('.', $domain)[0]),
        'description' => $domain,
        'image' => '',
        'favicon' => $faviconB64,
        'url' => $url
    ]);
    exit;
}

// Detect charset from Content-Type header or meta tags
$charset = null;
if ($contentType && preg_match('/charset=([^\s;]+)/i', $contentType, $m)) {
    $charset = trim($m[1]);
}
if (!$charset && preg_match('/<meta[^>]+charset=["\']?([^"\'\s;>]+)/i', $html, $m)) {
    $charset = trim($m[1]);
}
if (!$charset) {
    $charset = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
}
if ($charset && strtoupper($charset) !== 'UTF-8') {
    $html = mb_convert_encoding($html, 'UTF-8', $charset);
}

$doc = new DOMDocument();
@$doc->loadHTML('<?xml encoding="UTF-8">' . $html);

$title = '';
$image = '';
$description = '';

// Try to get OpenGraph tags
$metas = $doc->getElementsByTagName('meta');
for ($i = 0; $i < $metas->length; $i++) {
    $meta = $metas->item($i);
    $property = $meta->getAttribute('property');
    $name = $meta->getAttribute('name');
    $content = $meta->getAttribute('content');
    
    if ($property === 'og:title' || $name === 'twitter:title') $title = $content;
    if ($property === 'og:image' || $name === 'twitter:image') $image = $content;
    if ($property === 'og:description' || $name === 'description' || $name === 'twitter:description') $description = $content;
}

// Fallbacks
if (empty($title)) {
    $titleNodes = $doc->getElementsByTagName('title');
    if ($titleNodes->length > 0) $title = trim($titleNodes->item(0)->nodeValue);
}

// If title looks like a generic error page, use a cleaned domain name
$errorTitles = ['error', 'not found', '404', '403', '500', 'access denied', 'forbidden', 'login', 'log in', 'iniciar sesión'];
if (empty($title) || in_array(strtolower(trim($title)), $errorTitles)) {
    $parts = explode('.', $domain);
    $brandName = ($parts[0] === 'www' && count($parts) > 1) ? $parts[1] : $parts[0];
    $title = ucfirst($brandName);
}

if (empty($image)) {
    $imgs = $doc->getElementsByTagName('img');
    if ($imgs->length > 0) {
        $imgSrc = $imgs->item(0)->getAttribute('src');
        if (strpos($imgSrc, 'http') === 0) $image = $imgSrc;
    }
}

echo json_encode([
    'success' => true,
    'title' => mb_substr($title, 0, 100, 'UTF-8'),
    'description' => mb_substr($description, 0, 200, 'UTF-8'),
    'image' => $image,
    'favicon' => $faviconB64,
    'url' => $url
]);
