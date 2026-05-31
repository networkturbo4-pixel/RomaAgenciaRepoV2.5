<?php
// clear_finance_seed.php - Limpia datos de prueba de las tablas financieras
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $db->exec("DELETE FROM finance_expenses");
    echo "✅ finance_expenses limpiada.\n";

    $db->exec("DELETE FROM finance_incomes");
    echo "✅ finance_incomes limpiada.\n";

    $db->exec("DELETE FROM finance_monthly_closings");
    echo "✅ finance_monthly_closings limpiada.\n";

    // Reset auto-increment
    $db->exec("ALTER TABLE finance_expenses AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE finance_incomes AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE finance_monthly_closings AUTO_INCREMENT = 1");

    echo "\n🎉 Todos los datos de prueba eliminados.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
