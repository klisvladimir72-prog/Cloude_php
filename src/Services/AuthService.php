<?php

namespace Src\Services;

use Src\Core\App;
use Src\Models\User;

class AuthService
{
    /**
     * Аутентифицирует пользователя по email или login и паролю.
     *
     * @param string $emailOrLogin Email или Login пользователя
     * @param string $password Пароль пользователя
     * @return User|null Объект User, если аутентификация успешна, иначе null
     */
    public function authenticate(string $emailOrLogin, string $password): ?User
    {
        $repo = App::getService('user_repository');

        // Используем новый метод findForAuth из UserRepository
        $userData = $repo->findForAuth($emailOrLogin);

        if ($userData && password_verify($password, $userData['password_hash'])) {
            $user = new User($userData); // Предполагаем, что модель User обновлена
            session_start();
            $_SESSION['user_id'] = $user->id;
            $_SESSION['email'] = $user->email;
            $_SESSION['login'] = $user->login; // Сохраняем login в сессии
            return $user;
        }

        return null;
    }

    /**
     * Регистрирует нового пользователя.
     *
     * @param string $email Email пользователя
     * @param string $password Пароль пользователя
     * @param string $login Логин пользователя
     * @return bool True, если регистрация успешна
     */
    public function register(string $email, string $password, string $login): bool
    {
        $repo = App::getService('user_repository');

        // Проверяем, существует ли уже пользователь с таким email или login
        // Используем метод findByEmailOrLogin из UserRepository
        $existingUser = $repo->findByEmailOrLogin($email, $login);
        if ($existingUser) {
            return false; // Пользователь уже существует
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        return $repo->create([
            'email' => $email,
            'login' => $login, // Сохраняем login
            'password_hash' => $hashedPassword,
            'role' => 0,
        ]);
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
