<?php

namespace Src\Repositories;

class SharedResourceByGroupRepository extends BaseRepository
{
    protected string $table = 'shared_resources_by_group';

    /**
     * Проверяет, есть ли у пользователя доступ к ресурсу через его группы.
     *
     * @param int $userId ID пользователя
     * @param string $resourceType Тип ресурса ('file' или 'folder')
     * @param int $resourceId ID ресурса
     * @return bool True, если доступ есть, иначе False
     */
    public function hasAccessByGroup(int $userId, string $resourceType, int $resourceId): bool
    {
        $sql = "
            SELECT 1 FROM {$this->table} srg
            JOIN user_group_members ugm ON srg.group_id = ugm.group_id
            WHERE srg.resource_type = ? AND srg.resource_id = ? AND ugm.user_id = ?
            LIMIT 1 
        ";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$resourceType, $resourceId, $userId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Получает список ресурсов, расшаренных с группами, в которых состоит пользователь.
     *
     * @param int $userId ID пользователя
     * @return array Массив ресурсов
     */
    public function getResourcesSharedWithUserGroups(int $userId): array
    {
        $sql = "
            SELECT srg.resource_type, srg.resource_id, srg.permissions, ug.name as group_name
            FROM {$this->table} srg
            JOIN user_group_members ugm ON srg.group_id = ugm.group_id
            JOIN user_groups ug ON srg.group_id = ug.id
            WHERE ugm.user_id = ?
            ORDER BY srg.resource_type, srg.resource_id
        ";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Удаляет все шаринги для конкретного ресурса.
     * Полезно при удалении файла или папки.
     *
     * @param string $resourceType Тип ресурса ('file' или 'folder')
     * @param int $resourceId ID ресурса
     * @return bool True, если успешно удалено.
     */
    public function deleteByResource(string $resourceType, int $resourceId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE resource_type = ? AND resource_id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute([$resourceType, $resourceId]);
    }
}
