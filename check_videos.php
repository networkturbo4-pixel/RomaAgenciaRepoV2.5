<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$r = $db->query("SELECT id, concept, image_link, reference_image_link, post_type FROM month_posts ORDER BY id DESC LIMIT 15");
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $il = trim($p['image_link'] ?? '');
    $rl = trim($p['reference_image_link'] ?? '');
    if (!$il && !$rl) continue;
    echo "ID:{$p['id']} [{$p['post_type']}] {$p['concept']}\n";
    if ($il) echo "  img: " . substr($il, 0, 200) . "\n";
    if ($rl) echo "  ref: " . substr($rl, 0, 200) . "\n";
}
