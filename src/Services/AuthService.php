<?php

namespace Src\Services;

use Src\Core\App;
use Src\Models\User;
use Src\Repositories\UserTokenRepository;
use Src\Repositories\UserRepository;

class AuthService
{
    private UserRepository $userRepo;
    private UserTokenRepository $userTokenRepo;

    public function __construct()
    {
        $this->userRepo = App::getService('user_repository');
        $this->userTokenRepo = App::getService('user_token_repository');
    }

    /**
     * Аутентифицирует пользователя по email или логину и паролю.
     * Возвращает объект User, если аутентификация успешна.
     *
     * @param string $emailOrLogin
     * @param string $password
     * @return User|null
     */
    public function authenticate(string $emailOrLogin, string $password): ?User
    {
        // Используем метод из UserRepository
        $userData = $this->userRepo->findForAuth($emailOrLogin);

        if ($userData && password_verify($password, $userData['password_hash'])) {
            return new User($userData);
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
        // Проверяем, существует ли уже пользователь с таким email или login
        // Используем метод findByEmailOrLogin из UserRepository
        if ($this->userRepo->findByEmailOrLogin($email, $login)) {
            return false; // Пользователь уже существует
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        return $this->userRepo->create([
            'email' => $email,
            'login' => $login, // Сохраняем login
            'password_hash' => $hashedPassword,
            'role' => 0,
        ]);
    }

    /**
     * Проверяет токен и возвращает пользователя.
     *
     * @param string $token Токен
     * @return User|null Объект User или null
     */
    public function getUserByToken(string $token): ?User
    {
        $tokenData = $this->userTokenRepo->findByToken($token);

        if ($tokenData) {
            // Проверка срока действия токена
            $expiresAt = new \DateTime($tokenData['expires_at']);
            $now = new \DateTime();

            if ($now > $expiresAt) {
                // токен просрочен и удаляем его 
                $this->userTokenRepo->deleteByToken($token);
                return null;
            }

            // Находим пользователя по user_id 
            $userData = $this->userRepo->find($this->userRepo->getTable(), $tokenData['user_id']);
            if ($userData) {
                return new User($userData);
            }
        }


        return null;
    }

    /**
     * Генерирует и сохраняет новый токен для пользователя.
     * Возвращает новый токен, если успешно.
     *
     * @param int $userId
     * @return string|null
     */
    public function generateTokenForUser(int $userId): ?string
    {
        $newToken = bin2hex(random_bytes(32)); // 64 символьный токен

        // Устанавливаем срок действия токена (например 1 день)
        $expiresAt = (new \DateTime())->modify('+1 day');

        if ($this->userTokenRepo->createToken($userId, $newToken, $expiresAt)) {
            return $newToken;
        }

        return null;
    }


    /**
     * Удаляет токен (logout).
     *
     * @param int $userId
     * @return bool Успешно ли удалено
     */
    public function removeTokenForUser(int $userId): bool
    {
        return $this->userTokenRepo->deleteAllForUser($userId);
    }
}
