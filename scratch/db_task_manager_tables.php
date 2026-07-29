<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. tm_tasks
    $db->exec("CREATE TABLE IF NOT EXISTS tm_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('new', 'pending', 'overdue', 'completed', 'approved') DEFAULT 'new',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        start_date DATETIME NULL,
        due_date DATETIME NULL,
        assigned_users JSON NULL,
        assigned_roles JSON NULL,
        tags JSON NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Table tm_tasks created.\n";

    // 2. tm_subtasks
    $db->exec("CREATE TABLE IF NOT EXISTS tm_subtasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tm_tasks(id) ON DELETE CASCADE
    )");
    echo "Table tm_subtasks created.\n";

    // 3. tm_recurring_templates
    $db->exec("CREATE TABLE IF NOT EXISTS tm_recurring_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        recurrence_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
        recurrence_day VARCHAR(50) NULL,
        assigned_users JSON NULL,
        assigned_roles JSON NULL,
        tags JSON NULL,
        created_by INT NOT NULL,
        last_generated DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table tm_recurring_templates created.\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
