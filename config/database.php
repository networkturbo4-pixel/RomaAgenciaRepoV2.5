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
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->exec("SET time_zone = '-05:00'");
            // Set PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
?>
