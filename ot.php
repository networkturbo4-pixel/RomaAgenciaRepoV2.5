<?php
// ot.php — Gateway directo para órdenes de servicio públicas
$token = $_GET['t'] ?? $_GET['token'] ?? '';
if (empty($token) && !empty($_SERVER['PATH_INFO'])) {
    $token = trim($_SERVER['PATH_INFO'], '/');
}
if (empty($token)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/(?:ot|orden)/([^/]+)#', $uri, $m)) {
        $token = $m[1];
    }
}
$_GET['module'] = 'work_orders';
$_GET['action'] = 'public';
$_GET['token'] = $token;

require_once __DIR__ . '/index.php';
