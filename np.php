<?php
// np.php — Gateway directo y amigable para enlaces cortos de notas de pago
$token = $_GET['t'] ?? $_GET['token'] ?? '';
if (empty($token) && !empty($_SERVER['PATH_INFO'])) {
    $token = trim($_SERVER['PATH_INFO'], '/');
}
if (empty($token)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/(?:np|nota)/([^/]+)#', $uri, $m)) {
        $token = $m[1];
    }
}
$_GET['module'] = 'admin';
$_GET['action'] = 'payment_note_webview';
$_GET['view'] = 'public';
$_GET['token'] = $token;

require_once __DIR__ . '/index.php';
