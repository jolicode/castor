<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Says hello')]
function hello(): void
{
    io()->writeln('Hello from the fixture');
}
