<?php

namespace Src\Middleware;

use Src\Core\Request;
use Src\Core\Response;
use Src\Core\App;
use Src\Services\AuthService;

/**
 * Класс для проверки токена
 */
class AuthMiddleware
{
    public static function handle(Request $request, Response $response): ?array
    {
        $authService = App::getService('auth_service');

        // Извлекаем токен из Cookie 
        $token = $_COOKIE['auth_token'] ?? null;

        if (!$token) {
            // Токен не найден в заголовке 
            return ['success' => false, 'error' => 'Пользователь не авторизован.'];
        }

        $user = $authService->getUserByToken($token);

        if (!$user) {
            // Токен недействителен 
            return ['success' => false, 'error' => 'Неверный или просроченный токен.'];
        }

        return ['user' => $user];
    }
}
