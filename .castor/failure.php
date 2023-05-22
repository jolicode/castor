<?php

namespace failure;

use Castor\Attribute\Task;

use function Castor\exec;

#[Task(description: 'A failing task not authorized to fail')]
function failure()
{
    exec('i_do_not_exist');
}

#[Task(description: 'A failing task authorized to fail')]
function allow_failure()
{
    exec('i_do_not_exist', allowFailure: true);
}
