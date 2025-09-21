<?php

declare(strict_types=1);

namespace Atomic\Router\Middleware;

use Atomic\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dispatches the previously-matched route handler stored by RouterMatchMiddleware.
 *
 * @psalm-suppress UnusedClass This middleware may be consumed by applications and not referenced within the library itself.
 */
final class RouteDispatchMiddleware implements MiddlewareInterface
{
    #[\Override]
    /**
     * @psalm-suppress UnusedParam $handler is intentionally unused; dispatch ends the pipeline.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $matched = $request->getAttribute(Router::ATTR_HANDLER);

        if (! $matched instanceof RequestHandlerInterface) {
            throw new \RuntimeException('No matched route handler available for dispatch');
        }

        return $matched->handle($request);
    }
}
