<?php

namespace castor\phar;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Build phar for Linux system')]
function linux()
{
    run('vendor/bin/box compile -c box.linux-amd64.json', path: __DIR__);
}

#[AsTask(description: 'Build phar for MacOS system')]
function darwin()
{
    run('vendor/bin/box compile -c box.darwin-amd64.json', path: __DIR__);
}

#[AsTask(description: 'Build phar for Windows system')]
function windows()
{
    run('vendor/bin/box compile -c box.windows-amd64.json', path: __DIR__);
}

#[AsTask(description: 'Build phar for all systems')]
function build()
{
    parallel(fn () => linux(), fn () => darwin(), fn () => windows());
}
