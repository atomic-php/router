<?php

declare(strict_types=1);

namespace Atomic\Router\Support;

use Atomic\Router\Exceptions\MethodNotAllowedException;
use Atomic\Router\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Optimized, read-only router compiled from route definitions.
 *
 * Matching algorithm:
 * - Static map lookup by method+path
 * - Dynamic regex list per method (first match wins)
 *
 * 405 detection: for exact static paths only (fast path). Dynamic 405s are intentionally
 * not computed to avoid cross-method regex scans.
 */
final readonly class CompiledRouter implements RequestHandlerInterface
{
    /**
     * @param  array<string, array<string, RequestHandlerInterface>>  $staticMap  method => [path => handler]
     * @param  array<string, list{0:non-empty-string,1:list<string>,2:RequestHandlerInterface}[]>  $dynamicList  method => list of [regex, varNames, handler]
     * @param  array<string, array<string, true>>  $staticPathMethods  path => [method => true] for 405 detection
     */
    public function __construct(
        protected array $staticMap,
        protected array $dynamicList,
        protected array $staticPathMethods,
        protected string $paramsAttribute,
        protected ?RequestHandlerInterface $notFoundHandler = null,
        protected ?RequestHandlerInterface $methodNotAllowedHandler = null,
    ) {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $rawPath = $request->getUri()->getPath();
        // Decode percent-encoded octets per path segment to retain structure and support UTF-8
        $parts = explode('/', $rawPath);
        foreach ($parts as $i => $part) {
            $parts[$i] = rawurldecode($part);
        }
        $path = implode('/', $parts);

        // 1) Static fast path
        if (isset($this->staticMap[$method][$path])) {
            return $this->staticMap[$method][$path]->handle($request);
        }

        // 2) Dynamic regex list per method
        if (! empty($this->dynamicList[$method])) {
            foreach ($this->dynamicList[$method] as [$regex, $varNames, $handler]) {
                if (preg_match($regex, $path, $m)) {
                    // Extract ordered vars
                    $params = [];
                    foreach ($varNames as $name) {
                        $params[$name] = $m[$name] ?? null;
                    }
                    $request = $request->withAttribute($this->paramsAttribute, $params);

                    return $handler->handle($request);
                }
            }
        }

        // 3) 405 detection for static paths (other methods exist for same exact path)
        if (isset($this->staticPathMethods[$path]) && ! isset($this->staticPathMethods[$path][$method])) {
            $allowed = array_keys($this->staticPathMethods[$path]);
            if ($this->methodNotAllowedHandler) {
                // Let app handle 405
                return $this->methodNotAllowedHandler->handle($request);
            }

            throw new MethodNotAllowedException($path, $allowed);
        }

        // 4) Not found
        if ($this->notFoundHandler) {
            return $this->notFoundHandler->handle($request);
        }

        throw new RouteNotFoundException('No route matched for '.$method.' '.$path);
    }
}
