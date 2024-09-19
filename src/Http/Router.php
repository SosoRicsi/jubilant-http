<?php

declare(strict_types=1);

namespace Jubilant\Http;

use ReflectionMethod;
use ReflectionFunction;
use Closure;

class Router {
    private array $routes = [];
    private $notFoundHandler = null;
    private string $currentGroupPrefix = '';
    private array $currentGroupMiddleware = [];

    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';

    /**
     *| Add a GET route.
     */
    public function get(string $path, mixed $handler, ?array $middleware = null): void {
        $this->addRoute(self::METHOD_GET, $path, $handler, $middleware ?? []);
    }

    /**
     *| Redirect route.
     */
    public function redirect(string $path, string $redirectTo): void {
        $handler = function() use ($redirectTo) {
            header("Location: " . $redirectTo);
            exit();
        };
        $this->addRoute(self::METHOD_GET, $path, $handler);
    }

    /**
     *| Add a POST route.
     */
    public function post(string $path, mixed $handler, ?array $middleware = null): void {
        $this->addRoute(self::METHOD_POST, $path, $handler, $middleware ?? []);
    }

    /**
     *| Set the 404 not found handler.
     */
    public function add404Handler(callable $handler): void {
        $this->notFoundHandler = $handler;
    }

    /**
     *| Group routes with a prefix and middleware.
     */
    public function group(string $prefix, array $middleware, Closure $callback): void {
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddleware = $this->currentGroupMiddleware;

        $this->currentGroupPrefix .= $prefix;
        $this->currentGroupMiddleware = array_merge($this->currentGroupMiddleware, $middleware);

        $callback($this);

        // Restore previous group settings
        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    /**
     *| Add a route to the router.
     */
    private function addRoute(string $method, string $path, mixed $handler, array $middleware = []): void {
        $fullPath = $this->currentGroupPrefix . $path;
        $fullMiddleware = array_merge($this->currentGroupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $fullMiddleware,
        ];
    }

    /**
     *| Match a route with parameters.
     */
    private function match(string $requestPath, string $path, array &$params): bool {
        $pathParts = explode('/', trim($path, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($pathParts) !== count($requestParts)) {
            return false;
        }

        foreach ($pathParts as $index => $part) {
            if (preg_match('/\{(\w+):(.+)\}/', $part, $matches)) {
                $paramName = $matches[1];
                $pattern = $matches[2];
                if (!preg_match('/^' . $pattern . '$/', $requestParts[$index])) {
                    return false;
                }
                $params[$paramName] = $requestParts[$index];
            } elseif ($part !== $requestParts[$index]) {
                return false;
            }
        }
        return true;
    }

    /**
     *| Resolve dependencies for the handler.
     */
    private function resolveDependencies(array $params, callable $handler): array {
        $dependencies = [];

        $reflection = is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction($handler);

        foreach ($reflection->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            if (array_key_exists($paramName, $params)) {
                $dependencies[] = $params[$paramName];
            } elseif ($paramType && !$paramType->isBuiltin()) {
                $dependencies[] = new ($paramType->getName())();
            } else {
                $dependencies[] = $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null;
            }
        }

        return $dependencies;
    }

    /**
     *| Run the router.
     */
    public function run(string $uri = null, string $method = null): void {
        $requestUri = parse_url($uri ?? $_SERVER['REQUEST_URI']);
        $requestPath = $requestUri['path'];
        $method = $method ?? $_SERVER['REQUEST_METHOD'];

        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->match($requestPath, $route['path'], $params)) {
                foreach ($route['middleware'] as $middleware) {
                    $middlewareInstance = new $middleware;
                    if (!$middlewareInstance->handle($requestPath, $method)) {
                        return;
                    }
                }

                $callback = $route['handler'];
                $dependencies = $this->resolveDependencies($params, $callback);

                if (is_array($callback)) {
                    [$controller, $method] = $callback;
                    if (class_exists($controller) && method_exists($controller, $method)) {
                        call_user_func_array([new $controller(), $method], $dependencies);
                        return;
                    }
                } else {
                    call_user_func_array($callback, $dependencies);
                    return;
                }
            }
        }

        header("HTTP/1.0 404 Not Found");
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler);
        } else {
            echo '404 Not Found';
        }
    }
}
