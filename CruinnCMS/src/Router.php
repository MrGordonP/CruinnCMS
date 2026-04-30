<?php
/**
 * CruinnCMS — Custom Router
 *
 * Fully custom URL router. No external dependencies.
 * Supports clean URLs with named parameters: /events/{slug}
 * Middleware pipeline: auth checks, CSRF, rate limiting run before controllers.
 */

namespace Cruinn;

class Router
{
    /** @var array Registered routes grouped by HTTP method */
    private array $routes = [];

    /** @var array Global middleware applied to all routes */
    private array $globalMiddleware = [];

    /** @var array Route-specific middleware patterns */
    private array $routeMiddleware = [];

    // ── Route Registration ────────────────────────────────────────

    /**
     * Register a GET route.
     */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    /**
     * Register a route for any method.
     */
    public function any(string $pattern, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    /**
     * Add middleware that runs on every request.
     */
    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Add middleware for routes matching a URL prefix.
     * Example: addPrefixMiddleware('/admin', $authCheck)
     */
    public function addPrefixMiddleware(string $prefix, callable $middleware): void
    {
        $this->routeMiddleware[] = [
            'prefix'     => $prefix,
            'middleware'  => $middleware,
        ];
    }

    // ── Route Dispatching ─────────────────────────────────────────

    /**
     * Dispatch the current request to the matching route handler.
     * Returns the handler's response or sends a 404.
     */
    public function dispatch(string $method, string $uri): mixed
    {
        // Normalise: strip query string, ensure leading slash, remove trailing slash
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $method = strtoupper($method);

        if (!isset($this->routes[$method])) {
            return $this->notFound();
        }

        foreach ($this->routes[$method] as $route) {
            $params = $this->match($route['pattern'], $uri);
            if ($params !== false) {
                // Run global middleware
                foreach ($this->globalMiddleware as $mw) {
                    $result = call_user_func($mw, $uri, $method);
                    if ($result !== null) {
                        return $result; // Middleware blocked the request
                    }
                }

                // Run prefix-matched middleware (path-boundary-aware)
                foreach ($this->routeMiddleware as $rm) {
                    $pfx = $rm['prefix'];
                    if ($uri === $pfx || str_starts_with($uri, $pfx . '/')) {
                        $result = call_user_func($rm['middleware'], $uri, $method);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }

                // Run route-specific middleware
                foreach ($route['middleware'] as $mw) {
                    $result = call_user_func($mw, $uri, $method);
                    if ($result !== null) {
                        return $result;
                    }
                }

                // Instantiate controller and call action
                [$controllerClass, $actionMethod] = $route['handler'];
                $controller = new $controllerClass();
                return call_user_func_array([$controller, $actionMethod], array_values($params));
            }
        }

        return $this->notFound();
    }

    // ── Internal Helpers ──────────────────────────────────────────

    /**
     * Register a route internally.
     */
    private function addRoute(string $method, string $pattern, array $handler, array $middleware): void
    {
        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware'  => $middleware,
        ];
    }

    /**
     * Attempt to match a URI against a route pattern.
     * Returns an associative array of named parameters on success, or false.
     *
     * Pattern: /events/{slug}
     * URI:     /events/spring-fieldtrip-2026
     * Result:  ['slug' => 'spring-fieldtrip-2026']
     */
    private function match(string $pattern, string $uri): array|false
    {
        // Exact match (no parameters)
        if ($pattern === $uri) {
            return [];
        }

        // Convert {param} placeholders to named regex groups
        // {id}    matches digits only
        // {name*} matches multiple path segments (slashes allowed) — use for catch-alls
        // others  match a single segment: word chars + hyphens
        $regex = preg_replace_callback(
            '/\{(\w+)(\*)?\}/',
            function ($matches) {
                $name     = $matches[1];
                $wildcard = ($matches[2] ?? '') === '*';
                if ($wildcard) {
                    return '(?P<' . $name . '>[a-zA-Z0-9/_-]+)';
                }
                if ($name === 'id') {
                    return '(?P<' . $name . '>\d+)';
                }
                return '(?P<' . $name . '>[a-zA-Z0-9_.+%-]+)';
            },
            $pattern
        );

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Extract only named groups (not numeric keys)
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return false;
    }

    /**
     * Send a 404 Not Found response.
     */
    private function notFound(): never
    {
        http_response_code(404);
        $template = new Template();
        echo $template->render('errors/404');
        exit;
    }

    // ── URL Generation ────────────────────────────────────────────

    /**
     * Generate a URL for a given route pattern with parameters filled in.
     * Example: url('/events/{slug}', ['slug' => 'spring-2026']) => '/events/spring-2026'
     */
    public static function url(string $pattern, array $params = []): string
    {
        $url = $pattern;
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', rawurlencode($value), $url);
        }
        return $url;
    }
}
