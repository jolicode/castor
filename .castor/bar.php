<?php

namespace bar;

use Castor\Attribute\Task;

use function foo\foo;

#[Task(description: 'A simple task that prints bar, but also executes foo')]
function bar()
{
    foo();

    echo "bar\n";
}
