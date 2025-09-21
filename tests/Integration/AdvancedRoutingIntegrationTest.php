<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atomic\Router\Exceptions\MethodNotAllowedException;
use Atomic\Router\Exceptions\RouteNotFoundException;
use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdvancedRoutingIntegrationTest extends TestCase
{
    public function test_overlapping_dynamic_patterns_first_match_wins(): void
    {
        $router = new Router();

        // More specific numeric id pattern added first
        $router->add('GET', '/posts/{id:\\d+}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'by-id');
            }
        });

        // Less specific slug pattern added second
        $router->add('GET', '/posts/{slug}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'by-slug');
            }
        });

        $r1 = $router->dispatch(new ServerRequest('GET', '/posts/123'));
        self::assertSame('by-id', (string) $r1->getBody(), 'First matching route should win');

        $r2 = $router->dispatch(new ServerRequest('GET', '/posts/hello-world'));
        self::assertSame('by-slug', (string) $r2->getBody());
    }

    public function test_callable_handler_dispatch_end_to_end(): void
    {
        $router = new Router();
        $router->add('GET', '/callable', function (ServerRequestInterface $req): ResponseInterface {
            return new Response(200, [], 'ok');
        });

        $res = $router->dispatch(new ServerRequest('GET', '/callable'));
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('ok', (string) $res->getBody());
    }

    public function test_utf8_paths_and_parameters_are_supported(): void
    {
        $router = new Router();
        // Cyrillic path and slug
        $router->add('GET', '/категория/{slug}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $params = $request->getAttribute(Router::ATTR_PARAMS, []);
                return new Response(200, [], (string) ($params['slug'] ?? ''));
            }
        });

        $psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();
        // Use percent-encoded UTF-8 path to reflect typical URI encoding
        $encoded = '/%D0%BA%D0%B0%D1%82%D0%B5%D0%B3%D0%BE%D1%80%D0%B8%D1%8F/%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%80';
        $req = $psr17->createServerRequest('GET', $psr17->createUri($encoded));
        $res = $router->dispatch($req);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('пример', (string) $res->getBody());
    }

    public function test_default_404_throws_without_custom_handler(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = new Router();
        $router->dispatch(new ServerRequest('GET', '/missing'));
    }

    public function test_default_405_throws_without_custom_handler(): void
    {
        $this->expectException(MethodNotAllowedException::class);

        $router = new Router();
        $router->add('GET', '/only-get', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $router->dispatch(new ServerRequest('POST', '/only-get'));
    }
}
