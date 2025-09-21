<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CompilationCachingTest extends TestCase
{
    public function testCompileIsCachedUntilRoutesChange(): void
    {
        $router = new Router();

        $router->add('GET', '/ping', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $compiled1 = $router->compile();
        $compiled2 = $router->compile();

        self::assertSame($compiled1, $compiled2);

        // Add a new route; compiled instance should be invalidated
        $router->add('POST', '/ping', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(201);
            }
        });

        $compiled3 = $router->compile();
        self::assertNotSame($compiled1, $compiled3);
    }
}
