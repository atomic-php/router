<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Benchmarks\BenchmarkRunner;
use Benchmarks\RouterBenchmark;

echo "Atomic Router Benchmarks\n";
echo "=========================\n\n";

$runner = new BenchmarkRunner();
$runner->register(new RouterBenchmark());

$results = $runner->runAll();

echo "\nBenchmark Results Summary:\n";
echo "==========================\n";

foreach ($results as $name => $tests) {
    echo "\n{$name}:\n";
    echo str_repeat('-', strlen($name))."\n";
    foreach ($tests as $testName => $result) {
        echo sprintf(
            "  %-30s: %8.2f ops/sec (%6.3f ms/op) [%d iterations]\n",
            $testName,
            $result['ops_per_sec'],
            $result['time_per_op'] * 1000,
            $result['iterations']
        );
    }
}

echo "\nBenchmarks completed.\n";
