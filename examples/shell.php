<?php

namespace shell;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Runs a bash')]
function bash()
{
    run('bash', tty: true);
}

#[AsTask(description: 'Runs a sh')]
function sh()
{
    run('sh', tty: true);
}
