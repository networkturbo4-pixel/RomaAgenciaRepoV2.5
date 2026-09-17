<?php
require_once 'config/database.php';
try {
    $db = (new Database())->getConnection();
    
    // Create romita_prepts table
    $sql1 = "CREATE TABLE IF NOT EXISTS romita_prepts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        tone VARCHAR(255) DEFAULT '',
        audience TEXT,
        rules TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($sql1);
    
    // Create romita_prept_content table (to track generated posts/content for a prept)
    $sql2 = "CREATE TABLE IF NOT EXISTS romita_prept_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prept_id INT NOT NULL,
        topic VARCHAR(255) NOT NULL,
        content_summary TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (prept_id),
        FOREIGN KEY (prept_id) REFERENCES romita_prepts(id) ON DELETE CASCADE
    )";
    $db->exec($sql2);
    
    echo "Prept tables created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
