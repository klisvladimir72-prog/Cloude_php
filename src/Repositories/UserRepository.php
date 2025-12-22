<?php

namespace Src\Repositories;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';

    public function findByEmail(string $email)
    {
        return $this->db->findOneBy($this->table, ['email' => $email]);
    }

    public function findById(int $id)
    {
        return $this->db->findOneBy($this->table, ['id' => $id]);
    }

    // --- Метод для проверки уникальности email и login (для регистрации) ---
    /**
     * Проверяет, существует ли пользователь с указанным email или login.
     *
     * @param string $email Email для проверки.
     * @param string $login Login для проверки.
     * @return array|null Данные пользователя, если найден, иначе null.
     */
    public function findByEmailOrLogin(string $email, string $login): ?array
    {
        $sql = "SELECT * FROM users WHERE email = :email OR login = :login";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->bindParam(':login', $login, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Приведение к ?array: если fetch вернул false, возвращаем null
        return $result === false ? null : $result;
    }

    /**
     * Находит пользователя для аутентификации по email или login.
     *
     * @param string $emailOrLogin Email или Login пользователя.
     * @return array|null Данные пользователя, если найден, иначе null.
     */
    public function findForAuth(string $emailOrLogin): ?array
    {
        $sql = "SELECT * FROM users WHERE email = :emailOrLogin OR login = :emailOrLogin";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':emailOrLogin', $emailOrLogin, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Приведение к ?array: если fetch вернул false, возвращаем null
        return $result === false ? null : $result;
    }

    public function getAllUsersExcludingAdmin(): array
    {
        try {
            // Прямой SQL запрос, т.к. BaseRepository не поддерживает сложные условия в findBy
            $sql = 'SELECT id, email, login FROM users WHERE login !="admin"';
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $users;
        } catch (\Throwable $e) {
            error_log("UserRepository::getAllUsersExcludingAdmin error: " . $e->getMessage());
            return [];
        }
    }
}
