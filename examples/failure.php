<?php

namespace failure;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'A failing task not authorized to fail')]
function failure()
{
    run('i_do_not_exist', path: '/tmp');
}

#[AsTask(description: 'A failing task authorized to fail')]
function allow_failure()
{
    run('i_do_not_exist', allowFailure: true);
}
