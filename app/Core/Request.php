<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $body;

    public function __construct(
        private readonly ?string $requestMethod = null,
        private readonly ?string $requestUri = null,
        ?array $body = null
    ) {
        $this->body = $body ?? $this->parseBody();
    }

    public function method(): string
    {
        $method = strtoupper($this->requestMethod ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $path = parse_url($this->requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with((string) $path, $scriptDir)) {
            $path = substr((string) $path, strlen($scriptDir));
        } elseif ($scriptDir !== '' && str_ends_with($scriptDir, '/public')) {
            $parentDir = substr($scriptDir, 0, -7);
            if ($parentDir !== '' && str_starts_with((string) $path, $parentDir)) {
                $path = substr((string) $path, strlen($parentDir));
            }
        }
        return '/' . trim((string) $path, '/');
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        if (!empty($_POST)) {
            return $_POST;
        }
        parse_str((string) file_get_contents('php://input'), $parsed);
        return is_array($parsed) ? $parsed : [];
    }
}
