<?php

namespace Src\Controllers;

use Src\Core\Request;
use Src\Core\Response;
use Src\Services\PasswordResetService;

class PasswordResetController
{
    private PasswordResetService $service;

    public function __construct()
    {
        $this->service = new PasswordResetService();
    }

    public function requestResetPassword(Request $request, Response $response)
    {
        try {
            $email = $request->getQueryParam('email');

            if (empty($email)) {
                http_response_code(400);
                $response->setData(['success' => false, 'message' => 'Email не указан.']);
                $response->sendJson();
                return $response;
            }

            $result = $this->service->initiatePasswordReset($email);

            http_response_code($result['success'] ? 200 : 400);
            $response->setData($result);
            $response->sendJson();

            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            $response->setData([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
                'debug' => $e->getMessage()
            ]);
            $response->sendJson();
        }
    }
}
