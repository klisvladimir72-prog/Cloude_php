<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;

class FolderController
{
    public function create(Request $request, Response $response)
    {
        try {
            session_start();
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                $response->setData(['success' => false, 'message' => 'Требуется аутентификация']);
                $response->sendJson();
                return;
            }

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

            $service = App::getService('folder_service');
            $success = $service->createFolder($name, $_SESSION['user_id'], $parentId);

            if ($success) {
                $response->setData(['success' => true, 'message' => 'Папка создана']);
            } else {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'Ошибка при создании папки']);
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
            $folderId = $data['folder_id'] ?? null;

            if (!$folderId) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'ID папки не указан']);
                $response->sendJson();
                return;
            }

            $service = App::getService('folder_service');
            $success = $service->deleteFolder($folderId, $_SESSION['user_id']);

            if ($success) {
                $response->setData(['success' => true, 'message' => 'Папка и всё её содержимое удалены']);
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
}
