<?php
// servicio.php — Gateway directo para servicios públicos
$id = $_GET['id'] ?? 0;
$slug = $_GET['slug'] ?? '';

if (!$id && !empty($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    if (!empty($parts[0]) && is_numeric($parts[0])) {
        $id = (int)$parts[0];
        $slug = $parts[1] ?? '';
    }
}
if (!$id) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/servicio(?:\\.php)?/([0-9]+)(?:/([^/?#]+))?#', $uri, $m)) {
        $id = (int)$m[1];
        if (!empty($m[2])) {
            $slug = $m[2];
        }
    }
}

if ($id > 0) {
    $_GET['module'] = 'public';
    $_GET['action'] = 'service';
    $_GET['id'] = $id;
    if (!empty($slug)) {
        $_GET['slug'] = $slug;
    }
    require_once __DIR__ . '/index.php';
    exit;
}

header("Location: index.php?module=public&action=catalog");
exit;
