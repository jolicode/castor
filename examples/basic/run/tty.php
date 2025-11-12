<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Run a command with TTY enabled')]
function tty(): void
{
    run('echo bar', context()->withTty(true));
}
