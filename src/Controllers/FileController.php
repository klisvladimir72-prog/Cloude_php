<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Models\User;
use Src\Middleware\AuthMiddleware;

class FileController
{

    private $fileRepo;
    private $fileService;

    public function __construct()
    {
        $this->fileRepo = App::getService('file_repository');
        $this->fileService = App::getService('file_service');
    }

    /**
     * Проверяет токен в заголовке Authorization и возвращает пользователя.
     *
     * @param Request $request
     * @param Response $response
     */
    public function authenticateUser(Request $request, Response $response): ?User
    {
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            $response->sendHtml('login.php');
        };
        return $authResult['user'];
    }

    /**Возвращает список всех файлов */
    public function getFilesList(Request $request, Response $response)
    {
        try {
            $filesList = $this->fileRepo->findAll($this->fileRepo->getTable());

            http_response_code(200);
            $response->setData(['success' => 'true', 'filesList' => $filesList]);
            $response->sendJson();
            return;
        } catch (\Exception $e) {
            http_response_code(500);
            $response->setData(['success' => 'false', 'message' => $e->getMessage()]);
            $response->sendJson();
            return;
        }
    }

    /**Возвращает список всех файлов пользователя */
    public function getFilesListById(Request $request, Response $response)
    {
        try {
            $user_id = $request->getQueryParam('id');

            if (!$user_id) {
                http_response_code(500);
                $response->setData(['success' => 'false', "message" => "id пользователя отсутствует."]);
                $response->sendJson();
                return;
            }

            $filesList = $this->fileRepo->findBy($this->fileRepo->getTable(), ["user_id" => $user_id]);

            http_response_code(200);
            $response->setData(['success' => 'true', 'filesList' => $filesList]);
            $response->sendJson();
            return;
        } catch (\Exception $e) {
            http_response_code(500);
            $response->setData(['success' => 'false', "message" => $e->getMessage()]);
            $response->sendJson();
            return;
        }
    }

    /**Возвращает данные о файле */
    public function getFileByFileId(Request $request, Response $response)
    {
        try {
            $file_id = $request->getQueryParam('id');

            if (!$file_id) {
                http_response_code(500);
                $response->setData(['success' => 'false', "message" => "id файла отсутствует."]);
                $response->sendJson();
                return;
            }

            $file = $this->fileRepo->findBy($this->fileRepo->getTable(), ["id" => $file_id]);

            http_response_code(200);
            $response->setData(['success' => 'true', 'file' => $file]);
            $response->sendJson();
            return;
        } catch (\Exception $e) {
            http_response_code(500);
            $response->setData(['success' => 'false', "message" => $e->getMessage()]);
            $response->sendJson();
            return;
        }
    }


    public function index(Request $request, Response $response)
    {
        // Проверяем токен 
        $user = $this->authenticateUser($request, $response);
        if (!$user) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        };

        $userId = $user->id;
        $folderId = (int)($_GET['folder'] ?? 0); // ID папки, которую просматриваем

        $fileRepo = App::getService('file_repository');
        $folderRepo = App::getService('folder_repository');
        $sharedFileRepo = App::getService('shared_file_repository');
        $sharedFolderRepo = App::getService('shared_folder_repository');
        $shareByGroupService = App::getService('share_by_group_service'); // Получаем сервис

        // 1. Получаем собственные элементы (те, у кого user_id = текущий пользователь)
        // и находящиеся в текущей просматриваемой папке (folder_id или parent_id)
        if ($folderId === 0) {
            $ownFiles = $fileRepo->findBy('files', ['folder_id' => null, 'user_id' => $userId]);
            $ownFolders = $folderRepo->findBy('folders', ['parent_id' => null, 'user_id' => $userId]);
        } else {
            // Проверка доступа к родительской папке: либо я владелец, либо папка расшарена мне (email или группа)
            $currentFolder = $folderRepo->find('folders', $folderId);
            if (!$currentFolder) {
                http_response_code(404);
                $response->sendHtml('layout.php', ['content' => '<p>Папка не найдена.</p>']);
                return;
            }

            $isOwner = $currentFolder['user_id'] === $userId;
            $isSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId, 'shared_with_email' => $user->email]));
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
        foreach ($ownFiles as $key => $file) {
            $ownFiles[$key]['is_shared'] = false;
            $ownFiles[$key]['is_shared_by_group'] = false;
            $ownFiles[$key]['group_name'] = null;
            $ownFiles[$key]['permissions'] = null;
            $ownFiles[$key]['owner_email'] = $this->getUserEmailById($ownFiles[$key]['user_id']);
        }
        foreach ($ownFolders as $key => $folder) {
            $ownFolders[$key]['is_shared'] = false;
            $ownFolders[$key]['is_shared_by_group'] = false;
            $ownFolders[$key]['group_name'] = null;
            $ownFolders[$key]['permissions'] = null;
            $ownFolders[$key]['owner_email'] = $this->getUserEmailById($ownFolders[$key]['user_id']);
        }

        // Сбор всех доступных элементов и определение их "виртуального" родителя ---

        // Собираем все расшаренные файлы (по email и по группе)
        $allSharedFiles = [];
        $sharedFileEntries = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $user->email]);
        $sharedByGroupResources = $shareByGroupService->getResourcesSharedWithUserGroups($userId);

        // Процессим файлы, расшаренные по email
        foreach ($sharedFileEntries as $entry) {
            $originalFile = $fileRepo->find('files', $entry['file_id']);
            if ($originalFile && $originalFile['user_id'] !== $userId) {
                $realParentFolderId = $originalFile['folder_id'];

                // Проверяем, есть ли у меня доступ к родительской папке файла
                $parentAccessible = false;
                if ($realParentFolderId) {
                    $parentFolder = $folderRepo->find('folders', $realParentFolderId);
                    if ($parentFolder) {
                        $parentIsOwner = $parentFolder['user_id'] === $userId;
                        $parentIsSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $realParentFolderId, 'shared_with_email' => $user->email]));
                        $parentIsSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $realParentFolderId);
                        $parentAccessible = $parentIsOwner || $parentIsSharedToMeByEmail || $parentIsSharedToMeByGroup;
                    }
                } else {
                    // Если файл в корне, доступ всегда "есть"
                    $parentAccessible = true;
                }

                // Определяем, в какой "виртуальной" папке должен отображаться файл
                $virtualParentId = $parentAccessible ? $realParentFolderId : null; // null означает "виртуальный корень"

                $allSharedFiles[$originalFile['id']] = [
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
                    'real_folder_id' => $realParentFolderId, // Реальный родительский ID
                    'virtual_folder_id' => $virtualParentId, // Виртуальный родительский ID
                ];
            }
        }

        // Процессим файлы, расшаренные по группе
        foreach ($sharedByGroupResources as $resource) {
            if ($resource['resource_type'] === 'file') {
                $originalFile = $fileRepo->find('files', $resource['resource_id']);
                if ($originalFile && $originalFile['user_id'] !== $userId) {
                    $realParentFolderId = $originalFile['folder_id'];

                    // Проверяем, есть ли у меня доступ к родительской папке файла
                    $parentAccessible = false;
                    if ($realParentFolderId) {
                        $parentFolder = $folderRepo->find('folders', $realParentFolderId);
                        if ($parentFolder) {
                            $parentIsOwner = $parentFolder['user_id'] === $userId;
                            $parentIsSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $realParentFolderId, 'shared_with_email' => $user->email]));
                            $parentIsSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $realParentFolderId);
                            $parentAccessible = $parentIsOwner || $parentIsSharedToMeByEmail || $parentIsSharedToMeByGroup;
                        }
                    } else {
                        // Если файл в корне, доступ всегда "есть"
                        $parentAccessible = true;
                    }

                    // Определяем, в какой "виртуальной" папке должен отображаться файл
                    $virtualParentId = $parentAccessible ? $realParentFolderId : null; // null означает "виртуальный корень"

                    $allSharedFiles[$originalFile['id']] = [
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
                        'real_folder_id' => $realParentFolderId, // Реальный родительский ID
                        'virtual_folder_id' => $virtualParentId, // Виртуальный родительский ID
                    ];
                }
            }
        }

        // Собираем все расшаренные папки (по email и по группе)
        $allSharedFolders = [];
        $sharedFolderEntries = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $user->email]);

        // Процессим папки, расшаренные по email
        foreach ($sharedFolderEntries as $entry) {
            $originalFolder = $folderRepo->find('folders', $entry['folder_id']);
            if ($originalFolder && $originalFolder['user_id'] !== $userId) {
                $realParentFolderId = $originalFolder['parent_id'];

                // Проверяем, есть ли у меня доступ к родительской папке
                $parentAccessible = false;
                if ($realParentFolderId) {
                    $parentFolder = $folderRepo->find('folders', $realParentFolderId);
                    if ($parentFolder) {
                        $parentIsOwner = $parentFolder['user_id'] === $userId;
                        $parentIsSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $realParentFolderId, 'shared_with_email' => $user->email]));
                        $parentIsSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $realParentFolderId);
                        $parentAccessible = $parentIsOwner || $parentIsSharedToMeByEmail || $parentIsSharedToMeByGroup;
                    }
                } else {
                    // Если папка в корне, доступ всегда "есть"
                    $parentAccessible = true;
                }

                // Определяем, в какой "виртуальной" папке должна отображаться папка
                $virtualParentId = $parentAccessible ? $realParentFolderId : null; // null означает "виртуальный корень"

                $allSharedFolders[$originalFolder['id']] = [
                    'id' => $originalFolder['id'],
                    'name' => $originalFolder['name'],
                    'created_at' => $originalFolder['created_at'],
                    'user_id' => $originalFolder['user_id'],
                    'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                    'is_shared' => true,
                    'is_shared_by_group' => false,
                    'group_name' => null,
                    'permissions' => null,
                    'real_parent_id' => $realParentFolderId, // Реальный родительский ID
                    'virtual_parent_id' => $virtualParentId, // Виртуальный родительский ID
                ];
            }
        }

        // Процессим папки, расшаренные по группе
        foreach ($sharedByGroupResources as $resource) {
            if ($resource['resource_type'] === 'folder') {
                $originalFolder = $folderRepo->find('folders', $resource['resource_id']);
                if ($originalFolder && $originalFolder['user_id'] !== $userId) {
                    $realParentFolderId = $originalFolder['parent_id'];

                    // Проверяем, есть ли у меня доступ к родительской папке
                    $parentAccessible = false;
                    if ($realParentFolderId) {
                        $parentFolder = $folderRepo->find('folders', $realParentFolderId);
                        if ($parentFolder) {
                            $parentIsOwner = $parentFolder['user_id'] === $userId;
                            $parentIsSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $realParentFolderId, 'shared_with_email' => $user->email]));
                            $parentIsSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $realParentFolderId);
                            $parentAccessible = $parentIsOwner || $parentIsSharedToMeByEmail || $parentIsSharedToMeByGroup;
                        }
                    } else {
                        // Если папка в корне, доступ всегда "есть"
                        $parentAccessible = true;
                    }

                    // Определяем, в какой "виртуальной" папке должна отображаться папка
                    $virtualParentId = $parentAccessible ? $realParentFolderId : null; // null означает "виртуальный корень"

                    $allSharedFolders[$originalFolder['id']] = [
                        'id' => $originalFolder['id'],
                        'name' => $originalFolder['name'],
                        'created_at' => $originalFolder['created_at'],
                        'user_id' => $originalFolder['user_id'],
                        'owner_email' => $this->getUserEmailById($originalFolder['user_id']),
                        'is_shared' => true,
                        'is_shared_by_group' => true,
                        'group_name' => $resource['group_name'],
                        'permissions' => $resource['permissions'],
                        'real_parent_id' => $realParentFolderId, // Реальный родительский ID
                        'virtual_parent_id' => $virtualParentId, // Виртуальный родительский ID
                    ];
                }
            }
        }

        // --- ФИЛЬТРАЦИЯ ЭЛЕМЕНТОВ ДЛЯ ОТОБРАЖЕНИЯ ---
        // Выбираем только те элементы, которые должны отображаться в $folderId
        $displayedFiles = [];
        $displayedFolders = [];


        // Добавляем собственные элементы
        foreach ($ownFiles as $file) {
            // Проверяем, что файл находится в текущей просматриваемой папке
            // Используем === для строгого сравнения, чтобы избежать проблем с типами
            $fileParentId = $file['folder_id'];
            if ($fileParentId === null && $folderId === 0) {
                // Если файл в корне (folder_id === null) и мы просматриваем корень (folderId === 0)
                $displayedFiles[$file['id']] = $file;
            } elseif ($fileParentId !== null && (int)$fileParentId === $folderId) {
                // Если файл не в корне и его folder_id совпадает с $folderId
                $displayedFiles[$file['id']] = $file;
            }
        }
        foreach ($ownFolders as $folder) {
            // Проверяем, что папка находится в текущей просматриваемой папке
            // Используем === для строгого сравнения, чтобы избежать проблем с типами
            $folderParentId = $folder['parent_id'];
            if ($folderParentId === null && $folderId === 0) {
                // Если папка в корне (parent_id === null) и мы просматриваем корень (folderId === 0)
                $displayedFolders[$folder['id']] = $folder;
            } elseif ($folderParentId !== null && (int)$folderParentId === $folderId) {
                // Если папка не в корне и её parent_id совпадает с $folderId
                $displayedFolders[$folder['id']] = $folder;
            }
        }

        // Добавляем расшаренные файлы, которые должны отображаться в $folderId
        foreach ($allSharedFiles as $file) {
            // Файл отображается в $folderId, если его виртуальный родитель - $folderId
            $virtualParentId = $file['virtual_folder_id'];
            if ($virtualParentId === null && $folderId === 0) {
                // Если файл в виртуальном корне (virtual_folder_id === null) и мы просматриваем корень (folderId === 0)
                $displayedFiles[$file['id']] = $file;
            } elseif ($virtualParentId !== null && (int)$virtualParentId === $folderId) {
                // Если файл не в виртуальном корне и его virtual_folder_id совпадает с $folderId
                $displayedFiles[$file['id']] = $file;
            }
        }

        // Добавляем расшаренные папки, которые должны отображаться в $folderId
        foreach ($allSharedFolders as $folder) {
            // Папка отображается в $folderId, если её виртуальный родитель - $folderId
            $virtualParentId = $folder['virtual_parent_id'];
            if ($virtualParentId === null && $folderId === 0) {
                // Если папка в виртуальном корне (virtual_parent_id === null) и мы просматриваем корень (folderId === 0)
                $displayedFolders[$folder['id']] = $folder;
            } elseif ($virtualParentId !== null && (int)$virtualParentId === $folderId) {
                // Если папка не в виртуальном корне и её virtual_parent_id совпадает с $folderId
                $displayedFolders[$folder['id']] = $folder;
            }
        }


        // Преобразуем обратно в индексированные массивы
        $allFiles = array_values($displayedFiles);
        $allFolders = array_values($displayedFolders);


        // Получаем хлебные крошки (только для папок, принадлежащих пользователю или расшаренных ему как папка)
        $breadcrumbs = $this->getBreadcrumbs($folderId, $folderRepo, $userId, $shareByGroupService, $sharedFolderRepo, $user->email);

        // Отправляем данные в шаблон
        $response->sendHtml('dashboard.php', [
            'files' => $allFiles,
            'folders' => $allFolders,
            'currentFolder' => $currentFolder ?? null,
            'breadcrumbs' => $breadcrumbs,
            'login' => $user->login,
            'id' => $user->id,
        ]);
    }

    // Вспомогательный метод для получения email пользователя по ID
    private function getUserEmailById(int $id): string
    {
        $userRepo = App::getService('user_repository');
        $user = $userRepo->find('users', $id);
        return $user['email'] ?? 'unknown';
    }

    /**
     * Получает хлебные крошки для текущей папки.
     * Учитывает, является ли папка расшаренной и должна ли отображаться как виртуальный корень.
     *
     * @param int $folderId ID текущей папки.
     * @param object $folderRepo Репозиторий папок.
     * @param int $userId ID текущего пользователя.
     * @param object $shareByGroupService Сервис для проверки доступа по группе.
     * @param object $sharedFolderRepo Репозиторий расшаренных папок.
     * @param string $userEmail Email текущего пользователя.
     * @return array Массив хлебных крошек.
     */
    private function getBreadcrumbs(int $folderId, $folderRepo, int $userId, $shareByGroupService, $sharedFolderRepo, string $userEmail): array
    {
        $breadcrumbs = [];

        // Если просматриваем "виртуальный" корень (например, $folderId === 0), возвращаем пустой массив или "Корень"
        if ($folderId === 0) {
            return $breadcrumbs; // Или return [['id' => 0, 'name' => '🏠 Корень']];
        }

        // Получаем информацию о текущей папке
        $currentFolder = $folderRepo->find('folders', $folderId);
        if (!$currentFolder) {
            return $breadcrumbs; // Или бросить исключение
        }

        // Проверяем, является ли текущая папка расшаренной и виртуальным корнем
        $isCurrentFolderVirtualRoot = false;

        // Проверка расшаривания по email - используем findBy из BaseRepository и берем первый элемент
        $sharedFolderEntries = $sharedFolderRepo->findBy('shared_folders', ['folder_id' => $folderId, 'shared_with_email' => $userEmail]);
        $sharedFolderEntry = !empty($sharedFolderEntries) ? $sharedFolderEntries[0] : null; // Берем первый элемент или null
        if ($sharedFolderEntry) {
            $isCurrentFolderVirtualRoot = true;
        }

        // Проверка расшаривания по группе
        if (!$isCurrentFolderVirtualRoot) {
            $isCurrentFolderVirtualRoot = $shareByGroupService->hasAccessByGroup($userId, 'folder', $folderId);
        }

        // Если папка является виртуальным корнем, строим хлебные крошки только до неё
        // Но нужно проверить, есть ли доступ к родительской папке этой виртуальной. Если да, то строим нормальный путь.
        $currentId = $folderId;
        while ($currentId !== null && $currentId !== 0) {
            $folder = $folderRepo->find('folders', $currentId);
            if (!$folder) {
                break; // На случай ошибки в данных
            }

            // Проверяем доступ к текущей папке в цепочке
            $isOwner = $folder['user_id'] === $userId;
            $isSharedToMeByEmail = !empty($sharedFolderRepo->findBy('shared_folders', ['folder_id' => $currentId, 'shared_with_email' => $userEmail]));
            $isSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'folder', $currentId);

            // Если доступа к родительской папке нет, останавливаем построение цепочки
            if (!($isOwner || $isSharedToMeByEmail || $isSharedToMeByGroup)) {
                // Эта папка виртуальный корень, добавляем её и выходим
                array_unshift($breadcrumbs, ['id' => $currentId, 'name' => $folder['name'] . ' (🔒 Вирт. корень)']); // Помечаем для ясности
                break;
            }

            // Добавляем папку в начало массива
            array_unshift($breadcrumbs, ['id' => $folder['id'], 'name' => $folder['name']]);
            $currentId = $folder['parent_id']; // Переходим к родительской папке
        }

        return $breadcrumbs;
    }

    /**Загрузка файла на сервер */
    public function upload(Request $request, Response $response)
    {
        $user = $this->authenticateUser($request, $response);
        if (!$user) return;

        try {
            $data = $request->getData();
            $folderId = $data['folder_id'] ?? null;

            if ($folderId === '' || $folderId === 'null' || $folderId === 'undefined') {
                $folderId = null;
            }

            $service = App::getService('file_service');
            $result = $service->handleUpload($data, $_FILES, $user->id, $folderId);

            if ($result['success']) {
                $response->setData($result, ['data' => $data]);
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

    /**Переименование файла */
    public function renameFile(Request $request, Response $response)
    {
        $user = $this->authenticateUser($request, $response);
        if (!$user) return;

        try {
            $data = $request->getData();
            $fileId = $data['file_id'] ?? null;
            $fileNewName = $data['new_name'];

            if (!$fileId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла не указан']);
                $response->sendJson();
                return;
            }

            if (!$fileNewName) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'Новое имя файла не может быть пустым.']);
                $response->sendJson();
                return;
            }

            $fileExtract = $this->fileService->extractFileInfo($fileId);
            $fileName = $fileExtract['basename'];
            $fileExp = $fileExtract['extension'];

            $fileNewName = $fileNewName . "." . $fileExp;

            $isUniqueName = $this->fileService->isUniqueFileNameByUser($user->id, $fileNewName);
            if (!$isUniqueName) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => "Файл с таким именем у вас уже есть - $fileName."]);
                $response->sendJson();
                return;
            }


            $success = $this->fileRepo->update($fileId, ['original_name' => $fileNewName]);

            if ($success) {
                http_response_code(200);
                $response->setData(['success' => true, 'message' => "Файл успешно переименован на '$fileNewName'"]);
            } else {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => 'Ошибка при обновлении файла.']);
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

    // Метод для скачивания файла (проверка доступа)
    public function download(Request $request, Response $response)
    {
        $user = $this->authenticateUser($request, $response);
        if (!$user) return;

        $userId = $user->id;

        // Получаем имя файла из GET-параметра
        $fileName = $_GET['file'] ?? '';

        if (empty($fileName)) {
            http_response_code(400);
            $response->setData(['error' => 'Имя файла обязательно']);
            $response->sendJson();
            return;
        }

        $fileRepo = App::getService('file_repository');
        $sharedFileRepo = App::getService('shared_file_repository');
        $shareByGroupService = App::getService('share_by_group_service');

        // Получаем файл по имени
        $files = $fileRepo->findBy('files', ['filename' => $fileName]);
        if (empty($files)) {
            http_response_code(404);
            $response->setData(['error' => 'Файл не найден']);
            $response->sendJson();
            return;
        }

        // Берем первый файл с таким именем
        $file = $files[0];

        // Проверяем доступ: владелец или расшарен
        $isOwner = $file['user_id'] === $userId;
        $isSharedToMeByEmail = !empty($sharedFileRepo->findBy('shared_files', ['file_id' => $file['id'], 'shared_with_email' => $user->email]));
        $isSharedToMeByGroup = $shareByGroupService->hasAccessByGroup($userId, 'file', $file['id']);

        if (!($isOwner || $isSharedToMeByEmail || $isSharedToMeByGroup)) {
            http_response_code(403);
            $response->setData(['error' => 'Нет доступа к файлу']);
            $response->sendJson();
            return;
        }

        // Отправляем файл
        $filePath = __DIR__ . '/../../uploads/' . $file['filename'];
        if (!file_exists($filePath)) {
            http_response_code(404);
            $response->setData(['error' => 'Файл не найден на сервере']);
            $response->sendJson();
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit(); // Важно: завершаем выполнение после отправки файла
    }

    /**Удаление файла с проверкой на права (admin может) */
    public function delete(Request $request, Response $response)
    {
        $user = $this->authenticateUser($request, $response);
        if (!$user) return;

        try {
            $fileId = $request->getQueryParam('id');

            if (!$fileId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID файла не указан']);
                $response->sendJson();
                return;
            }

            $service = App::getService('file_service');
            $result = $service->deleteFile($fileId, $user->id, $user->role);

            if ($result['success']) {
                $response->setData(['success' => true, 'message' => $result['message']]);
            } else {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => $result['message']]);
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


    public function shared(Request $request, Response $response)
    {
        $user = $this->authenticateUser($request, $response);
        if (!$user) return;

        $userId = $user->id;
        $sharedFileRepo = App::getService('shared_file_repository');
        $sharedFolderRepo = App::getService('shared_folder_repository');
        $fileRepo = App::getService('file_repository');
        $folderRepo = App::getService('folder_repository');
        $shareByGroupService = App::getService('share_by_group_service');

        // --- Старые расшаренные (email) ---
        $sharedFilesByEmail = $sharedFileRepo->findBy('shared_files', ['shared_with_email' => $user->email]);
        foreach ($sharedFilesByEmail as &$file) {
            $originalFile = $fileRepo->find('files', $file['file_id']);
            $file['original_name'] = $originalFile['original_name'] ?? 'Неизвестный файл';
            $file['filename'] = $originalFile['filename'] ?? '';
            $file['is_shared_by_group'] = false;
        }

        $sharedFoldersByEmail = $sharedFolderRepo->findBy('shared_folders', ['shared_with_email' => $user->email]);
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
    }
}
