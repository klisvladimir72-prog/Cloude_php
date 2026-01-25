<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class AuthController
{
    private $authService;

    public function __construct()
    {
        $this->authService = App::getService('auth_service');
    }

    /**
     * Перенаправление на форму входа
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function loginForm(Request $request, Response $response)
    {
        $response->sendHtml('login.php');
    }

    /**
     * Перенаправление на форму регистрации
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function registerForm(Request $request, Response $response)
    {
        $response->sendHtml('register.php');
    }

    /**
     * Авторизация пользователя 
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function login(Request $request, Response $response)
    {
        $data = $request->getData();


        // Токена нет значит логинимся по login or email
        $emailOrLogin = $data['email_or_login'] ?? '';
        $password = $data['password'];

        $user = $this->authService->authenticate($emailOrLogin, $password);

        if ($user) {
            // Успешная аутентификация 
            $newToken = $this->authService->generateTokenForUser($user->id);
            $this->authService->setTokenCookie($newToken);

            if ($newToken) {
                $response->setData([
                    'success' => true,
                    "id" => $user->id,
                    'redirect' => '/'
                ]);
            } else {
                http_response_code(500);
                $response->setData([
                    'success' => false,
                    'message' => 'Ошибка при создании токена'
                ]);
            }
        } else {
            http_response_code(401);
            $response->setData([
                'success' => false,
                'message' => 'Неверный логин или пароль.'
            ]);
        }


        $response->sendJson();
    }

    /**
     * Регистрация пользователя
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function register(Request $request, Response $response)
    {
        $data = $request->getData();
        $email = trim($data['email'] ?? '');
        $login = trim($data['login'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password) || empty($login)) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Email, Login и пароль обязательны']);
            $response->sendJson();
            return;
        }

        $success = $this->authService->register($email, $password, $login);

        if ($success['success']) {
            // После успешной регистрации — не логиним пользователя и не возвращаем токен
            // Логин происходит отдельно через /login
            $response->setData(['success' => true, 'message' => $success['message']]);
        } else {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => $success['message']]);
        }
        $response->sendJson();
    }

    /**
     * Выход пользователя
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function logout(Request $request, Response $response)
    {

        try {
            // Извлекаем токен из Cookie 
            $token = $_COOKIE['auth_token'] ?? null;

            if ($token) {
                $authService = App::getService('auth_service');

                $user = $authService->getUserByToken($token);
                if ($user) {
                    $authService->removeTokenForUser($user->id);
                } else {
                    http_response_code(500);
                    $response->setData(['success' => false, 'message' => 'Ошибка при выходе из системы.']);
                    $response->sendJson();
                    exit();
                }
            }

            $this->authService->unsetTokenCookie();

            http_response_code(200);
            $response->setData(['success' => true, 'message' => 'Вы успешно вышли из системы.']);
            $response->sendJson();
            exit();
        } catch (\Exception $e) {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при выходе из системы.']);
            $response->sendJson();
            exit();
        }
    }


    /**
     * Отображение формы для смены пароля
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function showChangePasswordForm(Request $request, Response $response)
    {
        // Проверяем токен через AuthMiddleware
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        }

        // Получаем объект пользователя (необязательно, можно использовать его данные)
        $user = $authResult['user'];
        $userId = $user->id;

        // Просто отображаем форму
        $response->sendHtml('change_password.php', [
            'login' => $user->login,
            'id' => $userId
        ]);
    }

    /**
     * Метод для смены пароля пользователем
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function changePassword(Request $request, Response $response)
    {
        // Проверяем токен через AuthMiddleware
        $authResult = AuthMiddleware::handle($request, $response);
        if (!$authResult) {
            http_response_code(401);
            $response->sendHtml('login.php');
            return;
        }

        // Получаем объект пользователя
        $user = $authResult['user'];
        $userId = $user->id;

        $data = $request->getData();
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmNewPassword = $data['confirm_new_password'] ?? '';

        // Проверки
        if (!$currentPassword || !$newPassword || !$confirmNewPassword) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Все поля обязательны']);
            $response->sendJson();
            return;
        }

        if ($newPassword !== $confirmNewPassword) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Новый пароль и подтверждение не совпадают']);
            $response->sendJson();
            return;
        }

        // Проверим, не слишком ли короткий новый пароль (например, 6 символов)
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Новый пароль должен быть не менее 6 символов']);
            $response->sendJson();
            return;
        }

        $userRepo = App::getService('user_repository');

        // Получаем текущий хеш пароля из БД
        $currentUserData = $userRepo->find('users', $userId);
        if (!$currentUserData) {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка: пользователь не найден']);
            $response->sendJson();
            return;
        }

        // Проверяем текущий пароль
        if (!password_verify($currentPassword, $currentUserData['password_hash'])) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Текущий пароль неверен']);
            $response->sendJson();
            return;
        }

        // Хешируем новый пароль
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Обновляем пароль в БД
        $success = $userRepo->update($userId, ['password_hash' => $hashedNewPassword]);

        if ($success) {
            $response->setData(['success' => true, 'message' => 'Пароль успешно изменён.']);
        } else {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при изменении пароля.']);
        }
        $response->sendJson();
    }
}
