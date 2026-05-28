<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico de Producción 2</h2>";

// 1. Test database connection
echo "<h3>1. Conexión a BD</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<p style='color:green;'>✅ Conexión OK</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Create post_revisions table if missing
echo "<h3>2. Verificar tabla post_revisions</h3>";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS post_revisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            image_link TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "<p style='color:green;'>✅ Tabla post_revisions creada o ya existente.</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Error al crear post_revisions: " . $e->getMessage() . "</p>";
}

// 3. Test loading the module file
echo "<h3>3. Test de sintaxis del módulo</h3>";
$output = [];
$returnCode = 0;
exec("php -l " . escapeshellarg(__DIR__ . '/modules/month_board/index.php') . " 2>&1", $output, $returnCode);
echo "<p>" . implode("<br>", $output) . "</p>";

echo "<hr><p><strong>Diagnóstico completo.</strong> Si todo está verde, intenta cargar la URL de nuevo.</p>";
?>
