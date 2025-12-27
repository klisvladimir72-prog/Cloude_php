<?php

namespace Src\Core;

use PDO;
use PDOException;

class Db
{
    private static ?Db $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = include __DIR__ . '/../../config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // public function find(string $table, int $id)
    // {
    //     $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = :id");
    //     $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    //     $stmt->execute();
    //     return $stmt->fetch(PDO::FETCH_ASSOC);
    // }

    // public function findAll(string $table)
    // {
    //     $stmt = $this->pdo->query("SELECT * FROM $table");
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

    // public function findBy(string $table, array $criteria)
    // {
    //     $fields = array_keys($criteria);
    //     $whereClause = implode(' AND ', array_map(fn($f) => "$f = :$f", $fields));
    //     $sql = "SELECT * FROM $table WHERE $whereClause";
    //     $stmt = $this->pdo->prepare($sql);
    //     foreach ($criteria as $key => $value) {
    //         $stmt->bindValue(":$key", $value);
    //     }
    //     $stmt->execute();
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

    // public function findOneBy(string $table, array $criteria)
    // {
    //     $result = $this->findBy($table, $criteria);
    //     return reset($result) ?: null;
    // }
}
