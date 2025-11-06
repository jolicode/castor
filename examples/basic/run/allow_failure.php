<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'A failing task authorized to fail')]
function allow_failure(): void
{
    run('bash -c i_do_not_exist', context()->withAllowFailure());
}
