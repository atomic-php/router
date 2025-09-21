<?php

declare(strict_types=1);

namespace Atomic\Router\Support;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wraps a callable(ServerRequestInterface): ResponseInterface into a PSR-15 handler.
 */
final readonly class CallableHandler implements RequestHandlerInterface
{
    public function __construct(protected Closure $callable)
    {
    }

    /**
     * @param  callable(ServerRequestInterface):ResponseInterface  $callable
     */
    public static function fromCallable(callable $callable): self
    {
        return new self(Closure::fromCallable($callable));
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = ($this->callable)($request);
        if (! $response instanceof ResponseInterface) {
            throw new \RuntimeException('Callable handler must return a ResponseInterface');
        }

        return $response;
    }
}
