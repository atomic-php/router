<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterCachingTest extends TestCase
{
    public function test_setting_not_found_handler_invalidates_compiled_dispatcher(): void
    {
        $router = new Router();
        $router->add('GET', '/a', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $c1 = $router->compile();
        $router->setNotFoundHandler(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(404);
            }
        });
        $c2 = $router->compile();
        self::assertNotSame($c1, $c2);
    }

    public function test_setting_method_not_allowed_handler_invalidates_compiled_dispatcher(): void
    {
        $router = new Router();
        $router->add('GET', '/a', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $c1 = $router->compile();
        $router->setMethodNotAllowedHandler(new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(405);
            }
        });
        $c2 = $router->compile();
        self::assertNotSame($c1, $c2);
    }
}
