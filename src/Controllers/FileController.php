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

        // Определяем, является ли текущая папка расшаренной (если мы внутри неё)
        $currentFolder = null;
        $isCurrentFolderShared = false;
        if ($folderId > 0) {
            $currentFolder = $folderRepo->find('folders', $folderId);
            if (!$currentFolder) {
                http_response_code(404);
                $response->sendHtml('layout.php', ['content' => '<p>Папка не найдена.</p>']);
                return;
            }
            // Проверяем, является ли папка расшаренной мне
            $sharedFolderEntry = $sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId, 'shared_with_email' => $_SESSION['email']]);
            $isCurrentFolderShared = !empty($sharedFolderEntry) && $currentFolder['user_id'] !== $userId;
        }

        // --- Получение собственных элементов ---
        if ($folderId === 0) {
            $ownFiles = $fileRepo->findBy('files', ['folder_id' => null, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => null, 'user_id' => $userId]);
        } else {
            // Проверка доступа: либо я владелец, либо папка расшарена мне
            $isOwner = $currentFolder['user_id'] === $userId;
            $isSharedToMe = $isCurrentFolderShared;

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
        }
        foreach ($ownFolders as &$folder) {
            $folder['is_shared'] = false;
        }

        // --- Получение расшаренных элементов ---
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

        // Добавляем расшаренные элементы, только те, которые принадлежат текущей папке
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

        // --- РЕКУРСИВНОЕ ПОЛУЧЕНИЕ ВЛОЖЕННОГО СОДЕРЖИМОГО ДЛЯ РАСШАРЕННЫХ ПАПОК ---
        // Если мы находимся внутри расшаренной папки, нам нужно получить всё её содержимое рекурсивно
        if ($isCurrentFolderShared && $currentFolder) {
            // Получаем все файлы и папки, которые принадлежат этой расшаренной папке и её подпапкам
            $recursiveContent = $this->getRecursiveContent($currentFolder['id'], $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo);

            // Добавляем рекурсивное содержимое в allFiles и allFolders
            foreach ($recursiveContent['files'] as $file) {
                // Убедимся, что файл не был уже добавлен (например, если он прямой потомок)
                if (!isset($allFiles[$file['id']])) {
                    $allFiles[$file['id']] = $file;
                }
            }
            foreach ($recursiveContent['folders'] as $folder) {
                // Убедимся, что папка не была уже добавлена (например, если она прямой потомок)
                if (!isset($allFolders[$folder['id']])) {
                    $allFolders[$folder['id']] = $folder;
                }
            }
        }

        // Преобразуем обратно в индексированные массивы
        $allFiles = array_values($allFiles);
        $allFolders = array_values($allFolders);

        // Получаем хлебные крошки
        $breadcrumbs = $this->getBreadcrumbs($folderId, $folderRepo);

        // Отправляем данные в шаблон
        $response->sendHtml('dashboard.php', [
            'files' => $allFiles,
            'folders' => $allFolders,
            'currentFolder' => $currentFolder,
            'isCurrentFolderShared' => $isCurrentFolderShared,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    // Вспомогательный метод для рекурсивного получения содержимого папки
    private function getRecursiveContent(int $folderId, int $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo): array
    {
        $files = [];
        $folders = [];

        // Получаем прямых потомков (файлы и папки) этой папки
        $childFiles = $fileRepo->findBy('files', ['folder_id' => $folderId]);
        $childFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId]);

        // Обрабатываем файлы
        foreach ($childFiles as $file) {
            // Для файла, который находится в расшаренной папке, мы должны установить is_shared = true
            // и owner_email, даже если он не был явно расшарен, потому что он находится в расшаренной папке
            $file['is_shared'] = true;
            $file['owner_email'] = $this->getUserEmailById($file['user_id']);
            $files[$file['id']] = $file;
        }

        // Обрабатываем папки
        foreach ($childFolders as $folder) {
            // Для папки, которая находится в расшаренной папке, мы должны установить is_shared = true
            // и owner_email, даже если она не была явно расшарена, потому что она находится в расшаренной папке
            $folder['is_shared'] = true;
            $folder['owner_email'] = $this->getUserEmailById($folder['user_id']);
            $folders[$folder['id']] = $folder;

            // Рекурсивно получаем содержимое этой подпапки
            $subContent = $this->getRecursiveContent($folder['id'], $userId, $fileRepo, $folderRepo, $sharedFileRepo, $sharedFolderRepo);
            // Добавляем содержимое подпапки
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

        $sharedFileRepo = App::getService('shared_file_repository');
        $sharedFolderRepo = App::getService('shared_folder_repository');
        $fileRepo = App::getService('file_repository');
        $folderRepo = App::getService('folder_repository');

        $sharedFiles = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFiles as &$file) {
            $originalFile = $fileRepo->find('files', $file['file_id']);
            $file['original_name'] = $originalFile['original_name'] ?? 'Неизвестный файл';
            $file['filename'] = $originalFile['filename'] ?? '';
        }

        $sharedFolders = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $_SESSION['email']]);
        foreach ($sharedFolders as &$folder) {
            $originalFolder = $folderRepo->find('folders', $folder['folder_id']);
            $folder['name'] = $originalFolder['name'] ?? 'Неизвестная папка';
        }

        $response->sendHtml('shared.php', [
            'sharedFiles' => $sharedFiles,
            'sharedFolders' => $sharedFolders
        ]);
    }
}
