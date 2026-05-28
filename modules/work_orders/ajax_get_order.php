<?php
// modules/work_orders/ajax_get_order.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM work_orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        echo json_encode(['success' => true, 'data' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
