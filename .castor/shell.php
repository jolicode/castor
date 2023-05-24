<?php

namespace shell;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'A simple task that runs a bash')]
function bash()
{
    exec('bash', tty: true);
}

#[AsTask(description: 'A simple task that runs a sh')]
function sh()
{
    exec('sh', tty: true);
}
