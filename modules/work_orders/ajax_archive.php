<?php
// modules/work_orders/ajax_archive.php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de orden de servicio no válido.']);
        exit;
    }

    try {
        $db = (new Database())->getConnection();
        
        // Check current status
        $stmtCheck = $db->prepare("SELECT is_archived FROM work_orders WHERE id = ?");
        $stmtCheck->execute([$id]);
        $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $newStatus = $order['is_archived'] == 1 ? 0 : 1;
            $stmt = $db->prepare("UPDATE work_orders SET is_archived = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            
            echo json_encode(['success' => true, 'is_archived' => $newStatus]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró la orden de servicio.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
}
