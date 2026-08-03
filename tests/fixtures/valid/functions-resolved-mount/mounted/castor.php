<?php

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'A task in the mounted application')]
function world(): void
{
    io()->writeln('Hello from mounted');
}
