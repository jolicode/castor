<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\context;

#[AsTask(description: 'Run a command with PTY disabled')]
function pty(): void
{
    run('echo "bar"', context()->withPty(false));
}
