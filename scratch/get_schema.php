<?php
require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();

try {
    // 1. Add columns to tm_tasks if they don't exist
    $cols = $db->query("DESCRIBE tm_tasks")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('frequency', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN frequency ENUM('daily', 'weekly', 'one_time') DEFAULT 'one_time' AFTER priority");
        echo "Added frequency column.\n";
    }
    if (!in_array('area', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN area ENUM('general', 'desarrollo_marca', 'desarrollo_web', 'audiovisual') DEFAULT 'general' AFTER frequency");
        echo "Added area column.\n";
    }
    if (!in_array('project_id', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN project_id INT NULL AFTER area");
        echo "Added project_id column.\n";
    }
    if (!in_array('project_month_id', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN project_month_id INT NULL AFTER project_id");
        echo "Added project_month_id column.\n";
    }
    if (!in_array('brand_project_id', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN brand_project_id INT NULL AFTER project_month_id");
        echo "Added brand_project_id column.\n";
    }
    if (!in_array('is_daily_objective', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN is_daily_objective TINYINT(1) DEFAULT 0 AFTER brand_project_id");
        echo "Added is_daily_objective column.\n";
    }
    if (!in_array('objective_date', $cols)) {
        $db->exec("ALTER TABLE tm_tasks ADD COLUMN objective_date DATE NULL AFTER is_daily_objective");
        echo "Added objective_date column.\n";
    }

    // 2. Create tm_daily_evaluations table
    $db->exec("CREATE TABLE IF NOT EXISTS tm_daily_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        evaluation_date DATE NOT NULL,
        total_objectives INT DEFAULT 0,
        completed_objectives INT DEFAULT 0,
        compliance_percentage DECIMAL(5,2) DEFAULT 0.00,
        score INT NULL,
        performance_level ENUM('pending', 'excellent', 'good', 'average', 'poor') DEFAULT 'pending',
        evaluation_notes TEXT NULL,
        evaluated_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_eval_date (user_id, evaluation_date)
    )");
    echo "Table tm_daily_evaluations ensured.\n";

    echo "Migration completed successfully!\n";
} catch(Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}







