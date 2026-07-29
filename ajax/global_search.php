<?php
// ajax/global_search.php
require_once '../config/database.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

$db = (new Database())->getConnection();
$results = [];

// 1. Buscar en Marcas / Clientes
$stmt = $db->prepare("SELECT id, name FROM client_brands WHERE name LIKE ? LIMIT 5");
$stmt->execute(['%' . $q . '%']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'type' => 'link',
        'title' => $row['name'],
        'subtitle' => 'Marca / Cliente',
        'icon' => 'ph-briefcase',
        'url' => 'index.php?module=clients&action=view_brand&id=' . $row['id']
    ];
}

// 2. Buscar en Reuniones
$stmt = $db->prepare("SELECT id, motivo, fecha_hora FROM reuniones WHERE motivo LIKE ? OR resumen LIKE ? ORDER BY fecha_hora DESC LIMIT 5");
$stmt->execute(['%' . $q . '%', '%' . $q . '%']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'type' => 'link',
        'title' => $row['motivo'],
        'subtitle' => 'Reunión - ' . date('d M Y', strtotime($row['fecha_hora'])),
        'icon' => 'ph-video-camera',
        'url' => 'index.php?module=reuniones&action=view&id=' . $row['id']
    ];
}

// 3. Buscar en Cotizaciones
$stmt = $db->prepare("SELECT id, title, quote_number FROM quotes WHERE title LIKE ? OR quote_number LIKE ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute(['%' . $q . '%', '%' . $q . '%']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'type' => 'link',
        'title' => $row['title'],
        'subtitle' => 'Cotización ' . $row['quote_number'],
        'icon' => 'ph-file-text',
        'url' => 'index.php?module=quotes&action=view&id=' . $row['id']
    ];
}

// 4. Buscar Tareas / Proyectos
$stmt = $db->prepare("SELECT id, title FROM tasks WHERE title LIKE ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute(['%' . $q . '%']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'type' => 'link',
        'title' => $row['title'],
        'subtitle' => 'Tarea',
        'icon' => 'ph-check-square-offset',
        'url' => 'index.php?module=projects&action=task_board'
    ];
}

echo json_encode(['results' => $results]);
