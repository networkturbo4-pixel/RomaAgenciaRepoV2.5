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

    /**
     * Revalida o reconecta la conexión a la base de datos si el servidor la cerró por inactividad
     */
    public function reconnectDb() {
        $this->db = null;
        try {
            require_once __DIR__ . '/../config/database.php';
            $dbInstance = new Database();
            $this->db = $dbInstance->getConnection();
            if ($this->db instanceof PDO) {
                @$this->db->exec("SET SESSION wait_timeout = 600, interactive_timeout = 600");
            }
        } catch (\Throwable $e) {
            $this->log("Aviso de reconexión DB: " . $e->getMessage());
        }
        return $this->db;
    }

    /**
     * Obtiene una conexión PDO activa garantizada, reconectando automáticamente si se perdió
     */
    public function getDb() {
        try {
            if ($this->db instanceof PDO) {
                $test = $this->db->query("SELECT 1");
                if ($test !== false) {
                    return $this->db;
                }
            }
        } catch (\Throwable $e) {
            $this->db = null;
        }

        return $this->reconnectDb();
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
            'git_origin' => '',
            'mode' => 'api'
        ];

        // 1. Verificar si Git está instalado y el directorio es un repo activo
        if (is_dir($this->basePath . '/.git')) {
            $originUrl = $this->runCommand('git remote get-url origin');
            if (!empty($originUrl) && strpos($originUrl, 'fatal:') === false) {
                $info['has_git'] = true;
                $info['mode'] = 'git';
                $info['git_origin'] = trim($originUrl);

                $commitHash = $this->runCommand('git log -1 --format="%h - %H"');
                if ($commitHash && strpos($commitHash, 'fatal:') === false) {
                    $parts = explode(' - ', trim($commitHash));
                    $info['current_commit'] = $parts[0] ?? trim($commitHash);
                    $info['current_commit_full'] = $parts[1] ?? trim($commitHash);
                }

                $commitDate = $this->runCommand('git log -1 --format="%cd" --date=format:"%d/%m/%Y %H:%M"');
                if ($commitDate && strpos($commitDate, 'fatal:') === false) {
                    $info['current_commit_date'] = trim($commitDate);
                }

                $commitMsg = $this->runCommand('git log -1 --format="%s"');
                if ($commitMsg && strpos($commitMsg, 'fatal:') === false) {
                    $info['current_commit_msg'] = trim($commitMsg);
                }

                $currentBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD');
                if ($currentBranch && strpos($currentBranch, 'fatal:') === false) {
                    $info['git_branch'] = trim($currentBranch);
                }
            }
        }

        // 2. Si no hay Git o es un hosting de producción (cPanel / Web) sin .git, usar registros de BD
        if (!$info['has_git']) {
            $savedCommit = $this->getSetting('system_current_commit', '');
            if (!empty($savedCommit)) {
                $info['current_commit'] = $savedCommit;
                $info['current_commit_date'] = $this->getSetting('system_current_commit_date', 'Fecha no registrada');
                $info['current_commit_msg'] = $this->getSetting('system_current_commit_msg', 'Actualización aplicada');
            } else {
                $info['current_commit'] = 'v2.5.0';
                $info['current_commit_date'] = '05/09/2026';
                $info['current_commit_msg'] = 'Roma Agencia V2.5 Base';
            }
        }

        return $info;
    }

    /**
     * Comprueba si hay actualizaciones disponibles en el repositorio de GitHub
     */
    public function checkUpdates() {
        $this->log = [];
        $this->log("Iniciando comprobación de actualizaciones en GitHub...");

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

        // Si Git está disponible localmente, intentar por Git
        if ($info['has_git']) {
            if (!empty($targetRepo) && $info['git_origin'] !== $targetRepo) {
                $this->log("Alineando URL remota: $targetRepo");
                $this->runCommand("git remote set-url origin " . escapeshellarg($targetRepo));
            }

            $this->log("Consultando rama $targetBranch vía Git local...");
            $fetchOutput = $this->runCommand("git fetch origin $targetBranch 2>&1");
            
            if (strpos($fetchOutput, 'fatal:') === false && strpos($fetchOutput, 'error:') === false) {
                $remoteRef = "origin/$targetBranch";
                $revCount = $this->runCommand("git rev-list --count HEAD..$remoteRef 2>&1");
                $count = intval(trim($revCount));

                if ($count > 0) {
                    $result['has_updates'] = true;
                    $result['commits_behind'] = $count;
                    $this->log("Se encontraron $count actualización(es) pendiente(s) en GitHub.");

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
                    $this->setSetting('system_last_check', date('Y-m-d H:i:s'));
                    return $result;
                } else {
                    $result['has_updates'] = false;
                    $result['message'] = "El sistema se encuentra en la versión más reciente.";
                    $this->log("El sistema ya está actualizado a la última versión disponible.");
                    $this->setSetting('system_last_check', date('Y-m-d H:i:s'));
                    return $result;
                }
            } else {
                $this->log("Aviso: Git CLI local no disponible o bloqueado en este hosting. Conectando vía GitHub API universal...");
            }
        }

        // Si no hay Git o estamos en producción cPanel, consultar directo con GitHub REST API
        $this->log("Conectando con GitHub API para verificar últimas versiones...");
        return $this->checkUpdatesViaGitHubApi($targetRepo, $targetBranch, $info['current_commit']);
    }

    /**
     * Consulta actualizaciones usando la API pública de GitHub (Funciona en cualquier hosting sin Git)
     */
    private function checkUpdatesViaGitHubApi($repoUrl, $branch, $currentCommit) {
        $result = [
            'has_updates' => false,
            'commits_behind' => 0,
            'new_commits' => [],
            'current_commit' => $currentCommit,
            'remote_commit' => '',
            'message' => ''
        ];

        $repoData = $this->parseGitHubRepo($repoUrl);
        if (!$repoData) {
            $this->log("❌ URL de repositorio no válida para GitHub: $repoUrl");
            $result['message'] = "URL de repositorio no válida.";
            return $result;
        }

        $apiUrl = "https://api.github.com/repos/{$repoData['owner']}/{$repoData['repo']}/commits?sha={$branch}&per_page=15";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'RomaAgencia-Updater',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            $this->log("❌ No se pudo consultar la API de GitHub (HTTP $httpCode). " . ($curlErr ?: ''));
            $result['message'] = "No se pudo conectar con GitHub API (HTTP $httpCode).";
            return $result;
        }

        $commits = json_decode($response, true);
        if (!is_array($commits) || empty($commits)) {
            $this->log("⚠️ No se encontraron commits en la rama $branch.");
            $result['message'] = "No se encontraron commits disponibles.";
            return $result;
        }

        $latest = $commits[0];
        $latestSha = $latest['sha'] ?? '';
        $latestShortSha = substr($latestSha, 0, 7);
        $latestMsg = $latest['commit']['message'] ?? '';
        $latestDate = isset($latest['commit']['author']['date']) ? date('d/m/Y H:i', strtotime($latest['commit']['author']['date'])) : '';

        $result['remote_commit'] = $latestShortSha;

        $isSame = false;
        if (!empty($currentCommit) && $currentCommit !== 'N/A') {
            if ($currentCommit === $latestShortSha || $currentCommit === $latestSha) {
                $isSame = true;
            }
        }

        if (!$isSame) {
            $result['has_updates'] = true;
            $this->log("✅ ¡Nueva versión detectada en GitHub!");
            $this->log("Último commit disponible: $latestShortSha - $latestMsg ($latestDate)");

            $newCommits = [];
            foreach ($commits as $c) {
                $cSha = $c['sha'] ?? '';
                $cShort = substr($cSha, 0, 7);
                if ($cShort === $currentCommit || $cSha === $currentCommit) {
                    break;
                }
                $newCommits[] = [
                    'hash' => $cShort,
                    'message' => $c['commit']['message'] ?? '',
                    'date' => isset($c['commit']['author']['date']) ? date('Y-m-d', strtotime($c['commit']['author']['date'])) : ''
                ];
            }

            if (empty($newCommits)) {
                $newCommits[] = [
                    'hash' => $latestShortSha,
                    'message' => $latestMsg,
                    'date' => $latestDate
                ];
            }

            $result['commits_behind'] = count($newCommits);
            $result['new_commits'] = $newCommits;
            $result['message'] = "Hay " . count($newCommits) . " nueva(s) actualización(es) disponible(s).";
        } else {
            $result['has_updates'] = false;
            $result['message'] = "El sistema se encuentra en la versión más reciente ($latestShortSha).";
            $this->log("✅ El sistema se encuentra al día con la última versión de GitHub ($latestShortSha).");
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
            $this->log("   (Tus datos están 100% protegidos ante cualquier eventualidad)");

            // PASO 2: Sincronización de código desde GitHub
            $this->log("Paso 2/4: Sincronizando archivos del sistema desde GitHub...");
            $info = $this->getLocalInfo();
            $targetRepo = $info['repo_url'];
            $targetBranch = $info['branch'];

            $updateViaGitSuccess = false;
            if ($info['has_git']) {
                $this->log("Intentando sincronización mediante Git pull...");
                $dbConfigFile = $this->basePath . '/config/database.php';
                $dbConfigContent = file_exists($dbConfigFile) ? file_get_contents($dbConfigFile) : null;

                $pullOutput = $this->runCommand("git pull origin $targetBranch 2>&1");
                if (strpos($pullOutput, 'fatal:') === false && strpos($pullOutput, 'error:') === false) {
                    $this->log("Git output: " . trim($pullOutput));
                    if ($dbConfigContent !== null) {
                        file_put_contents($dbConfigFile, $dbConfigContent);
                    }
                    $updateViaGitSuccess = true;
                    $this->log("✅ Código sincronizado exitosamente con Git.");
                } else {
                    $this->log("Git local omitido (" . trim($pullOutput) . "). Usando descarga directa ZIP...");
                }
            }

            if (!$updateViaGitSuccess) {
                // Modo 2: Actualización Incremental Inteligente (Descarga SOLO los archivos modificados)
                $updateViaGitSuccess = $this->applyIncrementalUpdate($targetRepo, $targetBranch, $info['current_commit']);
            }

            if (!$updateViaGitSuccess) {
                // Modo 3: Si no fue posible incremental, fallback al paquete ZIP completo
                $this->downloadAndApplyZipUpdate($targetRepo, $targetBranch);
            }

            // PASO 3: Verificación y aplicación de migraciones SQL (no destructivas)
            $this->reconnectDb();
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
     * Descarga y aplica EXCLUSIVAMENTE los archivos modificados entre el commit actual y el remoto.
     * Pesa apenas kilobytes en lugar de cientos de megabytes y se ejecuta en cuestión de segundos.
     */
    private function applyIncrementalUpdate($repoUrl, $branch, $currentCommit) {
        $repoData = $this->parseGitHubRepo($repoUrl);
        if (!$repoData) return false;

        $owner = $repoData['owner'];
        $repo = $repoData['repo'];

        // Si no hay commit base conocido válido, usar fallback a ZIP
        if (empty($currentCommit) || $currentCommit === 'N/A' || strlen($currentCommit) < 4) {
            return false;
        }

        // Obtener el último commit remoto de la rama
        $apiUrl = "https://api.github.com/repos/$owner/$repo/commits/$branch";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'RomaAgencia-Updater',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $remoteData = json_decode($resp, true);
        if (empty($remoteData['sha'])) return false;

        $headSha = $remoteData['sha'];
        $headShort = substr($headSha, 0, 7);
        $headMsg = $remoteData['commit']['message'] ?? 'Actualización incremental';
        $headDate = isset($remoteData['commit']['author']['date']) ? date('d/m/Y H:i', strtotime($remoteData['commit']['author']['date'])) : date('d/m/Y H:i');

        if ($currentCommit === $headShort || $currentCommit === $headSha) {
            $this->log("El sistema ya cuenta con el último commit ($headShort).");
            return true;
        }

        // Consultar diferencias con GitHub Compare API
        $compareUrl = "https://api.github.com/repos/$owner/$repo/compare/$currentCommit...$headSha";
        $chComp = curl_init($compareUrl);
        curl_setopt_array($chComp, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'RomaAgencia-Updater',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $compResp = curl_exec($chComp);
        $compHttp = curl_getinfo($chComp, CURLINFO_HTTP_CODE);
        curl_close($chComp);

        if ($compHttp !== 200 || empty($compResp)) {
            $this->log("Aviso: No se pudo comparar diff incremental (HTTP $compHttp). Pasando a paquete ZIP...");
            return false;
        }

        $compData = json_decode($compResp, true);
        $files = $compData['files'] ?? [];

        // Si son demasiados archivos (ej. más de 80), es preferible el paquete ZIP completo
        if (empty($files) || count($files) > 80) {
            $this->log("Se detectaron " . count($files) . " archivos modificados. Optimizando vía paquete ZIP...");
            return false;
        }

        $this->log("⚡ Modo Incremental Inteligente: Descargando únicamente " . count($files) . " archivo(s) modificado(s) (¡Súper ligero!)...");

        $updatedCount = 0;
        foreach ($files as $f) {
            $filename = $f['filename'];
            $status = $f['status'] ?? 'modified';

            // Exclusiones críticas de seguridad
            if (strpos($filename, 'uploads/') === 0 || strpos($filename, 'backups/') === 0 || strpos($filename, '.git') === 0) {
                continue;
            }
            if ($filename === 'config/database.php') {
                continue; // Jamás tocar credenciales de la BD
            }

            $targetPath = $this->basePath . '/' . $filename;

            if ($status === 'removed') {
                if (file_exists($targetPath) && is_file($targetPath)) {
                    @unlink($targetPath);
                    $this->log(" - Eliminado archivo obsoleto: $filename");
                }
                $updatedCount++;
                continue;
            }

            // Descargar contenido directo del archivo individual desde raw
            $rawUrl = "https://raw.githubusercontent.com/$owner/$repo/$headSha/$filename";
            $rawCh = curl_init($rawUrl);
            curl_setopt_array($rawCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'RomaAgencia-Updater',
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $fileContent = curl_exec($rawCh);
            $rawHttp = curl_getinfo($rawCh, CURLINFO_HTTP_CODE);
            curl_close($rawCh);

            if ($rawHttp === 200 && $fileContent !== false) {
                @mkdir(dirname($targetPath), 0755, true);
                if (file_put_contents($targetPath, $fileContent) !== false) {
                    $this->log(" ✅ Actualizado: $filename (" . round(strlen($fileContent) / 1024, 1) . " KB)");
                    $updatedCount++;
                } else {
                    $this->log(" ⚠️ Error de permisos al escribir: $filename");
                }
            } else {
                $this->log(" ⚠️ Error al descargar archivo: $filename (HTTP $rawHttp)");
            }
        }

        $this->log("✅ Sincronización incremental finalizada: $updatedCount archivo(s) actualizados.");

        // Revalidar DB y registrar commit
        $this->reconnectDb();
        $this->setSetting('system_current_commit', $headShort);
        $this->setSetting('system_current_commit_date', $headDate);
        $this->setSetting('system_current_commit_msg', $headMsg);

        return true;
    }

    /**
     * Descarga y descomprime la actualización de GitHub directamente preservando BD y uploads
     */
    private function downloadAndApplyZipUpdate($repoUrl, $branch) {
        $repoData = $this->parseGitHubRepo($repoUrl);
        if (!$repoData) {
            throw new Exception("URL de repositorio no válida para descargar actualización.");
        }

        $owner = $repoData['owner'];
        $repo = $repoData['repo'];

        // Consultar metadatos del commit más reciente
        $latestCommitSha = 'v2.5.0';
        $latestCommitMsg = 'Actualización Roma Agencia';
        $latestCommitDate = date('d/m/Y H:i');

        $apiUrl = "https://api.github.com/repos/$owner/$repo/commits/$branch";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'RomaAgencia-Updater',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $commitResp = curl_exec($ch);
        curl_close($ch);

        if ($commitResp) {
            $cData = json_decode($commitResp, true);
            if (!empty($cData['sha'])) {
                $latestCommitSha = substr($cData['sha'], 0, 7);
                $latestCommitMsg = $cData['commit']['message'] ?? $latestCommitMsg;
                if (!empty($cData['commit']['author']['date'])) {
                    $latestCommitDate = date('d/m/Y H:i', strtotime($cData['commit']['author']['date']));
                }
            }
        }

        $zipUrl = "https://github.com/$owner/$repo/archive/refs/heads/$branch.zip";
        $backupDir = $this->basePath . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0777, true);
        }

        $tempZip = $backupDir . '/update_' . time() . '.zip';
        $tempExtractDir = $backupDir . '/temp_extract_' . time();

        $this->log("Descargando paquete de actualización oficial desde GitHub ($branch.zip)...");
        $fp = fopen($tempZip, 'w+');
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'RomaAgencia-Updater',
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $downloadSuccess = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);
        curl_close($ch);

        if (!$downloadSuccess || $httpCode !== 200 || filesize($tempZip) < 1000) {
            @unlink($tempZip);
            throw new Exception("Error al descargar paquete ZIP de GitHub (HTTP $httpCode).");
        }

        $this->log("✅ Paquete ZIP descargado (" . round(filesize($tempZip) / 1024 / 1024, 2) . " MB). Extrayendo archivos...");

        $zip = new ZipArchive();
        if ($zip->open($tempZip) !== TRUE) {
            @unlink($tempZip);
            throw new Exception("No se pudo descomprimir el archivo de actualización.");
        }

        @mkdir($tempExtractDir, 0777, true);
        $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempZip);

        // GitHub empaqueta dentro de una carpeta raíz ej: RomaAgenciaRepoV2.5-main
        $subdirs = glob($tempExtractDir . '/*', GLOB_ONLYDIR);
        $sourceDir = (!empty($subdirs) && is_dir($subdirs[0])) ? $subdirs[0] : $tempExtractDir;

        $this->log("Sincronizando archivos del sistema y protegiendo base de datos y uploads...");
        $copiedCount = $this->syncExtractedFiles($sourceDir, $this->basePath);

        // Limpiar directorio temporal de extracción
        $this->deleteDirRecursively($tempExtractDir);

        $this->log("✅ Se actualizaron $copiedCount archivos del sistema.");

        // Revalidar conexión MySQL tras la descarga y extracción pesada de archivos
        $this->reconnectDb();

        // Guardar metadata del nuevo commit en base de datos
        $this->setSetting('system_current_commit', $latestCommitSha);
        $this->setSetting('system_current_commit_date', $latestCommitDate);
        $this->setSetting('system_current_commit_msg', $latestCommitMsg);
    }

    /**
     * Copia recursivamente archivos ignorando uploads, backups y config/database.php
     */
    private function syncExtractedFiles($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        $count = 0;
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // Protección absoluta: jamás sobrescribir uploads, backups, .git ni config/database.php
            if ($file === 'uploads' || $file === 'backups' || $file === '.git') {
                continue;
            }
            if ($file === 'database.php' && basename(dirname($dstPath)) === 'config') {
                continue;
            }

            if (is_dir($srcPath)) {
                $count += $this->syncExtractedFiles($srcPath, $dstPath);
            } else {
                if (@copy($srcPath, $dstPath)) {
                    $count++;
                }
            }
        }
        closedir($dir);
        return $count;
    }

    private function deleteDirRecursively($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirRecursively($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function parseGitHubRepo($url) {
        $parsed = parse_url(trim($url));
        $path = trim($parsed['path'] ?? '', '/');
        if (substr($path, -4) === '.git') {
            $path = substr($path, 0, -4);
        }
        $parts = explode('/', $path);
        if (count($parts) >= 2) {
            return [
                'owner' => $parts[0],
                'repo' => $parts[1]
            ];
        }
        return null;
    }

    /**
     * Escanea y aplica migraciones SQL de forma no destructiva
     */
    public function runMigrations() {
        $db = $this->getDb();
        if (!$db) {
            $this->log("Aviso: Conexión a la base de datos no disponible para migraciones.");
            return 0;
        }

        // Asegurar que exista la tabla para control de migraciones
        $db->exec("
            CREATE TABLE IF NOT EXISTS `system_migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration_name` varchar(255) NOT NULL,
                `batch` int(11) NOT NULL DEFAULT 1,
                `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration_name` (`migration_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $stmtApplied = $db->query("SELECT migration_name FROM system_migrations");
        $applied = $stmtApplied ? $stmtApplied->fetchAll(PDO::FETCH_COLUMN) : [];

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
        $stmtBatch = $db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM system_migrations");
        $nextBatch = $stmtBatch ? intval($stmtBatch->fetchColumn()) : 1;

        $appliedCount = 0;
        foreach ($migrationFiles as $name => $path) {
            if (in_array($name, $applied)) {
                continue; // Ya fue ejecutada previamente
            }

            $this->log("Ejecutando migración: $name...");
            $sqlContent = file_get_contents($path);

            if (!empty(trim($sqlContent))) {
                // Desactivar temporalmente foreign keys para migraciones
                @$db->exec("SET FOREIGN_KEY_CHECKS=0;");
                
                // Ejecutar sentencias individuales tolerando columnas ya existentes
                $queries = $this->splitSqlQueries($sqlContent);
                foreach ($queries as $query) {
                    if (empty(trim($query))) continue;
                    try {
                        $db->exec($query);
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
                @$db->exec("SET FOREIGN_KEY_CHECKS=1;");
            }

            // Registrar como ejecutada
            $stmtInsert = $db->prepare("INSERT INTO system_migrations (migration_name, batch) VALUES (?, ?)");
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
        if (strpos($cmd, 'git ') === 0) {
            $cmd = 'git -c safe.directory=* ' . substr($cmd, 4);
        }

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
            $db = $this->getDb();
            if (!$db) return $default;
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function setSetting($key, $val) {
        try {
            $db = $this->getDb();
            if (!$db) return;
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute([':key' => $key, ':val' => $val]);
        } catch (\Throwable $e) {
            // Si la conexión se perdió, forzar reconexión y reintentar una vez más
            try {
                $db = $this->reconnectDb();
                if ($db) {
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val");
                    $stmt->execute([':key' => $key, ':val' => $val]);
                }
            } catch (\Throwable $ex) {
                $this->log("Aviso al guardar configuración ($key): " . $ex->getMessage());
            }
        }
    }
}
