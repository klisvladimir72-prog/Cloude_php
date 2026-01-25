<?php

namespace Src\Core;

use Src\Middleware\AuthMiddleware;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    private array $patternRoutes = [];

    public function add(string $method, string $path, callable $callback): void
    {
        if (str_contains($path, '{')) {
            $this->patternRoutes[$method][] = [
                'pattern' => $this->convertToRegex($path),
                'callback' => $callback,
            ];
        } else {
            $this->routes[$method][$path] = $callback;
        }
    }

    /**
     * Роутинг с проверкой на авторизацию пользователя
     *
     * @param string $method
     * @param string $path
     * @param callable $callback
     * @return void
     */
    public function addSecureByAuth(string $method, string $path, callable $callback): void
    {
        $wrapper = function (Request $request, Response $response) use ($callback) {
            $authResult = AuthMiddleware::handle($request, $response);

            if (isset($authResult['error'])) {
                $response->setData(['error' => "Доступ запрещен."]);
                $response->sendJson();
                return;
            }

            $user = $authResult['user'];
            $request->setUser($user);

            return $callback($request, $response, $user);
        };

        $this->add($method, $path, $wrapper);
    }

    private function convertToRegex(string $path): string
    {
        // Разбиваем строку по шаблонным параметрам
        $parts = preg_split('/\{([^}]+)\}/', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';

        $isParam = false;
        foreach ($parts as $part) {
            if ($isParam) {
                // Это имя параметра
                $regex .= '(?<' . $part . '>[^/]+)';
            } else {
                // Это обычный текст
                $regex .= preg_quote($part, '#'); // <-- Используем # как ограничитель
            }
            $isParam = !$isParam;
        }

        return '#^' . $regex . '$#'; // <-- Используем # как ограничитель
    }

    public function processRequest(Request $request)
    {
        $response = new Response();
        $method = $request->getMethod();
        $path = $request->getRoute();

        // Проверяем точные маршруты
        if (isset($this->routes[$method][$path])) {
            $callback = $this->routes[$method][$path];
            $callback($request, $response);
            return;
        }

        // Проверяем шаблонные маршруты
        if (isset($this->patternRoutes[$method])) {
            foreach ($this->patternRoutes[$method] as $route) {
                if (preg_match($route['pattern'], $path, $matches)) {
                    unset($matches[0]); // убираем полное совпадение
                    $request->setMatches($matches);
                    $callback = $route['callback'];
                    $callback($request, $response);
                    return;
                }
            }
        }

        http_response_code(404);
        $response->setData(['success' => false, 'error' => 'Маршрут не найден']);
        $response->sendJson();
        return;
    }
}
