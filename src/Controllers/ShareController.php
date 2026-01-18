<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class ShareController
{
    private $sharedFileRepo;
    private $sharedFolderRepo;
    private $userRepo;
    private $fileRepo;
    private $fileService;
    private $groupRepo;
    private $shareByGroupService;
    private $sharedByGroupResourceRepo;
    private $folderRepo;

    public function __construct()
    {
        $this->sharedFileRepo = App::getService('shared_file_repository');
        $this->userRepo = App::getService('user_repository');
        $this->fileRepo = App::getService('file_repository');
        $this->fileService = App::getService('file_service');
        $this->groupRepo = App::getService('user_group_repository');
        $this->shareByGroupService = App::getService('share_by_group_service');
        $this->sharedFolderRepo = App::getService('shared_folder_repository');
        $this->sharedByGroupResourceRepo = App::getService('shared_resources_by_group');
        $this->folderRepo = App::getService('folder_repository');
    }

    /**
     * Шаринг файла с пользователями и с группами по id[]
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function shareFile(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            $user = $authResult['user'];

            $data = $request->getQueryParamsAll();

            if (!isset($data['id']) || empty($data['id'])) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла не передано.']);
                $response->sendJson();
                return;
            }

            if (!isset($data['user_id']) || count(array_filter($data['user_id'])) === 0) {
                $userIds = [];
            } else {
                $userIds = $data['user_id'];
            }

            if (!isset($data['group_id']) || count(array_filter($data['group_id'])) === 0) {
                $groupIds = [];
            } else {
                $groupIds = $data['group_id'];
            }

            $fileId = $data['id'];



            $file = $this->fileRepo->find($this->fileRepo->getTable(), $fileId);

            $isOwner = $this->fileService->isPermissions($user, $file);
            if (!$isOwner) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => 'Нет прав на шаринг файла.']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

            // --- Шаринг по пользователям ---
            if (!empty($userIds)) {
                foreach ($userIds as $userId) {
                    $user = $this->userRepo->find($this->userRepo->getTable(), $userId);
                    if (!$user) {
                        continue;
                    }

                    $existingShare = $this->sharedFileRepo->findBy(
                        $this->sharedFileRepo->getTable(),
                        [
                            'file_id' => $fileId,
                            'shared_with_email' => $user['email']
                        ]
                    );

                    if (!$existingShare) {
                        $this->sharedFileRepo->create([
                            'file_id' => $fileId,
                            'shared_by' => $userId,
                            'shared_with_email' => $user['email']
                        ]);
                        $successCount++;
                    }
                }
            }
            // ---
            $messageList = [];
            // --- Шаринг по группам ---
            if (!empty($groupIds)) {
                $permissions = 'read'; // опционально (на будущее💡)
                foreach ($groupIds as $groupId) {
                    // Проверяем, существует ли группа (опционально, но рекомендуется)
                    $group = $this->groupRepo->find($this->groupRepo->getTable(), $groupId);
                    if (!$group) {
                        continue;
                    }

                    // Вызываем метод для шаринга файла с группой
                    // Этот метод уже проверяет транзакции и т.д.
                    $wasShared = $this->shareByGroupService->shareFile($fileId, $groupId, $permissions, $userId);
                    if (isset($wasShared['success']['success'])) {

                        $successCount++; // в случае если шарили, то обновляем permissions 
                    }

                    if (isset($wasShared['success']['message'])) {
                        $messageList[] = $wasShared['success']['message'];
                    }
                }
            }
            // ---

            http_response_code(200);
            $result = [
                'success' => true
            ];

            if ($successCount > 0) {
                $result['message'] = "Файл успешно поделён с {$successCount} сущностями (пользователями или группами).";
            } else {
                $result['message'] = "Файл уже был поделён с указанными сущностями.";
            }

            // Добавляем дополнительные сообщения, если есть
            if (!empty($messageList)) {
                $result['details'] = $messageList;
            }

            $response->setData($result);
            $response->sendJson();
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
        }
    }

    /**
     * Удаляет шаринг для пользователей по `user_id[]`.
     * 
     * Удаляет шаринг для групп по `group_id[]`.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function removeShareFile(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->setData(['success' => false, 'message' => 'Пользователь не авторизован.']);
                $response->sendJson();
                return;
            }

            $user = $authResult['user'];

            $data = $request->getQueryParamsAll();

            if (!isset($data['id']) || empty($data['id'])) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла не передано.']);
                $response->sendJson();
                return;
            }

            if (!isset($data['user_id']) || count(array_filter($data['user_id'])) === 0) {
                $userIds = [];
            } else {
                $userIds = $data['user_id'];
            }

            if (!isset($data['group_id']) || count(array_filter($data['group_id'])) === 0) {
                $groupIds = [];
            } else {
                $groupIds = $data['group_id'];
            }


            $fileId = $data['id'];

            $file = $this->fileRepo->find($this->fileRepo->getTable(), $fileId);

            $isOwner = $this->fileService->isPermissions($user, $file);
            if (!$isOwner) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => 'Нет прав на удаление шаринга.']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

            if (!empty($userIds)) {
                foreach ($userIds as $userId) {
                    $user = $this->userRepo->find($this->userRepo->getTable(), $userId);
                    if (!$user) {
                        continue;
                    }

                    $existingShare = $this->sharedFileRepo->findBy(
                        $this->sharedFileRepo->getTable(),
                        [
                            'file_id' => $fileId,
                            'shared_with_email' => $user['email']
                        ]
                    );

                    if ($existingShare) {
                        $this->sharedFileRepo->delete($existingShare[0]['id']);
                        $successCount++;
                    }
                }
            }

            if (!empty($groupIds)) {
                foreach ($groupIds as $groupId) {
                    $group = $this->groupRepo->find($this->groupRepo->getTable(), $groupId);
                    if (!$group) {
                        continue;
                    }

                    $existingShare = $this->sharedByGroupResourceRepo->findBy(
                        $this->sharedByGroupResourceRepo->getTable(),
                        [
                            'resource_id' => $fileId,
                            'group_id' => $group['id']
                        ]
                    );

                    if ($existingShare) {
                        $this->sharedByGroupResourceRepo->delete($existingShare[0]['id']);
                        $successCount++;
                    }
                }
            }

            http_response_code(200);
            if ($successCount > 0) {
                $response->setData(['success' => true, 'message' => "Шаринг удален из $successCount сущностей"]);
            } else {
                $response->setData(['success' => true, 'message' => 'Файл не был расшарен для предоставленных сущностей.']);
            }
            $response->sendJson();
            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера.',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
            return;
        }
    }

    /**
     * Шаринг директории для пользователей и для групп.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function shareFolder(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            // Получаем объект пользователя
            $user = $authResult['user'];
            $userId = $user->id;

            $data = $request->getData();
            $folderId = $data['folder_id'] ?? null;
            $userIds = $data['user_ids'] ?? []; // Массив ID пользователей
            $groupIds = $data['group_ids'] ?? []; // Массив ID групп

            if (!$folderId || (empty($userIds) && empty($groupIds))) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки и пользователи/группы обязательны']);
                $response->sendJson();
                return;
            }


            $folder = $this->folderRepo->find($this->folderRepo->getTable(), $folderId);

            if (!$folder || $folder['user_id'] !== $userId) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Нет прав на папку']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

            // --- Шаринг по пользователям ---
            if (!empty($userIds)) {
                foreach ($userIds as $userId) {
                    $user = $this->userRepo->find('users', $userId);
                    if (!$user) {
                        continue;
                    }

                    $sharedWithEmail = $user['email'];

                    $existingShare = $this->sharedFolderRepo->findBy(
                        $this->sharedFolderRepo->getTable(),
                        [
                            'folder_id' => $folderId,
                            'shared_with_email' => $sharedWithEmail
                        ]
                    );

                    if (empty($existingShare)) {
                        $this->sharedFolderRepo->create([
                            'folder_id' => $folderId,
                            'shared_by' => $userId,
                            'shared_with_email' => $sharedWithEmail
                        ]);
                        $successCount++;
                    }
                }
            }
            // ---

            // --- Шаринг по группам ---
            if (!empty($groupIds)) {
                $permissions = 'read'; // Установите нужный уровень доступа

                foreach ($groupIds as $groupId) {
                    $group = $this->groupRepo->find('user_groups', $groupId);
                    if (!$group) {
                        continue;
                    }

                    // Вызываем метод для рекурсивного шаринга папки с группой
                    $wasShared = $this->shareByGroupService->shareFolderRecursively($folderId, $groupId, $permissions, $userId);
                    if ($wasShared) {
                        $successCount++; // Считаем как успешный шаринг, хотя это может быть обновление
                    }
                }
            }
            // ---

            if ($successCount > 0) {
                $response->setData([
                    'success' => true,
                    'message' => "Папка успешно поделена с {$successCount} сущностями (пользователями или группами)."
                ]);
            } else {
                $response->setData([
                    'success' => true,
                    'message' => "Папка уже была поделена с указанными сущностями или не были переданы новые."
                ]);
            }
            $response->sendJson();
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
        }
    }

    /**
     * Получение списка пользователей для расшаренного файла.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getUsersBySharedFile(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            $fileId = $request->getQueryParam('id');

            if (!$fileId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла обязательно.']);
                $response->sendJson();
                return;
            }

            $sharedFiles = $this->sharedFileRepo->findBy($this->sharedFileRepo->getTable(), ['file_id' => $fileId]);

            if (!$sharedFiles) {
                http_response_code(404);
                $response->setData(['success' => false, 'message' => 'Файл ни с кем не расшарен.']);
                $response->sendJson();
                return;
            }


            foreach ($sharedFiles as $file) {
                $user = $this->userRepo->findBy($this->userRepo->getTable(), ['email' => $file['shared_with_email']]);
                if ($user) {
                    $users[] = $user[0];
                }
            }

            http_response_code(200);
            $response->setData(['success' => true, 'users' => $users]);
            $response->sendJson();
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера.',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
            return;
        }
    }

    /**
     * Получение списка пользователей без авторизованного пользователя.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getUsers(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            // Получаем объект пользователя
            $user = $authResult['user'];
            $userId = $user->id;

            $userRepo = App::getService('user_repository');

            $users = $userRepo->findAll('users');
            if (!is_array($users)) {
                $users = [];
            }
            $filteredUsers = array_filter($users, fn($user) => $user['id'] !== $userId);
            $filteredUsers = array_values($filteredUsers);

            $response->setData(['users' => $filteredUsers]);
            $response->sendJson();
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'debug' => $e->getMessage(),
                'users' => []
            ]);
            $response->sendJson();
        }
    }

    /**
     * Получение списка групп
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getGroups(Request $request, Response $response)
    {
        try {
            if (!AuthMiddleware::handle($request, $response)) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            $groupService = App::getService('group_service');
            $groups = $groupService->getAllGroups(); // Возвращает все группы

            $response->setData(['groups' => $groups]);
            $response->sendJson();
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'debug' => $e->getMessage(),
                'groups' => []
            ]);
            $response->sendJson();
        }
    }

    /**
     * Метод для отображения админ-панели групп
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function showAdminPanel(Request $request, Response $response)
    {

        // Проверяем токен через AuthMiddleware
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        }

        // Получаем объект пользователя
        $user = $authResult['user'];
        $userId = $user->id;

        $userRepo = App::getService('user_repository');
        $currentUser = $userRepo->find('users', $userId);
        if (!$currentUser || $currentUser['login'] !== 'admin') {
            http_response_code(403);
            $response->sendHtml('error.php', ['message' => 'Доступ запрещен']);
            return;
        }

        $groupService = App::getService('group_service');

        $groups = $groupService->getAllGroups();
        $allUsers = $userRepo->getAllUsersExcludingAdmin();

        $usersInGroups = [];
        foreach ($groups as $group) {
            $usersInGroups[$group['id']] = $groupService->getUsersInGroup($group['id']);
        }

        $response->sendHtml('admin_groups.php', [
            'groups' => $groups,
            'allUsers' => $allUsers,
            'usersInGroups' => $usersInGroups,
            'login' => $user->login,
            'id' => $userId
        ]);
    }
}
