<?php

namespace log;

use Castor\Attribute\AsTask;

use function Castor\log;
use function Castor\output;

#[AsTask(description: 'Logs some messages with different levels')]
function all_level(): void
{
    if (!output()->isVerbose()) {
        output()->writeln('Re-run with -v, -vv, -vvv for different output.');
    }

    $levels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    foreach ($levels as $level) {
        log("level: {$level}", $level);
    }
}
