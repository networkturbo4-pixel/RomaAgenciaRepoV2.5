<?php
// database/migrate.php
// Ejecutor de migraciones de base de datos desde la línea de comandos (CLI)

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la terminal (CLI).\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/DatabaseMigrationManager.php';

$db = (new Database())->getConnection();
if (!$db) {
    fwrite(STDERR, "❌ ERROR: No se pudo conectar a la base de datos MySQL.\n");
    exit(1);
}

$manager = new DatabaseMigrationManager($db);
$args = array_slice($argv, 1);

// Flag: --create=nombre
foreach ($args as $arg) {
    if (strpos($arg, '--create=') === 0 || strpos($arg, '--make=') === 0) {
        $name = substr($arg, strpos($arg, '=') + 1);
        if (empty($name)) {
            fwrite(STDERR, "❌ Error: Debe especificar un nombre para la migración. Ej: php database/migrate.php --create=agregar_columna\n");
            exit(1);
        }
        $file = $manager->createMigrationFile($name, 'sql');
        echo "✓ Archivo de migración creado:\n";
        echo "  " . realpath($file) . "\n\n";
        echo "Edite el archivo y luego ejecute: php database/migrate.php\n";
        exit(0);
    }
}

// Flag: --status
if (in_array('--status', $args) || in_array('-s', $args)) {
    echo "=========================================================\n";
    echo " ESTADO DE MIGRACIONES DE BASE DE DATOS\n";
    echo "=========================================================\n";
    
    $applied = $manager->getAppliedMigrations();
    $available = $manager->getAvailableMigrations();
    $pending = $manager->getPendingMigrations();

    echo "Total disponibles: " . count($available) . "\n";
    echo "Total aplicadas:   " . count($applied) . "\n";
    echo "Total pendientes:  " . count($pending) . "\n\n";

    echo "--- Historial de Migraciones ---\n";
    foreach ($available as $name => $path) {
        $isApplied = in_array($name, $applied);
        $statusStr = $isApplied ? "[✓ APLICADA ]" : "[⏳ PENDIENTE]";
        echo " {$statusStr} {$name}\n";
    }
    echo "\n";
    exit(0);
}

// Ejecutar migraciones pendientes
echo "=========================================================\n";
echo " EJECUTOR DE MIGRACIONES AUTOMÁTICAS (NO DESTRUCTIVAS)\n";
echo "=========================================================\n";

$pending = $manager->getPendingMigrations();
if (empty($pending)) {
    echo "✓ La base de datos está al día. No hay migraciones pendientes.\n";
    exit(0);
}

echo "Migraciones pendientes detectadas: " . count($pending) . "\n\n";

$result = $manager->runPendingMigrations();

foreach ($result['logs'] as $log) {
    echo "  {$log}\n";
}

if (!empty($result['errors'])) {
    echo "\n❌ ERRORES DURANTE LA MIGRACIÓN:\n";
    foreach ($result['errors'] as $err) {
        fwrite(STDERR, "  - {$err}\n");
    }
    exit(1);
}

echo "\n✓ Proceso completado exitosamente. {$result['applied_count']} migración(es) aplicada(s) sin afectar datos existentes.\n";
exit(0);
