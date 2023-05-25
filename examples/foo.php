<?php

namespace foo;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Prints foo')]
function foo()
{
    echo "foo\n";
}
