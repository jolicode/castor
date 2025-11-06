<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'A failing task not authorized to fail')]
function failure(): void
{
    run('bash -c i_do_not_exist');
}
