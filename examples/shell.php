<?php

namespace shell;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'Runs a bash')]
function bash()
{
    exec('bash', tty: true);
}

#[AsTask(description: 'Runs a sh')]
function sh()
{
    exec('sh', tty: true);
}
