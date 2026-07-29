<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS user_webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key TEXT NOT NULL,
    sign_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "Tabla user_webauthn_credentials creada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error al crear la tabla: " . $e->getMessage() . "\n";
}
?>
