<?php

declare(strict_types=1);

namespace Atomic\Router\Support;

use Atomic\Router\Exceptions\MethodNotAllowedException;
use Atomic\Router\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lightweight read-only matcher for use in middleware pipelines.
 */
final readonly class RouterMatcher
{
    /**
     * @param  array<string, array<string, RequestHandlerInterface>>  $staticMap
     * @param  array<string, list{0:non-empty-string,1:list<string>,2:RequestHandlerInterface}[]>  $dynamicList
     * @param  array<string, array<string, true>>  $staticPathMethods
     */
    public function __construct(
        protected array $staticMap,
        protected array $dynamicList,
        protected array $staticPathMethods,
        protected string $paramsAttribute,
    ) {
    }

    /**
     * Match request and return tuple [handler, requestWithParams].
     *
     * @psalm-suppress PossiblyUnusedMethod Used by RouterMatchMiddleware in match-before-dispatch pipelines
     * @return array{0:RequestHandlerInterface,1:ServerRequestInterface}
     */
    public function match(ServerRequestInterface $request): array
    {
        $method = strtoupper($request->getMethod());
        $rawPath = $request->getUri()->getPath();
        $parts = explode('/', $rawPath);
        foreach ($parts as $i => $part) {
            $parts[$i] = rawurldecode($part);
        }
        $path = implode('/', $parts);

        // Static fast path
        if (isset($this->staticMap[$method][$path])) {
            return [$this->staticMap[$method][$path], $request];
        }

        // Dynamic per-method regex list
        if (! empty($this->dynamicList[$method])) {
            foreach ($this->dynamicList[$method] as [$regex, $varNames, $handler]) {
                if (preg_match($regex, $path, $m)) {
                    $params = [];
                    foreach ($varNames as $name) {
                        $params[$name] = $m[$name] ?? null;
                    }
                    $req = $request->withAttribute($this->paramsAttribute, $params);

                    return [$handler, $req];
                }
            }
        }

        if (isset($this->staticPathMethods[$path]) && ! isset($this->staticPathMethods[$path][$method])) {
            throw new MethodNotAllowedException($path, array_keys($this->staticPathMethods[$path]));
        }

        throw new RouteNotFoundException('No route matched for '.$method.' '.$path);
    }
}
