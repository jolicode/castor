<?php

namespace quiet;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Executes something but does not output anything')]
function quiet(): void
{
    run('ls -alh', context: context()->withQuiet());
}
