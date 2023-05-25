<?php

namespace quiet;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'Executes something but does not output anything')]
function quiet()
{
    exec('ls -alh', quiet: true);
}
