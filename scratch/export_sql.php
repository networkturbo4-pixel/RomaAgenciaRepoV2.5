<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Get the database name from the PDO connection
$dbName = $db->query('SELECT DATABASE()')->fetchColumn();

$tables = ['finance_monthly_closings', 'finance_incomes', 'finance_expenses', 'finance_recurring_expenses'];
$sql = "-- Archivo de actualización de base de datos para el módulo de Finanzas\n\n";

if ($dbName) {
    $sql .= "USE `$dbName`;\n\n";
}

foreach ($tables as $table) {
    $stmt = $db->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $row['Create Table'] . ";\n\n";
}

file_put_contents('scratch/update_finanzas.sql', $sql);
echo "SQL exportado a scratch/update_finanzas.sql (con USE $dbName y DROP TABLE IF EXISTS)\n";
