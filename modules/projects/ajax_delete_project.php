<?php
header('Content-Type: application/json');

// 1. Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

// 2. Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 3. Validate required field
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de proyecto inválido.']);
    exit;
}

try {
    // 4. Fetch the project to check for a custom logo file
    $stmt = $db->prepare("SELECT logo FROM module_projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado.']);
        exit;
    }

    // 5. Delete logo file from disk if it exists (only custom uploads, not brand logos)
    if (!empty($project['logo']) && strpos($project['logo'], 'uploads/projects/') === 0) {
        $logoPath = __DIR__ . '/../../' . $project['logo'];
        if (file_exists($logoPath)) {
            unlink($logoPath);
        }
    }

    // 6. Delete the record
    $stmt = $db->prepare("DELETE FROM module_projects WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Proyecto eliminado correctamente.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el proyecto: ' . $e->getMessage()]);
}
