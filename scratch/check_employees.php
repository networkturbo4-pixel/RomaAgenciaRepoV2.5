<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "=== EMPLOYEES TABLE ===\n";
$stmt = $db->query('DESCRIBE employees');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== EMPLOYEE DATA ===\n";
$stmt = $db->query('SELECT id, name, salary, hire_date, status FROM employees');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "ID:{$r['id']} | {$r['name']} | S/{$r['salary']} | {$r['hire_date']} | {$r['status']}\n";
}
echo "Total: " . count($rows) . "\n";
