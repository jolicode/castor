<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh;
use function Castor\ssh_upload;

#[AsTask(description: 'Lists content of /var/www directory on the remote server')]
function ls(): void
{
    // List content of /var/www directory on the remote server
    ssh('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}

#[AsTask(description: 'Upload a file on the remote server')]
function upload(): void
{
    // List content of /var/www directory on the remote server
    ssh_upload('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}

#[AsTask(description: 'Download a file from the remote server')]
function download(): void
{
    // List content of /var/www directory on the remote server
    ssh_upload('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
