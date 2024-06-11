<?php

namespace run;

use Castor\Attribute\AsTask;
use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\Console\Helper\ProcessHelper;

use function Castor\app;
use function Castor\capture;
use function Castor\context;
use function Castor\exit_code;
use function Castor\io;
use function Castor\output;
use function Castor\run;

#[AsTask(description: 'Run a sub-process and display information about it')]
function ls(): void
{
    if (OsHelper::isWindows()) {
        $process = run('dir');
    } else {
        $process = run('ls -alh && echo $foo', context: context()->withQuiet()->withEnvironment(['foo' => 'ba\'"`r']));
    }

    io()->writeln('Output:' . $process->getOutput());
    io()->writeln('Error output: ' . $process->getErrorOutput());
    io()->writeln('Exit code: ' . $process->getExitCode());
}

#[AsTask(description: 'Run a sub-process with environment variables and display information about it')]
function variables(): void
{
    $process = run('echo $foo', context: context()->withQuiet()->withEnvironment(['foo' => 'ba\'"`r']));

    io()->writeln('Output: ' . $process->getOutput());
    io()->writeln('Error output: ' . $process->getErrorOutput());
    io()->writeln('Exit code: ' . $process->getExitCode());
}

#[AsTask(description: 'Run a sub-process and display information about it, with capture() function')]
function whoami(): void
{
    // Note: we don't run `whoami` here, because it would break the tests suite
    // for each different users
    $whoami = capture('echo whoami');

    io()->writeln("Hello: {$whoami}");
}

#[AsTask(description: 'Run a sub-process and return its exit code, with get_exit_code() function')]
function testFile(): int
{
    return exit_code('test -f unknown-file');
}

#[AsTask(description: 'Run a command that will fail')]
function exception(): void
{
    if (!output()->isVerbose()) {
        output()->writeln('Re-run with -v, -vv, -vvv for different output.');
    }

    run('echo foo; echo bar>&2; exit 1', context: context()->withPty(false)->withQuiet());
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
