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
        $this->data = json_decode($input, true) ?: $_POST;

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
}
