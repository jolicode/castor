<?php

namespace Castor\Runner;

use Castor\Context;
use Symfony\Component\Process\Process;

/**
 * PHP creates the PTY of a process without a size (0x0): the commands fall back
 * on 80 columns, and a full screen one (top, vim, ...) is unusable. This process
 * gives the PTY the size of the terminal of Castor first, through a shell which
 * then runs the command in its place: same PID, signals and exit code.
 *
 * Only the initial size: a resizing of the terminal does not reach the PTY.
 *
 * @internal
 */
final class SizedPtyProcess extends Process
{
    // /dev/tty is the terminal of Castor: the PTY, on the standard input, keeps
    // no size if it can not be read anymore (closed meanwhile, for example). The
    // errors are silenced before opening it, as the shell reports its failure
    private const SET_SIZE = 'castor_pty_size=$(stty size 2>/dev/null </dev/tty) && stty rows "${castor_pty_size% *}" cols "${castor_pty_size#* }" 2>/dev/null; unset castor_pty_size';

    private static ?bool $hasTerminal = null;

    private string $commandLine;

    /**
     * Without terminal (in a CI, for example), there is no size to give: the
     * command runs as is.
     */
    public static function hasTerminal(): bool
    {
        if (null === self::$hasTerminal) {
            $tty = @fopen('/dev/tty', 'r');
            self::$hasTerminal = false !== $tty;

            if (false !== $tty) {
                fclose($tty);
            }
        }

        return self::$hasTerminal;
    }

    /**
     * @param string|string[] $command
     */
    public static function create(string|array $command, Context $context): self
    {
        if (\is_array($command)) {
            $process = new self(['sh', '-c', self::SET_SIZE . '; exec "$@"', 'sh', ...$command], $context->workingDirectory, $context->environment, null, $context->timeout);
            $process->commandLine = new Process($command)->getCommandLine();
        } else {
            $process = self::fromShellCommandline(self::SET_SIZE . '; ' . $command, $context->workingDirectory, $context->environment, null, $context->timeout);
            $process->commandLine = $command;
        }

        return $process;
    }

    // The command as asked, without the shell which sizes the PTY
    public function getCommandLine(): string
    {
        return $this->commandLine;
    }
}
