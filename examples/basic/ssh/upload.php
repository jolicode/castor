<?php

namespace ssh;

use Castor\Attribute\AsTask;

use function Castor\ssh_upload;

#[AsTask(description: 'Uploads a file to the remote server')]
function upload(): void
{
    ssh_upload(__FILE__, '/var/www/index.html', host: 'server-1.example.com', user: 'debian');
}
