<?php

namespace quiet;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Executes something but does not output anything')]
function quiet()
{
    run('ls -alh', quiet: true);
}
