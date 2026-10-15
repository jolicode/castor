<?php

namespace Castor\Exception;

use Symfony\Component\Process\Process;

/**
 * Thrown when a process run by Castor is interrupted by a CTRL+C: the task
 * stops, even when the context allows the failures.
 */
class ProcessInterruptedException extends \RuntimeException
{
    public function __construct(
        public readonly Process $process,
        ?\Throwable $previous = null,
    ) {
        // 128 + SIGINT, the exit code of a shell interrupted by a CTRL+C
        parent::__construct(\sprintf('The command "%s" has been interrupted.', $process->getCommandLine()), 130, $previous);
    }
}
