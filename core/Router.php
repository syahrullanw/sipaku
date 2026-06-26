<?php

namespace Core;

use Closure;
use InvalidArgumentException;

class Router
{
    protected Application $app;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $routes = [];

    protected ?string $lastResolvedAction = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['DELETE'], $uri, $action);
    }

    public function any(string $uri, callable|array|string $action): void
    {
        $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action);
    }

    public function addRoute(array $methods, string $uri, callable|array|string $action): void
    {
        $pattern = $this->compileUriToRegex($uri);

        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'uri' => $uri,
            'pattern' => $pattern,
            'parameters' => $this->extractParameters($uri),
            'action' => $action,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $this->lastResolvedAction = null;

        foreach ($this->routes as $route) {
            if (!$this->routeMatches($route, $request)) {
                continue;
            }

            $parameters = $this->resolveParameters($route, $request);
            $this->lastResolvedAction = $this->describeAction($route['action']);

            return $this->runRoute($route['action'], $request, $parameters);
        }

        return Response::make('404 Not Found', 404);
    }

    protected function routeMatches(array $route, Request $request): bool
    {
        if (!in_array($request->getMethod(), $route['methods'], true)) {
            return false;
        }

        return (bool) preg_match($route['pattern'], $request->getPath());
    }

    /**
     * @return array<string, string>
     */
    protected function resolveParameters(array $route, Request $request): array
    {
        $matches = [];
        preg_match($route['pattern'], $request->getPath(), $matches);

        $parameters = [];

        foreach ($route['parameters'] as $parameter) {
            if (array_key_exists($parameter, $matches)) {
                $parameters[$parameter] = $matches[$parameter];
            }
        }

        return $parameters;
    }

    protected function runRoute(callable|array|string $action, Request $request, array $parameters): Response
    {
        $result = $this->callAction($action, $request, $parameters);

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::make((string) $result);
    }

    protected function callAction(callable|array|string $action, Request $request, array $parameters): mixed
    {
        if ($action instanceof Closure || is_callable($action)) {
            return $action($request, ...array_values($parameters));
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);

            return $this->callClassAction($class, $method, $request, $parameters);
        }

        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;

            return $this->callClassAction($class, (string) $method, $request, $parameters);
        }

        throw new InvalidArgumentException('Invalid route action type.');
    }

    protected function callClassAction(string $class, string $method, Request $request, array $parameters): mixed
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Controller {$class} not found.");
        }

        $controller = new $class($this->app);

        if (!method_exists($controller, $method)) {
            throw new InvalidArgumentException("Controller method {$class}::{$method} not found.");
        }

        return $controller->{$method}($request, ...array_values($parameters));
    }

    public function getLastResolvedAction(): ?string
    {
        return $this->lastResolvedAction;
    }

    protected function compileUriToRegex(string $uri): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $uri);

        return '#^' . $pattern . '$#';
    }

    /**
     * @return array<int, string>
     */
    protected function extractParameters(string $uri): array
    {
        $matches = [];
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', $uri, $matches);

        return $matches[1] ?? [];
    }

    protected function describeAction(callable|array|string $action): string
    {
        if ($action instanceof Closure) {
            return 'closure@anonymous';
        }

        if (is_array($action) && count($action) === 2) {
            $class = is_object($action[0]) ? get_class($action[0]) : (string) $action[0];
            $method = (string) $action[1];

            return $class . '@' . $method;
        }

        if (is_string($action)) {
            return $action;
        }

        if (is_object($action) && method_exists($action, '__invoke')) {
            return get_class($action) . '@__invoke';
        }

        return 'callable';
    }
}
