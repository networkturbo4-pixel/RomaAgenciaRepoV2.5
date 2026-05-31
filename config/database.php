<?php
// config/database.php

class Database {
    private $host = "localhost";
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Detectamos si estamos en local (localhost o terminal XAMPP)
        $is_local = false;
        if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
            $is_local = true;
        } elseif (php_sapi_name() === 'cli' && strpos(__DIR__, 'xampp') !== false) {
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
