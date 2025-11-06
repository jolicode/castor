<?php

namespace log;

use Castor\Attribute\AsTask;

use function Castor\log;

#[AsTask(description: 'Logs an "error" message')]
function error(): void
{
    log('Error!, this is an "error" log message.', 'error');
}
