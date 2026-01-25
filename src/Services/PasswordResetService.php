<?php

namespace Src\Services;

use Src\Core\App;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Dotenv\Dotenv;

// Загружаем .env
$dotenv = Dotenv::createImmutable(realpath(__DIR__ . "../../../"));
$dotenv->load();

class PasswordResetService
{
    private $userRepo;
    private $resetTokenRepo;

    public function __construct()
    {
        $this->userRepo = App::getService('user_repository');
        $this->resetTokenRepo = App::getService('password_reset_repository');
    }

    public function initiatePasswordReset(string $email): array
    {
        // Проверяем, существует ли пользователь
        $user = $this->userRepo->findBy($this->userRepo->getTable(), ['email' => $email]);

        if (empty($user)) {
            return ['success' => false, 'message' => 'Пользователь с таким email не найден.'];
        }

        // Проверяем ограничения
        $rateLimitCheck = $this->checkRateLimit($email);

        if (!$rateLimitCheck['allowed']) {
            return ['success' => false, 'message' => $rateLimitCheck['message']];
        }

        // Удаляем старые токены для этого email
        $this->resetTokenRepo->deleteExpiredTokens($email);

        // Генерируем токен
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Сохраняем токен в БД
        $this->resetTokenRepo->create(['email' => $email, 'token' => $token, 'expires_at' => $expiresAt]);

        // Формируем ссылку
        $resetLink = "https://cloudstorage/reset-password-form?token=$token";

        // Отправляем email
        $this->sendResetEmail($email, $resetLink);

        return [
            'success' => true,
            'message' => 'Ссылка для сброса пароля отправлена на ваш email.'
        ];
    }

    private function checkRateLimit(string $email): array
    {
        // Проверяем, был ли запрос за последние 15 минут
        $recentCount = $this->resetTokenRepo->hasRecentRequest($email, 15);

        if ($recentCount > 0) {
            return [
                'allowed' => false,
                'message' => 'Слишком частые запросы. Повторите попытку через 15 минут.'
            ];
        }

        // Проверяем, был ли запрос за последние 24 часа (лимит 5)
        $dailyCount = $this->resetTokenRepo->hasDailyRequests($email, 24);

        if ($dailyCount >= 5) {
            return [
                'allowed' => false,
                'message' => 'Превышено количество запросов на сегодня. Попробуйте завтра.'
            ];
        }

        return ['allowed' => true];
    }

    private function sendResetEmail(string $email, string $link): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'];
        $mail->Password = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port = intval($_ENV['MAIL_PORT']);
        $mail->CharSet = 'UTF-8';


        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($email, 'Получатель');

        $mail->isHTML(true);
        $mail->Subject = 'Сброс пароля';
        $mail->Body = "
            <h2>Сброс пароля</h2>
            <p>Для сброса пароля перейдите по ссылке:</p>
            <a href='$link'>$link</a>
        ";

        $mail->send();
    }
}
