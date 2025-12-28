<?php

namespace Src\Repositories;

use Src\Core\App;
use PDO;

class UserTokenRepository extends BaseRepository
{
    protected string $table = 'user_tokens';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Находит токен по значению.
     *
     * @param string $token
     * @return array|null Данные токена, если найден, иначе null.
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT * FROM user_tokens WHERE token = :token";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':token', $token, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Создает новый токен для пользователя.
     *
     * @param int $userId
     * @param string $token
     * @param DateTime $expiresAt
     * @return bool
     */
    public function createToken(int $userId, string $token, \DateTime $expiresAt): bool
    {
        $sql = "INSERT INTO user_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindParam(':token', $token, \PDO::PARAM_STR);
        $stmt->bindParam(':expires_at', $expiresAt->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Удаляет токен по значению.
     *
     * @param string $token
     * @return bool
     */
    public function deleteByToken(string $token): bool
    {
        $sql = "DELETE FROM user_tokens WHERE token = :token";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':token', $token, \PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Удаляет все токены пользователя. для реализиции logout из всех устройств
     *
     * @param int $userId
     * @return bool
     */
    public function deleteAllForUser(int $userId): bool
    {
        $sql = "DELETE FROM user_tokens WHERE user_id = :user_id";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Удаляет все просроченные токены.
     *
     * @return int Количество удаленных записей.
     */
    public function deleteExpired(): int
    {
        $sql = "DELETE FROM user_tokens WHERE expires_at < NOW()";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
