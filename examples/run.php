<?php

namespace run;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

use function Castor\run;

#[AsTask(description: 'Run a sub-process and display information about it')]
function run_ls()
{
    $process = run('ls -alh', quiet: true);

    echo "Output: \n" . $process->getOutput();
    echo "\nError output: \n" . $process->getErrorOutput();
    echo "\nExit code: " . $process->getExitCode();
    echo "\n";
}

#[AsTask(description: 'Run a sub-process and display information about it, with ProcessHelper')]
function run_with_process_helper(Application $application, OutputInterface $output)
{
    if (!$output->isVeryVerbose()) {
        $output->writeln('Re-run with -vv, -vvv to see the output of the process.');
    }
    /** @var ProcessHelper */
    $helper = $application->getHelperSet()->get('process');
    $helper->run($output, ['ls', '-alh']);
}
