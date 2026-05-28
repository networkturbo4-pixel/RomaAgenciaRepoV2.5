<?php
// modules/client_portal/ajax.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle_status':
            $client_id = $_POST['client_id'] ?? '';
            $status = $_POST['status'] ?? 0;
            
            if ($client_id) {
                $stmt = $db->prepare("UPDATE clients SET portal_enabled = ? WHERE id = ?");
                $stmt->execute([$status, $client_id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID del cliente']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
