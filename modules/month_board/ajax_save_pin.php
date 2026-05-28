<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    
    if ($month_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }
    
    // Si pin está vacío, se pone a NULL para desactivar protección
    $pinValue = $pin === '' ? null : $pin;
    
    $stmt = $db->prepare("UPDATE project_months SET pin = ? WHERE id = ?");
    $stmt->execute([$pinValue, $month_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
