<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\output;
use function Castor\run;

#[AsTask(description: 'Run a command that will fail')]
function exception(): void
{
    if (!output()->isVerbose()) {
        output()->writeln('Re-run with -v, -vv, -vvv for different output.');
    }

    run('echo foo; echo bar>&2;exit 1');
}
