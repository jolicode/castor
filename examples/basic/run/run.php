<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run a sub-process')]
function run_(): void
{
    run('my-script.sh');
    run(['php', 'vendor/bin/phpunit', '--filter', 'MyTest']);
}
