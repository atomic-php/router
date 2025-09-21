<?php

namespace Atomic\Router;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Route definition value object.
 *
 * Immutable (readonly) container describing a single route mapping:
 * - methods: list of normalized HTTP methods (e.g. GET, POST)
 * - path: static path or parameterized pattern (e.g. /users/{id:\\d+})
 * - handler: PSR-15 RequestHandlerInterface to dispatch when matched
 *
 * Notes
 * - Instances are created by Router::add() and consumed at compile time.
 * - This class is internal to the router package; consumers should use Router’s API.
 *
 * @internal
 */
final readonly class Route
{
    /**
     * @param  list<string>  $methods
     */
    public function __construct(
        public array $methods,
        public string $path,
        public RequestHandlerInterface $handler,
    ) {
    }
}
