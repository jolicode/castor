<?php

namespace Castor\Example;

use Castor\Attribute\Task;

use function Castor\exec;
use function Castor\parallel;

function sleep_5()
{
    echo "sleep 5\n";
    exec(['sleep', '5']);

    echo "re sleep 5\n";
    exec(['sleep', '5']);

    return 'foo';
}

function sleep_7()
{
    echo "sleep 7\n";
    exec(['sleep', '7']);

    return 'bar';
}

function sleep_10()
{
    echo "sleep 10\n";
    exec(['sleep', '10']);

    return 'sleep 10';
}

function embed_sleep()
{
    [$foo, $bar] = parallel(fn () => sleep_5(), fn () => sleep_7());

    return [$foo, $bar];
}

#[Task(description: 'A simple task that sleeps for 5 and 7 seconds in parallel')]
function sleep()
{
    $start = microtime(true);
    [[$foo, $bar], $sleep10] = parallel(fn () => embed_sleep(), fn () => sleep_10());
    $end = microtime(true);

    $duration = $end - $start;
    echo "Foo: {$foo}\n";
    echo "Bar: {$bar}\n";
    echo "Sleep 10: {$sleep10}\n";
    echo "Duration: {$duration}\n";
}
