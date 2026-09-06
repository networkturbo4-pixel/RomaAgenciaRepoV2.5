<?php
// modules/work_orders/ajax_search_orders.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'active'; // active, archived, all

try {
    // 1. Get counts for KPIs
    $totalCount = (int)$db->query("SELECT COUNT(*) FROM work_orders")->fetchColumn();
    $activeCount = (int)$db->query("SELECT COUNT(*) FROM work_orders WHERE is_archived = 0")->fetchColumn();
    $archivedCount = (int)$db->query("SELECT COUNT(*) FROM work_orders WHERE is_archived = 1")->fetchColumn();

    // 2. Build Search SQL
    $sql = "SELECT wo.*, 
                   b.name as joined_brand_name, 
                   b.logo as brand_logo
            FROM work_orders wo
            LEFT JOIN client_brands b ON wo.brand_name = b.name
            WHERE 1=1";
    $params = [];

    if ($status === 'active') {
        $sql .= " AND wo.is_archived = 0";
    } elseif ($status === 'archived') {
        $sql .= " AND wo.is_archived = 1";
    }

    if ($query !== '') {
        $sql .= " AND (wo.correlativo LIKE ? OR wo.brand_name LIKE ? OR wo.data LIKE ?)";
        $wildcard = "%$query%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY wo.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse data JSON for frontend consumption
    $orders = [];
    foreach ($rawOrders as $wo) {
        $dataDecoded = json_decode($wo['data'], true) ?: [];
        $cliente = $dataDecoded['cliente'] ?? 'Sin cliente';
        
        // Parse redes
        $redesRaw = $dataDecoded['redes'] ?? '';
        $redes = [];
        if (!empty($redesRaw)) {
            $decoded = json_decode($redesRaw, true);
            if (is_array($decoded)) {
                $redes = $decoded;
            } else {
                $arr = array_filter(array_map('trim', explode(',', $redesRaw)));
                $redes = array_map(function($r) { return ['id' => $r, 'url' => '']; }, $arr);
            }
        }

        $presupuesto = $dataDecoded['presupuesto'] ?? 'No definido';
        $fechaInicio = $dataDecoded['fechaInicio'] ?? '';
        $fechaFinal = $dataDecoded['fechaFinal'] ?? '';
        $prioridad = $dataDecoded['prioridad'] ?? 'Media';

        $orders[] = [
            'id' => (int)$wo['id'],
            'correlativo' => $wo['correlativo'],
            'brand_name' => $wo['brand_name'] ?? 'Sin Marca',
            'brand_logo' => $wo['brand_logo'] ?? '',
            'public_token' => $wo['public_token'],
            'is_archived' => (int)$wo['is_archived'],
            'created_at' => $wo['created_at'],
            'cliente' => $cliente,
            'redes' => $redes,
            'presupuesto' => $presupuesto,
            'fechaInicio' => $fechaInicio,
            'fechaFinal' => $fechaFinal,
            'prioridad' => $prioridad
        ];
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'total' => $totalCount,
            'active' => $activeCount,
            'archived' => $archivedCount
        ],
        'data' => $orders
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
