<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Exceptions\MethodNotAllowedException;
use Atomic\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MethodNotAllowedTest extends TestCase
{
    public function testThrows405ForStaticPathDifferentMethod(): void
    {
        $this->expectException(MethodNotAllowedException::class);

        $router = new Router();
        $router->add('GET', '/items', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });

        $request = new ServerRequest('POST', '/items');
        $router->dispatch($request);
    }
}
