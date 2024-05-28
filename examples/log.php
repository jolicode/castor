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

#[AsTask(description: 'Logs an "error" message')]
function error(): void
{
    log('Error!, this is an "error" log message.', 'error');
}

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
