<?php
// Quick test for finance APIs
require_once 'config/database.php';
$db = (new Database())->getConnection();

$month = '2026-05';

// Test 1: Closing
$stmt = $db->prepare("SELECT * FROM finance_monthly_closings WHERE period = ?");
$stmt->execute([$month]);
$closing = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Closing for $month: " . json_encode($closing) . "\n";

// Test 2: Incomes
$stmt = $db->prepare("SELECT COUNT(*) FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
$stmt->execute([$month]);
echo "Incomes count: " . $stmt->fetchColumn() . "\n";

// Test 3: Expenses
$stmt = $db->prepare("SELECT COUNT(*) FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
$stmt->execute([$month]);
echo "Expenses count: " . $stmt->fetchColumn() . "\n";

// Test 4: Recurring templates
$stmt = $db->query("SELECT COUNT(*) FROM finance_recurring_expenses");
echo "Recurring templates: " . $stmt->fetchColumn() . "\n";

// Test 5: Simulate the full ajax_get_finances response
echo "\n--- Simulating full response ---\n";
$_GET['month'] = '2026-05';
ob_start();
include 'modules/admin/ajax_get_finances.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
if (!$data['success']) {
    echo "Error: " . ($data['message'] ?? 'unknown') . "\n";
}
echo "Incomes: " . count($data['incomes'] ?? []) . "\n";
echo "Expenses: " . count($data['expenses'] ?? []) . "\n";
echo "History: " . count($data['history'] ?? []) . "\n";
echo "Recurring: " . count($data['recurring_templates'] ?? []) . "\n";
