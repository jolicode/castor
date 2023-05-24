<?php

namespace failure;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'A failing task not authorized to fail')]
function failure()
{
    exec('i_do_not_exist');
}

#[AsTask(description: 'A failing task authorized to fail')]
function allow_failure()
{
    exec('i_do_not_exist', allowFailure: true);
}
