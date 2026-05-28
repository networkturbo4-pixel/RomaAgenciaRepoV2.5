<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

echo "=== May 2026 months ===\n";
$r = $db->query("SELECT * FROM project_months WHERE month=5 AND year=2026 ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "\n=== Posts in month 9 ===\n";
$r2 = $db->query("SELECT id, concept, platform, image_link, reference_image_link, post_type FROM month_posts WHERE month_id = 9 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
print_r($r2);
