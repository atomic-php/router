<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class RouterTest extends TestCase
{
    public function testDispatchesExactMatchToPsrHandler(): void
    {
        $router = new Router();

        $router->add('GET', '/hello', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'Hello, Router!');
            }
        });

        $request = new ServerRequest('GET', '/hello');
        $response = $router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, Router!', (string) $response->getBody());
    }

    public function testDispatchesExactMatchToCallable(): void
    {
        $router = new Router();
        $router->add('POST', '/echo', function (ServerRequestInterface $request): ResponseInterface {
            return new Response(201, [], 'Created');
        });

        $request = new ServerRequest('POST', '/echo');
        $response = $router->dispatch($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', (string) $response->getBody());
    }

    public function testThrowsWhenNoRouteMatches(): void
    {
        $this->expectException(RuntimeException::class);

        $router = new Router();
        $request = new ServerRequest('GET', '/missing');
        $router->dispatch($request);
    }
}
