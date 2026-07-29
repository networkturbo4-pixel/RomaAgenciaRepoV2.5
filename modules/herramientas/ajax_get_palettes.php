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

    // Fetch all palettes for the current user
    $stmt = $db->prepare("SELECT id, name, primary_color, secondary_color, harmony_mode, palette_data, created_at FROM tool_palettes WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $palettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'palettes' => $palettes]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
