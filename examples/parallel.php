<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

function sleep_5(int $sleep = 5): string
{
    echo "sleep {$sleep}\n";
    run(['sleep', $sleep]);

    echo "re sleep {$sleep}\n";
    run(['sleep', $sleep]);

    return 'foo';
}

function sleep_7(int $sleep = 7): string
{
    echo "sleep {$sleep}\n";
    run(['sleep', $sleep]);

    return 'bar';
}

function sleep_10(int $sleep = 10): string
{
    echo "sleep {$sleep}\n";
    run(['sleep', $sleep]);

    return "sleep {$sleep}";
}

/**
 * @return string[]
 */
function embed_sleep(int $sleep5 = 5, int $sleep7 = 7): array
{
    [$foo, $bar] = parallel(fn () => sleep_5($sleep5), fn () => sleep_7($sleep7));

    return [$foo, $bar];
}

#[AsTask(description: 'Sleeps for 5, 7, and 10 seconds in parallel')]
function sleep(int $sleep5 = 5, int $sleep7 = 7, int $sleep10 = 10): void
{
    $start = microtime(true);
    [[$foo, $bar], $sleep10] = parallel(fn () => embed_sleep($sleep5, $sleep7), fn () => sleep_10($sleep10));
    $end = microtime(true);

    $duration = (int) ($end - $start);
    echo "Foo: {$foo}\n";
    echo "Bar: {$bar}\n";
    echo "Sleep 10: {$sleep10}\n";
    echo "Duration: {$duration}\n";
}

#[AsTask(description: 'Sleep and throw an exception')]
function exception(): void
{
    parallel(
        fn () => run('exit 1'),
        fn () => run('sleep 1; echo "I am executed"'),
        fn () => throw new \RuntimeException('This is an exception'),
    );
}
