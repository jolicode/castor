<?php

namespace foo;

use Castor\Attribute\AsTask;

#[AsTask(description: 'A simple command that prints foo')]
function foo()
{
    echo "foo\n";
}
