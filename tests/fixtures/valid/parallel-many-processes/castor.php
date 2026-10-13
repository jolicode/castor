<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\parallel;

#[AsTask(name: 'par')]
function par(int $processes = 40): void
{
    $start = microtime(true);

    parallel(...array_fill(0, $processes, static fn () => capture('sleep 0.3')));

    echo round(microtime(true) - $start, 2) . "\n";
}

#[AsTask(name: 'nested')]
function nested(): void
{
    $start = microtime(true);

    parallel(
        static fn () => parallel(...array_fill(0, 20, static fn () => capture('sleep 0.3'))),
        static fn () => parallel(...array_fill(0, 20, static fn () => capture('sleep 0.3'))),
    );

    echo round(microtime(true) - $start, 2) . "\n";
}
