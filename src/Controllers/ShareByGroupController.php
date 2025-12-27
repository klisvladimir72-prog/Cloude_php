<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;

/**
 * Контроллер для обработки запросов на шаринг файлов/папок по группам.
 * Только администратор может выполнять шаринг.
 */
class ShareByGroupController
{
    private $shareService;
    private $groupService; // Для проверки админа

    public function __construct()
    {
        $this->shareService = App::getService('share_by_group_service');
        $this->groupService = App::getService('group_service'); // Получаем GroupService для проверки
    }

    /**
     * Обрабатывает запрос на шаринг ресурса (файл или папка) с группой.
     */
    public function shareResource(Request $request, Response $response)
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        // Проверяем, является ли текущий пользователь администратором
        if (!$userId || !$this->groupService->isAdmin($userId)) { // Используем GroupService для проверки
            http_response_code(403); // Forbidden
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $resourceType = $data['resource_type'] ?? '';
        $resourceId = (int)($data['resource_id'] ?? 0);
        $groupId = (int)($data['group_id'] ?? 0);
        $permissions = $data['permissions'] ?? 'read';

        // Проверяем корректность данных
        if (!in_array($resourceType, ['file', 'folder']) || $resourceId <= 0 || $groupId <= 0 || !in_array($permissions, ['read', 'write', 'full'])) {
            http_response_code(400); // Bad Request
            $response->setData(['error' => 'Неверные данные']);
            $response->sendJson();
            return;
        }

        $success = false;
        // Вызываем соответствующий метод сервиса в зависимости от типа ресурса
        if ($resourceType === 'folder') {
            $success = $this->shareService->shareFolderRecursively($resourceId, $groupId, $permissions, $userId);
        } else if ($resourceType === 'file') {
            $success = $this->shareService->shareFile($resourceId, $groupId, $permissions, $userId);
        }

        if ($success) {
            $response->setData(['success' => true, 'message' => 'Ресурс успешно поделен']);
        } else {
            http_response_code(500); // Internal Server Error
            $response->setData(['error' => 'Не удалось поделиться ресурсом']);
        }
        $response->sendJson();
    }
}
