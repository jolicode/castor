<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh_run;

#[AsTask(description: 'Connect to a remote server without specifying a user')]
function whoami(): void
{
    ssh_run('whoami', host: 'server-1.example.com', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
