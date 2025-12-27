<?php

namespace Src\Services;


use Src\Core\App;


/**
 * Сервис для управления группами пользователей.
 * Содержит логику создания, обновления, удаления групп и управления участниками.
 */
class GroupService
{
    /**
     * Проверяет, является ли пользователь администратором.
     * 
     * @param int $userId IВ пользователя
     * @return bool True, если пользователь админ 
     */
    public function isAdmin(string $userLogin): bool
    {
        try {
            $userRepo = App::getService('user_repository');
            // Используем find из BaseRepository 
            $user = $userRepo->findForAuth($userLogin);
            // Возвращаем True если логин равен "admin"
            return $user && $user['login'] === 'admin';
        } catch (\Throwable $e) {
            error_log("GroupService::isAdmin error: " . $e->getMessage());
            return false;
        }
    }

    // CRUD для групп 
    public function createGroup(string $name): bool
    {
        try {
            $groupRepo = App::getService('user_group_repository');

            // Проверка на существование уже такой группы 
            if ($groupRepo->findBy($groupRepo->getTable(),  ['name' => $name])) {
                error_log("GroupService::createGroup: Group with name '$name' already exist.");
                return false;
            }

            $result = $groupRepo->create(['name' => $name]);
            if (!$result) {
                error_log("GroupService::createGroup: Group '$name' is failed to create.");
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("GroupService::createGroup error: " . $e->getMessage());
            return false;
        }
    }

    public function updateGroup(int $id, string $newName): bool
    {
        try {
            $groupRepo = App::getService('user_group_repository');

            //Проверяем не существует ли группа с новым именем 
            $existingGroup = $groupRepo->findByName($newName);
            if ($existingGroup) {
                error_log("GroupService::updateGroup: Another group already uses name '$newName'.");
                return false;
            }

            $result = $groupRepo->update($id, ['name' => $newName]);
            if (!$result) {
                error_log("GroupService::updateGroup: Failed to update group ID '$id'.");
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("GroupService::updateGroup error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteGroup(int $id): bool
    {
        try {
            $groupRepo = App::getService('user_group_repository');
            // CASCADE в БД автоматически удалит связи в user_group_members и shared_resources_by_group 
            $result = $groupRepo->delete($id);
            if (!$result) {
                error_log("GroupService::deleteGroup: Group ID '$id' deleted is failed.");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("GroupService::deleteGroup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Добавляет пользователя в группу.
     * 
     * @param int $userId ID пользователя 
     * @param int $groupId ID группы 
     * @return bool True, если успешно добавлен или уже состоял в группе 
     */
    public function addUserToGroup(int $userId, int $groupId): bool
    {
        try {
            $memberRepo = App::getService('user_group_member_repository');

            // Проверяем , не состоит ли пользователь уже в группе 
            if ($memberRepo->isUserInGroup($userId, $groupId)) {
                return true;
            }

            $result = $memberRepo->create(['user_id' => $userId, 'group_id' => $groupId]);
            if (!$result) {
                error_log("GroupService::addUserToGroup: Failed to add user ID '$userId' added to group '$groupId'.");
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("GroupService::addUserToGroup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет пользователя из группы 
     * 
     * @param int $userId ID пользователя
     * @param int $groupId ID группы 
     * @return bool True, если успешно удален или не состоял в группе
     */
    public function removeUserFromGroup(int $userId, int $groupId): bool
    {
        try {
            $memberRepo = App::getService('user_group_member_repository');

            // Находим связь через findBy 
            $relations = $memberRepo->isUserInGroup($userId, $groupId);
            if ($relations == true) {
                $result = $memberRepo->deleteUserFromGroup($userId, $groupId);
            } else {
                error_log("GroupService::deleteUserFromGroup: Failed to remove user ID '$userId' from group ID '$groupId'.");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("GroupService::deleteUserFromGroup error: " . $e->getMessage());
            return false;
        }
    }

    // Методы для получения данных 
    public function getAllGroups(): array
    {
        try {
            $groupRepo = App::getService('user_group_repository');
            // Используем findAll из BaseRepository 
            $groups = $groupRepo->findAllGroups();

            return $groups;
        } catch (\Throwable $e) {
            error_log("GroupService::getAllGroups error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllUsersExcludingAdmin(): array
    {
        try {
            $userRepo = App::getService('user_repository');
            // Прямой SQL запрос, т.к. BaseRepository не поддерживает сложные условия в findBy
            $sql = 'SELECT id, email, login FROM users WHERE login !=="admin"';
            $stmt = $userRepo->db->getConnection()->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $users;
        } catch (\Throwable $e) {
            error_log("GroupService::getAllUsersExcludingAdmin error: " . $e->getMessage());
            return [];
        }
    }

    public function getUsersInGroup(int $groupId): array
    {
        try {
            $memberRepo = App::getService('user_group_member_repository');
            $userRepo = App::getService('user_repository');

            // Получаем ID пользователей в группе 
            $userIds = $memberRepo->getUserIdsByGroupId($groupId);
            $users = [];
            foreach ($userIds as $userId) {
                // Находим данные каждого пользователя по ID 
                $user = $userRepo->find($userRepo->getTable(), $userId); // Используем find is BaseRepository
                if ($user) {
                    $users[] = $user;
                }
            }
            return $users;
        } catch (\Throwable $e) {
            error_log("GroupService::getUsersInGroup error: " . $e->getMessage());
            return [];
        }
    }
}
