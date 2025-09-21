<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atomic\Http\Kernel as HttpKernel;
use Atomic\Http\MiddlewareStack;
use Atomic\Router\Middleware\RouteDispatchMiddleware;
use Atomic\Router\Middleware\RouterMatchMiddleware;
use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterMiddlewarePipelineTest extends TestCase
{
    public function test_route_aware_middleware_sees_params_before_dispatch(): void
    {
        $router = new Router();
        $router->add('GET', '/users/{id:\\d+}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $stack = new MiddlewareStack();
        $stack->add(new RouterMatchMiddleware($router));

        // Route-aware middleware: inject header with id param
        $stack->add(new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $params = $request->getAttribute(Router::ATTR_PARAMS, []);
                $res = $handler->handle($request);
                return $res->withHeader('X-User-Id', (string) ($params['id'] ?? 'missing'));
            }
        });

        $stack->add(new RouteDispatchMiddleware());

        // Final handler should never be called; RouteDispatchMiddleware returns
        $final = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(500);
            }
        };

        $kernel = new HttpKernel($final, $stack);

        $res = $kernel->handle(new ServerRequest('GET', '/users/42'));
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('42', $res->getHeaderLine('X-User-Id'));
    }
}
