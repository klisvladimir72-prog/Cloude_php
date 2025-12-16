<?php

namespace Src\Services;

use Src\Core\App;
use Src\Models\User;

class AuthService
{
    public function authenticate(string $email, string $password): ?User
    {
        $repo = App::getService('user_repository');
        $userData = $repo->findByEmail($email);

        if ($userData && password_verify($password, $userData['password_hash'])) {
            $user = new User($userData);
            session_start();
            $_SESSION['user_id'] = $user->id;
            $_SESSION['email'] = $user->email; // <-- Это должно работать
            return $user;
        }

        return null;
    }

    public function register(string $email, string $password): bool
    {
        $repo = App::getService('user_repository');

        if ($repo->findByEmail($email)) {
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        return $repo->create([
            'email' => $email,
            'password_hash' => $hashedPassword,
            'role' => 0,
        ]);
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
