<?php
header('Content-Type: application/json');

// 1. Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 2. Validate input
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido.']);
    exit;
}

try {
    // 3. Fetch current status
    $stmt = $db->prepare("SELECT status FROM module_projects WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
        exit;
    }

    $currentStatus = $project['status'];

    // Toggle status
    if ($currentStatus === 'active') {
        $newStatus = 'archived';
    } elseif ($currentStatus === 'archived') {
        $newStatus = 'active';
    } else {
        echo json_encode(['success' => false, 'message' => 'El estado actual del proyecto no permite esta acción']);
        exit;
    }

    // Update status
    $stmt = $db->prepare("UPDATE module_projects SET status = :status WHERE id = :id");
    $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $newStatus === 'archived' ? 'Proyecto archivado correctamente' : 'Proyecto restaurado correctamente',
        'new_status' => $newStatus
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
