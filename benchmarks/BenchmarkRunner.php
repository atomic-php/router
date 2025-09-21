<?php

declare(strict_types=1);

namespace Benchmarks;

use ReflectionClass;
use ReflectionMethod;

class BenchmarkRunner
{
    /** @var BenchmarkInterface[] */
    protected array $benchmarks = [];

    public function register(BenchmarkInterface $benchmark): void
    {
        $this->benchmarks[] = $benchmark;
    }

    /**
     * @return array<string, array<string, array{iterations:int,total_time:float,time_per_op:float,ops_per_sec:float}>>
     */
    public function runAll(): array
    {
        $results = [];

        foreach ($this->benchmarks as $benchmark) {
            $name = $this->getBenchmarkName($benchmark);
            echo "Running {$name}...\n";
            $results[$name] = $this->runBenchmark($benchmark);
        }

        return $results;
    }

    /**
     * @return array<string, array{iterations:int,total_time:float,time_per_op:float,ops_per_sec:float}>
     */
    protected function runBenchmark(BenchmarkInterface $benchmark): array
    {
        $ref = new ReflectionClass($benchmark);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $results = [];

        if (method_exists($benchmark, 'setUp')) {
            $benchmark->setUp();
        }

        foreach ($methods as $method) {
            $name = $method->getName();
            if (! str_starts_with($name, 'bench') || in_array($name, ['setUp', 'tearDown'], true)) {
                continue;
            }
            echo "  {$name}... ";
            $results[$name] = $this->measureMethod($benchmark, $name);
            echo sprintf("%8.2f ops/sec\n", $results[$name]['ops_per_sec']);
        }

        if (method_exists($benchmark, 'tearDown')) {
            $benchmark->tearDown();
        }

        return $results;
    }

    /**
     * @return array{iterations:int,total_time:float,time_per_op:float,ops_per_sec:float}
     */
    protected function measureMethod(BenchmarkInterface $benchmark, string $method): array
    {
        $iterations = 0;
        $totalTime = 0.0;
        $targetTime = 1.0;
        $minIterations = 100;

        // Warm-up
        $benchmark->$method();

        $start = hrtime(true);
        do {
            $benchmark->$method();
            $iterations++;
            $totalTime = (hrtime(true) - $start) / 1_000_000_000;
        } while ($totalTime < $targetTime || $iterations < $minIterations);

        $timePerOp = $totalTime / $iterations;

        return [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'time_per_op' => $timePerOp,
            'ops_per_sec' => $timePerOp > 0 ? 1.0 / $timePerOp : 0.0,
        ];
    }

    protected function getBenchmarkName(BenchmarkInterface $benchmark): string
    {
        $class = get_class($benchmark);
        $short = substr($class, strrpos($class, '\\') + 1);

        return preg_replace('/([A-Z])/', ' $1', $short);
    }
}
