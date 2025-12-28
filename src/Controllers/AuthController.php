<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Services\AuthService;

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
}
