<?php

namespace run;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Helper\ProcessHelper;

use function Castor\app;
use function Castor\capture;
use function Castor\exit_code;
use function Castor\output;
use function Castor\run;

#[AsTask(description: 'Say Hi')]
function say_hi(): void
{
    run(['echo', 'hello']);
}

#[AsTask(description: 'Run a sub-process and display information about it')]
function ls(): void
{
    $process = run('ls -alh && echo $foo', quiet: true, environment: ['foo' => 'ba\'"`r']);

    echo "Output: \n" . $process->getOutput();
    echo "\nError output: \n" . $process->getErrorOutput();
    echo "\nExit code: " . $process->getExitCode();
    echo "\n";
}

#[AsTask(description: 'Run a sub-process with environment variables and display information about it')]
function variables(): void
{
    $process = run('echo $foo', quiet: true, environment: ['foo' => 'ba\'"`r']);

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

#[AsTask(description: 'Run a sub-process and return its exit code, with get_exit_code() function')]
function testFile(): int
{
    return exit_code('test -f unknown-file');
}

#[AsTask(description: 'Run a sub-process and display information about it, with ProcessHelper')]
function with_process_helper(): void
{
    if (!output()->isVeryVerbose()) {
        output()->writeln('Re-run with -vv, -vvv to see the output of the process.');
    }
    /** @var ProcessHelper */
    $helper = app()->getHelperSet()->get('process');
    $helper->run(output(), ['ls', '-alh']);
}
