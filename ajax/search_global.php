<?php
// ajax/search_global.php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['clientes' => [], 'marcas' => []]);
    exit();
}

$db = (new Database())->getConnection();

// Search Clients
$stmt_clients = $db->prepare("SELECT id, name, email FROM clients WHERE name LIKE :q OR email LIKE :q LIMIT 3");
$stmt_clients->execute([':q' => "%$query%"]);
$clientes = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

// Search Brands
$stmt_brands = $db->prepare("SELECT id, client_id, name FROM client_brands WHERE name LIKE :q LIMIT 3");
$stmt_brands->execute([':q' => "%$query%"]);
$marcas = [];
while ($row = $stmt_brands->fetch(PDO::FETCH_ASSOC)) {
    $marcas[] = [
        'title' => $row['name'],
        'subtitle' => 'Marca / Empresa',
        'url' => 'index.php?module=clients&action=view&id=' . $row['client_id']
    ];
    
    // Find associated project and all months
    $stmt_proj = $db->prepare("
        SELECT p.id as project_id, pm.id as month_id, pm.month, pm.year 
        FROM projects p 
        JOIN work_orders w ON p.work_order_id = w.id
        LEFT JOIN project_months pm ON p.id = pm.project_id
        WHERE w.brand_name = ?
        ORDER BY p.id DESC, pm.id DESC
    ");
    $stmt_proj->execute([$row['name']]);
    $projDataRows = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);

    $meses_nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $project_added = false;

    foreach ($projDataRows as $projData) {
        if ($projData['project_id'] && !$project_added) {
            $marcas[] = [
                'title' => 'Project Board - ' . $row['name'],
                'subtitle' => 'Tablero de Proyecto',
                'url' => 'index.php?module=project_board&action=index&id=' . $projData['project_id']
            ];
            $project_added = true;
        }
        
        if ($projData['month_id']) {
            $mes_str = isset($meses_nombres[(int)$projData['month']]) ? $meses_nombres[(int)$projData['month']] : $projData['month'];
            $marcas[] = [
                'title' => 'Month Board (' . $mes_str . ' ' . $projData['year'] . ') - ' . $row['name'],
                'subtitle' => 'Tablero Mensual',
                'url' => 'index.php?module=month_board&action=index&id=' . $projData['month_id']
            ];
        }
    }
}

// Search Reuniones
$stmt_reuniones = $db->prepare("SELECT id, motivo, fecha_hora FROM reuniones WHERE motivo LIKE :q OR resumen LIKE :q ORDER BY fecha_hora DESC LIMIT 3");
$stmt_reuniones->execute([':q' => "%$query%"]);
$reuniones_raw = $stmt_reuniones->fetchAll(PDO::FETCH_ASSOC);
$reuniones = [];
foreach($reuniones_raw as $r) {
    $reuniones[] = [
        'title' => $r['motivo'],
        'subtitle' => date('d M, Y', strtotime($r['fecha_hora'])),
        'url' => 'index.php?module=reuniones&action=view&id=' . $r['id']
    ];
}

// Search Quotes
$is_numeric = is_numeric($query);
$stmt_quotes = $db->prepare("
    SELECT q.id, c.name as client_name, q.id as quote_number 
    FROM quotes q 
    LEFT JOIN clients c ON q.client_id = c.id 
    WHERE c.name LIKE :q " . ($is_numeric ? "OR q.id = :id" : "") . "
    ORDER BY q.created_at DESC LIMIT 3
");
$params = [':q' => "%$query%"];
if ($is_numeric) {
    $params[':id'] = (int)$query;
}
$stmt_quotes->execute($params);
$cot_raw = $stmt_quotes->fetchAll(PDO::FETCH_ASSOC);
$cotizaciones = [];
foreach($cot_raw as $c) {
    $cotizaciones[] = [
        'title' => 'Cotización #' . str_pad($c['quote_number'], 5, '0', STR_PAD_LEFT),
        'subtitle' => $c['client_name'],
        'url' => 'index.php?module=quotes&action=view&id=' . $c['id']
    ];
}

// Search Tasks
$stmt_tasks = $db->prepare("SELECT id, title FROM tasks WHERE title LIKE :q ORDER BY created_at DESC LIMIT 3");
$stmt_tasks->execute([':q' => "%$query%"]);
$tareas = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

// Search Services
$stmt_services = $db->prepare("SELECT id, name FROM services WHERE name LIKE :q LIMIT 3");
$stmt_services->execute([':q' => "%$query%"]);
$servicios_raw = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
$servicios = [];
foreach($servicios_raw as $s) {
    $servicios[] = [
        'title' => $s['name'],
        'subtitle' => 'Servicio',
        'url' => 'index.php?module=services&action=view&id=' . $s['id']
    ];
}

echo json_encode([
    'clientes' => $clientes,
    'marcas' => $marcas,
    'reuniones' => $reuniones,
    'cotizaciones' => $cotizaciones,
    'tareas' => $tareas,
    'servicios' => $servicios
]);
