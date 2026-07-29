<?php
// modules/projects/ajax_reorder_tasks.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if (empty($type) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    if ($type === 'groups') {
        $stmt = $db->prepare("UPDATE task_groups SET sort_order = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute([$item['sort'], $item['id']]);
        }
    } else if ($type === 'cards') {
        $stmt = $db->prepare("UPDATE task_cards SET sort_order = ?, task_group_id = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute([$item['sort'], $item['group_id'], $item['id']]);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error reordenando: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>
