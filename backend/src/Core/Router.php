<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as [$m, $p, $h]) {
            $params = $this->match($p, $path);
            if ($m === $method && $params !== null) {
                $h($params);
                return;
            }
        }
        Response::error('Not Found', 404);
    }

    // Supporte /api/admin/events/{id}/approve
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $matches)) return null;

        $params = [];
        foreach ($matches as $k => $v) {
            if (!is_int($k)) $params[$k] = $v;
        }
        return $params;
    }
}
