<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Run a sub-process with environment variables and display information about it')]
function variables(): void
{
    $process = run('echo $foo', context()->withQuiet()->withEnvironment(['foo' => 'ba\'"`r']));

    io()->writeln('Output: ' . $process->getOutput());
    io()->writeln('Error output: ' . $process->getErrorOutput());
    io()->writeln('Exit code: ' . $process->getExitCode());
}
