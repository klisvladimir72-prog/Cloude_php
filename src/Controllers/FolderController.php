<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;
use Src\Models\User;


class FolderController
{
    private $folderService;
    private $folderRepo;

    public function __construct()
    {
        $this->folderService = App::getService('folder_service');
        $this->folderRepo = App::getService('folder_repository');
    }

    /**
     * Проверка на аутентификацию пользователя.
     *
     * @param Request $request
     * @param Response $response
     * @return User|null
     */
    public function authenticateUser(Request $request, Response $response): ?User
    {
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            $response->sendHtml('login.php');
        };
        return $authResult['user'];
    }


    /**
     * Получение всех файлов и папок для текущей директории.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getContentFolder(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            $user = $authResult['user'];
            $folderId = $request->getParam('id');

            if (!$folderId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки не указан']);
                $response->sendJson();
                return;
            }

            $folder = $this->folderRepo->find($this->folderRepo->getTable(), $folderId);
            if (!$folder) {
                http_response_code(404);
                $response->setData(['success' => false, 'message' => "Папки не найдено."]);
                $response->sendJson();
                return;
            }

            $content = $this->folderService->getContentFolder($folderId);
            if (isset($content['error'])) {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => $content['error']]);
                $response->sendJson();
                return;
            }

            if (empty($content['folders']) && empty($content['files'])) {
                http_response_code(200);
                $response->setData(['success' => true, 'data' => '']);
                $response->sendJson();
                return;
            }

            http_response_code(200);
            $response->setData(['success' => true, 'data' => $content]);
            $response->sendJson();
            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => "Внутренняя ошибка сервера.",
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
            return;
        }
    }

    /**
     * Метод для создания директории.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function createFolder(Request $request, Response $response)
    {
        try {
            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->setData(['success' => false, 'message' => "Необходима авторизация."]);
                $response->sendJson();
                return;
            }

            // Получаем объект пользователя
            $user = $authResult['user'];
            $userId = $user->id;

            $data = $request->getData();
            $name = trim($data['name'] ?? '');
            $parentId = $data['parent_id'] ?? null;

            if (empty($name)) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'Название папки обязательно']);
                $response->sendJson();
                return;
            }

            if ($parentId === '' || $parentId === 'null' || $parentId === 'undefined') {
                $parentId = null;
            }

            if ($parentId !== null) {
                $parentFolder = $this->folderRepo->find($this->folderRepo->getTable(), (int)$parentId);
                if (!$parentFolder) {
                    http_response_code(404);
                    $response->setData(['success' => false, 'message' => "Указанной родительской папки не существует."]);
                    $response->sendJson();
                    return;
                }
            }

            $isUniqueFolder = $this->folderService->isUniqueFolderNameByParentFolder($userId, $parentId, $name);
            if (!$isUniqueFolder) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => "Папка с таким именем у вас уже есть - $name."]);
                $response->sendJson();
                return;
            }

            $success = $this->folderService->createFolder($name, $userId, $parentId);

            if ($success) {
                http_response_code(200);
                $response->setData(['success' => true, 'message' => 'Папка создана успешно.']);
            } else {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => 'Ошибка при создании папки.']);
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
     * Метод для удаление директории.
     * 
     * Удаляет все содержимое папки.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function deleteFolder(Request $request, Response $response)
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

            $folderId = $request->getParam('id');

            if (!$folderId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки не указан']);
                $response->sendJson();
                return;
            }

            $folder = $this->folderRepo->find($this->folderRepo->getTable(), $folderId);
            if (!$folder) {
                http_response_code(404);
                $response->setData(['success' => false, 'message' => 'Указанной папки не найдено.']);
                $response->sendJson();
                return;
            }

            $isOwner = $this->folderService->isPermissions($user, $folder);
            if (!$isOwner) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => 'Нет прав на удаление папки.']);
                $response->sendJson();
                return;
            }

            $service = App::getService('folder_service');
            $success = $service->deleteFolder($folderId, $user);

            if ($success) {
                $response->setData(['success' => true, 'message' => 'Папка и всё её содержимое удалены']);
                $response->sendJson();
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера.',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
        }
    }

    /**
     * Переименование директории.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function renameFolder(Request $request, Response $response)
    {
        try {
            $user = $this->authenticateUser($request, $response);
            if (!$user) return;

            $authResult = AuthMiddleware::handle($request, $response);
            if (!$authResult) {
                http_response_code(401);
                $response->sendHtml('login.php');
                return;
            }

            // Получаем объект пользователя
            $user = $authResult['user'];

            $folderId = $request->getData()['id'];
            $folderNewName = trim($request->getData()['new_name']);

            if (!$folderId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки не указан']);
                $response->sendJson();
                return;
            }

            if (!$folderNewName) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'Новое имя папки обязательно']);
                $response->sendJson();
                return;
            }

            $folder = $this->folderRepo->find($this->folderRepo->getTable(), $folderId);

            $isOwner = $this->folderService->isPermissions($user, $folder);
            if (!$isOwner) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => 'Нет прав на переименование папки.']);
                $response->sendJson();
                return;
            }

            $isUniqueName = $this->folderService->isUniqueFolderNameByParentFolder($user->id, $folder['parent_id'], $folderNewName);
            if (!$isUniqueName) {
                http_response_code(409);
                $response->setData(['success' => false, 'message' => 'Папка с таким именем уже существует.']);
                $response->sendJson();
                return;
            }

            $success = $this->folderRepo->update($folderId, ['name' => $folderNewName]);
            if ($success) {
                http_response_code(200);
                $response->setData(['success' => true, 'message' => "Папка успешно переименована на '$folderNewName'"]);
            } else {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => 'Ошибка при обновлении папки.']);
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
}
