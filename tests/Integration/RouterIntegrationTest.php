<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterIntegrationTest extends TestCase
{
    public function test_static_and_dynamic_routing_end_to_end(): void
    {
        $router = new Router();

        // Static route
        $router->add('GET', '/hello', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'hello');
            }
        });

        // Dynamic with regex
        $router->add('GET', '/users/{id:\\d+}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $params = $request->getAttribute(Router::ATTR_PARAMS, []);

                return new Response(200, [], 'user:'.($params['id'] ?? 'missing'));
            }
        });

        // Dispatch static
        $res1 = $router->dispatch(new ServerRequest('GET', '/hello'));
        self::assertSame(200, $res1->getStatusCode());
        self::assertSame('hello', (string) $res1->getBody());

        // Dispatch dynamic
        $res2 = $router->dispatch(new ServerRequest('GET', '/users/99'));
        self::assertSame(200, $res2->getStatusCode());
        self::assertSame('user:99', (string) $res2->getBody());
    }

    public function test_custom_404_and_405_handlers_end_to_end(): void
    {
        $router = new Router();

        // Define a static GET route
        $router->add('GET', '/items', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'items');
            }
        });

        // Custom 404
        $router->setNotFoundHandler(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(404, ['X-NotFound' => 'true'], 'nf');
            }
        });

        // Custom 405
        $router->setMethodNotAllowedHandler(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(405, ['Allow' => 'GET'], 'na');
            }
        });

        // 404
        $r404 = $router->dispatch(new ServerRequest('GET', '/missing'));
        self::assertSame(404, $r404->getStatusCode());
        self::assertSame('true', $r404->getHeaderLine('X-NotFound'));

        // 405 (path exists for GET, but not for POST)
        $r405 = $router->dispatch(new ServerRequest('POST', '/items'));
        self::assertSame(405, $r405->getStatusCode());
        // Optional: check Allow header populated by custom handler
        self::assertSame('GET', $r405->getHeaderLine('Allow'));
    }

    public function test_multiple_methods_array_and_pipe_syntax(): void
    {
        $router = new Router();

        $router->add(['GET', 'POST'], '/contact', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'contact');
            }
        });

        $router->add('PUT|PATCH', '/resource', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        });

        $r1 = $router->dispatch(new ServerRequest('GET', '/contact'));
        self::assertSame(200, $r1->getStatusCode());

        $r2 = $router->dispatch(new ServerRequest('POST', '/contact'));
        self::assertSame(200, $r2->getStatusCode());

        $r3 = $router->dispatch(new ServerRequest('PUT', '/resource'));
        self::assertSame(204, $r3->getStatusCode());

        $r4 = $router->dispatch(new ServerRequest('PATCH', '/resource'));
        self::assertSame(204, $r4->getStatusCode());
    }

    public function test_param_extraction_with_multiple_segments(): void
    {
        $router = new Router();

        $router->add('GET', '/users/{userId:\\d+}/posts/{slug}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $p = $request->getAttribute(Router::ATTR_PARAMS, []);
                $body = sprintf('%s:%s', $p['userId'] ?? 'x', $p['slug'] ?? 'y');

                return new Response(200, [], $body);
            }
        });

        $res = $router->dispatch(new ServerRequest('GET', '/users/7/posts/hello-world'));
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('7:hello-world', (string) $res->getBody());
    }
}
