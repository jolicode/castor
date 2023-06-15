<?php

namespace run;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Helper\ProcessHelper;

use function Castor\capture;
use function Castor\get_application;
use function Castor\get_output;
use function Castor\run;

#[AsTask(description: 'Run a sub-process and display information about it')]
function ls(): void
{
    $process = run('ls -alh', quiet: true);

    echo "Output: \n" . $process->getOutput();
    echo "\nError output: \n" . $process->getErrorOutput();
    echo "\nExit code: " . $process->getExitCode();
    echo "\n";
}

#[AsTask(description: 'Run a sub-process and display information about it, with capture() function')]
function whoami(): void
{
    // Note: we don't run `whoami` here, because it would break the tests suite
    // for each different users
    $whoami = capture('echo whoami');

    echo "Hello: {$whoami}\n";
}

#[AsTask(description: 'Run a sub-process and display information about it, with ProcessHelper')]
function with_process_helper(): void
{
    if (!get_output()->isVeryVerbose()) {
        get_output()->writeln('Re-run with -vv, -vvv to see the output of the process.');
    }
    /** @var ProcessHelper */
    $helper = get_application()->getHelperSet()->get('process');
    $helper->run(get_output(), ['ls', '-alh']);
}
