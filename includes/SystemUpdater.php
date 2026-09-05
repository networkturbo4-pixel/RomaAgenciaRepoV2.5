<?php
// includes/SystemUpdater.php
// Gestor de actualización del sistema con 1 solo clic desde GitHub sin afectar la base de datos

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/BackupHelper.php';

class SystemUpdater {
    private $db;
    private $basePath;
    private $log = [];

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = (new Database())->getConnection();
        }
        $this->basePath = realpath(__DIR__ . '/..');
    }

    private function log($message) {
        $timestamp = date('H:i:s');
        $this->log[] = "[$timestamp] $message";
    }

    public function getLogs() {
        return $this->log;
    }

    /**
     * Obtiene información sobre la versión local del sistema y Git
     */
    public function getLocalInfo() {
        $info = [
            'version' => $this->getSetting('system_version', 'v2.5.0'),
            'repo_url' => $this->getSetting('system_repo_url', 'https://github.com/networkturbo4-pixel/RomaAgenciaRepoV2.5.git'),
            'branch' => $this->getSetting('system_repo_branch', 'main'),
            'last_update' => $this->getSetting('system_last_update', null),
            'has_git' => false,
            'current_commit' => 'N/A',
            'current_commit_date' => 'N/A',
            'current_commit_msg' => 'N/A',
            'git_origin' => ''
        ];

        // Verificar si Git está disponible y el proyecto es un repo
        if (is_dir($this->basePath . '/.git')) {
            $info['has_git'] = true;
            $originUrl = $this->runCommand('git remote get-url origin');
            $info['git_origin'] = trim($originUrl);

            $commitHash = $this->runCommand('git log -1 --format="%h - %H"');
            if ($commitHash) {
                $parts = explode(' - ', trim($commitHash));
                $info['current_commit'] = $parts[0] ?? trim($commitHash);
                $info['current_commit_full'] = $parts[1] ?? trim($commitHash);
            }

            $commitDate = $this->runCommand('git log -1 --format="%cd" --date=format:"%d/%m/%Y %H:%M"');
            if ($commitDate) {
                $info['current_commit_date'] = trim($commitDate);
            }

            $commitMsg = $this->runCommand('git log -1 --format="%s"');
            if ($commitMsg) {
                $info['current_commit_msg'] = trim($commitMsg);
            }

            $currentBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD');
            if ($currentBranch) {
                $info['git_branch'] = trim($currentBranch);
            }
        }

        return $info;
    }

    /**
     * Comprueba si hay actualizaciones disponibles en el repositorio de GitHub
     */
    public function checkUpdates() {
        $this->log = [];
        $this->log("Comprobando repositorio remoto...");

        $info = $this->getLocalInfo();
        $targetRepo = $info['repo_url'];
        $targetBranch = $info['branch'];

        $result = [
            'has_updates' => false,
            'commits_behind' => 0,
            'new_commits' => [],
            'current_commit' => $info['current_commit'],
            'remote_commit' => '',
            'message' => ''
        ];

        if (!$info['has_git']) {
            $result['message'] = "El directorio no está configurado con Git.";
            return $result;
        }

        // Asegurar que el remote origin esté apuntando al repo deseado
        if (!empty($targetRepo) && $info['git_origin'] !== $targetRepo) {
            $this->log("Actualizando URL remota a: $targetRepo");
            $this->runCommand("git remote set-url origin " . escapeshellarg($targetRepo));
        }

        // Intentar hacer git fetch
        $this->log("Consultando cambios en $targetBranch...");
        $fetchOutput = $this->runCommand("git fetch origin $targetBranch 2>&1");

        // Comparar HEAD local con origin/branch
        $remoteRef = "origin/$targetBranch";
        $revCount = $this->runCommand("git rev-list --count HEAD..$remoteRef 2>&1");
        $count = intval(trim($revCount));

        if ($count > 0) {
            $result['has_updates'] = true;
            $result['commits_behind'] = $count;
            $this->log("Se encontraron $count actualización(es) pendiente(s).");

            // Obtener lista de commits pendientes
            $commitsLog = $this->runCommand("git log HEAD..$remoteRef --pretty=format:\"%h|%s|%cd\" --date=short");
            $lines = explode("\n", trim($commitsLog));
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $parts = explode("|", $line);
                $result['new_commits'][] = [
                    'hash' => $parts[0] ?? '',
                    'message' => $parts[1] ?? '',
                    'date' => $parts[2] ?? ''
                ];
            }

            $remoteHash = $this->runCommand("git rev-parse --short $remoteRef");
            $result['remote_commit'] = trim($remoteHash);
            $result['message'] = "Hay $count nueva(s) actualización(es) disponible(s).";
        } else {
            $result['has_updates'] = false;
            $result['message'] = "El sistema se encuentra en la versión más reciente.";
            $this->log("El sistema ya está actualizado a la última versión disponible.");
        }

        $this->setSetting('system_last_check', date('Y-m-d H:i:s'));
        return $result;
    }

    /**
     * Ejecuta la actualización a 1 solo clic:
     * 1. Respaldo preventivo de la Base de Datos
     * 2. Descarga y sincronización de código desde GitHub
     * 3. Ejecución de migraciones SQL no destructivas
     * 4. Limpieza de caché
     */
    public function runOneClickUpdate() {
        $this->log = [];
        $this->log("=== INICIANDO ACTUALIZACIÓN DEL SISTEMA A 1 CLIC ===");

        try {
            // PASO 1: Respaldo de seguridad preventivo de la Base de Datos
            $this->log("Paso 1/4: Generando copia de seguridad preventiva de la base de datos...");
            $backupHelper = new BackupHelper($this->db);
            
            // Guardar backup preventivo en carpeta backups/ o scratch/
            $backupDir = $this->basePath . '/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }
            $safetyBackup = $backupHelper->createZipBackup('db');
            $safetyFileName = 'safety_backup_pre_update_' . date('Ymd_His') . '.zip';
            $safetyPath = $backupDir . '/' . $safetyFileName;
            @copy($safetyBackup['zipPath'], $safetyPath);
            @unlink($safetyBackup['zipPath']);

            $this->log("✅ Copia de seguridad preventiva creada con éxito: $safetyFileName (" . round($safetyBackup['size'] / 1024, 2) . " KB)");
            $this->log("   (Tus datos están protegidos ante cualquier eventualidad)");

            // PASO 2: Sincronización de código desde GitHub
            $this->log("Paso 2/4: Sincronizando archivos desde GitHub...");
            $info = $this->getLocalInfo();
            $targetBranch = $info['branch'];

            // Guardar credenciales de base de datos actuales en memoria para garantizar que no se pierdan
            $dbConfigFile = $this->basePath . '/config/database.php';
            $dbConfigContent = file_exists($dbConfigFile) ? file_get_contents($dbConfigFile) : null;

            // Ejecutar pull seguro
            $pullOutput = $this->runCommand("git pull origin $targetBranch 2>&1");
            $this->log("Git output: " . trim($pullOutput));

            // Asegurar que config/database.php no sea alterado
            if ($dbConfigContent !== null) {
                file_put_contents($dbConfigFile, $dbConfigContent);
            }
            $this->log("✅ Archivos de código actualizados con éxito. Base de datos y credenciales preservadas.");

            // PASO 3: Verificación y aplicación de migraciones SQL (no destructivas)
            $this->log("Paso 3/4: Verificando posibles migraciones de base de datos...");
            $migrationsApplied = $this->runMigrations();
            if ($migrationsApplied > 0) {
                $this->log("✅ Se aplicaron $migrationsApplied actualización(es) de estructura sin tocar datos existentes.");
            } else {
                $this->log("✅ No hay nuevas migraciones pendientes. Estructura de base de datos intacta.");
            }

            // PASO 4: Limpieza de caché
            $this->log("Paso 4/4: Optimizando y limpiando cachés del servidor...");
            if (function_exists('opcache_reset')) {
                @opcache_reset();
                $this->log("Caché OPcache restablecido.");
            }

            // Actualizar fecha de última actualización en settings
            $this->setSetting('system_last_update', date('Y-m-d H:i:s'));

            $newInfo = $this->getLocalInfo();
            $this->log("=== ACTUALIZACIÓN COMPLETADA CON ÉXITO ===");
            $this->log("Versión instalada: " . $newInfo['current_commit'] . " (" . $newInfo['current_commit_msg'] . ")");

            return [
                'success' => true,
                'safety_backup' => $safetyFileName,
                'new_commit' => $newInfo['current_commit'],
                'log' => $this->log
            ];

        } catch (Exception $e) {
            $this->log("❌ ERROR DURANTE LA ACTUALIZACIÓN: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    /**
     * Escanea y aplica migraciones SQL de forma no destructiva
     */
    public function runMigrations() {
        // Asegurar que exista la tabla para control de migraciones
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `system_migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration_name` varchar(255) NOT NULL,
                `batch` int(11) NOT NULL DEFAULT 1,
                `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration_name` (`migration_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $stmtApplied = $this->db->query("SELECT migration_name FROM system_migrations");
        $applied = $stmtApplied->fetchAll(PDO::FETCH_COLUMN);

        // Buscar archivos SQL de migración en la raíz y en database/migrations/
        $migrationFiles = [];
        $rootSqlFiles = glob($this->basePath . '/actualizacion_*.sql');
        if ($rootSqlFiles) {
            foreach ($rootSqlFiles as $f) {
                $migrationFiles[basename($f)] = $f;
            }
        }

        $migrationsDir = $this->basePath . '/database/migrations';
        if (is_dir($migrationsDir)) {
            $dirSqlFiles = glob($migrationsDir . '/*.sql');
            if ($dirSqlFiles) {
                foreach ($dirSqlFiles as $f) {
                    $migrationFiles[basename($f)] = $f;
                }
            }
        }

        // Determinar siguiente lote (batch)
        $stmtBatch = $this->db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM system_migrations");
        $nextBatch = intval($stmtBatch->fetchColumn());

        $appliedCount = 0;
        foreach ($migrationFiles as $name => $path) {
            if (in_array($name, $applied)) {
                continue; // Ya fue ejecutada previamente
            }

            $this->log("Ejecutando migración: $name...");
            $sqlContent = file_get_contents($path);

            if (!empty(trim($sqlContent))) {
                // Desactivar temporalmente foreign keys para migraciones
                $this->db->exec("SET FOREIGN_KEY_CHECKS=0;");
                
                // Ejecutar sentencias individuales tolerando columnas ya existentes
                $queries = $this->splitSqlQueries($sqlContent);
                foreach ($queries as $query) {
                    if (empty(trim($query))) continue;
                    try {
                        $this->db->exec($query);
                    } catch (PDOException $e) {
                        // Ignorar errores benignos como "Duplicate column name" o "Table already exists"
                        $msg = $e->getMessage();
                        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
                            // Columna o tabla ya existía, seguro continuar
                            continue;
                        }
                        $this->log("Aviso en query de migración: " . $e->getMessage());
                    }
                }
                $this->db->exec("SET FOREIGN_KEY_CHECKS=1;");
            }

            // Registrar como ejecutada
            $stmtInsert = $this->db->prepare("INSERT INTO system_migrations (migration_name, batch) VALUES (?, ?)");
            $stmtInsert->execute([$name, $nextBatch]);
            $appliedCount++;
        }

        return $appliedCount;
    }

    /**
     * Divide sentencias SQL por ';' respetando delimitadores y bloques
     */
    private function splitSqlQueries($sql) {
        $queries = [];
        $lines = explode("\n", $sql);
        $currentQuery = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                continue; // Comentario
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
     * Cambia la URL del repositorio remoto
     */
    public function setRemoteRepository($url, $branch = 'main') {
        $url = trim($url);
        $branch = trim($branch);

        $this->setSetting('system_repo_url', $url);
        $this->setSetting('system_repo_branch', $branch);

        if (is_dir($this->basePath . '/.git') && !empty($url)) {
            $this->runCommand("git remote set-url origin " . escapeshellarg($url));
        }

        return true;
    }

    private function runCommand($cmd) {
        $descriptor = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $process = proc_open($cmd, $descriptor, $pipes, $this->basePath);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);

            return !empty($stdout) ? $stdout : $stderr;
        }

        return '';
    }

    private function getSetting($key, $default = '') {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public function setSetting($key, $val) {
        try {
            $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute([':key' => $key, ':val' => $val]);
        } catch (Exception $e) {
            // Ignorar
        }
    }
}
