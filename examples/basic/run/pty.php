<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Run a command with PTY disabled')]
function pty(): void
{
    run('echo "bar"', context()->withPty(false));
}
