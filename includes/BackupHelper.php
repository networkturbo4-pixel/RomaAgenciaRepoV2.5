<?php
// includes/BackupHelper.php
// Gestor integral de copias de seguridad (SQL Dumps, Compresión ZIP y Restauración Segura)

require_once __DIR__ . '/../config/database.php';

class BackupHelper {
    private $db;

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = (new Database())->getConnection();
        }
    }

    /**
     * Obtiene la contraseña de respaldo configurada en el sistema (opcional)
     */
    public function getBackupPassword() {
        if (!$this->db) return '';
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'backup_password'");
            return $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Genera un volcado SQL completo de todas las tablas y sus datos
     * @param string|null $outputPath Si se define, escribe directamente en disco ahorrando RAM
     * @return string Ruta al archivo generado
     */
    public function generateDbDump($outputPath = null) {
        if (!$this->db) {
            throw new Exception("No hay conexión activa con la base de datos.");
        }

        if (!$outputPath) {
            $scratchDir = __DIR__ . '/../scratch';
            if (!is_dir($scratchDir)) {
                @mkdir($scratchDir, 0777, true);
            }
            $outputPath = $scratchDir . '/db_dump_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        }

        $fp = fopen($outputPath, 'w');
        if (!$fp) {
            throw new Exception("No se pudo crear el archivo de volcado SQL en: $outputPath");
        }

        // Encabezado
        fwrite($fp, "-- ========================================================\n");
        fwrite($fp, "-- COPIA DE SEGURIDAD DEL SISTEMA (ROMA AGENCIA)\n");
        fwrite($fp, "-- Fecha de generación: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- ========================================================\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($fp, "SET time_zone = '-05:00';\n");
        fwrite($fp, "SET NAMES utf8mb4;\n\n");

        $stmtTables = $this->db->query("SHOW TABLES");
        $tables = [];
        while ($r = $stmtTables->fetch(PDO::FETCH_NUM)) {
            $tables[] = $r[0];
        }

        foreach ($tables as $table) {
            // Estructura de la tabla
            $stmtCreate = $this->db->query("SHOW CREATE TABLE `$table`");
            $rowCreate = $stmtCreate->fetch(PDO::FETCH_NUM);
            if (!$rowCreate) continue;

            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "-- Estructura de tabla para `$table`\n");
            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($fp, $rowCreate[1] . ";\n\n");

            // Datos de la tabla
            $stmtData = $this->db->query("SELECT * FROM `$table`");
            $rowCount = $stmtData->rowCount();

            if ($rowCount > 0) {
                fwrite($fp, "-- Datos para la tabla `$table` ($rowCount registros)\n");
                $batchSize = 100;
                $batch = [];
                $count = 0;

                while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = "NULL";
                        } else {
                            $vals[] = $this->db->quote($val);
                        }
                    }
                    $batch[] = "(" . implode(", ", $vals) . ")";
                    $count++;

                    if (count($batch) >= $batchSize) {
                        fwrite($fp, "INSERT INTO `$table` VALUES \n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    fwrite($fp, "INSERT INTO `$table` VALUES \n" . implode(",\n", $batch) . ";\n\n");
                } else {
                    fwrite($fp, "\n");
                }
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($fp, "-- FIN DEL RESPALDO\n");
        fclose($fp);

        return $outputPath;
    }

    /**
     * Empaqueta el backup en un archivo ZIP listo para descargar
     * @param string $type 'db' (solo base de datos) o 'full' (BD + archivos del sistema)
     * @param string|null $password Contraseña de cifrado ZIP (opcional)
     * @return array [ 'zipPath' => string, 'fileName' => string, 'size' => int ]
     */
    public function createZipBackup($type = 'db', $password = null) {
        if (!extension_loaded('zip')) {
            throw new Exception("La extensión PHP ZIP no está habilitada en el servidor.");
        }

        if ($password === null) {
            $password = $this->getBackupPassword();
        }

        $scratchDir = __DIR__ . '/../scratch';
        if (!is_dir($scratchDir)) {
            @mkdir($scratchDir, 0777, true);
        }

        $timestamp = date('Ymd_His');
        $fileName = ($type === 'db' ? 'backup_db_' : 'backup_completo_') . $timestamp . '.zip';
        $zipPath = $scratchDir . '/' . $fileName;

        // 1. Generar SQL
        $sqlPath = $this->generateDbDump();

        // 2. Crear ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sqlPath);
            throw new Exception("No se pudo inicializar el archivo ZIP.");
        }

        // Añadir archivo SQL
        $sqlInnerName = 'database_dump.sql';
        $zip->addFile($sqlPath, $sqlInnerName);

        if (!empty($password)) {
            $zip->setPassword($password);
            if (defined('ZipArchive::EM_AES_256')) {
                $zip->setEncryptionName($sqlInnerName, ZipArchive::EM_AES_256);
            }
        }

        // 3. Si es completo, añadir archivos del sistema respetando exclusiones de peso
        if ($type === 'full') {
            $this->addDirectoryToZip(__DIR__ . '/..', $zip, '', $password);
        }

        $zip->close();

        // Eliminar temporal SQL después de cerrar el ZIP
        @unlink($sqlPath);

        return [
            'zipPath' => $zipPath,
            'fileName' => $fileName,
            'size' => filesize($zipPath)
        ];
    }

    /**
     * Añade directorios recursivamente al ZIP excluyendo carpetas gigantes/inútiles
     */
    private function addDirectoryToZip($dir, ZipArchive $zip, $zipPath = '', $password = null) {
        $excludeRoots = ['.git', 'scratch', 'node_modules', 'vendor', 'archivos_biometria'];
        $items = @scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (empty($zipPath) && in_array($item, $excludeRoots)) continue;

            $fullPath = $dir . '/' . $item;
            $localPath = $zipPath . $item;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($localPath);
                $this->addDirectoryToZip($fullPath, $zip, $localPath . '/', $password);
            } elseif (is_file($fullPath)) {
                // No incluir archivos de más de 20MB en el zip para no saturar memoria
                if (filesize($fullPath) < 20 * 1024 * 1024) {
                    $zip->addFile($fullPath, $localPath);
                    if (!empty($password) && defined('ZipArchive::EM_AES_256')) {
                        $zip->setEncryptionName($localPath, ZipArchive::EM_AES_256);
                    }
                }
            }
        }
    }

    /**
     * Restaura una base de datos directamente desde un archivo SQL
     * @param string $sqlFilePath Ruta al archivo .sql
     * @return array Resumen de restauración
     */
    public function restoreFromSqlFile($sqlFilePath) {
        if (!file_exists($sqlFilePath)) {
            throw new Exception("El archivo SQL no existe: $sqlFilePath");
        }

        $content = file_get_contents($sqlFilePath);
        if (empty(trim($content))) {
            throw new Exception("El archivo SQL está vacío o no contiene consultas válidas.");
        }

        return $this->executeSqlContent($content);
    }

    /**
     * Restaura una base de datos a partir de un archivo ZIP cargado
     * @param string $zipFilePath Ruta al archivo .zip
     * @param string|null $password Contraseña de desencriptado (opcional)
     * @return array Resumen de restauración
     */
    public function restoreFromZipFile($zipFilePath, $password = null) {
        if (!file_exists($zipFilePath)) {
            throw new Exception("El archivo ZIP no existe.");
        }

        if ($password === null) {
            $password = $this->getBackupPassword();
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new Exception("No se pudo abrir el archivo ZIP. Verifique que sea un ZIP válido.");
        }

        if (!empty($password)) {
            $zip->setPassword($password);
        }

        // Buscar archivo SQL dentro del zip
        $sqlFileName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (pathinfo($name, PATHINFO_EXTENSION) === 'sql' || strpos($name, 'database_dump') !== false) {
                $sqlFileName = $name;
                break;
            }
        }

        if (!$sqlFileName) {
            $zip->close();
            throw new Exception("El archivo ZIP no contiene ningún archivo de volcado SQL (.sql).");
        }

        $extractDir = __DIR__ . '/../scratch/restore_' . time() . '_' . bin2hex(random_bytes(3));
        if (!is_dir($extractDir)) {
            @mkdir($extractDir, 0777, true);
        }

        $extracted = $zip->extractTo($extractDir, $sqlFileName);
        $zip->close();

        if (!$extracted) {
            $this->cleanDir($extractDir);
            throw new Exception("Fallo al extraer el archivo SQL del ZIP. Si tiene contraseña, verifique la clave.");
        }

        $extractedSqlPath = $extractDir . '/' . $sqlFileName;
        if (!file_exists($extractedSqlPath)) {
            $this->cleanDir($extractDir);
            throw new Exception("No se pudo ubicar el archivo SQL extraído.");
        }

        $result = $this->restoreFromSqlFile($extractedSqlPath);

        // Limpiar directorio temporal
        $this->cleanDir($extractDir);

        return $result;
    }

    /**
     * Ejecuta el script SQL asegurando desactivar y reactivar claves foráneas
     */
    public function executeSqlContent($sqlContent) {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $this->db->exec("SET FOREIGN_KEY_CHECKS=0;");
        $this->db->exec("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");

        try {
            // Ejecutar el script SQL
            $this->db->exec($sqlContent);
            $this->db->exec("SET FOREIGN_KEY_CHECKS=1;");

            // Contar tablas actuales en la base de datos
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return [
                'success' => true,
                'tables_restored' => count($tables),
                'message' => 'Base de datos restaurada exitosamente.'
            ];
        } catch (PDOException $e) {
            $this->db->exec("SET FOREIGN_KEY_CHECKS=1;");
            throw new Exception("Error al ejecutar consultas SQL: " . $e->getMessage());
        }
    }

    /**
     * Limpia un directorio temporal y sus archivos
     */
    private function cleanDir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $p = "$dir/$file";
            is_dir($p) ? $this->cleanDir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
