<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'A simple task that only output process result')]
function run()
{
    $process = exec('ls -alh', quiet: true);

    echo "Output: \n" . $process->getOutput();
    echo "\nError output: \n" . $process->getErrorOutput();
    echo "\nExit code: " . $process->getExitCode();
    echo "\n";
}
