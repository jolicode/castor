<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\ssh_run;

#[AsTask(description: 'Output in real-time ssh command output')]
function real_time_output(): void
{
    ssh_run(
        command: 'ls -alh',
        host: 'server-1.example.com',
        user: 'debian',
        callback: function ($type, $buffer): void {
            io()->writeln('REAL TIME OUTPUT> ' . $buffer);
        }
    );
}
