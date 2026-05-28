<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT id, concept, image_link, reference_image_link, post_type FROM month_posts ORDER BY id DESC LIMIT 15");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($posts as $p) {
    $il = trim($p['image_link']);
    $rl = trim($p['reference_image_link']);
    if (!$il && !$rl) continue;
    echo "ID:{$p['id']} [{$p['post_type']}] {$p['concept']}\n";
    if ($il) echo "  image_link: " . substr($il, 0, 200) . "\n";
    if ($rl) echo "  ref_link:   " . substr($rl, 0, 200) . "\n";
}
