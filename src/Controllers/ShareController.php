<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;

class ShareController
{
    public function shareFile(Request $request, Response $response)
    {
        try {
            session_start();
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                $response->setData(['error' => 'Требуется аутентификация']);
                $response->sendJson();
                return;
            }

            $data = $request->getData();
            $fileId = $data['file_id'] ?? null;
            $userIds = $data['user_ids'] ?? [];

            if (!$fileId || empty($userIds)) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла и пользователи обязательны']);
                $response->sendJson();
                return;
            }

            $fileRepo = App::getService('file_repository');
            $sharedFileRepo = App::getService('shared_file_repository');
            $userRepo = App::getService('user_repository');

            $file = $fileRepo->find('files', $fileId);

            if (!$file || $file['user_id'] !== $_SESSION['user_id']) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Нет прав на файл']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

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
                        'shared_by' => $_SESSION['user_id'],
                        'shared_with_email' => $sharedWithEmail
                    ]);
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                $response->setData([
                    'success' => true,
                    'message' => "Файл успешно поделён с {$successCount} новыми пользователями."
                ]);
            } else {
                $response->setData([
                    'success' => true,
                    'message' => "Файл уже был поделён с указанными пользователями."
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
            session_start();
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                $response->setData(['error' => 'Требуется аутентификация']);
                $response->sendJson();
                return;
            }

            $data = $request->getData();
            $folderId = $data['folder_id'] ?? null;
            $userIds = $data['user_ids'] ?? [];

            if (!$folderId || empty($userIds)) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки и пользователи обязательны']);
                $response->sendJson();
                return;
            }

            $folderRepo = App::getService('folder_repository');
            $sharedFolderRepo = App::getService('shared_folder_repository');
            $userRepo = App::getService('user_repository');

            $folder = $folderRepo->find('folders', $folderId);

            if (!$folder || $folder['user_id'] !== $_SESSION['user_id']) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Нет прав на папку']);
                $response->sendJson();
                return;
            }

            $successCount = 0;

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
                        'shared_by' => $_SESSION['user_id'],
                        'shared_with_email' => $sharedWithEmail
                    ]);
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                $response->setData([
                    'success' => true,
                    'message' => "Папка успешно поделена с {$successCount} новыми пользователями."
                ]);
            } else {
                $response->setData([
                    'success' => true,
                    'message' => "Папка уже была поделена с указанными пользователями."
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
            session_start();
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                $response->setData(['error' => 'Требуется аутентификация']);
                $response->sendJson();
                return;
            }

            $userRepo = App::getService('user_repository');

            $users = $userRepo->findAll('users');
            if (!is_array($users)) {
                $users = [];
            }
            $filteredUsers = array_filter($users, fn($user) => $user['id'] !== $_SESSION['user_id']);
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

    // --- Новый метод для отображения админ-панели групп ---
    public function showAdminPanel(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        // Проверяем, является ли текущий пользователь администратором
        $userRepo = App::getService('user_repository');
        $currentUser = $userRepo->find('users', $userId);
        if (!$currentUser || $currentUser['login'] !== 'admin') {
            http_response_code(403);
            $response->sendHtml('error.php', ['message' => 'Доступ запрещен']);
            return;
        }

        // Получаем все сервисы, необходимые для отображения панели
        $groupService = App::getService('group_service');

        $groups = $groupService->getAllGroups();
        $allUsers = $userRepo->getAllUsersExcludingAdmin();

        // Создаем ассоциативный массив пользователей по группам
        $usersInGroups = [];
        foreach ($groups as $group) {
            $usersInGroups[$group['id']] = $groupService->getUsersInGroup($group['id']);
        }

        $response->sendHtml('admin_groups.php', [
            'groups' => $groups,
            'allUsers' => $allUsers,
            'usersInGroups' => $usersInGroups,
        ]);
    }
    // ---
}
