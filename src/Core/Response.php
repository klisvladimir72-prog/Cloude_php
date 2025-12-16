<?php

namespace Src\Core;

class Response
{
    private array $data = [];
    private array $headers = [];

    public function setData(array $data): void
    {
        $this->data = $data;
    }
    public function setHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    public function sendJson(): void
    {
        header('Content-Type: application/json');
        foreach ($this->headers as $h) header($h);
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    public function sendHtml(string $templateFile, array $params = []): void
    {
        extract($params);
        ob_start();
        include __DIR__ . "/../Templates/$templateFile";
        $content = ob_get_clean();
        echo $content;
        exit();
    }
}
