<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class UserController
{

    private function isAdminCheck(Request $request, Response $response)
    {
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        }

        $user = $authResult['user'];
        if ($user->login !== 'admin') {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        return $user;
    }
    public function showAdminUserPanel(Request $request, Response $response)
    {


        $user = $this->isAdminCheck($request, $response);
        $userId = $user->id;
        $userLogin = $user->login;

        $userRepo = App::getService('user_repository');

        $allUsers = $userRepo->getAllUsersExcludingAdmin();

        $response->sendHtml('admin_users.php', [
            'users' => $allUsers,
            'login' => $userLogin,
            'id' => $userId
        ]);
    }

    public function updateUserField(Request $request, Response $response)
    {
        $user = $this->isAdminCheck($request, $response);
        $userId = $user->id;

        $data = $request->getData();
        $userId = $data['user_id'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        // Не разрешаем менять пароль , id , роль
        if (!$userId || !$field || $field === 'password' || $field === 'id' || $field === 'role') {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        $allowFields = ['email', 'login'];
        if (!in_array($field, $allowFields)) {
            http_response_code(400);
            $response->setData(['error' => 'Поле не разрешено для редактирования']);
            $response->sendJson();
            return;
        }

        // Проверим уникальность email и login при их изменении 
        $userRepo = App::getService('user_repository');
        $existingUser = $userRepo->findForAuth($value);
        if ($existingUser && $existingUser['id'] !== $userId) {
            http_response_code(400);
            $response->setData(['error' => 'Значение уже используется другим пользователем']);
            $response->sendJson();
            return;
        }

        // Обновляем поле 
        $updateDate = [$field => $value];
        $success = $userRepo->update($userId, $updateDate);

        if ($success) {
            $response->setData(['success' => true, 'message' => 'Поле обновлено.']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Ошибка при обновлении.']);
        }
        $response->sendJson();
    }

    public function resetUserPassword(Request $request, Response $response)
    {
        $user = $this->isAdminCheck($request, $response);

        $data = $request->getData();
        $userId = $data['user_id'] ?? null;
        $newPassword = $data['new_password'] ?? '';

        if (!$userId) {
            http_response_code(400);
            $response->setData(['error' => 'ID пользователя обязателен.']);
            $response->sendJson();
            return;
        }

        $passwordToSet = !empty($newPassword) ? $newPassword : 'user123';

        $hashedPassword = password_hash($passwordToSet, PASSWORD_DEFAULT);

        $userRepo = App::getService('user_repository');

        $success = $userRepo->update($userId, ['password_hash' => $hashedPassword]);
        if ($success) {
            $response->setData(['success' => true, 'message' => 'Пароль изменен.']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Ошибка при смене пароля.']);
        }
        $response->sendJson();
    }

    public function deleteUser(Request $request, Response $response)
    {

        $user = $this->isAdminCheck($request, $response);

        $data = $request->getData();
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            http_response_code(400);
            $response->setData(['error' => 'ID пользователя обязателен.']);
            $response->sendJson();
            return;
        }

        $userRepo = App::getService('user_repository');

        $success = $userRepo->delete($userId);

        if ($success) {
            $response->setData(['success' => true, 'message' => 'Пользователь успешно удален.']);
        } else {
            http_response_code(500);
            $response->setData(['error' => 'Ошибка при удалении пользователя.']);
        }
        $response->sendJson();
    }
}
