<?php

namespace Castor\Example;

use Castor\Attribute\Task;

#[Task(description: 'A simple task that prints bar, but also executes foo')]
function bar()
{
    foo();

    echo "bar\n";
}
