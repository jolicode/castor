<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run a sub-process')]
function run_(): void
{
    run('examples/basic/run/my-script.sh');
    run(['grep', '-rni', 'todo', 'examples/']);
}
