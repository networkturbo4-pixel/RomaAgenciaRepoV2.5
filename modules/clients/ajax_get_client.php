<?php
// modules/clients/ajax_get_client.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID requerido']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
        exit();
    }

    $stmtBrands = $db->prepare("SELECT id, name, logo, has_membership, services_ids, whatsapp_group FROM client_brands WHERE client_id = ?");
    $stmtBrands->execute([$id]);
    $brands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'client' => $client,
        'brands' => $brands
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
