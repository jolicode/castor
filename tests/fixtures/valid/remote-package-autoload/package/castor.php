<?php

namespace CastorTest\Package;

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(namespace: 'package', description: 'A task of the castor.php of the package')]
function check(): void
{
    io()->writeln(greet() . ', checked');
}
