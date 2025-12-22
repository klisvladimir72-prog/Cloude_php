<?php

namespace Src\Services;

use Src\Core\App;

/**
 * Сервис для управления шарингом файлов и папок по группам.
 * Содержит логику рекурсивного шаринга папок и проверка доступа.
 */
class ShareByGroupService
{
    /**
     * Рекурсивно шарит папку и все ее содержимое с группой.
     *
     * @param int $folderId ID папки для шаринга
     * @param int $groupId ID группы
     * @param string $permissions уровень доступа
     * @param int $sharedByUserId ID пользователя который шарит
     * @return bool True, если успешно
     */
    public function shareFolderRecursively(int $folderId, int $groupId, string $permissions, int $sharedByUserId): bool
    {
        $pdo = null;
        try {
            $shareRepo = App::getService('shared_resource_by_group_repository');
            $folderRepo = App::getService('folder_repository');
            $fileRepo = App::getService('file_repository');

            // Используем транзакцию для обеспечения целостности данных
            $pdo = $shareRepo->db->getConnection(); // Получаем PDO из Db
            $pdo->beginTransaction();

            // Шарим саму папку
            $this->shareResource('folder', $folderId, $groupId, $permissions, $sharedByUserId, $shareRepo);

            // Обходим рекурсивно подпапки и файлы
            $this->processFolderContentsRecursively($folderId, $groupId, $permissions, $sharedByUserId, $folderRepo, $fileRepo, $shareRepo);

            // если все прошло успешно то фиксируем изменения
            $pdo->commit();

            return true;
        } catch (\Exception $e) {
            // Если произошла ошибка откатываем все изменения
            if ($pdo !== null) {
                $pdo->rollback();
            }
            error_log("ShareByGroupService::shareFolderRecursively error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Внутренний метод для добавления или обновления записи шаринга.
     * Принимает репозиторий как параметр для доступа к его свойству $db.
     *
     * @param string $type
     * @param integer $id
     * @param integer $groupId
     * @param string $perms
     * @param integer $sharedByUserId
     * @param [type] $shareRepo
     * @return void
     */
    private function shareResource(string $type, int $id, int $groupId, string $perms, int $sharedByUserId, $shareRepo): void
    {
        // Проверяем, не шарили ли мы уже этот ресурс с этой группой
        $existing = $shareRepo->findBy($shareRepo->table, [
            'resource_type' => $type,
            'resource_id' => $id,
            'group_id' => $groupId
        ]);

        if (!empty($existing)) {
            // Если шарили то обновляем уровень доступа
            $shareRepo->update($existing[0]['id'], ['permissions' => $perms]);
        } else {
            // Если не шарили то создаем новую запись
            $shareRepo->create([
                'resource_type' => $type,
                'resource_id' => $id,
                'group_id' => $groupId,
                'permissions' => $perms,
                'shared_by_user_id' => $sharedByUserId
            ]);
        }
    }

    /**
     * Внутренний метод для рекурсивного обхода содержимого папки.
     * Принимает репозитории как параметры для избежания повторного вызова App::getService().
     *
     * @param integer $folderId
     * @param integer $groupId
     * @param string $permissions
     * @param integer $sharedByUserId
     * @param [type] $folderRepo
     * @param [type] $fileRepo
     * @param [type] $shareRepo
     * @return void
     */
    private function processFolderContentsRecursively(int $folderId, int $groupId, string $permissions, int $sharedByUserId, $folderRepo, $fileRepo, $shareRepo): void
    {
        // Получаем все подпапки текущей папки
        $subFolders = $folderRepo->findBy($folderRepo->table, ['parent_folder_id' => $folderId]);
        foreach ($subFolders as $subFolder) {
            // Шарим подпапку
            $this->shareResource('folder', $subFolder['id'], $groupId, $permissions, $sharedByUserId, $shareRepo);
            // Рекурсивно обрабатываем содержимое подпапки
            $this->processFolderContentsRecursively($subFolder['id'], $groupId, $permissions, $sharedByUserId, $folderRepo, $fileRepo, $shareRepo);
        }

        // Получаем все файлы текущей папки
        $files = $fileRepo->findBy($fileRepo->table, ['folder_id' => $folderId]);
        foreach ($files as $file) {
            $this->shareResource('file', $file['file_id'], $groupId, $permissions, $sharedByUserId, $shareRepo);
        }
    }

    /**
     * Удаляет все шаринги для конкретного ресурса (например при удалении файла/папки).
     * Вызывается из соответствующего сервиса удаления.
     *
     * @param string $resourceType тип ресурса ('file' or 'folder')
     * @param integer $resourceId ID ресурса
     * @return boolean True , если удаление прошло успешно
     */
    public function removeSharesForResource(string $resourceType, int $resourceId): bool
    {
        try {
            $shareRepo = App::getService('shared_resource_by_group_repository');
            $result = $shareRepo->deleteByResource($resourceType, $resourceId);

            if (!$result) {
                error_log("ShareByGroupService::removeSharesForResource: No shares found or failed to remove for '$resourceType' ID '$resourceId'.");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("ShareByGroupService::removeSharesForResource error: " . $e->getMessage());
            return false;
        }
    }

    // --- Новый публичный метод для получения расшаренных ресурсов ---
    /**
     * Получает список ресурсов, расшаренных с группами, в которых состоит пользователь.
     *
     * @param int $userId ID пользователя
     * @return array Массив ресурсов
     */
    public function getResourcesSharedWithUserGroups(int $userId): array
    {
        $shareRepo = App::getService('shared_resource_by_group_repository');
        return $shareRepo->getResourcesSharedWithUserGroups($userId); // Вызываем метод из репозитория
    }
    // ---

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
        $shareRepo = App::getService('shared_resource_by_group_repository');
        return $shareRepo->hasAccessByGroup($userId, $resourceType, $resourceId); // Вызываем метод из репозитория
    }
}
