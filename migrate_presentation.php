<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("ALTER TABLE month_posts ADD COLUMN presenter_notes TEXT NULL");
    echo "Agregado presenter_notes a month_posts\n";
} catch (Exception $e) { echo "presenter_notes: " . $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE month_posts ADD COLUMN reviewed TINYINT(1) DEFAULT 0");
    echo "Agregado reviewed a month_posts\n";
} catch (Exception $e) { echo "reviewed: " . $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE project_months ADD COLUMN agenda_text TEXT NULL");
    echo "Agregado agenda_text a project_months\n";
} catch (Exception $e) { echo "agenda_text: " . $e->getMessage() . "\n"; }

try {
    $db->exec("CREATE TABLE IF NOT EXISTS post_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        post_id INT NOT NULL, 
        image_link TEXT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Creada tabla post_revisions\n";
} catch (Exception $e) { echo "post_revisions: " . $e->getMessage() . "\n"; }

echo "Terminado.";
