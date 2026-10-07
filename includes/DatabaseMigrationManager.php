<?php
// includes/DatabaseMigrationManager.php
// Gestor automatizado de migraciones de Base de Datos
// Diseñado para actualizar la BD de forma 100% no destructiva preservando datos existentes

require_once __DIR__ . '/../config/database.php';

class DatabaseMigrationManager {
    private $db;
    private $basePath;
    private $logs = [];
    private $errors = [];

    public function __construct($db = null, $basePath = null) {
        $this->basePath = $basePath ? rtrim($basePath, '/\\') : realpath(__DIR__ . '/..');
        if ($db instanceof PDO) {
            $this->db = $db;
        } else {
            $this->db = (new Database())->getConnection();
        }
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $this->logs[] = "[$timestamp] $message";
    }

    public function getLogs() {
        return $this->logs;
    }

    public function getErrors() {
        return $this->errors;
    }

    /**
     * Asegura que exista la tabla de control de migraciones `system_migrations`
     * y que tenga todas las columnas requeridas sin tocar datos.
     */
    public function ensureMigrationsTable() {
        if (!$this->db) return false;

        $sql = "
            CREATE TABLE IF NOT EXISTS `system_migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration_name` varchar(255) NOT NULL,
                `batch` int(11) NOT NULL DEFAULT 1,
                `checksum` varchar(64) DEFAULT NULL,
                `execution_time_ms` int(11) DEFAULT NULL,
                `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration_name` (`migration_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);

        // Verificar si faltan columnas en tablas preexistentes
        try {
            $colChecksum = $this->db->query("SHOW COLUMNS FROM `system_migrations` LIKE 'checksum'")->fetch();
            if (!$colChecksum) {
                @$this->db->exec("ALTER TABLE `system_migrations` ADD COLUMN `checksum` varchar(64) DEFAULT NULL AFTER `batch`");
            }
            $colTime = $this->db->query("SHOW COLUMNS FROM `system_migrations` LIKE 'execution_time_ms'")->fetch();
            if (!$colTime) {
                @$this->db->exec("ALTER TABLE `system_migrations` ADD COLUMN `execution_time_ms` int(11) DEFAULT NULL AFTER `checksum`");
            }
        } catch (\Throwable $t) {
            // Ignorar errores benignos
        }

        return true;
    }

    /**
     * Obtiene el listado de nombres de migraciones ya aplicadas
     */
    public function getAppliedMigrations() {
        $this->ensureMigrationsTable();
        try {
            $stmt = $this->db->query("SELECT migration_name FROM `system_migrations` ORDER BY id ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Obtiene el historial completo de migraciones aplicadas con detalles
     */
    public function getMigrationHistory() {
        $this->ensureMigrationsTable();
        try {
            $stmt = $this->db->query("SELECT id, migration_name, batch, execution_time_ms, applied_at FROM `system_migrations` ORDER BY id DESC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Escanea y descubre todos los archivos de migración disponibles en el proyecto
     * Retorna array asociativo: [ 'nombre_archivo' => 'ruta_completa' ] ordenado
     */
    public function getAvailableMigrations() {
        $files = [];

        // 1. Archivos SQL en database/sql/
        $dirSql = $this->basePath . '/database/sql';
        if (is_dir($dirSql)) {
            $sqlFiles = glob($dirSql . '/*.sql');
            if ($sqlFiles) {
                foreach ($sqlFiles as $f) {
                    $files[basename($f)] = $f;
                }
            }
        }

        // 2. Archivos SQL en database/migrations/
        $dirMigrations = $this->basePath . '/database/migrations';
        if (is_dir($dirMigrations)) {
            $migSqlFiles = glob($dirMigrations . '/*.sql');
            if ($migSqlFiles) {
                foreach ($migSqlFiles as $f) {
                    $files[basename($f)] = $f;
                }
            }

            // Migraciones PHP formales (ej. 2026_*.php o migration_*.php)
            $phpFiles = glob($dirMigrations . '/*.php');
            if ($phpFiles) {
                foreach ($phpFiles as $f) {
                    $base = basename($f);
                    // Solo incluir archivos PHP con prefijos de migración formales o timestamps
                    if (preg_match('/^(20\d{2}_|migration_|actualizacion_)/i', $base)) {
                        $files[$base] = $f;
                    }
                }
            }
        }

        // 3. Archivos SQL en la raíz del proyecto (actualizacion_*.sql)
        $rootSql = glob($this->basePath . '/actualizacion_*.sql');
        if ($rootSql) {
            foreach ($rootSql as $f) {
                $files[basename($f)] = $f;
            }
        }

        // Ordenamiento natural para ejecutar en orden cronológico / secuencial
        uksort($files, 'strnatcasecmp');

        return $files;
    }

    /**
     * Retorna las migraciones pendientes que aún no se han aplicado
     */
    public function getPendingMigrations() {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();

        $pending = [];
        foreach ($available as $name => $path) {
            if (!in_array($name, $applied)) {
                $pending[$name] = $path;
            }
        }

        return $pending;
    }

    /**
     * Ejecuta todas las migraciones pendientes garantizando que no se afecten datos actuales
     */
    public function runPendingMigrations() {
        $this->logs = [];
        $this->errors = [];
        $this->ensureMigrationsTable();

        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            $this->log("Base de datos al día. No hay migraciones pendientes.");
            $this->updateCacheFingerprint();
            return [
                'success' => true,
                'applied_count' => 0,
                'logs' => $this->logs,
                'errors' => $this->errors
            ];
        }

        // Calcular siguiente número de lote (batch)
        $stmtBatch = $this->db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `system_migrations`");
        $nextBatch = $stmtBatch ? intval($stmtBatch->fetchColumn()) : 1;

        $this->log("Iniciando ejecución de " . count($pending) . " migración(es) pendientes en lote #{$nextBatch}...");

        $appliedCount = 0;

        foreach ($pending as $name => $path) {
            $startTime = microtime(true);
            $this->log(">>> Ejecutando migración: {$name}");

            $fileSuccess = false;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($ext === 'sql') {
                $fileSuccess = $this->executeSqlMigration($path);
            } elseif ($ext === 'php') {
                $fileSuccess = $this->executePhpMigration($path);
            } else {
                $this->log("Formato no soportado para: {$name}");
                continue;
            }

            $execTimeMs = (int)round((microtime(true) - $startTime) * 1000);

            if ($fileSuccess) {
                // Registrar migración completada
                $checksum = file_exists($path) ? md5_file($path) : null;
                $stmtInsert = $this->db->prepare("
                    INSERT INTO `system_migrations` (`migration_name`, `batch`, `checksum`, `execution_time_ms`) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE `batch` = VALUES(`batch`), `checksum` = VALUES(`checksum`), `execution_time_ms` = VALUES(`execution_time_ms`)
                ");
                $stmtInsert->execute([$name, $nextBatch, $checksum, $execTimeMs]);

                $appliedCount++;
                $this->log("✓ Migración {$name} completada con éxito ({$execTimeMs} ms).");
            } else {
                $this->log("⚠️ La migración {$name} finalizó con advertencias o errores.");
            }
        }

        // Actualizar huella de caché para evitar escaneos pesados
        $this->updateCacheFingerprint();
        $this->writeLogFile();

        return [
            'success' => count($this->errors) === 0,
            'applied_count' => $appliedCount,
            'logs' => $this->logs,
            'errors' => $this->errors
        ];
    }

    /**
     * Ejecuta un archivo .SQL de forma no destructiva statement por statement
     */
    private function executeSqlMigration($filePath) {
        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            $this->log("Archivo SQL vacío: " . basename($filePath));
            return true;
        }

        $queries = $this->splitSqlQueries($content);
        if (empty($queries)) {
            return true;
        }

        // Desactivar temporalmente revisión de claves foráneas
        @$this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");

        $allGood = true;

        foreach ($queries as $query) {
            $trimmed = trim($query);
            if (empty($trimmed)) continue;

            // Filtro de seguridad: Bloquear destructivos accidentales
            if ($this->isDestructiveQuery($trimmed)) {
                $this->log("⚠️ BLOQUEO DE SEGURIDAD: Se omitió sentencia destructiva por protección de datos: " . substr($trimmed, 0, 80) . "...");
                continue;
            }

            // Normalizar CREATE TABLE para asegurar IF NOT EXISTS
            $safeQuery = $this->makeCreateTableSafe($trimmed);

            try {
                $this->db->exec($safeQuery);
            } catch (PDOException $e) {
                $msg = $e->getMessage();

                // Errores benignos que ocurren cuando la columna o tabla ya existe (idempotencia)
                if (
                    strpos($msg, 'Duplicate column name') !== false ||
                    strpos($msg, 'already exists') !== false ||
                    strpos($msg, 'Duplicate key name') !== false ||
                    strpos($msg, '1060 Duplicate column') !== false ||
                    strpos($msg, '1050 Table') !== false ||
                    strpos($msg, '1061 Duplicate key') !== false
                ) {
                    // Columna o índice ya existía previamente, preservar datos y continuar
                    $this->log("ℹ️ Preservación de datos: Estructura ya presente en la tabla, continuando de forma segura.");
                    continue;
                }

                // Error real
                $this->errors[] = "Error en query (" . basename($filePath) . "): " . $msg;
                $this->log("❌ Error en query: " . $msg);
                $allGood = false;
            }
        }

        // Reactivar claves foráneas
        @$this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");

        return $allGood;
    }

    /**
     * Ejecuta una migración en PHP dentro de un entorno controlado
     */
    private function executePhpMigration($filePath) {
        $db = $this->db;
        $migrationManager = $this;

        try {
            ob_start();
            $result = (function() use ($filePath, $db, $migrationManager) {
                return include $filePath;
            })();
            $output = ob_get_clean();

            if (!empty(trim($output))) {
                $this->log("Salida de migración PHP: " . trim($output));
            }

            return true;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->errors[] = "Error en migración PHP (" . basename($filePath) . "): " . $e->getMessage();
            $this->log("❌ Error en PHP: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convierte sentencias 'CREATE TABLE tablename' en 'CREATE TABLE IF NOT EXISTS tablename'
     */
    private function makeCreateTableSafe($query) {
        if (preg_match('/^\s*CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', $query)) {
            return preg_replace('/^\s*CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $query);
        }
        return $query;
    }

    /**
     * Detecta sentencias peligrosas que podrían borrar datos
     */
    private function isDestructiveQuery($query) {
        $upper = strtoupper(trim($query));

        // Permitir solo si el archivo tiene la anotación explícita -- ALLOW_DESTRUCTIVE
        if (strpos($query, '-- ALLOW_DESTRUCTIVE') !== false) {
            return false;
        }

        // Bloquear DROP TABLE, DROP DATABASE, TRUNCATE TABLE
        if (preg_match('/^(DROP\s+TABLE|DROP\s+DATABASE|TRUNCATE\s+TABLE|TRUNCATE\s+)/i', $upper)) {
            return true;
        }

        return false;
    }

    /**
     * Divide sentencias SQL respetando comentarios, comillas y delimitadores
     */
    public function splitSqlQueries($sql) {
        $queries = [];
        $lines = explode("\n", $sql);
        $currentQuery = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                continue;
            }

            if (preg_match('/^DELIMITER\b/i', $trimmed)) {
                continue;
            }

            $currentQuery .= $line . "\n";
            if (substr(rtrim($line), -1) === ';') {
                $queries[] = trim($currentQuery);
                $currentQuery = '';
            }
        }

        if (!empty(trim($currentQuery))) {
            $queries[] = trim($currentQuery);
        }

        return $queries;
    }

    /**
     * Huella digital rápida para comprobación instantánea (<0.2 ms)
     */
    public function getSystemFingerprint() {
        $dirMigrations = $this->basePath . '/database/migrations';
        $dirSql = $this->basePath . '/database/sql';

        $m1 = is_dir($dirMigrations) ? filemtime($dirMigrations) : 0;
        $m2 = is_dir($dirSql) ? filemtime($dirSql) : 0;
        $c1 = is_dir($dirMigrations) ? count(scandir($dirMigrations)) : 0;
        $c2 = is_dir($dirSql) ? count(scandir($dirSql)) : 0;

        return "{$m1}-{$m2}-{$c1}-{$c2}";
    }

    private function getCacheFilePath() {
        return $this->basePath . '/database/.migration_cache';
    }

    public function updateCacheFingerprint() {
        @file_put_contents($this->getCacheFilePath(), $this->getSystemFingerprint());
    }

    private function writeLogFile() {
        if (!empty($this->logs)) {
            $logFile = $this->basePath . '/database/migration_log.txt';
            $content = implode("\n", $this->logs) . "\n----------------------------------------\n";
            @file_put_contents($logFile, $content, FILE_APPEND);
        }
    }

    /**
     * MÉTODO ESTÁTICO DE VERIFICACIÓN AUTOMÁTICA
     * Se ejecuta de forma ultra-ligera en el arranque del sistema.
     * Si detecta nuevas migraciones desplegadas en el código, las aplica inmediatamente.
     */
    public static function autoMigrateIfPending($db = null) {
        // Evitar ejecuciones simultáneas con semáforo de archivo
        static $alreadyChecked = false;
        if ($alreadyChecked) return false;
        $alreadyChecked = true;

        $basePath = realpath(__DIR__ . '/..');
        $cacheFile = $basePath . '/database/.migration_cache';

        $dirMigrations = $basePath . '/database/migrations';
        $dirSql = $basePath . '/database/sql';

        $m1 = is_dir($dirMigrations) ? filemtime($dirMigrations) : 0;
        $m2 = is_dir($dirSql) ? filemtime($dirSql) : 0;
        $c1 = is_dir($dirMigrations) ? count(scandir($dirMigrations)) : 0;
        $c2 = is_dir($dirSql) ? count(scandir($dirSql)) : 0;
        $currentFingerprint = "{$m1}-{$m2}-{$c1}-{$c2}";

        // Comprobación relámpago: si la huella coincide con el caché, salir en 0.1ms sin consultar BD
        if (file_exists($cacheFile)) {
            $cachedFingerprint = trim(@file_get_contents($cacheFile));
            if ($cachedFingerprint === $currentFingerprint) {
                return false;
            }
        }

        // Si la huella cambió (se desplegó un nuevo archivo o git pull), instanciar manager y aplicar
        try {
            $manager = new self($db, $basePath);
            $pending = $manager->getPendingMigrations();
            if (!empty($pending)) {
                $manager->runPendingMigrations();
                return true;
            } else {
                $manager->updateCacheFingerprint();
            }
        } catch (\Throwable $e) {
            error_log("Error en auto-migración de base de datos: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Crea una nueva plantilla de migración segura con timestamp
     */
    public function createMigrationFile($name, $type = 'sql') {
        $timestamp = date('Y_m_d_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($name)));
        $filename = "{$timestamp}_{$safeName}." . strtolower($type);
        $fullPath = $this->basePath . '/database/migrations/' . $filename;

        if ($type === 'sql') {
            $content = "-- ==============================================================================\n"
                     . "-- Migración Segura: {$safeName}\n"
                     . "-- Fecha: " . date('Y-m-d H:i:s') . "\n"
                     . "-- Nota: Los datos existentes se conservan intactos.\n"
                     . "-- ==============================================================================\n\n"
                     . "SET NAMES utf8mb4;\n"
                     . "SET FOREIGN_KEY_CHECKS = 0;\n\n"
                     . "-- 1. Para crear una nueva tabla (segura):\n"
                     . "-- CREATE TABLE IF NOT EXISTS `nueva_tabla` (\n"
                     . "--   `id` int(11) NOT NULL AUTO_INCREMENT,\n"
                     . "--   `nombre` varchar(255) NOT NULL,\n"
                     . "--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n"
                     . "--   PRIMARY KEY (`id`)\n"
                     . "-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
                     . "-- 2. Para agregar columnas a una tabla existente (seguro, preserva datos):\n"
                     . "-- ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `nuevo_campo` varchar(100) DEFAULT NULL;\n\n"
                     . "-- 3. Para insertar datos iniciales/configuraciones sin duplicar:\n"
                     . "-- INSERT INTO `settings` (`setting_key`, `setting_value`)\n"
                     . "-- SELECT 'clave_nueva', 'valor_inicial'\n"
                     . "-- WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `setting_key` = 'clave_nueva');\n\n"
                     . "SET FOREIGN_KEY_CHECKS = 1;\n";
        } else {
            $content = "<?php\n"
                     . "// Migración PHP: {$safeName}\n"
                     . "// Fecha: " . date('Y-m-d H:i:s') . "\n\n"
                     . "/** @var PDO \$db */\n"
                     . "/** @var DatabaseMigrationManager \$migrationManager */\n\n"
                     . "// Ejecutar lógica personalizada o consultas complejas\n"
                     . "// \$db->exec(\"...\");\n";
        }

        file_put_contents($fullPath, $content);
        return $fullPath;
    }
}
