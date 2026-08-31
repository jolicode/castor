<?php

namespace signal;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Traps SIGINT and forwards it to the running process')]
function trap(): void
{
    $pid = posix_getpid();

    io()->writeln('Running a process that sends a SIGINT to Castor, like a CTRL+C would do');

    $process = run(
        // "exec" makes the "sleep" process replace the shell, so it is the one
        // Castor forwards the signal to
        ['sh', '-c', "(sleep 1; kill -INT {$pid}) & exec sleep 10"],
        context: context()
            ->withTrappedSignals()
            ->withAllowFailure()
            ->withPty(false),
    );

    io()->writeln('The process has been stopped with the exit code ' . $process->getExitCode());
    io()->writeln('And Castor is still running!');
}
