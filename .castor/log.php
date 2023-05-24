<?php

namespace log;

use Castor\Attribute\Task;

use function Castor\log;

#[Task(description: 'Logs an "info" message (needs "-vv" option).')]
function info(): void
{
    log('Hello, this is an "info" log message.', 'info');
}

#[Task(description: 'Logs an "error" message.')]
function error(): void
{
    log('Error!, this is an "error" log message.', 'error');
}

#[Task(description: 'Logs an "error" message.')]
function with_context(): void
{
    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}

#[Task(description: 'Logs some messages with different levels.')]
function all_level(): void
{
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
