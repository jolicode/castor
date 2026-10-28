<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;
use RemoteAutoload\Greeter;

use function Castor\io;

#[AsTask(description: 'Uses a class autoloaded by castor.composer.json')]
function hello(): void
{
    io()->writeln(new Greeter()->greet());
}
