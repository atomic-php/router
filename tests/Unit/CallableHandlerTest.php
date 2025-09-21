<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atomic\Router\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CallableHandlerTest extends TestCase
{
    public function test_callable_must_return_response_interface(): void
    {
        $this->expectException(RuntimeException::class);

        $router = new Router();
        $router->add('GET', '/bad', function () {
            return 'not-a-response';
        });

        $router->dispatch(new ServerRequest('GET', '/bad'));
    }
}
