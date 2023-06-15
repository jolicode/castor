<?php

namespace env;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Display environment variables')]
function env(): void
{
    run('echo \"$FOO\"', environment: [
        'FOO' => 'toto',
    ]);
}
