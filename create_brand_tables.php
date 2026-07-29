<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$queries = [
    "CREATE TABLE IF NOT EXISTS brand_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(30) DEFAULT '#6366f1',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS brand_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        client_name VARCHAR(150) NULL,
        client_avatar VARCHAR(255) NULL,
        status ENUM('Active', 'Pending', 'Completed', 'Archived') DEFAULT 'Active',
        cover_image TEXT NULL,
        secondary_image TEXT NULL,
        budget_range VARCHAR(100) NULL,
        due_date DATE NULL,
        drive_folder_url TEXT NULL,
        drive_folder_id VARCHAR(255) NULL,
        description TEXT NULL,
        messages_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS brand_project_tags (
        project_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (project_id, tag_id),
        FOREIGN KEY (project_id) REFERENCES brand_projects(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES brand_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $query) {
    try {
        $db->exec($query);
        echo "Successfully executed query.\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
?>
