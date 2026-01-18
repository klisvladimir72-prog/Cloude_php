<?php

namespace Src\Core;

class Request
{
    private array $data;
    private string $route;
    private string $method;
    private array $matches = [];

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $input = file_get_contents('php://input');

        if ($this->method === "PUT") {
            parse_str($input, $putData);
            $this->data = json_decode($input, true) ?: $putData;
        } else {
            $this->data = json_decode($input, true) ?: $_POST;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->route = trim($path, '/');
    }

    public function setMatches(array $matches): void
    {
        $this->matches = $matches;
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Получает все HTTP-заголовки запроса.
     * @return array Ассоциативный массив заголовков.
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $header = strtolower($header);
                $header = ucwords($header, '-');
                $headers[$header] = $value;
            }
        }

        // Некоторые серверы могут передавать заголовки Authorization через переменную $_SERVER
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders['Authorization'])) {
                $headers['Authorization'] = $apacheHeaders['Authorization'];
            }
        }

        return $headers;
    }

    /**
     * Получает параметр из GET-запроса (query string)
     */
    public function getQueryParam(string $key, $default = null): string
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if ($_SERVER['QUERY_STRING'] === '') {
            return '';
        }

        parse_str($queryString, $queryParams);
        return $queryParams[$key] ?? $default;
    }

    public function getQueryParamsAll(): array
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if ($queryString === '') {
            return [];
        }

        parse_str($queryString, $queryParams);

        return $queryParams;
    }
}
