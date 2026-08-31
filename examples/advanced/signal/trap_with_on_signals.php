<?php

namespace signal;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

if (!\defined('SIGINT')) {
    \define('SIGINT', 2);
}

#[AsTask(description: 'Combines a trapped signal and an "onSignals" handler', onSignals: [\SIGINT => 'signal\onSigInt'])]
function trap_with_on_signals(): void
{
    $pid = posix_getpid();

    // While the process runs, the trap wins: the SIGINT is forwarded to the
    // process and the "onSignals" handler of the task is not called
    $process = run(
        ['sh', '-c', "(sleep 1; kill -INT {$pid}) & exec sleep 10"],
        context: context()
            ->withTrappedSignals()
            ->withAllowFailure()
            ->withPty(false),
    );

    io()->writeln('The process has been stopped with the exit code ' . $process->getExitCode());

    // Once the process is finished, the trap is released and the "onSignals"
    // handler of the task gets the signals again
    posix_kill($pid, \SIGINT);
    usleep(100_000);

    io()->writeln('And Castor is still running!');
}

/**
 * @return false
 */
function onSigInt(int $signal): bool
{
    io()->writeln('SIGINT received by the task itself');

    return false;
}
