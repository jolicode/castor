<?php

namespace shell;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Runs a bash')]
function bash(): void
{
    run('bash', context: context()->withTty());
}

#[AsTask(description: 'Runs a sh')]
function sh(): void
{
    run('sh', context: context()->withTty());
}
