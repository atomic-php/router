<?php

declare(strict_types=1);

namespace Atomic\Router;

use Atomic\Router\Support\CallableHandler;
use Atomic\Router\Support\CompiledRouter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * High-performance, framework-agnostic router with compile-time optimization.
 *
 * - Collect routes via add()
 * - Compile into an optimized matcher once (lazy on first dispatch)
 * - Dispatch quickly with precompiled static maps and regex lists
 *
 * Handlers may be RequestHandlerInterface or callables(ServerRequestInterface): ResponseInterface.
 * Callables are wrapped once at compile time to avoid per-request checks.
 *
 * @psalm-api
 */
final class Router
{
    /** Attribute name for extracted route parameters */
    public const ATTR_PARAMS = 'atomic.router.params';

    /** Attribute name for the matched route handler (set by RouterMatchMiddleware) */
    public const ATTR_HANDLER = 'atomic.router.handler';

    /** @var list<Route> */
    protected array $routes = [];

    /** Cached compiled router handler */
    protected ?RequestHandlerInterface $compiled = null;

    /** Cached compiled matcher */
    protected ?Support\RouterMatcher $compiledMatcher = null;

    /** Optional handler overrides for 404/405 if the app prefers handling instead of throwing */
    protected ?RequestHandlerInterface $notFoundHandler = null;

    protected ?RequestHandlerInterface $methodNotAllowedHandler = null;

    /**
     * Register a route.
     *
     * @param  string|array<int,string>  $method  One or more HTTP methods (e.g. 'GET' or ['GET','POST'])
     * @param  string  $path  Route path pattern. Supports static paths and placeholders like /users/{id} or {id:\\d+}
     * @param  RequestHandlerInterface|callable  $handler  Handler to execute on match
     */
    public function add(string|array $method, string $path, RequestHandlerInterface|callable $handler): void
    {
        $methods = is_array($method)
            ? array_map(static fn ($m): string => strtoupper($m), $method)
            : array_map(static fn ($m): string => strtoupper($m), preg_split('/\s*\|\s*/', $method) ?: []);

        // Normalize to list<string> and drop empties
        /** @var list<string> $methods */
        $methods = array_values(array_filter($methods, static fn ($m): bool => $m !== ''));

        if (empty($methods)) {
            throw new \InvalidArgumentException('At least one HTTP method must be provided');
        }

        $wrapped = $handler instanceof RequestHandlerInterface ? $handler : CallableHandler::fromCallable($handler);
        $this->routes[] = new Route($methods, $path, $wrapped);

        // Invalidate compiled cache when routes change
        $this->compiled = null;
        $this->compiledMatcher = null;
    }

    /**
     * Provide a custom 404 handler to return a response instead of throwing RouteNotFoundException.
     */
    public function setNotFoundHandler(?RequestHandlerInterface $handler): void
    {
        $this->notFoundHandler = $handler;
        $this->compiled = null;
        $this->compiledMatcher = null;
    }

    /**
     * Provide a custom 405 handler to return a response instead of throwing MethodNotAllowedException.
     */
    public function setMethodNotAllowedHandler(?RequestHandlerInterface $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
        $this->compiled = null;
        $this->compiledMatcher = null;
    }

    /**
     * Compile route table into an optimized dispatcher. Idempotent and cached until routes change.
     */
    public function compile(): RequestHandlerInterface
    {
        if ($this->compiled) {
            return $this->compiled;
        }

        [$static, $dynamic, $allStaticByPath] = $this->buildTables();

        return $this->compiled = new CompiledRouter(
            staticMap: $static,
            dynamicList: $dynamic,
            staticPathMethods: $allStaticByPath,
            paramsAttribute: self::ATTR_PARAMS,
            notFoundHandler: $this->notFoundHandler,
            methodNotAllowedHandler: $this->methodNotAllowedHandler,
        );
    }

    /**
     * Compile and return a lightweight matcher for route-aware middleware pipelines.
     */
    public function compileMatcher(): Support\RouterMatcher
    {
        if ($this->compiledMatcher) {
            return $this->compiledMatcher;
        }

        [$static, $dynamic, $allStaticByPath] = $this->buildTables();

        return $this->compiledMatcher = new Support\RouterMatcher(
            staticMap: $static,
            dynamicList: $dynamic,
            staticPathMethods: $allStaticByPath,
            paramsAttribute: self::ATTR_PARAMS,
        );
    }

    /**
     * Dispatch the request using a compiled router, throwing on 404/405 by default.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->compile()->handle($request);
    }

    /**
     * Compile a path pattern like "/users/{id:\\d+}" to a regex and ordered var names array.
     *
     * @return array{0:non-empty-string,1:list<string>} [regex, varNames]
     */
    protected static function compilePattern(string $pattern): array
    {
        $varNames = [];

        $regex = preg_replace_callback(
            pattern: '/\{([a-zA-Z_][a-zA-Z0-9_]*) (?:: ([^}]+))?\}/x',
            callback: static function (array $m) use (&$varNames): string {
                $varNames[] = $m[1];
                $sub = isset($m[2]) ? trim($m[2]) : '[^/]+';

                return '(?P<'.$m[1].'>'.$sub.')';
            },
            subject: $pattern,
        );

        // Anchor the pattern for full-match performance and correctness
        $regex = '#^'.$regex.'$#';

        return [$regex, $varNames];
    }

    /**
     * Build internal static and dynamic route tables shared by dispatcher and matcher.
     *
     * @return array{0: array<string,array<string,RequestHandlerInterface>>, 1: array<string, list{0:non-empty-string,1:list<string>,2:RequestHandlerInterface}[]>, 2: array<string,array<string,true>>}
     */
    protected function buildTables(): array
    {
        $static = [];
        $dynamic = [];
        $allStaticByPath = [];

        foreach ($this->routes as $route) {
            $handler = $route->handler;

            foreach ($route->methods as $method) {
                if (! isset($allStaticByPath[$route->path])) {
                    $allStaticByPath[$route->path] = [];
                }
                $allStaticByPath[$route->path][$method] = true;

                if (! isset($static[$method])) {
                    $static[$method] = [];
                }
                if (! isset($dynamic[$method])) {
                    $dynamic[$method] = [];
                }

                if (! str_contains($route->path, '{')) {
                    $static[$method][$route->path] = $handler;
                } else {
                    [$regex, $varNames] = self::compilePattern($route->path);
                    $dynamic[$method][] = [$regex, $varNames, $handler];
                }
            }
        }

        return [$static, $dynamic, $allStaticByPath];
    }
}
