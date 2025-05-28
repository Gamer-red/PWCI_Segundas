<?php
class Database {
    private static $instance = null;
    private $connection;

    private $host = 'localhost';
    private $port = '3307'; // ← PUERTO correcto
    private $dbName = 'pwci'; // ← CAMBIA esto al nombre real
    private $username = 'root';
    private $password = ''; // ← vacía en XAMPP

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbName};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
?>
