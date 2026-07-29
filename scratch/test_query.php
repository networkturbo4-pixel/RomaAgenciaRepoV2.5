<?php
require 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
try {
    $query = "SELECT u.id, u.name, u.email, a.entrada, a.inicio_refrigerio, a.fin_refrigerio, a.salida FROM users u LEFT JOIN asistencias a ON u.id = a.user_id AND a.fecha = CURDATE() WHERE u.status = 'active' OR u.status = 'Activo' ORDER BY u.name ASC";
    $stmt = $db->query($query);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
