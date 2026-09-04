<?php

namespace CureRx\Core;

class Request
{
    private string $method;
    private array  $query;
    private array  $post;
    private array  $headers;
    private ?array $jsonBody = null;

    private function __construct(
        string $method,
        array $query,
        array $post,
        array $headers
    ) {
        $this->method  = strtoupper($method);
        $this->query   = $query;
        $this->post    = $post;
        $this->headers = $headers;
    }

    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_GET,
            $_POST,
            getallheaders() ?: []
        );
    }

    public function method(): string { return $this->method; }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $normalized) {
                return $v;
            }
        }
        return null;
    }

    public function body(): array
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = json_decode($raw ?: '', true) ?? [];
        }
        return $this->jsonBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }
}
