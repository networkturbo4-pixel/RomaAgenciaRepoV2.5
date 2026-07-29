<?php
// modules/admin/ajax_payment_methods.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC");
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'methods' => $methods]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'add';

        if ($action === 'add') {
            $label = trim($input['label'] ?? '');
            $code = trim($input['code'] ?? '');
            $image_url = trim($input['image_url'] ?? '');
            
            if (empty($label) || empty($code)) {
                echo json_encode(['success' => false, 'error' => 'Nombre y código son obligatorios']);
                exit;
            }

            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM payment_methods")->fetchColumn();
            $stmt = $db->prepare("INSERT INTO payment_methods (label, code, image_url, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$label, $code, $image_url ?: null, $maxOrder]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        }
        elseif ($action === 'update') {
            $id = intval($input['id'] ?? 0);
            $label = trim($input['label'] ?? '');
            $code = trim($input['code'] ?? '');
            $image_url = trim($input['image_url'] ?? '');
            
            $stmt = $db->prepare("UPDATE payment_methods SET label = ?, code = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$label, $code, $image_url ?: null, $id]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'delete') {
            $id = intval($input['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'reorder') {
            $order = $input['order'] ?? [];
            $stmt = $db->prepare("UPDATE payment_methods SET sort_order = ? WHERE id = ?");
            foreach ($order as $idx => $id) {
                $stmt->execute([$idx + 1, intval($id)]);
            }
            echo json_encode(['success' => true]);
        }
        else {
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
