<?php

namespace AJM\Core;

/**
 * Router — enrutamiento mínimo para public/index.php.
 * Deliberadamente pequeño (sin librería externa): el sitio público tiene
 * un número acotado de patrones de URL (home, listados, detalle por slug).
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $paramNames = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, rtrim($pattern, '/'));

        $regex = '#^' . ($regex === '' ? '' : $regex) . '/?$#';

        $this->routes[] = [
            'method'  => $method,
            'regex'   => $regex,
            'params'  => $paramNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $args = array_combine($route['params'], $matches);
                call_user_func($route['handler'], $args);
                return;
            }
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        $repo = new Repository();
        $page = $repo->findBySlug('pagina', '404');
        if ($page) {
            View::display(ROOT_PATH . '/templates/public/pagina.php', ['entry' => $page]);
            return;
        }
        echo '<h1>404 — Página no encontrada</h1>';
    }
}
