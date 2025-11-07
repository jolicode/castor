<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;

#[AsTask(description: 'Run a sub-process and display information about it, with capture() function')]
function capture_(): void
{
    // Note: we don't run `whoami` here, because it would break the tests suite
    // for each different users
    $whoami = capture('echo whoami');

    io()->writeln("Hello: {$whoami}");
}
