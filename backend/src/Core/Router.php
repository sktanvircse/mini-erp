<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): void
    {
        $fullPath = '';
        $allMiddlewares = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $fullPath .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $allMiddlewares = array_merge($allMiddlewares, $m);
            }
        }

        $fullPath .= '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        $allMiddlewares = array_merge($allMiddlewares, $middlewares);

        // Convert {param} to named regex. For ID parameters, strictly match digits to avoid shadowing sub-routes
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) {
            $param = $m[1];
            if ($param === 'id' || str_ends_with($param, '_id')) {
                return '(?P<' . $param . '>\d+)';
            }
            return '(?P<' . $param . '>[^/]+)';
        }, $fullPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];
    }

    public function dispatch(Request $request): void
    {
        $reqMethod = $request->getMethod();
        $reqUri = $request->getUri();

        // Strip subfolder if hosted in a subfolder (e.g. /mini_erp/backend/public)
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($reqUri, $scriptName)) {
            $reqUri = substr($reqUri, strlen($scriptName));
            if (!str_starts_with($reqUri, '/')) {
                $reqUri = '/' . $reqUri;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $reqMethod && $route['method'] !== 'ANY') {
                continue;
            }

            if (preg_match($route['regex'], $reqUri, $matches)) {
                $routeParams = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $routeParams[$k] = $v;
                    }
                }
                $request->setRouteParams($routeParams);

                // Run middlewares
                foreach ($route['middlewares'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middleware->handle($request);
                }

                // Invoke Handler
                $handler = $route['handler'];
                if (is_callable($handler)) {
                    call_user_func($handler, $request);
                    return;
                }

                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    call_user_func([$controller, $method], $request);
                    return;
                }
            }
        }

        Response::notFound("Endpoint not found: {$reqMethod} {$reqUri}");
    }
}
