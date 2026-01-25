<?php

namespace Src\Repositories;

class PasswordResetRepository extends BaseRepository
{
    protected string $table = 'password_resets';

    public function getTable(): string
    {
        return $this->table;
    }


    /**
     * Проверяет, был ли запрос на сброс за последние N минут
     */
    public function hasRecentRequest(string $email, int $minutes): int
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([$email, $minutes]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log("PasswordResetRepository::hasRecentRequest error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверяет, был ли запрос на сброс за последние N часов
     */
    public function hasDailyRequests(string $email, int $hours): int
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)"
            );
            $stmt->execute([$email, $hours]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log("PasswordResetRepository::hasDailyRequests error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет просроченные токены
     */
    public function deleteExpiredTokens(string $email): void
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "DELETE FROM {$this->table} WHERE email = ? AND expires_at < NOW()"
            );
            $stmt->execute([$email]);
        } catch (\Throwable $e) {
            error_log("PasswordResetRepository::deleteExpiredTokens error: " . $e->getMessage());
            return;
        }
    }
}
