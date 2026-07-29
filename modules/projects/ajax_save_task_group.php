<?php
// modules/projects/ajax_save_task_group.php
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

    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
    $project_service_id = isset($_POST['project_service_id']) ? intval($_POST['project_service_id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '#0f172a';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        exit;
    }

    if ($group_id > 0) {
        // Update
        $stmt = $db->prepare("UPDATE task_groups SET name = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $color, $group_id]);
        $message = 'Grupo actualizado';
    } else {
        // Insert
        if (!$project_service_id) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID de la tarea padre']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO task_groups (project_service_id, name, color) VALUES (?, ?, ?)");
        $stmt->execute([$project_service_id, $name, $color]);
        $group_id = $db->lastInsertId();
        $message = 'Grupo creado';
    }

    echo json_encode([
        'success' => true, 
        'message' => $message, 
        'group' => [
            'id' => $group_id,
            'name' => $name
        ]
    ]);
} catch (Exception $e) {
    error_log("Error guardando grupo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar el grupo']);
}
?>
