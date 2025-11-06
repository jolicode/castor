<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Display environment variables')]
function env_override(): void
{
    run('echo \"$FOO\"', context()->withEnvironment([
        'FOO' => 'toto',
    ]));
}
