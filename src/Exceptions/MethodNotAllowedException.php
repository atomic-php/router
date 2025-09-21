<?php

declare(strict_types=1);

namespace Atomic\Router\Exceptions;

final class MethodNotAllowedException extends \RuntimeException
{
    /**
     * @param list<string> $allowed
     */
    public function __construct(string $path, array $allowed)
    {
        parent::__construct('Method not allowed for ' . $path . '; allowed: ' . implode(',', $allowed), 405);
    }
}
