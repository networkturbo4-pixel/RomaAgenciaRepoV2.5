<?php
// modules/clients/ajax_delete_client.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID requerido']);
    exit();
}

try {
    // client_brands are deleted via CASCADE
    $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
