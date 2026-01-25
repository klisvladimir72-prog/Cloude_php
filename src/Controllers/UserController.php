<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class UserController
{
    // Проверка на администратора.
    private function isAdminCheck(Request $request, Response $response)
    {
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        }

        $user = $authResult['user'];
        if ($user->role !== 1) {
            http_response_code(403);
            $response->setData(['error' => 'Доступ запрещен']);
            $response->sendJson();
            return;
        }

        return $user;
    }

    /**
     * Метод для отображения страницы управления пользователями для администратора.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
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

    /**
     * Получение списка всех пользователей.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getUsersList(Request $request, Response $response)
    {
        $userRepo = App::getService('user_repository');

        $allUsers = $userRepo->findAll($userRepo->getTable());

        if (!$allUsers) {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при получении списка пользователей.']);
            $response->sendJson();
            return;
        }

        $filteredUsers = array_map(function ($user) {
            unset($user['password_hash']);
            unset($user['created_at']);
            return $user;
        }, $allUsers);

        $response->setData($filteredUsers);
        http_response_code(200);
        $response->sendJson();
    }

    /**
     * Получение пользователя по `email`.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getUserByEmail(Request $request, Response $response)
    {
        $userRepo = App::getService('user_repository');

        $email = $request->getParam('email');
        if (!$email) {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'email отсутствует.']);
            $response->sendJson();
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => "Email не валиден."]);
            $response->sendJson();
            return;
        }

        $user = $userRepo->findBy($userRepo->getTable(), ["email" => $email]);

        if (!$user) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => "Пользователь с '$email' не найден."]);
            $response->sendJson();
            return;
        }


        unset($user[0]['password_hash']);
        unset($user[0]['created_at']);

        http_response_code(200);
        $response->setData(['success' => true, 'user' => $user]);


        $response->sendJson();
    }

    /**
     * Получение пользователя по `id`.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function getUserById(Request $request, Response $response)
    {
        $userRepo = App::getService('user_repository');

        $user_id = $request->getParam('id');

        if (!$user_id) {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'id отсутствует.']);
            $response->sendJson();
            return;
        }


        $user = $userRepo->findBy($userRepo->getTable(), ["id" => $user_id]);

        if (!$user) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => "Пользователь с '$user_id' не найден."]);
            $response->sendJson();
            return;
        }


        unset($user[0]['password_hash']);
        unset($user[0]['created_at']);

        http_response_code(200);
        $response->setData(['success' => true, 'user' => $user]);


        $response->sendJson();
    }

    /**
     * Изменение данных о пользователе (самим пользователем).
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function updateUserFieldByUser(Request $request, Response $response)
    {
        // Получаем объект пользователя (необязательно, можно использовать его данные)
        $user = $request->getUser();
        $userId = $user->id;

        $data = $request->getData();

        $data = isset($data['data']) ? $data['data'] : $data;


        // Запрет на смену пароля, id, роли
        if (!is_array($data) || empty($data)) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Данные для обновления не предоставлены.']);
            $response->sendJson();
            return;
        }

        $forbiddenFields = ['password', 'id', 'role'];
        foreach ($data as $field => $value) {
            if (in_array($field, $forbiddenFields)) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => 'Доступ запрещен.']);
                $response->sendJson();
                return;
            }
        }

        $allowFields = ['email', 'login'];
        // Проверка на запрещенное поле 
        foreach ($data as $field => $value) {
            if (!in_array($field, $allowFields)) {
                http_response_code(403);
                $response->setData(['success' => false, 'message' => "Поле '$field' не разрешено для редактирования."]);
                $response->sendJson();
                return;
            }
        }

        $userRepo = App::getService('user_repository');
        foreach ($data as $field => $value) {
            if ($field === 'email' || $field === 'login') {
                if ($field === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        $response->setData(['success' => false, 'message' => "Email не валиден."]);
                        $response->sendJson();
                        return;
                    }
                }
                $existingUser = $userRepo->findForAuth($value);
                if ($existingUser && $existingUser['id'] != $userId) {
                    http_response_code(400);
                    $response->setData(['success' => false, 'message' => "Значение '$value' уже используется."]);
                    $response->sendJson();
                    return;
                }
            }
        }



        $success = $userRepo->update($userId, $data);

        if ($success) {
            http_response_code(200);
            $response->setData(['success' => true, 'message' => 'Данные обновлены.']);
        } else {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при обновлении.']);
        }

        $response->sendJson();
    }
}
