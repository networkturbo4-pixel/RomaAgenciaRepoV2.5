<?php
// config/database.php
date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../includes/env.php';

class Database {
    private $host = "localhost";
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Detectamos si estamos en local (localhost o terminal XAMPP o ruta windows)
        $is_local = false;
        if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
            $is_local = true;
        } elseif (strpos(__DIR__, 'xampp') !== false || strpos(__DIR__, 'XAMPP') !== false || strpos(__DIR__, 'htdocs') !== false) {
            $is_local = true;
        }

        $env_db = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? null);
        $env_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
        $env_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? null);
        $env_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');

        if (!empty($env_db) && !empty($env_user)) {
            // Prioridad a variables de entorno (.env o servidor)
            $this->host = $env_host;
            $this->db_name = $env_db;
            $this->username = $env_user;
            $this->password = $env_pass !== null ? $env_pass : "";
        } elseif ($is_local) {
            // Credenciales Locales por defecto
            $this->host = "localhost";
            $this->db_name = "saas_cesar_db";
            $this->username = "root";
            $this->password = "";
        } else {
            // Producción sin .env: Registrar advertencia crítica de seguridad
            error_log("CRITICAL SECURITY ERROR: El archivo .env con las credenciales de base de datos no está configurado.");
            die("Error de configuración de entorno. Por favor verifique el archivo .env.");
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 15
            ];
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password, $options);
            $this->conn->exec("SET names utf8mb4");
            $this->conn->exec("SET time_zone = '-05:00'");
            // Prevenir desconexión prematura de MySQL por inactividad durante inferencias largas de IA
            @$this->conn->exec("SET SESSION wait_timeout = 300");
            @$this->conn->exec("SET SESSION interactive_timeout = 300");
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }

    /**
     * Verifica si la conexión sigue viva antes de una operación crítica; si murió, reconecta.
     */
    public function getValidConnection() {
        if ($this->conn instanceof PDO) {
            try {
                $check = @$this->conn->query("SELECT 1");
                if ($check !== false) {
                    return $this->conn;
                }
            } catch (\Throwable $t) {
                // Conexión caída
            }
        }
        return $this->getConnection();
    }

    /**
     * Helper estático para reconectar una instancia PDO existente si el servidor MySQL se desconectó
     */
    public static function reconnectIfDead(&$db) {
        if ($db instanceof PDO) {
            try {
                $test = @$db->query("SELECT 1");
                if ($test !== false) {
                    return $db;
                }
            } catch (\Throwable $t) {
                // Conexión perdida, forzar reconexión abajo
            }
        }
        $db = (new self())->getConnection();
        return $db;
    }
}
?>
