<?php
require 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
try {
    $query = "
        SELECT u.id, u.name, u.email, 
               a.entrada, a.inicio_refrigerio, a.fin_refrigerio, a.salida,
               p.estado as estado_permiso, p.motivo as motivo_permiso
        FROM users u
        LEFT JOIN asistencias a ON u.id = a.user_id AND a.fecha = CURDATE()
        LEFT JOIN asistencia_permisos p ON u.id = p.user_id AND DATE(p.created_at) = CURDATE()
        WHERE a.id IS NOT NULL OR p.id IS NOT NULL
        ORDER BY u.name ASC
    ";
    $stmt = $db->query($query);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
