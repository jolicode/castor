<?php

namespace quiet;

use Castor\Attribute\Task;

use function Castor\exec;

#[Task(description: 'A simple task that does not output anything')]
function quiet()
{
    exec('ls -alh', quiet: true);
}
