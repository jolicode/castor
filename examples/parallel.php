<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\parallel;
use function Castor\run;

function sleep_5(int $sleep = 5): string
{
    run("echo 'sleep {$sleep}s'; sleep {$sleep} ; echo 'has slept {$sleep}s'");
    run("echo 'sleep for another {$sleep}s'; sleep {$sleep}; echo 'has slept for another {$sleep}s'");

    return 'foo';
}

function sleep_7(int $sleep = 7): string
{
    run("echo 'sleep {$sleep}s'; sleep {$sleep}; echo 'has slept {$sleep}s'");

    return 'bar';
}

function sleep_10(int $sleep = 10): string
{
    run("echo 'sleep {$sleep}s'; sleep {$sleep}; echo 'has slept {$sleep}s'");

    return 'baz';
}

/**
 * @return string[]
 */
function embed_sleep(int $sleep5 = 5, int $sleep7 = 7): array
{
    return parallel(
        fn () => sleep_5($sleep5),
        fn () => sleep_7($sleep7)
    );
}

#[AsTask(description: 'Sleeps for 5, 7, and 10 seconds in parallel')]
function sleep(int $sleep5 = 5, int $sleep7 = 7, int $sleep10 = 10): void
{
    $start = microtime(true);

    [$baz, [$foo, $bar]] = parallel(
        // $baz is the return value of sleep_10()
        fn () => sleep_10($sleep10),

        // $foo and $bar are the return values of sleep_5() and sleep_7()
        fn () => embed_sleep($sleep5, $sleep7)
    );

    io()->writeln('');
    $duration = (int) (microtime(true) - $start);
    io()->writeln("Duration: {$duration}s");

    io()->writeln('');

    io()->writeln("\$foo = '{$foo}';");
    io()->writeln("\$bar = '{$bar}';");
    io()->writeln("\$baz = '{$baz}';");
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
