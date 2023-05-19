<?php

use Castor\Attribute\Task;

use function Castor\exec;

#[Task(description: 'A simple task that run a bash')]
function bash()
{
    exec('bash', tty: true);
}
