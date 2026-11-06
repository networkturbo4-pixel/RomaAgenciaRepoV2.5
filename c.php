<?php
// c.php — Gateway directo y amigable para cotizaciones públicas
$token = $_GET['t'] ?? $_GET['token'] ?? '';
if (empty($token) && !empty($_SERVER['PATH_INFO'])) {
    $token = trim($_SERVER['PATH_INFO'], '/');
}
if (empty($token)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/(?:c|cotizacion)/([^/]+)#', $uri, $m)) {
        $token = $m[1];
    }
}
$_GET['module'] = 'quotes';
$_GET['action'] = 'public';
$_GET['token'] = $token;

require_once __DIR__ . '/index.php';
