<?php

declare(strict_types=1);

namespace Benchmarks;

interface BenchmarkInterface
{
    public function setUp(): void;

    public function tearDown(): void;
}

