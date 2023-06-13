<?php

namespace shell;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Runs a bash')]
function bash(): void
{
    run('bash', tty: true);
}

#[AsTask(description: 'Runs a sh')]
function sh(): void
{
    run('sh', tty: true);
}
