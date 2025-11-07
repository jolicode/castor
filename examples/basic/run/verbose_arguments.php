<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'A failing task that will suggest to re-run with verbose arguments')]
function verbose_arguments(): void
{
    run('bash -c i_do_not_exist', context: context()->withVerboseArguments(['-x', '-e']));
}
