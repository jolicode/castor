#!/usr/bin/env -S castor --castor-file
<?php

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function shebangTask(): void
{
    io()->writeln('Hello from shebang task!');
}
