<?php

namespace log;

use Castor\Attribute\AsTask;

use function Castor\log;
use function Castor\output;

#[AsTask(description: 'Logs an "info" message')]
function info(): void
{
    if (!output()->isVeryVerbose()) {
        output()->writeln('Re-run with -vv, -vvv for different output.');
    }

    log('Hello, this is an "info" log message.', 'info');
}
