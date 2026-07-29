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
    // Auto-create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS tool_palettes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        primary_color VARCHAR(7) NOT NULL,
        secondary_color VARCHAR(7) DEFAULT NULL,
        harmony_mode VARCHAR(30) DEFAULT 'auto',
        palette_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_palettes (user_id)
    )");

    // Try to add secondary_color if table already existed without it
    try {
        $db->exec("ALTER TABLE tool_palettes ADD COLUMN secondary_color VARCHAR(7) DEFAULT NULL AFTER primary_color");
    } catch (Exception $e) {}

    // Read and validate input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['name']) || empty($input['primary_color'])) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos (name, primary_color)']);
        exit;
    }

    $name = trim($input['name']);
    $primaryColor = trim($input['primary_color']);
    $secondaryColor = !empty($input['secondary_color']) ? trim($input['secondary_color']) : null;
    $harmonyMode = isset($input['harmony_mode']) ? trim($input['harmony_mode']) : 'auto';
    $paletteData = isset($input['palette_data']) ? $input['palette_data'] : null;

    // Validate color format
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
        echo json_encode(['success' => false, 'error' => 'Formato de color inválido']);
        exit;
    }
    if ($secondaryColor !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $secondaryColor)) {
        echo json_encode(['success' => false, 'error' => 'Formato de color secundario inválido']);
        exit;
    }

    // Validate name length
    if (strlen($name) > 100) {
        echo json_encode(['success' => false, 'error' => 'El nombre no puede exceder 100 caracteres']);
        exit;
    }

    // Ensure palette_data is a valid JSON string if provided
    if ($paletteData !== null && !is_string($paletteData)) {
        $paletteData = json_encode($paletteData);
    }

    // Insert new palette
    $stmt = $db->prepare("INSERT INTO tool_palettes (user_id, name, primary_color, secondary_color, harmony_mode, palette_data) VALUES (:user_id, :name, :primary_color, :secondary_color, :harmony_mode, :palette_data)");
    $stmt->execute([
        ':user_id'         => $_SESSION['user_id'],
        ':name'            => $name,
        ':primary_color'   => $primaryColor,
        ':secondary_color' => $secondaryColor,
        ':harmony_mode'    => $harmonyMode,
        ':palette_data'    => $paletteData
    ]);

    echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
