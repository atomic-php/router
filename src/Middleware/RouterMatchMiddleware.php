<?php

declare(strict_types=1);

namespace Atomic\Router\Middleware;

use Atomic\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Matches the incoming request using Router and injects route params and matched handler as attributes.
 * Does not dispatch; the next middleware can access params and the final dispatch is done by RouteDispatchMiddleware.
 *
 * @psalm-suppress UnusedClass This middleware may be consumed by applications and not referenced within the library itself.
 */
final class RouterMatchMiddleware implements MiddlewareInterface
{
    public function __construct(protected Router $router)
    {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$matchedHandler, $requestWithParams] = $this->router->compileMatcher()->match($request);

        $requestWithMatch = $requestWithParams->withAttribute(Router::ATTR_HANDLER, $matchedHandler);

        return $handler->handle($requestWithMatch);
    }
}
