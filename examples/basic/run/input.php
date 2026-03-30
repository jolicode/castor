<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Run a sub-process with stdin input')]
function run_with_input(): void
{
    $result = run(
        ['cat'],
        context: context()->withInput("Hello from stdin!\n"),
    );
    io()->writeln('Output: ' . $result->getOutput());
}
