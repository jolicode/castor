#!/usr/bin/env -S castor --castor-file
<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function shebangTask(): void
{
    io()->writeln('Hello from shebang task!');
}
