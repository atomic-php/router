# Atomic Router

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)](tests/)
[![CI](https://github.com/atomic-php/router/actions/workflows/ci.yml/badge.svg)](https://github.com/atomic-php/router/actions)
[![Codecov](https://codecov.io/gh/atomic-php/router/branch/main/graph/badge.svg)](https://codecov.io/gh/atomic-php/router)
[![Packagist](https://img.shields.io/packagist/v/atomic/router)](https://packagist.org/packages/atomic/router)

A blazingly fast, zero‑bloat PHP router designed for high‑throughput applications. Built with modern PHP features and designed to integrate cleanly with PSR‑7/PSR‑15 stacks and frameworks. Follows the same compile‑time optimization philosophy as Atomic HTTP Kernel.

## Goals

- Ultra‑fast route matching with minimal overhead
- Clean, simple API for defining routes
- PSR‑7/PSR‑15 compatible handlers and middleware
- Framework‑agnostic; drop into any PSR stack

## Features

- PSR‑7/PSR‑15 compatible routing
- Compile‑time optimized dispatcher (cached until routes change)
- Static and parameterized paths (`/users/{id}` or `{id:\d+}`)
- 404/405 handling (throw exceptions by default; optional handlers)
- Handler delegation via `RequestHandlerInterface` or callable
- Type‑safe: strict types and modern PHP 8.4 features

## PSR Compliance

- PSR‑7: consumes `ServerRequestInterface`, returns `ResponseInterface`
- PSR‑15: all route handlers are `RequestHandlerInterface` (callables are wrapped)
- PSR‑standards friendly: unopinionated and framework‑agnostic

## Installation

```bash
composer require atomic/router
```

Requirements:

- PHP 8.4 or higher
- PSR‑7 HTTP Message implementation
- PSR‑15 HTTP Server Request Handler interfaces

## Quick Start

```php
<?php

use Atomic\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$router = new Router();

$router->add('GET', '/hello', new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, [], 'Hello, Router!');
    }
});

// Later, dispatch a PSR-7 request
$response = $router->dispatch($request);
```

### Parameterized routes

```php
$router->add('GET', '/users/{id:\\d+}', function (ServerRequestInterface $req): ResponseInterface {
    $params = $req->getAttribute(Router::ATTR_PARAMS, []);
    return new Response(200, [], 'user:'.$params['id']);
});
```

### Multiple methods

```php
$router->add(['GET','POST'], '/contact', $handler);
// or pipe separated
$router->add('GET|POST', '/contact', $handler);
```

### 404/405 handling

 - Default: throws `Atomic\Router\Exceptions\RouteNotFoundException` or `MethodNotAllowedException`.
- Custom handlers:

```php
$router->setNotFoundHandler($notFoundHandler);
$router->setMethodNotAllowedHandler($methodNotAllowedHandler);
```

### Compose with Atomic HTTP Kernel

```php
use Atomic\Http\Kernel as HttpKernel;
use Atomic\Http\MiddlewareStack;

$router = new Router();
$router->add('GET', '/health', fn ($r) => new Response(200, [], 'ok'));

$stack = new MiddlewareStack();
$kernel = new HttpKernel($router->compile(), $stack);

$response = $kernel->handle($request);
```

Install the kernel package first:

```bash
composer require atomic/http-kernel
```

### Route‑Aware Middleware (match before dispatch)

Sometimes middleware needs access to route params before dispatch (e.g., authorization based on `{id}`). You can model this as two middleware:

- `RouterMatchMiddleware` — matches the request and injects route params under `Router::ATTR_PARAMS` and the matched handler under `Router::ATTR_HANDLER`.
- `RouteDispatchMiddleware` — dispatches the matched handler as the end of the pipeline.

Example with `Atomic\Http\Kernel`:

```php
use Atomic\Http\Kernel as HttpKernel;
use Atomic\Http\MiddlewareStack;
use Atomic\Router\Middleware\RouterMatchMiddleware;
use Atomic\Router\Middleware\RouteDispatchMiddleware;

$router = new Router();
$router->add('GET', '/users/{id:\\d+}', $userShowHandler);

$stack = new MiddlewareStack();

// 1) Match and inject params + matched handler (no dispatch yet)
$stack->add(new RouterMatchMiddleware($router));

// 2) Route‑aware middleware can use params
$stack->add(new class implements \Psr\Http\Server\MiddlewareInterface {
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
    {
        $params = $request->getAttribute(Router::ATTR_PARAMS, []);
        // e.g., authorization based on $params['id']
        return $handler->handle($request);
    }
});

// 3) Dispatch the matched route and return the response
$stack->add(new RouteDispatchMiddleware());

// Kernel’s final handler won’t be reached; RouteDispatchMiddleware ends the pipeline
$kernel = new HttpKernel(new class implements \Psr\Http\Server\RequestHandlerInterface {
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    { return new \Nyholm\Psr7\Response(500); }
}, $stack);

$response = $kernel->handle($request);
```

## API Reference

### Router

```php
final class Router
{
    public function add(string|array $method, string $path, RequestHandlerInterface|callable $handler): void;
    public function compile(): RequestHandlerInterface; // optimized dispatcher
    public function dispatch(ServerRequestInterface $request): ResponseInterface;
}
```

### Middleware

- `Atomic\Router\Middleware\RouterMatchMiddleware` — matches request, injects params and matched handler as request attributes.
- `Atomic\Router\Middleware\RouteDispatchMiddleware` — dispatches the matched handler and returns the response.

Constants

- `Router::ATTR_PARAMS` — request attribute key where extracted route parameters are injected
- `Router::ATTR_HANDLER` — request attribute key where the matched handler is injected by RouterMatchMiddleware

Exceptions

- `Atomic\Router\Exceptions\RouteNotFoundException` — no matching route (404)
- `Atomic\Router\Exceptions\MethodNotAllowedException` — static path matched under different methods (405)

## Architecture

- Compile‑time
  - Build per‑method static maps for O(1) lookups
  - Precompile parameterized patterns to anchored regex with named groups
  - Wrap callables into PSR‑15 once; cache compiled dispatcher
- Runtime
  - Try static map by `method + path`
  - Scan dynamic list (per method) and match first regex; inject named params under `ATTR_PARAMS`
  - Detect 405 for static paths only (fast path), otherwise 404

Notes

- Dynamic 405 detection is intentionally not cross‑scanned across methods to keep constant‑time dispatch in common paths.
- Callables are wrapped once and reused; write stateless handlers for maximal safety and performance.

## Design Principles

- Performance first; avoid unnecessary allocations and conditionals
- Clear, predictable matching rules
- Explicit APIs over magic

## Testing

```bash
composer test
```

All tests live under `tests/` and cover static + dynamic matching, 404/405 behavior, and compilation caching.

## Code Quality

```bash
composer psalm
composer cs-check
composer cs-fix
composer qa
```

Psalm and PHP‑CS‑Fixer are configured; CI should run these along with PHPUnit on PHP 8.4.

## Benchmarking

```bash
composer benchmark
```

Example results (vary by machine):

```
Router Benchmark:
benchStaticExactMatch         : ~600,000 ops/sec
benchDynamicMatch             : ~400,000 ops/sec
benchNotFound                 : ~100,000 ops/sec
benchCompileRoutes            :  ~50,000 ops/sec
```

## Contributing

See CONTRIBUTING.md for guidelines.

## Changelog

See CHANGELOG.md for release notes and version history.

## License

MIT License — see LICENSE.

---

Built by Thavarshan for high‑performance PHP applications

> "An idiot admires complexity, a genius admires simplicity" - Terry A. Davis, Creator of Temple OS
