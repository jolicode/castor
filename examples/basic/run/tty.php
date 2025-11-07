<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\context;

#[AsTask(description: 'Run a command with TTY enabled')]
function tty(): void
{
    run('echo "bar"', context: context()->withTty(true));
}
