<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS linktrees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            title VARCHAR(255) NOT NULL,
            bio TEXT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            profile_image VARCHAR(255) NULL,
            theme_config TEXT NULL,
            views INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table linktrees created.\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS linktree_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            linktree_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            icon VARCHAR(50) NULL,
            sort_order INT DEFAULT 0,
            is_social TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (linktree_id) REFERENCES linktrees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table linktree_links created.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
