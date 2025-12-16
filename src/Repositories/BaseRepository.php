<?php

namespace Src\Repositories;

use Src\Core\Db;
use PDO;

abstract class BaseRepository
{
    protected string $table;
    protected Db $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    public function create(array $data): bool
    {
        try {
            $fields = array_keys($data);
            $placeholders = ':' . implode(', :', $fields);
            $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
            $stmt = $this->db->getConnection()->prepare($sql);

            if (!$stmt->execute($data)) {
                error_log("BaseRepository::create failed: " . print_r($stmt->errorInfo(), true));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            error_log("BaseRepository::create error: " . $e->getMessage());
            return false;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $fields = array_keys($data);
            $setClause = implode(' = ?, ', $fields) . ' = ?';
            $sql = "UPDATE {$this->table} SET $setClause WHERE id = ?";
            $values = array_values($data);
            $values[] = $id;
            $stmt = $this->db->getConnection()->prepare($sql);
            return $stmt->execute($values);
        } catch (\Throwable $e) {
            error_log("BaseRepository::update error: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            error_log("BaseRepository::delete error: " . $e->getMessage());
            return false;
        }
    }

    // Метод find — для поиска по ID
    public function find(string $table, int $id)
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT); // <-- Теперь PDO известен!
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);       // <-- Здесь тоже!
    }

    public function findBy(string $table, array $criteria)
    {
        try {
            $fields = array_keys($criteria);
            $conditions = [];
            $params = [];

            foreach ($fields as $field) {
                $value = $criteria[$field];
                if ($value === null) {
                    $conditions[] = "$field IS NULL";
                } else {
                    $conditions[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }

            $whereClause = implode(' AND ', $conditions);
            $sql = "SELECT * FROM $table WHERE $whereClause";

            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));

            $stmt = $this->db->getConnection()->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log("BaseRepository::findBy error: " . $e->getMessage());
            return [];
        }
    }

    public function findByWithLimit(string $table, array $criteria, int $limit, int $offset)
    {
        try {
            $fields = array_keys($criteria);
            $conditions = [];
            $params = [];

            foreach ($fields as $field) {
                $value = $criteria[$field];
                if ($value === null) {
                    $conditions[] = "$field IS NULL";
                } else {
                    $conditions[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }

            $whereClause = implode(' AND ', $conditions);
            $sql = "SELECT * FROM $table WHERE $whereClause LIMIT :limit OFFSET :offset";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log("BaseRepository::findByWithLimit error: " . $e->getMessage());
            return [];
        }
    }

    public function findAll(string $table)
    {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM $table");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
