<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Exceptions\MethodNotAllowedException;
use Atomic\Router\Exceptions\RouteNotFoundException;
use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterBehaviorTest extends TestCase
{
    public function test_static_route_precedence_over_dynamic(): void
    {
        $router = new Router();

        // Dynamic route
        $router->add('GET', '/users/{id}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'dynamic');
            }
        });

        // Static route with same prefix
        $router->add('GET', '/users/new', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'static');
            }
        });

        $res = $router->dispatch(new ServerRequest('GET', '/users/new'));
        self::assertSame('static', (string) $res->getBody());
    }

    public function test_dynamic_route_does_not_trigger_405_detection(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = new Router();
        $router->add('GET', '/u/{id}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        // POST for dynamic route should fall through to 404, not 405
        $router->dispatch(new ServerRequest('POST', '/u/1'));
    }

    public function test_methods_are_case_insensitive(): void
    {
        $router = new Router();
        $router->add('get', '/ping', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $res = $router->dispatch(new ServerRequest('GET', '/ping'));
        self::assertSame(200, $res->getStatusCode());
    }

    public function test_pipe_separated_methods_with_spaces(): void
    {
        $router = new Router();
        $router->add('GET | POST', '/contact', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        self::assertSame(200, $router->dispatch(new ServerRequest('GET', '/contact'))->getStatusCode());
        self::assertSame(200, $router->dispatch(new ServerRequest('POST', '/contact'))->getStatusCode());
    }

    public function test_trailing_slash_must_match_exactly(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = new Router();
        $router->add('GET', '/a', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        // '/a/' does not equal '/a'
        $router->dispatch(new ServerRequest('GET', '/a/'));
    }

    public function test_default_parameter_regex_accepts_non_slash(): void
    {
        $router = new Router();
        $router->add('GET', '/x/{slug}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $p = $request->getAttribute(Router::ATTR_PARAMS, []);
                return new Response(200, [], $p['slug'] ?? '');
            }
        });

        $res = $router->dispatch(new ServerRequest('GET', '/x/hello-world'));
        self::assertSame('hello-world', (string) $res->getBody());
    }

    public function test_static_405_detection_triggers_for_exact_path(): void
    {
        $this->expectException(MethodNotAllowedException::class);

        $router = new Router();
        $router->add('GET', '/items', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        // POST to exact static path should be 405
        $router->dispatch(new ServerRequest('POST', '/items'));
    }

    public function test_duplicate_static_route_overwrites_to_last_added(): void
    {
        $router = new Router();
        $router->add('GET', '/dup', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'first');
            }
        });
        $router->add('GET', '/dup', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'second');
            }
        });

        $res = $router->dispatch(new ServerRequest('GET', '/dup'));
        self::assertSame('second', (string) $res->getBody());
    }
}
