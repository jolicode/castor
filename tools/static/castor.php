<?php

namespace castor\static;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build phar for Linux system')]
function linux()
{
    run('bin/castor compile ./tools/phar/build/castor.linux-amd64.phar --os=linux --arch=x86_64', timeout: 0);
}

#[AsTask(description: 'Build phar for all systems')]
function build()
{
    parallel(linux(...));
}
