<?php
// modules/calendar/ajax_get_work_order.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT brand_name, data FROM work_orders WHERE id = ?");
    $stmt->execute([$id]);
    $wo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wo) {
        echo json_encode(['success' => false, 'error' => 'Orden de servicio no encontrada']);
        exit();
    }

    $data = json_decode($wo['data'], true) ?: [];

    // Get Logo
    $stmtBrand = $db->prepare("SELECT logo FROM client_brands WHERE name = ?");
    $stmtBrand->execute([$wo['brand_name']]);
    $brand = $stmtBrand->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'brand_name' => $wo['brand_name'],
        'logo' => $brand['logo'] ?? '',
        'networks' => $data['redes'] ?? '',
        'start_date' => $data['fechaInicio'] ?? ''
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
