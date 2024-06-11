<?php

namespace env;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Display environment variables')]
function env(): void
{
    run('echo \"$FOO\"', context: context()->withEnvironment([
        'FOO' => 'toto',
    ]));
}
