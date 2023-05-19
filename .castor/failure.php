<?php

namespace failure;

use Castor\Attribute\Task;

use function Castor\exec;

#[Task(description: 'A simple task that fails')]
function failure()
{
    exec('i_do_not_exist');
}

#[Task(description: 'A simple task that fails')]
function allow_failure()
{
    exec('i_do_not_exist', allowFailure: true);
}
