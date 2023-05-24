<?php

namespace bar;

use Castor\Attribute\AsTask;

use function foo\foo;

#[AsTask(description: 'A simple task that prints bar, but also executes foo')]
function bar()
{
    foo();

    echo "bar\n";
}
