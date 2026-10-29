<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;
use function CastorTest\Functions\shout;
use function CastorTest\Package\greet;

#[AsTask(description: 'Uses the functions of the packages, without importing them')]
function greet_loudly(): void
{
    io()->writeln(shout(greet()));
}
