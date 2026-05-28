<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'Proyecto no proporcionado']);
        exit();
    }

    $stmt = $db->prepare("SELECT id, month, year, start_date FROM project_months WHERE project_id = ? ORDER BY year DESC, month DESC");
    $stmt->execute([$project_id]);
    $months = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $result = [];
    foreach ($months as $m) {
        $result[] = [
            'id' => $m['id'],
            'name' => $monthNames[$m['month']] . ' ' . $m['year'],
            'start_date' => $m['start_date']
        ];
    }

    echo json_encode(['success' => true, 'months' => $result]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
