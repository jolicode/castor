<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh;

#[AsTask(description: 'Runs a command on a remote server')]
function ls(): void
{
    // List content of /var/www directory on the remote server
    ssh('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
