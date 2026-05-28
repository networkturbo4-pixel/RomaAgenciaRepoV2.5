<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

echo "=== SETTINGS ===\n";
$rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo $r['setting_key'] . ' => ' . substr($r['setting_value'], 0, 120) . "\n";
}
