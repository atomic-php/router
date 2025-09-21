<?php

declare(strict_types=1);

namespace Tests\Integration;

// Lightweight PSR-4 autoloader for Atomic\\Http from sibling package
spl_autoload_register(static function (string $class): void {
    $prefix = 'Atomic\\Http\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../../../http-kernel/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

use Atomic\Http\Kernel as HttpKernel;
use Atomic\Http\MiddlewareStack;
use Atomic\Http\PerformanceKernel;
use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class KernelRouterCompositionTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure Atomic\\Http classes are available; if not, skip tests in standalone router context
        if (!class_exists(HttpKernel::class)) {
            $this->markTestSkipped('Atomic\\Http not available; skip cross-package composition test.');
        }
    }
    public function test_kernel_handles_router_as_final_handler(): void
    {
        $router = new Router();
        $router->add('GET', '/hello', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['X-Route' => 'yes'], 'hi');
            }
        });

        $kernel = new HttpKernel($router->compile(), new MiddlewareStack());

        $res = $kernel->handle(new ServerRequest('GET', '/hello'));
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('yes', $res->getHeaderLine('X-Route'));
        self::assertSame('hi', (string) $res->getBody());
    }

    public function test_kernel_middleware_wraps_router_pipeline(): void
    {
        $router = new Router();
        $router->add('GET', '/ping', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['X-Core' => 'router'], 'pong');
            }
        });

        $stack = new MiddlewareStack();

        // Add simple header-modifying middleware around the router
        $stack->add(new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $res = $handler->handle($request);
                return $res->withHeader('X-MW', '1');
            }
        });

        $kernel = new HttpKernel($router->compile(), $stack);
        $perf = new PerformanceKernel($kernel); // ensure decorators compose

        $res = $perf->handle(new ServerRequest('GET', '/ping'));
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('router', $res->getHeaderLine('X-Core'));
        self::assertSame('1', $res->getHeaderLine('X-MW'));
        self::assertSame('pong', (string) $res->getBody());
    }
}
