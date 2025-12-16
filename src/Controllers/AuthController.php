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
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $authService = App::getService('auth_service');
        $user = $authService->authenticate($email, $password);

        if ($user) {
            session_start();
            $_SESSION['user_id'] = $user->id;
            $response->setData(['success' => true, 'redirect' => '/']);
        } else {
            http_response_code(401);
            $response->setData(['success' => false, 'message' => 'Неверный логин или пароль']);
        }
        $response->sendJson();
    }

    public function register(Request $request, Response $response)
    {
        $data = $request->getData(); // Данные из JSON уже в $data
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Email и пароль обязательны']);
            $response->sendJson();
            return;
        }

        $authService = App::getService('auth_service');
        $success = $authService->register($email, $password);

        if ($success) {
            // После успешной регистрации — сразу логиним пользователя
            $user = $authService->authenticate($email, $password);
            if ($user) {
                session_start();
                $_SESSION['user_id'] = $user->id;
                $response->setData(['success' => true, 'message' => 'Пользователь зарегистрирован', 'redirect' => '/']);
            } else {
                http_response_code(500);
                $response->setData(['success' => false, 'message' => 'Ошибка авторизации после регистрации']);
            }
        } else {
            http_response_code(400);
            $response->setData(['success' => false, 'message' => 'Пользователь с таким email уже существует']);
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
