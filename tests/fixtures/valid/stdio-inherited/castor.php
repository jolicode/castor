<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\parallel;
use function Castor\run;

#[AsTask]
function streams(): void
{
    $process = run(['php', '-r', <<<'PHP'
        echo 'stdout: ', stream_isatty(STDOUT) ? 'terminal' : 'not a terminal', "\n";
        fwrite(STDERR, 'stderr: ' . (stream_isatty(STDERR) ? 'terminal' : 'not a terminal') . "\n");
        PHP], context: context()->withStdioInherited());

    io()->writeln('Captured: "' . $process->getOutput() . '"');
}

#[AsTask]
function size(): void
{
    run(['stty', 'size'], context: context()->withStdioInherited());
}

#[AsTask]
function input(): void
{
    run(['php', '-r', <<<'PHP'
        echo trim(stream_get_contents(STDIN)), ' (stdout: ', stream_isatty(STDOUT) ? 'terminal' : 'not a terminal', ")\n";
        PHP], context: context()->withStdioInherited()->withInput("from the input\n"));
}

#[AsTask]
function quiet(): void
{
    io()->writeln('Captured: "' . capture('echo foo', context: context()->withStdioInherited()) . '"');
}

#[AsTask]
function callback(): void
{
    run('echo foo', context: context()->withStdioInherited(), callback: static function (string $type, string $bytes): void {
        io()->writeln('Callback: "' . trim($bytes) . '"');
    });
}

#[AsTask]
function parallel_job(): void
{
    parallel(job: static fn () => run('echo foo', context: context()->withStdioInherited()));
}

// Waits for a CTRL+C: the process dies from it
#[AsTask]
function interrupted(): void
{
    run('echo ready; exec sleep 10', context: context()->withStdioInherited()->withAllowFailure());

    io()->writeln('Next step');
}

// Waits for a CTRL+C: the process survives it, like a REPL
#[AsTask]
function survives(): void
{
    run('trap "echo caught; kill $!" INT; echo ready; sleep 10 & wait; echo done', context: context()->withStdioInherited());

    io()->writeln('Next step');
}

#[AsTask]
function exit_130(): void
{
    run('exit 130', context: context()->withStdioInherited()->withAllowFailure());

    io()->writeln('Next step');
}
