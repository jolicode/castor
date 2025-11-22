<?php

namespace usage;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(name: 'bar', namespace: 'foo', description: 'Configures the default name, namespace, and description')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    io()->writeln('Foo bar');
}
