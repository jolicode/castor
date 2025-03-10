<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run_php;

#[AsTask(description: 'Run a phar in a sub process')]
function phar(): void
{
    run_php('examples/run.phar', ['a', 'list', 'of', 'arguments'], context: context()->withEnvironment([
        'CASTOR_MEMORY_LIMIT' => '16M',
    ]));
}
