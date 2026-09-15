<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): void { $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable|array $handler): void { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $normalized = '/' . trim($path, '/');
        if ($normalized === '//') {
            $normalized = '/';
        }
        $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', $normalized);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . $pattern . '/?$#',
            'handler' => $handler,
        ];
    }

    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }
            return [
                'handler' => $route['handler'],
                'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
            ];
        }
        return null;
    }

    public function dispatch(Request $request): mixed
    {
        $match = $this->match($request->method(), $request->path());
        if ($match === null) {
            return Response::json(['error' => 'Không tìm thấy tài nguyên.'], 404);
        }

        $handler = $match['handler'];
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }
        return $handler($request, ...array_values($match['params']));
    }
}
