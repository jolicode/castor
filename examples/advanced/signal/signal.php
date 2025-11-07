<?php

namespace signal;

use Castor\Attribute\AsTask;

use function Castor\io;

if (!\defined('SIGUSR2')) {
    \define('SIGUSR2', 12);
}

#[AsTask(description: 'Captures SIGUSR2 signal', onSignals: [\SIGUSR2 => 'signal\onSigUsr2'])]
function sigusr2(): void
{
    // This send SIGUSR2 to the current process
    posix_kill(posix_getpid(), \SIGUSR2);
}

/**
 * @return false
 */
function onSigUsr2(int $signal): bool
{
    io()->writeln('SIGUSR2 received');

    return false;
}
