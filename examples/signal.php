<?php

namespace signal;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Captures SIGUSR2 signal', onSignals: [\SIGUSR2 => 'signal\onSigUsr2'])]
function sigusr2(): void
{
    // This send SIGUSR2 to the current process
    posix_kill(posix_getpid(), \SIGUSR2);
}

function onSigUsr2(int $signal): int|false
{
    echo "SIGUSR2 received\n";

    return false;
}
