<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Middleware\AuthMiddleware;

class AuthController
{
    public function loginForm(Request $request, Response $response)
    {
        $response->sendHtml('login.php');
    }

    public function registerForm(Request $request, Response $response)
    {
        $response->sendHtml('register.php');
    }

    public function login(Request $request, Response $response)
    {
        $authService = App::getService('auth_service');
        $data = $request->getData();


        // Токена нет значит логинимся по login or email
        $emailOrLogin = $data['email_or_login'] ?? '';
        $password = $data['password'];

        $user = $authService->authenticate($emailOrLogin, $password);

        if ($user) {
            // Успешная аутентификация 
            $newToken = $authService->generateTokenForUser($user->id);
            $this->setTokenCookie($newToken);

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

        $authService = App::getService('auth_service');
        $success = $authService->register($email, $password, $login);

        if ($success) {
            // После успешной регистрации — не логиним пользователя и не возвращаем токен
            // Логин происходит отдельно через /login
            $response->setData(['success' => true, 'message' => 'Пользователь зарегистрирован. Пожалуйста, войдите.', 'redirect' => '/login']);
        } else {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Пользователь с таким email или login уже существует']);
        }
        $response->sendJson();
    }


    public function logout(Request $request, Response $response)
    {
        // Извлекаем токен из Cookie 
        $token = $_COOKIE['auth_token'] ?? null;

        if ($token) {
            $authService = App::getService('auth_service');

            $user = $authService->getUserByToken($token);
            if ($user) {
                $authService->removeTokenForUser($user->id);
            }
        }

        $this->unsetTokenCookie();

        header('Location: /');
        exit();
    }

    private function setTokenCookie(string $token): void
    {
        // Устанавливаем HTTP-only cookie
        // Срок действия такой же как и бд 
        setcookie('auth_token', $token, [
            'expires' => time() + (1 * 24 * 60 * 60), //1 день
            'path' => '/',
            'secure' => true, // при использовании HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function unsetTokenCookie(): void
    {
        setcookie('auth_token', '', [
            'expires' => time() - 3600, // Устанавливаем в прошлое 
            'path' => '/',
            'secure' => true, // при использовании HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

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
            $response->setData(['success' => true, 'message' => 'Пароль успешно изменён']);
        } else {
            http_response_code(500);
            $response->setData(['success' => false, 'message' => 'Ошибка при изменении пароля']);
        }
        $response->sendJson();
    }
}
