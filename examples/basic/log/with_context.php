<?php

namespace log;

use Castor\Attribute\AsTask;

use function Castor\log;
use function Castor\output;

#[AsTask(description: 'Logs an "error" message')]
function with_context(): void
{
    if (!output()->isVerbose()) {
        output()->writeln('Re-run with -v, -vv, -vvv for different output.');
    }

    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}
