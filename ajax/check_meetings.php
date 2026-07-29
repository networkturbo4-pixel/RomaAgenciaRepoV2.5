<?php
// ajax/check_meetings.php
require_once '../config/database.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

// Buscar reuniones en los próximos 5 minutos (solo para el día de hoy)
$stmt = $db->prepare("SELECT r.*, b.name as brand_name 
                      FROM reuniones r 
                      LEFT JOIN client_brands b ON r.brand_id = b.id 
                      WHERE r.estado = 'Programada' 
                      AND r.fecha_hora >= NOW() 
                      AND r.fecha_hora <= DATE_ADD(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'meetings' => $meetings]);
