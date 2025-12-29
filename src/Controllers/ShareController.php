<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class ShareController
{
    public function shareFile(Request $request, Response $response)
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
            $fileId = $data['file_id'] ?? null;
            $userIds = $data['user_ids'] ?? []; // Массив ID пользователей
            $groupIds = $data['group_ids'] ?? []; // Массив ID групп

            // Проверяем, что передан хотя бы один список (пользователей или групп)
            if (!$fileId || (empty($userIds) && empty($groupIds))) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла и пользователи/группы обязательны']);
                $response->sendJson();
                return;
            }

            $fileRepo = App::getService('file_repository');
            $sharedFileRepo = App::getService('shared_file_repository');
            $userRepo = App::getService('user_repository');

            $file = $fileRepo->find('files', $fileId);

            // Проверяем, является ли пользователь владельцем файла
            if (!$file || $file['user_id'] !== $userId) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Нет прав на файл']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

            // --- Шаринг по пользователям ---
            if (!empty($userIds)) {
                foreach ($userIds as $userId) {
                    $user = $userRepo->find('users', $userId);
                    if (!$user) {
                        continue;
                    }

                    $sharedWithEmail = $user['email'];

                    $existingShare = $sharedFileRepo->findBy('shared_files', [
                        'file_id' => $fileId,
                        'shared_with_email' => $sharedWithEmail
                    ]);

                    if (empty($existingShare)) {
                        $sharedFileRepo->create([
                            'file_id' => $fileId,
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
                $shareByGroupService = App::getService('share_by_group_service');
                $permissions = 'read'; // Установите нужный уровень доступа, возможно, из $data

                foreach ($groupIds as $groupId) {
                    // Проверяем, существует ли группа (опционально, но рекомендуется)
                    $groupRepo = App::getService('user_group_repository');
                    $group = $groupRepo->find('user_groups', $groupId);
                    if (!$group) {
                        continue; // Пропускаем несуществующую группу
                    }

                    // Вызываем метод для шаринга файла с группой
                    // Этот метод уже проверяет транзакции и т.д.
                    $wasShared = $shareByGroupService->shareFile($fileId, $groupId, $permissions, $userId);
                    if ($wasShared) {
                        $successCount++; // Считаем как успешный шаринг, хотя это может быть обновление
                    }
                }
            }
            // ---

            if ($successCount > 0) {
                $response->setData([
                    'success' => true,
                    'message' => "Файл успешно поделён с {$successCount} сущностями (пользователями или группами)."
                ]);
            } else {
                $response->setData([
                    'success' => true, // Успех, но не добавлено новых
                    'message' => "Файл уже был поделён с указанными сущностями или не были переданы новые."
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

            $folderRepo = App::getService('folder_repository');
            $sharedFolderRepo = App::getService('shared_folder_repository');
            $userRepo = App::getService('user_repository');

            $folder = $folderRepo->find($folderRepo->getTable(), $folderId);

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
                    $user = $userRepo->find('users', $userId);
                    if (!$user) {
                        continue;
                    }

                    $sharedWithEmail = $user['email'];

                    $existingShare = $sharedFolderRepo->findBy('shared_folders', [
                        'folder_id' => $folderId,
                        'shared_with_email' => $sharedWithEmail
                    ]);

                    if (empty($existingShare)) {
                        $sharedFolderRepo->create([
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
                $shareByGroupService = App::getService('share_by_group_service');
                $permissions = 'read'; // Установите нужный уровень доступа

                foreach ($groupIds as $groupId) {
                    $groupRepo = App::getService('user_group_repository');
                    $group = $groupRepo->find('user_groups', $groupId);
                    if (!$group) {
                        continue;
                    }

                    // Вызываем метод для рекурсивного шаринга папки с группой
                    $wasShared = $shareByGroupService->shareFolderRecursively($folderId, $groupId, $permissions, $userId);
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

    // --- Новый метод для получения списка групп ---
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
    // ---

    // --- Метод для отображения админ-панели групп ---
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
            'id'=> $userId
        ]);
    }
}
