<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh_run;

#[AsTask(description: 'Lists content of /var/www directory on the remote server')]
function ls(): void
{
    ssh_run('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}
