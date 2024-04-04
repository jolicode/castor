<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh_download;
use function Castor\ssh_run;
use function Castor\ssh_upload;

#[AsTask(description: 'Lists content of /var/www directory on the remote server')]
function ls(): void
{
    ssh_run('ls -alh', host: 'server-1.example.com', user: 'debian', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}

#[AsTask(description: 'Connect to a remote server without specifying a user')]
function whoami(): void
{
    ssh_run('whoami', host: 'server-1.example.com', sshOptions: [
        'port' => 2222,
    ], path: '/var/www');
}

#[AsTask(description: 'Uploads a file to the remote server')]
function upload(): void
{
    ssh_upload('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}

#[AsTask(description: 'Downloads a file from the remote server')]
function download(): void
{
    ssh_download('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
