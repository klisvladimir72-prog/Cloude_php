<?php

namespace Src\Controllers;

use Exception;
use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class AdminController
{
    private $userRepo;

    public function __construct()
    {
        $this->userRepo = App::getService('user_repository');
    }

    /**Проверка на администратора */
    private function isAdminCheck(Request $request, Response $response)
    {
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->setData(['error' => "Необходима авторизация плоьзователя."]);
            $response->sendJson();
            return;
        }

        $user = $authResult['user'];
        if ($user->role !== 1) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен!', 'user' => $user]);
            $response->sendJson();
        }

        return true;
    }

    /**
     * Получение списка пользователей администратором.
     */
    public function getUsersList(Request $request, Response $response)
    {
        if (!$this->isAdminCheck($request, $response)) {
            http_response_code(403);
            $response->setData(['error' => 'Вы не администратор.']);
            $response->sendJson();
            return;
        }

        $usersList = $this->userRepo->getAllUsersExcludingAdmin();

        http_response_code(200);
        $response->setData(['success' => true, 'usersList' => $usersList]);
        $response->sendJson();
        return;
    }

    /**
     * Получение пользователя администратором по id.
     */
    public function getUserById(Request $request, Response $response)
    {
        if (!$this->isAdminCheck($request, $response)) {
            http_response_code(403);
            $response->setData(['error' => 'Вы не администратор.']);
            $response->sendJson();
            return;
        }

        $userId = $request->getParam('id');

        if (!$userId) {
            http_response_code(500);
            $response->setData(['success' => false, "message" => "id пользователя отсутствует."]);
            $response->sendJson();
            return;
        }

        $user = $this->userRepo->findBy($this->userRepo->getTable(), ['id' => $userId]);

        if (!$user) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => "Пользователь с id '$userId' не найден."]);
            $response->sendJson();
            return;
        }


        http_response_code(200);
        $response->setData(['success' => true, 'user' => $user]);
        $response->sendJson();
    }

    /**
     * Удаление пользователя администратором.
     */
    public function deleteUser(Request $request, Response $response)
    {

        if (!$this->isAdminCheck($request, $response)) {
            http_response_code(403);
            $response->setData(['error' => 'Вы не администратор.']);
            $response->sendJson();
            return;
        }

        $userId = $request->getParam('id');

        if (!$userId) {
            http_response_code(500);
            $response->setData(['success' => false, "message" => "id пользователя отсутствует."]);
            $response->sendJson();
            return;
        }

        $user = $this->userRepo->find($this->userRepo->getTable(), $userId);
        if (!$user) {
            http_response_code(404);
            $response->setData(['success' => false, 'message' => 'Такого пользователя ен существует.']);
            $response->sendJson();
            return;
        }

        $success = $this->userRepo->delete($userId);

        if ($success) {
            http_response_code(200);
            $response->setData(['success' => true, 'message' => 'Пользователь успешно удален.']);
        } else {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при удалении пользователя.']);
        }
        $response->sendJson();
    }

    /**
     * Обновление данных пользователя администратором.
     */
    public function updateUserField(Request $request, Response $response)
    {
        if (!$this->isAdminCheck($request, $response)) {
            http_response_code(403);
            $response->setData(['error' => 'Вы не администратор.']);
            $response->sendJson();
            return;
        }

        $userId = $request->getParam('id');

        $user = $this->userRepo->find($this->userRepo->getTable(), $userId);
        if (!$user) {
            http_response_code(404);
            $response->setData(['success' => false, 'message' => 'Такого пользователя нет.']);
            $response->sendJson();
            return;
        }

        if (!$userId) {
            http_response_code(500);
            $response->setData(['success' => false, "message" => "id пользователя отсутствует."]);
            $response->sendJson();
            return;
        }

        $data = $request->getData()['dataUser'][0];

        if (!is_array($data) || empty($data)) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => "Данные для обновления отсутствуют."]);
            $response->sendJson();
            return;
        }

        $forbiddenField = ['password', 'id'];
        foreach ($data as $key => $value) {
            if (in_array($key, $forbiddenField)) {
                http_response_code(403);
                $response->setData(['success' => false, "message" => "Поля запрещены для редактирования."]);
                $response->sendJson();
                return;
            }
        }



        foreach ($data as $key => $value) {
            if ($key === 'email' || $key === 'login') {
                if ($key == 'email') {
                    if (!filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        $response->setData(['success' => false, 'message' => 'Email не валиден.']);
                        $response->sendJson();
                        return;
                    }
                }
                $existingUser = $this->userRepo->findForAuth($value);
                if ($existingUser && $existingUser['id'] != $userId) {
                    http_response_code(400);
                    $response->setData(['success' => false, 'message' => "Значение $key - $value уже используется."]);
                    $response->sendJson();
                    return;
                }
            }

            if ($key === 'role') {
                if ((int)$value !== 0 && (int)$value !== 1) {
                    http_response_code(400);
                    $response->setData(['success' => false, 'message' => "Значение $key - должно быть '0' или '1'"]);
                    $response->sendJson();
                    return;
                }
            }
        }

        $success = $this->userRepo->update($userId, $data);
        if ($success) {
            http_response_code(200);
            $response->setData(['success' => true, 'message' => 'Данные пользователя успешно обновлены.']);
        } else {
            http_response_code(500);
            $response->setData(['success' => $success, 'message' => 'Ошибка при обновлении данных.']);
        }

        $response->sendJson();
    }

    /**
     * Сброс пароля (установка нового) администратором.
     */
    public function resetUserPassword(Request $request, Response $response)
    {
        if (!$this->isAdminCheck($request, $response)) {
            http_response_code(403);
            $response->setData(['error' => 'Вы не администратор.']);
            $response->sendJson();
            return;
        }

        $userId = $request->getParam('id');

        if (!$userId) {
            http_response_code(500);
            $response->setData(['success' => false, "message" => "id пользователя отсутствует."]);
            $response->sendJson();
            return;
        }

        $user = $this->userRepo->find($this->userRepo->getTable(), $userId);
        if (!$user) {
            http_response_code(404);
            $response->setData(['success' => false, 'message' => 'Пользователь не найден.']);
            $response->sendJson();
            return;
        }

        $data = $request->getData();
        $newPassword = $data['new_password'] ?? '';

        // Проверим, не слишком ли короткий новый пароль (например, 6 символов)
        if (strlen($newPassword) !== 0 && strlen($newPassword) < 6) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Новый пароль должен быть не менее 6 символов']);
            $response->sendJson();
            return;
        }

        $passwordToSet = !empty($newPassword) ? $newPassword : 'user123';

        $hashedPassword = password_hash($passwordToSet, PASSWORD_DEFAULT);


        $success = $this->userRepo->update($userId, ['password_hash' => $hashedPassword]);
        if ($success) {
            http_response_code(200);
            if (empty($newPassword)) {
                $response->setData(['success' => true, 'message' => 'Пароль успешно изменен на стандартный.']);
            } else {
                $response->setData(['success' => true, 'message' => 'Пароль успешно изменен.']);
            }
        } else {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при смене пароля.']);
        }
        $response->sendJson();
    }
}
