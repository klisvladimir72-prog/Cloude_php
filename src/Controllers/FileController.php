<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;

class FileController
{
    public function index(Request $request, Response $response)
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $response->sendHtml('login.php');
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $folderId = (int)($_GET['folder'] ?? 0);

        $fileRepo = App::getService('file_repository');
        $folderRepo = App::getService('folder_repository');
        $sharedFileRepo = App::getService('shared_file_repository');
        $sharedFolderRepo = App::getService('shared_folder_repository');
        $shareByGroupService = App::getService('share_by_group_service');

        // --- ОСНОВНОЙ КОД ДЛЯ ПОЛУЧЕНИЯ И ОБЪЕДИНЕНИЯ РЕСУРСОВ ---
        // 1. Получаем собственные элементы
        if ($folderId === 0) {
            $ownFiles = $fileRepo->findBy('files', ['folder_id' => null, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => null, 'user_id' => $userId]);
        } else {
            // Проверка доступа: либо я владелец, либо папка расшарена мне (email или группа)
            $currentFolder = $folderRepo->find('folders', $folderId);
            if (!$currentFolder) {
                http_response_code(404);
                $response->sendHtml('layout.php', ['content' => '<p>Папка не найдена.</p>']);
                return;
            }

            $isOwner = $currentFolder['user_id'] === $userId;
            $isSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId, 'shared_with_email' => $_SESSION['email']]));
            $isSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $folderId);

            if (!($isOwner || $isSharedToMeByEmail || $isSharedToMeByGroup)) {
                http_response_code(403);
                $response->sendHtml('layout.php', ['content' => '<p>Нет доступа к папке.</p>']);
                return;
            }

            $ownFiles = $fileRepo->findBy('files', ['folder_id' => $folderId, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId, 'user_id' => $userId]);
        }

        // Явно помечаем собственные элементы
        foreach ($ownFiles as &$file) {
            $file['is_shared'] = false;
            $file['is_shared_by_group'] = false;
            $file['group_name'] = null;
            $file['permissions'] = null;
        }
        foreach ($ownFolders as &$folder) {
            $folder['is_shared'] = false;
            $folder['is_shared_by_group'] = false;
            $folder['group_name'] = null;
            $folder['permissions'] = null;
        }

        // 2. Получаем элементы, расшаренные мне по email
        $sharedFileEntries = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $_SESSION['email']]);
        $sharedFileDetails = [];
        foreach ($sharedFileEntries as $entry) {
            $originalFile = $fileRepo->find('files', $entry['file_id']);
            if ($originalFile && $originalFile['user_id'] !== $userId) {
                if (!isset($sharedFileDetails[$entry['file_id']])) {
                    $sharedFileDetails[$entry['file_id']] = [
                        'id' => $originalFile['id'],
                        'original_name' => $originalFile['original_name'],
                        'size' => $originalFile['size'],
                        'filename' => $originalFile['filename'],
                        'created_at' => $originalFile['created_at'],
                        'user_id' => $originalFile['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFile['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => false,
                        'group_name' => null,
                        'permissions' => null,
                        'shared_entry_id' => $entry['id'],
                        'folder_id' => $originalFile['folder_id']
                    ];
                }
            }
        }

        $sharedFolderEntries = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $_SESSION['email']]);
        $sharedFolderDetails = [];
        foreach ($sharedFolderEntries as $entry) {
            $originalFolder = $folderRepo->find('folders', $entry['folder_id']);
            if ($originalFolder && $originalFolder['user_id'] !== $userId) {
                if (!isset($sharedFolderDetails[$entry['folder_id']])) {
                    $sharedFolderDetails[$entry['folder_id']] = [
                        'id' => $originalFolder['id'],
                        'name' => $originalFolder['name'],
                        'created_at' => $originalFolder['created_at'],
                        'user_id' => $originalFolder['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => false,
                        'group_name' => null,
                        'permissions' => null,
                        'shared_entry_id' => $entry['id'],
                        'parent_id' => $originalFolder['parent_id']
                    ];
                }
            }
        }

        // 3. Получаем элементы, расшаренные мне по группе
        $sharedByGroupResources = $shareByGroupService->getResourcesSharedWithUserGroups($userId);
        $sharedByGroupFileDetails = [];
        $sharedByGroupFolderDetails = [];

        foreach ($sharedByGroupResources as $resource) {
            if ($resource['resource_type'] === 'file') {
                $originalFile = $fileRepo->find('files', $resource['resource_id']);
                if ($originalFile && $originalFile['user_id'] !== $userId) {
                    if (!isset($sharedByGroupFileDetails[$resource['resource_id']])) {
                        $sharedByGroupFileDetails[$resource['resource_id']] = [
                            'id' => $originalFile['id'],
                            'original_name' => $originalFile['original_name'],
                            'size' => $originalFile['size'],
                            'filename' => $originalFile['filename'],
                            'created_at' => $originalFile['created_at'],
                            'user_id' => $originalFile['user_id'],
                            'owner_email' => $this->getUserEmailById($originalFile['user_id']),
                            'is_shared' => true,
                            'is_shared_by_group' => true,
                            'group_name' => $resource['group_name'],
                            'permissions' => $resource['permissions'],
                            'folder_id' => $originalFile['folder_id']
                        ];
                    }
                }
            } elseif ($resource['resource_type'] === 'folder') {
                $originalFolder = $folderRepo->find('folders', $resource['resource_id']);
                if ($originalFolder && $originalFolder['user_id'] !== $userId) {
                    if (!isset($sharedByGroupFolderDetails[$resource['resource_id']])) {
                        $sharedByGroupFolderDetails[$resource['resource_id']] = [
                            'id' => $originalFolder['id'],
                            'name' => $originalFolder['name'],
                            'created_at' => $originalFolder['created_at'],
                            'user_id' => $originalFolder['user_id'],
                            'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                            'is_shared' => true,
                            'is_shared_by_group' => true,
                            'group_name' => $resource['group_name'],
                            'permissions' => $resource['permissions'],
                            'parent_id' => $originalFolder['parent_id']
                        ];
                    }
                }
            }
        }

        // 4. Объединяем все элементы
        $allFiles = [];
        $allFolders = [];

        // Добавляем собственные
        foreach ($ownFiles as $file) {
            $allFiles[$file['id']] = $file;
        }
        foreach ($ownFolders as $folder) {
            $allFolders[$folder['id']] = $folder;
        }

        // Добавляем расшаренные по email (только те, что в нужной папке)
        if ($folderId === 0) {
            foreach ($sharedFileDetails as $file) {
                if ($file['folder_id'] === null) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($sharedFolderDetails as $folder) {
                if ($folder['parent_id'] === null) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        } else {
            foreach ($sharedFileDetails as $file) {
                if ($file['folder_id'] == $folderId) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($sharedFolderDetails as $folder) {
                if ($folder['parent_id'] == $folderId) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // Добавляем расшаренные по группе (только те, что в нужной папке)
        if ($folderId === 0) {
            foreach ($sharedByGroupFileDetails as $file) {
                if ($file['folder_id'] === null) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($sharedByGroupFolderDetails as $folder) {
                if ($folder['parent_id'] === null) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        } else {
            foreach ($sharedByGroupFileDetails as $file) {
                if ($file['folder_id'] == $folderId) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($sharedByGroupFolderDetails as $folder) {
                if ($folder['parent_id'] == $folderId) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // Преобразуем обратно в индексированные массивы
        $allFiles = array_values($allFiles);
        $allFolders = array_values($allFolders);

        // --- КОНЕЦ ОСНОВНОГО КОДА ---

        // Получаем хлебные крошки
        $breadcrumbs = $this->getBreadcrumbs($folderId, $folderRepo);

        // Отправляем данные в шаблон
        $response->sendHtml('dashboard.php', [
            'files' => $allFiles,
            'folders' => $allFolders,
            'currentFolder' => $currentFolder ?? null,
            // 'isCurrentFolderShared' => $isSharedToMeByEmail || $isSharedToMeByGroup,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    // Вспомогательный метод для рекурсивного получения содержимого папки (email)
    private function getRecursiveContent(int $folderId, int $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo)
    {
        $files = [];
        $folders = [];

        $childFiles = $fileRepo->findBy('files', ['folder_id' => $folderId]);
        $childFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId]);

        foreach ($childFiles as $file) {
            $file['is_shared'] = true;
            $file['is_shared_by_group'] = false;
            $file['owner_email'] = $this->getUserEmailById($file['user_id']);
            $files[$file['id']] = $file;
        }

        foreach ($childFolders as $folder) {
            $folder['is_shared'] = true;
            $folder['is_shared_by_group'] = false;
            $folder['owner_email'] = $this->getUserEmailById($folder['user_id']);
            $folders[$folder['id']] = $folder;

            $subContent = $this->getRecursiveContent($folder['id'], $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo);
            foreach ($subContent['files'] as $subFile) {
                $files[$subFile['id']] = $subFile;
            }
            foreach ($subContent['folders'] as $subFolder) {
                $folders[$subFolder['id']] = $subFolder;
            }
        }

        return [
            'files' => $files,
            'folders' => $folders
        ];
    }

    // Вспомогательный метод для рекурсивного получения содержимого папки (группа)
    private function getRecursiveContentByGroup(int $folderId, int $userId, $fileRepo, $folderRepo, $shareByGroupService)
    {
        $files = [];
        $folders = [];

        $childFiles = $fileRepo->findBy('files', ['folder_id' => $folderId]);
        $childFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId]);

        foreach ($childFiles as $file) {
            if ($shareByGroupService->hasAccessByGroup($userId, 'file', $file['id'])) {
                $file['is_shared'] = true;
                $file['is_shared_by_group'] = true;
                $file['owner_email'] = $this->getUserEmailById($file['user_id']);
                $file['group_name'] = null; // Можно получить, но сложно
                $file['permissions'] = null; // Можно получить, но сложно
                $files[$file['id']] = $file;
            }
        }

        foreach ($childFolders as $folder) {
            if ($shareByGroupService->hasAccessByGroup($userId, 'folder', $folder['id'])) {
                $folder['is_shared'] = true;
                $folder['is_shared_by_group'] = true;
                $folder['owner_email'] = $this->getUserEmailById($folder['user_id']);
                $folder['group_name'] = null; // Можно получить, но сложно
                $folder['permissions'] = null; // Можно получить, но сложно
                $folders[$folder['id']] = $folder;

                $subContent = $this->getRecursiveContentByGroup($folder['id'], $userId, $fileRepo, $folderRepo, $shareByGroupService);
                foreach ($subContent['files'] as $subFile) {
                    $files[$subFile['id']] = $subFile;
                }
                foreach ($subContent['folders'] as $subFolder) {
                    $folders[$subFolder['id']] = $subFolder;
                }
            }
        }

        return [
            'files' => $files,
            'folders' => $folders
        ];
    }

    // Вспомогательный метод для получения email пользователя по ID
    private function getUserEmailById(int $id): string
    {
        $userRepo = App::getService('user_repository');
        $user = $userRepo->find('users', $id);
        return $user['email'] ?? 'unknown';
    }

    // --- Остальные методы остаются без изменений ---
    public function upload(Request $request, Response $response)
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

            if ($folderId === '' || $folderId === 'null' || $folderId === 'undefined') {
                $folderId = null;
            }

            $service = App::getService('file_service');
            $result = $service->handleUpload($data, $_FILES, $_SESSION['user_id'], $folderId);

            if ($result['success']) {
                $response->setData($result);
            } else {
                http_response_code(400);
                $response->setData($result);
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

    public function download(Request $request, Response $response)
    {
        $fileName = $_GET['file'] ?? null;

        if (!$fileName) {
            http_response_code(400);
            $response->setData(['error' => 'Имя файла не указано']);
            $response->sendJson();
            return;
        }

        $fileName = basename($fileName);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            $response->setData(['error' => 'Требуется аутентификация']);
            $response->sendJson();
            return;
        }

        $userId = $_SESSION['user_id'];

        $service = App::getService('file_service');
        $downloadData = $service->prepareDownload($fileName, $userId);

        if (!$downloadData) {
            http_response_code(404);
            $response->setData(['error' => 'Файл не найден или нет прав для доступа']);
            $response->sendJson();
            return;
        }

        $fileRecord = $downloadData['file_record'];
        $filePath = $downloadData['file_path'];
        $originalName = $fileRecord['original_name'] ?? basename($filePath);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $originalName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($filePath);
        exit();
    }

    public function delete(Request $request, Response $response)
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

            if (!$fileId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла не указан']);
                $response->sendJson();
                return;
            }

            $service = App::getService('file_service');
            $success = $service->deleteFile($fileId, $_SESSION['user_id']);

            if ($success) {
                $response->setData(['success' => true, 'message' => 'Файл удалён']);
            } else {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Нет прав на удаление']);
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

    private function getBreadcrumbs(int $folderId, $folderRepo)
    {
        $breadcrumbs = [];
        $currentId = $folderId;

        while ($currentId !== null) {
            $folder = $folderRepo->find('folders', $currentId);
            if (!$folder) break;

            array_unshift($breadcrumbs, [
                'id' => $folder['id'],
                'name' => $folder['name']
            ]);

            $currentId = $folder['parent_id'];
        }

        return $breadcrumbs;
    }

    public function shared(Request $request, Response $response)
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $response->sendHtml('login.php');
            return;
        }

        $userId = $_SESSION['user_id'];
        $sharedFileRepo = App::getService('shared_file_repository');
        $sharedFolderRepo = App::getService('shared_folder_repository');
        $fileRepo = App::getService('file_repository');
        $folderRepo = App::getService('folder_repository');
        $shareByGroupService = App::getService('share_by_group_service');

        // --- Старые расшаренные (email) ---
        $sharedFilesByEmail = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFilesByEmail as &$file) {
            $originalFile = $fileRepo->find('files', $file['file_id']);
            $file['original_name'] = $originalFile['original_name'] ?? 'Неизвестный файл';
            $file['filename'] = $originalFile['filename'] ?? '';
            $file['is_shared_by_group'] = false;
        }

        $sharedFoldersByEmail = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFoldersByEmail as &$folder) {
            $originalFolder = $folderRepo->find('folders', $folder['folder_id']);
            $folder['name'] = $originalFolder['name'] ?? 'Неизвестная папка';
            $folder['is_shared_by_group'] = false;
        }

        // --- Новые расшаренные (группы) ---
        $sharedResourcesByGroup = $shareByGroupService->getResourcesSharedWithUserGroups($userId);
        $sharedFilesByGroup = [];
        $sharedFoldersByGroup = [];

        foreach ($sharedResourcesByGroup as $resource) {
            if ($resource['resource_type'] === 'file') {
                $originalFile = $fileRepo->find('files', $resource['resource_id']);
                if ($originalFile) {
                    $sharedFilesByGroup[] = [
                        'id' => $originalFile['id'],
                        'original_name' => $originalFile['original_name'],
                        'filename' => $originalFile['filename'],
                        'size' => $originalFile['size'],
                        'created_at' => $originalFile['created_at'],
                        'user_id' => $originalFile['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFile['user_id']),
                        'is_shared_by_group' => true,
                        'permissions' => $resource['permissions'],
                        'group_name' => $resource['group_name']
                    ];
                }
            } elseif ($resource['resource_type'] === 'folder') {
                $originalFolder = $folderRepo->find('folders', $resource['resource_id']);
                if ($originalFolder) {
                    $sharedFoldersByGroup[] = [
                        'id' => $originalFolder['id'],
                        'name' => $originalFolder['name'],
                        'created_at' => $originalFolder['created_at'],
                        'user_id' => $originalFolder['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                        'is_shared_by_group' => true,
                        'permissions' => $resource['permissions'],
                        'group_name' => $resource['group_name']
                    ];
                }
            }
        }

        $response->sendHtml('shared.php', [
            'sharedFilesByEmail' => $sharedFilesByEmail,
            'sharedFoldersByEmail' => $sharedFoldersByEmail,
            'sharedFilesByGroup' => $sharedFilesByGroup,
            'sharedFoldersByGroup' => $sharedFoldersByGroup
        ]);
    }
}
