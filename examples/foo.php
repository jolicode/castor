<?php

namespace foo;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Prints foo')]
function foo(): void
{
    echo "foo\n";
}

#[AsTask(name: 'bar', namespace: 'foo', description: 'Echo foo bar')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    echo 'Foo bar';
}
