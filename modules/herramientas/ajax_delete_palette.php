<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
$db = (new Database())->getConnection();

try {
    // Read and validate input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'error' => 'Falta el ID de la paleta']);
        exit;
    }

    $paletteId = (int)$input['id'];

    // Verify the palette belongs to the current user before deleting
    $stmt = $db->prepare("SELECT id FROM tool_palettes WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id'      => $paletteId,
        ':user_id' => $_SESSION['user_id']
    ]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Paleta no encontrada o no autorizado']);
        exit;
    }

    // Delete the palette
    $stmt = $db->prepare("DELETE FROM tool_palettes WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id'      => $paletteId,
        ':user_id' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
