<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Sleeps for 3 and 4 seconds in parallel')]
function sleep(): void
{
    $start = microtime(true);

    parallel(
        static fn () => sleep_(3),
        static fn () => sleep_(4),
    );

    io()->writeln('');
    $duration = (int) (microtime(true) - $start);
    io()->writeln("Duration: {$duration}s");
}

function sleep_(int $sleep): string
{
    run("echo 'sleep {$sleep}s'; sleep {$sleep} ; echo 'has slept {$sleep}s'");

    return 'foo';
}
