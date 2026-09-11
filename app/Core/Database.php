<?php

class Database
{
    private $connection;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = "mysql:host={$config['host']};"
             . "port={$config['port']};"
             . "dbname={$config['dbname']};"
             . "charset={$config['charset']}";

        try {
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password']
            );

            $this->connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $this->connection->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

        } catch (PDOException $e) {
            die("Lỗi kết nối database: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }
}