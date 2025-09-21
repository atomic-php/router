<?php

declare(strict_types=1);

namespace Benchmarks;

use Atomic\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouterBenchmark implements BenchmarkInterface
{
    protected Router $router;

    protected RequestHandlerInterface $compiled;

    protected ServerRequestInterface $reqStatic;

    protected ServerRequestInterface $reqDynamic;

    protected ServerRequestInterface $reqNotFound;

    public function setUp(): void
    {
        $response = $this->createMockResponse();
        $handler = new class($response) implements RequestHandlerInterface
        {
            public function __construct(protected ResponseInterface $r) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->r;
            }
        };

        $this->router = new Router;

        // Add several static routes
        for ($i = 1; $i <= 10; $i++) {
            $this->router->add('GET', '/static-'.$i, $handler);
        }

        // Add a dynamic route
        $this->router->add('GET', '/users/{id:\\d+}', $handler);

        $this->compiled = $this->router->compile();

        $this->reqStatic = $this->createRequest('GET', '/static-5');
        $this->reqDynamic = $this->createRequest('GET', '/users/123');
        $this->reqNotFound = $this->createRequest('GET', '/missing');
    }

    public function tearDown(): void
    {
        // no-op
    }

    public function benchStaticExactMatch(): void
    {
        $this->compiled->handle($this->reqStatic);
    }

    public function benchDynamicMatch(): void
    {
        $this->compiled->handle($this->reqDynamic);
    }

    public function benchNotFound(): void
    {
        try {
            $this->compiled->handle($this->reqNotFound);
        } catch (\Throwable) {
        }
    }

    public function benchCompileRoutes(): void
    {
        $r = new Router;
        $response = $this->createMockResponse();
        $h = new class($response) implements RequestHandlerInterface
        {
            public function __construct(protected ResponseInterface $r) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->r;
            }
        };

        for ($i = 1; $i <= 10; $i++) {
            $r->add('GET', '/s-'.$i, $h);
        }
        $r->add('GET', '/u/{id}', $h);
        $r->compile();
    }

    protected function createRequest(string $method, string $path): ServerRequestInterface
    {
        return new class($method, $path) implements ServerRequestInterface
        {
            public function __construct(protected string $m, protected string $p) {}

            public function getMethod(): string
            {
                return $this->m;
            }

            public function getUri(): UriInterface
            {
                return new class($this->p) implements UriInterface
                {
                    public function __construct(protected string $p) {}

                    public function getPath(): string
                    {
                        return $this->p;
                    }

                    public function getScheme(): string
                    {
                        return '';
                    }

                    public function getAuthority(): string
                    {
                        return '';
                    }

                    public function getUserInfo(): string
                    {
                        return '';
                    }

                    public function getHost(): string
                    {
                        return '';
                    }

                    public function getPort(): ?int
                    {
                        return null;
                    }

                    public function getQuery(): string
                    {
                        return '';
                    }

                    public function getFragment(): string
                    {
                        return '';
                    }

                    public function withScheme(string $scheme): static
                    {
                        return $this;
                    }

                    public function withUserInfo(string $user, ?string $password = null): static
                    {
                        return $this;
                    }

                    public function withHost(string $host): static
                    {
                        return $this;
                    }

                    public function withPort(?int $port): static
                    {
                        return $this;
                    }

                    public function withPath(string $path): static
                    {
                        return $this;
                    }

                    public function withQuery(string $query): static
                    {
                        return $this;
                    }

                    public function withFragment(string $fragment): static
                    {
                        return $this;
                    }

                    public function __toString(): string
                    {
                        return $this->p;
                    }
                };
            }

            public function getProtocolVersion(): string
            {
                return '1.1';
            }

            public function withProtocolVersion(string $version): static
            {
                return $this;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function hasHeader(string $name): bool
            {
                return false;
            }

            public function getHeader(string $name): array
            {
                return [];
            }

            public function getHeaderLine(string $name): string
            {
                return '';
            }

            public function withHeader(string $name, $value): static
            {
                return $this;
            }

            public function withAddedHeader(string $name, $value): static
            {
                return $this;
            }

            public function withoutHeader(string $name): static
            {
                return $this;
            }

            public function getBody(): \Psr\Http\Message\StreamInterface
            {
                throw new \RuntimeException('n/a');
            }

            public function withBody(\Psr\Http\Message\StreamInterface $body): static
            {
                return $this;
            }

            public function getRequestTarget(): string
            {
                return '/';
            }

            public function withRequestTarget(string $requestTarget): static
            {
                return $this;
            }

            public function withUri(UriInterface $uri, bool $preserveHost = false): static
            {
                return $this;
            }

            public function withMethod(string $method): static
            {
                return $this;
            }

            public function getServerParams(): array
            {
                return [];
            }

            public function getCookieParams(): array
            {
                return [];
            }

            public function withCookieParams(array $cookies): static
            {
                return $this;
            }

            public function getQueryParams(): array
            {
                return [];
            }

            public function withQueryParams(array $query): static
            {
                return $this;
            }

            public function getUploadedFiles(): array
            {
                return [];
            }

            public function withUploadedFiles(array $uploadedFiles): static
            {
                return $this;
            }

            public function getParsedBody()
            {
                return null;
            }

            public function withParsedBody($data): static
            {
                return $this;
            }

            public function getAttributes(): array
            {
                return [];
            }

            public function getAttribute(string $name, $default = null)
            {
                return $default;
            }

            public function withAttribute(string $name, $value): static
            {
                return $this;
            }

            public function withoutAttribute(string $name): static
            {
                return $this;
            }
        };
    }

    protected function createMockResponse(): ResponseInterface
    {
        return new class implements ResponseInterface
        {
            public function getProtocolVersion(): string
            {
                return '1.1';
            }

            public function withProtocolVersion(string $version): static
            {
                return $this;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function hasHeader(string $name): bool
            {
                return false;
            }

            public function getHeader(string $name): array
            {
                return [];
            }

            public function getHeaderLine(string $name): string
            {
                return '';
            }

            public function withHeader(string $name, $value): static
            {
                return $this;
            }

            public function withAddedHeader(string $name, $value): static
            {
                return $this;
            }

            public function withoutHeader(string $name): static
            {
                return $this;
            }

            public function getBody(): \Psr\Http\Message\StreamInterface
            {
                throw new \RuntimeException('n/a');
            }

            public function withBody(\Psr\Http\Message\StreamInterface $body): static
            {
                return $this;
            }

            public function getStatusCode(): int
            {
                return 200;
            }

            public function withStatus(int $code, string $reasonPhrase = ''): static
            {
                return $this;
            }

            public function getReasonPhrase(): string
            {
                return 'OK';
            }
        };
    }
}
