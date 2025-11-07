<?php

namespace run;

use Castor\Attribute\AsTask;
use JoliCode\PhpOsHelper\OsHelper;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Run a sub-process and display information about it')]
function ls(): void
{
    if (OsHelper::isWindows()) {
        $process = run('dir');
    } else {
        $process = run('ls -alh && echo $foo', context()->withQuiet()->withEnvironment(['foo' => 'ba\'"`r']));
    }

    io()->writeln('Output:' . $process->getOutput());
    io()->writeln('Error output: ' . $process->getErrorOutput());
    io()->writeln('Exit code: ' . $process->getExitCode());
}
