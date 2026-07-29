<?php
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Añadir drive_folder_id a chat_channels
    $db->exec("ALTER TABLE chat_channels ADD COLUMN drive_folder_id VARCHAR(255) NULL AFTER secret_password;");
    echo "Agregado drive_folder_id a chat_channels.\n";
} catch (Exception $e) {
    echo "Error (quizás ya existe): " . $e->getMessage() . "\n";
}

try {
    // 2. Modificar enum message_type en chat_messages
    $db->exec("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','card','file','poll','task','audio','image','video') DEFAULT 'text';");
    echo "Modificado message_type en chat_messages.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    // 3. Crear tabla chat_message_reads
    $sql = "
    CREATE TABLE IF NOT EXISTS chat_message_reads (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        message_id INT(11) NOT NULL,
        user_id INT(11) NULL, /* Puede ser NULL si es un invitado */
        guest_name VARCHAR(100) NULL,
        status ENUM('delivered', 'read') NOT NULL DEFAULT 'delivered',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY msg_user_status (message_id, user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);
    echo "Tabla chat_message_reads creada.\n";
} catch (Exception $e) {
    echo "Error al crear chat_message_reads: " . $e->getMessage() . "\n";
}

echo "Base de datos actualizada.\n";
?>
