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

final class DynamicRoutesTest extends TestCase
{
    public function testMatchesParameterizedRouteAndInjectsParams(): void
    {
        $router = new Router();

        $router->add('GET', '/users/{id:\\d+}', new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $params = $request->getAttribute(Router::ATTR_PARAMS, []);
                $body = 'user:' . ($params['id'] ?? 'missing');
                return new Response(200, [], $body);
            }
        });

        $request = new ServerRequest('GET', '/users/42');
        $response = $router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('user:42', (string) $response->getBody());
    }
}
