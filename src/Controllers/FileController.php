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
        $shareByGroupService = App::getService('share_by_group_service'); // <-- Новый сервис

        // --- НОВАЯ ЛОГИКА: Получение всех расшаренных по группам ---
        $sharedByGroupResources = $shareByGroupService->getResourcesSharedWithUserGroups($userId);
        $sharedByGroupFileDetails = [];
        $sharedByGroupFolderDetails = [];

        foreach ($sharedByGroupResources as $resource) {
            if ($resource['resource_type'] === 'file') {
                $originalFile = $fileRepo->find('files', $resource['resource_id']);
                if ($originalFile) { // Убрана проверка на $originalFile['user_id'] !== $userId
                    $sharedByGroupFileDetails[$resource['resource_id']] = [
                        'id' => $originalFile['id'],
                        'original_name' => $originalFile['original_name'],
                        'size' => $originalFile['size'],
                        'filename' => $originalFile['filename'],
                        'created_at' => $originalFile['created_at'],
                        'user_id' => $originalFile['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFile['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => true, // <-- Уточняем, что по группе
                        'shared_entry_id' => null, // <-- Нет ID в старой таблице
                        'folder_id' => $originalFile['folder_id'],
                        'permissions' => $resource['permissions'] // <-- Уровень доступа
                    ];
                }
            } elseif ($resource['resource_type'] === 'folder') {
                $originalFolder = $folderRepo->find('folders', $resource['resource_id']);
                if ($originalFolder) { // Убрана проверка на $originalFolder['user_id'] !== $userId
                    $sharedByGroupFolderDetails[$resource['resource_id']] = [
                        'id' => $originalFolder['id'],
                        'name' => $originalFolder['name'],
                        'created_at' => $originalFolder['created_at'],
                        'user_id' => $originalFolder['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => true, // <-- Уточняем, что по группе
                        'shared_entry_id' => null, // <-- Нет ID в старой таблице
                        'parent_id' => $originalFolder['parent_id'],
                        'permissions' => $resource['permissions'] // <-- Уровень доступа
                    ];
                }
            }
        }
        // --- КОНЕЦ НОВОЙ ЛОГИКИ ---

        // Определяем, является ли текущая папка расшаренной (если мы внутри неё)
        $currentFolder = null;
        $isCurrentFolderShared = false;
        $isCurrentFolderSharedByGroup = false; // <-- Новое поле
        if ($folderId > 0) {
            $currentFolder = $folderRepo->find('folders', $folderId);
            if (!$currentFolder) {
                http_response_code(404);
                $response->sendHtml('layout.php', ['content' => '<p>Папка не найдена.</p>']);
                return;
            }
            // Проверяем, является ли папка расшаренной мне по email
            $sharedFolderEntry = $sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId, 'shared_with_email' => $_SESSION['email']]);
            $isCurrentFolderShared = !empty($sharedFolderEntry) && $currentFolder['user_id'] !== $userId;

            // Проверяем, является ли папка расшаренной мне по группе
            $isCurrentFolderSharedByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $folderId) && $currentFolder['user_id'] !== $userId;
        }

        // --- Получение собственных элементов ---
        if ($folderId === 0) {
            $ownFiles = $fileRepo->findBy('files', ['folder_id' => null, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => null, 'user_id' => $userId]);
        } else {
            // Проверка доступа: либо я владелец, либо папка расшарена мне (email или группа)
            $isOwner = $currentFolder['user_id'] === $userId;
            $isSharedToMe = $isCurrentFolderShared || $isCurrentFolderSharedByGroup; // <-- Объединяем проверки

            if (!($isOwner || $isSharedToMe)) {
                http_response_code(403);
                $response->sendHtml('layout.php', ['content' => '<p>Нет доступа к папке.</p>']);
                return;
            }

            $ownFiles = $fileRepo->findBy('files', ['folder_id' => $folderId, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId, 'user_id' => $userId]);
        }

        // Явно помечаем собственные элементы как не расшаренные
        foreach ($ownFiles as &$file) {
            $file['is_shared'] = false;
            $file['is_shared_by_group'] = false; // <-- Новое поле
        }
        foreach ($ownFolders as &$folder) {
            $folder['is_shared'] = false;
            $folder['is_shared_by_group'] = false; // <-- Новое поле
        }

        // --- Получение расшаренных элементов (по email) ---
        // Получаем все расшаренные мне папки
        $sharedFolderEntries = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $_SESSION['email']]);
        $sharedFolderIds = [];
        $sharedFolderDetails = [];

        foreach ($sharedFolderEntries as $entry) {
            $originalFolder = $folderRepo->find('folders', $entry['folder_id']);
            if ($originalFolder && $originalFolder['user_id'] !== $userId) {
                // Проверяем, не добавили ли мы уже эту папку
                if (!isset($sharedFolderDetails[$entry['folder_id']])) {
                    $sharedFolderDetails[$entry['folder_id']] = [
                        'id' => $originalFolder['id'],
                        'name' => $originalFolder['name'],
                        'created_at' => $originalFolder['created_at'],
                        'user_id' => $originalFolder['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => false, // <-- Уточняем, что не по группе
                        'shared_entry_id' => $entry['id'],
                        'parent_id' => $originalFolder['parent_id']
                    ];
                    $sharedFolderIds[] = $entry['folder_id'];
                }
            }
        }

        // Получаем все расшаренные мне файлы
        $sharedFileEntries = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $_SESSION['email']]);
        $sharedFileDetails = [];
        foreach ($sharedFileEntries as $entry) {
            $originalFile = $fileRepo->find('files', $entry['file_id']);
            if ($originalFile && $originalFile['user_id'] !== $userId) {
                // Проверяем, не добавили ли мы уже этот файл
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
                        'is_shared_by_group' => false, // <-- Уточняем, что не по группе
                        'shared_entry_id' => $entry['id'],
                        'folder_id' => $originalFile['folder_id']
                    ];
                }
            }
        }

        // --- Фильтрация и объединение элементов для текущей папки ---

        // Начинаем с собственных элементов
        $allFiles = [];
        $allFolders = [];

        // Добавляем собственные элементы
        foreach ($ownFiles as $file) {
            $allFiles[$file['id']] = $file;
        }
        foreach ($ownFolders as $folder) {
            $allFolders[$folder['id']] = $folder;
        }

        // Добавляем расшаренные элементы (по email), только те, которые принадлежат текущей папке
        if ($folderId === 0) {
            // В корне: добавляем расшаренные файлы, у которых folder_id = null
            foreach ($sharedFileDetails as $file) {
                if ($file['folder_id'] === null) {
                    $allFiles[$file['id']] = $file;
                }
            }
            // В корне: добавляем расшаренные папки, у которых parent_id = null
            foreach ($sharedFolderDetails as $folder) {
                if ($folder['parent_id'] === null) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        } else {
            // Внутри папки: добавляем расшаренные файлы, у которых folder_id = $folderId
            foreach ($sharedFileDetails as $file) {
                if ($file['folder_id'] == $folderId) {
                    $allFiles[$file['id']] = $file;
                }
            }
            // Внутри папки: добавляем расшаренные папки, у которых parent_id = $folderId
            foreach ($sharedFolderDetails as $folder) {
                if ($folder['parent_id'] == $folderId) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // Добавляем расшаренные элементы (по группе), только те, которые принадлежат текущей папке
        if ($folderId === 0) {
            // В корне: добавляем расшаренные файлы, у которых folder_id = null
            foreach ($sharedByGroupFileDetails as $file) {
                if ($file['folder_id'] === null) {
                    $allFiles[$file['id']] = $file;
                }
            }
            // В корне: добавляем расшаренные папки, у которых parent_id = null
            foreach ($sharedByGroupFolderDetails as $folder) {
                if ($folder['parent_id'] === null) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        } else {
            // Внутри папки: добавляем расшаренные файлы, у которых folder_id = $folderId
            foreach ($sharedByGroupFileDetails as $file) {
                if ($file['folder_id'] == $folderId) {
                    $allFiles[$file['id']] = $file;
                }
            }
            // Внутри папки: добавляем расшаренные папки, у которых parent_id = $folderId
            foreach ($sharedByGroupFolderDetails as $folder) {
                if ($folder['parent_id'] == $folderId) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // --- РЕКУРСИВНОЕ ПОЛУЧЕНИЕ ВЛОЖЕННОГО СОДЕРЖИМОГО ДЛЯ РАСШАРЕННЫХ ПАПОК (email) ---
        // Если мы находимся внутри расшаренной папки (email), получаем её содержимое
        if ($isCurrentFolderShared && $currentFolder) {
            $recursiveContent = $this->getRecursiveContent($currentFolder['id'], $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo);
            foreach ($recursiveContent['files'] as $file) {
                if (!isset($allFiles[$file['id']])) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($recursiveContent['folders'] as $folder) {
                if (!isset($allFolders[$folder['id']])) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // --- РЕКУРСИВНОЕ ПОЛУЧЕНИЕ ВЛОЖЕННОГО СОДЕРЖИМОГО ДЛЯ РАСШАРЕННЫХ ПАПОК (группа) ---
        // Если мы находимся внутри расшаренной папки (группа), получаем её содержимое
        if ($isCurrentFolderSharedByGroup && $currentFolder) {
            $recursiveContentByGroup = $this->getRecursiveContentByGroup($currentFolder['id'], $userId, $fileRepo, $folderRepo, $shareByGroupService);
            foreach ($recursiveContentByGroup['files'] as $file) {
                if (!isset($allFiles[$file['id']])) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($recursiveContentByGroup['folders'] as $folder) {
                if (!isset($allFolders[$folder['id']])) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // Преобразуем обратно в индексированные массивы
        $allFiles = array_values($allFiles);
        $allFolders = array_values($allFolders);

        // --- НОВАЯ ЛОГИКА: Подготовка списка расшаренных по группам для отображения в отдельной таблице ---
        $detailedSharedByGroupResources = [];
        foreach ($sharedByGroupResources as $resource) {
            $details = null;
            if ($resource['resource_type'] === 'file') {
                $details = $fileRepo->find('files', $resource['resource_id']);
            } elseif ($resource['resource_type'] === 'folder') {
                $details = $folderRepo->find('folders', $resource['resource_id']);
            }
            if ($details && $details['user_id'] !== $userId) { // Убедимся, что это не мой ресурс
                $detailedSharedByGroupResources[] = [
                    'type' => $resource['resource_type'],
                    'details' => $details,
                    'group_name' => $resource['group_name'],
                    'permissions' => $resource['permissions'],
                    'owner_email' => $this->getUserEmailById($details['user_id'])
                ];
            }
        }
        // --- КОНЕЦ НОВОЙ ЛОГИКИ ---

        // Получаем хлебные крошки
        $breadcrumbs = $this->getBreadcrumbs($folderId, $folderRepo);

        // Отправляем данные в шаблон
        $response->sendHtml('dashboard.php', [
            'files' => $allFiles,
            'folders' => $allFolders,
            'currentFolder' => $currentFolder,
            'isCurrentFolderShared' => $isCurrentFolderShared,
            'isCurrentFolderSharedByGroup' => $isCurrentFolderSharedByGroup, // <-- Новое поле
            'breadcrumbs' => $breadcrumbs,
            'shared_resources_by_group' => $detailedSharedByGroupResources // <-- Новые данные для отображения
        ]);
    }

    // Вспомогательный метод для рекурсивного получения содержимого папки (email)
    private function getRecursiveContent(int $folderId, int $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo): array
    {
        $files = [];
        $folders = [];

        $childFiles = $fileRepo->findBy('files', ['folder_id' => $folderId]);
        $childFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId]);

        foreach ($childFiles as $file) {
            $file['is_shared'] = true;
            $file['is_shared_by_group'] = false; // <-- Уточняем
            $file['owner_email'] = $this->getUserEmailById($file['user_id']);
            $files[$file['id']] = $file;
        }

        foreach ($childFolders as $folder) {
            $folder['is_shared'] = true;
            $folder['is_shared_by_group'] = false; // <-- Уточняем
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
    private function getRecursiveContentByGroup(int $folderId, int $userId, $fileRepo, $folderRepo, $shareByGroupService): array
    {
        $files = [];
        $folders = [];

        // Получаем файлы и папки в этой папке, которые доступны пользователю через группы
        $childFiles = $fileRepo->findBy('files', ['folder_id' => $folderId]);
        $childFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId]);

        foreach ($childFiles as $file) {
            // Проверяем, есть ли доступ к файлу через группы
            if ($shareByGroupService->hasAccessByGroup($userId, 'file', $file['id'])) {
                $file['is_shared'] = true;
                $file['is_shared_by_group'] = true; // <-- Уточняем
                $file['owner_email'] = $this->getUserEmailById($file['user_id']);
                $files[$file['id']] = $file;
            }
        }

        foreach ($childFolders as $folder) {
            // Проверяем, есть ли доступ к папке через группы
            if ($shareByGroupService->hasAccessByGroup($userId, 'folder', $folder['id'])) {
                $folder['is_shared'] = true;
                $folder['is_shared_by_group'] = true; // <-- Уточняем
                $folder['owner_email'] = $this->getUserEmailById($folder['user_id']);
                $folders[$folder['id']] = $folder;

                // Рекурсивно получаем содержимое подпапки
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
        $shareByGroupService = App::getService('share_by_group_service'); // <-- Новый сервис

        // --- Старые расшаренные (email) ---
        $sharedFilesByEmail = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFilesByEmail as &$file) {
            $originalFile = $fileRepo->find('files', $file['file_id']);
            $file['original_name'] = $originalFile['original_name'] ?? 'Неизвестный файл';
            $file['filename'] = $originalFile['filename'] ?? '';
            $file['is_shared_by_group'] = false; // <-- Уточняем
        }

        $sharedFoldersByEmail = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFoldersByEmail as &$folder) {
            $originalFolder = $folderRepo->find('folders', $folder['folder_id']);
            $folder['name'] = $originalFolder['name'] ?? 'Неизвестная папка';
            $folder['is_shared_by_group'] = false; // <-- Уточняем
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
                        'is_shared_by_group' => true, // <-- Уточняем
                        'permissions' => $resource['permissions']
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
                        'is_shared_by_group' => true, // <-- Уточняем
                        'permissions' => $resource['permissions']
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
