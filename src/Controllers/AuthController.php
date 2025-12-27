<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;

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
        $data = $request->getData();
        // Получаем email или login из запроса
        $emailOrLogin = $data['email_or_login'] ?? '';
        $password = $data['password'] ?? '';

        $authService = App::getService('auth_service');
        // Аутентифицируем пользователя
        $user = $authService->authenticate($emailOrLogin, $password);

        if ($user) {
            $response->setData(['success' => true, 'redirect' => '/']);
        } else {
            http_response_code(401);
            $response->setData(['success' => false, 'message' => 'Неверный логин или пароль']);
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
        $success = $authService->register($email, $password, $login); // Передаем login

        if ($success) {
            // После успешной регистрации — сразу логиним пользователя
            $user = $authService->authenticate($email, $password); // Можно использовать email или login
            if ($user) {
                // session_start() и установка $_SESSION уже внутри authenticate
                $response->setData(['success' => true, 'message' => 'Пользователь зарегистрирован', 'redirect' => '/']);
            } else {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => 'Ошибка авторизации после регистрации']);
            }
        } else {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Пользователь с таким email или login уже существует']);
        }
        $response->sendJson();
    }

    public function logout(Request $request, Response $response)
    {
        session_start();
        session_destroy();
        header('Location: /');
        exit();
    }
}
