<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS month_posts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    month_id INT(11) NOT NULL,
    post_date DATE NOT NULL,
    concept VARCHAR(255) NOT NULL,
    copy_text TEXT NULL,
    platform VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'Borrador',
    image_link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (month_id) REFERENCES project_months(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "Tabla month_posts creada con éxito.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
