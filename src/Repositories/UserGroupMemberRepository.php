<?php

namespace Src\Repositories;

/**
 * Репозиторий для работы с таблицей user_group_members
 */
class UserGroupMemberRepository extends BaseRepository
{
    protected string $table = 'user_group_members';

    /**
     * Получает массив ID групп, в которых состоит пользователь.
     * Использует прямой SQL-запрос для эффективности.
     *
     * @param int $userId ID пользователя
     * @return array Массив ID групп
     */
    public function getGroupIdsByUserId(int $userId): array
    {
        // Подготавливаем SQL запрос с плейсхолдером для безопасности 
        $sql = "SELECT group_id FROM user_group_members WHERE user_id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Получает массив ID пользователей, состоящих в группе.
     * Использует прямой SQL-запрос для эффективности.
     *
     * @param int $groupId ID группы
     * @return array Массив ID пользователей
     */
    public function getUserIdsByGroupId(int $groupId): array
    {
        $sql = "SELECT user_id FROM {$this->table} WHERE group_id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$groupId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Проверяет, состоит ли пользователь в конкретной группе.
     *
     * @param int $userId ID пользователя
     * @param int $groupId ID группы
     * @return bool True, если пользователь состоит в группе.
     */
    public function isUserInGroup(int $userId, int $groupId): bool
    {
        $result = $this->findBy($this->table, ['user_id' => $userId, 'group_id' => $groupId]);

        return !empty($result);
    }

    public function deleteUserFromGroup(int $userId, $groupId): bool{
        try {
            $sql = "DELETE FROM {$this->table} WHERE user_id = ? AND group_id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            return $stmt->execute([$userId, $groupId]);
        } catch (\Throwable $e) {
            error_log("BaseRepository::delete error: " . $e->getMessage());
            return false;
        }
    }
}
