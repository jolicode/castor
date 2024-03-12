<?php

namespace foo;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Prints foo')]
function foo(): void
{
    io()->writeln('foo');
}

#[AsTask(name: 'bar', namespace: 'foo', description: 'Echo foo bar')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    io()->writeln('Foo bar');
}
