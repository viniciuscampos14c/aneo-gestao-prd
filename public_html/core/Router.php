<?php

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][trim($path, '/')] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][trim($path, '/')] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = trim($path, '/');

        if (!isset($this->routes[$method][$path])) {
            http_response_code(404);
            view('generic/not_found', ['path' => $path], 'layouts/guest');
            return;
        }

        $handler = $this->routes[$method][$path];
        $handler();
    }
}
