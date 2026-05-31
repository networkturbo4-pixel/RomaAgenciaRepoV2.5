<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "=== PAYMENT_NOTES TABLE ===\n";
$stmt = $db->query('DESCRIBE payment_notes');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== SAMPLE DATA ===\n";
$stmt = $db->query('SELECT id, note_code, client_name, company_name, total, status, created_at FROM payment_notes ORDER BY id DESC LIMIT 10');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "#{$r['id']} | {$r['note_code']} | {$r['client_name']} | {$r['company_name']} | S/{$r['total']} | {$r['status']} | {$r['created_at']}\n";
}
