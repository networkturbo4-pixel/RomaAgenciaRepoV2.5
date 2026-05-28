<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

// Check FK constraints
echo "=== FK on quote_items ===\n";
$result = $db->query("SHOW CREATE TABLE quote_items")->fetch(PDO::FETCH_ASSOC);
echo $result['Create Table'] . "\n\n";

echo "=== FK on quote_gantt_tasks ===\n";
$result = $db->query("SHOW CREATE TABLE quote_gantt_tasks")->fetch(PDO::FETCH_ASSOC);
echo $result['Create Table'] . "\n\n";

echo "=== FK on quotes ===\n";
$result = $db->query("SHOW CREATE TABLE quotes")->fetch(PDO::FETCH_ASSOC);
echo $result['Create Table'] . "\n\n";

// Test delete manually
echo "=== Testing delete of ID 3 ===\n";
$id = 3;
$stmt = $db->prepare("SELECT id FROM quotes WHERE id = ?");
$stmt->execute([$id]);
$exists = $stmt->fetch();
echo "Quote $id exists: " . ($exists ? 'YES' : 'NO') . "\n";

// Test the actual AJAX call would work - simulate
$stmt = $db->prepare("DELETE FROM quote_gantt_tasks WHERE quote_id = ?");
$stmt->execute([$id]);
echo "Gantt tasks deleted: " . $stmt->rowCount() . "\n";

$stmt = $db->prepare("DELETE FROM quote_items WHERE quote_id = ?");
$stmt->execute([$id]);
echo "Items deleted: " . $stmt->rowCount() . "\n";

$stmt = $db->prepare("DELETE FROM quotes WHERE id = ?");
$stmt->execute([$id]);
echo "Quote deleted rowCount: " . $stmt->rowCount() . "\n";
