<?php
// modules/clients/ajax_search_clients.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$query = trim($_GET['q'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'recent');

try {
    $params = [];
    $whereClauses = [];

    if (!empty($query)) {
        $searchWildcard = '%' . $query . '%';
        $whereClauses[] = "(c.name LIKE ? OR c.dni LIKE ? OR c.whatsapp LIKE ? OR c.email LIKE ? OR c.id IN (SELECT client_id FROM client_brands WHERE name LIKE ?))";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }

    if ($filter === 'membership') {
        $whereClauses[] = "c.id IN (SELECT client_id FROM client_brands WHERE has_membership = 1)";
    } elseif ($filter === 'has_brands') {
        $whereClauses[] = "c.id IN (SELECT client_id FROM client_brands)";
    } elseif ($filter === 'no_brands') {
        $whereClauses[] = "c.id NOT IN (SELECT client_id FROM client_brands)";
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $orderSql = 'ORDER BY c.created_at DESC';
    if ($sort === 'name_asc') {
        $orderSql = 'ORDER BY c.name ASC';
    } elseif ($sort === 'name_desc') {
        $orderSql = 'ORDER BY c.name DESC';
    } elseif ($sort === 'oldest') {
        $orderSql = 'ORDER BY c.created_at ASC';
    }

    $sql = "
        SELECT c.*, 
               GROUP_CONCAT(b.name SEPARATOR '||') as brands, 
               GROUP_CONCAT(b.logo SEPARATOR '||') as logos,
               GROUP_CONCAT(COALESCE(b.has_membership, 0) SEPARATOR '||') as memberships
        FROM clients c
        LEFT JOIN client_brands b ON c.id = b.client_id
        $whereSql
        GROUP BY c.id
        $orderSql
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($clients),
        'clients' => $clients
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
