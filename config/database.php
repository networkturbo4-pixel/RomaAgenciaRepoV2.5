<?php
// config/database.php
date_default_timezone_set('America/Lima');

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

        if ($is_local) {
            // Credenciales Locales
            $this->db_name = "saas_cesar_db";
            $this->username = "root";
            $this->password = "";
        } else {
            // Credenciales de Producción
            $this->db_name = "iqxalgre_saasroma";
            $this->username = "iqxalgre_cesarsaas";
            $this->password = "RomaAgencia2026@$%&$$$$";
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
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>
