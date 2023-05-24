<?php

namespace quiet;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'A simple task that does not output anything')]
function quiet()
{
    exec('ls -alh', quiet: true);
}
