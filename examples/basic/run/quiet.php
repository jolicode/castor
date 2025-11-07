<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Executes something but does not output anything')]
function quiet(): void
{
    $process = run('ls -alh', context()->withQuiet()); // will not print anything

    // If you want to get the output, you can still do it:
    // io()->writeln('Output:' . $process->getOutput());
}
