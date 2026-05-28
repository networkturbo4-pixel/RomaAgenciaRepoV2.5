<?php
// modules/work_orders/ajax_save_order.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['work_order_id'] ?? '';
    $marca = $_POST['marca'] ?? 'Marca Desconocida';
    $data = $_POST['data'] ?? '';

    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
        exit();
    }

    try {
        if (empty($id)) {
            // Generar correlativo
            $stmt = $db->query("SELECT id FROM work_orders ORDER BY id DESC LIMIT 1");
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextId = $last ? ($last['id'] + 1) : 1;
            $correlativo = 'OS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
            // Generar token único
            $token = bin2hex(random_bytes(16));

            $stmt = $db->prepare("INSERT INTO work_orders (correlativo, brand_name, data, public_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$correlativo, $marca, $data, $token]);
            
            $newId = $db->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Orden creada correctamente.',
                'redirect_url' => "index.php?module=work_orders&action=edit&id=$newId"
            ]);
        } else {
            // Update
            $stmt = $db->prepare("UPDATE work_orders SET brand_name = ?, data = ? WHERE id = ?");
            $stmt->execute([$marca, $data, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Orden actualizada correctamente.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
