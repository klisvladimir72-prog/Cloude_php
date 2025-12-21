// File: Src/Controllers/GroupController.php

<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Services\GroupService;

/**
 * Контроллер для обработки запросов, связанных с управлением группами.
 * Только администратор может выполнять действия.
 */
class GroupController
{
    private GroupService $groupService;

    public function __construct()
    {
        $this->groupService = App::getService('group_service');
    }

    /**
     * Отображает страницу управления группами для администратора.
     */
    public function showAdminPanel(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->sendHtml('error.php', ['message' => 'Доступ запрещен']);
            return;
        }

        $groups = $this->groupService->getAllGroups();
        $allUsers = $this->groupService->getAllUsersExcludingAdmin();

        // Создаем ассоциативный массив пользователей по группам
        $usersInGroups = [];
        foreach ($groups as $group) {
            $usersInGroups[$group['id']] = $this->groupService->getUsersInGroup($group['id']);
        }

        $response->sendHtml('admin_groups.php', [
            'groups' => $groups,
            'allUsers' => $allUsers,
            'usersInGroups' => $usersInGroups,
        ]);
    }

    /**
     * Обрабатывает запрос на создание новой группы.
     */
    public function createGroup(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            $response->setData(['error' => 'Имя группы обязательно']);
            $response->sendJson();
            return;
        }

        if ($this->groupService->createGroup($name)) {
            $response->setData(['success' => true, 'message' => 'Группа создана']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Не удалось создать группу']);
        }
        $response->sendJson();
    }

    /**
     * Обрабатывает запрос на обновление имени группы.
     */
    public function updateGroup(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $id = (int)($data['id'] ?? 0);
        $newName = trim($data['new_name'] ?? '');

        if ($id <= 0 || empty($newName)) {
            http_response_code(400);
            $response->setData(['error' => 'ID и новое имя обязательны']);
            $response->sendJson();
            return;
        }

        if ($this->groupService->updateGroup($id, $newName)) {
            $response->setData(['success' => true, 'message' => 'Группа обновлена']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Не удалось обновить группу']);
        }
        $response->sendJson();
    }

    /**
     * Обрабатывает запрос на удаление группы.
     */
    public function deleteGroup(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            $response->setData(['error' => 'ID группы обязателен']);
            $response->sendJson();
            return;
        }

        if ($this->groupService->deleteGroup($id)) {
            $response->setData(['success' => true, 'message' => 'Группа удалена']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Не удалось удалить группу']);
        }
        $response->sendJson();
    }

    /**
     * Обрабатывает запрос на добавление пользователя в группу.
     */
    public function addUserToGroup(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $userIdToAdd = (int)($data['user_id'] ?? 0);
        $groupId = (int)($data['group_id'] ?? 0);

        if ($userIdToAdd <= 0 || $groupId <= 0) {
            http_response_code(400);
            $response->setData(['error' => 'ID пользователя и группы обязательны']);
            $response->sendJson();
            return;
        }

        if ($this->groupService->addUserToGroup($userIdToAdd, $groupId)) {
            $response->setData(['success' => true, 'message' => 'Пользователь добавлен в группу']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Не удалось добавить пользователя в группу']);
        }
        $response->sendJson();
    }

    /**
     * Обрабатывает запрос на удаление пользователя из группы.
     */
    public function removeUserFromGroup(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId || !$this->groupService->isAdmin($userId)) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $userIdToRemove = (int)($data['user_id'] ?? 0);
        $groupId = (int)($data['group_id'] ?? 0);

        if ($userIdToRemove <= 0 || $groupId <= 0) {
            http_response_code(400);
            $response->setData(['error' => 'ID пользователя и группы обязательны']);
            $response->sendJson();
            return;
        }

        if ($this->groupService->removeUserFromGroup($userIdToRemove, $groupId)) {
            $response->setData(['success' => true, 'message' => 'Пользователь удален из группы']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Не удалось удалить пользователя из группы']);
        }
        $response->sendJson();
    }
}
