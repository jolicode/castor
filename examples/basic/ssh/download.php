<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh_download;

#[AsTask(description: 'Downloads a file from the remote server')]
function download(): void
{
    ssh_download('/tmp/test.html', '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
