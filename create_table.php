<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS project_months (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    status VARCHAR(50) DEFAULT 'pendiente',
    folder_link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "Tabla project_months creada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
