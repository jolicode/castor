<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\exec;
use function Castor\parallel;

function sleep_5(int $sleep = 5)
{
    echo "sleep {$sleep}\n";
    exec(['sleep', $sleep]);

    echo "re sleep {$sleep}\n";
    exec(['sleep', $sleep]);

    return 'foo';
}

function sleep_7(int $sleep = 7)
{
    echo "sleep {$sleep}\n";
    exec(['sleep', $sleep]);

    return 'bar';
}

function sleep_10(int $sleep = 10)
{
    echo "sleep {$sleep}\n";
    exec(['sleep', $sleep]);

    return "sleep {$sleep}";
}

function embed_sleep(int $sleep5 = 5, int $sleep7 = 7)
{
    [$foo, $bar] = parallel(fn () => sleep_5($sleep5), fn () => sleep_7($sleep7));

    return [$foo, $bar];
}

#[AsTask(description: 'Sleeps for 5, 7, and 10 seconds in parallel')]
function sleep(int $sleep5 = 5, int $sleep7 = 7, int $sleep10 = 10)
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
