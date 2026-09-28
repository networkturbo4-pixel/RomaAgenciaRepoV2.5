<?php
// database/migrations/create_brand_guidelines_tables.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = (new Database())->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `brand_guidelines` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_id` INT NULL,
        `brand_name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(100) NOT NULL UNIQUE,
        `tagline` VARCHAR(255) NULL,
        `description` TEXT NULL,
        `mission` TEXT NULL,
        `vision` TEXT NULL,
        `values_json` LONGTEXT NULL,
        `tone_of_voice` TEXT NULL,
        `logo_primary` VARCHAR(500) NULL,
        `logo_primary_dark` VARCHAR(500) NULL,
        `logo_symbol` VARCHAR(500) NULL,
        `logo_variations_json` LONGTEXT NULL,
        `icons_json` LONGTEXT NULL,
        `safe_zone_rules` TEXT NULL,
        `min_size_rules` TEXT NULL,
        `incorrect_uses_json` LONGTEXT NULL,
        `colors_json` LONGTEXT NULL,
        `fonts_json` LONGTEXT NULL,
        `applications_json` LONGTEXT NULL,
        `allow_asset_download` TINYINT(1) DEFAULT 1,
        `is_public` TINYINT(1) DEFAULT 1,
        `access_password` VARCHAR(255) NULL,
        `views_count` INT DEFAULT 0,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_slug` (`slug`),
        INDEX `idx_client` (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);

    // Register module in role_permissions for Administrator and Designer
    $rolesStmt = $db->query("SELECT id FROM roles WHERE id = 1 OR name IN ('Administrador', 'Diseñador')");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    $checkPerm = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_name = 'brand_guidelines'");
    $insertPerm = $db->prepare("INSERT INTO role_permissions (role_id, module_name) VALUES (?, 'brand_guidelines')");

    foreach ($roles as $r) {
        $checkPerm->execute([$r['id']]);
        if ($checkPerm->fetchColumn() == 0) {
            $insertPerm->execute([$r['id']]);
        }
    }

    echo "Migration completed successfully!";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}
