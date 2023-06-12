<?php

namespace log;

use Castor\Attribute\AsTask;

use function Castor\get_output;
use function Castor\log;

#[AsTask(description: 'Logs an "info" message')]
function info(): void
{
    if (!get_output()->isVeryVerbose()) {
        get_output()->writeln('Re-run with -vv, -vvv to different output.');
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
    if (!get_output()->isVerbose()) {
        get_output()->writeln('Re-run with -v, -vv, -vvv to different output.');
    }

    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}

#[AsTask(description: 'Logs some messages with different levels')]
function all_level(): void
{
    if (!get_output()->isVerbose()) {
        get_output()->writeln('Re-run with -v, -vv, -vvv to different output.');
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
