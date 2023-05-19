<?php

use Castor\Attribute\Task;

#[Task(description: 'A simple task that does not output anything')]
function run()
{
    $process = \Castor\exec('ls -alh', quiet: true);

    echo "OUPUT: \n" . $process->getOutput();
    echo "\nERR: \n" . $process->getErrorOutput();
    echo "\nExit code: " .  $process->getExitCode();
    echo "\n";
}
