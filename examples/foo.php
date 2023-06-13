<?php

namespace foo;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Prints foo')]
function foo(): void
{
    echo "foo\n";
}
