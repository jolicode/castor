<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;

#[AsTask(description: 'Run a sub-process and display information about it, with capture() function')]
function capture_(): void
{
    $time = capture('date +%H:%M:%S');

    io()->writeln("Current time: {$time}");
}
